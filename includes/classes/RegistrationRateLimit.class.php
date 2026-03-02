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
class RegistrationRateLimit
{
    private const SESSION_KEY  = 'reg_ratelimit';
    private const MAX_ATTEMPTS = 5;
    private const WINDOW       = 3600;

    public static function isAllowed(string $ip): bool
    {
        self::init();
        self::prune();

        $key = md5($ip);
        $attempts = $_SESSION[self::SESSION_KEY][$key]['count'] ?? 0;

        return $attempts < self::MAX_ATTEMPTS;
    }

    public static function record(string $ip): void
    {
        self::init();
        self::prune();

        $key = md5($ip);
        if (!isset($_SESSION[self::SESSION_KEY][$key])) {
            $_SESSION[self::SESSION_KEY][$key] = ['count' => 0, 'first' => time()];
        }
        $_SESSION[self::SESSION_KEY][$key]['count']++;
    }

    public static function getRemainingSeconds(string $ip): int
    {
        self::init();
        $key = md5($ip);
        if (!isset($_SESSION[self::SESSION_KEY][$key])) {
            return 0;
        }
        $elapsed = time() - $_SESSION[self::SESSION_KEY][$key]['first'];
        return max(0, self::WINDOW - $elapsed);
    }

    private static function init(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    private static function prune(): void
    {
        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] as $key => $entry) {
            if ($now - $entry['first'] >= self::WINDOW) {
                unset($_SESSION[self::SESSION_KEY][$key]);
            }
        }
    }
}
