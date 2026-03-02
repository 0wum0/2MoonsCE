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

if ($USER['authlevel'] == AUTH_USR)
{
	throw new Exception("Permission error!");#PagePermissionException
}

function ShowDumpPage()
{
	global $LNG;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:'';

	switch($action)
	{
		case 'dump':
			$dbTables	= HTTP::_GP('dbtables', array());
			if(empty($dbTables)) {
				$template	= new template();
				$template->message($LNG['du_not_tables_selected']);
				exit;
			}
			
			$fileName	= '2MoonsBackup_'.date('d_m_Y_H_i_s', TIMESTAMP).'.sql';
			$filePath	= 'includes/backups/'.$fileName;
		
			require_once 'includes/classes/SQLDumper.class.php';
		
			$dump	= new SQLDumper;
			$dump->dumpTablesToFile($dbTables, $filePath);
			
			$template	= new template();
			$template->message(sprintf($LNG['du_success'], 'includes/backups/'.$fileName));
		break;
		default:
			$dumpData['perRequest']		= 100;

			$dumpData		= array();

			$prefixCounts	= strlen(DB_PREFIX);

			$dumpData['sqlTables']	= array();
			$sqlTableRaw			= $GLOBALS['DATABASE']->query("SHOW TABLE STATUS FROM `".DB_NAME."`;");

			while($table = $GLOBALS['DATABASE']->fetchArray($sqlTableRaw))
			{
				if(DB_PREFIX == substr($table['Name'], 0, $prefixCounts))
				{
					$dumpData['sqlTables'][]	= $table['Name'];
				}
			}

			$template	= new template();

			$template->assign_vars(array(	
				'dumpData'	=> $dumpData,
			));
			
			$template->show('DumpPage.tpl');
		break;
	}
}