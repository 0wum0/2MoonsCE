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

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) {
    throw new Exception("Permission error!");
}

/**
 * Safe Cache Clear for SmartMoons (Twig/Legacy compatible)
 * - erstellt fehlende cache-Ordner automatisch
 * - löscht nur Dateien/Unterordner innerhalb definierter Cache-Verzeichnisse
 * - kein scandir() auf nicht existierende Ordner
 */
function ClearCacheSafe(): array
{
    $results = [
        'cleared_files' => 0,
        'cleared_dirs'  => 0,
        'skipped'       => 0,
        'errors'        => [],
        'paths'         => [],
    ];

    // ROOT_PATH ist in 2Moons definiert (admin.php -> includes/common.php)
    $root = defined('ROOT_PATH') ? (string)ROOT_PATH : './';

    // Normiere Root (mit Slash am Ende)
    $root = rtrim(str_replace('\\', '/', $root), '/') . '/';

    // Diese Cache-Ordner sind typisch (Smarty/Twig/Custom). Fehlende werden angelegt.
    $cacheDirs = [
        $root . 'cache/',
        $root . 'cache/templates/',
        $root . 'cache/twig/',
        $root . 'cache/twig/compilation/',
        $root . 'cache/twig/cache/',
        $root . 'cache/smarty/',
        $root . 'cache/sql/',
        $root . 'cache/sessions/', // falls ihr da was cached
    ];

    // Helper: sicheren Pfadcheck (nur innerhalb ROOT_PATH erlaubt)
    $isInsideRoot = static function (string $path) use ($root): bool {
        $pathNorm = str_replace('\\', '/', $path);

        // realpath kann false liefern, wenn der Pfad nicht existiert -> dann manuell prüfen
        $real = @realpath($pathNorm);
        if ($real !== false) {
            $realNorm = rtrim(str_replace('\\', '/', $real), '/') . '/';
            return strpos($realNorm, $root) === 0;
        }

        // Fallback: wenn kein realpath, wenigstens Prefix-Check
        return strpos(rtrim($pathNorm, '/') . '/', $root) === 0;
    };

    // Helper: Ordner sicher anlegen
    $ensureDir = static function (string $dir) use (&$results, $isInsideRoot): bool {
        if (!$isInsideRoot($dir)) {
            $results['errors'][] = 'Refused to create dir outside ROOT_PATH: ' . $dir;
            return false;
        }

        if (is_dir($dir)) {
            return true;
        }

        if (@mkdir($dir, 0775, true)) {
            return true;
        }

        // Nochmal checken, ob parallel erstellt wurde
        if (is_dir($dir)) {
            return true;
        }

        $results['errors'][] = 'Failed to create cache dir: ' . $dir;
        return false;
    };

    // Helper: rekursiv löschen (nur Inhalt, nicht den Root-Ordner selbst)
    $deleteContents = static function (string $dir) use (&$results, $isInsideRoot, &$deleteContents): void {
        if (!is_dir($dir)) {
            $results['skipped']++;
            return;
        }

        if (!$isInsideRoot($dir)) {
            $results['errors'][] = 'Refused to delete outside ROOT_PATH: ' . $dir;
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            $results['errors'][] = 'Cannot scan dir: ' . $dir;
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Sicherheitsdateien nicht löschen (falls vorhanden)
            if ($item === '.htaccess' || $item === 'index.html' || $item === 'index.htm' || $item === 'index.php') {
                $results['skipped']++;
                continue;
            }

            $path = rtrim($dir, '/\\') . '/' . $item;

            // Symlinks niemals verfolgen/löschen (Sicherheit)
            if (is_link($path)) {
                $results['skipped']++;
                continue;
            }

            if (is_dir($path)) {
                $deleteContents($path);
                // Ordner entfernen, wenn leer
                $after = @scandir($path);
                if (is_array($after) && count($after) <= 2) {
                    if (@rmdir($path)) {
                        $results['cleared_dirs']++;
                    } else {
                        $results['skipped']++;
                    }
                }
            } else {
                if (@unlink($path)) {
                    $results['cleared_files']++;
                } else {
                    $results['skipped']++;
                }
            }
        }
    };

    // Erstmal alle Cache-Dirs sicherstellen (damit später nix “No such file” wirft)
    foreach ($cacheDirs as $dir) {
        $dir = str_replace('\\', '/', $dir);
        $results['paths'][] = $dir;
        $ensureDir($dir);
    }

    // Dann Inhalte löschen
    foreach ($cacheDirs as $dir) {
        $dir = str_replace('\\', '/', $dir);
        $deleteContents($dir);
    }

    return $results;
}

function ShowClearCachePage()
{
    global $LNG;

    // ✅ NICHT mehr das alte ClearCache() aufrufen (das knallt bei fehlenden Ordnern)
    $res = ClearCacheSafe();

    $msg = $LNG['cc_cache_clear'] ?? 'Cache wurde geleert.';
    // Debug-Info optional anhängen (wenn du willst, kannst du das später rausnehmen)
    $msg .= '<br><br><small style="opacity:.8;">'
          . 'Gelöscht: ' . (int)$res['cleared_files'] . ' Dateien, '
          . (int)$res['cleared_dirs'] . ' Ordner'
          . ($res['skipped'] ? ' | Übersprungen: ' . (int)$res['skipped'] : '')
          . '</small>';

    if (!empty($res['errors'])) {
        $msg .= '<br><br><small style="color:#f87171;">'
              . 'Hinweise: ' . htmlspecialchars(implode(' | ', $res['errors']), ENT_QUOTES, 'UTF-8')
              . '</small>';
    }

    $template = new template();
    $template->message($msg);
}