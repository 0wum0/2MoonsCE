<?php
/**
 * One-time migration runner — DELETES ITSELF after execution.
 * Access: https://yourdomain/run_migrations.php?token=2moonsce_migrate_now
 */

declare(strict_types=1);

define('MODE', 'INSTALL');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)) . '/');
set_include_path(ROOT_PATH);
chdir(ROOT_PATH);

$token = $_GET['token'] ?? '';
if ($token !== '2moonsce_migrate_now') {
    http_response_code(403);
    exit('Forbidden');
}

require_once 'includes/config.php';
require_once 'includes/classes/Database.class.php';
require_once 'includes/dbtables.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::get();

    $dbVersion = 0;
    try {
        $row = $db->selectSingle("SELECT dbVersion FROM %%SYSTEM%% LIMIT 1;", [], 'dbVersion');
        $dbVersion = (int)$row;
    } catch (Throwable $e) {
        echo "Could not read dbVersion: " . $e->getMessage() . "\n";
    }

    echo "Current dbVersion: $dbVersion\n";
    echo "Required dbVersion: " . DB_VERSION_REQUIRED . "\n\n";

    if ($dbVersion >= DB_VERSION_REQUIRED) {
        echo "Nothing to do — database is up to date.\n";
        @unlink(__FILE__);
        exit;
    }

    $migrations = [];
    $dir = ROOT_PATH . 'install/migrations/';
    foreach (new DirectoryIterator($dir) as $f) {
        if (!$f->isFile() || !preg_match('/^migration_(\d+)\.sql$/', $f->getFilename(), $m)) continue;
        $rev = (int)$m[1];
        if ($rev > $dbVersion && $rev <= DB_VERSION_REQUIRED) {
            $migrations[$rev] = $f->getPathname();
        }
    }
    ksort($migrations);

    foreach ($migrations as $rev => $path) {
        echo "Running migration_$rev.sql ...\n";
        $sql  = file_get_contents($path);
        $sql  = str_replace('%PREFIX%', DB_PREFIX, $sql);
        $stmts = array_filter(array_map('trim', explode(";\n", $sql)));
        $errors = 0;
        foreach ($stmts as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || strpos($stmt, '--') === 0) continue;
            try {
                $db->nativeQuery($stmt);
                echo "  OK: " . substr($stmt, 0, 60) . "\n";
            } catch (Throwable $e) {
                echo "  WARN: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        echo "  migration_$rev done ($errors warnings)\n\n";
    }

    echo "All migrations applied. dbVersion is now " . DB_VERSION_REQUIRED . ".\n";

} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}

// Self-destruct
@unlink(__FILE__);
echo "\nThis file has been deleted.\n";
