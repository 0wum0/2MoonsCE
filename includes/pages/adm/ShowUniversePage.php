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

class ShowUniversePage extends AbstractAdminPage
{
	public function __construct()
	{
		parent::__construct('ShowUniversePage');
	}

	protected function run(): void
	{
		global $LNG, $USER;

		$db       = Database::get();
		$action   = HTTP::_GP('action', '');
		$universe = (int) HTTP::_GP('uniID', 0);

		switch ($action) {
			case 'open':
				$config = Config::get($universe);
				$config->game_disable = 1;
				$config->save();
				break;

			case 'closed':
				$config = Config::get($universe);
				$config->game_disable = 0;
				$config->save();
				break;

			case 'delete':
				if (!empty($universe) && $universe !== ROOT_UNI && $universe !== Universe::current()) {
					$u = $universe;
					$db->nativeQuery("DELETE FROM %%ALLIANCE%%, %%ALLIANCE_RANK%%, %%ALLIANCE_REQUEST%%
					USING %%ALLIANCE%%
					LEFT JOIN %%ALLIANCE_RANK%%    ON %%ALLIANCE%%.id = %%ALLIANCE_RANK%%.allianceID
					LEFT JOIN %%ALLIANCE_REQUEST%% ON %%ALLIANCE%%.id = %%ALLIANCE_REQUEST%%.allianceID
					WHERE ally_universe = {$u};");
					$db->delete("DELETE FROM %%BANNED%%     WHERE universe          = :u;", [':u' => $u]);
					$db->nativeQuery("DELETE FROM %%BUDDY%%, %%BUDDY_REQUEST%%
					USING %%BUDDY%%
					LEFT JOIN %%BUDDY_REQUEST%% ON %%BUDDY%%.id = %%BUDDY_REQUEST%%.id
					WHERE %%BUDDY%%.universe = {$u};");
					$db->delete("DELETE FROM %%CONFIG%%     WHERE uni               = :u;", [':u' => $u]);
					$db->delete("DELETE FROM %%DIPLO%%      WHERE universe          = :u;", [':u' => $u]);
					$db->nativeQuery("DELETE FROM %%FLEETS%%, %%FLEETS_EVENT%%, %%AKS%%, %%LOG_FLEETS%%
					USING %%FLEETS%%
					LEFT JOIN %%FLEETS_EVENT%% ON %%FLEETS%%.fleet_id    = %%FLEETS_EVENT%%.fleetID
					LEFT JOIN %%AKS%%          ON %%FLEETS%%.fleet_group = %%AKS%%.id
					LEFT JOIN %%LOG_FLEETS%%   ON %%FLEETS%%.fleet_id    = %%LOG_FLEETS%%.fleet_id
					WHERE %%FLEETS%%.fleet_universe = {$u};");
					$db->delete("DELETE FROM %%MESSAGES%%   WHERE message_universe  = :u;", [':u' => $u]);
					$db->delete("DELETE FROM %%NOTES%%      WHERE universe          = :u;", [':u' => $u]);
					$db->delete("DELETE FROM %%PLANETS%%    WHERE universe          = :u;", [':u' => $u]);
					$db->delete("DELETE FROM %%STATPOINTS%% WHERE universe          = :u;", [':u' => $u]);
					$db->nativeQuery("DELETE FROM %%TICKETS%%, %%TICKETS_ANSWER%%
					USING %%TICKETS%%
					LEFT JOIN %%TICKETS_ANSWER%% ON %%TICKETS%%.ticketID = %%TICKETS_ANSWER%%.ticketID
					WHERE universe = {$u};");
					$db->delete("DELETE FROM %%TOPKB%%      WHERE universe          = :u;", [':u' => $u]);
					$db->nativeQuery("DELETE FROM %%USERS%%, %%USERS_ACS%%, %%USERS_AUTH%%, %%TOPKB_USERS%%, %%SESSION%%, %%SHORTCUTS%%, %%RECORDS%%
					USING %%USERS%%
					LEFT JOIN %%USERS_ACS%%    ON %%USERS%%.id = %%USERS_ACS%%.userID
					LEFT JOIN %%USERS_AUTH%%   ON %%USERS%%.id = %%USERS_AUTH%%.id
					LEFT JOIN %%TOPKB_USERS%%  ON %%USERS%%.id = %%TOPKB_USERS%%.uid
					LEFT JOIN %%SESSION%%      ON %%USERS%%.id = %%SESSION%%.userID
					LEFT JOIN %%SHORTCUTS%%    ON %%USERS%%.id = %%SHORTCUTS%%.ownerID
					LEFT JOIN %%RECORDS%%      ON %%USERS%%.id = %%RECORDS%%.userID
					LEFT JOIN %%LOSTPASSWORD%% ON %%USERS%%.id = %%LOSTPASSWORD%%.userID
					WHERE %%USERS%%.universe = {$u};");
					$db->delete("DELETE FROM %%USERS_VALID%% WHERE universe         = :u;", [':u' => $u]);

					if (Universe::getEmulated() === $universe) {
						Universe::setEmulated(Universe::current());
					}
					if (count(Universe::availableUniverses()) === 2) {
						setcookie(session_name(), session_id(), SESSION_LIFETIME, HTTP_BASE, null, HTTPS, true);
						HTTP::redirectTo("../admin.php?reload=r");
					}
				}
				break;

			case 'create':
				$universeCount = count(Universe::availableUniverses());
				$ch = curl_init();
				$url = ($universeCount === 1)
					? PROTOCOL . HTTP_HOST . HTTP_BASE . 'uni' . ROOT_UNI . '/'
					: PROTOCOL . HTTP_HOST . HTTP_BASE;
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HTTPGET, true);
				curl_setopt($ch, CURLOPT_AUTOREFERER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; 2Moons/' . Config::get()->VERSION . '; +http://2moons.cc)');
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3',
					'Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4',
				]);
				curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);

				if ($httpCode !== 302) {
					$this->message(
						str_replace('{NGINX-CODE}', 'rewrite /(.*)/?uni[0-9]+/?(.*) /$1/$2 break;', $LNG->getTemplate('createUniverseInfo'))
						. '<a href="javascript:window.history.back();"><button>' . $LNG['uvs_back'] . '</button></a>'
						. '<a href="javascript:window.location.reload();"><button>' . $LNG['uvs_reload'] . '</button></a>'
					);
					return;
				}

				$config     = Config::get();
				$pdo        = $db->getHandle();
				$setClauses = [];
				$params     = [];
				foreach (Config::getGlobalConfigKeys() as $key) {
					$setClauses[] = '`' . $key . '` = :cfg_' . $key;
					$params[':cfg_' . $key] = $config->$key;
				}
				$setClauses[] = '`uni_name` = :uni_name';
				$setClauses[] = '`close_reason` = :close_reason';
				$setClauses[] = '`OverviewNewsText` = :overview_news';
				$params[':uni_name']      = $LNG['fcm_universe'] . ' ' . ($universeCount + 1);
				$params[':close_reason']  = '';
				$params[':overview_news'] = (string) $config->OverviewNewsText;

				$db->insert('INSERT INTO %%CONFIG%% SET ' . implode(', ', $setClauses) . ';', $params);
				$newUniverse = (int) $db->lastInsertId();

				Config::reload();

				[$userID] = PlayerUtil::createPlayer($newUniverse, $USER['username'], '', $USER['email'], $USER['lang'], 1, 1, 1, null, AUTH_ADM);
				$db->update(
					"UPDATE %%USERS%% SET `password` = :pw WHERE `id` = :id;",
					[':pw' => $USER['password'], ':id' => (int) $userID]
				);

				if ($universeCount === 1) {
					setcookie(session_name(), session_id(), SESSION_LIFETIME, HTTP_ROOT . 'uni' . $USER['universe'] . '/', null, HTTPS, true);
					HTTP::redirectTo('uni' . $USER['universe'] . '/admin.php?reload=r');
				}
				break;
		}

		$uniRows = $db->select(
			"SELECT conf.uni, conf.users_amount, conf.game_disable, conf.energySpeed, conf.halt_speed,
			        conf.resource_multiplier, conf.fleet_speed, conf.game_speed, conf.uni_name,
			        COUNT(DISTINCT inac.id) AS inactive,
			        COUNT(planet.id)        AS planet
			 FROM %%CONFIG%% conf
			 LEFT JOIN %%USERS%%   AS inac   ON inac.universe  = conf.uni AND inac.onlinetime < :inactive_time
			 LEFT JOIN %%PLANETS%% AS planet ON planet.universe = conf.uni
			 GROUP BY conf.uni
			 ORDER BY conf.uni ASC;",
			[':inactive_time' => TIMESTAMP - INACTIVE]
		);

		$uniList = [];
		foreach ($uniRows as $uniRow) {
			$uniList[$uniRow['uni']] = $uniRow;
		}

		$this->assign([
			'uniList' => $uniList,
			'SID'     => session_id(),
		]);
		$this->show('UniversePage.twig');
	}
}