# 2MoonsCE – Service Architecture Guide

> Based on direct inspection of the real codebase as of Phase 7 (Mar 2026).
> This document describes the practical service-layer pattern used in this project,
> not a theoretical ideal.

---

## 1. The Problem: Tangled Responsibilities

Before Phase 7, most admin pages mixed three distinct responsibilities inside a
single function:

```php
function ShowBanPage(): void
{
    // 1. Request parsing
    $name = HTTP::_GP('ban_name', '', true);

    // 2. Business logic (ban time calculation, permanent ban flag, vacation mode)
    $banTime = $days * 86400 + $hour * 3600 + $mins * 60 + $secs;
    if ($banUser['longer'] > TIMESTAMP) { $banTime += ...; }
    $bannedUntil = isset($_POST['permanent']) ? 2147483647 : ...;

    // 3. Database writes (directly in the page function)
    $db->insert("INSERT INTO %%BANNED%% ...", [...]);
    $db->update("UPDATE %%USERS%% SET bana = 1 ...", [...]);

    // 4. Template rendering
    $template = new template();
    $template->show('BanPage.twig');
}
```

This makes it hard to:
- Test the business logic in isolation
- Reuse the ban logic from another context (e.g. a plugin)
- Understand what the page is doing vs. what the database is doing
- Debug a DB failure without re-reading the whole page function

---

## 2. The Target Pattern

The project uses a **lightweight three-layer pattern**:

```
Page / Controller
    ↓  reads request, calls service, assigns template vars
Service Class
    ↓  implements business logic, delegates DB reads/writes
Database::get()
    ↓  PDO wrapper — all SQL lives here
```

**Key constraints for this project:**

- Do NOT invent a full repository abstraction for every table
- Do NOT require dependency injection; services use `Database::get()` directly
- Service classes are plain PHP classes, no interface required unless reuse demands it
- Pages call services; services do NOT call templates
- Existing `AdminStatsService` is the reference implementation

---

## 3. Existing Service Classes

### `AdminStatsService` (reference)

`includes/pages/adm/AdminStatsService.php`

- Singleton with `getInstance(int $universe)`
- All SQL in service methods; page only calls methods and assigns result to template
- All catches logged; optional tables return `-1` sentinel (see `ERROR_HANDLING_GUIDE.md`)
- Template variable mapping: page-level responsibility, not service-level

### `PlayerUtil`

`includes/classes/PlayerUtil.class.php`

- Static utility class — not a service per se, but encapsulates player creation,
  password hashing, position finding, message sending
- `sendMessage()` — used by multiple pages and plugins
- **Pattern:** static methods, DB access internal, callers do not touch SQL

### `Cronjob`

`includes/classes/Cronjob.class.php`

- Static methods: `execute()`, `reCalculateCronjobs()`, `getNeedTodoExecutedJobs()`
- DB access fully encapsulated; page only calls static API
- **Already a good example of the target pattern**

---

## 4. Services Introduced in Phase 7

### `CacheService`

`includes/pages/adm/CacheService.php`

**Extracted from:** `ShowClearCachePage.php` — the `ClearCacheSafe()` function was
140 lines of business logic (path validation, directory creation, recursive deletion)
living inside a page file.

**Responsibility:** Know which cache directories exist, ensure they are present, and
safely delete their contents. No DB access. No rendering.

```php
$result = CacheService::clearAll();
// returns: ['cleared_files' => int, 'cleared_dirs' => int,
//           'skipped' => int, 'errors' => string[], 'paths' => string[]]
```

**Page after:** `ShowClearCachePage` calls `CacheService::clearAll()`, formats a
one-line message, and renders. ~15 lines total.

---

### `BanService`

`includes/pages/adm/BanService.php`

**Extracted from:** `ShowBanPage.php` — ban-time calculation, banned-until logic,
permanent ban flag, vacation mode toggle, and all `%%BANNED%%`/`%%USERS%%` writes
were embedded in the page.

**Responsibility:** All read/write operations on the ban system. Returns structured
data to the page; page only assigns template vars and renders.

```php
$service = new BanService($universe);

// Read
$banService->getUserList(int $callerAuthLevel, string $order, bool $bannedOnly): array
$banService->getBannedList(string $order): array
$banService->getBanRecord(string $username): ?array

// Write
$banService->banUser(string $username, array $params): void
$banService->unbanUser(string $username): void
```

**Page after:** `ShowBanPage` handles request parsing and template assignment only.
The 80-line ban logic block is replaced by two service calls.

---

### `NewsRepository`

`includes/pages/adm/NewsRepository.php`

**Extracted from:** `ShowNewsPage.php` — all four SQL operations (SELECT all, SELECT
single, INSERT, UPDATE, DELETE) were inline in the page function.

**Responsibility:** All read/write operations on the `%%NEWS%%` table. Pure CRUD,
no rendering concern.

```php
$repo = new NewsRepository();

$repo->findAll(): array
$repo->findById(int $id): ?array
$repo->create(string $user, string $title, string $text): void
$repo->update(int $id, string $title, string $text): void
$repo->delete(int $id): void
```

**Page after:** `ShowNewsPage` reads request params, calls repository, and assigns
template vars. No SQL in the page.

---

## 5. File-Level Responsibility Classification

### After Phase 7

| File | Responsibility | Pattern |
|------|----------------|---------|
| `ShowClearCachePage.php` | Request → call CacheService → render | AbstractAdminPage |
| `ShowStatsPage.php` | Request → save config → render | AbstractAdminPage |
| `ShowBanPage.php` | Request → call BanService → render | AbstractAdminPage |
| `ShowNewsPage.php` | Request → call NewsRepository → render | AbstractAdminPage |
| `CacheService.php` | Cache directory management, safe deletion | Service (static) |
| `BanService.php` | Ban/unban logic, ban record DB access | Service (instance) |
| `NewsRepository.php` | News CRUD — `%%NEWS%%` table only | Repository (instance) |
| `AdminStatsService.php` | Dashboard KPIs, chart data, report | Service (singleton) |

### Not yet refactored (deferred)

| File | Why deferred |
|------|-------------|
| `ShowCronjobPage.php` | Logic is mostly `Cronjob::` static calls already; low gain |
| `ShowFlyingFleetPage.php` | Complex JOIN query + lock/unlock; defer to Phase 7b |
| `ShowBanPage.php` HTML builder | `<option>` building inside loop — move to Twig filter in Phase 8 |
| `ShowSendMessagesPage.php` | `PlayerUtil::sendMessage()` already exists; small risk/gain |
| All high-risk admin pages | `ShowAccountEditorPage`, `ShowResetPage`, etc. — deferred |

---

## 6. Rules for New Service Classes

1. **Services do not render.** They return arrays, scalars, or throw exceptions.
2. **Services use `Database::get()` directly.** No injection required at this scale.
3. **Services log errors.** Follow `ERROR_HANDLING_GUIDE.md` — no silent catches.
4. **One service per domain.** `BanService` only touches bans. `NewsRepository` only
   touches news. Do not create "AdminService" that does everything.
5. **Static vs instance:** Use static methods for stateless utilities (`CacheService`).
   Use instance methods when the service holds state (e.g. `$universe` in `BanService`).
6. **No base class required** unless two services share significant boilerplate.
7. **Naming:** `XxxService` for logic-bearing classes; `XxxRepository` for pure CRUD.

---

## 7. What Is NOT a Service

- **Template assignment helpers** — keep these in the page/controller
- **LNG string formatting** — format in the page, pass to template
- **Permission checks** — handled by `AbstractAdminPage::__construct()` + `allowedTo()`
- **HTTP input parsing** — always in the page, never in a service
- **Config reads** — `Config::get()` is called from wherever needed; no wrapper

---

## 8. Relationship to Other Docs

- `docs/ARCHITECTURE.md §5` — admin page base class (`AbstractAdminPage`)
- `docs/ARCHITECTURE.md §4` — database layer (`Database::get()`)
- `docs/ERROR_HANDLING_GUIDE.md` — how to handle exceptions in service methods
- `docs/ROADMAP.md §Phase 7` — what was done and what is planned
- `docs/ADMIN_PAGE_MIGRATION.md` — migration pattern for `AbstractAdminPage`
