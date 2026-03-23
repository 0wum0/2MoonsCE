# 2MoonsCE – Refactoring Roadmap

> This roadmap tracks planned improvements to code quality, architecture, and maintainability.
> All changes must be **backward compatible** and **incremental**.
> Do NOT break working functionality.

---

## Status Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Done |
| 🔄 | In progress |
| 📋 | Planned |
| ⏸ | On hold |

---

## Phase 1 – Foundation (Current)

### 1.1 Coding Standards Documentation ✅
- [x] `docs/CODING_STYLE.md` created
- [x] `docs/NAMING_CONVENTIONS.md` created
- [x] `docs/ARCHITECTURE.md` created
- [x] `docs/ROADMAP.md` created

### 1.2 Exception Handling Cleanup 🔄
- [x] Policy defined in `CODING_STYLE.md` and `ARCHITECTURE.md`
- [x] `AdminStatsService.php` fixed as reference implementation
- [x] `PluginManager.class.php`: fixed 3 silent catches (`dbGetPlugin`, `getAllPlugins`, `lang()`) — now all log errors
- [x] `ShowOverviewPage.php` (adm): fixed silent `??` on row keys — now logs missing keys
- [ ] Audit remaining game page classes
- [ ] Remove remaining bare `catch (\Exception $e) { $result = []; }` patterns in adm pages

### 1.3 Admin Page Base Class 🔄
- [x] `AbstractAdminPage.php` created
- [x] `ShowStatsPage.php` — style pass applied (`@admin-style`)
- [x] `ShowBanPage.php` — PDO migrated (`@admin-migrated`)
- [x] `ShowFlyingFleetPage.php` — PDO migrated (`@admin-migrated`)
- [ ] Apply AbstractAdminPage class extension to `ShowStatsPage.php`
- [ ] Apply to `ShowIndexPage.php`
- [ ] Apply to remaining admin pages incrementally

### 1.4 Database Unification Preparation ✅ (Phase 3 complete)
- [x] `Database` (PDO) is primary interface for all game pages
- [x] `AdminStatsService.php` uses `Database::get()` (PDO)
- [x] `ShowBanPage.php` migrated (Phase 2)
- [x] `ShowFlyingFleetPage.php` migrated (Phase 2)
- [x] `ShowOverviewPage.php` (adm) — already PDO, style pass applied
- [x] `Database_BC.class.php` marked `@deprecated` with migration instructions
- [x] `ShowSearchPage.php` — `// TODO: @db-migrate` markers added (deferred)
- [x] Full codebase audit documented in `docs/DATABASE_MIGRATION.md`
- [x] Phase 3 batch: 6 more files migrated (see Phase 3 below)
- [ ] Continue Phase 3b: medium-complexity files (see list below)

**Remaining mysqli files — Phase 3b candidates (medium, ~7–13 usages):**
1. 📋 `ShowCronjobPage.php` (7 usages)
2. 📋 `ShowMessageListPage.php` (7 usages)
3. 📋 `ShowRightsPage.php` (7 usages)
4. 📋 `ShowCreatorPage.php` (6 usages)
5. 📋 `ShowTeamspeakPage.php` (1 usage, trivial)
6. 📋 `ShowAutoCompletePage.php` (4 usages, complex ORDER BY)
7. 📋 `ShowDumpPage.php` (2 usages, needs SQLDumper audit)
8. 📋 `ShowInformationPage.php` (2 usages, needs PDO version string helper)

**Remaining mysqli files — Phase 3c (high complexity, defer):**
1. ⏸ `ShowLogPage.php` (13 usages)
2. ⏸ `ShowQuickEditorPage.php` (13 usages)
3. ⏸ `ShowAccountDataPage.php` (11 usages)
4. ⏸ `ShowSearchPage.php` (9 usages, dynamic SQL)
5. ⏸ `ShowUniversePage.php` (20 usages)
6. ⏸ `ShowResetPage.php` (23 usages)
7. ⏸ `ShowAccountEditorPage.php` (51 usages — do last)

---

## Phase 2 – Admin Page Migration

> Migrate all admin pages from plain PHP to `AbstractAdminPage`.
> One page per task, test after each.

**Priority order:**

1. 📋 `ShowStatsPage.php` — style-pass done, extend AbstractAdminPage next
2. 📋 `ShowAccountEditorPage.php`
3. 📋 `ShowAccountDataPage.php`
4. ✅ `ShowBanPage.php` — PDO migrated (style + parameterised queries)
5. ✅ `ShowSendMessagesPage.php` — PDO migrated (Phase 3)
6. 📋 `ShowCronjobPage.php`
7. ✅ `ShowNewsPage.php` — PDO migrated (Phase 3)
8. 📋 All remaining pages

---

## Phase 3 – Database Unification ✅ (Batch 1 complete)

> Full migration from `Database_BC` (mysqli) to `Database` (PDO).
> Reference: `docs/DATABASE_MIGRATION.md`

### Phase 3 Batch 1 — Completed Mar 2026

| File | mysqli Usages Removed | Method |
|------|-----------------------|--------|
| `ShowMultiIPPage.php` | 4 | `select()`, `insert()`, `delete()` |
| `ShowActivePage.php` | 3 | `select()`, `delete()` |
| `ShowNewsPage.php` | 7 | `select()`, `selectSingle()`, `insert()`, `update()`, `delete()` |
| `ShowMenuPage.php` | 1 | `selectSingle()` scalar |
| `ShowSendMessagesPage.php` | 4 | `select()` × 2, lang param filter |
| `ShowConfigUniPage.php` | 1 | `update()` parameterised |
| **Total batch** | **20** | |

**Cumulative total removed:** 31 usages across 8 files
**Still remaining:** ~158 usages across ~15 files

**Migration steps per page (DB):**
1. Add `$db = Database::get();` at top of function
2. Replace `$GLOBALS['DATABASE']->query("SELECT...")` + `while(fetch_array)` → `$db->select(...)` + `foreach`
3. Replace `sql_escape($x)` → `:param` bindings
4. Add `// @admin-migrated (DB: PDO via Database::get())` marker
5. Test page in browser

---

## Phase 4 – Admin Page Architecture ✅ (Batch 1 complete)

> Introduce consistent admin page architecture via `AbstractAdminPage`.
> Reference: `docs/ADMIN_PAGE_MIGRATION.md`

### Phase 4 Batch 1 — Completed Mar 2026

| File | Route | What changed |
|------|-------|--------------|
| `ShowPassEncripterPage.php` | `?page=password` | Plain function → `AbstractAdminPage` subclass |
| `ShowDisclamerPage.php` | `?page=disclamer` | Plain function → `AbstractAdminPage` subclass |
| `ShowStatUpdatePage.php` | `?page=statsupdate` | Plain function → `AbstractAdminPage` subclass |
| `ShowTopnavPage.php` | `?page=topnav` | Plain function → `AbstractAdminPage` subclass |
| `ShowModulePage.php` | `?page=module` | Plain function → `AbstractAdminPage` subclass |

**Also in this phase:**
- [x] `AbstractAdminPage` improved: added `run()` dispatch, `show()` (correct — calls `template::show()`), `message()`, `redirectTo()`, `requireAdminLevel()`, `loadScript()`, `execScript()`
- [x] Fixed pre-existing bug: old `display()` method called `template::display()` which skips `adm_main()` layout injection — now `show()` calls `template::show()` correctly
- [x] `admin.php` routing updated: 5 cases changed from `ShowXxxPage()` → `new ShowXxxPage()`
- [x] `docs/ADMIN_PAGE_MIGRATION.md` created with full risk classification and migration plan
- [x] `docs/ARCHITECTURE.md` updated with accurate admin lifecycle and `AbstractAdminPage` API

### Phase 4 Batch 2 — Planned (medium-risk pages)

| File | Route | Prerequisite |
|------|-------|--------------|
| `ShowClearCachePage.php` | `?page=clearcache` | — |
| `ShowStatsPage.php` | `?page=statsconf` | — |
| `ShowMenuPage.php` | `?page=menu` | DB migrated ✅ |
| `ShowMultiIPPage.php` | `?page=multiips` | DB migrated ✅ |
| `ShowActivePage.php` | `?page=active` | DB migrated ✅ |
| `ShowNewsPage.php` | `?page=news` | DB migrated ✅ |
| `ShowSendMessagesPage.php` | `?page=globalmessage` | DB migrated ✅ |
| `ShowConfigUniPage.php` | `?page=configuni` | DB migrated ✅ |
| `ShowSystemDebugPage.php` | `?page=systemDebug` | — |
| `ShowPluginAdminPage.php` | `?page=pluginAdmin` | — |

### Phase 4 Batch 3 — Deferred (high-risk pages)

⏸ `ShowAccountEditorPage.php`, `ShowResetPage.php`, `ShowUniversePage.php`,
`ShowLogPage.php`, `ShowQuickEditorPage.php`, `ShowAccountDataPage.php`,
`ShowSearchPage.php`, `ShowRightsPage.php`

**Migration steps per page (admin architecture):**
1. Replace `if (!allowedTo(...)) throw ...` + `function ShowXxxPage()` with `class ShowXxxPage extends AbstractAdminPage`
2. Add `public function __construct() { parent::__construct('ShowXxxPage'); }`
3. Move function body into `protected function run(): void`
4. Replace `$template = new template()` + `$template->assign_vars(...)` + `$template->show(...)` with `$this->assign(...)` + `$this->show(...)`
5. Replace `$template->message(...)` with `$this->message(...)`
6. Update `admin.php` case: `ShowXxxPage()` → `new ShowXxxPage()`
7. Add `// @admin-migrated (Phase N — AbstractAdminPage)` marker
8. Test page in browser

---

## Phase 5 – Contribution Standards ✅ (Complete — Mar 2026)

> Establish and enforce practical contribution standards.
> Reference: `docs/CODING_STYLE.md`, `docs/NAMING_CONVENTIONS.md`, `CONTRIBUTING.md`

- [x] `docs/CODING_STYLE.md` — extended with DB access rules (§13), admin page pattern (§14), Twig (§15), JS (§16), legacy vs. new standard (§18)
- [x] `docs/NAMING_CONVENTIONS.md` — extended with admin page classes (§7), plugin conventions (§8), migration markers (§9)
- [x] `CONTRIBUTING.md` — created: branch/commit conventions, PR expectations, architecture rules, reviewer checklist, legacy code policy
- [x] `.editorconfig` — fixed PHP indent from tabs to 4 spaces; expanded to all file types
- [x] `.github/pull_request_template.md` — created
- [x] `.github/ISSUE_TEMPLATE/bug_report.md` — created
- [x] `.github/ISSUE_TEMPLATE/feature_request.md` — created
- [x] `.php-cs-fixer.dist.php` — created (manual-use only, not wired to CI)
- [x] `README.md` contributing section updated

---

## Phase 6 – Error Handling Audit ✅ (Batch 1 complete — Mar 2026)

> Improve error transparency: remove silent catches, log all degraded fallbacks,
> remove pointless catch+rethrow identity blocks.
> Reference: `docs/ERROR_HANDLING_GUIDE.md`

### Phase 6 Batch 1 — Completed Mar 2026

| File | Issue | Fix |
|------|-------|-----|
| `includes/GeneralFunctions.php` | Empty catch in error page builder | Added `error_log()` |
| `includes/pages/login/ShowVertifyPage.class.php` | `// This mail is wayne.` — silent mail catch | Added `error_log()`, fixed catch type to `\Throwable` |
| `includes/pages/game/ShowOverviewPage.class.php` | Silent events table catch | Added `error_log()` |
| `includes/pages/game/AbstractGamePage.class.php` | Forum notif + DateTime fallback — no log | Added `error_log()` to both |
| `includes/pages/adm/ShowSystemDebugPage.php` | Reflection + `isEnabled()` catches — silent | Added `error_log()` to both |
| `includes/pages/adm/ShowInformationPage.php` | Four DateTimeZone catches — silent | Added `error_log()` to all four |
| `includes/libs/tdcron/class.tdcron.php` | Three pointless catch+rethrow identity blocks | Removed |
| `includes/libs/tdcron/class.tdcron.entry.php` | One pointless catch+rethrow identity block | Removed |

**Already correct (no changes needed in Batch 1):**
`AdminStatsService.php`, `PluginManager.class.php`, `ModuleManager.class.php`,
`HookManager.class.php`, `QueueModule.class.php`, `ProductionModule.class.php`,
`class.statbuilder.php`, `ShowForumPage.class.php`, `ShowPluginAdminPage.php`,
`ShowForumAdminPage.php`, `MissionCaseSpy.class.php`

### Phase 6 Batch 2 — Planned

- 📋 Audit remaining game page classes (`includes/pages/game/`) for silent catches
- 📋 Audit remaining admin pages for bare `catch ($e) { $result = []; }` patterns
- 📋 Null coalescing audit: `grep -rn "??" includes/pages/` — replace `??` on required keys with `isset()` + `error_log()`

---

## Phase 7 – Service Architecture ✅ (Batch 1 complete — Mar 2026)

> Separate responsibilities between pages/controllers, services, and database access.
> Reference: `docs/SERVICE_ARCHITECTURE.md`

### Phase 7 Batch 1 — Completed Mar 2026

**New service / repository classes:**

| File | Extracted from | Responsibility |
|------|----------------|----------------|
| `includes/pages/adm/CacheService.php` | `ShowClearCachePage.php` | Cache directory management, safe deletion |
| `includes/pages/adm/BanService.php` | `ShowBanPage.php` | Ban/unban logic, `%%BANNED%%` + `%%USERS%%` writes |
| `includes/pages/adm/NewsRepository.php` | `ShowNewsPage.php` | CRUD on `%%NEWS%%` table |

**Pages migrated to `AbstractAdminPage` in this batch:**

| File | What changed |
|------|-------------|
| `ShowClearCachePage.php` | Plain function → `AbstractAdminPage`; logic moved to `CacheService` |
| `ShowStatsPage.php` | Plain function → `AbstractAdminPage` |
| `ShowBanPage.php` | Plain function → `AbstractAdminPage`; SQL moved to `BanService` |
| `ShowNewsPage.php` | Plain function → `AbstractAdminPage`; SQL moved to `NewsRepository` |

**`admin.php` routing:** 4 cases updated from `ShowXxxPage()` → `new ShowXxxPage()`

**`AbstractAdminPage` subclass count: 10** (was 6 after Phase 4)

### Phase 7 Batch 2 — Planned

- 📋 `ShowFlyingFleetPage.php` — extract fleet query + lock/unlock to `FleetAdminService`
- 📋 `ShowCronjobPage.php` — extract CRUD to `CronjobRepository` (already has `Cronjob::` statics for logic)
- 📋 `ShowSendMessagesPage.php` — extract bulk message/mail dispatch to `BroadcastService`
- 📋 Migrate all remaining plain-function admin pages to `AbstractAdminPage` (see Phase 4 Batch 2 list)

---

## Phase 8 – Installer & Admin Harmonization ✅ (Batch 1 complete — Mar 2026)

> Harmonize the legacy installer and remaining legacy admin entry flow with the
> newer project architecture. Reduce inconsistency, fix legacy DB usage, align
> permission guards, and remove `$GLOBALS['DATABASE']` from remaining admin pages.
> Reference: `docs/INSTALLER_ADMIN_HARMONIZATION.md`

### Phase 8 Batch 1 — Completed Mar 2026

**Admin pages migrated to `AbstractAdminPage` + PDO:**

| File | What changed |
|------|-------------|
| `ShowAutoCompletePage.php` | `$GLOBALS['DATABASE']` + raw string SQL (injection risk) → `AbstractAdminPage` + PDO bound params |
| `ShowDumpPage.php` | `$GLOBALS['DATABASE']` table listing → `AbstractAdminPage` + `Database::get()->nativeQuery()` |
| `ShowMenuPage.php` | Plain function (no guard) → `AbstractAdminPage` with `allowedTo()` |

**Permission guards aligned (logic unchanged):**

| File | Before | After |
|------|--------|-------|
| `ShowLoginPage.php` | `if ($USER['authlevel'] == AUTH_USR)` | `allowedTo()` via file-scope guard |
| `ShowLogoutPage.php` | `if ($USER['authlevel'] == AUTH_USR)` | `allowedTo()` via file-scope guard |

**Installer fix:**

| File | Issue | Fix |
|------|-------|-----|
| `install/index.php` | `ClearCache()` called after `doupgrade` — old Smarty-era function | Replaced with `CacheService::clearAll()` |

**`admin.php` routing:** 3 cases updated from `ShowXxxPage()` → `new ShowXxxPage()`

**`AbstractAdminPage` subclass count: 13** (was 10 after Phase 7)

**`$GLOBALS['DATABASE']` remaining usages in admin pages:**
- `ShowGiveawayPage.php` — deferred (high-risk mass UPDATE with dynamic columns)

### Phase 8 Batch 2 — Planned

- 📋 Migrate `ShowGiveawayPage.php` to PDO + `AbstractAdminPage`
- 📋 Migrate `ShowVertify.php` to Twig templates (last `.tpl` references in admin)
- 📋 Fix installer step 2: `register_globals` check (always vacuously true on PHP 8)
- 📋 Migrate remaining ~30 plain-function admin pages to `AbstractAdminPage`

---

## Phase 9 – Plugin & Extension Architecture ✅ (Batch 1 complete — Mar 2026)

> Prepare 2MoonsCE for a cleaner extension/plugin/modding architecture without a
> risky full rewrite. Document all existing extension points, introduce missing
> hook spots, and enable `AbstractAdminPage` subclasses as plugin admin handlers.
> Reference: `docs/PLUGIN_ARCHITECTURE.md`

### Phase 9 Batch 1 — Completed Mar 2026

**New documentation:**

| File | Contents |
|------|----------|
| `docs/PLUGIN_ARCHITECTURE.md` | Full authoritative plugin reference: hook catalogue (18+ Twig spots), manifest schema, PHP lifecycle hooks, game data filters, routing patterns, module system, element registry, config, lang, safe-mode, quick-start checklist |

**Code changes:**

| File | Change |
|------|--------|
| `includes/classes/PluginManager.class.php` | `dispatchAdminRoute()` now supports `AbstractAdminPage` subclasses as admin handlers (class detected via `class_exists()` + `is_a()`). Plain-function style retained for backward compatibility. |
| `styles/templates/adm/overall_header.twig` | Added `admin.sidebar.bottom` hook (bottom of sidebar nav, before logout) and `admin.content.top` hook (top of main content area). |

**Documentation updated:**

| File | What changed |
|------|-------------|
| `docs/ARCHITECTURE.md` | §6 Plugin System expanded with full extension layer table, API summary, Twig spot summary, lifecycle hooks, game data filters, safe-mode |
| `CONTRIBUTING.md` | §1 reading list: `PLUGIN_ARCHITECTURE.md` added. §5 Plugins: expanded from 3 lines to full section with manifest rules, bootstrap rules, both admin route styles with examples, hook registration examples, and Twig spot list |

**Twig hook spot count: 18 ingame + 8 admin = 26 total**

**Admin route handler styles supported:**
- Style 1 (Phase 9+): `AbstractAdminPage` subclass — `new $class()` triggered automatically
- Style 2 (legacy, backward-compatible): plain function — `$fn()` called directly

### Phase 9 Batch 2 — Planned

- 📋 `admin.php` `beforeController` / `afterController` hook context: add `$rightKey` so plugins know which page is being accessed without inspecting `$_GET`
- 📋 `MissionRegistry` for plugin-defined fleet mission types (high-risk, deferred)
- 📋 `game.php` plugin page route dispatch: support `AbstractGamePage` subclass auto-detection (currently requires manual `show()`/`$defaultController` convention)
- 📋 Add `ingame.overview.after_resources` Twig hook spot on the overview resource bar
- 📋 Expose hook debug panel in `ShowSystemDebugPage` — list all registered actions and filters with priority and callback signatures

---

## Phase 10 – Code Style Normalization

> Apply `CODING_STYLE.md` rules to touched files incrementally.

**Rules to enforce:**
- 4-space indentation (no tabs)
- Space before `(` in control structures
- Always-braces
- One statement per line

**Process:** Apply during regular development when touching a file. Do NOT mass-reformat.

---

## Phase 11 – Long-term (Future)

- 📋 PSR-4 autoloading (replace manual `require_once` chains)
- 📋 Dependency injection container (replace global singletons)
- 📋 Unit test coverage for service classes
- 📋 CI pipeline (PHP CS Fixer, PHPStan level 5+)
- 📋 Admin API layer (RESTful endpoints for AJAX admin actions)
- 📋 `MissionRegistry` for plugin-defined fleet missions
- 📋 `NotificationProvider` interface for plugin-defined notification badge types
