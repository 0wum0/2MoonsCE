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
 * NewsRepository — all CRUD operations on the %%NEWS%% table.
 *
 * Extracted from ShowNewsPage.php (Phase 7).
 * Single responsibility: read and write news records. No rendering,
 * no request parsing, no LNG formatting.
 *
 * Callers are responsible for reading HTTP input, formatting dates,
 * and assigning template variables.
 */
class NewsRepository
{
    /**
     * Returns all news entries ordered by id ascending.
     *
     * @return array<int, array{id: int, title: string, text: string, date: int, user: string}>
     */
    public function findAll(): array
    {
        return Database::get()->select("SELECT * FROM %%NEWS%% ORDER BY id ASC;");
    }

    /**
     * Returns a single news entry by id, or null if not found.
     *
     * @return array{id: int, title: string, text: string}|null
     */
    public function findById(int $id): ?array
    {
        $row = Database::get()->selectSingle(
            "SELECT id, title, text FROM %%NEWS%% WHERE id = :id;",
            [':id' => $id]
        );

        return $row ?: null;
    }

    /**
     * Creates a new news entry.
     *
     * @param string $user   Username of the admin creating the entry.
     * @param string $title  News title.
     * @param string $text   News body text.
     */
    public function create(string $user, string $title, string $text): void
    {
        Database::get()->insert(
            "INSERT INTO %%NEWS%% (`id`, `user`, `date`, `title`, `text`)
             VALUES (NULL, :user, :date, :title, :text);",
            [
                ':user'  => $user,
                ':date'  => TIMESTAMP,
                ':title' => self::safe4byte($title),
                ':text'  => self::safe4byte($text),
            ]
        );
    }

    /**
     * Updates an existing news entry's title and text.
     * The date is refreshed to the current timestamp on every update.
     *
     * @param int    $id    News entry id.
     * @param string $title Updated title.
     * @param string $text  Updated body text.
     */
    public function update(int $id, string $title, string $text): void
    {
        Database::get()->update(
            "UPDATE %%NEWS%% SET `title` = :title, `text` = :text, `date` = :date
             WHERE `id` = :id LIMIT 1;",
            [
                ':title' => self::safe4byte($title),
                ':text'  => self::safe4byte($text),
                ':date'  => TIMESTAMP,
                ':id'    => $id,
            ]
        );
    }

    /**
     * Strips 4-byte UTF-8 sequences (emoji etc.) that are rejected by columns
     * still using the 3-byte utf8 charset. Safe to call after migration_17
     * because once the table is utf8mb4 the value passes through the PDO driver
     * without hitting this path — the column accepts all codepoints natively.
     */
    private static function safe4byte(string $value): string
    {
        return (string) preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', $value);
    }

    /**
     * Deletes a news entry by id.
     *
     * @param int $id News entry id.
     */
    public function delete(int $id): void
    {
        Database::get()->delete(
            "DELETE FROM %%NEWS%% WHERE `id` = :id;",
            [':id' => $id]
        );
    }
}
