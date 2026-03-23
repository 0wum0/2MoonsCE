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

// @admin-migrated (DB: PDO via Database::get())
if (!allowedTo(str_replace([dirname(__FILE__), '\\', '/', '.php'], '', __FILE__))) {
    throw new \Exception('Permission error!');
}

function ShowBanPage(): void
{
    global $LNG, $USER;

    $db  = Database::get();
    $uni = Universe::getEmulated();

    // Allowed sort columns – whitelist to prevent SQL injection via ORDER BY
    $order  = (isset($_GET['order'])  && $_GET['order']  === 'id') ? 'id' : 'username';
    $order2 = (isset($_GET['order2']) && $_GET['order2'] === 'id') ? 'id' : 'username';

    $wherebanaSql = '';
    if (isset($_GET['view']) && $_GET['view'] === 'bana') {
        $wherebanaSql = 'AND `bana` = 1';
    }

    // Build user list for the ban selector
    $userRows = $db->select(
        "SELECT `username`, `id`, `bana`
         FROM %%USERS%%
         WHERE `id` != 1
           AND `authlevel` <= :authlevel
           AND `universe` = :uni
           {$wherebanaSql}
         ORDER BY {$order} ASC;",
        [':authlevel' => (int) $USER['authlevel'], ':uni' => (int) $uni]
    );

    $userSelect = ['List' => '', 'ListBan' => ''];
    $users = 0;

    foreach ($userRows as $a) {
        $banned = ($a['bana'] == 1) ? $LNG['bo_characters_suus'] : '';
        $userSelect['List'] .= '<option value="' . htmlspecialchars($a['username']) . '">'
            . htmlspecialchars($a['username'])
            . '&nbsp;&nbsp;(ID:&nbsp;' . (int) $a['id'] . ')' . $banned
            . '</option>';
        $users++;
    }

    // Build banned-user list for the unban selector
    $bannedRows = $db->select(
        "SELECT `username`, `id`
         FROM %%USERS%%
         WHERE `bana` = 1
           AND `universe` = :uni
         ORDER BY {$order2} ASC;",
        [':uni' => (int) $uni]
    );

    $banneds = 0;
    foreach ($bannedRows as $b) {
        $userSelect['ListBan'] .= '<option value="' . htmlspecialchars($b['username']) . '">'
            . htmlspecialchars($b['username'])
            . '&nbsp;&nbsp;(ID:&nbsp;' . (int) $b['id'] . ')</option>';
        $banneds++;
    }

    $template = new template();
    $template->loadscript('filterlist.js');

    $name    = HTTP::_GP('ban_name', '', true);
    $banUser = $db->selectSingle(
        "SELECT b.theme, b.longer, u.id, u.urlaubs_modus, u.banaday
         FROM %%USERS%% AS u
         LEFT JOIN %%BANNED%% AS b ON u.`username` = b.`who`
         WHERE u.`username` = :name
           AND u.`universe` = :uni;",
        [':name' => $name, ':uni' => (int) $uni]
    );

    if (isset($_POST['panel'])) {
        if ((int) ($banUser['banaday'] ?? 0) <= TIMESTAMP) {
            $title            = $LNG['bo_bbb_title_1'];
            $changedate       = $LNG['bo_bbb_title_2'];
            $changedateAdvert = '';
            $reas             = '';
            $timesus          = '';
        } else {
            $title            = $LNG['bo_bbb_title_3'];
            $changedate       = $LNG['bo_bbb_title_6'];
            $changedateAdvert = '<td class="c" width="18px"><img src="./styles/resource/images/admin/i.gif"'
                . ' class="tooltip" data-tooltip-content="' . $LNG['bo_bbb_title_4'] . '"></td>';
            $reas             = $banUser['theme'] ?? '';
            $timesus          = '<tr>'
                . '<th>' . $LNG['bo_bbb_title_5'] . '</th>'
                . '<th height=25 colspan=2>' . date($LNG['php_tdformat'], (int) $banUser['longer']) . '</th>'
                . '</tr>';
        }

        $vacation = ((int) ($banUser['urlaubs_modus'] ?? 0) === 1);

        $template->assign_vars([
            'name'             => $name,
            'bantitle'         => $title,
            'changedate'       => $changedate,
            'reas'             => $reas,
            'changedate_advert' => $changedateAdvert,
            'timesus'          => $timesus,
            'vacation'         => $vacation,
        ]);

    } elseif (isset($_POST['bannow']) && !empty($banUser['id']) && (int) $banUser['id'] !== 1) {
        $name        = HTTP::_GP('ban_name', '', true);
        $reas        = HTTP::_GP('why', '', true);
        $days        = HTTP::_GP('days', 0);
        $hour        = HTTP::_GP('hour', 0);
        $mins        = HTTP::_GP('mins', 0);
        $secs        = HTTP::_GP('secs', 0);
        $admin       = $USER['username'];
        $mail        = $USER['email'];
        $banTime     = $days * 86400 + $hour * 3600 + $mins * 60 + $secs;

        if ((int) ($banUser['longer'] ?? 0) > TIMESTAMP) {
            $banTime += ((int) $banUser['longer'] - TIMESTAMP);
        }

        $bannedUntil = isset($_POST['permanent'])
            ? 2147483647
            : (($banTime + TIMESTAMP) < TIMESTAMP ? TIMESTAMP : TIMESTAMP + $banTime);

        if ((int) ($banUser['banaday'] ?? 0) > TIMESTAMP) {
            $db->update(
                "UPDATE %%BANNED%%
                 SET `who` = :who, `theme` = :theme, `time` = :time,
                     `longer` = :longer, `author` = :author, `email` = :email
                 WHERE `who2` = :who2 AND `universe` = :uni;",
                [
                    ':who'    => $name,
                    ':theme'  => $reas,
                    ':time'   => TIMESTAMP,
                    ':longer' => $bannedUntil,
                    ':author' => $admin,
                    ':email'  => $mail,
                    ':who2'   => $name,
                    ':uni'    => (int) $uni,
                ]
            );
        } else {
            $db->insert(
                "INSERT INTO %%BANNED%%
                     (`who`, `theme`, `time`, `longer`, `author`, `universe`, `email`)
                 VALUES
                     (:who, :theme, :time, :longer, :author, :uni, :email);",
                [
                    ':who'    => $name,
                    ':theme'  => $reas,
                    ':time'   => TIMESTAMP,
                    ':longer' => $bannedUntil,
                    ':author' => $admin,
                    ':uni'    => (int) $uni,
                    ':email'  => $mail,
                ]
            );
        }

        $vacationMode = isset($_POST['vacat']) ? 1 : 0;
        $db->update(
            "UPDATE %%USERS%%
             SET `bana` = 1, `banaday` = :bannedUntil, `urlaubs_modus` = :vacation
             WHERE `username` = :name AND `universe` = :uni;",
            [
                ':bannedUntil' => $bannedUntil,
                ':vacation'    => $vacationMode,
                ':name'        => $name,
                ':uni'         => (int) $uni,
            ]
        );

        $template->message($LNG['bo_the_player'] . $name . $LNG['bo_banned'], '?page=bans');
        exit;

    } elseif (isset($_POST['unban_name'])) {
        $name = HTTP::_GP('unban_name', '', true);
        $db->update(
            "UPDATE %%USERS%%
             SET bana = 0, banaday = 0
             WHERE username = :name AND `universe` = :uni;",
            [':name' => $name, ':uni' => (int) $uni]
        );
        $template->message($LNG['bo_the_player2'] . $name . $LNG['bo_unbanned'], '?page=bans');
        exit;
    }

    $template->assign_vars([
        'UserSelect' => $userSelect,
        'usercount'  => $users,
        'bancount'   => $banneds,
    ]);

    $template->show('BanPage.twig');
}