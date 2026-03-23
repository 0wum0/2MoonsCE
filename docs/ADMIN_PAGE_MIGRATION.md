# 2MoonsCE – Admin Page Architecture & Migration Plan

> Based on direct inspection of the actual repository.
> No guesswork — every finding below is derived from the real code.

---

## 1. Current Admin Bootstrap Flow

### Entry Point: `admin.php`

```
admin.php
  └─ define('MODE', 'ADMIN')
  └─ require 'includes/common.php'      ← session, DB, $USER, universe
  └─ require 'includes/classes/class.Log.php'
  └─ auth check: $USER['authlevel'] == AUTH_USR → redirect to game.php
  └─ Session::create()->adminAccess check → ShowLoginPage() or continue
  └─ Universe::setEmulated($uni)         ← per-request universe context
  └─ PluginManager->dispatchAdminRoute() ← plugin page dispatch (exits on match)
  └─ HookManager->doAction('beforeController')
  └─ switch ($page) → include + call ShowXxxPage()  OR  new ShowXxxPage()
  └─ HookManager->doAction('afterController')
```

### Template Render Path

All admin pages create `new template()` then call `$tplObj->show('Filename.twig')`.

`template::show()` in `MODE === 'ADMIN'`:
1. Sets `$currentSubDir = 'adm/'` → resolves templates from `styles/templates/adm/`
2. Calls `adm_main()` which injects into template vars:
   - `title`, `lang`, `REV`, `VERSION`, `date`, `Offset`
   - `GET`, `currentUser`, `authlevel`, `AvailableUnis`, `UNI`, `sid`
   - `supportTicketCount`, `safeModeNotices`, `safeModeLocked`
   - `scripts`, `execscript`, `pluginCss`, `pluginJs`, `LNG`

This means **all layout vars are injected automatically** by `template::show()` —
individual page files only need to call `show()` (not `display()`).

`template::display()` skips `adm_main()` and renders raw — only for non-full-page
responses (AJAX, messages from within layouts already rendered).

`template::message()` renders `error_message_body.twig` with a status message,
calling `show()` internally — so it also handles the full layout.

---

## 2. Authentication / Authorization Pattern

Two distinct guard patterns exist in the codebase:

### Pattern A: `allowedTo()` — rights-based
```php
if (!allowedTo(str_replace([dirname(__FILE__), '\\', '/', '.php'], '', __FILE__))) {
    throw new \Exception('Permission error!');
}
```
- Used by most admin pages
- Derives right key from filename (e.g. `ShowStatsPage`)
- Calls global `allowedTo()` which checks `$USER['rights']` or `AUTH_ADM` bypass

### Pattern B: `$USER['authlevel']` — level-based
```php
if ((int)$USER['authlevel'] !== AUTH_ADM) {
    HTTP::redirectTo('admin.php');
}
```
- Used by `ShowSystemDebugPage` (AUTH_ADM only)
- Also used for `ShowDumpPage`, `ShowAutoCompletePage`

**Note:** The top-level `admin.php` already guards against `AUTH_USR` and
unauthenticated access before any page file is included. The per-page guards
are a second layer for finer-grained right key checks.

---

## 3. Template Assignment / Rendering Pattern

**Legacy pattern (all existing plain PHP pages):**
```php
function ShowXxxPage(): void
{
    global $LNG, $USER;
    $template = new template();
    $template->assign_vars([
        'key' => $value,
        // ...
    ]);
    $template->show('XxxPage.twig');
}
```

**Class-based pattern (ShowSupportPage — pre-existing OOP page):**
```php
class ShowSupportPage
{
    public $tplObj;
    function __construct() {
        $this->tplObj = new template();
        $ACTION = HTTP::_GP('mode', 'show');
        if (method_exists($this, $ACTION)) {
            $this->{$ACTION}();
        } else {
            $this->show();
        }
    }
    // ... methods call $this->tplObj->assign_vars() and $this->tplObj->show()
}
```

**Target pattern (AbstractAdminPage subclasses):**
```php
class ShowXxxPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowXxxPage');  // registers right key
        $this->run();
    }

    protected function run(): void
    {
        // ... logic
        $this->assign(['key' => $value]);
        $this->show('XxxPage.twig');
    }
}
```

---

## 4. Duplicated Bootstrap Logic (Per-Page)

Every plain PHP admin page currently repeats:

| Pattern | Approx. files |
|---------|--------------|
| `new template()` init | 38/46 pages |
| `$template->assign_vars([...])` | 38/46 pages |
| `$template->show(...)` | 38/46 pages |
| `allowedTo()` guard (top-level) | ~32 pages |
| `global $LNG, $USER` | ~35 pages |
| `$config = Config::get(Universe::getEmulated())` | ~15 pages |

Common boilerplate per page: 5–10 lines.
`AbstractAdminPage` centralises: template init, assign, show, redirect, JSON, access check.

---

## 5. Admin Page Inventory

### Architecture Types Found

| Type | Count | Description |
|------|-------|-------------|
| Plain PHP function | ~43 | `function ShowXxxPage(): void { ... }` |
| Class (OOP, not extending base) | 1 | `ShowSupportPage` — pre-existing class |
| Class (extending AbstractAdminPage) | 0 | Target state |

### Pages Already Using `Database::get()` (PDO)

See `DATABASE_MIGRATION.md` for full DB audit.
Short list: `AdminStatsService`, `ShowBanPage`, `ShowFlyingFleetPage`, `ShowMenuPage`,
`ShowMultiIPPage`, `ShowActivePage`, `ShowNewsPage`, `ShowSendMessagesPage`,
`ShowConfigUniPage`, `ShowOverviewPage`, `ShowSupportPage`

---

## 6. Page Risk Classification

### Low Risk (Phase 4 — this phase) ✅

Simple, no-DB or Config-only, single-function, short:

| File | Route | Complexity | DB | Migrated |
|------|-------|-----------|-----|---------|
| `ShowPassEncripterPage.php` | `?page=password` | Tiny — utility form | None | ✅ Phase 4 |
| `ShowDisclamerPage.php` | `?page=disclamer` | Small — Config CRUD | None | ✅ Phase 4 |
| `ShowStatUpdatePage.php` | `?page=statsupdate` | Small — action + message | None | ✅ Phase 4 |
| `ShowTopnavPage.php` | `?page=topnav` | Small — read-only display | None | ✅ Phase 4 |
| `ShowModulePage.php` | `?page=module` | Small — Config toggle | None | ✅ Phase 4 |

### Medium Risk (Phase 5 — next)

Simple to moderate logic, some DB queries, PDO-migrated or easily migrated:

| File | Route | Why medium |
|------|-------|-----------|
| `ShowMenuPage.php` | `?page=menu` | PDO migrated, short, good candidate |
| `ShowClearCachePage.php` | `?page=clearcache` | File system ops, helper function deps |
| `ShowStatsPage.php` | `?page=statsconf` | Config-only, style pass done |
| `ShowConfigBasicPage.php` | `?page=config` | Large config form but no DB |
| `ShowConfigUniPage.php` | `?page=configuni` | Large config form, PDO migrated |
| `ShowSystemDebugPage.php` | `?page=systemDebug` | Complex logic but already clean |
| `ShowPluginAdminPage.php` | `?page=pluginAdmin` | Complex but already clean/modern |
| `ShowMultiIPPage.php` | `?page=multiips` | PDO migrated, moderate logic |
| `ShowActivePage.php` | `?page=active` | PDO migrated, simple |
| `ShowNewsPage.php` | `?page=news` | PDO migrated, moderate |
| `ShowSendMessagesPage.php` | `?page=globalmessage` | PDO migrated, moderate |

### High Risk / Deferred (Phase 6+)

Complex state, many DB queries, intricate logic, high breakage risk:

| File | Route | Why deferred |
|------|-------|-------------|
| `ShowAccountEditorPage.php` | `?page=accounteditor` | 51 DB usages, massive form, high risk |
| `ShowResetPage.php` | `?page=reset` | 23 DB usages, destructive ops |
| `ShowUniversePage.php` | `?page=universe` | 20 DB usages, galaxy creation |
| `ShowLogPage.php` | `?page=log` | 13 DB usages, pagination |
| `ShowQuickEditorPage.php` | `?page=qeditor` | 13 DB usages, multi-table editor |
| `ShowAccountDataPage.php` | `?page=accountdata` | 11 DB usages, complex joins |
| `ShowSearchPage.php` | `?page=search` | Dynamic SQL, complex logic |
| `ShowRightsPage.php` | `?page=rights` | Permission management, side effects |

### Bootstrap/Auth/Utility Pages (Do Not Migrate)

| File | Reason |
|------|--------|
| `ShowLoginPage.php` | Auth entry point, called before session check |
| `ShowLogoutPage.php` | Session destruction, called directly |
| `ShowIndexPage.php` | Thin wrapper, just calls ShowOverviewPage |
| `ShowMenuPage.php` | Used as partial AJAX response, unusual call pattern |

---

## 7. `AbstractAdminPage` Design Principles

### What the base class MUST do

1. **Lazy template init** — create `template` only when first needed
2. **Use `show()`** — NOT `display()`. `show()` in `MODE=ADMIN` auto-injects
   layout vars via `adm_main()`. `display()` skips layout.
3. **Provide `assign()`** — thin wrapper over `$this->tplObj->assign_vars()`
4. **Provide `show()`** — renders with full admin layout
5. **Provide `message()`** — renders status messages via `template::message()`
6. **Provide `redirect()`** — clean `admin.php?page=X` redirect helper
7. **Provide `sendJSON()`** — for AJAX responses
8. **Provide `checkAccess()`** — calls `allowedTo()`, throws on failure
9. **Provide `run()` dispatch** — allows constructor to dispatch `mode` param
   to named methods (mirrors `ShowSupportPage` pattern)
10. **Protect `__construct()`** — accept right key, call `checkAccess()`

### What the base class must NOT do

- Must NOT inject DB dependencies (each page fetches its own `Database::get()`)
- Must NOT call `global` for `$LNG` or `$USER` (pages do this themselves)
- Must NOT duplicate `adm_main()` logic already in `template::show()`
- Must NOT add abstract methods that break legacy plain-function pages
- Must NOT change routing — `admin.php` still calls `new ShowXxxPage()`

### Migration Pattern

**Before (plain function):**
```php
if (!allowedTo(...)) throw new Exception('Permission error!');
function ShowXxxPage(): void {
    global $LNG;
    $template = new template();
    $template->assign_vars(['key' => $val]);
    $template->show('XxxPage.twig');
}
```

**After (AbstractAdminPage subclass):**
```php
// @admin-migrated (Phase 4)
class ShowXxxPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowXxxPage');
    }

    protected function run(): void
    {
        global $LNG;
        $this->assign(['key' => $val]);
        $this->show('XxxPage.twig');
    }
}
```

**Routing in admin.php stays the same:**
```php
case 'password':
    require_once 'includes/pages/adm/ShowPassEncripterPage.php';
    new ShowPassEncripterPage();   // ← was: ShowPassEncripterPage()
break;
```

---

## 8. Remaining Legacy Pages

The following pages are intentionally left as plain PHP functions.
They continue to work unchanged:

- All pages in the "High Risk / Deferred" category above
- `ShowLoginPage.php`, `ShowLogoutPage.php`, `ShowIndexPage.php`
- All pages with active `// TODO: @db-migrate` markers (see `DATABASE_MIGRATION.md`)

Add the following comment to each remaining legacy page over time:
```php
// TODO: @admin-migrate — still plain PHP function.
// Migrate to AbstractAdminPage when DB migration is also complete.
// See docs/ADMIN_PAGE_MIGRATION.md §6.
```

---

## 9. Progress Log

| Phase | Date | Pages Migrated | Notes |
|-------|------|---------------|-------|
| Phase 4 | Mar 2026 | ShowPassEncripterPage, ShowDisclamerPage, ShowStatUpdatePage, ShowTopnavPage, ShowModulePage | 5 low-risk pages |
| Phase 5 | TBD | Medium-risk pages | After DB migration Phase 3b complete |
| Phase 6 | TBD | High-risk pages | Last, after Phase 3b+5 complete |
