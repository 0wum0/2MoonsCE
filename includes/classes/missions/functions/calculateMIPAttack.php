<?php

declare(strict_types=1);

/**
 *  SmartMoons / 2Moons Community Edition (2MoonsCE)
 *
 *  Based on the original 2Moons project:
 *
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
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

/**
 * calculateMIPAttack – Interplanetary Missile (MIP) damage calculation.
 *
 * FIXES vs previous version:
 *
 *  1. CORRECTNESS: $defMultiplier was named "TargetDefTech" in the
 *     parameter, but it is the defender's SHIELD tech that determines how
 *     much structural HP a defence structure has (HP = cost/10 * shield_tech).
 *     The main engine (calculateAttack) uses shield_tech for structure HP.
 *     MIP bypasses shields entirely and deals direct structural damage, so
 *     the defender's shield_tech is the correct multiplier.
 *     Parameter renamed from $TargetDefTech → $TargetShieldTech for clarity,
 *     but the function signature is BACKWARD COMPATIBLE because the caller
 *     (MissionCaseMIP) passes $targetData["shield_tech"] which was already
 *     the shield_tech column.  The rename only affects this file.
 *
 *  2. SAFETY: Added isset() guard for $pricelist[$element] before accessing
 *     cost sub-keys – avoids undefined-index notice on unknown element IDs.
 *
 *  3. SAFETY: $countMissles (typo preserved for backward compat) clamped to
 *     int via (int) cast before use in multiplication.
 *
 *  4. SAFETY: $totalAttack can underflow below 0.0 from floating-point.
 *     Added explicit early-return when $totalAttack <= 0.0 inside the loop
 *     (was already present but without the float guard).
 *
 * @param int   $TargetShieldTech  Defender's shield tech level (raw level, not multiplier)
 * @param int   $OwnerAttTech      Attacker's military tech level (raw level)
 * @param int   $missiles          Number of missiles fired
 * @param array $targetDefensive   [elementID => count] of target's defensive units
 * @param int   $firstTarget       Element ID of the priority target
 * @param int   $defenseMissles    Number of interception missiles (typo preserved)
 * @return array                   [elementID => destroyedCount]
 */
function calculateMIPAttack(
    int   $TargetShieldTech,
    int   $OwnerAttTech,
    int   $missiles,
    array $targetDefensive,
    int   $firstTarget,
    int   $defenseMissles
): array {
    global $pricelist, $CombatCaps;

    $destroyShips = [];

    // Net missiles after interception
    $countMissles = (int)$missiles - (int)$defenseMissles;

    if ($countMissles <= 0) {
        return $destroyShips;
    }

    // Both tech values are raw levels; convert to multipliers matching the main engine.
    // Attacker military tech → attack multiplier
    // Defender shield tech   → structure HP multiplier (HP = cost/10 * shield_mul)
    $attMultiplier = 1.0 + 0.1 * (float)$OwnerAttTech;
    $defMultiplier = 1.0 + 0.1 * (float)$TargetShieldTech;

    $missileAttack = (float)($CombatCaps[503]['attack'] ?? 0);
    $totalAttack   = (float)$countMissles * $missileAttack * $attMultiplier;

    if ($totalAttack <= 0.0) {
        return $destroyShips;
    }

    // Bring the primary target to the front of the queue
    if (isset($targetDefensive[$firstTarget])) {
        $firstTargetData  = [$firstTarget => $targetDefensive[$firstTarget]];
        unset($targetDefensive[$firstTarget]);
        $targetDefensive  = $firstTargetData + $targetDefensive;
    }

    foreach ($targetDefensive as $element => $count) {
        // SAFETY: unknown element in pricelist
        if (!isset($pricelist[$element])) {
            continue;
        }

        $metalCost   = (float)($pricelist[$element]['cost'][901] ?? 0);
        $crystalCost = (float)($pricelist[$element]['cost'][902] ?? 0);

        // Structure points per unit (matches calculateAttack's unitHP formula)
        $structPerUnit = ($metalCost + $crystalCost) * $defMultiplier / 10.0;

        if ($structPerUnit <= 0.0) {
            continue;
        }

        $destroyCount  = (int)min(floor($totalAttack / $structPerUnit), (int)$count);
        $totalAttack  -= (float)$destroyCount * $structPerUnit;

        if ($destroyCount > 0) {
            $destroyShips[$element] = $destroyCount;
        }

        if ($totalAttack <= 0.0) {
            return $destroyShips;
        }
    }

    return $destroyShips;
}
