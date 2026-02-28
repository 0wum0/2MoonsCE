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
$LNG['mn_username']			= "Pseudo";

/**
 * page.overview.default.tpl
**/
$LNG['ov_online_user']			= "Joueurs en ligne";
$LNG['ov_ticket']				= "Ticket";
$LNG['ov_create_moon']			= "Créer une lune";
$LNG['ov_list_title_build']		= "Bâtiment";
$LNG['ov_list_title_tech']		= "Recherche";
$LNG['ov_list_title_fleet']		= "Flotte/Défense";

/**
 * Admin
 */
// ShowMenuPage.tpl
$LNG['mu_mods_settings'] = "Configurer les Mods";
// ConfigModsBody.tpl
$LNG['msg_expedition'] = "Configuration Expédition";
$LNG['msg_expedition_active'] = "Système de limite";
$LNG['msg_expedition_active_desc'] = "Lors de l'activation de ce système vous pouvez mettre une limite sur les ressource trouver lors d'une expédition par les joueurs";
$LNG['msg_expedition_active_price'] = "Montant des ressources maximum";
$LNG['msg_expedition_active_price_desc'] = "Montant maximum que le joueur peut trouver lors d'une expédition même si la capacité est supérieur";