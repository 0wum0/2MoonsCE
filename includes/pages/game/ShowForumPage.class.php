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
        $this->assign([
            'mode' => 'index',
            'categories' => $this->forum->getCategories()
        ]);
        $this->display('ForumPage.twig');
    }

    public function category(): void {
        $id = (int)HTTP::_GP('id', 0);
        $this->assign([
            'mode' => 'category',
            'category' => $this->forum->getCategory($id),
            'topics' => $this->forum->getTopics($id, (int)HTTP::_GP('site', 1), 20),
        ]);
        $this->display('ForumPage.twig');
    }

    public function topic(): void {
        global $USER;
        $id = (int)HTTP::_GP('id', 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
            $this->forum->createPost($id, (int)$USER['id'], HTTP::_GP('content', '', true));
            $this->redirectTo('game.php?page=forum&mode=topic&id='.$id);
            return;
        }
        $this->assign([
            'mode' => 'topic',
            'topic' => $this->forum->getTopic($id),
            'posts' => $this->forum->getPosts($id, (int)HTTP::_GP('site', 1), 15),
            'can_moderate' => ($USER['authlevel'] >= AUTH_MOD),
            'current_user_id' => $USER['id']
        ]);
        $this->display('ForumPage.twig');
    }

    public function new_topic(): void {
        global $USER;
        $catId = (int)HTTP::_GP('category_id', 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic'])) {
            $topicId = $this->forum->createTopic($catId, (int)$USER['id'], HTTP::_GP('title', '', true), HTTP::_GP('content', '', true));
            $this->redirectTo('game.php?page=forum&mode=topic&id='.$topicId);
            return;
        }
        $this->assign(['mode' => 'new_topic', 'category' => $this->forum->getCategory($catId)]);
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
}