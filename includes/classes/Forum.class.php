<?php

declare(strict_types=1);

/**
 * SmartMoons Forum Model
 * PHP 8.3/8.4 Optimized
 * FIX: Player Rankings, Strict Mode & Unix Timestamps
 */

class Forum
{
    private Database $db;
    private BBCode $bbcode;

    public function __construct()
    {
        $this->db = Database::get();
        if (!class_exists('BBCode')) {
            require_once ROOT_PATH . 'includes/classes/BBCode.class.php';
        }
        $this->bbcode = new BBCode();
    }

    public function getCategories(): array
    {
        $categories = $this->db->select("SELECT * FROM %%FORUM_CATEGORIES%% ORDER BY sort_order ASC, id ASC");
        $lookup = [];
        foreach ($categories as &$cat) {
            if (!isset($cat['parent_id']) || (int)$cat['parent_id'] === 0) {
                $cat['parent_id'] = null;
            }
            $cat['children'] = [];
            $cat['topic_count'] = (int)$this->db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_TOPICS%% WHERE category_id = :cat", [':cat' => $cat['id']], 'c');
            $cat['post_count'] = (int)$this->db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_POSTS%% p INNER JOIN %%FORUM_TOPICS%% t ON p.topic_id = t.id WHERE t.category_id = :cat", [':cat' => $cat['id']], 'c');
            
            $lastPost = $this->db->selectSingle("SELECT p.*, u.username, t.title as topic_title FROM %%FORUM_POSTS%% p INNER JOIN %%FORUM_TOPICS%% t ON p.topic_id = t.id LEFT JOIN %%USERS%% u ON p.user_id = u.id WHERE t.category_id = :cat ORDER BY p.created_at DESC LIMIT 1", [':cat' => $cat['id']]);
            $cat['last_post'] = $lastPost ?: null;
            
            $lookup[$cat['id']] = &$cat;
        }

        $tree = [];
        foreach ($categories as &$cat) {
            if ($cat['parent_id'] === null) {
                $tree[] = &$cat;
            } else if (isset($lookup[$cat['parent_id']])) {
                $lookup[$cat['parent_id']]['children'][] = &$cat;
            }
        }
        return $tree;
    }

    public function getCategory(int $id): ?array
    {
        $res = $this->db->selectSingle("SELECT * FROM %%FORUM_CATEGORIES%% WHERE id = :id", [':id' => $id]);
        return $res ?: null;
    }

    public function getTopics(int $categoryId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        return $this->db->select(
            "SELECT t.*, u.username, 
            (SELECT COUNT(*) FROM %%FORUM_POSTS%% WHERE topic_id = t.id AND is_deleted = 0) as post_count 
             FROM %%FORUM_TOPICS%% t 
             LEFT JOIN %%USERS%% u ON t.user_id = u.id 
             WHERE t.category_id = :cat 
             ORDER BY t.is_sticky DESC, t.last_post_time DESC 
             LIMIT :limit OFFSET :offset",
            [':cat' => $categoryId, ':limit' => $limit, ':offset' => $offset]
        );
    }

    public function getTopic(int $id): ?array
    {
        $topic = $this->db->selectSingle("SELECT t.*, c.title as category_title, c.id as category_id FROM %%FORUM_TOPICS%% t LEFT JOIN %%FORUM_CATEGORIES%% c ON t.category_id = c.id WHERE t.id = :id", [':id' => $id]);
        if ($topic) {
            $this->db->update("UPDATE %%FORUM_TOPICS%% SET views = views + 1 WHERE id = :id", [':id' => $id]);
        }
        return $topic ?: null;
    }

    public function getPosts(int $topicId, int $page = 1, int $limit = 15): array
    {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT p.*, u.username, u.authlevel, u.ally_id, 
                       a.ally_tag as alliance_tag, a.ally_name as alliance_name,
                       s.total_points as user_points, s.total_rank as user_rank
                FROM %%FORUM_POSTS%% p 
                LEFT JOIN %%USERS%% u ON p.user_id = u.id 
                LEFT JOIN %%ALLIANCE%% a ON u.ally_id = a.id 
                LEFT JOIN %%STATPOINTS%% s ON s.id_owner = u.id AND s.stat_type = 1
                WHERE p.topic_id = :topic AND p.is_deleted = 0 
                ORDER BY p.created_at ASC 
                LIMIT :limit OFFSET :offset";

        $posts = $this->db->select($sql, [':topic' => $topicId, ':limit' => $limit, ':offset' => $offset]);
        foreach ($posts as &$post) {
            $post['content_html'] = $this->bbcode->parse($post['content']);
        }
        return $posts;
    }

    public function createTopic(int $categoryId, int $userId, string $title, string $content): int
    {
        $now = TIMESTAMP;
        $this->db->insert("INSERT INTO %%FORUM_TOPICS%% SET category_id = :cat, user_id = :user, title = :title, created_at = :now, last_post_time = :now, updated_at = :now",
            [':cat' => $categoryId, ':user' => $userId, ':title' => $title, ':now' => $now]
        );
        $topicId = (int)$this->db->lastInsertId();
        $this->createPost($topicId, $userId, $content);
        return $topicId;
    }

    public function createPost(int $topicId, int $userId, string $content): void
    {
        $now = TIMESTAMP;
        $this->db->insert("INSERT INTO %%FORUM_POSTS%% SET topic_id = :topic, user_id = :user, content = :content, created_at = :now, updated_at = :now",
            [':topic' => $topicId, ':user' => $userId, ':content' => $content, ':now' => $now]
        );
        $this->db->update("UPDATE %%FORUM_TOPICS%% SET last_post_time = :now WHERE id = :topic", [':now' => $now, ':topic' => $topicId]);
    }

    public function toggleLike(int $postId, int $userId): bool
    {
        $exists = $this->db->selectSingle("SELECT id FROM %%FORUM_POST_LIKES%% WHERE post_id = :post AND user_id = :user", [':post' => $postId, ':user' => $userId]);
        if ($exists) {
            $this->db->delete("DELETE FROM %%FORUM_POST_LIKES%% WHERE id = :id", [':id' => $exists['id']]);
            $this->db->update("UPDATE %%FORUM_POSTS%% SET like_count = like_count - 1 WHERE id = :id", [':id' => $postId]);
            return false;
        } else {
            $this->db->insert("INSERT INTO %%FORUM_POST_LIKES%% SET post_id = :post, user_id = :user", [':post' => $postId, ':user' => $userId]);
            $this->db->update("UPDATE %%FORUM_POSTS%% SET like_count = like_count + 1 WHERE id = :id", [':id' => $postId]);
            return true;
        }
    }
}