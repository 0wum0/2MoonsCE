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
 * BanService — encapsulates all ban/unban business logic and database access.
 *
 * Extracted from ShowBanPage.php (Phase 7).
 * Single responsibility: read and write ban records, calculate ban durations,
 * and update user ban state. No rendering, no request parsing.
 *
 * Callers are responsible for reading HTTP input and assigning template vars.
 */
class BanService
{
    private int $universe;

    public function __construct(int $universe)
    {
        $this->universe = $universe;
    }

    /**
     * Returns a list of all users eligible to be shown in the ban selector.
     *
     * @param int    $callerAuthLevel  Only users with authlevel <= this value are shown.
     * @param string $order            'id' or 'username' (SQL-safe whitelist enforced internally).
     * @param bool   $bannedOnly       When true, only already-banned users are returned.
     * @return array<int, array{username: string, id: int, bana: int}>
     */
    public function getUserList(int $callerAuthLevel, string $order = 'username', bool $bannedOnly = false): array
    {
        $order        = ($order === 'id') ? 'id' : 'username';
        $bannedFilter = $bannedOnly ? 'AND `bana` = 1' : '';

        return Database::get()->select(
            "SELECT `username`, `id`, `bana`
             FROM %%USERS%%
             WHERE `id` != 1
               AND `authlevel` <= :authlevel
               AND `universe` = :uni
               {$bannedFilter}
             ORDER BY {$order} ASC;",
            [':authlevel' => $callerAuthLevel, ':uni' => $this->universe]
        );
    }

    /**
     * Returns a list of currently banned users for the unban selector.
     *
     * @param string $order 'id' or 'username'
     * @return array<int, array{username: string, id: int}>
     */
    public function getBannedList(string $order = 'username'): array
    {
        $order = ($order === 'id') ? 'id' : 'username';

        return Database::get()->select(
            "SELECT `username`, `id`
             FROM %%USERS%%
             WHERE `bana` = 1
               AND `universe` = :uni
             ORDER BY {$order} ASC;",
            [':uni' => $this->universe]
        );
    }

    /**
     * Returns the combined ban record + user info for a given username.
     * Returns null when the user does not exist in this universe.
     *
     * @return array{theme: string|null, longer: int|null, id: int, urlaubs_modus: int, banaday: int}|null
     */
    public function getBanRecord(string $username): ?array
    {
        $row = Database::get()->selectSingle(
            "SELECT b.theme, b.longer, u.id, u.urlaubs_modus, u.banaday
             FROM %%USERS%% AS u
             LEFT JOIN %%BANNED%% AS b ON u.`username` = b.`who`
             WHERE u.`username` = :name
               AND u.`universe` = :uni;",
            [':name' => $username, ':uni' => $this->universe]
        );

        return $row ?: null;
    }

    /**
     * Bans a user.
     *
     * Calculates the final ban-until timestamp from the supplied duration
     * components, then writes to %%BANNED%% (insert or update) and sets
     * bana=1 on %%USERS%%.
     *
     * @param string $username     The username to ban.
     * @param array  $params {
     *   reason:    string,
     *   days:      int,
     *   hours:     int,
     *   mins:      int,
     *   secs:      int,
     *   permanent: bool,
     *   vacation:  bool,
     *   admin:     string  — admin username performing the ban,
     *   email:     string  — admin email,
     *   existingLonger: int — current ban end timestamp (0 if not banned)
     * }
     */
    public function banUser(string $username, array $params): void
    {
        $db = Database::get();

        $banTime = (int) $params['days']  * 86400
                 + (int) $params['hours'] * 3600
                 + (int) $params['mins']  * 60
                 + (int) $params['secs'];

        // Stack on top of any existing remaining ban time
        $existingLonger = (int) ($params['existingLonger'] ?? 0);
        if ($existingLonger > TIMESTAMP) {
            $banTime += $existingLonger - TIMESTAMP;
        }

        if ($params['permanent'] ?? false) {
            $bannedUntil = 2147483647;
        } else {
            $calculated = TIMESTAMP + $banTime;
            $bannedUntil = ($calculated < TIMESTAMP) ? TIMESTAMP : $calculated;
        }

        $vacationMode  = ($params['vacation'] ?? false) ? 1 : 0;
        $currentBanaday = (int) ($params['currentBanaday'] ?? 0);

        if ($currentBanaday > TIMESTAMP) {
            $db->update(
                "UPDATE %%BANNED%%
                 SET `who` = :who, `theme` = :theme, `time` = :time,
                     `longer` = :longer, `author` = :author, `email` = :email
                 WHERE `who2` = :who2 AND `universe` = :uni;",
                [
                    ':who'    => $username,
                    ':theme'  => $params['reason'],
                    ':time'   => TIMESTAMP,
                    ':longer' => $bannedUntil,
                    ':author' => $params['admin'],
                    ':email'  => $params['email'],
                    ':who2'   => $username,
                    ':uni'    => $this->universe,
                ]
            );
        } else {
            $db->insert(
                "INSERT INTO %%BANNED%%
                     (`who`, `theme`, `time`, `longer`, `author`, `universe`, `email`)
                 VALUES
                     (:who, :theme, :time, :longer, :author, :uni, :email);",
                [
                    ':who'    => $username,
                    ':theme'  => $params['reason'],
                    ':time'   => TIMESTAMP,
                    ':longer' => $bannedUntil,
                    ':author' => $params['admin'],
                    ':uni'    => $this->universe,
                    ':email'  => $params['email'],
                ]
            );
        }

        $db->update(
            "UPDATE %%USERS%%
             SET `bana` = 1, `banaday` = :bannedUntil, `urlaubs_modus` = :vacation
             WHERE `username` = :name AND `universe` = :uni;",
            [
                ':bannedUntil' => $bannedUntil,
                ':vacation'    => $vacationMode,
                ':name'        => $username,
                ':uni'         => $this->universe,
            ]
        );
    }

    /**
     * Unbans a user by clearing bana flag and banaday.
     *
     * @param string $username The username to unban.
     */
    public function unbanUser(string $username): void
    {
        Database::get()->update(
            "UPDATE %%USERS%%
             SET bana = 0, banaday = 0
             WHERE username = :name AND `universe` = :uni;",
            [':name' => $username, ':uni' => $this->universe]
        );
    }
}
