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

// @admin-migrated (Phase 10 — AbstractAdminPage)

class ShowMultiIPPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowMultiIPPage');
    }

    protected function run(): void
    {
    global $LNG;

    $db     = Database::get();
    $uni    = Universe::getEmulated();
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {
        case 'known':
            $db->insert(
                "INSERT INTO %%MULTI%% SET userID = :id;",
                [':id' => (int) ($_GET['id'] ?? 0)]
            );
            HTTP::redirectTo('admin.php?page=multiips');
            break;

        case 'unknown':
            $db->delete(
                "DELETE FROM %%MULTI%% WHERE userID = :id;",
                [':id' => (int) ($_GET['id'] ?? 0)]
            );
            HTTP::redirectTo('admin.php?page=multiips');
            break;
    }

    $rows = $db->select(
        "SELECT u.id, u.username, u.email, u.register_time, u.onlinetime,
                u.user_lastip, IFNULL(m.multiID, 0) AS isKnown
         FROM %%USERS%% u
         LEFT JOIN %%MULTI%% m ON m.userID = u.id
         WHERE u.`universe` = :uni
           AND u.user_lastip IN (
               SELECT user_lastip FROM %%USERS%%
               WHERE `universe` = :uni2
               GROUP BY user_lastip
               HAVING COUNT(*) > 1
           )
         ORDER BY u.user_lastip, u.id ASC;",
        [':uni' => (int) $uni, ':uni2' => (int) $uni]
    );

    $ips = [];
    foreach ($rows as $data) {
        $ip = $data['user_lastip'];
        if (!isset($ips[$ip])) {
            $ips[$ip] = [];
        }
        $data['register_time'] = _date($LNG['php_tdformat'], $data['register_time']);
        $data['onlinetime']    = _date($LNG['php_tdformat'], $data['onlinetime']);
        $ips[$ip][$data['id']] = $data;
    }

    $this->assign(['multiGroups' => $ips]);
    $this->show('MultiIPs.twig');
    }
}
