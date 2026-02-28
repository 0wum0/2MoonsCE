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

class ShowForumPage extends AbstractGamePage
{
    public static $requireModule = MODULE_FORUM;
    private Forum $forum;

    function __construct() {
        parent::__construct();
        if (!class_exists('Forum')) {
            require_once ROOT_PATH . 'includes/classes/Forum.class.php';
        }
        $this->forum = new Forum();
    }

    // ── Permission helpers ────────────────────────────────────────────────────

    /**
     * True if the user has moderator-level access or higher.
     * Source: $USER['authlevel'] >= AUTH_MOD (= 1).
     * Same check used by ShowForumAdminPage and the existing topic() method.
     */
    private function canModerateForum(array $user): bool
    {
        return (int)$user['authlevel'] >= AUTH_MOD;
    }

    /**
     * Moderators can edit any post; players can only edit their own.
     */
    private function canEditPost(array $user, array $post): bool
    {
        return $this->canModerateForum($user)
            || (int)$post['user_id'] === (int)$user['id'];
    }

    /**
     * Moderators can delete any post; players can only delete their own.
     */
    private function canDeletePost(array $user, array $post): bool
    {
        return $this->canModerateForum($user)
            || (int)$post['user_id'] === (int)$user['id'];
    }

    /**
     * Any logged-in user can report a post that is not their own.
     * Moderators can also report (they may want to escalate to admins),
     * but reporting your own post is silently blocked.
     */
    private function canReportPost(array $user, array $post): bool
    {
        return (int)$post['user_id'] !== (int)$user['id'];
    }

    // ── Public page actions ───────────────────────────────────────────────────

    public function show(): void {
        global $USER;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
            $topicId = (int)HTTP::_GP('topic_id', 0);
            $content  = HTTP::_GP('content', '', true);
            if ($topicId > 0 && $content !== '') {
                $topic = $this->forum->getTopic($topicId);
                if ($topic && empty($topic['is_locked'])) {
                    $this->forum->createPost($topicId, (int)$USER['id'], $content);
                }
            }
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic'])) {
            $catId   = (int)HTTP::_GP('category_id', 0);
            $title   = HTTP::_GP('title', '', true);
            $content = HTTP::_GP('content', '', true);
            if ($catId > 0 && $title !== '' && $content !== '') {
                $topicId = $this->forum->createTopic($catId, (int)$USER['id'], $title, $content);
                $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
                return;
            }
        }
        $this->assign([
            'mode'       => 'index',
            'categories' => $this->forum->getCategories(),
            'message'    => [],
        ]);
        $this->display('ForumPage.twig');
    }

    public function category(): void {
        $id   = (int)HTTP::_GP('id', 0);
        $page = max(1, (int)HTTP::_GP('p', 1));
        if ($id <= 0) {
            $this->redirectTo('game.php?page=forum');
            return;
        }
        $category = $this->forum->getCategory($id);
        if (empty($category)) {
            $this->redirectTo('game.php?page=forum');
            return;
        }
        $this->assign([
            'mode'     => 'category',
            'category' => $category,
            'topics'   => $this->forum->getTopics($id, $page, 20),
            'p'        => $page,
            'message'  => [],
        ]);
        $this->display('ForumPage.twig');
    }

    public function topic(): void {
        global $USER;
        $id   = (int)HTTP::_GP('id', 0);
        $page = max(1, (int)HTTP::_GP('p', 1));
        if ($id <= 0) {
            $this->redirectTo('game.php?page=forum');
            return;
        }
        $message = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
            $content = HTTP::_GP('content', '', true);
            if ($content !== '') {
                $topic = $this->forum->getTopic($id);
                if ($topic && empty($topic['is_locked'])) {
                    $this->forum->createPost($id, (int)$USER['id'], $content);
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        $this->sendJSON(['ok' => true, 'resources' => []]);
                    }
                    $this->redirectTo('game.php?page=forum&mode=topic&id=' . $id);
                    return;
                }
                $message = ['class' => 'error', 'text' => 'Topic ist gesperrt oder existiert nicht.'];
            } else {
                $message = ['class' => 'error', 'text' => 'Inhalt darf nicht leer sein.'];
            }
        }
        $topic = $this->forum->getTopic($id);
        if (empty($topic)) {
            $this->redirectTo('game.php?page=forum');
            return;
        }

        $this->forum->markTopicRead($id, (int)$USER['id']);

        $canModerate = $this->canModerateForum($USER);
        $currentUserId = (int)$USER['id'];

        $posts = $this->forum->getPosts($id, $page, 15);
        foreach ($posts as &$post) {
            $post['can_edit']   = $this->canEditPost($USER, $post);
            $post['can_delete'] = $this->canDeletePost($USER, $post);
            $post['can_report'] = $this->canReportPost($USER, $post);
        }
        unset($post);

        $this->assign([
            'mode'             => 'topic',
            'topic'            => $topic,
            'posts'            => $posts,
            'p'                => $page,
            'can_moderate'     => $canModerate,
            'current_user_id'  => $currentUserId,
            'message'          => $message,
        ]);
        $this->display('ForumPage.twig');
    }

    public function new_topic(): void {
        global $USER;
        $catId = (int)HTTP::_GP('category_id', 0);
        if ($catId <= 0) {
            $this->redirectTo('game.php?page=forum');
            return;
        }
        $message = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic'])) {
            $title   = HTTP::_GP('title', '', true);
            $content = HTTP::_GP('content', '', true);
            if ($title !== '' && $content !== '') {
                $topicId = $this->forum->createTopic($catId, (int)$USER['id'], $title, $content);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    $this->sendJSON(['ok' => true, 'topicId' => $topicId]);
                }
                $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
                return;
            }
            $message = ['class' => 'error', 'text' => 'Bitte fülle alle Felder aus.'];
        }
        $category = $this->forum->getCategory($catId);
        if (empty($category)) {
            $this->redirectTo('game.php?page=forum');
            return;
        }
        $this->assign([
            'mode'     => 'new_topic',
            'category' => $category,
            'message'  => $message,
        ]);
        $this->display('ForumPage.twig');
    }

    public function like(): void {
        global $USER;
        $postId = (int)HTTP::_GP('post_id', 0);
        if ($postId > 0) {
            $isLiked = $this->forum->toggleLike($postId, (int)$USER['id']);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'liked' => $isLiked]);
            exit;
        }
    }

    public function edit_post(): void {
        global $USER;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectTo('game.php?page=forum');
            return;
        }
        $postId  = (int)HTTP::_GP('post_id', 0);
        $content = HTTP::_GP('content', '', true);
        $topicId = (int)HTTP::_GP('topic_id', 0);

        if ($postId <= 0 || $content === '') {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $post = $this->forum->getPost($postId);
        if (empty($post) || !$this->canEditPost($USER, $post)) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $this->forum->updatePost($postId, (int)$USER['id'], $content);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->sendAjaxSuccess();
        }
        $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId . '#post-' . $postId);
    }

    public function delete_post(): void {
        global $USER;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectTo('game.php?page=forum');
            return;
        }
        $postId  = (int)HTTP::_GP('post_id', 0);
        $topicId = (int)HTTP::_GP('topic_id', 0);

        if ($postId <= 0) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $post = $this->forum->getPost($postId);
        if (empty($post) || !$this->canDeletePost($USER, $post)) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $this->forum->softDeletePost($postId);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->sendAjaxSuccess();
        }
        $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
    }

    public function report_post(): void {
        global $USER;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectTo('game.php?page=forum');
            return;
        }
        $postId  = (int)HTTP::_GP('post_id', 0);
        $topicId = (int)HTTP::_GP('topic_id', 0);
        $reason  = HTTP::_GP('reason', '', true);

        if ($postId <= 0) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $post = $this->forum->getPost($postId);
        if (empty($post) || !$this->canReportPost($USER, $post)) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $this->forum->reportPost($postId, (int)$USER['id'], $reason);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->sendAjaxSuccess();
        }
        $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId . '#post-' . $postId);
    }
}