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

// @admin-migrated (Phase 7 — AbstractAdminPage + BanService)
require_once 'includes/pages/adm/BanService.php';

/**
 * Ban/unban management page.
 * Request parsing and template assignment here; all DB access in BanService.
 */
class ShowBanPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowBanPage');
    }

    protected function run(): void
    {
        global $LNG, $USER;

        $uni     = Universe::getEmulated();
        $service = new BanService((int) $uni);

        // Sort order whitelist
        $order  = (isset($_GET['order'])  && $_GET['order']  === 'id') ? 'id' : 'username';
        $order2 = (isset($_GET['order2']) && $_GET['order2'] === 'id') ? 'id' : 'username';
        $bannedOnly = isset($_GET['view']) && $_GET['view'] === 'bana';

        // Build user list for ban selector
        $userRows   = $service->getUserList((int) $USER['authlevel'], $order, $bannedOnly);
        $userSelect = ['List' => '', 'ListBan' => ''];
        $users      = 0;

        foreach ($userRows as $a) {
            $banned = ($a['bana'] == 1) ? $LNG['bo_characters_suus'] : '';
            $userSelect['List'] .= '<option value="' . htmlspecialchars($a['username']) . '">'
                . htmlspecialchars($a['username'])
                . '&nbsp;&nbsp;(ID:&nbsp;' . (int) $a['id'] . ')' . $banned
                . '</option>';
            $users++;
        }

        // Build banned-user list for unban selector
        $bannedRows = $service->getBannedList($order2);
        $banneds    = 0;
        foreach ($bannedRows as $b) {
            $userSelect['ListBan'] .= '<option value="' . htmlspecialchars($b['username']) . '">'
                . htmlspecialchars($b['username'])
                . '&nbsp;&nbsp;(ID:&nbsp;' . (int) $b['id'] . ')</option>';
            $banneds++;
        }

        $this->loadScript('filterlist.js');

        $name    = HTTP::_GP('ban_name', '', true);
        $banUser = $service->getBanRecord($name);

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

            $this->assign([
                'name'              => $name,
                'bantitle'          => $title,
                'changedate'        => $changedate,
                'reas'              => $reas,
                'changedate_advert' => $changedateAdvert,
                'timesus'           => $timesus,
                'vacation'          => ((int) ($banUser['urlaubs_modus'] ?? 0) === 1),
            ]);

        } elseif (isset($_POST['bannow']) && !empty($banUser['id']) && (int) $banUser['id'] !== 1) {
            $service->banUser($name, [
                'reason'         => HTTP::_GP('why', '', true),
                'days'           => HTTP::_GP('days', 0),
                'hours'          => HTTP::_GP('hour', 0),
                'mins'           => HTTP::_GP('mins', 0),
                'secs'           => HTTP::_GP('secs', 0),
                'permanent'      => isset($_POST['permanent']),
                'vacation'       => isset($_POST['vacat']),
                'admin'          => $USER['username'],
                'email'          => $USER['email'],
                'existingLonger' => (int) ($banUser['longer'] ?? 0),
                'currentBanaday' => (int) ($banUser['banaday'] ?? 0),
            ]);

            $this->message($LNG['bo_the_player'] . $name . $LNG['bo_banned'], '?page=bans');
            return;

        } elseif (isset($_POST['unban_name'])) {
            $unbanName = HTTP::_GP('unban_name', '', true);
            $service->unbanUser($unbanName);
            $this->message($LNG['bo_the_player2'] . $unbanName . $LNG['bo_unbanned'], '?page=bans');
            return;
        }

        $this->assign([
            'UserSelect' => $userSelect,
            'usercount'  => $users,
            'bancount'   => $banneds,
        ]);

        $this->show('BanPage.twig');
    }
}