<?php
declare(strict_types=1);

/**
 * SmartMoons Config
 * Zentrale DB-Konfiguration
 * Kompatibel zu Database_BC.class.php
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
