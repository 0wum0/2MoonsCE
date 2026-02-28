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
class MathCaptcha
{
    private const SESSION_KEY = 'mathcaptcha';

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::init();
            @session_start();
        }
    }

    public static function generate(): array
    {
        self::ensureSession();
        $a = random_int(1, 12);
        $b = random_int(1, 12);
        $ops = ['+', '-', '*'];
        $op = $ops[array_rand($ops)];

        switch ($op) {
            case '+': $answer = $a + $b; break;
            case '-':
                if ($a < $b) { [$a, $b] = [$b, $a]; }
                $answer = $a - $b;
                break;
            case '*':
                $a = random_int(1, 9);
                $b = random_int(1, 9);
                $answer = $a * $b;
                break;
            default: $answer = $a + $b;
        }

        $token = bin2hex(random_bytes(16));
        $expiry = time() + 1800;

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        self::pruneExpired();

        $_SESSION[self::SESSION_KEY][$token] = [
            'answer' => $answer,
            'expiry' => $expiry,
        ];

        return [
            'question' => $a . ' ' . $op . ' ' . $b,
            'token'    => $token,
        ];
    }

    public static function verify(string $userAnswer, string $token): bool
    {
        self::ensureSession();
        self::pruneExpired();

        if (empty($token) || !isset($_SESSION[self::SESSION_KEY][$token])) {
            return false;
        }

        $slot = $_SESSION[self::SESSION_KEY][$token];
        unset($_SESSION[self::SESSION_KEY][$token]);

        if (time() > $slot['expiry']) {
            return false;
        }

        return (int)$userAnswer === (int)$slot['answer'];
    }

    private static function pruneExpired(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return;
        }
        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] as $t => $slot) {
            if ($now > $slot['expiry']) {
                unset($_SESSION[self::SESSION_KEY][$t]);
            }
        }
    }
}
