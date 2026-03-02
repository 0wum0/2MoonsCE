<?php

declare(strict_types=1);

/**
 *	SmartMoons / 2Moons Community Edition (2MoonsCE)
 * 
 *	Based on the original 2Moons project:
 *	
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * Modernization, PHP 8.3/8.4 compatibility, Twig Migration (Smarty removed)
 * Refactoring and feature extensions:
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 * @link https://github.com/0wum0/2MoonsCE
 * @eMail info.browsergame@gmail.com
 * 
 * Licensed under the MIT License.
 * See LICENSE for details.
 * @visit http://makeit.uno/
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
