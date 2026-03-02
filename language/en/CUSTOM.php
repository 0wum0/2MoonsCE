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
 
// If you have custom lang vars, you can include them here. The file ll be not overwrite by updatemanager
// Also you can overwrite exists var, too. You use the same key.

/**
 * main.navigation.tpl
**/
$LNG['mn_username']			= "Username";

/**
 * page.overview.default.tpl
**/
$LNG['ov_online_user']			= "Players online";
$LNG['ov_ticket']				= "Ticket";
$LNG['ov_create_moon']			= "Create moon";
$LNG['ov_list_title_build']		= "Building";
$LNG['ov_list_title_tech']		= "Research";
$LNG['ov_list_title_fleet']		= "Fleet/Defense";

/**
 * Admin
 */
// ShowMenuPage.tpl
$LNG['mu_mods_settings'] = "Configure Mods";
// ConfigModsBody.tpl
$LNG['msg_expedition'] = "Shipping Configuration";
$LNG['msg_expedition_active'] = "Limit system";
$LNG['msg_expedition_active_desc'] = "When activating this system you can put a limit on the resources to find during an expedition by the players";
$LNG['msg_expedition_active_price'] = "Maximum amount of resources";
$LNG['msg_expedition_active_price_desc'] = "Maximum amount the player can find on an expedition even if the capacity is higher";