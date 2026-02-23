<?php

declare(strict_types=1);

/**
 * 2Moons 
 * by Jan-Otto Kröpke 2009-2016
 *
 * Cron entrypoint — supports both HTTP (1px GIF) and CLI invocation.
 * CLI usage:  php cronjob.php cronjobID=<id>
 * HTTP usage: GET /cronjob.php?cronjobID=<id>
 */

define('MODE', 'CRON');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)).'/');
set_include_path(ROOT_PATH);

require_once 'includes/common.php';

// Load game vars needed by statbuilder and other cronjobs (resource, reslist, pricelist)
require_once 'includes/vars.php';
require_once 'includes/classes/class.BuildFunctions.php';

require_once 'includes/classes/Cronjob.class.php';

// ── Resolve cronjobID from CLI args or HTTP ──────────────────────────────────
$cronjobID = 0;
if (PHP_SAPI === 'cli') {
    // Accept:  php cronjob.php cronjobID=5   OR   php cronjob.php 5
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (str_starts_with($arg, 'cronjobID=')) {
            $cronjobID = (int) substr($arg, 10);
        } elseif (is_numeric($arg)) {
            $cronjobID = (int) $arg;
        }
    }
} else {
    $cronjobID = HTTP::_GP('cronjobID', 0);
}

// ── HTTP: send 1px transparent GIF immediately so the browser doesn't wait ──
$isImageRequest = !empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'image');
if ($isImageRequest) {
    HTTP::sendHeader('Cache-Control', 'no-cache');
    HTTP::sendHeader('Content-Type', 'image/gif');
    HTTP::sendHeader('Expires', '0');
    echo "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B";
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

if (empty($cronjobID)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Usage: php cronjob.php <cronjobID>\n");
        exit(1);
    }
    exit;
}

// ── Ensure lockTime column exists (safe one-time migration) ──────────────────
try {
    $db = Database::get();
    $db->nativeQuery("ALTER TABLE %%CRONJOBS%% ADD COLUMN IF NOT EXISTS `lockTime` INT(11) NULL DEFAULT NULL;");
} catch (Throwable $e) {
    // Column may already exist or DB doesn't support IF NOT EXISTS — ignore
}

// ── Repair: clear phantom locks (lock set but lockTime NULL, or lockTime expired) ──
try {
    $db = Database::get();
    $expiry = TIMESTAMP - Cronjob::LOCK_EXPIRY_SECONDS;
    $db->update(
        'UPDATE %%CRONJOBS%% SET `lock` = NULL, `lockTime` = NULL WHERE `lock` IS NOT NULL AND (COALESCE(`lockTime`, 0) = 0 OR `lockTime` < :expiry);',
        [':expiry' => $expiry]
    );
} catch (Throwable $e) {
    // Non-fatal — log and continue
    @file_put_contents(ROOT_PATH . 'cache/cron_debug.log',
        '[' . date('Y-m-d H:i:s') . '] WARNING: phantom-lock repair failed: ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

// ── Determine which jobs are due ─────────────────────────────────────────────
$cronjobsTodo = Cronjob::getNeedTodoExecutedJobs();
$isDue        = in_array($cronjobID, $cronjobsTodo, true);

if ($isDue) {
    try {
        Cronjob::execute($cronjobID);
        if (PHP_SAPI === 'cli') {
            echo "Cronjob $cronjobID executed successfully.\n";
        }
    } catch (Throwable $e) {
        $msg = "Cronjob $cronjobID ERROR: " . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine();
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg . "\n");
            exit(1);
        } elseif (!$isImageRequest) {
            echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        }
    }
} else {
    if (PHP_SAPI === 'cli') {
        echo "Cronjob $cronjobID is not due yet.\n";
    }
}
