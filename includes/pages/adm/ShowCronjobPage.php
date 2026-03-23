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

class ShowCronjobPage extends AbstractAdminPage
{
	public function __construct()
	{
		parent::__construct('ShowCronjobPage');
	}

	protected function run(): void
	{
		$cronId = HTTP::_GP('id', 0);
		switch (HTTP::_GP('action', 'overview')) {
			case 'edit':     $this->editCronjob($cronId);    break;
			case 'delete':   $this->deleteCronjob($cronId);  break;
			case 'lock':     $this->lockCronjob($cronId);    break;
			case 'unlock':   $this->unlockCronjob($cronId);  break;
			case 'detail':   $this->detailCronjob($cronId);  break;
			case 'enable':   $this->enableCronjob($cronId);  break;
			case 'resetAll': $this->resetAll();               break;
			default:         $this->overviewCronjob();        break;
		}
	}

	private function getCronjobTimes($row, int $max)
	{
		$arr = explode(',', (string) $row);
		if (count($arr) > 1) return $arr;
		if (str_starts_with($arr[0], '*/')) return range(0, $max, (int) substr($arr[0], 2));
		return $arr[0];
	}

	private function checkPostData(string $column, int $max)
	{
		$all = HTTP::_GP($column . '_all', 0);
		if ($all) return '*';
		$post = HTTP::_GP($column, array());
		$post = array_filter($post, 'is_numeric');
		if (empty($post)) return false;
		$check  = [2, 3, 5, 6, 7, 10, 14, 15, 20, 30];
		$result = [];
		foreach ($check as $i) {
			if ($i <= $max && range(0, $max, $i) == $post) $result[] = $i;
		}
		return !empty($result) ? '*/' . max($result) : implode(',', $post);
	}

	private function editCronjob(int $post_id): void
	{
		global $LNG;
		$post_name  = HTTP::_GP('name', '');
		$post_min   = $this->checkPostData('min', 59);
		$post_hours = $this->checkPostData('hours', 23);
		$post_month = $this->checkPostData('month', 12);
		$post_dow   = $this->checkPostData('dow', 6);
		$post_dom   = $this->checkPostData('dom', 31);
		$post_class = HTTP::_GP('class', '');
		$error_msg  = [];

		if ($post_name == '')     $error_msg[] = $LNG['cronjob_error_name'];
		if ($post_min === false)  $error_msg[] = $LNG['cronjob_error_min'];
		if ($post_hours === false) $error_msg[] = $LNG['cronjob_error_hours'];
		if ($post_month === false) $error_msg[] = $LNG['cronjob_error_month'];
		if ($post_dow === false)  $error_msg[] = $LNG['cronjob_error_dow'];
		if ($post_dom === false)  $error_msg[] = $LNG['cronjob_error_dom'];
		if ($post_class == '') {
			$error_msg[] = $LNG['cronjob_error_class'];
		} elseif (!file_exists(ROOT_PATH . 'includes/classes/cronjob/' . $post_class . '.class.php')) {
			$error_msg[] = $LNG['cronjob_error_filenotfound'] . ': includes/classes/cronjob/' . $post_class . '.class.php';
		}

		if (count($error_msg) == 0) {
			$db = Database::get();
			require_once 'includes/classes/Cronjob.class.php';
			if ($post_id != 0) {
				$db->update('UPDATE %%CRONJOBS%% SET name = :name, min = :min, hours = :hours, month = :month, dow = :dow, dom = :dom, class = :class WHERE cronjobID = :id;', [
					':name' => $post_name, ':min' => $post_min, ':hours' => $post_hours,
					':month' => $post_month, ':dow' => $post_dow, ':dom' => $post_dom,
					':class' => $post_class, ':id' => $post_id,
				]);
				Cronjob::reCalculateCronjobs((int) $post_id);
			} else {
				$db->insert('INSERT INTO %%CRONJOBS%% SET name = :name, min = :min, hours = :hours, month = :month, dow = :dow, dom = :dom, class = :class, nextTime = 0, isActive = 1;', [
					':name' => $post_name, ':min' => $post_min, ':hours' => $post_hours,
					':month' => $post_month, ':dow' => $post_dow, ':dom' => $post_dom, ':class' => $post_class,
				]);
				$newId = (int) $db->lastInsertId();
				if ($newId > 0) Cronjob::reCalculateCronjobs($newId);
			}
			HTTP::redirectTo('admin.php?page=cronjob');
		} else {
			$this->detailCronjob($post_id, $error_msg);
		}
	}

	private function deleteCronjob(int $cronjobId): void
	{
		$db = Database::get();
		$db->delete('DELETE FROM %%CRONJOBS%% WHERE cronjobID = :id;', [':id' => $cronjobId]);
		$db->delete('DELETE FROM %%CRONJOBS_LOG%% WHERE cronjobId = :id;', [':id' => $cronjobId]);
		HTTP::redirectTo('admin.php?page=cronjob');
	}

	private function lockCronjob(int $cronjobId): void
	{
		Database::get()->update('UPDATE %%CRONJOBS%% SET `lock` = :lock, `lockTime` = :lockTime WHERE cronjobID = :id;', [
			':lock' => md5((string) TIMESTAMP), ':lockTime' => TIMESTAMP, ':id' => $cronjobId,
		]);
		HTTP::redirectTo('admin.php?page=cronjob');
	}

	private function unlockCronjob(int $cronjobId): void
	{
		Database::get()->update('UPDATE %%CRONJOBS%% SET `lock` = NULL, `lockTime` = NULL WHERE cronjobID = :id;', [':id' => $cronjobId]);
		HTTP::redirectTo('admin.php?page=cronjob');
	}

	private function enableCronjob(int $cronjobId): void
	{
		require_once 'includes/classes/Cronjob.class.php';
		Database::get()->update('UPDATE %%CRONJOBS%% SET `isActive` = :active WHERE cronjobID = :id;', [
			':active' => HTTP::_GP('enable', 0), ':id' => $cronjobId,
		]);
		Cronjob::reCalculateCronjobs((int) $cronjobId);
		HTTP::redirectTo('admin.php?page=cronjob');
	}

	private function resetAll(): void
	{
		require_once 'includes/classes/Cronjob.class.php';
		Cronjob::reCalculateCronjobs();
		HTTP::redirectTo('admin.php?page=cronjob');
	}

	private function overviewCronjob(): void
	{
		$db   = Database::get();
		$data = $db->select('SELECT * FROM %%CRONJOBS%%;');

		$CronjobArray = [];
		foreach ($data as $CronjobRow) {
			$CronjobArray[] = [
				'id'       => $CronjobRow['cronjobID'],
				'isActive' => $CronjobRow['isActive'],
				'name'     => $CronjobRow['name'],
				'min'      => $CronjobRow['min'],
				'hours'    => $CronjobRow['hours'],
				'dom'      => $CronjobRow['dom'],
				'month'    => $this->getCronjobTimes($CronjobRow['month'], 12),
				'dow'      => $this->getCronjobTimes($CronjobRow['dow'], 6),
				'class'    => $CronjobRow['class'],
				'nextTime' => $CronjobRow['nextTime'],
				'lock'     => !empty($CronjobRow['lock']),
			];
		}

		$this->assign(['CronjobArray' => $CronjobArray]);
		$this->show('CronjobOverview.twig');
	}

	private function detailCronjob(int $detail, ?array $error_msg = null): void
	{
		$avalibleCrons = [];
		$path = ROOT_PATH . 'includes/classes/cronjob/';
		if (is_dir($path)) {
			$dir = new DirectoryIterator($path);
			foreach ($dir as $fileinfo) {
				if ($fileinfo->isFile() && !str_starts_with($fileinfo->getFilename(), '.') && str_ends_with($fileinfo->getFilename(), '.class.php')) {
					$avalibleCrons[] = $fileinfo->getBasename('.class.php');
				}
			}
		}

		if ($detail != 0) {
			$CronjobRow = Database::get()->selectSingle('SELECT * FROM %%CRONJOBS%% WHERE cronjobID = :id;', [':id' => $detail]);
			$this->assign([
				'id'            => $CronjobRow['cronjobID'],
				'name'          => HTTP::_GP('name', $CronjobRow['name']),
				'min'           => isset($_POST['min_all'])   ? ['*'] : (isset($_POST['min'])   ? HTTP::_GP('min', [])   : $this->getCronjobTimes($CronjobRow['min'], 59)),
				'hours'         => isset($_POST['hours_all']) ? ['*'] : (isset($_POST['hours']) ? HTTP::_GP('hours', []) : $this->getCronjobTimes($CronjobRow['hours'], 23)),
				'dom'           => isset($_POST['dom_all'])   ? ['*'] : (isset($_POST['dom'])   ? HTTP::_GP('dom', [])   : $this->getCronjobTimes($CronjobRow['dom'], 31)),
				'month'         => isset($_POST['month_all']) ? ['*'] : (isset($_POST['month']) ? HTTP::_GP('month', []) : $this->getCronjobTimes($CronjobRow['month'], 12)),
				'dow'           => isset($_POST['dow_all'])   ? ['*'] : (isset($_POST['dow'])   ? HTTP::_GP('dow', [])   : $this->getCronjobTimes($CronjobRow['dow'], 6)),
				'class'         => HTTP::_GP('class', $CronjobRow['class']),
				'avalibleCrons' => $avalibleCrons,
				'error_msg'     => $error_msg,
			]);
		} else {
			$this->assign([
				'id'            => 0,
				'name'          => HTTP::_GP('name', ''),
				'min'           => isset($_POST['min_all'])   ? ['*'] : HTTP::_GP('min', []),
				'hours'         => isset($_POST['hours_all']) ? ['*'] : HTTP::_GP('hours', []),
				'dom'           => isset($_POST['dom_all'])   ? ['*'] : HTTP::_GP('dom', []),
				'month'         => isset($_POST['month_all']) ? ['*'] : HTTP::_GP('month', []),
				'dow'           => isset($_POST['dow_all'])   ? ['*'] : HTTP::_GP('dow', []),
				'class'         => HTTP::_GP('class', ''),
				'avalibleCrons' => $avalibleCrons,
				'error_msg'     => $error_msg,
			]);
		}
		$this->show('CronjobDetail.twig');
	}
}