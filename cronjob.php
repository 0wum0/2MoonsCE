<?php

declare(strict_types=1);

/**
 * 2Moons 
 * by Jan-Otto Kröpke 2009-2016
 */

define('MODE', 'CRON');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)).'/');
set_include_path(ROOT_PATH);

require_once 'includes/common.php';

// Wir laden die Session, aber wir brechen NICHT ab, wenn sie fehlt (für System-Crons)
$session = Session::load();

$cronjobID = HTTP::_GP('cronjobID', 0);

// Falls der Aufruf über das 1px Bild kommt (Browser), senden wir das GIF
if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'image')) {
    HTTP::sendHeader('Cache-Control', 'no-cache');
    HTTP::sendHeader('Content-Type', 'image/gif');
    HTTP::sendHeader('Expires', '0');
    echo("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
}

if(empty($cronjobID))
{
    exit;
}

require_once 'includes/classes/Cronjob.class.php';

// Prüfen, welche Jobs fällig sind
$cronjobsTodo = Cronjob::getNeedTodoExecutedJobs();

// Wenn der Job fällig ist ODER wenn wir explizit im Browser testen (Admin ist eingeloggt)
if(in_array($cronjobID, $cronjobsTodo) || ($session->isValidSession() && $session->adminAccess))
{
    try {
        Cronjob::execute($cronjobID);
        if (!$session->isValidSession()) {
            echo "Cronjob ".$cronjobID." erfolgreich ausgeführt.";
        }
    } catch (Exception $e) {
        if ($session->isValidSession()) {
            echo "Fehler im Cronjob: ".$e->getMessage();
        }
    }
}
