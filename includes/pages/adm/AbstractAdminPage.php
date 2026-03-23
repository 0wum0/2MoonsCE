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
 * Centralises common admin page boilerplate:
 *   - access guard via allowedTo()
 *   - lazy template initialisation
 *   - assign() / show() / message() helpers
 *   - mode-based method dispatch (run())
 *   - redirect / JSON helpers
 *
 * Migration path: existing plain-PHP admin page functions are converted to
 * classes that extend this base one at a time, incrementally and safely.
 *
 * Routing in admin.php stays identical — the switch case just calls
 * `new ShowXxxPage()` instead of `ShowXxxPage()`.
 *
 * See docs/ADMIN_PAGE_MIGRATION.md for the full migration plan.
 * See docs/ROADMAP.md §Phase 4 for progress tracking.
 *
 * IMPORTANT — template rendering:
 *   Use $this->show()    for full-page admin renders (calls template::show()
 *                        which auto-injects layout vars via adm_main()).
 *   Use $this->message() for status/error messages.
 *   Do NOT call template::display() directly — it skips the admin layout.
 */
abstract class AbstractAdminPage
{
    /** @var template|null Twig template wrapper instance (lazy) */
    protected ?object $tplObj = null;

    /**
     * Constructor.
     *
     * Performs the access check and then dispatches to run().
     * Subclasses MUST call parent::__construct($rightKey) first.
     *
     * @param string $rightKey  Right key passed to allowedTo().
     *                          Typically the class/file name without extension,
     *                          e.g. 'ShowPassEncripterPage'.
     *                          Pass '' to skip the allowedTo() check (use only
     *                          when the page has its own auth logic, e.g. AUTH_ADM).
     */
    public function __construct(string $rightKey = '')
    {
        if ($rightKey !== '' && !allowedTo($rightKey)) {
            throw new \Exception('Permission error!');
        }

        $this->run();
    }

    /**
     * Page logic entry point.
     *
     * Override this in subclasses to implement page behaviour.
     * The default implementation dispatches by the 'mode' GET/POST parameter,
     * calling $this->{$mode}() if the method exists, otherwise falling back
     * to $this->show*(). This mirrors the existing ShowSupportPage pattern.
     *
     * Simple pages that have no mode variants should just override run() directly.
     */
    protected function run(): void
    {
        $mode = HTTP::_GP('mode', 'show');

        if ($mode !== 'show' && method_exists($this, $mode)) {
            $this->{$mode}();
        } else {
            $this->index();
        }
    }

    /**
     * Default page handler, called when no mode is given or mode='show'.
     * Override in subclass to render the main page view.
     * Named 'index' to avoid collision with PHP reserved word.
     */
    protected function index(): void
    {
        // Subclasses override either run() or index().
        // Reaching here without an override is a programming error.
        throw new \LogicException(static::class . ' must implement run() or index().');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Template helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Initialise the Twig template wrapper (lazy — called automatically).
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
     * Render a full admin page via template::show().
     *
     * template::show() in MODE=ADMIN calls adm_main() which injects all
     * admin layout variables (nav, user, universe selector, LNG, etc.).
     * This is the correct method for full-page admin renders.
     *
     * @param string $tplFile Template filename, e.g. 'StatsPage.twig'.
     *                        Resolved from styles/templates/adm/.
     */
    protected function show(string $tplFile): void
    {
        $this->initTemplate();
        $this->tplObj->show($tplFile);
    }

    /**
     * Render a status/info message using the standard admin message layout.
     *
     * Wraps template::message() — renders error_message_body.twig via show().
     *
     * @param string      $message  HTML message content.
     * @param string|bool $redirect URL or false for no redirect.
     * @param int         $time     Redirect delay in seconds (default 3).
     * @param bool        $fatal    Whether to mark as fatal error.
     */
    protected function message(
        string $message,
        string|bool $redirect = false,
        int $time = 3,
        bool $fatal = false
    ): void {
        $this->initTemplate();
        $this->tplObj->message($message, $redirect, $time, $fatal);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Response helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send a JSON response and terminate execution.
     *
     * Clears output buffer so no stray HTML contaminates the response.
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
     * Redirect to an admin page and terminate.
     *
     * @param string $page  Target page name, e.g. 'statsconf'.
     *                      Pass a full URL (starting with http) to redirect elsewhere.
     */
    protected function redirectTo(string $page): never
    {
        if (str_starts_with($page, 'http')) {
            HTTP::redirectTo($page);
        } else {
            HTTP::redirectTo('admin.php?page=' . urlencode($page));
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Access helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check that the current admin user has the required right.
     *
     * Calls the global allowedTo() helper used throughout admin pages.
     * Throws on failure — same behaviour as existing top-level page guards.
     *
     * @param string $rightKey  Right key, e.g. 'ShowStatsPage'.
     * @throws \Exception if access is denied.
     */
    protected function checkAccess(string $rightKey): void
    {
        if (!allowedTo($rightKey)) {
            throw new \Exception('Permission error!');
        }
    }

    /**
     * Require full admin level (AUTH_ADM). Redirects to admin index on failure.
     * Use for pages that must be restricted to super-admins only
     * (e.g. ShowSystemDebugPage).
     */
    protected function requireAdminLevel(): void
    {
        global $USER;
        if ((int) ($USER['authlevel'] ?? 0) !== AUTH_ADM) {
            HTTP::redirectTo('admin.php');
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Script helpers (mirrors template methods)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Queue a JS script file to be loaded in the admin layout.
     *
     * @param string $script Path relative to scripts/ (without .js extension).
     */
    protected function loadScript(string $script): void
    {
        $this->initTemplate();
        $this->tplObj->loadscript($script);
    }

    /**
     * Queue an inline JS snippet to execute in the admin layout.
     *
     * @param string $script JS code string.
     */
    protected function execScript(string $script): void
    {
        $this->initTemplate();
        $this->tplObj->execscript($script);
    }
}
