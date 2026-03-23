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

// @admin-migrated (Phase 8 — AbstractAdminPage + PDO; SQL injection fixed)

/**
 * AJAX autocomplete endpoint: returns a JSON array of username suggestions.
 * Accepts ?term=<search> — prefix '#' searches by user ID.
 */
class ShowAutoCompletePage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowAutoCompletePage');
    }

    protected function run(): void
    {
        $searchText = HTTP::_GP('term', '', UTF8_SUPPORT);

        if (empty($searchText) || $searchText === '#') {
            $this->sendJSON([]);
        }

        $db  = Database::get();
        $uni = (int) Universe::getEmulated();

        if (str_starts_with($searchText, '#')) {
            $userId = (int) substr($searchText, 1);
            $rows = $db->select(
                "SELECT id, username FROM %%USERS%%
                 WHERE universe = :uni AND id = :id
                 ORDER BY id ASC
                 LIMIT 20;",
                [':uni' => $uni, ':id' => $userId]
            );
        } else {
            $rows = $db->select(
                "SELECT id, username FROM %%USERS%%
                 WHERE universe = :uni
                   AND username LIKE :term
                 ORDER BY
                   (IF(username = :exact, 1, 0) + IF(username LIKE :prefix, 1, 0)) DESC,
                   username ASC
                 LIMIT 20;",
                [
                    ':uni'    => $uni,
                    ':term'   => '%' . $searchText . '%',
                    ':exact'  => $searchText,
                    ':prefix' => $searchText . '%',
                ]
            );
        }

        $searchList = [];
        foreach ($rows as $row) {
            $searchList[] = [
                'label' => $row['username'] . ' (ID:' . $row['id'] . ')',
                'value' => $row['username'],
            ];
        }

        $this->sendJSON($searchList);
    }
}