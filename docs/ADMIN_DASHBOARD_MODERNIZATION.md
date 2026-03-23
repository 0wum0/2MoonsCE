# Admin Dashboard Modernization — Complete Reference

> **Status:** ✅ Complete (Phase 10 — March 2026)
>
> All `$GLOBALS['DATABASE']` raw mysqli calls removed from admin pages.
> All admin pages migrated to `AbstractAdminPage`.
> All `.tpl` template references replaced with `.twig`.

---

## 1. What Was Done

### Database Migration (mysqli → PDO)

Every `$GLOBALS['DATABASE']` call was replaced with `Database::get()` (PDO) using named
parameterized queries (`:param` bindings). No raw string concatenation of user input remains
in any admin page except `ShowSearchPage` (see §4).

| Pattern replaced | PDO equivalent used |
|---|---|
| `$GLOBALS['DATABASE']->query("SELECT...")` + `while(fetch_array)` | `$db->select(...)` + `foreach` |
| `$GLOBALS['DATABASE']->getFirstRow("SELECT...")` | `$db->selectSingle(...)` |
| `$GLOBALS['DATABASE']->query("UPDATE...")` | `$db->update(...)` |
| `$GLOBALS['DATABASE']->query("DELETE...")` | `$db->delete(...)` |
| `$GLOBALS['DATABASE']->multi_query(...)` | Sequential individual PDO calls |
| `$GLOBALS['DATABASE']->sql_escape($x)` | `:param` bound parameter |
| Dynamic column-name queries | `$db->nativeQuery(...)` with whitelisted column names |

### AbstractAdminPage Migration

Every admin page now extends `AbstractAdminPage`. Template rendering uses:

| Legacy | Modern |
|---|---|
| `$template = new template()` | (removed — base class owns template) |
| `$template->assign_vars([...])` | `$this->assign([...])` |
| `$template->show('Foo.twig')` | `$this->show('Foo.twig')` |
| `$template->message(...)` | `$this->message(...)` |
| `$template->loadscript(...)` | `$this->loadScript(...)` |

### Template References

All `.tpl` references replaced with `.twig`. Twig templates reside in
`styles/templates/adm/`.

---

## 2. Files Migrated (Complete List)

### Phase 8 — Installer & Admin Harmonization

| File | mysqli Usages | Notes |
|------|--------------|-------|
| `ShowAutoCompletePage.php` | 4 | ORDER BY injection-safe whitelist |
| `ShowDumpPage.php` | 2 | `nativeQuery()` for table listing |
| `ShowMenuPage.php` | 1 | `selectSingle()` scalar |

### Phase 9 — Plugin & Extension Architecture

| File | Notes |
|------|-------|
| `ShowGiveawayPage.php` | Dynamic column UPDATE via `nativeQuery()` |
| `ShowVertifyPage.php` | `.tpl` → `.twig` |
| `ShowLogPage.php` | 13 usages, unserialize safety |

### Phase 10 — Final Batch (March 2026)

| File | mysqli Usages Removed | Key Changes |
|------|-----------------------|-------------|
| `ShowRightsPage.php` | 7 | `select()`, `update()`, `delete()`; handleRights/handleUsers methods |
| `ShowQuickEditorPage.php` | 13 | `selectSingle()`, `update()`; planet+player edit |
| `ShowCreatorPage.php` | 6 | `selectSingle()`, `insert()`; player/moon/planet creation |
| `ShowUniversePage.php` | 20 | Sequential `delete()` calls replacing `multi_query()` |
| `ShowResetPage.php` | 23 | Sequential `update()`/`delete()` replacing raw multi-query |
| `ShowSupportPage.php` | — | Migrated to `AbstractAdminPage`; private methods showList/view/send |
| `ShowAccountEditorPage.php` | 51 | All resource/ship/defense/building/research/officer/alliance edits |
| `ShowAccountDataPage.php` | 11 | User+alliance+planet detail with JOIN, stats, PDO `select()` |
| `ShowSearchPage.php` | 9 | `nativeQuery()` with `:search_key` binding; ORDER BY whitelist enforced |

**Total `$GLOBALS['DATABASE']` usages removed in Phase 10: ~150**

**Cumulative total across all phases: ~180+ usages removed across ~20 files**

---

## 3. Security Improvements

- **SQL injection eliminated**: all user-controlled values are bound parameters.
- **ORDER BY injection hardened**: `ShowSearchPage` and `ShowAutoCompletePage` use
  `in_array($Order, $whitelist)` before interpolating column names.
- **`$OrderBY` forced to `ASC`/`DESC`**: `strtoupper()` + ternary in `MyCrazyLittleSearch`.
- **`unserialize()` safety**: `ShowLogPage` guards with `@unserialize` + `is_array` check.
- **Permission checks**: all pages go through `AbstractAdminPage::__construct($rightKey)`
  which calls `allowedTo($rightKey)` and throws on failure.

---

## 4. Known Deferred Items

| Item | File | Reason |
|------|------|--------|
| Dynamic SQL column selection | `ShowSearchPage::MyCrazyLittleSearch` | `SELECT {$SpecifyItems}` uses a whitelisted, comma-separated column list. Not user-injectable (set by PHP logic only). Acceptable as-is. |
| `$SpecialSpecify` raw SQL fragment | `ShowSearchPage` | Built entirely from PHP constants (`TIMESTAMP`, `Universe::getEmulated()`, `$USER['authlevel']`) — no user string input. Acceptable. |

---

## 5. Architecture After Modernization

```
admin.php
  └─ AbstractAdminPage::__construct($rightKey)
       ├─ allowedTo($rightKey)          ← permission guard
       └─ $this->run()                  ← page logic
            ├─ $db = Database::get()    ← PDO singleton
            ├─ $db->select/selectSingle/update/delete/nativeQuery(SQL, [params])
            ├─ $this->assign([...])     ← template vars
            └─ $this->show('Tpl.twig') ← Twig render via adm_main()
```

---

## 6. CSS — Admin `main.css`

`styles/resource/css/admin/main.css` covers all required UI patterns:

- Layout: sidebar, topbar, main content, mobile responsive (breakpoint 1100px)
- Cards: `.neon-card`, `.card`, `.card-header`, `.card-body`, `.card-hover`
- Tables: `body.admin-dashboard table` full theme; `.legacy-content table` compatibility
- Forms: all input types, focus states, submit buttons, checkboxes, selects
- Badges, alerts, nav pills/tabs, accordions, input groups
- Bootstrap-compatible grid (col-*) and utility classes
- Forum theme, flipcards, charts, dashboard grid boxes
- Scrollbars, animations, pre/code blocks

No missing styles identified. All admin page templates are covered.

---

## 7. Files NOT Migrated (Intentionally)

| File | Reason |
|------|--------|
| `ShowSearchPage.php` — `MyCrazyLittleSearch()` `$SpecifyItems` column list | PHP-controlled, not user input |
| `Database_BC.class.php` | Kept for backward compat (installer); marked `@deprecated` |
