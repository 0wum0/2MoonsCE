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
class ShowForumNotifPage extends AbstractGamePage
{
    public static $requireModule = 0;

    private Forum $forum;

    public function __construct()
    {
        parent::__construct();
        if (!class_exists('Forum')) {
            require_once ROOT_PATH . 'includes/classes/Forum.class.php';
        }
        $this->forum = new Forum();
    }

    private function jsonOut(array $data): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function notif_count(): void
    {
        global $USER;
        $this->jsonOut([
            'count' => $this->forum->getForumNotificationCount((int)$USER['id']),
        ]);
    }

    public function notifications(): void
    {
        global $USER;
        $userId = (int)$USER['id'];
        $items  = $this->forum->getForumNotifications($userId, 20);

        $safe = [];
        foreach ($items as $item) {
            $safe[] = [
                'type'        => $item['type'],
                'id'          => $item['id'],
                'post_id'     => $item['post_id'],
                'topic_id'    => $item['topic_id'],
                'topic_title' => htmlspecialchars((string)$item['topic_title'], ENT_QUOTES, 'UTF-8'),
                'by_username' => htmlspecialchars((string)$item['by_username'], ENT_QUOTES, 'UTF-8'),
                'snippet'     => htmlspecialchars((string)$item['snippet'], ENT_QUOTES, 'UTF-8'),
                'created_at'  => (int)$item['created_at'],
            ];
        }

        $this->jsonOut([
            'count' => $this->forum->getForumNotificationCount($userId),
            'items' => $safe,
        ]);
    }

    public function mark_read(): void
    {
        global $USER;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonOut(['ok' => false, 'error' => 'POST required']);
        }
        $type = HTTP::_GP('type', '');
        $id   = (int)HTTP::_GP('id', 0);
        if (!in_array($type, ['mention', 'new_post'], true) || $id <= 0) {
            $this->jsonOut(['ok' => false, 'error' => 'invalid params']);
        }
        $this->forum->markNotificationRead((int)$USER['id'], $type, $id);
        $this->jsonOut(['ok' => true, 'count' => $this->forum->getForumNotificationCount((int)$USER['id'])]);
    }

    public function mark_all_read(): void
    {
        global $USER;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonOut(['ok' => false, 'error' => 'POST required']);
        }
        $this->forum->markAllNotificationsRead((int)$USER['id']);
        $this->jsonOut(['ok' => true, 'count' => 0]);
    }

    public function show(): void
    {
        $this->notif_count();
    }
}
