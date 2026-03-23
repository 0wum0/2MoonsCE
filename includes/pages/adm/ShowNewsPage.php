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

// @admin-migrated (Phase 7 — AbstractAdminPage + NewsRepository)
require_once 'includes/pages/adm/NewsRepository.php';

/**
 * News management page.
 * Request parsing, date formatting, and template assignment here.
 * All SQL lives in NewsRepository.
 */
class ShowNewsPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowNewsPage');
    }

    protected function run(): void
    {
        global $LNG, $USER;

        $repo   = new NewsRepository();
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        if ($action === 'send') {
            $editId = HTTP::_GP('id', 0);
            $title  = HTTP::_GP('title', '', true);
            $text   = HTTP::_GP('text', '', true);
            $mode   = (int) ($_GET['mode'] ?? 0);

            if ($mode === 2) {
                $repo->create($USER['username'], $title, $text);
            } else {
                $repo->update((int) $editId, $title, $text);
            }
        } elseif ($action === 'delete' && isset($_GET['id'])) {
            $repo->delete(HTTP::_GP('id', 0));
        }

        $newsList = [];
        foreach ($repo->findAll() as $u) {
            $newsList[] = [
                'id'      => $u['id'],
                'title'   => $u['title'],
                'date'    => _date($LNG['php_tdformat'], $u['date'], $USER['timezone']),
                'user'    => $u['user'],
                'confirm' => sprintf($LNG['nws_confirm'], $u['title']),
            ];
        }

        if ($action === 'edit' && isset($_GET['id'])) {
            $news = $repo->findById(HTTP::_GP('id', 0));
            if ($news !== null) {
                $this->assign([
                    'mode'       => 1,
                    'nws_head'   => sprintf($LNG['nws_head_edit'], $news['title']),
                    'news_id'    => $news['id'],
                    'news_title' => $news['title'],
                    'news_text'  => $news['text'],
                ]);
            }
        } elseif ($action === 'create') {
            $this->assign([
                'mode'       => 2,
                'nws_head'   => $LNG['nws_head_create'],
                'news_title' => '',
                'news_text'  => '',
            ]);
        }

        $this->assign([
            'NewsList'      => $newsList,
            'button_submit' => $LNG['button_submit'],
            'nws_total'     => sprintf($LNG['nws_total'], count($newsList)),
            'nws_news'      => $LNG['nws_news'],
            'nws_id'        => $LNG['nws_id'],
            'nws_title'     => $LNG['nws_title'],
            'nws_date'      => $LNG['nws_date'],
            'nws_from'      => $LNG['nws_from'],
            'nws_del'       => $LNG['nws_del'],
            'nws_create'    => $LNG['nws_create'],
            'nws_content'   => $LNG['nws_content'],
        ]);

        $this->show('NewsPage.twig');
    }
}