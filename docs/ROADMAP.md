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
- [ ] Audit remaining admin service classes
- [ ] Audit game page classes
- [ ] Remove bare `catch (\Exception $e) { $result = []; }` patterns

### 1.3 Admin Page Base Class 🔄
- [x] `AbstractAdminPage.php` created
- [ ] Apply to `ShowStatsPage.php` (first migration candidate)
- [ ] Apply to `ShowIndexPage.php`
- [ ] Apply to remaining admin pages incrementally

### 1.4 Database Unification Preparation 🔄
- [x] `Database` (PDO) is primary interface for all game pages
- [x] `AdminStatsService` uses `Database::get()` (PDO)
- [ ] Audit `includes/pages/adm/` for `Database_BC` / raw `mysqli` usage
- [ ] Migrate admin pages one-by-one to `Database::get()`
- [ ] Mark `Database_BC.class.php` with `@deprecated` PHPDoc

---

## Phase 2 – Admin Page Migration

> Migrate all admin pages from plain PHP to `AbstractAdminPage`.
> One page per task, test after each.

**Priority order (most complex first):**

1. 📋 `ShowStatsPage.php` — already uses `AdminStatsService`, good candidate
2. 📋 `ShowAccountEditorPage.php`
3. 📋 `ShowAccountDataPage.php`
4. 📋 `ShowBanPage.php`
5. 📋 `ShowSendMessagesPage.php`
6. 📋 `ShowCronjobPage.php`
7. 📋 `ShowNewsPage.php`
8. 📋 All remaining pages

**Migration steps per page:**
1. Add `extends AbstractAdminPage` to class declaration
2. Replace manual template init with `$this->initTemplate()`
3. Replace `$tplObj->assign(...)` with `$this->assign(...)`
4. Replace `$tplObj->display(...)` with `$this->display(...)`
5. Replace JSON echoes with `$this->sendJSON(...)`
6. Add `// @admin-migrated` marker comment
7. Test page in browser

---

## Phase 3 – Database Unification

> Full migration from `Database_BC` (mysqli) to `Database` (PDO).

**Steps:**

1. 📋 Run audit:
   ```
   grep -rn "Database_BC\|new mysqli\|mysqli_connect" includes/pages/adm/
   ```

2. 📋 For each file found:
   - Replace `Database_BC::getInstance()` → `Database::get()`
   - Replace `$db->query(...)` → `$db->select(...)`/`$db->selectSingle(...)`
   - Replace `$result->fetch_assoc()` loops → direct array returns from `select()`
   - Test

3. 📋 Once all usages removed:
   - Add `@deprecated` PHPDoc to `Database_BC.class.php`
   - Remove `require_once` for `Database_BC` from bootstrap

4. 📋 Final: Delete `Database_BC.class.php`

---

## Phase 4 – Exception Handling Full Audit

> Systematically remove silent catches across the entire codebase.

**Target patterns to eliminate:**
```php
catch (\Exception $e) { $result = []; }
catch (\Exception $e) { $count = 0; }
catch (\Throwable $e) { /* nothing */ }
```

**Replacement:**
- If table/feature may not exist: log + return documented sentinel
- If unexpected: log + rethrow
- If validation failure: return structured error (no rethrow)

---

## Phase 5 – Null Coalescing Audit

> Replace inappropriate `??` usage with explicit checks.

Target pattern:
```php
$count = $result['cnt'] ?? 0;  // BAD if 'cnt' must always be set
```

**Process:**
1. `grep -rn "??" includes/pages/`
2. For each match: determine if `null` is an expected value
3. If not expected: replace with `isset()` check + `error_log()`

---

## Phase 6 – Code Style Normalization

> Apply `CODING_STYLE.md` rules to touched files incrementally.

**Rules to enforce:**
- 4-space indentation (no tabs)
- Space before `(`  in control structures
- Always-braces
- One statement per line

**Process:** Apply during regular development when touching a file. Do NOT mass-reformat.

---

## Phase 7 – Long-term (Future)

- 📋 PSR-4 autoloading (replace manual `require_once` chains)
- 📋 Dependency injection container (replace global singletons)
- 📋 Unit test coverage for service classes
- 📋 CI pipeline (PHP CS Fixer, PHPStan level 5+)
- 📋 Admin API layer (RESTful endpoints for AJAX admin actions)
