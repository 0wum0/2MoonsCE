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

function calculateMIPAttack($TargetDefTech, $OwnerAttTech, $missiles, $targetDefensive, $firstTarget, $defenseMissles)
{
	global $pricelist, $CombatCaps;
	
	$destroyShips		= array();
	$countMissles 		= $missiles - $defenseMissles;
	
	if($countMissles == 0)
	{
		return $destroyShips;
	}

	$totalAttack 		= $countMissles * $CombatCaps[503]['attack'] * (1 +  0.1 * $OwnerAttTech);
	
	// Select primary target, if exists
	if(isset($targetDefensive[$firstTarget]))
	{
		$firstTargetData	= array($firstTarget => $targetDefensive[$firstTarget]);
		unset($targetDefensive[$firstTarget]);
		$targetDefensive	= $firstTargetData + $targetDefensive;
	}
	
	foreach($targetDefensive as $element => $count)
	{
		if($element == 0)
		{
			throw new Exception("Unknown error. Please report this error on tracker.2moons.cc. Debuginforations:<br><br>".serialize(array($TargetDefTech, $OwnerAttTech, $missiles, $targetDefensive, $firstTarget, $defenseMissles)));
		}
		$elementStructurePoints = ($pricelist[$element]['cost'][901] + $pricelist[$element]['cost'][902]) * (1 + 0.1 * $TargetDefTech) / 10;
		$destroyCount           = floor($totalAttack / $elementStructurePoints);
		$destroyCount           = min($destroyCount, $count);
		$totalAttack  	       -= $destroyCount * $elementStructurePoints;
		
		$destroyShips[$element]	= $destroyCount;
		if($totalAttack <= 0)
		{
			return $destroyShips;
		}
	}
		
	return $destroyShips;
}