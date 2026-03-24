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

class ShowResetPage extends AbstractAdminPage
{
	public function __construct()
	{
		global $USER;
		if ($USER['id'] != ROOT_USER || ($_GET['sid'] ?? '') != session_id()) exit;
		parent::__construct('');
	}

	protected function run(): void
	{
		global $LNG, $reslist, $resource;

		$db     = Database::get();
		$config = Config::get(ROOT_UNI);
		$uni    = (int) Universe::getEmulated();

		if (!empty($_POST)) {
			$dbcol = ['build' => [], 'tech' => [], 'fleet' => [], 'defense' => [], 'officier' => [],
			          'resource_planet_start' => [], 'resource_user_start' => []];

			foreach ($reslist['build']    as $ID) { $dbcol['build'][$ID]    = '`' . $resource[$ID] . '` = 0'; }
			foreach ($reslist['tech']     as $ID) { $dbcol['tech'][$ID]     = '`' . $resource[$ID] . '` = 0'; }
			foreach ($reslist['fleet']    as $ID) { $dbcol['fleet'][$ID]    = '`' . $resource[$ID] . '` = 0'; }
			foreach ($reslist['defense']  as $ID) { $dbcol['defense'][$ID]  = '`' . $resource[$ID] . '` = 0'; }
			foreach ($reslist['officier'] as $ID) { $dbcol['officier'][$ID] = '`' . $resource[$ID] . '` = 0'; }

			foreach ($reslist['resstype'][1] as $ID) {
				if (isset($config->{$resource[$ID] . '_start'})) {
					$dbcol['resource_planet_start'][$ID] = '`' . $resource[$ID] . '` = ' . (int) $config->{$resource[$ID] . '_start'};
				}
			}
			foreach ($reslist['resstype'][3] as $ID) {
				if (isset($config->{$resource[$ID] . '_start'})) {
					$dbcol['resource_user_start'][$ID] = '`' . $resource[$ID] . '` = ' . (int) $config->{$resource[$ID] . '_start'};
				}
			}

			// Players and Planets
			if (($_POST['players'] ?? '') === 'on') {
				$rootOwner = (int) $db->selectSingle(
					"SELECT `id_owner` FROM %%PLANETS%% WHERE `universe` = :u AND `galaxy` = 1 AND `system` = 1 AND `planet` = 1;",
					[':u' => $uni],
					'id_owner'
				);
				$db->delete("DELETE FROM %%USERS%%   WHERE `universe` = :u AND `id` != :id;",   [':u' => $uni, ':id' => $rootOwner]);
				$db->delete("DELETE FROM %%PLANETS%% WHERE `universe` = :u AND `id_owner` != :id;", [':u' => $uni, ':id' => $rootOwner]);
			}

			if (($_POST['planets'] ?? '') === 'on') {
				$db->nativeQuery("DELETE FROM %%PLANETS%% WHERE `universe` = {$uni} AND `id` NOT IN (SELECT id_planet FROM %%USERS%% WHERE `universe` = {$uni});");
				$db->update("UPDATE %%PLANETS%% SET `id_luna` = 0 WHERE `universe` = :u;", [':u' => $uni]);
			}

			if (($_POST['moons'] ?? '') === 'on') {
				$db->delete("DELETE FROM %%PLANETS%% WHERE `planet_type` = 3 AND `universe` = :u;", [':u' => $uni]);
				$db->update("UPDATE %%PLANETS%% SET `id_luna` = 0 WHERE `universe` = :u;",          [':u' => $uni]);
			}

			// Defenses and ships
			if (($_POST['defenses'] ?? '') === 'on' && !empty($dbcol['defense'])) {
				$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $dbcol['defense']) . " WHERE `universe` = {$uni};");
			}
			if (($_POST['ships'] ?? '') === 'on' && !empty($dbcol['fleet'])) {
				$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $dbcol['fleet']) . " WHERE `universe` = {$uni};");
			}
			if (($_POST['h_d'] ?? '') === 'on') {
				$db->update("UPDATE %%PLANETS%% SET `b_hangar` = 0, `b_hangar_id` = '' WHERE `universe` = :u;", [':u' => $uni]);
			}

			// Buildings
			if (($_POST['edif_p'] ?? '') === 'on' && !empty($dbcol['build'])) {
				$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $dbcol['build']) . ", `field_current` = 0 WHERE `planet_type` = 1 AND `universe` = {$uni};");
			}
			if (($_POST['edif_l'] ?? '') === 'on' && !empty($dbcol['build'])) {
				$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $dbcol['build']) . ", `field_current` = 0 WHERE `planet_type` = 3 AND `universe` = {$uni};");
			}
			if (($_POST['edif'] ?? '') === 'on') {
				$db->update("UPDATE %%PLANETS%% SET `b_building` = 0, `b_building_id` = '' WHERE `universe` = :u;", [':u' => $uni]);
			}

			// Research and officers
			if (($_POST['inves'] ?? '') === 'on' && !empty($dbcol['tech'])) {
				$db->nativeQuery("UPDATE %%USERS%% SET " . implode(', ', $dbcol['tech']) . " WHERE `universe` = {$uni};");
			}
			if (($_POST['ofis'] ?? '') === 'on' && !empty($dbcol['officier'])) {
				$db->nativeQuery("UPDATE %%USERS%% SET " . implode(', ', $dbcol['officier']) . " WHERE `universe` = {$uni};");
			}
			if (($_POST['inves_c'] ?? '') === 'on') {
				$db->update("UPDATE %%USERS%% SET `b_tech_planet` = 0, `b_tech` = 0, `b_tech_id` = 0, `b_tech_queue` = '' WHERE `universe` = :u;", [':u' => $uni]);
			}

			// Resources
			if (($_POST['dark'] ?? '') === 'on' && !empty($dbcol['resource_user_start'])) {
				$db->nativeQuery("UPDATE %%USERS%% SET " . implode(', ', $dbcol['resource_user_start']) . " WHERE `universe` = {$uni};");
			}
			if (($_POST['resources'] ?? '') === 'on' && !empty($dbcol['resource_planet_start'])) {
				$db->nativeQuery("UPDATE %%PLANETS%% SET " . implode(', ', $dbcol['resource_planet_start']) . " WHERE `universe` = {$uni};");
			}

			// General
			if (($_POST['notes']      ?? '') === 'on') { $db->delete("DELETE FROM %%NOTES%%      WHERE `universe` = :u;",         [':u' => $uni]); }
			if (($_POST['rw']         ?? '') === 'on') { $db->delete("DELETE FROM %%TOPKB%%      WHERE `universe` = :u;",         [':u' => $uni]); }
			if (($_POST['friends']    ?? '') === 'on') { $db->delete("DELETE FROM %%BUDDY%%      WHERE `universe` = :u;",         [':u' => $uni]); }
			if (($_POST['fleets']     ?? '') === 'on') { $db->delete("DELETE FROM %%FLEETS%%     WHERE `fleet_universe` = :u;",   [':u' => $uni]); }
			if (($_POST['messages']   ?? '') === 'on') { $db->delete("DELETE FROM %%MESSAGES%%   WHERE `message_universe` = :u;", [':u' => $uni]); }
			if (($_POST['statpoints'] ?? '') === 'on') { $db->delete("DELETE FROM %%STATPOINTS%% WHERE `universe` = :u;",         [':u' => $uni]); }

			if (($_POST['alliances'] ?? '') === 'on') {
				$db->delete("DELETE FROM %%ALLIANCE%% WHERE `ally_universe` = :u;", [':u' => $uni]);
				$db->update("UPDATE %%USERS%% SET `ally_id` = 0, `ally_register_time` = 0, `ally_rank_id` = 0 WHERE `universe` = :u;", [':u' => $uni]);
			}
			if (($_POST['banneds'] ?? '') === 'on') {
				$db->delete("DELETE FROM %%BANNED%% WHERE `universe` = :u;",                                               [':u' => $uni]);
				$db->update("UPDATE %%USERS%% SET `bana` = 0, `banaday` = 0 WHERE `universe` = :u;", [':u' => $uni]);
			}

			if (($_POST['delete_bots'] ?? '') === 'on') {
				$botsTable   = DB_PREFIX . 'bots';
				// Collect bot owner IDs for this universe
				$botOwnerIds = $db->select(
					"SELECT b.id_owner FROM {$botsTable} b
					 INNER JOIN %%USERS%% u ON u.id = b.id_owner
					 WHERE u.universe = :u;",
					[':u' => $uni]
				);
				if (!empty($botOwnerIds)) {
					$ids = implode(',', array_map(fn($r) => (int)$r['id_owner'], $botOwnerIds));
					// Delete bot planets
					$db->nativeQuery("DELETE FROM %%PLANETS%% WHERE id_owner IN ({$ids}) AND universe = {$uni};");
					// Delete bot users
					$db->nativeQuery("DELETE FROM %%USERS%% WHERE id IN ({$ids}) AND universe = {$uni};");
					// Delete statpoints
					$db->nativeQuery("DELETE FROM %%STATPOINTS%% WHERE id_owner IN ({$ids});");
					// Delete fleets belonging to bots
					$db->nativeQuery("DELETE FROM %%FLEETS%% WHERE fleet_owner IN ({$ids}) AND fleet_universe = {$uni};");
				}
				// Delete all bot entries for this universe
				$db->nativeQuery(
					"DELETE b FROM {$botsTable} b
					 LEFT JOIN %%USERS%% u ON u.id = b.id_owner
					 WHERE u.id IS NULL OR u.universe = {$uni};"
				);
				// Also reset bot_activity_log and bot_stats if they exist
				try { $db->nativeQuery("TRUNCATE TABLE " . DB_PREFIX . "bot_activity_log;"); } catch (Throwable $e) {}
				try { $db->nativeQuery("TRUNCATE TABLE " . DB_PREFIX . "bot_stats;"); } catch (Throwable $e) {}
			}

			$this->message($LNG['re_reset_excess'], '?page=reset&sid=' . session_id(), 3);
			return;
		}

		$this->assign([
			'button_submit'                    => $LNG['button_submit'],
			're_reset_universe_confirmation'   => $LNG['re_reset_universe_confirmation'],
			're_reset_all'                     => $LNG['re_reset_all'],
			're_defenses_and_ships'            => $LNG['re_defenses_and_ships'],
			're_reset_buldings'                => $LNG['re_reset_buldings'],
			're_buildings_lu'                  => $LNG['re_buildings_lu'],
			're_buildings_pl'                  => $LNG['re_buildings_pl'],
			're_buldings'                      => $LNG['re_buldings'],
			're_reset_hangar'                  => $LNG['re_reset_hangar'],
			're_ships'                         => $LNG['re_ships'],
			're_defenses'                      => $LNG['re_defenses'],
			're_resources_met_cry'             => $LNG['re_resources_met_cry'],
			're_resources_dark'                => $LNG['re_resources_dark'],
			're_resources'                     => $LNG['re_resources'],
			're_reset_invest'                  => $LNG['re_reset_invest'],
			're_investigations'                => $LNG['re_investigations'],
			're_ofici'                         => $LNG['re_ofici'],
			're_inve_ofis'                     => $LNG['re_inve_ofis'],
			're_reset_statpoints'              => $LNG['re_reset_statpoints'],
			're_reset_messages'                => $LNG['re_reset_messages'],
			're_reset_banned'                  => $LNG['re_reset_banned'],
			're_reset_errors'                  => $LNG['re_reset_errors'],
			're_reset_fleets'                  => $LNG['re_reset_fleets'],
			're_reset_allys'                   => $LNG['re_reset_allys'],
			're_reset_buddies'                 => $LNG['re_reset_buddies'],
			're_reset_rw'                      => $LNG['re_reset_rw'],
			're_reset_notes'                   => $LNG['re_reset_notes'],
			're_reset_moons'                   => $LNG['re_reset_moons'],
			're_reset_planets'                 => $LNG['re_reset_planets'],
			're_reset_player'                  => $LNG['re_reset_player'],
			're_player_and_planets'            => $LNG['re_player_and_planets'],
			're_general'                       => $LNG['re_general'],
		]);
		$this->show('ResetPage.twig');
	}
}
