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
        $offset = (int)(($page - 1) * $limit);
        $limit  = (int)$limit;
        return $this->db->select(
            "SELECT t.*, u.username,
            (SELECT COUNT(*) FROM %%FORUM_POSTS%% WHERE topic_id = t.id AND is_deleted = 0) as post_count
             FROM %%FORUM_TOPICS%% t
             LEFT JOIN %%USERS%% u ON t.user_id = u.id
             WHERE t.category_id = :cat
             ORDER BY t.is_sticky DESC, t.last_post_time DESC
             LIMIT {$limit} OFFSET {$offset}",
            [':cat' => $categoryId]
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
                LIMIT {$limit} OFFSET {$offset}";

        $posts = $this->db->select($sql, [':topic' => $topicId]);
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
        $postId = (int)$this->db->lastInsertId();
        $this->db->update("UPDATE %%FORUM_TOPICS%% SET last_post_time = :now WHERE id = :topic", [':now' => $now, ':topic' => $topicId]);
        $this->ensureSubscription($topicId, $userId);
        $this->extractAndSaveMentions($postId, $userId, $content);
        $this->updateSubscriberUnreads($topicId, $postId, $userId);
    }

    // ── Notification helpers ──────────────────────────────────────────────────

    /**
     * Auto-subscribe the poster to the topic (idempotent).
     */
    public function ensureSubscription(int $topicId, int $userId): void
    {
        $exists = $this->db->selectSingle(
            "SELECT id FROM %%FORUM_SUBSCRIPTIONS%% WHERE topic_id = :t AND user_id = :u",
            [':t' => $topicId, ':u' => $userId]
        );
        if (!$exists) {
            $this->db->insert(
                "INSERT INTO %%FORUM_SUBSCRIPTIONS%% SET topic_id = :t, user_id = :u, created_at = :now",
                [':t' => $topicId, ':u' => $userId, ':now' => TIMESTAMP]
            );
        }
    }

    /**
     * Parse @mentions from raw post content and insert rows into forum_mentions.
     * Deduplicates per post+user. Does NOT mention the poster themselves.
     */
    public function extractAndSaveMentions(int $postId, int $byUserId, string $content): void
    {
        if (!class_exists('BBCode')) {
            require_once ROOT_PATH . 'includes/classes/BBCode.class.php';
        }
        $mentionedUserIds = $this->bbcode->extractMentions($content);
        $now = TIMESTAMP;
        foreach ($mentionedUserIds as $mentionedUserId) {
            if ($mentionedUserId === $byUserId) {
                continue;
            }
            $exists = $this->db->selectSingle(
                "SELECT id FROM %%FORUM_MENTIONS%% WHERE post_id = :p AND mentioned_user_id = :u",
                [':p' => $postId, ':u' => $mentionedUserId]
            );
            if (!$exists) {
                $this->db->insert(
                    "INSERT INTO %%FORUM_MENTIONS%% SET post_id = :p, mentioned_user_id = :u, by_user_id = :by, is_read = 0, created_at = :now",
                    [':p' => $postId, ':u' => $mentionedUserId, ':by' => $byUserId, ':now' => $now]
                );
            }
        }
    }

    /**
     * For every subscriber of a topic (except the poster), upsert an unread row
     * pointing at the new post_id.
     */
    public function updateSubscriberUnreads(int $topicId, int $newPostId, int $posterUserId): void
    {
        $subscribers = $this->db->select(
            "SELECT user_id FROM %%FORUM_SUBSCRIPTIONS%% WHERE topic_id = :t AND user_id != :u",
            [':t' => $topicId, ':u' => $posterUserId]
        );
        $now = TIMESTAMP;
        foreach ($subscribers as $row) {
            $uid = (int)$row['user_id'];
            $exists = $this->db->selectSingle(
                "SELECT id FROM %%FORUM_TOPIC_UNREADS%% WHERE topic_id = :t AND user_id = :u",
                [':t' => $topicId, ':u' => $uid]
            );
            if ($exists) {
                $this->db->update(
                    "UPDATE %%FORUM_TOPIC_UNREADS%% SET last_post_id = :p, updated_at = :now WHERE id = :id",
                    [':p' => $newPostId, ':now' => $now, ':id' => (int)$exists['id']]
                );
            } else {
                $this->db->insert(
                    "INSERT INTO %%FORUM_TOPIC_UNREADS%% SET topic_id = :t, user_id = :u, last_post_id = :p, updated_at = :now",
                    [':t' => $topicId, ':u' => $uid, ':p' => $newPostId, ':now' => $now]
                );
            }
        }
    }

    /**
     * Mark all unread notifications for a topic as read for this user.
     * Called when the user opens a topic view.
     */
    public function markTopicRead(int $topicId, int $userId): void
    {
        $this->db->delete(
            "DELETE FROM %%FORUM_TOPIC_UNREADS%% WHERE topic_id = :t AND user_id = :u",
            [':t' => $topicId, ':u' => $userId]
        );
    }

    /**
     * Total unread forum notification count for a user (mentions + topic unreads).
     */
    public function getForumNotificationCount(int $userId): int
    {
        $mentions = (int)$this->db->selectSingle(
            "SELECT COUNT(*) as c FROM %%FORUM_MENTIONS%% WHERE mentioned_user_id = :u AND is_read = 0",
            [':u' => $userId],
            'c'
        );
        $unreads = (int)$this->db->selectSingle(
            "SELECT COUNT(*) as c FROM %%FORUM_TOPIC_UNREADS%% WHERE user_id = :u",
            [':u' => $userId],
            'c'
        );
        return $mentions + $unreads;
    }

    /**
     * Returns structured notification list for the dropdown.
     * type=mention: post where user was @mentioned
     * type=new_post: subscribed topic with new posts
     */
    public function getForumNotifications(int $userId, int $limit = 20): array
    {
        $notifications = [];

        $mentions = $this->db->select(
            "SELECT m.id, m.post_id, m.is_read, m.created_at, m.by_user_id,
                    p.topic_id, p.content,
                    t.title as topic_title,
                    u.username as by_username
             FROM %%FORUM_MENTIONS%% m
             LEFT JOIN %%FORUM_POSTS%% p ON m.post_id = p.id
             LEFT JOIN %%FORUM_TOPICS%% t ON p.topic_id = t.id
             LEFT JOIN %%USERS%% u ON m.by_user_id = u.id
             WHERE m.mentioned_user_id = :u AND m.is_read = 0
             ORDER BY m.created_at DESC
             LIMIT {$limit}",
            [':u' => $userId]
        );
        foreach ($mentions as $row) {
            $notifications[] = [
                'type'        => 'mention',
                'id'          => (int)$row['id'],
                'post_id'     => (int)$row['post_id'],
                'topic_id'    => (int)$row['topic_id'],
                'topic_title' => $row['topic_title'] ?? '',
                'by_username' => $row['by_username'] ?? '',
                'snippet'     => $this->makeSnippet((string)($row['content'] ?? '')),
                'created_at'  => (int)$row['created_at'],
                'is_read'     => 0,
            ];
        }

        $unreads = $this->db->select(
            "SELECT u.id, u.topic_id, u.last_post_id, u.updated_at,
                    t.title as topic_title,
                    p.user_id as last_poster_id,
                    pu.username as last_poster_name,
                    p.content as last_content
             FROM %%FORUM_TOPIC_UNREADS%% u
             LEFT JOIN %%FORUM_TOPICS%% t ON u.topic_id = t.id
             LEFT JOIN %%FORUM_POSTS%% p ON u.last_post_id = p.id
             LEFT JOIN %%USERS%% pu ON p.user_id = pu.id
             WHERE u.user_id = :u
             ORDER BY u.updated_at DESC
             LIMIT {$limit}",
            [':u' => $userId]
        );
        foreach ($unreads as $row) {
            $notifications[] = [
                'type'        => 'new_post',
                'id'          => (int)$row['id'],
                'post_id'     => (int)$row['last_post_id'],
                'topic_id'    => (int)$row['topic_id'],
                'topic_title' => $row['topic_title'] ?? '',
                'by_username' => $row['last_poster_name'] ?? '',
                'snippet'     => $this->makeSnippet((string)($row['last_content'] ?? '')),
                'created_at'  => (int)$row['updated_at'],
                'is_read'     => 0,
            ];
        }

        usort($notifications, fn($a, $b) => $b['created_at'] - $a['created_at']);
        return array_slice($notifications, 0, $limit);
    }

    /**
     * Mark a single notification as read.
     * type=mention: set is_read=1 in forum_mentions
     * type=new_post: delete from forum_topic_unreads
     */
    public function markNotificationRead(int $userId, string $type, int $id): void
    {
        if ($type === 'mention') {
            $this->db->update(
                "UPDATE %%FORUM_MENTIONS%% SET is_read = 1 WHERE id = :id AND mentioned_user_id = :u",
                [':id' => $id, ':u' => $userId]
            );
        } elseif ($type === 'new_post') {
            $this->db->delete(
                "DELETE FROM %%FORUM_TOPIC_UNREADS%% WHERE id = :id AND user_id = :u",
                [':id' => $id, ':u' => $userId]
            );
        }
    }

    /**
     * Mark all forum notifications as read for a user.
     */
    public function markAllNotificationsRead(int $userId): void
    {
        $this->db->update(
            "UPDATE %%FORUM_MENTIONS%% SET is_read = 1 WHERE mentioned_user_id = :u AND is_read = 0",
            [':u' => $userId]
        );
        $this->db->delete(
            "DELETE FROM %%FORUM_TOPIC_UNREADS%% WHERE user_id = :u",
            [':u' => $userId]
        );
    }

    /**
     * Strip BBCode/HTML from content and return a short plain-text snippet.
     */
    private function makeSnippet(string $content, int $maxLen = 80): string
    {
        $text = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\[[^\]]*\]/', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);
        if (mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen) . '…';
        }
        return $text;
    }

    public function toggleLike(int $postId, int $userId): bool
    {
        $exists = $this->db->selectSingle("SELECT id FROM %%FORUM_POST_LIKES%% WHERE post_id = :post AND user_id = :user", [':post' => $postId, ':user' => $userId]);
        if ($exists) {
            $this->db->delete("DELETE FROM %%FORUM_POST_LIKES%% WHERE id = :id", [':id' => $exists['id']]);
            $this->db->update("UPDATE %%FORUM_POSTS%% SET like_count = like_count - 1 WHERE id = :id", [':id' => $postId]);
            return false;
        } else {
            $now = TIMESTAMP;
            $this->db->insert("INSERT INTO %%FORUM_POST_LIKES%% SET post_id = :post, user_id = :user, created_at = :now", [':post' => $postId, ':user' => $userId, ':now' => $now]);
            $this->db->update("UPDATE %%FORUM_POSTS%% SET like_count = like_count + 1 WHERE id = :id", [':id' => $postId]);
            return true;
        }
    }

    public function updatePost(int $postId, int $userId, string $content): void
    {
        $now = TIMESTAMP;
        $this->db->update(
            "UPDATE %%FORUM_POSTS%% SET content = :content, updated_at = :now WHERE id = :id",
            [':content' => $content, ':now' => $now, ':id' => $postId]
        );
    }

    public function createCategory(array $data): int
    {
        $now = TIMESTAMP;
        $parentId = isset($data['parent_id']) && (int)$data['parent_id'] > 0 ? (int)$data['parent_id'] : null;
        $this->db->insert(
            "INSERT INTO %%FORUM_CATEGORIES%% SET parent_id = :parent, title = :title, description = :desc, icon = :icon, color = :color, sort_order = :sort, is_locked = :locked, created_at = :now",
            [
                ':parent' => $parentId,
                ':title'  => $data['title'] ?? '',
                ':desc'   => $data['description'] ?? '',
                ':icon'   => $data['icon'] ?? '📁',
                ':color'  => $data['color'] ?? '#38bdf8',
                ':sort'   => (int)($data['sort_order'] ?? 0),
                ':locked' => (int)($data['is_locked'] ?? 0),
                ':now'    => $now,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateCategory(int $id, array $data): void
    {
        $parentId = isset($data['parent_id']) && (int)$data['parent_id'] > 0 ? (int)$data['parent_id'] : null;
        $this->db->update(
            "UPDATE %%FORUM_CATEGORIES%% SET parent_id = :parent, title = :title, description = :desc, icon = :icon, color = :color, sort_order = :sort, is_locked = :locked WHERE id = :id",
            [
                ':parent' => $parentId,
                ':title'  => $data['title'] ?? '',
                ':desc'   => $data['description'] ?? '',
                ':icon'   => $data['icon'] ?? '📁',
                ':color'  => $data['color'] ?? '#38bdf8',
                ':sort'   => (int)($data['sort_order'] ?? 0),
                ':locked' => (int)($data['is_locked'] ?? 0),
                ':id'     => $id,
            ]
        );
    }

    public function deleteCategory(int $id): void
    {
        $children = $this->db->select("SELECT id FROM %%FORUM_CATEGORIES%% WHERE parent_id = :id", [':id' => $id]);
        foreach ($children as $child) {
            $childTopics = $this->db->select("SELECT id FROM %%FORUM_TOPICS%% WHERE category_id = :cat", [':cat' => (int)$child['id']]);
            foreach ($childTopics as $topic) {
                $this->deleteTopic((int)$topic['id']);
            }
            $this->db->delete("DELETE FROM %%FORUM_CATEGORIES%% WHERE id = :id", [':id' => (int)$child['id']]);
        }
        $topics = $this->db->select("SELECT id FROM %%FORUM_TOPICS%% WHERE category_id = :cat", [':cat' => $id]);
        foreach ($topics as $topic) {
            $this->deleteTopic((int)$topic['id']);
        }
        $this->db->delete("DELETE FROM %%FORUM_CATEGORIES%% WHERE id = :id", [':id' => $id]);
    }

    public function deleteTopic(int $id): void
    {
        $posts = $this->db->select("SELECT id FROM %%FORUM_POSTS%% WHERE topic_id = :topic", [':topic' => $id]);
        foreach ($posts as $post) {
            $this->db->delete("DELETE FROM %%FORUM_POST_LIKES%% WHERE post_id = :id", [':id' => $post['id']]);
            $this->db->delete("DELETE FROM %%FORUM_MENTIONS%% WHERE post_id = :id", [':id' => $post['id']]);
        }
        $this->db->delete("DELETE FROM %%FORUM_POSTS%% WHERE topic_id = :id", [':id' => $id]);
        $this->db->delete("DELETE FROM %%FORUM_TOPICS%% WHERE id = :id", [':id' => $id]);
    }

    public function deletePost(int $id): void
    {
        $this->db->delete("DELETE FROM %%FORUM_POST_LIKES%% WHERE post_id = :id", [':id' => $id]);
        $this->db->delete("DELETE FROM %%FORUM_MENTIONS%% WHERE post_id = :id", [':id' => $id]);
        $this->db->delete("DELETE FROM %%FORUM_POSTS%% WHERE id = :id", [':id' => $id]);
    }

    public function getFlatCategories(): array
    {
        return $this->db->select("SELECT id, title, parent_id FROM %%FORUM_CATEGORIES%% ORDER BY sort_order ASC, id ASC");
    }

    public function getTopicsAdmin(int $page = 1, int $limit = 30, int $catId = 0, string $search = '', string $status = ''): array
    {
        [$where, $params] = $this->buildTopicAdminWhere($catId, $search, $status);
        $offset = (int)(($page - 1) * $limit);
        $limit  = (int)$limit;
        return $this->db->select(
            "SELECT t.*, u.username, c.title as category_title,
                    (SELECT COUNT(*) FROM %%FORUM_POSTS%% WHERE topic_id = t.id AND is_deleted = 0) as post_count
             FROM %%FORUM_TOPICS%% t
             LEFT JOIN %%USERS%% u ON t.user_id = u.id
             LEFT JOIN %%FORUM_CATEGORIES%% c ON t.category_id = c.id
             {$where}
             ORDER BY t.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public function getTopicsAdminCount(int $catId = 0, string $search = '', string $status = ''): int
    {
        [$where, $params] = $this->buildTopicAdminWhere($catId, $search, $status);
        return (int)$this->db->selectSingle(
            "SELECT COUNT(*) as c FROM %%FORUM_TOPICS%% t {$where}",
            $params,
            'c'
        );
    }

    private function buildTopicAdminWhere(int $catId, string $search, string $status): array
    {
        $conditions = [];
        $params     = [];

        if ($catId > 0) {
            $conditions[] = 't.category_id = :cat_id';
            $params[':cat_id'] = $catId;
        }
        if ($search !== '') {
            $conditions[] = '(t.title LIKE :search OR u.username LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }
        if ($status === 'sticky') {
            $conditions[] = 't.is_sticky = 1';
        } elseif ($status === 'locked') {
            $conditions[] = 't.is_locked = 1';
        } elseif ($status === 'deleted') {
            $conditions[] = 't.is_deleted = 1';
        }

        $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    public function getPostsAdmin(int $page = 1, int $limit = 30, int $catId = 0, int $topicId = 0, string $search = ''): array
    {
        [$where, $params] = $this->buildPostAdminWhere($catId, $topicId, $search);
        $offset = (int)(($page - 1) * $limit);
        $limit  = (int)$limit;
        return $this->db->select(
            "SELECT p.*, u.username, t.title as topic_title, c.title as category_title
             FROM %%FORUM_POSTS%% p
             LEFT JOIN %%USERS%% u ON p.user_id = u.id
             LEFT JOIN %%FORUM_TOPICS%% t ON p.topic_id = t.id
             LEFT JOIN %%FORUM_CATEGORIES%% c ON t.category_id = c.id
             {$where}
             ORDER BY p.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public function getPostsAdminCount(int $catId = 0, int $topicId = 0, string $search = ''): int
    {
        [$where, $params] = $this->buildPostAdminWhere($catId, $topicId, $search);
        return (int)$this->db->selectSingle(
            "SELECT COUNT(*) as c FROM %%FORUM_POSTS%% p
             LEFT JOIN %%USERS%% u ON p.user_id = u.id
             LEFT JOIN %%FORUM_TOPICS%% t ON p.topic_id = t.id
             {$where}",
            $params,
            'c'
        );
    }

    private function buildPostAdminWhere(int $catId, int $topicId, string $search): array
    {
        $conditions = ['p.is_deleted = 0'];
        $params     = [];

        if ($topicId > 0) {
            $conditions[] = 'p.topic_id = :topic_id';
            $params[':topic_id'] = $topicId;
        } elseif ($catId > 0) {
            $conditions[] = 't.category_id = :cat_id';
            $params[':cat_id'] = $catId;
        }
        if ($search !== '') {
            $conditions[] = '(u.username LIKE :search OR p.content LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }

    public function getStats(): array
    {
        $topAuthors = $this->db->select(
            "SELECT u.username, COUNT(p.id) as post_count
             FROM %%FORUM_POSTS%% p
             LEFT JOIN %%USERS%% u ON p.user_id = u.id
             WHERE p.is_deleted = 0
             GROUP BY p.user_id
             ORDER BY post_count DESC
             LIMIT 5",
            []
        );
        return [
            'total_categories'    => (int)$this->db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_CATEGORIES%%", [], 'c'),
            'total_subcategories' => (int)$this->db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_CATEGORIES%% WHERE parent_id IS NOT NULL AND parent_id > 0", [], 'c'),
            'total_topics'        => (int)$this->db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_TOPICS%% WHERE is_deleted = 0", [], 'c'),
            'total_posts'         => (int)$this->db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_POSTS%% WHERE is_deleted = 0", [], 'c'),
            'total_likes'         => (int)$this->db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_POST_LIKES%%", [], 'c'),
            'total_mentions'      => (int)$this->db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_MENTIONS%%", [], 'c'),
            'total_users'         => (int)$this->db->selectSingle("SELECT COUNT(DISTINCT user_id) as c FROM %%FORUM_POSTS%% WHERE is_deleted = 0", [], 'c'),
            'top_authors'         => $topAuthors,
        ];
    }

    public function getUserMentions(int $userId): array
    {
        return $this->db->select(
            "SELECT m.*, p.content, p.topic_id, t.title as topic_title, u.username as mentioned_by
             FROM %%FORUM_MENTIONS%% m
             LEFT JOIN %%FORUM_POSTS%% p ON m.post_id = p.id
             LEFT JOIN %%FORUM_TOPICS%% t ON p.topic_id = t.id
             LEFT JOIN %%USERS%% u ON p.user_id = u.id
             WHERE m.user_id = :user
             ORDER BY m.created_at DESC
             LIMIT 50",
            [':user' => $userId]
        );
    }

    public function markMentionRead(int $mentionId): void
    {
        $this->db->update(
            "UPDATE %%FORUM_MENTIONS%% SET is_read = 1 WHERE id = :id",
            [':id' => $mentionId]
        );
    }

    public function getTopicForEdit(int $id): ?array
    {
        $res = $this->db->selectSingle(
            "SELECT t.*, c.title as category_title FROM %%FORUM_TOPICS%% t LEFT JOIN %%FORUM_CATEGORIES%% c ON t.category_id = c.id WHERE t.id = :id",
            [':id' => $id]
        );
        return $res ?: null;
    }

    public function updateTopic(int $id, array $data): void
    {
        $catId = isset($data['category_id']) && (int)$data['category_id'] > 0 ? (int)$data['category_id'] : null;
        if ($catId !== null) {
            $this->db->update(
                "UPDATE %%FORUM_TOPICS%% SET title = :title, is_sticky = :sticky, is_locked = :locked, category_id = :cat WHERE id = :id",
                [
                    ':title'  => $data['title'] ?? '',
                    ':sticky' => (int)($data['is_sticky'] ?? 0),
                    ':locked' => (int)($data['is_locked'] ?? 0),
                    ':cat'    => $catId,
                    ':id'     => $id,
                ]
            );
        } else {
            $this->db->update(
                "UPDATE %%FORUM_TOPICS%% SET title = :title, is_sticky = :sticky, is_locked = :locked WHERE id = :id",
                [
                    ':title'  => $data['title'] ?? '',
                    ':sticky' => (int)($data['is_sticky'] ?? 0),
                    ':locked' => (int)($data['is_locked'] ?? 0),
                    ':id'     => $id,
                ]
            );
        }
    }

    public function getPost(int $id): ?array
    {
        $res = $this->db->selectSingle(
            "SELECT * FROM %%FORUM_POSTS%% WHERE id = :id AND is_deleted = 0",
            [':id' => $id]
        );
        return $res ?: null;
    }

    public function softDeletePost(int $id): void
    {
        $this->db->update(
            "UPDATE %%FORUM_POSTS%% SET is_deleted = 1, updated_at = :now WHERE id = :id",
            [':now' => TIMESTAMP, ':id' => $id]
        );
    }

    public function reportPost(int $postId, int $reporterId, string $reason): void
    {
        $this->db->insert(
            "INSERT INTO %%FORUM_REPORTS%% SET post_id = :post, reporter_id = :reporter, reason = :reason, status = 'open', created_at = :now",
            [':post' => $postId, ':reporter' => $reporterId, ':reason' => $reason, ':now' => TIMESTAMP]
        );
    }

    public function getTopicCount(int $categoryId): int
    {
        return (int)$this->db->selectSingle(
            "SELECT COUNT(*) as c FROM %%FORUM_TOPICS%% WHERE category_id = :cat",
            [':cat' => $categoryId],
            'c'
        );
    }

    public function getPostCount(int $topicId): int
    {
        return (int)$this->db->selectSingle(
            "SELECT COUNT(*) as c FROM %%FORUM_POSTS%% WHERE topic_id = :topic AND is_deleted = 0",
            [':topic' => $topicId],
            'c'
        );
    }
}