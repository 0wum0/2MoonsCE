<?php

declare(strict_types=1);

/**
 *	SmartMoons / 2Moons Community Edition (2MoonsCE)
 * 
 *	Based on the original 2Moons project:
 *	
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * Modernization, PHP 8.3/8.4 compatibility, Twig Migration (Smarty removed)
 * Refactoring and feature extensions:
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 * @link https://github.com/0wum0/2MoonsCE
 * @eMail info.browsergame@gmail.com
 * 
 * Licensed under the MIT License.
 * See LICENSE for details.
 * @visit http://makeit.uno/
 */

// @admin-migrated (Phase 10 — AbstractAdminPage)

class ShowQuickEditorPage extends AbstractAdminPage
{
	public function __construct()
	{
		parent::__construct('ShowQuickEditorPage');
	}

	protected function run(): void
	{
		global $USER, $LNG, $reslist, $resource;

		$db     = Database::get();
		$uni    = (int) Universe::getEmulated();
		$action = HTTP::_GP('action', '');
		$edit   = HTTP::_GP('edit', '');
		$id     = HTTP::_GP('id', 0);

		switch ($edit) {
			case 'planet':
				$DataIDs        = array_merge($reslist['fleet'], $reslist['build'], $reslist['defense']);
				$selectCols     = implode(', ', array_map(fn($ID) => '`' . $resource[$ID] . '`', $DataIDs));
				$PlanetData     = $db->selectSingle(
					"SELECT {$selectCols}, `name`, `id_owner`, `planet_type`, `galaxy`, `system`, `planet`,
					        `destruyed`, `diameter`, `field_current`, `field_max`, `temp_min`, `temp_max`,
					        `metal`, `crystal`, `deuterium`
					 FROM %%PLANETS%% WHERE `id` = :id;",
					[':id' => (int) $id]
				);

				if ($action === 'send') {
					$setClauses = [];
					$params     = [':id' => (int) $id, ':uni' => $uni];
					$Fields     = (int) $PlanetData['field_current'];

					foreach ($DataIDs as $ID) {
						$level = min(max(0, (int) round(HTTP::_GP($resource[$ID], 0.0))),
							in_array($ID, $reslist['build']) ? 255 : PHP_INT_MAX);
						if (in_array($ID, $reslist['allow'][$PlanetData['planet_type']])) {
							$Fields += $level - (int) $PlanetData[$resource[$ID]];
						}
						$setClauses[]        = '`' . $resource[$ID] . '` = :col_' . $ID;
						$params[':col_' . $ID] = $level;
					}
					$setClauses[] = '`metal`         = :metal';
					$setClauses[] = '`crystal`       = :crystal';
					$setClauses[] = '`deuterium`     = :deuterium';
					$setClauses[] = '`field_current` = :field_current';
					$setClauses[] = '`field_max`     = :field_max';
					$setClauses[] = '`name`          = :name';
					$setClauses[] = '`eco_hash`      = \'\'';
					$params[':metal']         = max(0, (int) round(HTTP::_GP('metal', 0.0)));
					$params[':crystal']       = max(0, (int) round(HTTP::_GP('crystal', 0.0)));
					$params[':deuterium']     = max(0, (int) round(HTTP::_GP('deuterium', 0.0)));
					$params[':field_current'] = $Fields;
					$params[':field_max']     = HTTP::_GP('field_max', 0);
					$params[':name']          = HTTP::_GP('name', '', UTF8_SUPPORT);

					$db->update("UPDATE %%PLANETS%% SET " . implode(', ', $setClauses) . " WHERE `id` = :id AND `universe` = :uni;", $params);

					$old = [];
					$new = [];
					foreach (array_merge($DataIDs, $reslist['resstype'][1]) as $IDs) {
						$old[$IDs] = $PlanetData[$resource[$IDs]];
						$new[$IDs] = max(0, (int) round(HTTP::_GP($resource[$IDs], 0.0)));
					}
					$old['field_max'] = $PlanetData['field_max'];
					$new['field_max'] = HTTP::_GP('field_max', 0);

					$LOG = new Log(2);
					$LOG->target = $id;
					$LOG->old    = $old;
					$LOG->new    = $new;
					$LOG->save();

					exit(sprintf($LNG['qe_edit_planet_sucess'], $PlanetData['name'], $PlanetData['galaxy'], $PlanetData['system'], $PlanetData['planet']));
				}

				$UserInfo = $db->selectSingle(
					"SELECT `username` FROM %%USERS%% WHERE `id` = :id AND `universe` = :uni;",
					[':id' => (int) $PlanetData['id_owner'], ':uni' => $uni]
				);

				$build = $defense = $fleet = [];
				foreach ($reslist['allow'][$PlanetData['planet_type']] as $ID) {
					$build[] = ['type' => $resource[$ID], 'name' => $LNG['tech'][$ID],
					            'count' => pretty_number($PlanetData[$resource[$ID]]), 'input' => $PlanetData[$resource[$ID]]];
				}
				foreach ($reslist['fleet'] as $ID) {
					$fleet[] = ['type' => $resource[$ID], 'name' => $LNG['tech'][$ID],
					            'count' => pretty_number($PlanetData[$resource[$ID]]), 'input' => $PlanetData[$resource[$ID]]];
				}
				foreach ($reslist['defense'] as $ID) {
					$defense[] = ['type' => $resource[$ID], 'name' => $LNG['tech'][$ID],
					              'count' => pretty_number($PlanetData[$resource[$ID]]), 'input' => $PlanetData[$resource[$ID]]];
				}

				$this->assign([
					'build'       => $build,
					'fleet'       => $fleet,
					'defense'     => $defense,
					'id'          => $id,
					'ownerid'     => $PlanetData['id_owner'],
					'ownername'   => $UserInfo['username'] ?? '',
					'name'        => $PlanetData['name'],
					'galaxy'      => $PlanetData['galaxy'],
					'system'      => $PlanetData['system'],
					'planet'      => $PlanetData['planet'],
					'field_min'   => $PlanetData['field_current'],
					'field_max'   => $PlanetData['field_max'],
					'temp_min'    => $PlanetData['temp_min'],
					'temp_max'    => $PlanetData['temp_max'],
					'metal'       => floatToString($PlanetData['metal']),
					'crystal'     => floatToString($PlanetData['crystal']),
					'deuterium'   => floatToString($PlanetData['deuterium']),
					'metal_c'     => pretty_number($PlanetData['metal']),
					'crystal_c'   => pretty_number($PlanetData['crystal']),
					'deuterium_c' => pretty_number($PlanetData['deuterium']),
				]);
				$this->show('QuickEditorPlanet.twig');
				break;

			case 'player':
				$DataIDs    = array_merge($reslist['tech'], $reslist['officier']);
				$selectCols = implode(', ', array_map(fn($ID) => '`' . $resource[$ID] . '`', $DataIDs));
				$UserData   = $db->selectSingle(
					"SELECT {$selectCols}, `username`, `authlevel`, `galaxy`, `system`, `planet`,
					        `id_planet`, `darkmatter`, `authattack`
					 FROM %%USERS%% WHERE `id` = :id;",
					[':id' => (int) $id]
				);
				$ChangePW = ((int) $USER['id'] === ROOT_USER) || ($id !== ROOT_USER && $USER['authlevel'] > $UserData['authlevel']);

				if ($action === 'send') {
					$setClauses = [];
					$params     = [':id' => (int) $id, ':uni' => $uni];

					foreach ($DataIDs as $ID) {
						$setClauses[]          = '`' . $resource[$ID] . '` = :col_' . $ID;
						$params[':col_' . $ID] = min(abs(HTTP::_GP($resource[$ID], 0)), 255);
					}
					$setClauses[]       = '`darkmatter` = :darkmatter';
					$params[':darkmatter'] = max(HTTP::_GP('darkmatter', 0), 0);

					if (!empty($_POST['password']) && $ChangePW) {
						$setClauses[]      = '`password` = :pw';
						$params[':pw']     = PlayerUtil::cryptPassword(HTTP::_GP('password', '', true));
					}

					$newUsername = HTTP::_GP('name', '', UTF8_SUPPORT);
					$authattack  = ($UserData['authlevel'] !== AUTH_USR && HTTP::_GP('authattack', '') === 'on')
						? $UserData['authlevel'] : 0;

					$setClauses[]         = '`username`   = :username';
					$setClauses[]         = '`authattack` = :authattack';
					$params[':username']  = $newUsername;
					$params[':authattack'] = $authattack;

					$db->update("UPDATE %%USERS%% SET " . implode(', ', $setClauses) . " WHERE `id` = :id AND `universe` = :uni;", $params);

					$multi    = HTTP::_GP('multi', 0);
					$oldMulti = (int) $db->selectSingle("SELECT COUNT(*) AS cnt FROM %%MULTI%% WHERE userID = :id;", [':id' => (int) $id], 'cnt');

					if ($oldMulti !== $multi) {
						if ($multi === 0) {
							$db->delete("DELETE FROM %%MULTI%% WHERE userID = :id;", [':id' => (int) $id]);
						} elseif ($multi === 1) {
							$db->insert("INSERT INTO %%MULTI%% SET userID = :id;", [':id' => (int) $id]);
						}
					}

					$old = [];
					$new = [];
					foreach ($DataIDs as $IDs) {
						$old[$IDs] = $UserData[$resource[$IDs]];
						$new[$IDs] = abs(HTTP::_GP($resource[$IDs], 0));
					}
					$old[921]          = $UserData[$resource[921]];
					$new[921]          = abs(HTTP::_GP($resource[921], 0));
					$old['username']   = $UserData['username'];
					$new['username']   = $newUsername;
					$old['authattack'] = $UserData['authattack'];
					$new['authattack'] = $authattack;
					$old['multi']      = $oldMulti;
					$new['multi']      = $multi;

					$LOG = new Log(1);
					$LOG->target = $id;
					$LOG->old    = $old;
					$LOG->new    = $new;
					$LOG->save();

					exit(sprintf($LNG['qe_edit_player_sucess'], $UserData['username'], $id));
				}

				$PlanetInfo = $db->selectSingle(
					"SELECT `name` FROM %%PLANETS%% WHERE `id` = :id AND `universe` = :uni;",
					[':id' => (int) $UserData['id_planet'], ':uni' => $uni]
				);
				$multiCount = (int) $db->selectSingle("SELECT COUNT(*) AS cnt FROM %%MULTI%% WHERE userID = :id;", [':id' => (int) $id], 'cnt');

				$tech = $officier = [];
				foreach ($reslist['tech'] as $ID) {
					$tech[] = ['type' => $resource[$ID], 'name' => $LNG['tech'][$ID],
					           'count' => pretty_number($UserData[$resource[$ID]]), 'input' => $UserData[$resource[$ID]]];
				}
				foreach ($reslist['officier'] as $ID) {
					$officier[] = ['type' => $resource[$ID], 'name' => $LNG['tech'][$ID],
					               'count' => pretty_number($UserData[$resource[$ID]]), 'input' => $UserData[$resource[$ID]]];
				}

				$this->assign([
					'tech'        => $tech,
					'officier'    => $officier,
					'id'          => $id,
					'planetid'    => $UserData['id_planet'],
					'planetname'  => $PlanetInfo['name'] ?? '',
					'name'        => $UserData['username'],
					'galaxy'      => $UserData['galaxy'],
					'system'      => $UserData['system'],
					'planet'      => $UserData['planet'],
					'authlevl'    => $UserData['authlevel'],
					'authattack'  => $UserData['authattack'],
					'multi'       => $multiCount,
					'ChangePW'    => $ChangePW,
					'yesorno'     => [1 => $LNG['one_is_yes_1'], 0 => $LNG['one_is_yes_0']],
					'darkmatter'  => floatToString($UserData['darkmatter']),
					'darkmatter_c'=> pretty_number($UserData['darkmatter']),
				]);
				$this->show('QuickEditorUser.twig');
				break;
		}
	}
}