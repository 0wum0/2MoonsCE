# Contributing to 2MoonsCE

Thank you for your interest in contributing. This guide explains how to work with the codebase effectively, keep PRs reviewable, and avoid breaking existing behaviour.

---

## Table of Contents

1. [Before You Start](#1-before-you-start)
2. [Branch and Commit Conventions](#2-branch-and-commit-conventions)
3. [Pull Request Expectations](#3-pull-request-expectations)
4. [Code Style](#4-code-style)
5. [Architecture and Migration Rules](#5-architecture-and-migration-rules)
6. [Testing and Verification](#6-testing-and-verification)
7. [Review Checklist](#7-review-checklist)
8. [What Not to Do](#8-what-not-to-do)
9. [Legacy Code Policy](#9-legacy-code-policy)
10. [Getting Help](#10-getting-help)

---

## 1. Before You Start

- Read [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — understand the request lifecycle, DB layer, plugin system, and admin page pattern before touching related code.
- Read [`docs/CODING_STYLE.md`](docs/CODING_STYLE.md) — all touched code must follow the style rules.
- Read [`docs/NAMING_CONVENTIONS.md`](docs/NAMING_CONVENTIONS.md) — naming rules for PHP, JS, Twig, LNG keys, plugins.
- Read [`docs/ERROR_HANDLING_GUIDE.md`](docs/ERROR_HANDLING_GUIDE.md) — exception handling rules, pattern catalogue, and per-file status.
- Read [`docs/ROADMAP.md`](docs/ROADMAP.md) — know which migrations are in progress before starting a related task.
- Read [`docs/PLUGIN_ARCHITECTURE.md`](docs/PLUGIN_ARCHITECTURE.md) — if writing a plugin or adding extension points: full hook catalogue, manifest schema, module system, and safe-mode rules.
- Check [open issues](https://github.com/0wum0/2MoonsCE/issues) and [discussions](https://github.com/0wum0/2MoonsCE/discussions) to avoid duplicate work.

---

## 2. Branch and Commit Conventions

### Branch naming

```
feature/short-description       # new features
fix/short-description           # bug fixes
refactor/short-description      # code cleanup, migration steps
docs/short-description          # documentation only
chore/short-description         # tooling, config, CI
```

### Commit messages

Use [Conventional Commits](https://www.conventionalcommits.org/) style:

```
feat: add GalacticEvents plugin cronjob
fix: resolve fleet dot visibility on galaxy map
refactor: migrate ShowMenuPage to AbstractAdminPage
docs: update ROADMAP.md with Phase 4 Batch 1 results
chore: add .editorconfig
```

- One purpose per commit where possible
- Use the imperative: "add", "fix", "update" — not "added", "fixed", "updated"
- Keep the subject line under 72 characters

---

## 3. Pull Request Expectations

### One purpose per PR

Each PR should have a single, clear purpose. Do not mix:
- Feature work + refactoring
- Bug fixes + formatting cleanups
- Migration steps + unrelated changes

If you find a separate bug while working on something else, open a separate issue or PR.

### PR description must include

- **What** the PR does
- **Why** the change is needed
- **How** to verify it (steps to reproduce the bug, or steps to test the feature)
- **Screenshots** if there is any visible UI change
- **Risk** level — note if any existing behaviour is changed or could be affected

### PR size

- Keep PRs small and focused — easier to review, less risk of regressions
- Large migrations (e.g. migrating many admin pages) should be split into batches
- A PR that touches 30+ unrelated files is likely doing too many things at once

### Draft PRs

Use draft PRs for work in progress. Convert to ready when all checklist items pass.

---

## 4. Code Style

All touched code must follow [`docs/CODING_STYLE.md`](docs/CODING_STYLE.md).

Quick summary of required rules:

- **4 spaces** indentation — never tabs
- `if ($x) {` style — space before `(`, opening brace on same line
- Always use braces, even for single-line bodies
- Short array syntax `[]` — never `array()`
- `declare(strict_types=1);` at top of every PHP file
- No closing `?>` in pure PHP files
- Type declarations on all new/modified methods
- `const` / `let` in JS — never `var`
- `===` / `!==` in JS — never `==` / `!=`

### What reviewers check for style

- Consistent indentation in touched code
- No mixed tab/space in the same file section
- Braces present for all control structures
- No silent exception swallowing
- Short array syntax in all new code

---

## 5. Architecture and Migration Rules

### Database access

Always use `Database::get()` (PDO) in new and touched code. Never write new code using `$GLOBALS['DATABASE']` (mysqli legacy).

```php
// GOOD
$db   = Database::get();
$rows = $db->select('SELECT * FROM %%USERS%% WHERE universe = :uni;', [':uni' => $uni]);

// BAD — do not add new usages
$GLOBALS['DATABASE']->query("SELECT * FROM " . USERS);
```

See [`docs/DATABASE_MIGRATION.md`](docs/DATABASE_MIGRATION.md) for current migration status.

### Admin pages

New admin pages must extend `AbstractAdminPage`. Do not write new plain-function admin pages.

```php
// GOOD — new admin page
class ShowXxxPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowXxxPage');
    }

    protected function run(): void
    {
        $this->assign([...]);
        $this->show('XxxPage.twig');
    }
}
```

When migrating an existing page, follow the steps in [`docs/ADMIN_PAGE_MIGRATION.md`](docs/ADMIN_PAGE_MIGRATION.md).

When adding a new case to `admin.php`, use `new ShowXxxPage()` — not `ShowXxxPage()`.

### Plugins

> **Full reference:** [`docs/PLUGIN_ARCHITECTURE.md`](docs/PLUGIN_ARCHITECTURE.md) — hook catalogue, manifest schema, module system, safe-mode rules.

#### Manifest rules

- Plugin folder name must match the `id` field in `manifest.json` **exactly** (case-sensitive)
- `id` must match `[a-zA-Z0-9\-_]+`
- `version` must start with `MAJOR.MINOR` (e.g. `1.0.0`)
- See `docs/NAMING_CONVENTIONS.md §8` for full plugin naming rules

#### Bootstrap (plugin.php)

- Runs on every page request when the plugin is active — **keep it fast**
- Must **never** throw an uncaught exception — wrap all DB calls in `try/catch`
- Must **never** produce output

#### Admin page routes — two supported styles

**Style 1 (preferred, Phase 9+): `AbstractAdminPage` subclass**

```php
// plugin.php
PluginManager::get()->registerAdminRoute(
    'MyPlugin',
    'plugin_my_page',
    'admin/ShowMyAdminPage.php',
    'ShowMyAdminPage'        // class name
);

// admin/ShowMyAdminPage.php
class ShowMyAdminPage extends AbstractAdminPage
{
    public function __construct() { parent::__construct('ShowMyAdminPage'); }
    protected function run(): void
    {
        $this->assign([...]);
        $this->show('@MyPlugin/admin/my_page.twig');
    }
}
```

**Style 2 (legacy, backward-compatible): plain function**

```php
// plugin.php
PluginManager::get()->registerAdminRoute(
    'MyPlugin',
    'plugin_my_page',
    'admin/MyAdminPage.php',
    'ShowMyAdminPage'        // function name — MUST match exactly
);

// admin/MyAdminPage.php
function ShowMyAdminPage(): void
{
    $template = new template();
    $template->assign_vars([...]);
    $template->show('MyAdminPage.twig');
}
```

`dispatchAdminRoute()` checks `class_exists()` first; if found and it extends `AbstractAdminPage`, the class is instantiated. Otherwise it falls back to `function_exists()`.

**Critical:** if neither the class nor the function exists after `require_once`, `dispatchAdminRoute()` returns `false` → the admin router falls through to the default case → `ShowIndexPage()` → `ShowOverviewPage()` → potential DB errors. Always verify the 4th parameter matches the defined symbol.

#### Hook registration

```php
// Action (event notification)
HookManager::get()->addAction('head_end', static function (array $ctx): string {
    return '<link rel="stylesheet" href="plugins/MyPlugin/assets/css/my.css">' . "\n";
}, 20);

// Filter (data transformation)
HookManager::get()->addFilter('game.planet', function (array $planet): array {
    $planet['my_column'] ??= 0;
    return $planet;
});
```

#### Available Twig hook spots

See [`docs/PLUGIN_ARCHITECTURE.md §7`](docs/PLUGIN_ARCHITECTURE.md#7-twig-hook-spots-reference) for the full list. Key ingame spots: `head_end`, `content_top`, `footer_end`. Key admin spots: `admin.sidebar.top`, `admin.sidebar.modules`, `admin.sidebar.bottom`, `admin.content.top`, `footer_end`.

### Template rendering

- Use `$this->show('Template.twig')` for full-page admin renders — it calls `adm_main()` which injects the layout
- **Never** call `$this->tplObj->display()` directly — it skips the admin layout
- Use `$this->message(...)` for status/confirmation messages

### Exception handling

Never silently swallow exceptions. See [`docs/ARCHITECTURE.md §7`](docs/ARCHITECTURE.md) for the full policy.

```php
// BAD — silent swallow
try {
    $result = $db->select($sql, $params);
} catch (\Throwable $e) {
    $result = [];
}

// GOOD — log and handle
try {
    $result = $db->select($sql, $params);
} catch (\Throwable $e) {
    error_log('[MyPage] Query failed: ' . $e->getMessage());
    $result = [];  // only if [] is a documented valid fallback
}
```

---

## 6. Testing and Verification

There is currently no automated test suite for the full game. Verification is manual.

### Before submitting a PR

- Test the affected pages in a local dev environment with PHP 8.3+
- Confirm no white-screen or fatal errors on the affected routes
- Confirm no regressions on closely related routes (e.g. if migrating an admin page, test adjacent pages too)
- For bug fixes: confirm the bug is no longer reproducible
- For DB migrations: confirm queries execute correctly and return expected data
- For admin page migrations: confirm the page renders with the full admin layout (sidebar, topbar, nav)
- For plugin changes: test activate/deactivate/reinstall cycle

### Local setup

```bash
git clone https://github.com/0wum0/2MoonsCE.git
cd 2MoonsCE
composer install
# Set up DB via install/ wizard, then:
# Open http://localhost/admin.php and http://localhost/game.php
```

---

## 7. Review Checklist

Reviewers use this checklist when evaluating PRs.

### Correctness

- [ ] Behaviour is preserved or intentionally changed with explanation
- [ ] No new silent exception swallows
- [ ] No new direct mysqli usage in files that have been PDO-migrated
- [ ] No new `array()` syntax — only `[]`
- [ ] No `var` in JavaScript — only `const` / `let`
- [ ] Type declarations present on new/modified PHP methods

### Architecture

- [ ] New admin pages extend `AbstractAdminPage`
- [ ] Admin pages use `$this->show()` not `$this->tplObj->display()`
- [ ] DB queries use `Database::get()` with `:param` bindings
- [ ] No SQL concatenation of user input
- [ ] Plugin `id` in manifest matches folder name exactly
- [ ] Plugin route handler function name matches `registerAdminRoute()` 4th parameter

### Style

- [ ] 4-space indentation in touched sections
- [ ] Braces on all control structures
- [ ] Opening brace on same line as control keyword
- [ ] No trailing whitespace
- [ ] One statement per line

### Documentation

- [ ] Migration marker added (`// @admin-migrated` or `// @admin-migrated (DB: ...)`)
- [ ] `docs/ROADMAP.md` updated if completing a planned migration step
- [ ] New patterns or architectural decisions documented in relevant `docs/` file
- [ ] `CHANGELOG.md` entry added if the change is user- or operator-visible

---

## 8. What Not to Do

These actions will cause a PR to be rejected or require major rework:

| ❌ Don't | ✅ Do instead |
|----------|--------------|
| Reformat untouched legacy files | Only format lines you are actually changing |
| Mix refactor + feature + style fix in one PR | Split into separate PRs |
| Rename DB columns or legacy variables globally | Leave them — migrations are planned separately |
| Add new `$GLOBALS['DATABASE']` usages | Use `Database::get()` |
| Write new plain-function admin pages | Extend `AbstractAdminPage` |
| Silently swallow exceptions | Log them, or rethrow |
| Call `template::display()` from admin pages | Use `$this->show()` |
| Add new `array()` syntax | Use `[]` |
| Use `var` in new JS | Use `const` / `let` |
| Submit a PR with no description | Fill in the PR template |
| Force-push to main/master | Use feature branches and PRs |

---

## 9. Legacy Code Policy

Most of the codebase is a modernized fork of a 2009 PHP game engine. Legacy code is expected and tolerated.

**Legacy indicators you will find** (do not "fix" unless explicitly tasked):
- `$GLOBALS['DATABASE']` — mysqli legacy, being migrated in Phase 3
- Plain PHP admin page functions — being migrated in Phase 4
- `array()` syntax in older files
- Missing type declarations
- Mixed naming styles from original 2Moons

**The incremental migration strategy:**
- Phase 3: DB layer — mysqli → PDO (see `docs/DATABASE_MIGRATION.md`)
- Phase 4: Admin page architecture — plain functions → `AbstractAdminPage` (see `docs/ADMIN_PAGE_MIGRATION.md`)
- Phase 5+: Exception handling, null coalescing audit (see `docs/ROADMAP.md`)

Full repo normalization is a long-term effort spanning many phases.
Do not attempt to do it all in one PR.

---

## 10. Getting Help

- **Questions and discussions:** [GitHub Discussions](https://github.com/0wum0/2MoonsCE/discussions)
- **Bug reports:** [GitHub Issues](https://github.com/0wum0/2MoonsCE/issues) — use the bug report template
- **Feature requests:** [GitHub Issues](https://github.com/0wum0/2MoonsCE/issues) — use the feature request template
- **Architecture questions:** Read `docs/ARCHITECTURE.md` first, then open a discussion if still unclear

---

*2MoonsCE – Community Edition · Licensed under GPLv2*
