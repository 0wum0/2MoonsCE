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
  └─ common.php (bootstrap)
       └─ admin.php: resolve page → include ShowXxxPage.php
            └─ ShowXxxPage (currently plain PHP class)
                 └─ TODO: migrate to AbstractAdminPage (see §5)
```

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

### Current State

Admin pages are plain PHP classes with no shared base. Each page includes its own boilerplate for:
- Authentication checks
- Template initialization
- Variable assignment

### Target State: `AbstractAdminPage`

Location: `includes/pages/adm/AbstractAdminPage.php`

Provides:
- `$this->assign(array $vars)` — pass variables to Twig
- `$this->display(string $template)` — render admin Twig template
- `$this->initTemplate()` — lazy template init
- `$this->checkAccess(int $requiredRight)` — auth guard
- `$this->sendJSON(array $data)` — JSON response helper

Migration: Add `// TODO: extend AbstractAdminPage` to existing admin pages. Migrate one page at a time.

---

## 6. Plugin System

Version: **1.2 – Dynamic Element Registry**

```
plugins/{id}/
├── manifest.json        # id, name, version, modules[], sqlFiles[]
├── plugin.php           # Bootstrap: registerPageRoute, registerAdminRoute, hooks
├── assets/              # CSS, JS, images (served as-is)
└── modules/             # GameModuleInterface implementations
```

Key APIs:
- `PluginManager::get()->registerPageRoute($pluginId, $pageName, $file, $handler)`
- `PluginManager::get()->registerAdminRoute($pluginId, $pageName, $file, $handler)`
- `PluginManager::get()->getAssetUrl($pluginId, $relativePath)`
- `PluginManager::get()->getConfig($pluginId, $key, $default)`
- `HookManager::get()->addFilter($hook, $callback, $priority)`
- `HookManager::get()->addAction($hook, $callback, $priority)`

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

## 10. Key Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `ROOT_PATH` | `/absolute/path/` | Filesystem root, always with trailing slash |
| `TIMESTAMP` | `time()` | Unix timestamp at request start |
| `AJAX_REQUEST` | `HTTP::_GP('ajax', 0)` | Truthy if `?ajax=1` in request |
| `MODE` | `'INGAME'`/`'ADMIN'`/`'INSTALL'` | Current execution context |
| `AUTH_USR` / `AUTH_ADM` | int constants | Authorization levels |
