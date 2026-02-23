<?php

declare(strict_types=1);

function ShowForumAdminPage(): void
{
    global $USER;

    if (!isset($USER) || ((int)$USER['authlevel'] < 3 && !allowedTo('ShowForumAdminPage'))) {
        HTTP::redirectTo('admin.php?page=overview');
        return;
    }

    if (!class_exists('Forum')) {
        require_once ROOT_PATH . 'includes/classes/Forum.class.php';
    }

    $template = new template();
    $mode     = HTTP::_GP('mode', 'categories');
    $message  = [];

    // ── TABLE EXISTENCE CHECK ──────────────────────────────────────────────────
    // Detect missing forum tables early and show a clear admin message instead
    // of a blank page or swallowed exception.
    try {
        $db = Database::get();
        $db->selectSingle("SELECT 1 FROM %%FORUM_CATEGORIES%% LIMIT 1");
    } catch (Exception $e) {
        $template->assign_vars([
            'mode'            => $mode,
            'message'         => [
                'class' => 'error',
                'text'  => 'Forum-Tabellen fehlen oder sind nicht erreichbar. '
                         . 'Bitte fuehre das Datenbank-Upgrade unter install/index.php?mode=upgrade aus. '
                         . '(Fehler: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . ')',
            ],
            'categories'      => [],
            'flat_categories' => [],
        ]);
        $template->show('ForumAdminPage.twig');
        return;
    }

    $forum = new Forum();

    // ── POST HANDLERS ──────────────────────────────────────────────────────────

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Category: create
        if (isset($_POST['create_category'])) {
            $title = trim(HTTP::_GP('title', '', true));
            if ($title !== '') {
                $forum->createCategory([
                    'parent_id'   => (int)HTTP::_GP('parent_id', 0),
                    'title'       => $title,
                    'description' => HTTP::_GP('description', '', true),
                    'icon'        => HTTP::_GP('icon', '📁', true),
                    'color'       => HTTP::_GP('color', '#38bdf8', true),
                    'sort_order'  => (int)HTTP::_GP('sort_order', 0),
                    'is_locked'   => 0,
                ]);
                HTTP::redirectTo('admin.php?page=ForumAdmin&mode=categories&msg=category_created');
                return;
            }
            $message = ['class' => 'error', 'text' => 'Titel darf nicht leer sein.'];
        }

        // Category: update
        if (isset($_POST['update_category'])) {
            $catId = (int)HTTP::_GP('category_id', 0);
            $title = trim(HTTP::_GP('title', '', true));
            if ($catId > 0 && $title !== '') {
                $forum->updateCategory($catId, [
                    'parent_id'   => (int)HTTP::_GP('parent_id', 0),
                    'title'       => $title,
                    'description' => HTTP::_GP('description', '', true),
                    'icon'        => HTTP::_GP('icon', '📁', true),
                    'color'       => HTTP::_GP('color', '#38bdf8', true),
                    'sort_order'  => (int)HTTP::_GP('sort_order', 0),
                    'is_locked'   => isset($_POST['is_locked']) ? 1 : 0,
                ]);
                HTTP::redirectTo('admin.php?page=ForumAdmin&mode=categories&msg=category_updated');
                return;
            }
            $message = ['class' => 'error', 'text' => 'Ungueltige Daten.'];
        }

        // Category: delete
        if (isset($_POST['delete_category'])) {
            $catId = (int)HTTP::_GP('category_id', 0);
            if ($catId > 0) {
                $forum->deleteCategory($catId);
                HTTP::redirectTo('admin.php?page=ForumAdmin&mode=categories&msg=category_deleted');
                return;
            }
        }

        // Topic: delete
        if (isset($_POST['delete_topic'])) {
            $topicId = (int)HTTP::_GP('topic_id', 0);
            if ($topicId > 0) {
                $forum->deleteTopic($topicId);
                $qs = http_build_query(array_filter([
                    'page'          => 'ForumAdmin',
                    'mode'          => 'topics',
                    'msg'           => 'topic_deleted',
                    'p'             => (int)HTTP::_GP('p', 1) > 1 ? (int)HTTP::_GP('p', 1) : null,
                    'cat_filter'    => (int)HTTP::_GP('cat_filter', 0) > 0 ? (int)HTTP::_GP('cat_filter', 0) : null,
                    'search'        => HTTP::_GP('search', '') !== '' ? HTTP::_GP('search', '') : null,
                    'status_filter' => HTTP::_GP('status_filter', '') !== '' ? HTTP::_GP('status_filter', '') : null,
                ]));
                HTTP::redirectTo('admin.php?' . $qs);
                return;
            }
        }

        // Topic: update (full edit)
        if (isset($_POST['update_topic'])) {
            $topicId = (int)HTTP::_GP('topic_id', 0);
            $title   = trim(HTTP::_GP('title', '', true));
            if ($topicId > 0 && $title !== '') {
                $forum->updateTopic($topicId, [
                    'title'       => $title,
                    'is_sticky'   => isset($_POST['is_sticky']) ? 1 : 0,
                    'is_locked'   => isset($_POST['is_locked']) ? 1 : 0,
                    'category_id' => (int)HTTP::_GP('category_id', 0),
                ]);
                HTTP::redirectTo('admin.php?page=ForumAdmin&mode=topics&msg=topic_updated');
                return;
            }
        }

        // Topic: quick action (lock/unlock/stick/unstick)
        if (isset($_POST['quick_action'])) {
            $topicId = (int)HTTP::_GP('topic_id', 0);
            $action  = HTTP::_GP('quick_action', '');
            if ($topicId > 0 && in_array($action, ['lock', 'unlock', 'stick', 'unstick'], true)) {
                $topic = $forum->getTopicForEdit($topicId);
                if ($topic !== null) {
                    $forum->updateTopic($topicId, [
                        'title'     => $topic['title'],
                        'is_sticky' => $action === 'stick'  ? 1 : ($action === 'unstick' ? 0 : (int)$topic['is_sticky']),
                        'is_locked' => $action === 'lock'   ? 1 : ($action === 'unlock'  ? 0 : (int)$topic['is_locked']),
                    ]);
                }
                $qs = http_build_query(array_filter([
                    'page'          => 'ForumAdmin',
                    'mode'          => 'topics',
                    'msg'           => 'topic_updated',
                    'p'             => (int)HTTP::_GP('p', 1) > 1 ? (int)HTTP::_GP('p', 1) : null,
                    'cat_filter'    => (int)HTTP::_GP('cat_filter', 0) > 0 ? (int)HTTP::_GP('cat_filter', 0) : null,
                    'search'        => HTTP::_GP('search', '') !== '' ? HTTP::_GP('search', '') : null,
                    'status_filter' => HTTP::_GP('status_filter', '') !== '' ? HTTP::_GP('status_filter', '') : null,
                ]));
                HTTP::redirectTo('admin.php?' . $qs);
                return;
            }
        }

        // Post: delete
        if (isset($_POST['delete_post'])) {
            $postId = (int)HTTP::_GP('post_id', 0);
            if ($postId > 0) {
                $forum->deletePost($postId);
                $qs = http_build_query(array_filter([
                    'page'         => 'ForumAdmin',
                    'mode'         => 'posts',
                    'msg'          => 'post_deleted',
                    'p'            => (int)HTTP::_GP('p', 1) > 1 ? (int)HTTP::_GP('p', 1) : null,
                    'cat_filter'   => (int)HTTP::_GP('cat_filter', 0) > 0 ? (int)HTTP::_GP('cat_filter', 0) : null,
                    'topic_filter' => (int)HTTP::_GP('topic_filter', 0) > 0 ? (int)HTTP::_GP('topic_filter', 0) : null,
                    'search'       => HTTP::_GP('search', '') !== '' ? HTTP::_GP('search', '') : null,
                ]));
                HTTP::redirectTo('admin.php?' . $qs);
                return;
            }
        }
    }

    // ── MSG FLASH ──────────────────────────────────────────────────────────────

    $msgKey = HTTP::_GP('msg', '');
    if ($msgKey !== '') {
        $msgMap = [
            'category_created' => ['class' => 'success', 'text' => 'Kategorie erstellt.'],
            'category_updated' => ['class' => 'success', 'text' => 'Kategorie gespeichert.'],
            'category_deleted' => ['class' => 'success', 'text' => 'Kategorie geloescht.'],
            'topic_deleted'    => ['class' => 'success', 'text' => 'Topic geloescht.'],
            'topic_updated'    => ['class' => 'success', 'text' => 'Topic gespeichert.'],
            'post_deleted'     => ['class' => 'success', 'text' => 'Post geloescht.'],
        ];
        if (isset($msgMap[$msgKey])) {
            $message = $msgMap[$msgKey];
        }
    }

    // ── COMMON DATA ────────────────────────────────────────────────────────────

    $tplData = [
        'mode'            => $mode,
        'message'         => $message,
        'categories'      => $forum->getCategories(),
        'flat_categories' => $forum->getFlatCategories(),
    ];

    // ── MODE-SPECIFIC DATA ─────────────────────────────────────────────────────

    try {
        switch ($mode) {

            case 'categories':
                break;

            case 'edit_category':
                $catId = (int)HTTP::_GP('id', 0);
                if ($catId > 0) {
                    $tplData['edit_category'] = $forum->getCategory($catId);
                    if ($tplData['edit_category'] === null) {
                        HTTP::redirectTo('admin.php?page=ForumAdmin&mode=categories');
                        return;
                    }
                } else {
                    HTTP::redirectTo('admin.php?page=ForumAdmin&mode=categories');
                    return;
                }
                break;

            case 'topics':
                $page         = max(1, (int)HTTP::_GP('p', 1));
                $catFilter    = (int)HTTP::_GP('cat_filter', 0);
                $search       = trim(HTTP::_GP('search', '', true));
                $statusFilter = HTTP::_GP('status_filter', '');
                $limit        = 25;

                $total   = $forum->getTopicsAdminCount($catFilter, $search, $statusFilter);
                $maxPage = max(1, (int)ceil($total / $limit));
                $page    = min($page, $maxPage);

                $tplData['topics']        = $forum->getTopicsAdmin($page, $limit, $catFilter, $search, $statusFilter);
                $tplData['topics_total']  = $total;
                $tplData['topics_page']   = $page;
                $tplData['topics_pages']  = $maxPage;
                $tplData['topics_limit']  = $limit;
                $tplData['cat_filter']    = $catFilter;
                $tplData['search']        = $search;
                $tplData['status_filter'] = $statusFilter;
                break;

            case 'edit_topic':
                $topicId = (int)HTTP::_GP('id', 0);
                if ($topicId > 0) {
                    $tplData['edit_topic'] = $forum->getTopicForEdit($topicId);
                    if ($tplData['edit_topic'] === null) {
                        HTTP::redirectTo('admin.php?page=ForumAdmin&mode=topics');
                        return;
                    }
                } else {
                    HTTP::redirectTo('admin.php?page=ForumAdmin&mode=topics');
                    return;
                }
                break;

            case 'posts':
                $page        = max(1, (int)HTTP::_GP('p', 1));
                $catFilter   = (int)HTTP::_GP('cat_filter', 0);
                $topicFilter = (int)HTTP::_GP('topic_filter', 0);
                $search      = trim(HTTP::_GP('search', '', true));
                $limit       = 25;

                $total   = $forum->getPostsAdminCount($catFilter, $topicFilter, $search);
                $maxPage = max(1, (int)ceil($total / $limit));
                $page    = min($page, $maxPage);

                $tplData['posts']        = $forum->getPostsAdmin($page, $limit, $catFilter, $topicFilter, $search);
                $tplData['posts_total']  = $total;
                $tplData['posts_page']   = $page;
                $tplData['posts_pages']  = $maxPage;
                $tplData['posts_limit']  = $limit;
                $tplData['cat_filter']   = $catFilter;
                $tplData['topic_filter'] = $topicFilter;
                $tplData['search']       = $search;
                break;

            case 'stats':
                $tplData['stats'] = $forum->getStats();
                break;
        }
    } catch (Exception $e) {
        $tplData['message'] = [
            'class' => 'error',
            'text'  => 'Datenbankfehler: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES),
        ];
        error_log('ForumAdmin Error [mode=' . $mode . ']: ' . $e->getMessage());
    }

    $template->assign_vars($tplData);
    $template->show('ForumAdminPage.twig');
}
