# 2MoonsCE – Database Migration Strategy

> **Purpose:** Document the current state of database access in the project,
> define the target standard, and provide a concrete, incremental migration plan.
> Based on direct inspection of the actual codebase — no guesswork.

---

## 1. Current Database Architecture

### Two Parallel Database Systems

The project currently runs **two database layers side-by-side**:

| Layer | Class | Backend | Location | Status |
|-------|-------|---------|----------|--------|
| PDO (primary) | `Database` | PDO + MySQL | `includes/classes/Database.class.php` | ✅ Active |
| mysqli (legacy) | `Database_BC` | mysqli | `includes/classes/Database_BC.class.php` | ⚠️ Deprecated |

`Database_BC` is instantiated as `$GLOBALS['DATABASE']` in the admin bootstrap and
accessed throughout legacy admin pages. It was marked `@deprecated` in a previous
session with a migration note pointing to this document.

---

## 2. PDO Layer (`Database` class) — Current State

### Available Methods

```php
Database::get()                         // singleton accessor
->select(string $qry, array $params)    // returns array of assoc rows
->selectSingle($qry, $params, $field)   // returns single row or field value
->selectSingleSafe(...)                 // like selectSingle, null-safe variant
->insert(string $qry, array $params)    // INSERT, returns bool
->update(string $qry, array $params)    // UPDATE, returns bool
->delete(string $qry, array $params)    // DELETE, returns bool
->replace(string $qry, array $params)   // REPLACE, returns bool
->nativeQuery(string $qry)             // raw query (no params — use only for DDL/edge cases)
->lastInsertId()                        // last INSERT id
->rowCount()                           // affected rows from last non-SELECT
->isConnected()                        // bool health check
->quote(string $str)                   // PDO::quote() wrapper
->lists($table, $column, $key)         // fetch key=>value map
```

### Table Name Resolution

The PDO layer automatically resolves `%%TABLE_NAME%%` placeholders to the actual
prefixed table names via `$this->dbTableNames`. All migrated code **must** use
`%%TABLE%%` syntax, not raw `DB_PREFIX . TABLE_CONSTANT` concatenation.

### Connection Details

- `PDO::ERRMODE_EXCEPTION` — all query errors throw `PDOException`
- `charset=utf8mb4`
- `PDO::ATTR_EMULATE_PREPARES = true` (MySQL compatibility)
- `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = true`
- `STRICT_ALL_TABLES` SQL mode

---

## 3. mysqli Layer (`Database_BC`) — Current State

`Database_BC extends mysqli` and is the legacy interface. It provides:

```php
$GLOBALS['DATABASE']->query($sql)           // throws on error
->getFirstRow($sql)                         // single assoc row
->getFirstCell($sql)                        // single scalar value
->fetch_array($result)                      // fetch next assoc row
->fetch_num($result)                        // fetch next numeric row
->fetchArray($result)                       // alias of fetch_array
->fetchquery($sql, $encode)                 // fetch all rows
->numRows($result)                          // row count from result
->affectedRows()                            // affected rows
->GetInsertID()                             // last insert id
->sql_escape($string, $flag)               // mysqli escape (NOT parameterised)
->escape($string, $flag)                   // alias
->free_result($result)                      // close cursor
->getVersion()                              // client version string
->getServerVersion()                        // server version string
->multi_query($sql)                         // multi-statement
```

**Critical note:** `sql_escape()` / `escape()` are **not** prepared statements.
They manually escape strings and concatenate them into SQL. This is the primary
SQL-injection risk surface that PDO prepared statements eliminate.

---

## 4. Full Audit — Admin Pages (`includes/pages/adm/`)

### Already PDO-Based ✅

These files use `Database::get()` exclusively — no `$GLOBALS['DATABASE']`:

| File | Notes |
|------|-------|
| `AdminStatsService.php` | Fully PDO, style pass applied |
| `ShowOverviewPage.php` (adm) | PDO, style pass applied |
| `ShowBanPage.php` | Migrated Phase 2 |
| `ShowFlyingFleetPage.php` | Migrated Phase 2 |
| `ShowStatsPage.php` | No DB at all (Config-only) |
| `ShowStatUpdatePage.php` | No DB at all (triggers cron) |
| `ShowClearCachePage.php` | No DB at all |
| `ShowDisclamerPage.php` | No DB at all |
| `ShowPassEncripterPage.php` | No DB at all |
| `ShowLoginPage.php` | No DB at all |
| `ShowLogoutPage.php` | No DB at all |
| `ShowPluginAdminPage.php` | Uses PluginManager (PDO internally) |
| `ShowModulePage.php` | No direct DB |
| `ShowSystemDebugPage.php` | No direct DB |
| `AbstractAdminPage.php` | Base class, no DB |
| `ShowConfigBasicPage.php` | Config only |
| `ShowConfigModsPage.php` | Config only |
| `ShowChatConfigPage.php` | Config only |
| `ShowFacebookPage.php` | Config only |
| `ShowTeamspeakPage.php` | 1 match → `getFirstCell` — **deferred** |
| `ShowTopnavPage.php` | No DB at all |

### Migrated in Phase 3 ✅

| File | mysqli Usages | Risk | Notes |
|------|--------------|------|-------|
| `ShowMultiIPPage.php` | 4 | Low | Simple read+insert+delete |
| `ShowActivePage.php` | 3 | Low | Simple read+delete |
| `ShowNewsPage.php` | 7 | Low | CRUD with sql_escape → params |
| `ShowMenuPage.php` | 1 | Low | Single scalar COUNT query |
| `ShowSendMessagesPage.php` | 4 | Low | Two SELECT loops, lang filter |
| `ShowConfigUniPage.php` | 1 | Low | Single UPDATE in config save |

### Remaining mysqli — Deferred ⏸

| File | mysqli Usages | Risk | Reason |
|------|--------------|------|--------|
| `ShowAccountEditorPage.php` | 51 | High | Very complex, multi-table updates |
| `ShowResetPage.php` | 23 | High | Full reset logic, transaction-like |
| `ShowUniversePage.php` | 20 | High | Complex universe management |
| `ShowLogPage.php` | 13 | Medium | Log reading with pagination |
| `ShowQuickEditorPage.php` | 13 | Medium | Multi-table planet/user editor |
| `ShowAccountDataPage.php` | 11 | Medium | Account viewer with many joins |
| `ShowSearchPage.php` | 9 | Medium | Dynamic SQL (whitelist in place) |
| `ShowRightsPage.php` | 7 | Medium | Permission management |
| `ShowCreatorPage.php` | 6 | Medium | Galaxy creation logic |
| `ShowMessageListPage.php` | 7 | Medium | Message list with pagination |
| `ShowDumpPage.php` | 2 | Low-Medium | Needs `SHOW TABLE STATUS` + SQLDumper class |
| `ShowAutoCompletePage.php` | 4 | Low-Medium | Complex ORDER BY relevance scoring |
| `ShowInformationPage.php` | 2 | Low | Uses `getVersion()`/`getServerVersion()` — needs PDO equivalent |
| `ShowTeamspeakPage.php` | 1 | Low | Single `getFirstCell`, trivial |
| `ShowGiveawayPage.php` | 1 | Low | Dynamic SET clause (resource loop) |

### Already Marked with TODO Markers ⚠️

| File | Marker |
|------|--------|
| `ShowSearchPage.php` | `// TODO: @db-migrate` at top and on `sql_escape()` call |

---

## 5. Non-Admin mysqli Usage

> Only admin pages were in scope for this audit. Game pages
> (`includes/pages/game/`) were **not** scanned for Phase 3 —
> game pages primarily use `Database::get()` already but a
> full audit should be done in a future phase.

Known areas still using `Database_BC` outside admin pages:
- `includes/install/` — installer, intentionally excluded (high risk)
- Some `includes/classes/` helpers that wrap legacy queries

---

## 6. Target Standard

```
NEW RULE (effective immediately):
All new database access MUST use Database::get() (PDO).
No new code should depend on $GLOBALS['DATABASE'] (mysqli).
```

### Prepared Statement Convention

```php
// ✅ CORRECT — parameterised
$db->select(
    "SELECT id, username FROM %%USERS%% WHERE universe = :uni AND bana = 0",
    [':uni' => (int) Universe::getEmulated()]
);

// ✅ CORRECT — single row
$db->selectSingle(
    "SELECT id FROM %%USERS%% WHERE username = :name",
    [':name' => $username]
);

// ❌ WRONG — string interpolation (SQL injection risk)
$GLOBALS['DATABASE']->query("SELECT ... WHERE username = '" . $name . "'");

// ❌ WRONG — sql_escape (not parameterised, deprecated)
$GLOBALS['DATABASE']->sql_escape($name);
```

### Table Placeholder Convention

Always use `%%TABLE_NAME%%` — never `DB_PREFIX . 'table_name'`.
The PDO `Database` class resolves placeholders via `$this->dbTableNames`.

---

## 7. Migration Steps (Per File)

For each file being migrated:

1. **Add** `$db = Database::get();` at the top of the function
2. **Replace** `$GLOBALS['DATABASE']->query("SELECT ...")` + `while(fetch_array)` loop
   → `$db->select("SELECT ... FROM %%TABLE%%", [':param' => $value])`  + `foreach`
3. **Replace** `$GLOBALS['DATABASE']->getFirstRow(...)` → `$db->selectSingle(...)`
4. **Replace** `$GLOBALS['DATABASE']->getFirstCell(...)` → `$db->selectSingle(..., field: 'col')`
5. **Replace** `$GLOBALS['DATABASE']->query("INSERT/UPDATE/DELETE ...")` → `$db->insert/update/delete(...)`
6. **Replace** `sql_escape($x)` concatenation → `:param` bindings
7. **Add** `// @admin-migrated (DB: PDO via Database::get())` comment
8. **Apply** 4-space indent + short array syntax (style pass)
9. **Remove** `$GLOBALS['DATABASE']->free_result(...)` — PDO closes cursors automatically

---

## 8. Special Cases & Known Issues

### `ShowInformationPage.php` — Version Strings
Uses `$GLOBALS['DATABASE']->getVersion()` (mysqli client version) and `->getServerVersion()`
(MySQL server version). PDO equivalent: `$db->getHandle()->getAttribute(PDO::ATTR_SERVER_VERSION)`.
Trivial to migrate but requires a helper or inline call. Deferred to Phase 3b.

### `ShowAutoCompletePage.php` — Relevance ORDER BY
Uses a multi-factor `ORDER BY (IF(...,1,0) + IF(...,1,0)) DESC` expression with
`sql_escape` embedded. Can be migrated with PDO but the ORDER BY clause must be
constructed carefully (it is injection-safe since it uses a fixed template with
one user-controlled parameter that will become a binding). Deferred to Phase 3b.

### `ShowDumpPage.php` — SHOW TABLE STATUS
`SHOW TABLE STATUS FROM \`dbname\`` is a DDL-style query that doesn't support
parameterised bindings. Use `$db->nativeQuery(...)` post-migration.
Depends on `SQLDumper.class.php` which may also use mysqli. Audit SQLDumper first.

### `ShowGiveawayPage.php` — Dynamic SET
Builds a dynamic `SET col = col + amount` list from the resource array.
The values are integers computed server-side — not user input. However the
pattern is complex; defer until a clear PDO idiom for this pattern is established.

---

## 9. Files Outside Scope (Do Not Touch)

| Area | Reason |
|------|--------|
| `includes/install/` | Installer runs before DB layer is initialised |
| `includes/classes/Database_BC.class.php` | Keep until all usages gone |
| `includes/classes/Database.class.php` | Already the target standard |
| All game pages | Already mostly PDO; full audit is a separate task |
| `cron.php` | Uses statbuilder (PDO) — no direct mysqli |

---

## 10. Progress Log

| Phase | Date | Files Migrated | mysqli Usages Eliminated |
|-------|------|---------------|--------------------------|
| Phase 2 | Mar 2026 | ShowBanPage, ShowFlyingFleetPage | 6 + 5 = 11 |
| Phase 3 | Mar 2026 | ShowMultiIPPage, ShowActivePage, ShowNewsPage, ShowMenuPage, ShowSendMessagesPage, ShowConfigUniPage | 4+3+7+1+4+1 = 20 |
| **Total** | | **8 files** | **31 usages** |

**Remaining:** ~19 files, ~158 usages (estimated from last grep).
