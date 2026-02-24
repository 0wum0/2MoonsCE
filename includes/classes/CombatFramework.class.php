<?php

declare(strict_types=1);

/**
 * CombatFramework – Plugin System v1.2 Combat Hook Extension
 *
 * Thin wrapper around the core calculateAttack() function.
 * Provides two mandatory hook points via HookManager:
 *
 *   combat.pre_calculate  (filter)
 *     Receives and returns the combat context array before the engine runs.
 *     Context keys:
 *       'attackers'    – attacker fleet array (by reference semantics via copy)
 *       'defenders'    – defender fleet array (by reference semantics via copy)
 *       'fleetTF'      – attacker debris % (Fleet_Cdr config)
 *       'defTF'        – defender debris % (Defs_Cdr config)
 *       'meta'         – arbitrary plugin data (initially [])
 *
 *   combat.post_calculate (filter)
 *     Receives and returns the combat result array after the engine runs.
 *     Result keys (from calculateAttack return value):
 *       'won'          – 'a' (attacker), 'r' (defender), 'w' (draw)
 *       'debris'       – ['attacker'=>[901,902], 'defender'=>[901,902]]
 *       'rw'           – round-by-round data array
 *       'unitLost'     – ['attacker'=>int, 'defender'=>int]
 *       'meta'         – arbitrary plugin data (initially [])
 *     Second argument $context contains ['ctx' => pre_calculate context].
 *
 * Without any plugins registered:
 *   - applyFilters() is a no-op (returns value unchanged)
 *   - Combat result is byte-for-byte identical to calling calculateAttack() directly
 *
 * Usage (replaces direct calculateAttack() call):
 *   $combatResult = CombatFramework::run($fleetAttack, $fleetDefend, $fleetIntoDebris, $defIntoDebris);
 */
if (class_exists('CombatFramework', false)) {
    return;
}

class CombatFramework
{
    /**
     * Run the combat engine with pre/post hook points.
     *
     * @param array $attackers    Attacker fleet array (passed by reference to calculateAttack)
     * @param array $defenders    Defender fleet array (passed by reference to calculateAttack)
     * @param float $fleetTF      Fleet debris percentage (Fleet_Cdr)
     * @param float $defTF        Defense debris percentage (Defs_Cdr)
     * @return array              Combat result array identical to calculateAttack() return value,
     *                            plus optional 'meta' key added by plugins.
     */
    public static function run(array &$attackers, array &$defenders, float $fleetTF, float $defTF): array
    {
        $hook = HookManager::get();

        // ── Hook 1: combat.pre_calculate ─────────────────────────────────────
        // Plugins may inspect or modify the input context.
        // We pass copies so plugins cannot accidentally corrupt the by-ref arrays
        // before we hand them to calculateAttack(); we apply any changes back after.
        $ctx = [
            'attackers' => $attackers,
            'defenders' => $defenders,
            'fleetTF'   => $fleetTF,
            'defTF'     => $defTF,
            'meta'      => [],
        ];

        $ctx = $hook->applyFilters('combat.pre_calculate', $ctx);

        // Apply any plugin modifications back to the by-ref arrays
        if (isset($ctx['attackers']) && is_array($ctx['attackers'])) {
            $attackers = $ctx['attackers'];
        }
        if (isset($ctx['defenders']) && is_array($ctx['defenders'])) {
            $defenders = $ctx['defenders'];
        }
        $fleetTF = isset($ctx['fleetTF']) ? (float)$ctx['fleetTF'] : $fleetTF;
        $defTF   = isset($ctx['defTF'])   ? (float)$ctx['defTF']   : $defTF;

        // ── Core engine (unchanged) ───────────────────────────────────────────
        require_once 'includes/classes/missions/functions/calculateAttack.php';
        $result = calculateAttack($attackers, $defenders, $fleetTF, $defTF);

        // Attach empty meta so plugins can add data in post hook
        $result['meta'] = [];

        // ── Hook 2: combat.post_calculate ────────────────────────────────────
        // Plugins may inspect or annotate the result.
        // They must NOT change 'won', 'debris', 'rw', 'unitLost' to preserve
        // game integrity — but the framework does not enforce this; it is a
        // plugin author responsibility.
        $result = $hook->applyFilters('combat.post_calculate', $result, ['ctx' => $ctx]);

        return $result;
    }
}
