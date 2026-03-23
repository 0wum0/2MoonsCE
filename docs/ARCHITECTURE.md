# 2MoonsCE – Architecture Overview

---

## 1. Project Structure

```
2MoonsCE/
├── admin.php                    # Admin entry point
├── game.php                     # Game entry point (dispatches to page classes)
├── includes/
│   ├── common.php               # Bootstrap: session, DB, planet, user loading
│   ├── classes/                 # Core singletons and utilities
│   │   ├── Database.class.php   # PDO-based database wrapper (PRIMARY)
│   │   ├── Database_BC.class.php# mysqli backward-compat layer (DEPRECATED)
│   │   ├── Config.class.php
│   │   ├── Session.class.php
│   │   ├── HTTP.class.php
│   │   ├── PluginManager.class.php
│   │   ├── HookManager.class.php
│   │   ├── ModuleManager.class.php
│   │   └── ...
│   └── pages/
│       ├── game/                # Game-facing pages (extend AbstractGamePage)
│       │   ├── AbstractGamePage.class.php
│       │   └── ShowXxxPage.class.php
│       ├── adm/                 # Admin pages (plain PHP; migrate to AbstractAdminPage)
│       │   ├── AbstractAdminPage.php   ← NEW base class (see §5)
│       │   ├── AdminStatsService.php
│       │   └── ShowXxxPage.php
│       └── login/
│           └── AbstractLoginPage.class.php
├── plugins/                     # Plugin system v1.2
├── styles/
│   └── templates/
│       ├── game/                # Game Twig templates
│       └── adm/                 # Admin Twig templates
├── language/
│   ├── de/ADMIN.php
│   └── en/ADMIN.php
└── docs/                        # ← This directory
```

---

## 2. Request Lifecycle (Game)

```
game.php
  └─ common.php (bootstrap: session, DB, $USER, $PLANET, resource update)
       └─ game.php: resolve $page → ShowXxxPage class
            └─ ShowXxxPage extends AbstractGamePage
                 ├─ __construct(): CalcResource() + initTemplate()  [non-AJAX]
                 ├─ __construct(): setWindow('ajax')                [AJAX_REQUEST]
                 └─ $mode(): show() | rename() | delete() | ...
                      └─ $this->display('template.twig')
```

Key: `AJAX_REQUEST = HTTP::_GP('ajax', 0)` — always pass `ajax=1` in XHR/fetch calls to avoid full bootstrap overhead.

---

## 3. Request Lifecycle (Admin)

```
admin.php
  └─ define('MODE', 'ADMIN')
  └─ common.php (bootstrap: session, DB, $USER, universe)
  └─ auth guard: AUTH_USR → game.php | no adminAccess → ShowLoginPage
  └─ Universe::setEmulated($uni)
  └─ PluginManager->dispatchAdminRoute($page)  ← plugin pages (exits on match)
  └─ HookManager->doAction('beforeController')
  └─ switch($page):
       ├─ Legacy:   include ShowXxxPage.php → ShowXxxPage()          (plain function)
       └─ Migrated: include ShowXxxPage.php → new ShowXxxPage()      (AbstractAdminPage)
            └─ AbstractAdminPage::__construct($rightKey)
                 ├─ allowedTo($rightKey) check
                 └─ $this->run() → page logic → $this->show('Tpl.twig')
                      └─ template::show() → adm_main() → Twig render
  └─ HookManager->doAction('afterController')
```

**Key:** `template::show()` in `MODE=ADMIN` always calls `adm_main()` which injects
all layout variables (nav, universe selector, user, safe-mode notices, LNG, etc.).
Page files only assign their own data. Never call `template::display()` for full pages.

---

## 4. Database Layer

### Current State

| Layer | Class | Driver | Used By |
|-------|-------|--------|---------|
| Primary | `Database` (singleton) | PDO | All game pages, admin service classes |
| Legacy compat | `Database_BC` | mysqli | Old admin/install code |

### PDO Wrapper API (`Database::get()`)

```php
$db = Database::get();

// Single row
$row = $db->selectSingle("SELECT * FROM %%USERS%% WHERE id = :id;", [':id' => 5]);

// Multiple rows
$rows = $db->select("SELECT id, username FROM %%USERS%% WHERE universe = :uni;", [':uni' => 1]);

// Insert
$id = $db->insert("INSERT INTO %%NEWS%% (title) VALUES (:t);", [':t' => 'Hello']);

// Update
$db->update("UPDATE %%USERS%% SET username = :n WHERE id = :id;", [':n' => 'Bob', ':id' => 5]);

// Delete
$db->delete("DELETE FROM %%PLANETS%% WHERE id = :id;", [':id' => 42]);
```

Table name placeholders `%%TABLE_NAME%%` are resolved automatically by the wrapper.

### Migration Plan (DB Unification)

**Phase 1 – Done:** All game pages use `Database::get()` (PDO).

**Phase 2 – In progress:** Admin service classes use `Database::get()`. ✓

**Phase 3 – TODO:** Migrate remaining `Database_BC` (mysqli) usages in admin pages to `Database::get()`.
- Search: `grep -r "Database_BC\|new mysqli\|mysqli_" includes/pages/adm/`
- Replace one file at a time; test after each
- Mark completed files with `// @db-migrated` comment

**Phase 4 – TODO:** Remove `Database_BC.class.php` once no usages remain.

---

## 5. Admin Page Architecture

### Current State (after Phase 8)

| Type | Count | Example |
|------|-------|---------|
| `AbstractAdminPage` subclass | 13 | `ShowPassEncripterPage`, `ShowDisclamerPage`, `ShowStatUpdatePage`, `ShowTopnavPage`, `ShowModulePage`, `ShowSupportPage`, `ShowClearCachePage`, `ShowStatsPage`, `ShowBanPage`, `ShowNewsPage`, `ShowAutoCompletePage`, `ShowDumpPage`, `ShowMenuPage` |
| Plain PHP function | ~30 | `ShowConfigBasicPage`, `ShowCronjobPage`, … |

> See `docs/INSTALLER_ADMIN_HARMONIZATION.md` for findings and deferred work.

### `AbstractAdminPage` API

Location: `includes/pages/adm/AbstractAdminPage.php`

```php
abstract class AbstractAdminPage
{
    // Constructor: runs allowedTo($rightKey) then $this->run()
    public function __construct(string $rightKey = '')

    // Override in subclass — page logic entry point
    protected function run(): void

    // Template
    protected function initTemplate(): void
    protected function assign(array $vars): void
    protected function show(string $tplFile): void        // ← full admin layout (calls adm_main)
    protected function message(string $msg, ...): void   // ← status/error message
    protected function loadScript(string $script): void
    protected function execScript(string $script): void

    // Response
    protected function sendJSON(array $data): never
    protected function redirectTo(string $page): never

    // Auth
    protected function checkAccess(string $rightKey): void
    protected function requireAdminLevel(): void          // ← AUTH_ADM only
}
```

### Migration Pattern

**Before:**
```php
if (!allowedTo(...)) throw new Exception('Permission error!');
function ShowXxxPage(): void {
    global $LNG;
    $t = new template();
    $t->assign_vars([...]);
    $t->show('Xxx.twig');
}
```

**After:**
```php
// @admin-migrated (Phase N — AbstractAdminPage)
class ShowXxxPage extends AbstractAdminPage {
    public function __construct() {
        parent::__construct('ShowXxxPage');
    }
    protected function run(): void {
        global $LNG;
        $this->assign([...]);
        $this->show('Xxx.twig');
    }
}
```

**Routing in admin.php:** change `ShowXxxPage()` → `new ShowXxxPage()`.

See `docs/ADMIN_PAGE_MIGRATION.md` for the full migration plan and risk classification.

---

## 6. Plugin & Extension System

> **Full reference:** [`docs/PLUGIN_ARCHITECTURE.md`](PLUGIN_ARCHITECTURE.md) — hook catalogue, manifest schema, module system, safe-mode rules, quick-start checklist.

Version: **1.2 – Dynamic Element Registry + v2 Module Engine**

### Extension layers (summary)

| Layer | Class | Purpose |
|-------|-------|---------|
| Twig hook spots | `HookManager::renderHook()` | Inject HTML into page layouts at named slots |
| PHP action hooks | `HookManager::doAction()` | React to lifecycle events |
| PHP filter hooks | `HookManager::applyFilters()` | Transform data arrays (pricelist, buildTime, etc.) |
| Game Modules (v2) | `ModuleManager` + `GameModuleInterface` | Per-request lifecycle: `boot` / `beforeRequest` / `afterRequest` |
| Element Registry | `ElementRegistry` | Register new buildings, ships, techs, defenses |
| Plugin routing | `PluginManager` | New game/admin pages, cronjobs, Twig namespaces, config, lang |

### Plugin structure

```
plugins/{id}/
├── manifest.json        # required: id, name, version; optional: modules[], type
├── plugin.php           # bootstrap: registers hooks/routes/cronjobs (runs every request)
├── install.sql          # optional — runs on install
├── uninstall.sql        # optional — runs on uninstall
├── lang/en.json         # ISO-639-1 language files
├── assets/              # CSS, JS, images (web-accessible)
├── views/               # Twig templates (registered as @{id}/...)
├── game/                # game page controllers
├── admin/               # admin page controllers (function or AbstractAdminPage)
├── cron/                # cronjob classes
└── modules/             # GameModuleInterface implementations
```

### Key APIs

```php
$pm = PluginManager::get();
$hm = HookManager::get();

// Routes
$pm->registerPageRoute($pluginId, $pageName, $file, $classOrFn);
$pm->registerAdminRoute($pluginId, $pageName, $file, $classOrFn); // class or function
$pm->registerCronjob($pluginId, $className, $file, $schedule);
$pm->registerTwigNamespace($pluginId, 'views');

// Config
$pm->getConfig($pluginId, $key, $default);
$pm->setConfig($pluginId, $key, $value);

// Hooks
$hm->addAction($hook, $callback, $priority = 10);
$hm->addFilter($hook, $callback, $priority = 10);
$hm->doAction($hook, $context);
$value = $hm->applyFilters($hook, $value, $context);
```

### Twig hook spots (summary)

Key ingame: `head_end`, `ingame.header.after`, `ingame.sidebar.top`, `ingame.sidebar.bottom`, `content_top`, `footer_end`

Key admin: `admin.header.after`, `admin.sidebar.top`, `admin.sidebar.modules`, `admin.sidebar.bottom`, `admin.content.top`, `footer_end`

Full list: [`docs/PLUGIN_ARCHITECTURE.md §7`](PLUGIN_ARCHITECTURE.md#7-twig-hook-spots-reference)

### PHP lifecycle hooks

| Hook | Fired from | Notes |
|------|-----------|-------|
| `beforeController` | `game.php`, `admin.php` | Before page controller runs |
| `afterController` | `game.php`, `admin.php` | After page controller; `ModuleManager::afterRequest` wired here at priority 200 |

### Game data filters

`game.resourceMap`, `game.pricelist`, `game.requirements`, `game.prodGrid`, `game.combatCaps`, `game.reslist` — all applied in `common.php` after element data is loaded.

`game.planet` — applied after `$PLANET` is loaded in INGAME mode (use to inject 0-defaults for plugin DB columns).

`game.buildTime`, `game.production` — applied in `BuildFunctions` / `ReBuildCache`.

### Admin route handler styles (Phase 9+)

`dispatchAdminRoute()` supports two styles, detected automatically:

1. **`AbstractAdminPage` subclass** (preferred): class is instantiated via `new $class()`.
2. **Plain function** (legacy, backward-compatible): function is called directly.

### Safe Mode

If a plugin crashes during bootstrap, it is auto-deactivated via `safeDeactivate()`. If DB deactivation fails, `cache/safe_mode.lock` is written and all plugins are skipped until an admin clears it via Plugin Admin.

---

## 7. Exception Handling Policy

**Rule:** Never silently swallow exceptions in production code.

| Situation | Action |
|-----------|--------|
| Optional table may not exist | Catch, `error_log()`, return sentinel `-1` or `[]` |
| Unexpected DB error | Catch, `error_log()`, rethrow |
| User-facing validation | Catch, return structured error array (no rethrow) |
| Unknown/unexpected error | Do NOT catch — let global handler deal with it |

Global handler is configured in `common.php` / `admin.php` via `set_exception_handler()`.

---

## 8. Template System

**Engine:** Twig (Smarty fully removed as of v2.0)

- Game templates: `styles/templates/game/`
- Admin templates: `styles/templates/adm/`
- Layout base:
  - Game: `layout.full.twig`, `layout.popup.twig`, `layout.ajax.twig`
  - Admin: `layout.admin.twig`
- LNG keys accessed as `{{ LNG.key_name }}` in all templates

---

## 9. Coding Standards

See `CODING_STYLE.md` and `NAMING_CONVENTIONS.md`.

---

## 10. Service Layer

See `docs/SERVICE_ARCHITECTURE.md` for the full pattern, rules, and file-level classification.

### Service / Repository classes introduced in Phase 7

| Class | File | Responsibility |
|-------|------|----------------|
| `CacheService` | `includes/pages/adm/CacheService.php` | Cache directory management, safe deletion |
| `BanService` | `includes/pages/adm/BanService.php` | Ban/unban logic, `%%BANNED%%` + `%%USERS%%` writes |
| `NewsRepository` | `includes/pages/adm/NewsRepository.php` | CRUD on `%%NEWS%%` table |

### Existing service classes (reference)

| Class | File | Responsibility |
|-------|------|----------------|
| `AdminStatsService` | `includes/pages/adm/AdminStatsService.php` | Dashboard KPIs, chart data |
| `PlayerUtil` | `includes/classes/PlayerUtil.class.php` | Player creation, message sending, password hashing |
| `Cronjob` | `includes/classes/Cronjob.class.php` | Cronjob execution, lock management, recalculation |

### Layer rule summary

```
Page / Controller  → reads request, calls service, assigns template vars
Service / Repository → implements logic, delegates DB access to Database::get()
Database::get()    → PDO wrapper — all SQL executed here
Template (Twig)    → presentation only
```

---

## 11. Key Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `ROOT_PATH` | `/absolute/path/` | Filesystem root, always with trailing slash |
| `TIMESTAMP` | `time()` | Unix timestamp at request start |
| `AJAX_REQUEST` | `HTTP::_GP('ajax', 0)` | Truthy if `?ajax=1` in request |
| `MODE` | `'INGAME'`/`'ADMIN'`/`'INSTALL'` | Current execution context |
| `AUTH_USR` / `AUTH_ADM` | int constants | Authorization levels |
