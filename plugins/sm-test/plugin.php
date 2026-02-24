<?php

declare(strict_types=1);

/**
 * sm-test – SmartMoons Test Plugin
 * Bootstrap: registers hooks and assets.
 */

// Register CSS and JS assets (served for all game pages)
AssetRegistry::get()->registerCss('sm-test', 'plugins/sm-test/assets/sm-test.css');
AssetRegistry::get()->registerJs('sm-test', 'plugins/sm-test/assets/sm-test.js');

// Register a content_top action hook: renders a dismissible info banner
HookManager::get()->addAction('content_top', function (array $context): string {
    $bannerText  = PluginManager::lang('sm-test', 'banner_text');
    $bannerClose = PluginManager::lang('sm-test', 'banner_close');

    return '<div id="sm-test-banner" class="sm-test-banner">'
        . '<span class="sm-test-banner__text">' . $bannerText . '</span>'
        . '<button id="sm-test-close" class="sm-test-banner__close">' . $bannerClose . '</button>'
        . '</div>';
}, 10);

// Register a footer_end action hook: small debug note (only visible in debug mode)
HookManager::get()->addAction('footer_end', function (array $context): string {
    if (!defined('MODE')) {
        return '';
    }
    return '<!-- sm-test plugin loaded | mode=' . htmlspecialchars(MODE) . ' -->';
}, 99);
