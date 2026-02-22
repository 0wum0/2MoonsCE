<?php

declare(strict_types=1);

/**
 * SmartMoons Forum - Admin Controller
 * FIX: Kein automatischer Redirect für Game-Admins
 */

function ShowForumAdminPage(): void
{
    global $LNG, $USER;
    
    // RADIKALER FIX: Game-Admins (Level 3) kommen immer rein.
    if (!isset($USER) || (int)$USER['authlevel'] < 3) {
        if (!allowedTo('ShowForumAdminPage')) {
            HTTP::redirectTo('admin.php?page=overview');
            return;
        }
    }
    
    if (!class_exists('Forum')) {
        require_once ROOT_PATH . 'includes/classes/Forum.class.php';
    }
    
    $forum    = new Forum();
    $template = new template();
    $db       = Database::get();
    
    $mode = HTTP::_GP('mode', 'categories');
    $message = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_category'])) {
            $forum->createCategory([
                'parent_id'   => (int)HTTP::_GP('parent_id', 0) ?: null,
                'title'       => HTTP::_GP('title', '', true),
                'description' => HTTP::_GP('description', '', true),
                'icon'        => HTTP::_GP('icon', '', true),
                'color'       => HTTP::_GP('color', '#38bdf8', true),
                'sort_order'  => (int)HTTP::_GP('sort_order', 0),
            ]);
            $message = ['class' => 'success', 'text' => 'Kategorie erstellt.'];
        }
    }
    
    $tplData = ['mode' => $mode, 'message' => $message, 'categories' => $forum->getCategories()];
    if ($mode === 'stats') {
        $tplData['stats'] = [
            'total_categories' => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_CATEGORIES%%", [], 'c'),
            'total_topics'     => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_TOPICS%%", [], 'c'),
            'total_posts'      => (int)$db->selectSingle("SELECT COUNT(*) as c FROM %%FORUM_POSTS%%", [], 'c'),
        ];
    }
    
    $template->assign_vars($tplData);
    $template->show('ForumAdminPage.twig');
}