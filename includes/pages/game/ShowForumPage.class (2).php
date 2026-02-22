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
                        $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
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
                        
                        if ($topic && empty($topic['is_locked'])) {
                            $forum->createPost($topicId, $USER['id'], $content);
                            $this->redirectTo('game.php?page=forum&mode=topic&id=' . $topicId);
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
        }
        
        // ==================== LOAD DATA ====================
        
        $tplData = [
            'mode'    => $mode,
            'message' => $message,
            'user'    => $USER,
        ];
        
        try {
            switch ($mode) {
                case 'index':
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
                        'total_topics'   => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_TOPICS%%", [], 'c'),
                        'total_posts'    => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_POSTS%%", [], 'c'),
                        'total_members'  => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%USERS%%", [], 'c'),
                    ];
                    break;
                    
                case 'category':
                    $categoryId = (int)HTTP::_GP('id', 0);

                    // IMPORTANT: "page" is router param in 2Moons -> use "p" for pagination
                    $currentPage = (int)HTTP::_GP('p', 1);
                    $currentPage = max(1, $currentPage);
                    
                    if ($categoryId > 0) {
                        $tplData['category'] = $forum->getCategory($categoryId);
                        if (empty($tplData['category'])) {
                            $this->redirectTo('game.php?page=forum');
                            return;
                        }
                        
                        $tplData['topics'] = $forum->getTopics($categoryId, $currentPage, 20);
                        $tplData['p'] = $currentPage;
                    } else {
                        $this->redirectTo('game.php?page=forum');
                        return;
                    }
                    break;
                    
                case 'topic':
                    $topicId = (int)HTTP::_GP('id', 0);

                    // IMPORTANT: "page" is router param in 2Moons -> use "p" for pagination
                    $currentPage = (int)HTTP::_GP('p', 1);
                    $currentPage = max(1, $currentPage);
                    
                    if ($topicId > 0) {
                        $tplData['topic'] = $forum->getTopic($topicId);
                        if (empty($tplData['topic'])) {
                            $this->redirectTo('game.php?page=forum');
                            return;
                        }
                        
                        $tplData['posts'] = $forum->getPosts($topicId, $currentPage, 15);
                        $tplData['p'] = $currentPage;
                    } else {
                        $this->redirectTo('game.php?page=forum');
                        return;
                    }
                    break;
                    
                case 'new_topic':
                    $categoryId = (int)HTTP::_GP('category_id', 0);
                    
                    if ($categoryId > 0) {
                        $tplData['category'] = $forum->getCategory($categoryId);
                        if (empty($tplData['category'])) {
                            $this->redirectTo('game.php?page=forum');
                            return;
                        }
                    } else {
                        $this->redirectTo('game.php?page=forum');
                        return;
                    }
                    break;

                case 'mentions':
                    $tplData['mentions'] = $forum->getUserMentions((int)$USER['id']);
                    break;

                case 'search':
                    // Optional: wenn du Search später richtig implementierst
                    // aktuell bleibt mode=search nur Template-seitig.
                    break;
            }
        } catch (Exception $e) {
            $tplData['error'] = 'Fehler beim Laden: ' . $e->getMessage();
            error_log('Forum Error: ' . $e->getMessage());
        }
        
        $this->assign($tplData);
        
        // Twig only
        $this->display('ForumPage.twig');
    }
}