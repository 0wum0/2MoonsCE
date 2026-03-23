# 2MoonsCE – Coding Style Guide

> **Scope:** All PHP files touched going forward. Do NOT reformat untouched legacy files.

---

## 1. General

- PHP version: **8.3 / 8.4**
- Every file starts with `<?php` followed by a blank line and `declare(strict_types=1);`
- No closing `?>` tag in pure PHP files
- UTF-8 encoding, LF line endings

---

## 2. Indentation & Spacing

- **4 spaces** – never tabs
- One blank line between methods
- One blank line after opening braces of class/function bodies only when needed for readability
- No trailing whitespace

---

## 3. Control Structures

Always use a **space before the opening parenthesis**, and braces **on the same line**:

```php
// GOOD
if ($condition) {
    doSomething();
}

foreach ($items as $key => $value) {
    process($key, $value);
}

while ($running) {
    tick();
}

// BAD – no space before parenthesis
if($condition){
    doSomething();
}

// BAD – brace on next line
if ($condition)
{
    doSomething();
}
```

---

## 4. Braces – Always Required

Even for single-line bodies:

```php
// GOOD
if ($x) {
    return true;
}

// BAD
if ($x) return true;
```

---

## 5. One Statement Per Line

```php
// GOOD
$a = 1;
$b = 2;

// BAD
$a = 1; $b = 2;
```

---

## 6. String Quotes

- **Single quotes** for plain strings with no variable interpolation
- **Double quotes** only when embedding variables or special escape sequences (`\n`, `\t`)

```php
$name = 'HelloWorld';
$msg  = "Hello, {$name}!\n";
```

---

## 7. Type Declarations

- Always declare parameter types and return types on new/modified methods
- Use `mixed` only when genuinely mixed

```php
public function getCount(int $limit): int
{
    // ...
}
```

---

## 8. Exception Handling

**Never silently swallow exceptions.** See `ARCHITECTURE.md` for the full policy.

```php
// GOOD – log and rethrow
try {
    $result = $db->selectSingle($sql, $params);
} catch (\Throwable $e) {
    error_log('[AdminStatsService] Query failed: ' . $e->getMessage());
    throw $e;
}

// GOOD – known optional table, return sentinel value
try {
    $result = $db->selectSingle($sql, $params);
} catch (\Throwable $e) {
    error_log('[AdminStatsService] Optional table missing: ' . $e->getMessage());
    return -1; // documented sentinel: table not available
}

// BAD – silent swallow
try {
    $result = $db->selectSingle($sql, $params);
} catch (\Exception $e) {
    $result = [];
}
```

---

## 9. Null Coalescing (`??`)

Use `??` **only** when `null` is an expected, documented return value.  
When a missing value indicates a bug, log and handle explicitly.

```php
// GOOD – null is expected (optional config key)
$speed = $config->resource_multiplier ?? 1;

// BAD – missing DB column silently defaults
$count = $result['cnt'] ?? 0;

// GOOD – missing DB column is flagged
if (!isset($result['cnt'])) {
    error_log('Expected column cnt missing in query result');
}
$count = (int) $result['cnt'];
```

---

## 10. Array Syntax

Always use short array syntax `[]`, never `array()`.

```php
// GOOD
$data = ['key' => 'value'];

// BAD
$data = array('key' => 'value');
```

---

## 11. Comments

- Use `//` for inline comments, `/* */` for block comments, `/** */` for PHPDoc
- Comments explain **why**, not **what**
- Do not leave commented-out dead code in committed files

---

## 12. File Naming

| Type | Convention | Example |
|------|-----------|---------|
| Class file (game) | `ClassName.class.php` | `ShowOverviewPage.class.php` |
| Class file (admin/service) | `ClassName.php` | `AdminStatsService.php` |
| Interface | `InterfaceName.php` | `GameModuleInterface.php` |
| Twig template | `page.section.variant.twig` | `page.overview.default.twig` |

---

## 13. Database Access

Always use the PDO wrapper `Database::get()`. Never use `$GLOBALS['DATABASE']` (mysqli legacy) in new or touched code.

```php
// GOOD — PDO via Database::get()
$db   = Database::get();
$rows = $db->select('SELECT id, username FROM %%USERS%% WHERE universe = :uni;', [':uni' => 1]);
$row  = $db->selectSingle('SELECT id FROM %%USERS%% WHERE id = :id;', [':id' => $userId]);
$db->update('UPDATE %%USERS%% SET username = :n WHERE id = :id;', [':n' => $name, ':id' => $userId]);
$db->insert('INSERT INTO %%NEWS%% (title) VALUES (:t);', [':t' => $title]);
$db->delete('DELETE FROM %%PLANETS%% WHERE id = :id;', [':id' => $planetId]);

// BAD — mysqli legacy, never use in new code
$GLOBALS['DATABASE']->query("SELECT * FROM " . USERS . " WHERE id = $id");
$GLOBALS['DATABASE']->sql_escape($input);
```

- Table name placeholders `%%TABLE_NAME%%` are resolved automatically by the wrapper
- Always use named `:param` bindings — never concatenate user input into SQL
- Mark files migrated from mysqli with `// @admin-migrated (DB: PDO via Database::get())`

---

## 14. Admin Page Pattern

New and migrated admin pages extend `AbstractAdminPage`. Legacy plain-function pages remain
untouched until explicitly migrated (see `docs/ADMIN_PAGE_MIGRATION.md`).

```php
// GOOD — new admin page pattern
// @admin-migrated (Phase N — AbstractAdminPage)
class ShowXxxPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowXxxPage');  // runs allowedTo() then $this->run()
    }

    protected function run(): void
    {
        global $LNG;
        $this->assign(['key' => $value]);
        $this->show('XxxPage.twig');         // calls template::show() → adm_main() → layout
    }
}

// BAD — old plain-function pattern (legacy only, do not write new pages this way)
if (!allowedTo(...)) throw new Exception('Permission error!');
function ShowXxxPage(): void {
    $template = new template();
    $template->assign_vars([...]);
    $template->show('XxxPage.twig');
}
```

Key rules:
- Use `$this->show()` for full-page renders — it calls `template::show()` which injects the admin layout
- Use `$this->message()` for status/info messages
- **Never** call `$this->tplObj->display()` directly — it skips the admin layout (`adm_main()`)
- Routing in `admin.php`: `ShowXxxPage()` → `new ShowXxxPage()`

---

## 15. Twig Templates

- Admin templates live in `styles/templates/adm/`, game in `styles/templates/game/`
- Use `{{ variable }}` for output — Twig auto-escapes HTML
- Use `{{ variable|raw }}` **only** when the value is trusted/pre-escaped HTML
- Use `{% if %}`, `{% for %}`, `{% block %}` — no inline PHP
- Access language keys via `{{ LNG.key_name }}` (Twig dot notation)
- Prefer descriptive variable names passed from PHP over complex Twig logic

```twig
{# GOOD #}
{% for planet in planets %}
    <li>{{ planet.name }} [{{ planet.galaxy }}:{{ planet.system }}:{{ planet.position }}]</li>
{% endfor %}

{# BAD — complex logic in template #}
{% set total = 0 %}
{% for p in planets %}{% set total = total + p.metal %}{% endfor %}
```

---

## 16. JavaScript

- Use `const` / `let` — never `var`
- One statement per line
- Use `===` / `!==` — never `==` / `!=`
- Always `use strict` in module-level scripts
- jQuery is available globally as `$` — no need to import

```js
// GOOD
const url = 'game.php?page=overview';
let count = 0;

if (response.ok === true) {
    updateUI(response.data);
}

// BAD
var url = 'game.php?page=overview'
if (response.ok) updateUI(response.data)
```

---

## 17. Enforcement

- Apply these rules to **all newly written or touched code**
- Do NOT mass-reformat legacy files without an explicit refactor task
- Code review checklist: spacing, braces, exception handling, `??` usage, DB layer, admin page pattern

---

## 18. Legacy vs. New Standard

> This section is important for setting realistic expectations.

**Legacy code** (most of `includes/pages/adm/`, parts of `includes/classes/`) may still contain:
- `array()` instead of `[]`
- `$GLOBALS['DATABASE']` (mysqli) instead of `Database::get()` (PDO)
- Plain PHP functions instead of `AbstractAdminPage` subclasses
- Tabs instead of 4-space indent
- Mixed variable naming styles
- Implicit type coercion and no type declarations

**This is intentional and expected.** Legacy code is migrated incrementally as per the
roadmap (`docs/ROADMAP.md`). A blanket reformat is explicitly forbidden.

**The rule is simple:**
- Touched files → must conform to this style guide
- Untouched legacy files → leave alone unless there is a specific migration task

New contributors should not submit PRs that reformat untouched legacy files.
Reviewers should reject such PRs to keep diffs reviewable.
