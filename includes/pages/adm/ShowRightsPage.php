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

class ShowRightsPage extends AbstractAdminPage
{
	public function __construct()
	{
		parent::__construct('ShowRightsPage');
	}

	protected function run(): void
	{
		global $LNG, $USER;

		$db   = Database::get();
		$uni  = (int) Universe::getEmulated();
		$mode = HTTP::_GP('mode', '');

		switch ($mode) {
			case 'rights':
				$this->loadScript('filterlist.js');
				$this->handleRights($db, $uni, $USER, $LNG);
				break;
			case 'users':
				$this->loadScript('filterlist.js');
				$this->handleUsers($db, $uni, $USER, $LNG);
				break;
		}
	}

	private function getUserList(\Database $db, int $uni, string $filterGet): array
	{
		$allowedLevels = ['adm' => AUTH_ADM, 'ope' => AUTH_OPS, 'mod' => AUTH_MOD, 'pla' => AUTH_USR];
		$params = [':uni' => $uni];
		$where  = '';
		if (isset($allowedLevels[$filterGet])) {
			$where = ' AND `authlevel` = :level';
			$params[':level'] = $allowedLevels[$filterGet];
		}
		return $db->select(
			"SELECT `id`, `username`, `authlevel` FROM %%USERS%% WHERE `universe` = :uni" . $where . " ORDER BY `username` ASC;",
			$params
		);
	}

	private function buildUserOptionList(array $rows, array $LNG): string
	{
		$html = '';
		foreach ($rows as $row) {
			$rankKey = 'rank_' . (int) $row['authlevel'];
			$rank    = $LNG[$rankKey] ?? (string) $row['authlevel'];
			$html   .= '<option value="' . (int) $row['id'] . '">'
				. htmlspecialchars($row['username'], ENT_QUOTES)
				. '&nbsp;&nbsp;(' . htmlspecialchars($rank, ENT_QUOTES) . ')</option>';
		}
		return $html;
	}

	private function handleRights(\Database $db, int $uni, array $USER, array $LNG): void
	{
		if (!empty($_POST)) {
			$id = HTTP::_GP('id_1', 0);

			if ((int) $USER['id'] !== ROOT_USER && $id === ROOT_USER) {
				$this->message($LNG['ad_authlevel_error_3'], '?page=rights&mode=rights&sid=' . session_id());
				return;
			}

			if (($_POST['action'] ?? '') === 'send') {
				$rights = isset($_POST['rights']) && is_array($_POST['rights'])
					? array_map('intval', $_POST['rights'])
					: [];
				$db->update(
					"UPDATE %%USERS%% SET `rights` = :rights WHERE `id` = :id;",
					[':rights' => serialize($rights), ':id' => (int) $id]
				);
			}

			$row    = $db->selectSingle("SELECT `rights` FROM %%USERS%% WHERE `id` = :id;", [':id' => (int) $id]);
			$rights = [];
			if (!empty($row['rights'])) {
				$unserialized = @unserialize($row['rights']);
				if (is_array($unserialized)) {
					$rights = $unserialized;
				}
			}

			$exclude = ['.', '..', '.svn', 'index.html', '.htaccess',
				'ShowIndexPage.php', 'ShowOverviewPage.php', 'ShowMenuPage.php', 'ShowTopnavPage.php',
				'AbstractAdminPage.php', 'AdminStatsService.php', 'BanService.php',
				'CacheService.php', 'NewsRepository.php', 'ShowLoginPage.php', 'ShowLogoutPage.php'];
			$files   = array_map(
				fn($f) => str_replace('.php', '', $f),
				array_diff(scandir(ROOT_PATH . 'includes/pages/adm/') ?: [], $exclude)
			);

			$this->assign([
				'Files'                  => array_values($files),
				'Rights'                 => $rights,
				'id'                     => $id,
				'yesorno'                => [1 => $LNG['one_is_yes_1'], 0 => $LNG['one_is_yes_0']],
				'ad_authlevel_title'     => $LNG['ad_authlevel_title'],
				'button_submit'          => $LNG['button_submit'],
				'sid'                    => session_id(),
			]);
			$this->show('ModerrationRightsPostPage.twig');
			return;
		}

		$filterGet = $_GET['get'] ?? '';
		$rows      = $this->getUserList($db, $uni, $filterGet);
		$UserList  = $this->buildUserOptionList($rows, $LNG);

		$this->assign([
			'Selector'               => [0 => $LNG['rank_0'], 1 => $LNG['rank_1'], 2 => $LNG['rank_2'], 3 => $LNG['rank_3']],
			'UserList'               => $UserList,
			'ad_authlevel_title'     => $LNG['ad_authlevel_title'],
			'bo_select_title'        => $LNG['bo_select_title'],
			'button_submit'          => $LNG['button_submit'],
			'button_deselect'        => $LNG['button_deselect'],
			'button_filter'          => $LNG['button_filter'],
			'ad_authlevel_insert_id' => $LNG['ad_authlevel_insert_id'],
			'ad_authlevel_auth'      => $LNG['ad_authlevel_auth'],
			'ad_authlevel_aa'        => $LNG['ad_authlevel_aa'],
			'ad_authlevel_oo'        => $LNG['ad_authlevel_oo'],
			'ad_authlevel_mm'        => $LNG['ad_authlevel_mm'],
			'ad_authlevel_jj'        => $LNG['ad_authlevel_jj'],
			'ad_authlevel_tt'        => $LNG['ad_authlevel_tt'],
			'sid'                    => session_id(),
		]);
		$this->show('ModerrationRightsPage.twig');
	}

	private function handleUsers(\Database $db, int $uni, array $USER, array $LNG): void
	{
		if (!empty($_POST)) {
			$id = HTTP::_GP('id_1', 0);
			if ($id === 0) {
				$id = HTTP::_GP('id_2', 0);
			}

			if ((int) $USER['id'] !== ROOT_USER && $id === ROOT_USER) {
				$this->message($LNG['ad_authlevel_error_3'], '?page=rights&mode=users&sid=' . session_id());
				return;
			}

			$authlevel = HTTP::_GP('authlevel', 0);
			$db->update(
				"UPDATE %%USERS%% SET `authlevel` = :level WHERE `id` = :id;",
				[':level' => (int) $authlevel, ':id' => (int) $id]
			);
			$this->message($LNG['ad_authlevel_succes'], '?page=rights&mode=users&sid=' . session_id());
			return;
		}

		$filterGet = $_GET['get'] ?? '';
		$rows      = $this->getUserList($db, $uni, $filterGet);
		$UserList  = $this->buildUserOptionList($rows, $LNG);

		$this->assign([
			'Selector'               => [0 => $LNG['rank_0'], 1 => $LNG['rank_1'], 2 => $LNG['rank_2'], 3 => $LNG['rank_3']],
			'UserList'               => $UserList,
			'ad_authlevel_title'     => $LNG['ad_authlevel_title'],
			'bo_select_title'        => $LNG['bo_select_title'],
			'button_submit'          => $LNG['button_submit'],
			'button_deselect'        => $LNG['button_deselect'],
			'button_filter'          => $LNG['button_filter'],
			'ad_authlevel_insert_id' => $LNG['ad_authlevel_insert_id'],
			'ad_authlevel_auth'      => $LNG['ad_authlevel_auth'],
			'ad_authlevel_aa'        => $LNG['ad_authlevel_aa'],
			'ad_authlevel_oo'        => $LNG['ad_authlevel_oo'],
			'ad_authlevel_mm'        => $LNG['ad_authlevel_mm'],
			'ad_authlevel_jj'        => $LNG['ad_authlevel_jj'],
			'ad_authlevel_tt'        => $LNG['ad_authlevel_tt'],
			'sid'                    => session_id(),
		]);
		$this->show('ModerrationUsersPage.twig');
	}
}

function prepare($val)
{
	return str_replace('.php', '', $val);
}