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

## 13. Enforcement

- Apply these rules to **all newly written or touched code**
- Do NOT mass-reformat legacy files without an explicit refactor task
- Code review checklist: spacing, braces, exception handling, `??` usage
