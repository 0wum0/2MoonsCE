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

$databaseConfig = [
    'host'     => '%s',         // Datenbank-Host
    'port'     => 3306,                // Port (Standard 3306)
    'user'     => '%s',  // Dein DB-Benutzer
    'password' => '%s',      // Dein DB-Passwort
    'dbname'   => '%s',  // Name der Datenbank
    'prefix'   => '%s',             // Tabellenprefix
];

// Salt für Hashing
$salt = 'IbHj8k9p7/MZ.4YQhOuLgV'; // 22 Zeichen ./0-9A-Za-z
