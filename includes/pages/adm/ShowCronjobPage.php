<?php

declare(strict_types=1);

/**
 * 2Moons 
 * by Jan-Otto Kröpke 2009-2016
 */

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) exit;

function getCronjobTimes($row, $max)
{
	$arr = explode(',', (string) $row);
	if (count($arr) > 1)
		return $arr;
	
	if (str_starts_with($arr[0], '*/'))
		return range(0, $max, (int) substr($arr[0], 2));
	else
		return $arr[0];
}

function checkPostData($column, $max)
{
	$all = HTTP::_GP($column.'_all', 0);
	if ($all)
		return '*';
	
	$post = HTTP::_GP($column, array());
	$post = array_filter($post, 'is_numeric');
	if (empty($post))
		return false;
	
	$check = array(2,3,5,6,7,10,14,15,20,30);
	$result = array();
	foreach ($check as $i)
	{
		if ($i <= $max && range(0, $max, $i) == $post)
			$result[] = $i;
	}
	if (!empty($result))
		return '*/'.max($result);
	else
		return implode(',', $post);
}

function ShowCronjob()
{
    $cronId = HTTP::_GP('id', 0);

    switch (HTTP::_GP('action', 'overview')) {
        case 'edit':
		    ShowCronjobEdit($cronId);
        break;
        case 'delete':
		    ShowCronjobDelete($cronId);
        break;
        case 'lock':
		    ShowCronjobLock($cronId);
        break;
        case 'unlock':
		    ShowCronjobUnlock($cronId);
        break;
        case 'detail':
		    ShowCronjobDetail($cronId);
        break;
        case 'enable':
		    ShowCronjobEnable($cronId);
        break;
        case 'overview':
        default:
		    ShowCronjobOverview();
        break;
    }
}

function ShowCronjobEdit($post_id)
{
	global $LNG;

	$post_name 		= 	HTTP::_GP('name', '');
	$post_min 		= 	checkPostData('min', 59);
	$post_hours 	= 	checkPostData('hours', 23);
	$post_month 	= 	checkPostData('month', 12);
	$post_dow 		= 	checkPostData('dow', 6);
	$post_dom 		= 	checkPostData('dom', 31);
	$post_class 	= 	HTTP::_GP('class', '');
	$error_msg 		= 	array();
	
	if ($post_name == '')
		$error_msg[] = $LNG['cronjob_error_name'];
	if ($post_min === false)
		$error_msg[] = $LNG['cronjob_error_min'];
	if ($post_hours === false)
		$error_msg[] = $LNG['cronjob_error_hours'];
	if ($post_month === false)
		$error_msg[] = $LNG['cronjob_error_month'];
	if ($post_dow === false)
		$error_msg[] = $LNG['cronjob_error_dow'];
	if ($post_dom === false)
		$error_msg[] = $LNG['cronjob_error_dom'];
	if ($post_class == '')
		$error_msg[] = $LNG['cronjob_error_class'];
	elseif (!file_exists(ROOT_PATH . 'includes/classes/cronjob/'.$post_class.'.class.php')) 
		$error_msg[] = $LNG['cronjob_error_filenotfound'].': includes/classes/cronjob/'.$post_class.'.class.php';
	
	if (count($error_msg) == 0)
	{
		$db = Database::get();
		if ($post_id != 0)
			$db->update("UPDATE ".CRONJOBS." SET name = :name, min = :min, hours = :hours, month = :month, dow = :dow, dom = :dom, class = :class WHERE cronjobID = :id;", [
				':name' => $post_name, ':min' => $post_min, ':hours' => $post_hours, ':month' => $post_month, ':dow' => $post_dow, ':dom' => $post_dom, ':class' => $post_class, ':id' => $post_id
			]);
		else
			$db->insert("INSERT INTO ".CRONJOBS." SET name = :name, min = :min, hours = :hours, month = :month, dow = :dow, dom = :dom, class = :class;", [
				':name' => $post_name, ':min' => $post_min, ':hours' => $post_hours, ':month' => $post_month, ':dow' => $post_dow, ':dom' => $post_dom, ':class' => $post_class
			]);

		HTTP::redirectTo('admin.php?page=cronjob');
	} else {
		ShowCronjobDetail($post_id, $error_msg);
	}
}

function ShowCronjobDelete($cronjobId) {
    $db = Database::get();
    $db->delete("DELETE FROM ".CRONJOBS." WHERE cronjobID = :id;", [':id' => $cronjobId]);
    $db->delete("DELETE FROM ".CRONJOBS_LOG." WHERE cronjobId = :id;", [':id' => $cronjobId]);
    HTTP::redirectTo('admin.php?page=cronjob');
}

function ShowCronjobLock($cronjobId) {
    Database::get()->update("UPDATE ".CRONJOBS." SET `lock` = :lock WHERE cronjobID = :id;", [':lock' => md5((string)TIMESTAMP), ':id' => $cronjobId]);
    HTTP::redirectTo('admin.php?page=cronjob');
}

function ShowCronjobUnlock($cronjobId) {
    Database::get()->update("UPDATE ".CRONJOBS." SET `lock` = NULL WHERE cronjobID = :id;", [':id' => $cronjobId]);
    HTTP::redirectTo('admin.php?page=cronjob');
}

function ShowCronjobEnable($cronjobId) {
    Database::get()->update("UPDATE ".CRONJOBS." SET `isActive` = :active WHERE cronjobID = :id;", [':active' => HTTP::_GP('enable', 0), ':id' => $cronjobId]);
    HTTP::redirectTo('admin.php?page=cronjob');
}

function ShowCronjobOverview() 
{
	global $LNG;
	$db = Database::get();
	$data = $db->select("SELECT * FROM ".CRONJOBS.";");

	$CronjobArray = array();
	foreach($data as $CronjobRow)
	{			
		$CronjobArray[]	= array(
			'id'			=> $CronjobRow['cronjobID'],
			'isActive'		=> $CronjobRow['isActive'],
			'name'			=> $CronjobRow['name'],
			'min'			=> $CronjobRow['min'],
			'hours'			=> $CronjobRow['hours'],
			'dom'			=> $CronjobRow['dom'],
			'month'			=> getCronjobTimes($CronjobRow['month'], 12),
			'dow'			=> getCronjobTimes($CronjobRow['dow'], 6),
			'class'			=> $CronjobRow['class'],
			'nextTime'		=> $CronjobRow['nextTime'],
			'lock'			=> !empty($CronjobRow['lock']),
		);
	}
	
	$template = new template();	
	$template->assign_vars(array(	
		'CronjobArray'	=> $CronjobArray,
	));
	$template->show("CronjobOverview.twig");
}

function ShowCronjobDetail($detail, $error_msg = NULL) 
{
	$template = new template();
	$avalibleCrons = array();
	
	$path = ROOT_PATH . 'includes/classes/cronjob/';
	if (is_dir($path)) {
		$dir = new DirectoryIterator($path);
		foreach ($dir as $fileinfo) {
			if ($fileinfo->isFile() && !str_starts_with($fileinfo->getFilename(), '.') && str_ends_with($fileinfo->getFilename(), '.class.php')) {
				$avalibleCrons[] = $fileinfo->getBasename('.class.php');
			}
		}
	}
	
	if ($detail != 0)
	{
		$db = Database::get();
		$CronjobRow = $db->selectSingle("SELECT * FROM ".CRONJOBS." WHERE cronjobID = :id", [':id' => $detail]);
		
		$template->assign_vars(array(	
			'id'			=> $CronjobRow['cronjobID'],
			'name'			=> HTTP::_GP('name', $CronjobRow['name']),
			'min'			=> isset($_POST['min_all']) ? array('*') : (isset($_POST['min']) ? HTTP::_GP('min', array()) : getCronjobTimes($CronjobRow['min'], 59)),
			'hours'			=> isset($_POST['hours_all']) ? array('*') : (isset($_POST['hours']) ? HTTP::_GP('hours', array()) : getCronjobTimes($CronjobRow['hours'], 23)),
			'dom'			=> isset($_POST['dom_all']) ? array('*') : (isset($_POST['dom']) ? HTTP::_GP('dom', array()) : getCronjobTimes($CronjobRow['dom'], 31)),
			'month'			=> isset($_POST['month_all']) ? array('*') : (isset($_POST['month']) ? HTTP::_GP('month', array()) : getCronjobTimes($CronjobRow['month'], 12)),
			'dow'			=> isset($_POST['dow_all']) ? array('*') : (isset($_POST['dow']) ? HTTP::_GP('dow', array()) : getCronjobTimes($CronjobRow['dow'], 6)),
			'class'			=> HTTP::_GP('class', $CronjobRow['class']),
			'avalibleCrons' => $avalibleCrons,
			'error_msg'		=> $error_msg,
		));
	} else {
		$template->assign_vars(array(	
			'id'			=> 0,
			'name'			=> HTTP::_GP('name', ''),
			'min'			=> isset($_POST['min_all']) ? array('*') : HTTP::_GP('min', array()),
			'hours'			=> isset($_POST['hours_all']) ? array('*') : HTTP::_GP('hours', array()),
			'dom'			=> isset($_POST['dom_all']) ? array('*') : HTTP::_GP('dom', array()),
			'month'			=> isset($_POST['month_all']) ? array('*') : HTTP::_GP('month', array()),
			'dow'			=> isset($_POST['dow_all']) ? array('*') : HTTP::_GP('dow', array()),
			'class'			=> HTTP::_GP('class', ''),
			'avalibleCrons' => $avalibleCrons,
			'error_msg'		=> $error_msg,
		));
	}
	$template->show("CronjobDetail.twig");
}