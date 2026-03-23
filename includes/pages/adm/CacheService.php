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

/**
 * CacheService — manages cache directory clearing for the admin panel.
 *
 * Extracted from ShowClearCachePage.php (Phase 7).
 * Single responsibility: know which cache dirs exist, ensure they are
 * present, and safely delete their contents within ROOT_PATH.
 *
 * No DB access. No rendering. Returns a result array only.
 */
class CacheService
{
    /**
     * List of cache directories relative to ROOT_PATH.
     * Missing directories are created automatically before clearing.
     */
    private static array $cacheDirs = [
        'cache/',
        'cache/templates/',
        'cache/twig/',
        'cache/twig/compilation/',
        'cache/twig/cache/',
        'cache/smarty/',
        'cache/sql/',
        'cache/sessions/',
    ];

    /**
     * Clear all known cache directories.
     *
     * @return array{
     *   cleared_files: int,
     *   cleared_dirs:  int,
     *   skipped:       int,
     *   errors:        string[],
     *   paths:         string[]
     * }
     */
    public static function clearAll(): array
    {
        $results = [
            'cleared_files' => 0,
            'cleared_dirs'  => 0,
            'skipped'       => 0,
            'errors'        => [],
            'paths'         => [],
        ];

        $root = defined('ROOT_PATH') ? (string) ROOT_PATH : './';
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';

        $isInsideRoot = static function (string $path) use ($root): bool {
            $pathNorm = str_replace('\\', '/', $path);
            $real     = @realpath($pathNorm);
            if ($real !== false) {
                $realNorm = rtrim(str_replace('\\', '/', $real), '/') . '/';
                return strpos($realNorm, $root) === 0;
            }
            return strpos(rtrim($pathNorm, '/') . '/', $root) === 0;
        };

        $ensureDir = static function (string $dir) use (&$results, $isInsideRoot): bool {
            if (!$isInsideRoot($dir)) {
                $results['errors'][] = 'Refused to create dir outside ROOT_PATH: ' . $dir;
                return false;
            }
            if (is_dir($dir)) {
                return true;
            }
            if (@mkdir($dir, 0775, true) || is_dir($dir)) {
                return true;
            }
            $results['errors'][] = 'Failed to create cache dir: ' . $dir;
            return false;
        };

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
                if ($item === '.htaccess' || $item === 'index.html'
                    || $item === 'index.htm' || $item === 'index.php') {
                    $results['skipped']++;
                    continue;
                }

                $path = rtrim($dir, '/\\') . '/' . $item;

                if (is_link($path)) {
                    $results['skipped']++;
                    continue;
                }

                if (is_dir($path)) {
                    $deleteContents($path);
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

        foreach (self::$cacheDirs as $rel) {
            $dir = $root . $rel;
            $dir = str_replace('\\', '/', $dir);
            $results['paths'][] = $dir;
            $ensureDir($dir);
        }

        foreach (self::$cacheDirs as $rel) {
            $dir = $root . $rel;
            $dir = str_replace('\\', '/', $dir);
            $deleteContents($dir);
        }

        return $results;
    }
}
