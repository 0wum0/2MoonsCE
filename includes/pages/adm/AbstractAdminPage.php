<?php

declare(strict_types=1);

/**
 *  SmartMoons / 2Moons Community Edition (2MoonsCE)
 *
 *  Based on the original 2Moons project:
 *
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
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

/**
 * AbstractAdminPage
 *
 * Base class for admin page controllers.
 * Provides shared boilerplate for template initialization, variable assignment,
 * JSON responses, and access control.
 *
 * Migration path: existing admin page functions (plain PHP) should be converted
 * to classes that extend this base incrementally.
 *
 * See docs/ROADMAP.md §Phase 2 for the migration plan.
 */
abstract class AbstractAdminPage
{
    /** @var template|null Twig template wrapper instance */
    protected ?object $tplObj = null;

    /**
     * Initialize the Twig template wrapper (lazy — call before assign/display).
     */
    protected function initTemplate(): void
    {
        if ($this->tplObj !== null) {
            return;
        }

        $this->tplObj = new template();
    }

    /**
     * Assign one or more template variables.
     *
     * @param array<string, mixed> $vars
     */
    protected function assign(array $vars): void
    {
        $this->initTemplate();
        $this->tplObj->assign_vars($vars);
    }

    /**
     * Render an admin Twig template and send to output.
     *
     * @param string $template Template filename relative to the admin template directory.
     */
    protected function display(string $template): void
    {
        $this->initTemplate();
        $this->tplObj->display($template);
    }

    /**
     * Send a JSON response and terminate execution.
     *
     * Clears any output buffer so no stray HTML contaminates the response.
     *
     * @param array<string, mixed> $data
     */
    protected function sendJSON(array $data): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * Check that the current admin user has the required right.
     *
     * Calls the global allowedTo() helper used throughout admin pages.
     * Throws an exception on failure — same behaviour as existing page guards.
     *
     * @param string $rightKey Right key string (e.g. 'ShowStatsPage')
     * @throws \Exception if access is denied
     */
    protected function checkAccess(string $rightKey): void
    {
        if (!allowedTo($rightKey)) {
            throw new \Exception('Permission error!');
        }
    }

    /**
     * Redirect to another admin page and terminate.
     *
     * @param string $page Target page name (e.g. 'index')
     */
    protected function redirect(string $page): never
    {
        header('Location: admin.php?page=' . urlencode($page));
        exit;
    }
}
