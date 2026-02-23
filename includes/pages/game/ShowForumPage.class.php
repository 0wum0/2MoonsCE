<?php

declare(strict_types=1);

/**
 * SmartMoons Forum - Frontend Controller
 * FIX: Routing für Like & Edit
 */

class ShowForumPage extends AbstractGamePage
{
    public static $requireModule = 0;
    private Forum $forum;

    function __construct() {
        parent::__construct();
        if (!class_exists('Forum')) {
            require_once ROOT_PATH . 'includes/classes/Forum.class.php';
        }
        $this->forum = new Forum();
    }

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
        $this->assign([
            'mode'            => 'topic',
            'topic'           => $topic,
            'posts'           => $this->forum->getPosts($id, $page, 15),
            'p'               => $page,
            'can_moderate'    => ($USER['authlevel'] >= AUTH_MOD),
            'current_user_id' => (int)$USER['id'],
            'message'         => $message,
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
        if (empty($post)) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $isMod = ((int)$USER['authlevel'] >= AUTH_MOD);
        $isOwner = ((int)$post['user_id'] === (int)$USER['id']);

        if (!$isMod && !$isOwner) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $this->forum->updatePost($postId, (int)$USER['id'], $content);
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
        if (empty($post)) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $isMod   = ((int)$USER['authlevel'] >= AUTH_MOD);
        $isOwner = ((int)$post['user_id'] === (int)$USER['id']);

        if (!$isMod && !$isOwner) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $this->forum->softDeletePost($postId);
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
        if (empty($post) || (int)$post['user_id'] === (int)$USER['id']) {
            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
            return;
        }

        $this->forum->reportPost($postId, (int)$USER['id'], $reason);
        $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId . '#post-' . $postId);
    }
}