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

    $forum    = new Forum();
    $template = new template();

    $mode    = HTTP::_GP('mode', 'categories');
    $message = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
            $message = ['class' => 'error', 'text' => 'Ungültige Daten.'];
        }

        if (isset($_POST['delete_category'])) {
            $catId = (int)HTTP::_GP('category_id', 0);
            if ($catId > 0) {
                $forum->deleteCategory($catId);
                HTTP::redirectTo('admin.php?page=ForumAdmin&mode=categories&msg=category_deleted');
                return;
            }
        }

        if (isset($_POST['delete_topic'])) {
            $topicId = (int)HTTP::_GP('topic_id', 0);
            if ($topicId > 0) {
                $forum->deleteTopic($topicId);
                HTTP::redirectTo('admin.php?page=ForumAdmin&mode=topics&msg=topic_deleted');
                return;
            }
        }

        if (isset($_POST['update_topic'])) {
            $topicId = (int)HTTP::_GP('topic_id', 0);
            $title   = trim(HTTP::_GP('title', '', true));
            if ($topicId > 0 && $title !== '') {
                $forum->updateTopic($topicId, [
                    'title'     => $title,
                    'is_sticky' => isset($_POST['is_sticky']) ? 1 : 0,
                    'is_locked' => isset($_POST['is_locked']) ? 1 : 0,
                ]);
                HTTP::redirectTo('admin.php?page=ForumAdmin&mode=topics&msg=topic_updated');
                return;
            }
        }

        if (isset($_POST['delete_post'])) {
            $postId = (int)HTTP::_GP('post_id', 0);
            if ($postId > 0) {
                $forum->deletePost($postId);
                HTTP::redirectTo('admin.php?page=ForumAdmin&mode=posts&msg=post_deleted');
                return;
            }
        }
    }

    $msgKey = HTTP::_GP('msg', '');
    if ($msgKey !== '') {
        $msgMap = [
            'category_created' => ['class' => 'success', 'text' => 'Kategorie erstellt.'],
            'category_updated' => ['class' => 'success', 'text' => 'Kategorie gespeichert.'],
            'category_deleted' => ['class' => 'success', 'text' => 'Kategorie gelöscht.'],
            'topic_deleted'    => ['class' => 'success', 'text' => 'Topic gelöscht.'],
            'topic_updated'    => ['class' => 'success', 'text' => 'Topic gespeichert.'],
            'post_deleted'     => ['class' => 'success', 'text' => 'Post gelöscht.'],
        ];
        if (isset($msgMap[$msgKey])) {
            $message = $msgMap[$msgKey];
        }
    }

    $tplData = [
        'mode'            => $mode,
        'message'         => $message,
        'categories'      => $forum->getCategories(),
        'flat_categories' => $forum->getFlatCategories(),
    ];

    try {
        switch ($mode) {
            case 'edit_category':
                $catId = (int)HTTP::_GP('id', 0);
                if ($catId > 0) {
                    $tplData['category'] = $forum->getCategory($catId);
                } else {
                    HTTP::redirectTo('admin.php?page=ForumAdmin&mode=categories');
                    return;
                }
                break;

            case 'edit_topic':
                $topicId = (int)HTTP::_GP('id', 0);
                if ($topicId > 0) {
                    $tplData['topic'] = $forum->getTopicForEdit($topicId);
                } else {
                    HTTP::redirectTo('admin.php?page=ForumAdmin&mode=topics');
                    return;
                }
                break;

            case 'topics':
                $tplData['topics'] = $forum->getTopicsAdmin();
                break;

            case 'posts':
                $tplData['posts'] = $forum->getPostsAdmin();
                break;

            case 'stats':
                $tplData['stats'] = $forum->getStats();
                break;
        }
    } catch (Exception $e) {
        $tplData['message'] = ['class' => 'error', 'text' => 'Fehler: ' . $e->getMessage()];
        error_log('ForumAdmin Error: ' . $e->getMessage());
    }

    $template->assign_vars($tplData);
    $template->show('ForumAdminPage.twig');
}
