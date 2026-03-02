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
