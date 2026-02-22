<?php

declare(strict_types=1);

/**
 * SmartMoons Forum - Frontend Page COMPLETE
 */

class ShowForumPage extends AbstractGamePage
{
    public static $requireModule = 0;
    
    function __construct()
    {
        parent::__construct();
    }
    
    function show()
    {
        global $USER;
        
        // Load Forum Model
        if (!class_exists('Forum')) {
            require_once ROOT_PATH . 'includes/classes/Forum.class.php';
        }
        
        $forum = new Forum();
        
        $mode = HTTP::_GP('mode', 'index');
        $message = [];
        
        // ==================== POST ACTIONS ====================
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // Create Topic
            if (isset($_POST['create_topic'])) {
                $categoryId = (int)HTTP::_GP('category_id', 0);
                $title = HTTP::_GP('title', '');
                $content = HTTP::_GP('content', '');
                
                if ($categoryId > 0 && !empty($title) && !empty($content)) {
                    try {
                        $topicId = $forum->createTopic($categoryId, $USER['id'], $title, $content);
                        $this->redirectTo('?page=forum&mode=topic&id=' . $topicId);
                        return;
                    } catch (Exception $e) {
                        $message = ['class' => 'error', 'text' => 'Fehler: ' . $e->getMessage()];
                    }
                } else {
                    $message = ['class' => 'error', 'text' => 'Bitte fülle alle Felder aus!'];
                }
            }
            
            // Create Post (Reply)
            if (isset($_POST['create_post'])) {
                $topicId = (int)HTTP::_GP('topic_id', 0);
                $content = HTTP::_GP('content', '');
                
                if ($topicId > 0 && !empty($content)) {
                    try {
                        $topic = $forum->getTopic($topicId);
                        
                        if ($topic && !$topic['is_locked']) {
                            $forum->createPost($topicId, $USER['id'], $content);
                            $this->redirectTo('?page=forum&mode=topic&id=' . $topicId);
                            return;
                        } else {
                            $message = ['class' => 'error', 'text' => 'Topic ist gesperrt!'];
                        }
                    } catch (Exception $e) {
                        $message = ['class' => 'error', 'text' => 'Fehler: ' . $e->getMessage()];
                    }
                } else {
                    $message = ['class' => 'error', 'text' => 'Bitte fülle alle Felder aus!'];
                }
            }
            
            // Edit Post
            if (isset($_POST['edit_post'])) {
                $postId = (int)HTTP::_GP('post_id', 0);
                $content = HTTP::_GP('content', '');
                
                if ($postId > 0 && !empty($content)) {
                    try {
                        $db = Database::get();
                        $post = $db->selectSingle("SELECT user_id, topic_id FROM %%FORUM_POSTS%% WHERE id = :id", [':id' => $postId]);
                        
                        if ($post && ($post['user_id'] == $USER['id'] || $USER['authlevel'] >= AUTH_ADM)) {
                            $forum->updatePost($postId, $USER['id'], $content);
                            $this->redirectTo('?page=forum&mode=topic&id=' . $post['topic_id']);
                            return;
                        }
                    } catch (Exception $e) {
                        $message = ['class' => 'error', 'text' => 'Fehler: ' . $e->getMessage()];
                    }
                }
            }
            
            // Like Post
            if (isset($_POST['like_post'])) {
                $postId = (int)HTTP::_GP('post_id', 0);
                
                if ($postId > 0) {
                    try {
                        $forum->toggleLike($postId, $USER['id']);
                        
                        // Return JSON for AJAX
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            $this->sendJSON(['success' => true]);
                            return;
                        }
                    } catch (Exception $e) {
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                            $this->sendJSON(['success' => false, 'error' => $e->getMessage()]);
                            return;
                        }
                    }
                }
            }
        }
        
        // ==================== LOAD DATA ====================
        
        $tplData = [
            'mode' => $mode,
            'message' => $message,
            'user' => $USER,
        ];
        
        try {
            switch ($mode) {
                case 'index':
                    // Forum Overview
                    $tplData['categories'] = $forum->getCategories();
                    
                    $db = Database::get();
                    $tplData['recent_topics'] = $db->select(
                        "SELECT t.*, u.username, c.title as category_title, c.color as category_color
                         FROM %%FORUM_TOPICS%% t
                         LEFT JOIN %%USERS%% u ON t.user_id = u.id
                         LEFT JOIN %%FORUM_CATEGORIES%% c ON t.category_id = c.id
                         ORDER BY t.last_post_time DESC
                         LIMIT 10"
                    );
                    
                    $tplData['stats'] = [
                        'total_topics' => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_TOPICS%%", [], 'c'),
                        'total_posts' => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_POSTS%%", [], 'c'),
                        'total_members' => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%USERS%%", [], 'c'),
                    ];
                    break;
                    
                case 'category':
                    // Category View
                    $categoryId = (int)HTTP::_GP('id', 0);
                    $page = (int)HTTP::_GP('page', 1);
                    
                    if ($categoryId > 0) {
                        $tplData['category'] = $forum->getCategory($categoryId);
                        $tplData['topics'] = $forum->getTopics($categoryId, $page, 20);
                        $tplData['page'] = $page;
                    } else {
                        $this->redirectTo('?page=forum');
                        return;
                    }
                    break;
                    
                case 'topic':
                    // Topic View
                    $topicId = (int)HTTP::_GP('id', 0);
                    $page = (int)HTTP::_GP('page', 1);
                    
                    if ($topicId > 0) {
                        $tplData['topic'] = $forum->getTopic($topicId);
                        $tplData['posts'] = $forum->getPosts($topicId, $page, 15);
                        $tplData['page'] = $page;
                        $tplData['can_moderate'] = ($USER['authlevel'] >= AUTH_ADM);
                    } else {
                        $this->redirectTo('?page=forum');
                        return;
                    }
                    break;
                    
                case 'new_topic':
                    // New Topic Form
                    $categoryId = (int)HTTP::_GP('category_id', 0);
                    
                    if ($categoryId > 0) {
                        $tplData['category'] = $forum->getCategory($categoryId);
                    } else {
                        $this->redirectTo('?page=forum');
                        return;
                    }
                    break;
                    
                case 'edit_post':
                    // Edit Post Form
                    $postId = (int)HTTP::_GP('id', 0);
                    
                    if ($postId > 0) {
                        $db = Database::get();
                        $post = $db->selectSingle(
                            "SELECT p.*, t.title as topic_title, t.id as topic_id
                             FROM %%FORUM_POSTS%% p
                             LEFT JOIN %%FORUM_TOPICS%% t ON p.topic_id = t.id
                             WHERE p.id = :id",
                            [':id' => $postId]
                        );
                        
                        if ($post && ($post['user_id'] == $USER['id'] || $USER['authlevel'] >= AUTH_ADM)) {
                            $tplData['post'] = $post;
                        } else {
                            $this->redirectTo('?page=forum');
                            return;
                        }
                    }
                    break;
                    
                case 'mentions':
                    // User Mentions
                    $tplData['mentions'] = $forum->getUserMentions($USER['id']);
                    
                    foreach ($tplData['mentions'] as $mention) {
                        if (!$mention['is_read']) {
                            $forum->markMentionRead((int)$mention['id']);
                        }
                    }
                    break;
                    
                case 'search':
                    // Search
                    $query = HTTP::_GP('q', '');
                    
                    if (!empty($query)) {
                        $db = Database::get();
                        
                        $tplData['topic_results'] = $db->select(
                            "SELECT t.*, u.username, c.title as category_title
                             FROM %%FORUM_TOPICS%% t
                             LEFT JOIN %%USERS%% u ON t.user_id = u.id
                             LEFT JOIN %%FORUM_CATEGORIES%% c ON t.category_id = c.id
                             WHERE t.title LIKE :query
                             ORDER BY t.created_at DESC
                             LIMIT 20",
                            [':query' => '%' . $query . '%']
                        );
                        
                        $tplData['post_results'] = $db->select(
                            "SELECT p.*, u.username, t.title as topic_title, t.id as topic_id
                             FROM %%FORUM_POSTS%% p
                             LEFT JOIN %%USERS%% u ON p.user_id = u.id
                             LEFT JOIN %%FORUM_TOPICS%% t ON p.topic_id = t.id
                             WHERE p.content LIKE :query AND p.is_deleted = 0
                             ORDER BY p.created_at DESC
                             LIMIT 20",
                            [':query' => '%' . $query . '%']
                        );
                        
                        $tplData['search_query'] = $query;
                    }
                    break;
            }
        } catch (Exception $e) {
            $tplData['error'] = 'Fehler beim Laden: ' . $e->getMessage();
            error_log('Forum Error: ' . $e->getMessage());
        }
        
        $this->assign($tplData);
        $this->display('ForumPage.twig');
    }
}