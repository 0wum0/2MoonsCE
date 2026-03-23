# 2MoonsCE – Installer & Legacy Admin Harmonization Guide

> Based on direct inspection of the real codebase as of Phase 8 (Mar 2026).
> This document captures the findings, decisions, and deferred work from the
> installer/legacy-admin harmonization phase.

---

## 1. Scope

This document covers:
- `install/index.php` — single-file installer/upgrade tool
- `admin.php` — admin entry point and dispatch switch
- Legacy admin pages in `includes/pages/adm/` that have not yet been migrated
  to `AbstractAdminPage` or PDO

It does NOT cover:
- Game-side pages (`includes/pages/game/`)
- Login bootstrap (`includes/pages/login/`)
- Core gameplay systems (battle, fleet, missions)

---

## 2. Installer Structure

### `install/index.php`

The installer is a single 629-line procedural file. It handles all modes via a
top-level `switch ($mode)`:

| Mode | Description |
|------|-------------|
| `(default)` | Welcome / intro screen |
| `install` + steps 1–8 | Full fresh installation wizard |
| `upgrade` | Show pending migrations |
| `doupgrade` | Apply migrations + DB backup |
| `ajax` | FTP chmod helper (legacy feature) |

**Bootstrap:** Calls `includes/common.php` with `MODE=INSTALL`. Uses
`Database::get()` (PDO) for all DB access in upgrade/install modes. Uses the
project's standard `template` class with `.tpl` templates.

**Security gate:** `includes/ENABLE_INSTALL_TOOL` — must exist and be younger
than 1 hour. Upgrade mode bypasses the file gate if `config.php` already exists.

### Issues found in the installer

| # | Issue | Severity | Fixed in Phase 8 |
|---|-------|----------|-----------------|
| 1 | `ClearCache()` called after `doupgrade` (line 276) — this is the old Smarty-era function that no longer exists as a standalone; should use `CacheService::clearAll()` | Medium | ✅ Yes |
| 2 | `doupgrade` uses `curl_init()` to execute PHP migration scripts via HTTP — this is a legacy mechanism; documented as `// TODO: Need a rewrite!` in code | Low (works) | Deferred |
| 3 | Step 2 requirement check: `ini_get('register_globals')` — removed in PHP 5.4; always returns `''` on PHP 8, meaning the check is vacuously true and produces a false "yes" | Low (cosmetic) | Deferred |
| 4 | Step 6 error handler references `$databaseConfig` which is only available after `require_once 'includes/config.php'` inside the catch — config keys might not resolve | Low (edge case) | Deferred |

---

## 3. Admin Entry Point (`admin.php`)

### Structure

```
admin.php
  define('MODE', 'ADMIN')
  define('DATABASE_VERSION', 'OLD')   ← vestigial constant, never read
  require_once 'includes/common.php'
  Auth guard: $USER['authlevel'] == AUTH_USR → redirect to game.php
  Session check: $session->adminAccess != 1 → ShowLoginPage() and exit
  Universe::setEmulated($uni)
  PluginManager::get()->dispatchAdminRoute($page)
  HookManager::get()->doAction('beforeController')
  switch ($page) { ... }
  HookManager::get()->doAction('afterController')
```

### Issues found in admin.php

| # | Issue | Severity | Fixed in Phase 8 |
|---|-------|----------|-----------------|
| 1 | `define('DATABASE_VERSION', 'OLD')` — constant defined but never read anywhere in the codebase | Cosmetic | Documented (not removed — conservative) |
| 2 | Mixed dispatch: 10 pages use `new ShowXxxPage()`, 23 use `ShowXxxPage()` (plain function). No automatic detection. | Medium | Ongoing — Phase 7 reduced this from 37→27 plain functions |
| 3 | `ShowLoginPage` loaded via `include_once` outside the switch — intentional pattern, not a bug | N/A | N/A |

---

## 4. Legacy Admin Pages — Findings

### Pattern inconsistencies identified

#### A. Permission guard styles (3 different patterns)

```php
// Pattern 1: allowedTo() — correct, used by most modern pages
if (!allowedTo(str_replace([dirname(__FILE__), '\\', '/', '.php'], '', __FILE__))) {
    throw new \Exception('Permission error!');
}

// Pattern 2: raw authlevel check — used by ShowLoginPage, ShowLogoutPage, ShowDumpPage, ShowAutoCompletePage
if ($USER['authlevel'] == AUTH_USR) {
    throw new Exception("Permission error!");
}

// Pattern 3: no guard — ShowMenuPage (relies entirely on admin.php gate)
```

**Pattern 2** is weaker than Pattern 1: it only checks that the caller is not a
regular user, but does not check per-page rights via the `allowedTo()` registry.
For pages that are inherently admin-only (login, logout, dump, autocomplete), this
is functionally acceptable but structurally inconsistent.

**Pattern 3** (no guard) relies on the `admin.php` session gate being the only
protection. Acceptable for the menu page (purely informational), but should have
a guard for clarity.

#### B. Legacy `$GLOBALS['DATABASE']` usage

Three pages still use the deprecated `Database_BC`/`$GLOBALS['DATABASE']` mysqli
wrapper:

| File | Usage | Risk |
|------|-------|------|
| `ShowAutoCompletePage.php` | `$GLOBALS['DATABASE']->query()`, `->escape()`, `->sql_escape()`, `->fetch_array()` | **High** — SQL injection via string concatenation |
| `ShowDumpPage.php` | `$GLOBALS['DATABASE']->query()`, `->fetchArray()` | Medium — read-only query |
| `ShowGiveawayPage.php` | `$GLOBALS['DATABASE']->query()` | **High** — mass UPDATE with dynamic columns |

#### C. Legacy `.tpl` template usage

| File | Template |
|------|----------|
| `ShowVertify.php` | `VertifyPage.tpl`, `VertifyPageResult.tpl` |
| `ShowGiveawayPage.php` | `giveaway.tpl` |

These are the last `.tpl` (Smarty-era) template references in admin pages.
The template engine now uses Twig and falls back gracefully, but these should be
migrated. **Deferred.**

---

## 5. Harmonization Work Done in Phase 8

### 5.1 `install/index.php` — Fix `ClearCache()` → `CacheService::clearAll()`

The `doupgrade` case called `ClearCache()` after applying migrations. `ClearCache()`
is the old Smarty-era function that no longer exists as a standalone function.
Replaced with `CacheService::clearAll()`, which is the Phase 7 extraction of the
same logic.

### 5.2 `ShowAutoCompletePage.php` — PDO migration + AbstractAdminPage

Migrated from:
- `$GLOBALS['DATABASE']->query()` with raw string SQL (injection risk)
- Plain PHP function
- `if ($USER['authlevel'] == AUTH_USR)` guard

To:
- `Database::get()->select()` with parameterized query (LIKE search bound via PDO)
- `AbstractAdminPage` subclass
- `allowedTo()` guard via `parent::__construct()`
- JSON output via `$this->sendJSON()` (exits cleanly)

**SQL injection fix:** The old code concatenated `->escape($searchText)` directly
into the LIKE clause and used `->sql_escape()` for the ORDER BY scoring. Replaced
with a single PDO bound parameter `LIKE :term`.

### 5.3 `ShowDumpPage.php` — PDO migration for table listing

Migrated the `SHOW TABLE STATUS` query from `$GLOBALS['DATABASE']->query()` +
`->fetchArray()` loop to `Database::get()->nativeQuery()` (already used by the
installer for the same query). The `dump` action was already using `SQLDumper`
correctly — untouched.

Permission guard aligned from `if ($USER['authlevel'] == AUTH_USR)` to
`allowedTo()` via `AbstractAdminPage`.

### 5.4 `ShowMenuPage.php` — permission guard added

Added `allowedTo()` guard consistent with all other admin pages.
Migrated from plain function to `AbstractAdminPage` subclass.

### 5.5 `ShowLoginPage.php` — permission guard aligned

No functional change. The `if ($USER['authlevel'] == AUTH_USR)` file-scope guard
is replaced with the standard `allowedTo()` pattern. The login logic is unchanged.

### 5.6 `ShowLogoutPage.php` — permission guard aligned

Same as ShowLoginPage — file-scope guard aligned to `allowedTo()`. Logic unchanged.

---

## 6. Deferred Work

| File / Area | Issue | Reason deferred |
|-------------|-------|----------------|
| `ShowGiveawayPage.php` | `$GLOBALS['DATABASE']` + raw SQL mass UPDATE | High risk: dynamic column list, complex query |
| `ShowVertify.php` | Legacy `.tpl` templates, curl to old repo | Feature is obsolete; low priority |
| `install/index.php` step 2 | `register_globals` check always passes on PHP 8 | Cosmetic; installer works correctly |
| `install/index.php` doupgrade | PHP migration files executed via curl | `// TODO: Need a rewrite!` already in code |
| `admin.php` | `DATABASE_VERSION = 'OLD'` constant vestigial | Conservative: may be read by third-party plugins |
| All remaining plain-function admin pages | ~27 still as plain functions | Phase 7 Batch 2 scope |

---

## 7. Alignment Rules Going Forward

1. **All new admin pages** must extend `AbstractAdminPage` and use `parent::__construct('RightKey')`.
2. **No new `$GLOBALS['DATABASE']` usage** — always use `Database::get()`.
3. **Permission guards** must use `allowedTo()` via the constructor, not raw `authlevel` checks.
4. **Installer** must not be restructured in a single pass — changes must be isolated to individual `case` blocks.
5. **Legacy `.tpl` templates** — migrate when touching the page for another reason; do not mass-migrate.
6. **`CacheService::clearAll()`** is the canonical cache-clear method. Remove any remaining `ClearCache()` calls when found.
