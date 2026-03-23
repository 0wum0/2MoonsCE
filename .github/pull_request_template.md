## Summary

<!-- What does this PR do? One sentence. -->

## Type

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor / migration step
- [ ] Documentation
- [ ] Tooling / chore

## Changes

<!-- List the files changed and briefly explain why. -->

## How to verify

<!-- Steps to test this change locally. Include route/URL, user actions, expected result. -->

1. 
2. 
3. 

## Screenshots

<!-- If there is any visible UI change, add a before/after screenshot. Delete this section if not applicable. -->

## Risk

<!-- Does this change any existing behaviour? Could it affect other pages or routes? -->

- [ ] Low — isolated change, no behaviour impact
- [ ] Medium — touches shared code or existing behaviour, tested manually
- [ ] High — architectural change or DB schema change — explain below

<!-- If medium or high, explain: -->

## Checklist

- [ ] Code follows `docs/CODING_STYLE.md` (4-space indent, braces, no `array()`, type hints)
- [ ] No new `$GLOBALS['DATABASE']` usages — uses `Database::get()` (PDO)
- [ ] No silent exception swallows — exceptions are logged or rethrown
- [ ] New admin pages extend `AbstractAdminPage` (if applicable)
- [ ] `admin.php` routing uses `new ShowXxxPage()` not `ShowXxxPage()` (if applicable)
- [ ] Migration marker added (`// @admin-migrated` or `// @admin-migrated (DB: ...)`) if applicable
- [ ] `docs/ROADMAP.md` updated if completing a planned migration step
- [ ] `CHANGELOG.md` entry added for user- or operator-visible changes
- [ ] Tested locally — no white screens, no fatal errors on affected routes
