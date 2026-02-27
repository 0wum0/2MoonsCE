<?php

declare(strict_types=1);

/**
 * IP-based rate limiting for the registration form.
 *
 * Stores attempt counts in $_SESSION keyed by IP.
 * Max 5 attempts per IP per hour.
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
