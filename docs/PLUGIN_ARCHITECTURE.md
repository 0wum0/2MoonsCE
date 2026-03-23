# 2MoonsCE – Plugin & Extension Architecture

> Based on direct inspection of the real codebase as of Phase 9 (Mar 2026).
> This document is the authoritative reference for plugin authors, modders,
> and core contributors who want to extend 2MoonsCE without patching core files.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Extension Layers](#2-extension-layers)
3. [Plugin Structure](#3-plugin-structure)
4. [manifest.json Reference](#4-manifestjson-reference)
5. [Bootstrap (plugin.php)](#5-bootstrap-pluginphp)
6. [Hook System (HookManager)](#6-hook-system-hookmanager)
7. [Twig Hook Spots Reference](#7-twig-hook-spots-reference)
8. [PHP Lifecycle Hooks Reference](#8-php-lifecycle-hooks-reference)
9. [Game Data Filters Reference](#9-game-data-filters-reference)
10. [Routing: Game Pages & Admin Pages](#10-routing-game-pages--admin-pages)
11. [Cronjobs](#11-cronjobs)
12. [Element Registry (Buildings / Ships / Techs)](#12-element-registry-buildings--ships--techs)
13. [Module System (v2)](#13-module-system-v2)
14. [Plugin Config](#14-plugin-config)
15. [Assets & Twig Namespaces](#15-assets--twig-namespaces)
16. [Language Files](#16-language-files)
17. [Safe Mode & Crash Handling](#17-safe-mode--crash-handling)
18. [Hard-coded Areas (Deferred Extension Points)](#18-hard-coded-areas-deferred-extension-points)
19. [Quick-Start Checklist](#19-quick-start-checklist)

---

## 1. Overview

2MoonsCE uses a layered extension model:

```
┌─────────────────────────────────────────────────┐
│  Twig templates                                  │
│   hook('spot_name') → HookManager::renderHook()  │
├─────────────────────────────────────────────────┤
│  PHP action hooks                                │
│   HookManager::doAction('event', $ctx)           │
├─────────────────────────────────────────────────┤
│  PHP filter hooks                                │
│   HookManager::applyFilters('filter', $value)    │
├─────────────────────────────────────────────────┤
│  Game Module lifecycle (v2)                      │
│   GameModuleInterface: boot/beforeRequest/after  │
├─────────────────────────────────────────────────┤
│  Element Registry                                │
│   ElementRegistry::register() → new buildings   │
├─────────────────────────────────────────────────┤
│  Plugin routing / cronjobs / assets / lang       │
│   PluginManager: registerPageRoute() etc.        │
└─────────────────────────────────────────────────┘
```

All extension is done from a plugin's `plugin.php` bootstrap — no core files need to be edited.

---

## 2. Extension Layers

| Layer | Class | When available | Use for |
|-------|-------|---------------|---------|
| Twig hook spots | `HookManager::renderHook()` | Template render time | Injecting HTML into page layouts |
| PHP action hooks | `HookManager::doAction()` | Various PHP points | Reacting to lifecycle events |
| PHP filter hooks | `HookManager::applyFilters()` | Various PHP points | Modifying data (game arrays, build times) |
| Game Modules (v2) | `ModuleManager` + `GameModuleInterface` | After USER/PLANET loaded | Complex per-request game logic |
| Element Registry | `ElementRegistry` | INGAME/ADMIN bootstrap | New buildings, ships, techs, defenses |
| Plugin routing | `PluginManager::registerPageRoute()` | After bootstrap | New game pages and admin pages |
| Cronjobs | `PluginManager::registerCronjob()` | After bootstrap | Scheduled background tasks |

---

## 3. Plugin Structure

```
plugins/
└── MyPlugin/
    ├── manifest.json          ← required
    ├── plugin.php             ← bootstrap (runs on every request when active)
    ├── install.sql            ← optional: runs on install
    ├── uninstall.sql          ← optional: runs on uninstall
    ├── lang/
    │   ├── en.json
    │   └── de.json
    ├── assets/
    │   ├── css/
    │   └── js/
    ├── views/                 ← Twig templates (registered as @MyPlugin/...)
    ├── game/                  ← game page controllers
    ├── admin/                 ← admin page controllers
    ├── cron/                  ← cronjob classes
    ├── lib/                   ← shared helpers / DB classes
    └── modules/               ← GameModuleInterface implementations
```

**Rules:**
- The folder name under `plugins/` must match the `id` field in `manifest.json` exactly (case-sensitive).
- `plugin.php` is `require_once`-d on every page request when the plugin is active. Keep it fast.
- All DB access in `plugin.php` must be non-fatal — wrap in `try/catch`.

---

## 4. manifest.json Reference

```json
{
    "id":          "MyPlugin",
    "name":        "My Plugin",
    "version":     "1.0.0",
    "description": "Short description shown in Plugin Admin.",
    "author":      "Your Name",
    "type":        "game",
    "modules":     ["modules/MyModule.php"]
}
```

| Field | Required | Type | Notes |
|-------|----------|------|-------|
| `id` | ✅ | string | `[a-zA-Z0-9\-_]+`. Must match folder name exactly. |
| `name` | ✅ | string | Display name shown in Plugin Admin. |
| `version` | ✅ | string | Must start with `MAJOR.MINOR` (e.g. `1.0`, `1.0.0`). |
| `description` | — | string | Short description. Shown in Plugin Admin. |
| `author` | — | string | Author name or URL. |
| `type` | — | string | `game` (default) or `admin`. Informational only. |
| `modules` | — | string[] | Relative paths to `GameModuleInterface` files (v2 module system). |

---

## 5. Bootstrap (plugin.php)

`plugin.php` runs once per page request when the plugin is active. It should:
- Register hooks, routes, cronjobs, assets
- Be idempotent (safe to run on every request)
- **Never** render output
- **Never** throw uncaught exceptions (wrap DB calls in `try/catch`)

```php
<?php
declare(strict_types=1);

$pm = PluginManager::get();
$hm = HookManager::get();

// Register routes, hooks, assets...
$pm->registerPageRoute('MyPlugin', 'my_page', 'game/MyPage.php', 'MyPageController');
$pm->registerAdminRoute('MyPlugin', 'plugin_my_admin', 'admin/MyAdminPage.php', 'ShowMyAdminPage');
$pm->registerCronjob('MyPlugin', 'MyPluginCronjob', 'cron/MyPluginCronjob.php');
$pm->registerTwigNamespace('MyPlugin', 'views');

// Inject CSS/JS
$hm->addAction('head_end', static function (): string {
    return '<link rel="stylesheet" href="plugins/MyPlugin/assets/css/my.css">' . "\n";
});
$hm->addAction('footer_end', static function (): string {
    return '<script src="plugins/MyPlugin/assets/js/my.js" defer></script>' . "\n";
});
```

---

## 6. Hook System (HookManager)

### Actions (event notifications)

```php
// Register a handler (in plugin.php)
HookManager::get()->addAction('hook.name', function (array $ctx): void {
    // react to event
}, $priority = 10);

// Fire an action (in core code)
HookManager::get()->doAction('hook.name', ['key' => $value]);
```

### Filters (data transformation)

```php
// Register a filter (in plugin.php)
HookManager::get()->addFilter('filter.name', function (mixed $value, array $ctx): mixed {
    // transform and return $value
    return $value;
}, $priority = 10);

// Apply a filter (in core code)
$value = HookManager::get()->applyFilters('filter.name', $originalValue, $context);
```

### Twig `hook()` function

All Twig templates can use the `hook()` function to render registered action callbacks:

```twig
{{ hook('spot_name') }}
{{ hook('spot_name', { key: value }) }}
```

Callbacks return HTML strings; output is concatenated in priority order.

### Priority

- Lower number = runs first (default: 10)
- Core modules register at priority 10
- Plugin modules register at priority 100
- Use priority to control ordering relative to other plugins

---

## 7. Twig Hook Spots Reference

These spots exist in core templates and are available to all plugins.

### Game (INGAME) spots

| Spot name | Template file | Position |
|-----------|--------------|----------|
| `head_end` | `main.header.twig` | Just before `</head>` |
| `ingame.header.before` | `main.header.twig` | Before `<header>` tag |
| `ingame.header.after` | `main.header.twig` | After `<body>` opens |
| `ingame.navHeader.before` | `main.navigation_header.twig` | Before the nav header block |
| `ingame.navHeader.after` | `main.navigation_header.twig` | After the nav header block |
| `ingame.sidebar.top` | `main.navigation.twig` | Top of sidebar nav items |
| `ingame.sidebar.bottom` | `main.navigation.twig` | Bottom of sidebar nav items |
| `sidebar_end` | `main.navigation.twig` | Inside sidebar, after nav |
| `content_top` | `layout.normal.twig`, `layout.modern.twig`, `page.overview.default.twig` | Top of page content area |
| `overview.after_planets` | `page.overview.default.twig` | After planet list on overview |
| `ingame.footer.beforeScripts` | `main.footer.twig` | Before `<script>` tags in footer |
| `footer_end` | `main.footer.twig` | Just before `</body>` |
| `ingame.footer.afterScripts` | `main.footer.twig` | After all scripts, before `</body>` |

### Admin (ADMIN) spots

| Spot name | Template file | Position |
|-----------|--------------|----------|
| `head_end` | `overall_header.twig` | Just before `</head>` (shared name with ingame) |
| `admin.header.before` | `overall_header.twig` | Before `</head>` |
| `admin.header.after` | `overall_header.twig` | After `<body>` opens |
| `admin.sidebar.top` | `overall_header.twig` | Top of admin sidebar nav |
| `admin.sidebar.modules` | `overall_header.twig` | After the modules section in sidebar |
| `admin.sidebar.bottom` | `overall_header.twig` | Bottom of admin sidebar (before footer) |
| `admin.content.top` | `overall_header.twig` | Top of main content area |
| `admin.footer.beforeScripts` | `overall_footer.twig` | Before `<script>` tags in footer |
| `footer_end` | `overall_footer.twig` | Just before `</body>` (shared name) |
| `admin.footer.afterScripts` | `overall_footer.twig` | After all scripts |

---

## 8. PHP Lifecycle Hooks Reference

These are `doAction` hooks fired from PHP entry points.

| Hook name | Fired from | Context keys | Notes |
|-----------|-----------|-------------|-------|
| `beforeController` | `game.php`, `admin.php` | `page`, `mode`, `pageObj` (game only), `context` (admin only) | Runs before the page controller |
| `afterController` | `game.php`, `admin.php` | same as above | Runs after the page controller. `ModuleManager::afterRequest()` is wired here at priority 200. |

### Example: Add logic before a specific page

```php
HookManager::get()->addAction('beforeController', function (array $ctx): void {
    if (($ctx['page'] ?? '') === 'overview') {
        // runs before ShowOverviewPage
    }
});
```

---

## 9. Game Data Filters Reference

These filters are applied in `common.php` after element data is loaded. They allow plugins to modify game-wide arrays before they are used by any page.

| Filter name | Value type | When applied |
|-------------|-----------|-------------|
| `game.resourceMap` | `array<int, string>` | After element registry export (INGAME/ADMIN) |
| `game.pricelist` | `array<int, array>` | After element registry export |
| `game.requirements` | `array<int, array>` | After element registry export |
| `game.prodGrid` | `array` | After element registry export |
| `game.combatCaps` | `array` | After element registry export |
| `game.reslist` | `array` | After element registry export |
| `game.planet` | `array` | After PLANET loaded in INGAME mode — use to inject default values for plugin columns |
| `game.buildTime` | `int\|float` | In `BuildFunctions::getBuildingTime()` — allows modifying build time |
| `game.production` | `array` | In `ReBuildCache()` — allows modifying planet production values |

### Example: Add 0-default values for a plugin DB column

```php
HookManager::get()->addFilter('game.planet', function (array $planet, array $ctx): array {
    $planet['my_plugin_column'] ??= 0;
    return $planet;
});
```

---

## 10. Routing: Game Pages & Admin Pages

### Game page route

```php
// plugin.php
PluginManager::get()->registerPageRoute(
    'MyPlugin',           // plugin id (must match manifest)
    'my_page',            // ?page=my_page in URL
    'game/MyPage.php',    // relative to plugin dir
    'MyPageController'    // class name — must extend AbstractGamePage or be callable
);
```

`game.php` checks plugin routes before the core page lookup. The class must have a `show()` method (or `$defaultController` property). It is instantiated and the mode method called.

### Admin page route — plain function (legacy)

```php
// plugin.php
PluginManager::get()->registerAdminRoute(
    'MyPlugin',
    'plugin_my_admin',            // ?page=plugin_my_admin in URL
    'admin/MyAdminPage.php',      // relative to plugin dir
    'ShowMyAdminPage'             // function name — MUST be defined in that file
);

// admin/MyAdminPage.php
function ShowMyAdminPage(): void
{
    $template = new template();
    $template->assign_vars([...]);
    $template->show('MyAdminPage.twig');
}
```

### Admin page route — AbstractAdminPage class (Phase 9+)

As of Phase 9, `dispatchAdminRoute()` also supports `AbstractAdminPage` subclasses.
Pass the class name as the 4th parameter:

```php
// plugin.php
PluginManager::get()->registerAdminRoute(
    'MyPlugin',
    'plugin_my_admin',
    'admin/ShowMyAdminPage.php',
    'ShowMyAdminPage'             // class name (extends AbstractAdminPage)
);

// admin/ShowMyAdminPage.php
class ShowMyAdminPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowMyAdminPage');
    }

    protected function run(): void
    {
        $this->assign([...]);
        $this->show('@MyPlugin/admin/my_page.twig');
    }
}
```

**Detection:** `dispatchAdminRoute()` checks `class_exists()` first; if the class exists and extends `AbstractAdminPage`, it instantiates it. If the class does not exist, it falls back to `function_exists()` for backward compatibility.

---

## 11. Cronjobs

```php
// plugin.php
PluginManager::get()->registerCronjob(
    'MyPlugin',
    'MyPluginCronjob',          // class name
    'cron/MyPluginCronjob.php', // relative to plugin dir
    [                           // optional schedule (crontab syntax)
        'min'   => '*/5',
        'hours' => '*',
        'dom'   => '*',
        'month' => '*',
        'dow'   => '*',
        'name'  => 'My Plugin Tick',
    ]
);
```

The class must implement the `Cronjob` interface (or be callable as `->execute()`).
`registerCronjob()` is idempotent — it inserts the DB row if missing, or re-enables it if deactivated.

---

## 12. Element Registry (Buildings / Ships / Techs)

The `ElementRegistry` allows plugins to register new game elements (buildings,
ships, techs, defenses, resources) that are automatically reflected in the legacy
`$resource`, `$pricelist`, `$reslist`, and `$requeriments` arrays.

```php
// plugin.php
PluginManager::get()->registerElementsCallback('MyPlugin', function (ElementRegistry $r): void {
    $r->register([
        'id'           => 900,               // unique element ID (avoid conflicts: 900+)
        'nameKey'      => 'my_column',       // DB column name in %%PLANETS%%
        'type'         => 'build',           // build | tech | fleet | defense | resource
        'cost'         => [
            'metal'    => 50000,
            'crystal'  => 25000,
            'deuterium'=> 10000,
            'factor'   => 1.5,
        ],
        'requirements' => [1 => 10, 120 => 5], // [elementId => minLevel]
        'max'          => 0,                 // 0 = unlimited
        'combat'       => [],                // combat stats for ships/defense
    ]);
});
```

After export, `$resource[900]` = `'my_column'`, `$pricelist[900]` = cost array, etc.
All existing game logic (build queue, requirements check, combat) will work with the new element automatically, **provided the DB column exists**.

---

## 13. Module System (v2)

For complex per-request logic, use `GameModuleInterface`. Declare modules in `manifest.json`:

```json
{
    "id": "MyPlugin",
    "modules": ["modules/MyModule.php"]
}
```

```php
// modules/MyModule.php
class MyModule implements GameModuleInterface
{
    public function getId(): string        { return 'MyPlugin.my_module'; }
    public function isEnabled(): bool      { return true; }
    public function boot(GameContext $ctx): void         { /* one-time setup */ }
    public function beforeRequest(GameContext $ctx): void { /* per-request pre-processing */ }
    public function afterRequest(GameContext $ctx): void  { /* per-request post-processing */ }
}
```

`GameContext` provides:
- `$ctx->user` — current `$USER` array
- `$ctx->planet` — current `$PLANET` array (INGAME only)
- `$ctx->mode` — `INGAME` | `ADMIN` | etc.
- `$ctx->time` — request timestamp
- `$ctx->isAjax` — whether this is an AJAX request
- `$ctx->get(key)` / `$ctx->set(key, value)` / `$ctx->has(key)` — shared bag

Module priority: plugin modules are registered at priority 100 (after core modules at 10).

If a module throws during `boot()`, `beforeRequest()`, or `afterRequest()`, the owning plugin is auto-deactivated (`safeDeactivate()`). Core modules (priority ≤ 10) are never auto-deactivated.

---

## 14. Plugin Config

Plugins can store and retrieve configuration via `PluginManager`:

```php
// Read a config value (with default)
$interval = (int) PluginManager::get()->getConfig('MyPlugin', 'tick_interval', 300);

// Write a config value
PluginManager::get()->setConfig('MyPlugin', 'tick_interval', 600);

// Read all config
$all = PluginManager::get()->getAllConfig('MyPlugin');

// Delete a key
PluginManager::get()->deleteConfig('MyPlugin', 'tick_interval');
```

Config is stored as JSON in the `%%PLUGINS%%` table's `config_json` column.
It can be read/written from the Plugin Admin UI if you expose a settings page via `registerAdminRoute()`.

---

## 15. Assets & Twig Namespaces

### Register a Twig namespace

```php
// plugin.php
PluginManager::get()->registerTwigNamespace('MyPlugin', 'views');

// Templates referenced as: @MyPlugin/game/my_page.twig
// Resolved to: plugins/MyPlugin/views/game/my_page.twig
```

### Asset URL helper

```php
$url = PluginManager::get()->getAssetUrl('MyPlugin', 'img/icon.png');
// Returns: './plugins/MyPlugin/assets/img/icon.png'
```

### Inject CSS/JS

```php
$hm->addAction('head_end', static function (): string {
    return '<link rel="stylesheet" href="plugins/MyPlugin/assets/css/my.css">' . "\n";
}, 20);

$hm->addAction('footer_end', static function (): string {
    return '<script src="plugins/MyPlugin/assets/js/my.js" defer></script>' . "\n";
}, 20);
```

### Register a building image (for core templates)

```php
PluginManager::get()->registerBuildingImage('MyPlugin', 'img/900.gif', '900.gif');
// Copies to every theme's gebaeude/ directory so {dpath}gebaeude/900.gif works
```

---

## 16. Language Files

Plugin language files use ISO-639-1 codes (`en`, `de`, `fr`, etc.) and are loaded automatically.

```
plugins/MyPlugin/lang/
    en.json
    de.json
```

```json
{
    "my_plugin_title": "My Plugin",
    "my_plugin_description": "This plugin does something."
}
```

Access in PHP:

```php
$title = PluginManager::lang('MyPlugin', 'my_plugin_title');
```

Access in Twig (if passed to template via assign):

```twig
{{ my_plugin_title }}
```

Language is loaded automatically at bootstrap based on `$LNG->getLanguage()` with a fallback to `en`.

---

## 17. Safe Mode & Crash Handling

If a plugin's `plugin.php` throws an uncaught exception, the plugin is automatically deactivated via `safeDeactivate()` and loading continues. No crash in a plugin can take down the entire request.

If DB deactivation itself fails, a `cache/safe_mode.lock` file is written. When the lock file exists, **all plugins are skipped** until an admin clears it via the Plugin Admin UI.

Admins can:
- Clear the safe-mode lock in Plugin Admin
- Re-activate individual plugins after fixing the crash

For module crashes: plugin modules (priority 100) auto-deactivate their owning plugin. Core modules (priority ≤ 10) are only logged.

---

## 18. Hard-coded Areas (Deferred Extension Points)

These areas currently require core edits for deep customization. They are documented as future extension work.

| Area | Current state | Future direction |
|------|---------------|-----------------|
| Battle engine (`includes/classes/class.Battle.php`) | Tightly coupled, no hooks | Hook before/after combat resolution (high-risk) |
| Fleet mission types | Hardcoded mission IDs | `MissionRegistry` for plugin-defined missions |
| Resource production formula | `ReBuildCache()` + `game.production` filter | Production formula is filterable; complex overrides need a `ProductionProvider` interface |
| Server config (speed, resource factors) | `Config::get()` singleton | Already extensible via config; admin UI for per-universe overrides deferred |
| Navigation menu (game) | Hard-coded in `main.navigation.twig` | Use `ingame.sidebar.top` / `ingame.sidebar.bottom` hooks to inject nav entries |
| Admin sidebar menu | Hard-coded in `overall_header.twig` | Use `admin.sidebar.top` / `admin.sidebar.modules` / `admin.sidebar.bottom` hooks |
| Notification types | Hard-coded in `AbstractGamePage` | Hook/filter for additional notification badge types |
| Build queue priority / slots | `class.BuildFunctions.php` | `queue.beforeProcess` / `queue.afterProcess` hooks available; slot limits deferred |
| Planet type definitions | `vars.php` | No extension point yet; deferred |
| Alliance/diplomacy | `ShowAlliancePage` | No hooks; high-risk, deferred |

---

## 19. Quick-Start Checklist

When creating a new plugin:

- [ ] Create `plugins/MyPlugin/manifest.json` — `id` matches folder name exactly
- [ ] Create `plugins/MyPlugin/plugin.php` — bootstrap that registers all hooks/routes
- [ ] `plugin.php` never throws — wrap all DB calls in `try/catch`
- [ ] Route handler function name (4th param of `registerAdminRoute`) matches the function/class defined in the file
- [ ] Twig namespace registered before any template that uses `@MyPlugin/...`
- [ ] Language files named `en.json`, `de.json` (ISO-639-1 codes)
- [ ] DB column names for new elements added to `%%PLANETS%%` via `install.sql`
- [ ] `install.sql` uses `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` for idempotency
- [ ] Test install → activate → deactivate → reactivate cycle
- [ ] Test with safe-mode lock cleared if a crash occurred during development
