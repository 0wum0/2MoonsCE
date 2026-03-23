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

# Actions not logged: Planet-Edit, Alliance-Edit 

// @admin-migrated (Phase 10 — AbstractAdminPage)

class ShowAccountEditorPage extends AbstractAdminPage
{
	public function __construct()
	{
		parent::__construct('ShowAccountEditorPage');
	}

	protected function run(): void
	{
		global $LNG, $reslist, $resource;

		$db  = Database::get();
		$uni = (int) Universe::getEmulated();
		$edit = $_GET['edit'] ?? '';

		switch ($edit) {
			case 'resources':
				$id      = HTTP::_GP('id', 0);
				$id_dark = HTTP::_GP('id_dark', 0);
				$metal   = max(0, (int) round(HTTP::_GP('metal', 0.0)));
				$cristal = max(0, (int) round(HTTP::_GP('cristal', 0.0)));
				$deut    = max(0, (int) round(HTTP::_GP('deut', 0.0)));
				$dark    = HTTP::_GP('dark', 0);

				if (!empty($_POST)) {
					$before = $before_dm = $after = $after_dm = [];
					if (!empty($id))
						$before = $db->selectSingle("SELECT `metal`,`crystal`,`deuterium`,`universe` FROM %%PLANETS%% WHERE `id` = :id;", [':id' => (int)$id]);
					if (!empty($id_dark))
						$before_dm = $db->selectSingle("SELECT `darkmatter`,`universe` FROM %%USERS%% WHERE `id` = :id;", [':id' => (int)$id_dark]);

					$op = !empty($_POST['add']) ? '+' : '-';
					if (!empty($id)) {
						$db->update("UPDATE %%PLANETS%% SET `metal` = `metal` {$op} :m, `crystal` = `crystal` {$op} :c, `deuterium` = `deuterium` {$op} :d WHERE `id` = :id AND `universe` = :uni;",
							[':m' => $metal, ':c' => $cristal, ':d' => $deut, ':id' => (int)$id, ':uni' => $uni]);
						$s = ($op === '+') ? 1 : -1;
						$after = ['metal' => $before['metal'] + $s * $metal, 'crystal' => $before['crystal'] + $s * $cristal, 'deuterium' => $before['deuterium'] + $s * $deut];
						$LOG = new Log(2); $LOG->target = $id; $LOG->universe = $before['universe']; $LOG->old = $before; $LOG->new = $after; $LOG->save();
					}
					if (!empty($id_dark)) {
						$db->update("UPDATE %%USERS%% SET `darkmatter` = `darkmatter` {$op} :dk WHERE `id` = :id AND `universe` = :uni;",
							[':dk' => $dark, ':id' => (int)$id_dark, ':uni' => $uni]);
						$s = ($op === '+') ? 1 : -1;
						$after_dm = ['darkmatter' => $before_dm['darkmatter'] + $s * $dark];
						$LOG = new Log(1); $LOG->target = $id_dark; $LOG->universe = $before_dm['universe']; $LOG->old = $before_dm; $LOG->new = $after_dm; $LOG->save();
					}
					$this->message(!empty($_POST['add']) ? $LNG['ad_add_res_sucess'] : $LNG['ad_delete_res_sucess'], '?page=accounteditor&edit=resources');
					return;
				}
				$this->show('AccountEditorPageResources.twig');
				break;

			case 'ships':
				if (!empty($_POST)) {
					$pid     = HTTP::_GP('id', 0);
					$before1 = $db->selectSingle("SELECT * FROM %%PLANETS%% WHERE `id` = :id;", [':id' => (int)$pid]);
					$before  = $after = [];
					foreach ($reslist['fleet'] as $ID) { $before[$ID] = $before1[$resource[$ID]]; }
					$op = !empty($_POST['add']) ? '+' : '-';
					$QryUpdate = [];
					foreach ($reslist['fleet'] as $ID) {
						$val = max(0, (int) round(HTTP::_GP($resource[$ID], 0.0)));
						$QryUpdate[] = '`' . $resource[$ID] . '` = `' . $resource[$ID] . "` {$op} {$val}";
						$after[$ID]  = $op === '+' ? $before[$ID] + $val : max(0, $before[$ID] - $val);
					}
					$db->nativeQuery("UPDATE %%PLANETS%% SET `eco_hash` = '', " . implode(', ', $QryUpdate) . " WHERE `id` = {$pid} AND `universe` = {$uni};");
					$LOG = new Log(2); $LOG->target = $pid; $LOG->universe = $before1['universe']; $LOG->old = $before; $LOG->new = $after; $LOG->save();
					$this->message(!empty($_POST['add']) ? $LNG['ad_add_ships_sucess'] : $LNG['ad_delete_ships_sucess'], '?page=accounteditor&edit=ships');
					return;
				}
				$INPUT = [];
				foreach ($reslist['fleet'] as $ID) { $INPUT[$ID] = ['type' => $resource[$ID]]; }
				$this->assign(['inputlist' => $INPUT]);
				$this->show('AccountEditorPageShips.twig');
				break;

			case 'defenses':
				if (!empty($_POST)) {
					$pid     = HTTP::_GP('id', 0);
					$before1 = $db->selectSingle("SELECT * FROM %%PLANETS%% WHERE `id` = :id;", [':id' => (int)$pid]);
					$before  = $after = [];
					foreach ($reslist['defense'] as $ID) { $before[$ID] = $before1[$resource[$ID]]; }
					$op = !empty($_POST['add']) ? '+' : '-';
					$QryUpdate = [];
					foreach ($reslist['defense'] as $ID) {
						$val = max(0, (int) round(HTTP::_GP($resource[$ID], 0.0)));
						$QryUpdate[] = '`' . $resource[$ID] . '` = `' . $resource[$ID] . "` {$op} {$val}";
						$after[$ID]  = $op === '+' ? $before[$ID] + $val : max(0, $before[$ID] - $val);
					}
					$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $QryUpdate) . " WHERE `id` = {$pid} AND `universe` = {$uni};");
					$LOG = new Log(2); $LOG->target = $pid; $LOG->universe = $before1['universe']; $LOG->old = $before; $LOG->new = $after; $LOG->save();
					$this->message(!empty($_POST['add']) ? $LNG['ad_add_defenses_success'] : $LNG['ad_delete_defenses_success'], '?page=accounteditor&edit=defenses');
					return;
				}
				$INPUT = [];
				foreach ($reslist['defense'] as $ID) { $INPUT[$ID] = ['type' => $resource[$ID]]; }
				$this->assign(['inputlist' => $INPUT]);
				$this->show('AccountEditorPageDefenses.twig');
				break;

			case 'buildings':
				if (!empty($_POST)) {
					$pid        = HTTP::_GP('id', 0);
					$PlanetData = $db->selectSingle("SELECT * FROM %%PLANETS%% WHERE `id` = :id;", [':id' => (int)$pid]);
					if (empty($PlanetData)) {
						$this->message($LNG['ad_add_not_exist'], '?page=accounteditor&edit=buildings');
						return;
					}
					$before = $after = [];
					foreach ($reslist['allow'][$PlanetData['planet_type']] as $ID) { $before[$ID] = $PlanetData[$resource[$ID]]; }
					$op = !empty($_POST['add']) ? '+' : '-';
					$QryUpdate = [];
					$Fields = 0;
					foreach ($reslist['allow'][$PlanetData['planet_type']] as $ID) {
						$Count = max(0, (int) round(HTTP::_GP($resource[$ID], 0.0)));
						$QryUpdate[] = '`' . $resource[$ID] . '` = `' . $resource[$ID] . "` {$op} {$Count}";
						$after[$ID]  = $op === '+' ? $before[$ID] + $Count : max(0, $before[$ID] - $Count);
						$Fields += $Count;
					}
					$db->nativeQuery("UPDATE %%PLANETS%% SET `eco_hash` = '', " . implode(', ', $QryUpdate) . ", `field_current` = `field_current` {$op} {$Fields} WHERE `id` = {$pid} AND `universe` = {$uni};");
					$LOG = new Log(2); $LOG->target = $pid; $LOG->universe = $uni; $LOG->old = $before; $LOG->new = $after; $LOG->save();
					$this->message(!empty($_POST['add']) ? $LNG['ad_add_build_success'] : $LNG['ad_delete_build_success'], '?page=accounteditor&edit=buildings');
					return;
				}
				$INPUT = [];
				foreach ($reslist['build'] as $ID) { $INPUT[$ID] = ['type' => $resource[$ID]]; }
				$this->assign(['inputlist' => $INPUT]);
				$this->show('AccountEditorPageBuilds.twig');
				break;

			case 'researchs':
				if (!empty($_POST)) {
					$uid     = HTTP::_GP('id', 0);
					$before1 = $db->selectSingle("SELECT * FROM %%USERS%% WHERE `id` = :id;", [':id' => (int)$uid]);
					$before  = $after = [];
					foreach ($reslist['tech'] as $ID) { $before[$ID] = $before1[$resource[$ID]]; }
					$op = !empty($_POST['add']) ? '+' : '-';
					$QryUpdate = [];
					foreach ($reslist['tech'] as $ID) {
						$val = max(0, (int) round(HTTP::_GP($resource[$ID], 0.0)));
						$QryUpdate[] = '`' . $resource[$ID] . '` = `' . $resource[$ID] . "` {$op} {$val}";
						$after[$ID]  = $op === '+' ? $before[$ID] + $val : max(0, $before[$ID] - $val);
					}
					$db->nativeQuery("UPDATE %%USERS%% SET " . implode(', ', $QryUpdate) . " WHERE `id` = {$uid} AND `universe` = {$uni};");
					$LOG = new Log(1); $LOG->target = $uid; $LOG->universe = $before1['universe']; $LOG->old = $before; $LOG->new = $after; $LOG->save();
					$this->message(!empty($_POST['add']) ? $LNG['ad_add_tech_success'] : $LNG['ad_delete_tech_success'], '?page=accounteditor&edit=researchs');
					return;
				}
				$INPUT = [];
				foreach ($reslist['tech'] as $ID) { $INPUT[$ID] = ['type' => $resource[$ID]]; }
				$this->assign(['inputlist' => $INPUT]);
				$this->show('AccountEditorPageResearch.twig');
				break;

			case 'personal':
				if (!empty($_POST)) {
					$id       = HTTP::_GP('id', 0);
					$username = HTTP::_GP('username', '', UTF8_SUPPORT);
					$password = HTTP::_GP('password', '', true);
					$email    = HTTP::_GP('email', '');
					$email_2  = HTTP::_GP('email_2', '');
					$vacation = HTTP::_GP('vacation', '');

					$before = $db->selectSingle("SELECT `username`,`email`,`email_2`,`password`,`urlaubs_modus`,`urlaubs_until`,`universe` FROM %%USERS%% WHERE `id` = :id;", [':id' => (int)$id]);
					$after  = [];

					$setClauses = [];
					$params     = [':id' => (int)$id, ':uni' => $uni];

					if (!empty($username) && $id !== ROOT_USER) {
						$setClauses[] = '`username` = :username';
						$params[':username'] = $username;
						$after['username'] = $username;
					}
					if (!empty($email) && $id !== ROOT_USER) {
						$setClauses[] = '`email` = :email';
						$params[':email'] = $email;
						$after['email'] = $email;
					}
					if (!empty($email_2) && $id !== ROOT_USER) {
						$setClauses[] = '`email_2` = :email2';
						$params[':email2'] = $email_2;
						$after['email_2'] = $email_2;
					}
					if (!empty($password) && $id !== ROOT_USER) {
						$hashed = PlayerUtil::cryptPassword($password);
						$setClauses[] = '`password` = :pw';
						$params[':pw'] = $hashed;
						$after['password'] = ($hashed !== $before['password']) ? 'CHANGED' : '';
					}
					$before['password'] = '';

					$Answer  = 0;
					$TimeAns = 0;
					if ($vacation === 'yes') {
						$Answer  = 1;
						$TimeAns = TIMESTAMP + (int)($_POST['d'] ?? 0) * 86400 + (int)($_POST['h'] ?? 0) * 3600 + (int)($_POST['m'] ?? 0) * 60 + (int)($_POST['s'] ?? 0);
						$after['urlaubs_modus'] = 1;
						$after['urlaubs_until'] = $TimeAns;
					}
					$setClauses[] = '`urlaubs_modus` = :vm';
					$setClauses[] = '`urlaubs_until` = :vu';
					$params[':vm'] = $Answer;
					$params[':vu'] = $TimeAns;

					if (!empty($setClauses)) {
						$db->update("UPDATE %%USERS%% SET " . implode(', ', $setClauses) . " WHERE `id` = :id AND `universe` = :uni;", $params);
					}

					$LOG = new Log(1); $LOG->target = $id; $LOG->universe = $before['universe']; $LOG->old = $before; $LOG->new = $after; $LOG->save();
					$this->message($LNG['ad_personal_succes'], '?page=accounteditor&edit=personal');
					return;
				}
				$this->assign(['Selector' => ['' => $LNG['select_option'], 'yes' => $LNG['one_is_no_1'], 'no' => $LNG['one_is_no_0']]]);
				$this->show('AccountEditorPagePersonal.twig');
				break;

			case 'officiers':
				if (!empty($_POST)) {
					$uid     = HTTP::_GP('id', 0);
					$before1 = $db->selectSingle("SELECT * FROM %%USERS%% WHERE `id` = :id;", [':id' => (int)$uid]);
					$before  = $after = [];
					foreach ($reslist['officier'] as $ID) { $before[$ID] = $before1[$resource[$ID]]; }
					$op = !empty($_POST['add']) ? '+' : '-';
					$QryUpdate = [];
					foreach ($reslist['officier'] as $ID) {
						$val = max(0, (int) round(HTTP::_GP($resource[$ID], 0.0)));
						$QryUpdate[] = '`' . $resource[$ID] . '` = `' . $resource[$ID] . "` {$op} {$val}";
						$after[$ID]  = $op === '+' ? $before[$ID] + $val : max(0, $before[$ID] - $val);
					}
					$db->nativeQuery("UPDATE %%USERS%% SET " . implode(', ', $QryUpdate) . " WHERE `id` = {$uid} AND `universe` = {$uni};");
					$LOG = new Log(1); $LOG->target = $uid; $LOG->universe = $before1['universe']; $LOG->old = $before; $LOG->new = $after; $LOG->save();
					$this->message(!empty($_POST['add']) ? $LNG['ad_add_offi_success'] : $LNG['ad_delete_offi_success'], '?page=accounteditor&edit=officiers');
					return;
				}
				$INPUT = [];
				foreach ($reslist['officier'] as $ID) { $INPUT[$ID] = ['type' => $resource[$ID]]; }
				$this->assign(['inputlist' => $INPUT]);
				$this->show('AccountEditorPageOfficiers.twig');
				break;

			case 'planets':
				if (!empty($_POST)) {
					$id          = HTTP::_GP('id', 0);
					$name        = HTTP::_GP('name', '', UTF8_SUPPORT);
					$diameter    = HTTP::_GP('diameter', 0);
					$fields      = HTTP::_GP('fields', 0);
					$buildings   = HTTP::_GP('0_buildings', '');
					$ships       = HTTP::_GP('0_ships', '');
					$defenses    = HTTP::_GP('0_defenses', '');
					$c_hangar    = HTTP::_GP('0_c_hangar', '');
					$c_buildings = HTTP::_GP('0_c_buildings', '');
					$change_pos  = HTTP::_GP('change_position', '');
					$galaxy      = HTTP::_GP('g', 0);
					$system      = HTTP::_GP('s', 0);
					$planet      = HTTP::_GP('p', 0);

					if (!empty($name))
						$db->update("UPDATE %%PLANETS%% SET `name` = :n WHERE `id` = :id AND `universe` = :uni;", [':n' => $name, ':id' => (int)$id, ':uni' => $uni]);

					if ($buildings === 'on') {
						$BUILD = array_map(fn($ID) => '`' . $resource[$ID] . '` = 0', $reslist['build']);
						$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $BUILD) . " WHERE `id` = {$id} AND `universe` = {$uni};");
					}
					if ($ships === 'on') {
						$SHIPS = array_map(fn($ID) => '`' . $resource[$ID] . '` = 0', $reslist['fleet']);
						$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $SHIPS) . " WHERE `id` = {$id} AND `universe` = {$uni};");
					}
					if ($defenses === 'on') {
						$DEFS = array_map(fn($ID) => '`' . $resource[$ID] . '` = 0', $reslist['defense']);
						$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $DEFS) . " WHERE `id` = {$id} AND `universe` = {$uni};");
					}
					if ($c_hangar === 'on')
						$db->update("UPDATE %%PLANETS%% SET `b_hangar` = 0, `b_hangar_plus` = 0, `b_hangar_id` = '' WHERE `id` = :id AND `universe` = :uni;", [':id' => (int)$id, ':uni' => $uni]);
					if ($c_buildings === 'on')
						$db->update("UPDATE %%PLANETS%% SET `b_building` = 0, `b_building_id` = '' WHERE `id` = :id AND `universe` = :uni;", [':id' => (int)$id, ':uni' => $uni]);
					if (!empty($diameter))
						$db->update("UPDATE %%PLANETS%% SET `diameter` = :d WHERE `id` = :id AND `universe` = :uni;", [':d' => (int)$diameter, ':id' => (int)$id, ':uni' => $uni]);
					if (!empty($fields))
						$db->update("UPDATE %%PLANETS%% SET `field_max` = :f WHERE `id` = :id AND `universe` = :uni;", [':f' => (int)$fields, ':id' => (int)$id, ':uni' => $uni]);

					$cfg = Config::get($uni);
					if ($change_pos === 'on' && $galaxy > 0 && $system > 0 && $planet > 0
						&& $galaxy <= $cfg->max_galaxy && $system <= $cfg->max_system && $planet <= $cfg->max_planets) {
						$P = $db->selectSingle("SELECT galaxy,system,planet,planet_type FROM %%PLANETS%% WHERE `id` = :id AND `universe` = :uni;", [':id' => (int)$id, ':uni' => $uni]);
						if ((int)$P['planet_type'] === 1) {
							if (PlayerUtil::checkPosition($uni, $galaxy, $system, $planet, $P['planet_type'])) {
								$this->message($LNG['ad_pla_error_planets3'], '?page=accounteditor&edit=planets'); return;
							}
							$db->update("UPDATE %%PLANETS%% SET `galaxy` = :g, `system` = :s, `planet` = :p WHERE `id` = :id AND `universe` = :uni;",
								[':g' => $galaxy, ':s' => $system, ':p' => $planet, ':id' => (int)$id, ':uni' => $uni]);
						} else {
							if (PlayerUtil::checkPosition($uni, $galaxy, $system, $planet, $P['planet_type'])) {
								$this->message($LNG['ad_pla_error_planets5'], '?page=accounteditor&edit=planets'); return;
							}
							$Target = $db->selectSingle("SELECT id_luna FROM %%PLANETS%% WHERE `galaxy` = :g AND `system` = :s AND `planet` = :p AND `planet_type` = 1;",
								[':g' => $galaxy, ':s' => $system, ':p' => $planet]);
							if (!empty($Target['id_luna'])) {
								$this->message($LNG['ad_pla_error_planets4'], '?page=accounteditor&edit=planets'); return;
							}
							$db->update("UPDATE %%PLANETS%% SET `id_luna` = 0 WHERE `galaxy` = :g AND `system` = :s AND `planet` = :p AND `planet_type` = 1;",
								[':g' => (int)$P['galaxy'], ':s' => (int)$P['system'], ':p' => (int)$P['planet']]);
							$db->update("UPDATE %%PLANETS%% SET `id_luna` = :mid WHERE `galaxy` = :g AND `system` = :s AND `planet` = :p AND `planet_type` = 1;",
								[':mid' => (int)$id, ':g' => $galaxy, ':s' => $system, ':p' => $planet]);
							$QMOON2 = $db->selectSingle("SELECT id_owner FROM %%PLANETS%% WHERE `galaxy` = :g AND `system` = :s AND `planet` = :p;",
								[':g' => $galaxy, ':s' => $system, ':p' => $planet]);
							$db->update("UPDATE %%PLANETS%% SET `galaxy` = :g, `system` = :s, `planet` = :p, `id_owner` = :own WHERE `id` = :id AND `universe` = :uni AND `planet_type` = 3;",
								[':g' => $galaxy, ':s' => $system, ':p' => $planet, ':own' => (int)$QMOON2['id_owner'], ':id' => (int)$id, ':uni' => $uni]);
						}
					}
					$this->message($LNG['ad_pla_succes'], '?page=accounteditor&edit=planets');
					return;
				}
				$this->show('AccountEditorPagePlanets.twig');
				break;

			case 'alliances':
				if (!empty($_POST)) {
					$id          = HTTP::_GP('id', 0);
					$name        = HTTP::_GP('name', '', UTF8_SUPPORT);
					$changeleader = HTTP::_GP('changeleader', 0);
					$tag         = HTTP::_GP('tag', '', UTF8_SUPPORT);
					$externo     = HTTP::_GP('externo', '', true);
					$interno     = HTTP::_GP('interno', '', true);
					$solicitud   = HTTP::_GP('solicitud', '', true);
					$delete      = HTTP::_GP('delete', '');
					$delete_u    = HTTP::_GP('delete_u', '');

					if (!empty($name))
						$db->update("UPDATE %%ALLIANCE%% SET `ally_name` = :n WHERE `id` = :id AND `ally_universe` = :uni;", [':n' => $name, ':id' => (int)$id, ':uni' => $uni]);
					if (!empty($tag))
						$db->update("UPDATE %%ALLIANCE%% SET `ally_tag` = :t WHERE `id` = :id AND `ally_universe` = :uni;", [':t' => $tag, ':id' => (int)$id, ':uni' => $uni]);

					$db->update("UPDATE %%ALLIANCE%% SET `ally_owner` = :cl WHERE `id` = :id AND `ally_universe` = :uni;", [':cl' => (int)$changeleader, ':id' => (int)$id, ':uni' => $uni]);
					$db->update("UPDATE %%USERS%% SET `ally_rank_id` = 0 WHERE `id` = :cl;", [':cl' => (int)$changeleader]);

					if (!empty($externo))
						$db->update("UPDATE %%ALLIANCE%% SET `ally_description` = :v WHERE `id` = :id AND `ally_universe` = :uni;", [':v' => $externo, ':id' => (int)$id, ':uni' => $uni]);
					if (!empty($interno))
						$db->update("UPDATE %%ALLIANCE%% SET `ally_text` = :v WHERE `id` = :id AND `ally_universe` = :uni;", [':v' => $interno, ':id' => (int)$id, ':uni' => $uni]);
					if (!empty($solicitud))
						$db->update("UPDATE %%ALLIANCE%% SET `ally_request` = :v WHERE `id` = :id AND `ally_universe` = :uni;", [':v' => $solicitud, ':id' => (int)$id, ':uni' => $uni]);

					if ($delete === 'on') {
						$db->delete("DELETE FROM %%ALLIANCE%% WHERE `id` = :id AND `ally_universe` = :uni;", [':id' => (int)$id, ':uni' => $uni]);
						$db->update("UPDATE %%USERS%% SET `ally_id` = 0, `ally_rank_id` = 0, `ally_register_time` = 0 WHERE `ally_id` = :id;", [':id' => (int)$id]);
					}
					if (!empty($delete_u)) {
						$db->update("UPDATE %%ALLIANCE%% SET `ally_members` = ally_members - 1 WHERE `id` = :id AND `ally_universe` = :uni;", [':id' => (int)$id, ':uni' => $uni]);
						$db->update("UPDATE %%USERS%% SET `ally_id` = 0, `ally_rank_id` = 0, `ally_register_time` = 0 WHERE `id` = :uid AND `ally_id` = :id;", [':uid' => (int)$delete_u, ':id' => (int)$id]);
					}

					$this->message($LNG['ad_ally_succes'], '?page=accounteditor&edit=alliances');
					return;
				}
				$this->show('AccountEditorPageAlliance.twig');
				break;

			default:
				$this->show('AccountEditorPageMenu.twig');
				break;
		}
	}
}
