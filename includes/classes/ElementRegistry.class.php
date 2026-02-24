<?php

declare(strict_types=1);

/**
 * ElementRegistry – Plugin System v1.2 "Dynamic Element Registry"
 *
 * Central in-memory registry for game elements (buildings, tech, fleet, defense).
 * Bootstraps from the legacy cache arrays, allows plugins to register new elements
 * via registerElements(), then exports updated legacy arrays so all existing core
 * code continues to work unchanged.
 *
 * Load order (wired in common.php):
 *   1. vars.php / VarsBuildCache  → legacy arrays available
 *   2. PluginManager::loadActivePlugins() → plugin.php files included, hooks registered
 *   3. ElementRegistry::bootFromLegacyArrays() → registry built from cache
 *   4. PluginManager::dispatchRegisterElements() → plugins add new elements
 *   5. HookManager filters (game.pricelist etc.) → existing v1.1 hooks still run
 *   6. ElementRegistry::exportLegacy*() → updated arrays written back to globals
 *
 * Element definition array (all keys optional except id/type):
 * [
 *   'id'          => int,               // required, unique element ID
 *   'type'        => string,            // required: building|tech|fleet|defense|missile|officier|dmfunc
 *   'nameKey'     => string,            // planet/user column name (e.g. 'my_building')
 *   'cost'        => [901=>M,902=>C,903=>D,911=>E,921=>DM],
 *   'factor'      => float,             // cost scaling factor per level (buildings/tech)
 *   'max'         => int,               // max level (0 = unlimited)
 *   'requirements'=> [elementId=>level, ...],
 *   'onPlanetType'=> [1,3],             // planet types this building appears on (buildings only)
 *   'stats'       => [                  // optional combat/production stats
 *     'attack'    => int,
 *     'shield'    => int,
 *     'speed'     => int,
 *     'capacity'  => int,
 *     'consumption'=> int,
 *   ],
 *   'metadata'    => array,             // arbitrary plugin data
 * ]
 *
 * Query API note: use ElementRegistry::get() to retrieve the singleton instance,
 * then call ->find(int $id) to look up an element by ID.
 */
if (class_exists('ElementRegistry', false)) {
    return;
}

class ElementRegistry
{
    private static ?ElementRegistry $instance = null;

    /** @var array<int, array<string, mixed>> */
    private array $elements = [];

    /** @var bool Whether bootFromLegacyArrays() has been called */
    private bool $booted = false;

    private function __construct() {}
    private function __clone() {}

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Boot ─────────────────────────────────────────────────────────────────

    /**
     * Populate the registry from the existing legacy arrays produced by VarsBuildCache.
     * Safe to call multiple times; subsequent calls are no-ops.
     *
     * @param array<int, string>            $resource      $resource[id] = 'column_name'
     * @param array<int, array>             $pricelist     $pricelist[id] = [cost=>[...], factor, max, ...]
     * @param array<string, array>          $reslist       $reslist['build'|'tech'|'fleet'|...]
     * @param array<int, array>             $requeriments  $requeriments[id][reqId] = level
     * @param array<string, mixed>          $additional    optional extra data (CombatCaps, ProdGrid)
     */
    public function bootFromLegacyArrays(
        array $resource,
        array $pricelist,
        array $reslist,
        array $requeriments = [],
        array $additional = []
    ): void {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        // Build a reverse map: elementId → type
        $typeMap = [];
        foreach (['build', 'tech', 'fleet', 'defense', 'missile', 'officier', 'dmfunc'] as $type) {
            if (!empty($reslist[$type]) && is_array($reslist[$type])) {
                foreach ($reslist[$type] as $id) {
                    $typeMap[(int)$id] = $type;
                }
            }
        }

        // Build onPlanetType reverse map for buildings
        $planetTypeMap = [];
        if (!empty($reslist['allow']) && is_array($reslist['allow'])) {
            foreach ($reslist['allow'] as $planetType => $ids) {
                foreach ($ids as $id) {
                    $planetTypeMap[(int)$id][] = (int)$planetType;
                }
            }
        }

        foreach ($pricelist as $id => $pl) {
            $id = (int)$id;
            $type = $typeMap[$id] ?? 'unknown';

            $def = [
                'id'           => $id,
                'type'         => $type,
                'nameKey'      => $resource[$id] ?? null,
                'cost'         => $pl['cost'] ?? [],
                'factor'       => $pl['factor'] ?? 1,
                'max'          => $pl['max'] ?? 0,
                'requirements' => $requeriments[$id] ?? [],
                'onPlanetType' => $planetTypeMap[$id] ?? [],
                'stats'        => [
                    'attack'       => $additional['CombatCaps'][$id]['attack'] ?? 0,
                    'shield'       => $additional['CombatCaps'][$id]['shield'] ?? 0,
                    'speed'        => $pl['speed'] ?? 0,
                    'speed2'       => $pl['speed2'] ?? 0,
                    'capacity'     => $pl['capacity'] ?? 0,
                    'consumption'  => $pl['consumption'] ?? 0,
                    'consumption2' => $pl['consumption2'] ?? 0,
                    'tech'         => $pl['tech'] ?? 0,
                    'time'         => $pl['time'] ?? 0,
                    'bonus'        => $pl['bonus'] ?? [],
                    'rapidfire'    => $additional['CombatCaps'][$id]['sd'] ?? [],
                ],
                'production'   => $additional['ProdGrid'][$id]['production'] ?? [],
                'storage'      => $additional['ProdGrid'][$id]['storage'] ?? [],
                'metadata'     => [],
                '_fromLegacy'  => true,
            ];

            $this->elements[$id] = $def;
        }
    }

    // ── Registration ─────────────────────────────────────────────────────────

    /**
     * Register a single element definition.
     * If an element with the same id already exists it is merged (plugin data wins).
     *
     * @param array<string, mixed> $elementDef
     */
    public function register(array $elementDef): void
    {
        $id = isset($elementDef['id']) ? (int)$elementDef['id'] : null;
        if ($id === null) {
            error_log('[ElementRegistry] register() called without id');
            return;
        }

        if (!isset($elementDef['type']) || !is_string($elementDef['type'])) {
            error_log('[ElementRegistry] register() called without type for id=' . $id);
            return;
        }

        if (isset($this->elements[$id])) {
            // Merge: plugin data wins over legacy for explicitly provided keys
            $existing = $this->elements[$id];
            $merged   = array_merge($existing, $elementDef);
            // Deep-merge cost and requirements
            if (isset($elementDef['cost']) && is_array($elementDef['cost'])) {
                $merged['cost'] = array_merge($existing['cost'] ?? [], $elementDef['cost']);
            }
            if (isset($elementDef['requirements']) && is_array($elementDef['requirements'])) {
                $merged['requirements'] = array_merge($existing['requirements'] ?? [], $elementDef['requirements']);
            }
            if (isset($elementDef['metadata']) && is_array($elementDef['metadata'])) {
                $merged['metadata'] = array_merge($existing['metadata'] ?? [], $elementDef['metadata']);
            }
            $this->elements[$id] = $merged;
        } else {
            // New element – apply defaults
            $def = array_merge([
                'nameKey'      => null,
                'cost'         => [],
                'factor'       => 1,
                'max'          => 0,
                'requirements' => [],
                'onPlanetType' => [1, 3],
                'stats'        => [],
                'production'   => [],
                'storage'      => [],
                'metadata'     => [],
                '_fromLegacy'  => false,
            ], $elementDef);
            $def['id'] = $id;
            $this->elements[$id] = $def;
        }
    }

    /**
     * Register multiple element definitions at once.
     *
     * @param array<int, array<string, mixed>> $elements
     */
    public function registerMany(array $elements): void
    {
        foreach ($elements as $def) {
            $this->register($def);
        }
    }

    // ── Query ────────────────────────────────────────────────────────────────

    public function find(int $id): ?array
    {
        return $this->elements[$id] ?? null;
    }

    public function has(int $id): bool
    {
        return isset($this->elements[$id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->elements;
    }

    /**
     * @param string $type building|tech|fleet|defense|missile|officier|dmfunc
     * @return array<int, array<string, mixed>>
     */
    public function byType(string $type): array
    {
        $result = [];
        foreach ($this->elements as $id => $def) {
            if (($def['type'] ?? '') === $type) {
                $result[$id] = $def;
            }
        }
        return $result;
    }

    // ── Export (legacy array reconstruction) ─────────────────────────────────

    /**
     * Rebuild $pricelist from the registry.
     * Includes both legacy elements and newly registered plugin elements.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportLegacyPricelist(): array
    {
        $pricelist = [];
        foreach ($this->elements as $id => $def) {
            $stats = $def['stats'] ?? [];
            $bonus = $stats['bonus'] ?? [];

            // Ensure bonus sub-keys exist (required by BuildFunctions/statbuilder)
            $bonusKeys = [
                'Attack','Defensive','Shield','BuildTime','ResearchTime',
                'ShipTime','DefensiveTime','Resource','Energy','ResourceStorage',
                'ShipStorage','FlyTime','FleetSlots','Planets','SpyPower',
                'Expedition','GateCoolTime','MoreFound',
            ];
            foreach ($bonusKeys as $bk) {
                if (!isset($bonus[$bk])) {
                    $bonus[$bk] = [0, 0];
                }
            }

            $pricelist[$id] = [
                'cost'         => array_merge(
                    [901 => 0, 902 => 0, 903 => 0, 911 => 0, 921 => 0],
                    $def['cost'] ?? []
                ),
                'factor'       => $def['factor'] ?? 1,
                'max'          => $def['max'] ?? 0,
                'consumption'  => $stats['consumption'] ?? 0,
                'consumption2' => $stats['consumption2'] ?? 0,
                'speed'        => $stats['speed'] ?? 0,
                'speed2'       => $stats['speed2'] ?? 0,
                'capacity'     => $stats['capacity'] ?? 0,
                'tech'         => $stats['tech'] ?? 0,
                'time'         => $stats['time'] ?? 0,
                'bonus'        => $bonus,
            ];
        }
        return $pricelist;
    }

    /**
     * Rebuild $reslist from the registry.
     * Preserves existing reslist sub-arrays and appends new plugin elements.
     *
     * @param array<string, mixed> $existingReslist  The current $reslist (from cache)
     * @return array<string, mixed>
     */
    public function exportLegacyReslist(array $existingReslist = []): array
    {
        $reslist = $existingReslist;

        // Ensure all sub-keys exist
        foreach (['prod','storage','bonus','one','build','tech','fleet','defense','missile','officier','dmfunc'] as $k) {
            if (!isset($reslist[$k])) {
                $reslist[$k] = [];
            }
        }
        if (!isset($reslist['allow'][1])) {
            $reslist['allow'][1] = [];
        }
        if (!isset($reslist['allow'][3])) {
            $reslist['allow'][3] = [];
        }

        // Only process elements NOT from legacy (new plugin elements)
        foreach ($this->elements as $id => $def) {
            if (!empty($def['_fromLegacy'])) {
                continue;
            }

            $type = $def['type'] ?? '';

            // Buildings need special handling (build + allow[]); others use typeListKey directly
            if ($type === 'building') {
                if (!in_array($id, $reslist['build'], true)) {
                    $reslist['build'][] = $id;
                }
                $planetTypes = $def['onPlanetType'] ?? [1, 3];
                foreach ($planetTypes as $pt) {
                    $pt = (int)$pt;
                    if (!isset($reslist['allow'][$pt])) {
                        $reslist['allow'][$pt] = [];
                    }
                    if (!in_array($id, $reslist['allow'][$pt], true)) {
                        $reslist['allow'][$pt][] = $id;
                    }
                }
            } else {
                $typeListKey = $this->typeToReslistKey($type);
                if ($typeListKey !== null && !in_array($id, $reslist[$typeListKey], true)) {
                    $reslist[$typeListKey][] = $id;
                }
            }

            // Production/storage flags
            $production = $def['production'] ?? [];
            if (!empty(array_filter($production)) && !in_array($id, $reslist['prod'], true)) {
                $reslist['prod'][] = $id;
            }
            $storage = $def['storage'] ?? [];
            if (!empty(array_filter($storage)) && !in_array($id, $reslist['storage'], true)) {
                $reslist['storage'][] = $id;
            }
        }

        return $reslist;
    }

    /**
     * Rebuild $resource (id → column name) from the registry.
     *
     * @param array<int, string> $existingResource  The current $resource array
     * @return array<int, string>
     */
    public function exportLegacyResourceMap(array $existingResource = []): array
    {
        $resource = $existingResource;
        foreach ($this->elements as $id => $def) {
            if (!empty($def['_fromLegacy'])) {
                continue;
            }
            if (!empty($def['nameKey']) && !isset($resource[$id])) {
                $resource[$id] = (string)$def['nameKey'];
            }
        }
        return $resource;
    }

    /**
     * Rebuild $requeriments from the registry.
     *
     * @param array<int, array<int, int>> $existingRequeriments
     * @return array<int, array<int, int>>
     */
    public function exportLegacyRequirements(array $existingRequeriments = []): array
    {
        $requeriments = $existingRequeriments;
        foreach ($this->elements as $id => $def) {
            if (!empty($def['_fromLegacy'])) {
                continue;
            }
            if (!empty($def['requirements']) && is_array($def['requirements'])) {
                $requeriments[$id] = $def['requirements'];
            }
        }
        return $requeriments;
    }

    /**
     * Rebuild $CombatCaps from the registry.
     *
     * @param array<int, array<string, mixed>> $existingCombatCaps
     * @return array<int, array<string, mixed>>
     */
    public function exportLegacyCombatCaps(array $existingCombatCaps = []): array
    {
        $caps = $existingCombatCaps;
        foreach ($this->elements as $id => $def) {
            if (!empty($def['_fromLegacy'])) {
                continue;
            }
            $stats = $def['stats'] ?? [];
            $caps[$id] = [
                'attack' => (int)($stats['attack'] ?? 0),
                'shield' => (int)($stats['shield'] ?? 0),
                'sd'     => $stats['rapidfire'] ?? [],
            ];
        }
        return $caps;
    }

    /**
     * Rebuild $ProdGrid from the registry.
     *
     * @param array<int, array<string, array>> $existingProdGrid
     * @return array<int, array<string, array>>
     */
    public function exportLegacyProdGrid(array $existingProdGrid = []): array
    {
        $grid = $existingProdGrid;
        foreach ($this->elements as $id => $def) {
            if (!empty($def['_fromLegacy'])) {
                continue;
            }
            $grid[$id] = [
                'production' => array_merge([901 => 0, 902 => 0, 903 => 0, 911 => 0], $def['production'] ?? []),
                'storage'    => array_merge([901 => 0, 902 => 0, 903 => 0], $def['storage'] ?? []),
            ];
        }
        return $grid;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function typeToReslistKey(string $type): ?string
    {
        return match($type) {
            'building'  => 'build',
            'tech'      => 'tech',
            'fleet'     => 'fleet',
            'defense'   => 'defense',
            'missile'   => 'missile',
            'officier'  => 'officier',
            'dmfunc'    => 'dmfunc',
            default     => null,
        };
    }

    /**
     * Reset the singleton (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
