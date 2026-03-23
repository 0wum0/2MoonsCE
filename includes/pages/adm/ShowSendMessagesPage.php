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

class ShowSendMessagesPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowSendMessagesPage');
    }

    protected function run(): void
    {
    global $USER, $LNG;

    $db     = Database::get();
    $uni    = Universe::getEmulated();
    $action = HTTP::_GP('action', '');

    if ($action === 'send') {
        $subject = HTTP::_GP('subject', '', true);
        $message = HTTP::_GP('text', '', true);
        $mode    = HTTP::_GP('mode', 0);
        $lang    = HTTP::_GP('globalmessagelang', '');

        if (!empty($message) && !empty($subject)) {
            // Build optional lang filter
            $langSql    = !empty($lang) ? ' AND `lang` = :lang' : '';
            $langParams = !empty($lang) ? [':lang' => $lang] : [];

            if ($mode == 0 || $mode == 2) {
                $from = $LNG['user_level_' . $USER['authlevel']] . ' ' . $USER['username'];

                $users = $db->select(
                    "SELECT `id`, `username` FROM %%USERS%%
                     WHERE `universe` = :uni{$langSql};",
                    array_merge([':uni' => (int) $uni], $langParams)
                );

                foreach ($users as $userData) {
                    $sendMessage = str_replace('{USERNAME}', $userData['username'], $message);
                    PlayerUtil::sendMessage(
                        $userData['id'], $USER['id'], $from,
                        50, $subject, $sendMessage,
                        TIMESTAMP, null, 1, $uni
                    );
                }
            }

            if ($mode == 1 || $mode == 2) {
                require_once 'includes/classes/Mail.class.php';

                $users = $db->select(
                    "SELECT `email`, `username` FROM %%USERS%%
                     WHERE `universe` = :uni{$langSql};",
                    array_merge([':uni' => (int) $uni], $langParams)
                );

                $userList = [];
                foreach ($users as $userData) {
                    $userList[$userData['email']] = [
                        'username' => $userData['username'],
                        'body'     => $bbcode->parse(
                            str_replace('{USERNAME}', $userData['username'], $message)
                        ),
                    ];
                }
                Mail::multiSend($userList, strip_tags($subject));
            }

            exit($LNG['ma_message_sended']);
        } else {
            exit($LNG['ma_subject_needed']);
        }
    }

    $sendModes = $LNG['ma_modes'];
    if (Config::get()->mail_active == 0) {
        unset($sendModes[1]);
        unset($sendModes[2]);
    }

    $this->assign([
        'langSelector' => array_merge(['' => $LNG['ma_all']], $LNG->getAllowedLangs(false)),
        'modes'        => $sendModes,
    ]);
    $this->show('SendMessagesPage.twig');
    }
}