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
class AssetRegistry
{
    private static ?AssetRegistry $instance = null;

    /** @var array<int, array{pluginId: string, path: string, pages: string[]}> */
    private array $cssAssets = [];

    /** @var array<int, array{pluginId: string, path: string, pages: string[]}> */
    private array $jsAssets = [];

    private function __construct() {}
    private function __clone() {}

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a CSS file for a plugin.
     *
     * @param string   $pluginId  Plugin identifier
     * @param string   $path      URL path relative to ROOT (e.g. "plugins/sm-test/assets/style.css")
     * @param string[] $pages     Limit to these page names; empty = all pages
     */
    public function registerCss(string $pluginId, string $path, array $pages = []): void
    {
        $this->cssAssets[] = [
            'pluginId' => $pluginId,
            'path'     => $path,
            'pages'    => $pages,
        ];
    }

    /**
     * Register a JS file for a plugin.
     *
     * @param string   $pluginId  Plugin identifier
     * @param string   $path      URL path relative to ROOT
     * @param string[] $pages     Limit to these page names; empty = all pages
     */
    public function registerJs(string $pluginId, string $path, array $pages = []): void
    {
        $this->jsAssets[] = [
            'pluginId' => $pluginId,
            'path'     => $path,
            'pages'    => $pages,
        ];
    }

    /**
     * Return all CSS paths applicable for the given page.
     *
     * @return string[]
     */
    public function getCssForPage(string $currentPage): array
    {
        return $this->filterAssets($this->cssAssets, $currentPage);
    }

    /**
     * Return all JS paths applicable for the given page.
     *
     * @return string[]
     */
    public function getJsForPage(string $currentPage): array
    {
        return $this->filterAssets($this->jsAssets, $currentPage);
    }

    // ── Debug / Introspection ─────────────────────────────────────────────────

    /**
     * Return all registered CSS assets (unfiltered) for debug inspection.
     *
     * @return array<int, array{pluginId: string, path: string, pages: string[]}>
     */
    public function getAllCssAssets(): array
    {
        return $this->cssAssets;
    }

    /**
     * Return all registered JS assets (unfiltered) for debug inspection.
     *
     * @return array<int, array{pluginId: string, path: string, pages: string[]}>
     */
    public function getAllJsAssets(): array
    {
        return $this->jsAssets;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * @param array<int, array{pluginId: string, path: string, pages: string[]}> $assets
     * @return string[]
     */
    private function filterAssets(array $assets, string $currentPage): array
    {
        $result = [];
        foreach ($assets as $asset) {
            if (empty($asset['pages']) || in_array($currentPage, $asset['pages'], true)) {
                $result[] = $asset['path'];
            }
        }
        return $result;
    }
}
