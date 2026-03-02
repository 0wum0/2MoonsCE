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

function calculateMIPAttack(
	int $TargetDefTech,
	int $OwnerAttTech,
	int $missiles,
	array $targetDefensive,
	int $firstTarget,
	int $defenseMissles
): array {
	global $pricelist, $CombatCaps;

	$destroyShips = [];
	$countMissles = $missiles - $defenseMissles;

	if ($countMissles <= 0) {
		return $destroyShips;
	}

	// Both tech values are raw levels; convert to multipliers matching the main engine
	$attMultiplier = 1.0 + 0.1 * $OwnerAttTech;
	$defMultiplier = 1.0 + 0.1 * $TargetDefTech;

	$missileAttack = (float)($CombatCaps[503]['attack'] ?? 0);
	$totalAttack   = $countMissles * $missileAttack * $attMultiplier;

	// Bring the primary target to the front of the queue
	if (isset($targetDefensive[$firstTarget])) {
		$firstTargetData = [$firstTarget => $targetDefensive[$firstTarget]];
		unset($targetDefensive[$firstTarget]);
		$targetDefensive = $firstTargetData + $targetDefensive;
	}

	foreach ($targetDefensive as $element => $count) {
		if (!isset($pricelist[$element])) {
			continue;
		}

		// Structure points per unit scaled by defender shield tech multiplier
		$structPerUnit = ((float)($pricelist[$element]['cost'][901] ?? 0)
		               + (float)($pricelist[$element]['cost'][902] ?? 0))
		               * $defMultiplier / 10.0;

		if ($structPerUnit <= 0.0) {
			continue;
		}

		$destroyCount = (int)min(floor($totalAttack / $structPerUnit), $count);
		$totalAttack -= $destroyCount * $structPerUnit;

		if ($destroyCount > 0) {
			$destroyShips[$element] = $destroyCount;
		}

		if ($totalAttack <= 0.0) {
			return $destroyShips;
		}
	}

	return $destroyShips;
}