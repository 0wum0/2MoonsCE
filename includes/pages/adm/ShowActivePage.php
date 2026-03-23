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

class ShowActivePage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowActivePage');
    }

    protected function run(): void
    {
        global $LNG, $USER;

        $db  = Database::get();
        $uni = Universe::getEmulated();
        $id  = HTTP::_GP('id', 0);

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($id)) {
            $db->delete(
                "DELETE FROM %%USERS_VALID%% WHERE `validationID` = :id AND `universe` = :uni;",
                [':id' => (int) $id, ':uni' => (int) $uni]
            );
        }

        $rows = $db->select(
            "SELECT * FROM %%USERS_VALID%% WHERE `universe` = :uni ORDER BY validationID ASC;",
            [':uni' => (int) $uni]
        );

        $users = [];
        foreach ($rows as $user) {
            $users[] = [
                'id'            => $user['validationID'],
                'name'          => $user['userName'],
                'date'          => _date($LNG['php_tdformat'], $user['date'], $USER['timezone']),
                'email'         => $user['email'],
                'ip'            => $user['ip'],
                'password'      => $user['password'],
                'validationKey' => $user['validationKey'],
            ];
        }

        $this->assign([
            'Users' => $users,
            'uni'   => $uni,
        ]);
        $this->show('ActivePage.twig');
    }
}