<?php

declare(strict_types=1);

/**
 * Simple server-side math CAPTCHA — no external dependencies, no Google.
 *
 * Usage:
 *   MathCaptcha::generate()  → stores question in session, returns ['question' => '3 + 7', 'token' => '<hash>']
 *   MathCaptcha::verify($answer, $token) → true/false, clears session slot on success
 */
class MathCaptcha
{
    private const SESSION_KEY = 'mathcaptcha';

    public static function generate(): array
    {
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
