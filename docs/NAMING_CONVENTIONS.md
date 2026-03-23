# 2MoonsCE – Naming Conventions

> **Scope:** All new and touched PHP, JS, and Twig code going forward.  
> Do NOT rename existing database columns or legacy variables in untouched code.

---

## 1. PHP

### Classes → PascalCase

```php
class AdminStatsService { }
class ShowOverviewPage { }
class AbstractAdminPage { }
class DatabaseMigrationHelper { }
```

### Interfaces → PascalCase + descriptive suffix

```php
interface GameModuleInterface { }
interface CronjobTaskInterface { }
```

### Methods & Functions → camelCase

```php
public function getPlayersOnline(): array { }
public function formatChartData(array $rows): array { }
function getPeriodTimestamp(string $period): int { }
```

### Variables → camelCase

```php
$playerCount = 0;
$onlineThreshold = TIMESTAMP - 900;
$flaggedUsers = 0;
```

### Constants → UPPER_CASE

```php
define('ROOT_PATH', '...');
define('TIMESTAMP', time());
const MAX_FLEET_SLOTS = 10;
```

### Properties → camelCase

```php
private int $universe;
private static ?AdminStatsService $instance = null;
protected ?PDO $dbHandle = null;
```

---

## 2. Database Columns

**Do NOT rename existing columns.** They follow legacy 2Moons conventions and renaming them requires schema migrations that are out of scope.

Examples of existing conventions to preserve:
- `fleet_start_time`, `fleet_universe` (prefixed with table short name)
- `b_building`, `b_hangar_id` (b_ = build queue)
- `id_luna`, `id_planet`
- `ally_universe`, `ally_register_time`

---

## 3. JavaScript

### Variables & Functions → camelCase

```js
let playerCount = 0;
function ovSaveRename() { }
function checkrename() { }  // legacy – do not rename existing functions
```

### Classes → PascalCase

```js
class FleetTracker { }
class DialogManager { }
```

### Constants → UPPER_CASE or camelCase (module-scoped)

```js
const MAX_RETRIES = 3;
const apiBaseUrl = 'game.php';
```

### Event handler functions → on + PascalCase or camelCase

```js
function onPlanetClick() { }
function handleFormSubmit() { }
```

---

## 4. Twig Templates

### Variables → camelCase (passed from PHP)

```twig
{{ planetname }}
{{ buildInfo.buildings.level }}
{{ AllPlanets }}
```

> Note: Some legacy template variables use PascalCase (`AllPlanets`, `Moon`). Preserve these in existing templates; use camelCase in new ones.

### Blocks → snake_case

```twig
{% block content %}
{% block page_scripts %}
{% block nav_top %}
```

---

## 5. Files & Directories

| Type | Convention | Example |
|------|-----------|---------|
| PHP game page class | `ShowXxxPage.class.php` | `ShowOverviewPage.class.php` |
| PHP admin page class | `ShowXxxPage.php` | `ShowStatsPage.php` |
| PHP service class | `XxxService.php` | `AdminStatsService.php` |
| PHP abstract base | `AbstractXxx.php` | `AbstractAdminPage.php` |
| Twig game template | `page.section.variant.twig` | `page.overview.default.twig` |
| Twig admin template | `PascalCase.twig` | `SystemDebugPage.twig` |
| JS game script | `section.feature.js` | `overview.actions.js` |
| CSS | `section.component.css` | `smartmoons.navigation.css` |
| Docs | `UPPER_CASE.md` | `CODING_STYLE.md` |

---

## 6. LNG Keys (Language / i18n)

All language keys use **snake_case** with a **prefix** indicating the section:

| Prefix | Section |
|--------|---------|
| `ov_` | Overview page |
| `lm_` | Left menu / navigation |
| `bot_` | Bot system |
| `adm_` | Admin generic |
| `sm_` | Send messages |
| `plg_` | Plugin admin |
| `fcm_` | Fleet/colony/moon |

```php
$LNG['ov_planet_rename']  = 'Planet umbenennen';
$LNG['bot_delete_confirm'] = 'Account #ID# wirklich löschen?';
$LNG['adm_select_class']   = '-- Klasse wählen --';
```

---

## 7. Admin Page Classes

Admin page controllers follow a `ShowXxxPage` naming convention — both legacy and migrated.

| Type | Convention | Example |
|------|-----------|---------|
| Admin page class | `ShowXxxPage` (PascalCase) | `ShowStatsPage`, `ShowPluginAdminPage` |
| Game page class | `ShowXxxPage` (PascalCase) | `ShowOverviewPage`, `ShowGalaxyMapPage` |
| Abstract admin base | `AbstractAdminPage` | `includes/pages/adm/AbstractAdminPage.php` |
| Abstract game base | `AbstractGamePage` | `includes/pages/game/AbstractGamePage.class.php` |

**Migrated admin pages** (extending `AbstractAdminPage`) are tagged with a marker comment:

```php
// @admin-migrated (Phase 4 — AbstractAdminPage)
class ShowPassEncripterPage extends AbstractAdminPage { ... }
```

**Legacy admin pages** (plain PHP functions) are tagged when identified for future migration:

```php
// TODO: @admin-migrate — still plain PHP function.
// Migrate to AbstractAdminPage when DB migration is also complete.
// See docs/ADMIN_PAGE_MIGRATION.md §6.
function ShowConfigBasicPage(): void { ... }
```

---

## 8. Plugin Conventions

Plugins live in `plugins/{PluginId}/` where `{PluginId}` matches the `id` field in `manifest.json` **exactly** (case-sensitive).

| Item | Convention | Example |
|------|-----------|---------|
| Plugin folder | PascalCase matching manifest `id` | `plugins/GalacticEvents/` |
| Plugin manifest `id` | PascalCase | `"id": "GalacticEvents"` |
| Plugin bootstrap | `plugin.php` | `plugins/GalacticEvents/plugin.php` |
| Plugin admin controller file | `XxxAdminController.php` or `ShowXxxAdminPage.php` | `LiveFleetAdminController.php` |
| Plugin admin handler function | `ShowXxxAdminPage` (matches `registerAdminRoute` 4th param) | `ShowLiveFleetAdminPage` |
| Plugin page handler function | `ShowXxxPage` (matches `registerPageRoute` 4th param) | `ShowGalacticEventsPage` |
| Plugin module class | PascalCase + `Module` suffix | `GalacticEventsModule` |
| Plugin cronjob class | PascalCase + `Cronjob` suffix | `GalacticEventsCronjob` |

> **Critical:** The function name in the 4th parameter of `registerAdminRoute()` / `registerPageRoute()`
> must **exactly** match the function defined in the file. A mismatch causes `function_exists()` to
> return `false`, which falls through to `ShowIndexPage()` and triggers DB errors.

---

## 9. Migration Markers

Use standard marker comments to track migration state across files:

| Marker | Meaning |
|--------|---------|
| `// @admin-migrated (Phase N — AbstractAdminPage)` | Page migrated to class-based pattern |
| `// @admin-migrated (DB: PDO via Database::get())` | mysqli replaced with PDO |
| `// TODO: @admin-migrate` | Identified for future admin architecture migration |
| `// TODO: @db-migrate` | Identified for future DB/PDO migration |

These markers make it trivial to audit migration progress:

```bash
grep -rn "@admin-migrated" includes/pages/adm/
grep -rn "TODO: @admin-migrate" includes/pages/adm/
grep -rn "TODO: @db-migrate" includes/pages/adm/
```

---

## 10. Summary Table

| Identifier type | Convention | Example |
|----------------|-----------|---------|
| PHP class | PascalCase | `AdminStatsService` |
| PHP interface | PascalCase | `GameModuleInterface` |
| PHP method | camelCase | `getPlayersOnline()` |
| PHP variable | camelCase | `$flaggedUsers` |
| PHP constant | UPPER_CASE | `ROOT_PATH` |
| Admin/game page class | `ShowXxxPage` PascalCase | `ShowStatsPage` |
| Plugin id (manifest + folder) | PascalCase | `GalacticEvents` |
| Plugin admin handler function | `ShowXxxAdminPage` | `ShowLiveFleetAdminPage` |
| JS function | camelCase | `ovSaveRename()` |
| JS class | PascalCase | `FleetTracker` |
| JS constant | UPPER_CASE | `MAX_RETRIES` |
| Twig block | snake_case | `page_scripts` |
| LNG key | snake_case + prefix | `ov_planet_rename` |
| DB column | keep legacy | `fleet_start_time` |

---

## 11. Legacy vs. New Standard

Legacy code in this repository may use inconsistent naming — mixed camelCase/PascalCase variables,
`$GLOBALS['DATABASE']` access, snake_case method names from the original 2Moons engine.

**Do not rename these in untouched files.** The naming conventions above apply to:
- All newly written code
- Files explicitly touched in a migration or bug-fix task
- New plugins and modules

When touching legacy code, normalize only the lines you are actually changing.
Do not submit PRs whose primary content is renaming unchanged identifiers.
