<?php

/**
 * PHP CS Fixer configuration for 2MoonsCE.
 *
 * This config enforces the rules defined in docs/CODING_STYLE.md.
 *
 * USAGE (run manually — not part of any auto-format pipeline):
 *
 *   # Dry run — show what would change, no files modified:
 *   vendor/bin/php-cs-fixer fix --dry-run --diff
 *
 *   # Fix a single file:
 *   vendor/bin/php-cs-fixer fix includes/pages/adm/ShowPassEncripterPage.php
 *
 *   # Fix all files in a directory:
 *   vendor/bin/php-cs-fixer fix includes/pages/adm/
 *
 * IMPORTANT:
 *   - Do NOT run this on the entire repo at once.
 *   - Apply only to newly written or explicitly migrated files.
 *   - See docs/CODING_STYLE.md §18 (Legacy vs. New Standard).
 *
 * INSTALL (dev only):
 *   composer require --dev friendsofphp/php-cs-fixer
 */

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/includes/pages/adm',
        __DIR__ . '/includes/pages/game',
        __DIR__ . '/includes/classes',
    ])
    ->name('*.php')
    ->notPath('vendor')
    ->notPath('cache')
    ->notPath('install')
    ->exclude('vendor')
    ->exclude('cache');

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(false)
    ->setRules([
        // ── Baseline ─────────────────────────────────────────────────────────
        '@PSR2'                         => true,

        // ── Indentation & whitespace ──────────────────────────────────────────
        'indentation_type'              => true,    // 4 spaces (per CODING_STYLE.md §2)
        'no_trailing_whitespace'        => true,
        'no_whitespace_in_blank_line'   => true,
        'blank_line_after_namespace'    => true,
        'blank_line_after_opening_tag'  => true,
        'single_blank_line_at_eof'      => true,

        // ── Braces & control structures ───────────────────────────────────────
        'braces'                        => [
            'position_after_functions_and_oop_constructs' => 'next',  // class/function brace on next line
            'position_after_control_structures'           => 'same',  // if/else/for brace on same line
            'position_after_anonymous_constructs'         => 'same',
        ],
        'control_structure_braces'      => true,    // always require braces (§4)
        'no_alternative_syntax'         => true,
        'elseif'                        => true,    // elseif not else if

        // ── Arrays ────────────────────────────────────────────────────────────
        'array_syntax'                  => ['syntax' => 'short'],  // [] not array() (§10)

        // ── Strings ──────────────────────────────────────────────────────────
        'single_quote'                  => true,    // prefer single quotes (§6)

        // ── PHP syntax ───────────────────────────────────────────────────────
        'no_closing_tag'                => true,    // no ?> at end of pure PHP files (§1)
        'declare_strict_types'          => false,   // do not auto-add — must be added intentionally
        'single_import_per_statement'   => true,
        'no_unused_imports'             => true,
        'ordered_imports'               => ['sort_algorithm' => 'alpha'],

        // ── Operators & spacing ───────────────────────────────────────────────
        'binary_operator_spaces'        => ['default' => 'single_space'],
        'unary_operator_spaces'         => true,
        'not_operator_with_successor_space' => false,

        // ── Comments ─────────────────────────────────────────────────────────
        'no_empty_comment'              => true,
        'no_empty_phpdoc'               => true,
        'phpdoc_trim'                   => true,
        'single_line_comment_style'     => ['comment_types' => ['hash']],  // # → //
    ])
    ->setFinder($finder);
