<?php

declare(strict_types=1);

/**
 * AssetRegistry – Plugin System v1
 * Manages CSS/JS assets registered by plugins with optional page-scoping.
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
