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

function ShowPassEncripterPage()
{
	global $LNG;
	$Password	= HTTP::_GP('md5q', '', true);
	
	$template	= new template();

	$template->assign_vars(array(
		'md5_md5' 			=> $Password,
		'md5_enc' 			=> PlayerUtil::cryptPassword($Password),
		'et_md5_encripter' 	=> $LNG['et_md5_encripter'],
		'et_encript' 		=> $LNG['et_encript'],
		'et_result' 		=> $LNG['et_result'],
		'et_pass' 			=> $LNG['et_pass'],
	));
	
	$template->show('PassEncripterPage.tpl');
}