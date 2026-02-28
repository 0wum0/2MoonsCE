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
class GalaxyMarkerRegistry
{
    private static ?GalaxyMarkerRegistry $instance = null;

    /** @var array<int, array<string,mixed>> */
    private array $markers = [];

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
     * Push a marker into the runtime registry.
     *
     * Expected keys: galaxy, system, position, type, icon, color, tooltip, expires_at
     * All keys are optional with sensible defaults.
     *
     * @param array<string,mixed> $markerData
     */
    public function push(array $markerData): void
    {
        $this->markers[] = [
            'galaxy'     => (int)($markerData['galaxy']     ?? 1),
            'system'     => (int)($markerData['system']     ?? 1),
            'position'   => (int)($markerData['position']   ?? 1),
            'type'       => (string)($markerData['type']    ?? 'info'),
            'icon'       => (string)($markerData['icon']    ?? 'fa-map-marker-alt'),
            'color'      => (string)($markerData['color']   ?? '#38bdf8'),
            'tooltip'    => (string)($markerData['tooltip'] ?? ''),
            'expires_at' => (int)($markerData['expires_at'] ?? 0),
            'runtime'    => true,
        ];
    }

    /**
     * Return all runtime markers for this request.
     * @return array<int, array<string,mixed>>
     */
    public function all(): array
    {
        return $this->markers;
    }

    public function clear(): void
    {
        $this->markers = [];
    }
}
