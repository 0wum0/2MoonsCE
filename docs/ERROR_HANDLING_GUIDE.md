# 2MoonsCE – Error Handling Guide

> Based on direct inspection of the actual codebase.
> Every pattern described here is derived from real code.

---

## 1. Project-Level Exception Handler

`includes/common.php` registers a global exception handler via `set_exception_handler()`.
Any uncaught `Throwable` is routed through `ShowErrorPage::printError()`, which renders
a safe HTML error page without exposing stack traces in production.

**This means:** code that intentionally lets exceptions propagate is safe — they will
be caught at the top level and presented cleanly. You do not need defensive `try/catch`
blocks just to avoid white screens.

---

## 2. Core Rules

### Rule 1 — Only catch when you add value

A `catch` block must do at least one of the following:
- Log the error with context
- Rethrow with added context
- Clean up a resource (rollback, close file, flush buffer)
- Implement a **documented, intentional** fallback (optional table, non-critical feature)

```php
// GOOD — logs, then provides a documented sentinel
try {
    $count = $db->selectSingle($sql, $params);
} catch (\Throwable $e) {
    error_log('[MyService] query failed: ' . $e->getMessage());
    return -1; // sentinel: data not available, caller must handle
}

// GOOD — rethrow with context
try {
    $db->nativeQuery($query);
} catch (\Throwable $e) {
    $this->log('SaveDataIntoDB() ERROR: ' . $e->getMessage() . ' | query=' . substr(trim($query), 0, 200));
    throw $e;
}

// BAD — silent swallow hides real failures
try {
    $result = $db->selectSingle($sql, $params);
} catch (\Exception $e) {
    $result = [];
}
```

### Rule 2 — Never use catch to mask required data

If the data is required for the operation to be correct, do not replace it with a fake value.
An exception in that case represents a real bug — surface it.

```php
// BAD — replaces required config with empty string; downstream fails mysteriously
try {
    $gameName = Config::get()->game_name;
} catch (\Throwable $e) {
}

// GOOD — log, keep a safe fallback visible in the output
try {
    $gameName = Config::get()->game_name;
} catch (\Throwable $e) {
    error_log('[ErrorPage] Config unavailable when building error page: ' . $e->getMessage());
    $gameName = '-';
}
```

### Rule 3 — Pointless catch+rethrow is noise

A `try { ... } catch (Exception $e) { throw $e; }` block with no added value
(no log, no context, no cleanup) must be removed. It adds indentation and complexity
without providing any benefit.

```php
// BAD — identical to having no try/catch at all
try {
    $next_time = self::calculateDateTime($expression, $next);
} catch (Exception $e) {
    throw $e;
}

// GOOD — just let the exception propagate naturally
$next_time = self::calculateDateTime($expression, $next);
```

### Rule 4 — Use `??` only for genuinely optional data

`??` is correct when the key or value may legitimately not exist.
It is wrong when a missing value means the query or structure is broken.

```php
// GOOD — optional config key with a sane default
$speed = $config->resource_multiplier ?? 1.0;

// BAD — the COUNT(*) column 'cnt' MUST exist; missing means the query is broken
$count = (int) ($row['cnt'] ?? 0);

// GOOD — validate first, then use
if (!isset($row['cnt'])) {
    error_log('[MyClass] Expected column cnt missing in result');
}
$count = (int) $row['cnt'];
```

### Rule 5 — Use `-1` sentinel only for documented optional tables

The `-1` sentinel pattern is used in `AdminStatsService` to indicate that a query
could not complete because an optional table does not exist in all installations.
This is acceptable **only when**:
- The table is documented as optional
- The caller explicitly handles the `-1` case (renders "N/A" in UI)
- The error is logged before returning `-1`

Do not use `-1` as a generic "something went wrong" value.

### Rule 6 — Mail failures are non-fatal, but log them

Sending email is always considered non-fatal. The recommended pattern is:

```php
try {
    Mail::send($email, $name, $subject, $content);
} catch (\Throwable $e) {
    error_log('[ClassName] Mail send failed for user ' . $userId . ': ' . $e->getMessage());
    // continue — mail failure does not abort the operation
}
```

### Rule 7 — Reflection is debug-only, degrade gracefully with a log

When using `ReflectionObject` for debug pages, catch + log, then proceed with partial data.

```php
try {
    $registry = $regProp->getValue($mm);
} catch (\Throwable $e) {
    error_log('[ShowSystemDebugPage] Reflection failed: ' . $e->getMessage());
    // $registry stays [] — debug page renders with partial data
}
```

---

## 3. Pattern Catalogue

### Pattern A — Optional table (sentinel `-1`)

Used for stats/metrics that query tables which may not exist in all installations.

```php
try {
    $result = $db->selectSingle($sql, $params);
    $count  = $this->extractCount($result, 'contextName');
} catch (\Throwable $e) {
    error_log('[AdminStatsService] contextName: table unavailable. ' . $e->getMessage());
    $count = -1; // sentinel: data not available
}
```

**Where used:** `AdminStatsService` — all methods querying `%%LOG_FLEETS%%`, `%%TOPKB%%`,
`%%TICKETS%%`, `%%ALLIANCE%%`.

### Pattern B — Optional feature, degrade to empty / default

Used for non-critical UI enrichment (forum notifications, event list, chart data).

```php
try {
    $forumNotifCount = $forumObj->getForumNotificationCount((int)$USER['id']);
} catch (\Throwable $e) {
    error_log('[AbstractGamePage] Forum notification count unavailable: ' . $e->getMessage());
    $forumNotifCount = 0; // degraded: no notifications shown
}
```

### Pattern C — Input validation with safe fallback (DateTimeZone)

Used when external input (user timezone string) may be malformed.

```php
try {
    new DateTimeZone($tz);
    return $tz;
} catch (\Throwable $e) {
    error_log('[AbstractGamePage] Invalid timezone "' . $tz . '", falling back to UTC: ' . $e->getMessage());
    return 'UTC';
}
```

### Pattern D — Mail send is non-fatal

```php
try {
    Mail::send($email, $name, $subject, $content);
} catch (\Throwable $e) {
    error_log('[ShowVertifyPage] Verification mail send failed: ' . $e->getMessage());
    // non-fatal: registration proceeds regardless
}
```

### Pattern E — DB write with log + rethrow (statbuilder)

Used when a failure is fatal to the current operation but must be visible.

```php
try {
    Database::get()->nativeQuery($query);
} catch (\Throwable $e) {
    $this->log('ERROR: ' . $e->getMessage() . ' | query=' . substr(trim($query), 0, 200));
    throw $e; // abort the stat build run
}
```

### Pattern F — Plugin/module isolation (ModuleManager, PluginManager)

Modules and plugins run inside isolated try/catch to prevent one bad plugin crashing the game.
These always log and call `handleModuleCrash()` or `safeDeactivate()` — NOT silent swallows.

```php
try {
    $module->boot($ctx);
} catch (Throwable $e) {
    $this->handleModuleCrash($module, 'boot', $e);
}
```

---

## 4. Anti-Patterns to Avoid

| Anti-pattern | Why it's bad | What to do instead |
|---|---|---|
| Empty catch `catch (\Throwable $e) { }` | Hides all failures silently | Add `error_log()` at minimum |
| Catch + rethrow identity `catch ($e) { throw $e; }` | Pure noise, adds no value | Remove the try/catch entirely |
| `$result = [] on catch` without logging | Caller sees empty data, no way to diagnose | Add `error_log()` |
| `// This mail is wayne.` | No log, mocking comment | Replace with `error_log()`, remove comment |
| `$count = $row['cnt'] ?? 0` | Masks missing COUNT(*) column | `isset()` check + log before cast |
| `-1` sentinel without documentation | Confuses callers | Document the sentinel in the PHPDoc |
| Nested try/catch with identical fallback | Hard to read | Flatten or use a helper method |

---

## 5. File-by-File Status (Phase 6 Audit)

### Cleaned in Phase 6

| File | Issue fixed |
|------|-------------|
| `includes/GeneralFunctions.php` | Empty catch in error page — added `error_log()` |
| `includes/pages/login/ShowVertifyPage.class.php` | `// This mail is wayne.` — replaced with `error_log()` |
| `includes/pages/game/ShowOverviewPage.class.php` | Silent events table catch — added `error_log()` |
| `includes/pages/game/AbstractGamePage.class.php` | Forum notif + DateTime fallback — added `error_log()` |
| `includes/pages/adm/ShowSystemDebugPage.php` | Reflection + `isEnabled()` catches — added `error_log()` |
| `includes/pages/adm/ShowInformationPage.php` | Three DateTimeZone catches — added `error_log()` |
| `includes/libs/tdcron/class.tdcron.php` | Three pointless catch+rethrow identity blocks — removed |
| `includes/libs/tdcron/class.tdcron.entry.php` | One pointless catch+rethrow identity block — removed |

### Already good (no changes needed)

| File | Why it's already correct |
|------|--------------------------|
| `includes/pages/adm/AdminStatsService.php` | All catches log + use documented `-1` sentinel |
| `includes/classes/PluginManager.class.php` | All catches log, safe-deactivate pattern |
| `includes/classes/ModuleManager.class.php` | Uses `handleModuleCrash()` — intentional isolation |
| `includes/classes/HookManager.class.php` | All catches log |
| `includes/classes/modules/QueueModule.class.php` | Config fallback documented (INSTALL mode) |
| `includes/classes/modules/ProductionModule.class.php` | Config fallback documented (INSTALL mode) |
| `includes/classes/class.statbuilder.php` | Logs + rethrows in write path, logs in read path |
| `includes/pages/game/ShowForumPage.class.php` | Shows clear user-facing error on missing tables |
| `includes/pages/adm/ShowPluginAdminPage.php` | All catches produce user-visible `$error` message |
| `includes/pages/adm/ShowForumAdminPage.php` | Catches log + set user-visible error message |
| `includes/missions/MissionCaseSpy.class.php` | Template fallback with ob cleanup — intentional |

### Deferred — legacy / risky

| File | Why deferred |
|------|-------------|
| All legacy plain-PHP admin pages | Not yet on the migration path; touching them is risky without a full DB migration |
| `includes/libs/phpmailer/*` | Third-party library — do not modify |
| `includes/libs/tdcron/class.tdcron.entry.php` (main parsing logic) | Large, complex expression parser — only removed the one clear identity-catch |

---

## 6. Relationship to Global Handler

The global exception handler in `common.php` is the last line of defense.
The rules above define what to do **before** the global handler catches it:

- If you can handle it locally (optional feature, non-fatal) → catch + log + degrade
- If you cannot handle it (required data, unexpected failure) → let it propagate to global handler
- If you need to add context before propagating → catch + log + rethrow

The goal is that the global handler only receives exceptions that are genuinely unexpected —
not things that were silently swallowed and turned into invisible wrong state.

---

## 7. Alignment with Other Docs

- `docs/CODING_STYLE.md §8` — Exception handling rules summary
- `docs/ARCHITECTURE.md §7` — Global exception handler details
- `docs/ROADMAP.md §Phase 6` — Phase 6 error transparency progress
- `CONTRIBUTING.md §5` — Reviewer checklist for exception handling
