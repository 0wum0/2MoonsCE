<?php
/**
 * 2MoonsCE Admin Network Hub — Configuration
 *
 * Copy this file to config.php and set your values.
 * The master key is used ONLY to register new instances.
 * Keep it secret — do not share it publicly.
 */

declare(strict_types=1);

// Master key for registering new instances (change this to something random!)
define('HUB_MASTER_KEY', 'CHANGE_ME_TO_A_RANDOM_SECRET_KEY_32CHARS');

// Path to the SQLite database file (must be writable by the web server)
define('HUB_DB_PATH', __DIR__ . '/data/hub.sqlite');

// Maximum messages to keep in DB (older ones are pruned automatically)
define('HUB_MAX_MESSAGES', 5000);

// Maximum message age in seconds before pruning (default: 30 days)
define('HUB_MAX_MESSAGE_AGE', 60 * 60 * 24 * 30);
