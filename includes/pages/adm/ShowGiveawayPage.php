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
 
 if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");
function ShowGiveaway()
{
	global $LNG, $resource, $reslist;
	$template	= new template();	
	$action	= HTTP::_GP('action', '');
	if ($action == 'send') {
		$planet			= HTTP::_GP('planet', 0);
		$moon			= HTTP::_GP('moon', 0);
		$mainplanet		= HTTP::_GP('mainplanet', 0);
		$no_inactive	= HTTP::_GP('no_inactive', 0);
		
		if (!$planet && !$moon) {
			$template->message($LNG['ga_selectplanettype']);
			exit;
		}
		
		$planetIN	= array();
		
		if ($planet) {
			$planetIN[]	= "'1'";
		} 
		
		if ($moon) {
			$planetIN[]	= "'3'";
		} 
		
		$data		= array();
		
		$DataIDs	= array_merge($reslist['resstype'][1], $reslist['resstype'][3], $reslist['build'], $reslist['tech'], $reslist['fleet'], $reslist['defense'], $reslist['officier']);
		
		$logOld		= array();
		$logNew		= array();
		
		foreach($DataIDs as $ID)
		{
			$amount	= max(0, round(HTTP::_GP('element_'.$ID, 0.0)));
			$data[]	= $resource[$ID]." = ".$resource[$ID]." + ".$amount;
			
			$logOld[$ID]	= 0;
			$logNew[$ID]	= $amount;
		}
		
		$SQL		= "UPDATE ".PLANETS." p INNER JOIN ".USERS." u ON p.id_owner = u.id";
		
		if ($mainplanet == true) {
			$SQL	.= " AND p.id = u.id_planet";
		}
		
		if ($no_inactive == true) {
			$SQL	.= " AND u.onlinetime > ".(TIMESTAMP - INACTIVE);
		}
		
		$SQL	.= " SET ".implode(', ', $data)." WHERE p.universe = ".Universe::getEmulated()." AND p.planet_type IN (".implode(',', $planetIN).")";
		
		$GLOBALS['DATABASE']->query($SQL);
		
		$LOG = new Log(4);
		$LOG->target = 0;
		$LOG->old = $logOld;
		$LOG->new = $logNew;
		$LOG->save();
		
		$template->message($LNG['ga_success']);
		exit;
	}	
	
	$template->assign_vars(array(	
		'reslist'		=> $reslist
	));
	$template->show("giveaway.tpl");
}

