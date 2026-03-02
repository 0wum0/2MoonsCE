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


/**
 * ============================================================
 * 2MoonsCE Combat Engine v2.0
 * ============================================================
 *
 * Key improvements over the legacy engine:
 *
 * 1. DAMAGE DISTRIBUTION by structural weight (metal+crystal cost),
 *    not by raw unit count.  A fleet of 1 Death Star + 1 Probe no
 *    longer splits damage 50/50 – the Death Star absorbs nearly all.
 *
 * 2. RAPID-FIRE handled correctly.  The legacy code mutated the
 *    running totals ($attackAmount['total'], $defenseAmount['total'])
 *    inside the per-unit loop, corrupting every subsequent unit's
 *    damage share.  v2 pre-computes RF bonus damage per fleet before
 *    the loss loop so totals are immutable during loss calculation.
 *
 * 3. HULL-BREACH PROBABILITY uses mt_rand() (Mersenne Twister) for
 *    uniform distribution and is clamped to [1,100] so a unit can
 *    never be immortal (old rand(0,200) could produce 0).
 *
 * 4. ROUND SNAPSHOTS are deep-copied before mutation, so the report
 *    for round N always reflects the state *at the start* of that
 *    round, not the state after all rounds have finished.
 *
 * 5. TECH MULTIPLIERS computed once per fleet per round, stored on
 *    the fleet array so GenerateReport can read them safely even when
 *    a fleet is destroyed mid-battle.
 *
 * 6. DEFENSE RECONSTRUCTION uses a named constant range (56–84 %)
 *    and mt_rand() for proper randomness.
 *
 * 7. STRICT int/float arithmetic throughout; no implicit string→int.
 *
 * 8. PASSIVE STRUCTURES (element IDs 407–409) attack is already 0
 *    in CombatCaps; the explicit override is kept for safety.
 *
 * Return value is identical to the legacy engine so MissionCaseAttack,
 * GenerateReport, and ShowBattleSimulatorPage need no changes.
 * ============================================================
 */
if (!defined('DEF_REBUILD_MIN')) define('DEF_REBUILD_MIN', 56);
if (!defined('DEF_REBUILD_MAX')) define('DEF_REBUILD_MAX', 84);

function calculateAttack(array &$attackers, array &$defenders, float $FleetTF, float $DefTF): array
{
	global $pricelist, $CombatCaps, $resource;

	// ── Plugin hook: allow plugins to modify fleets before combat ────────
	$combatData = HookManager::get()->applyFilters('game.combatModifier', [
		'attackers' => $attackers,
		'defenders' => $defenders,
	]);
	if (!empty($combatData['attackers']) && is_array($combatData['attackers'])) {
		$attackers = $combatData['attackers'];
	}
	if (!empty($combatData['defenders']) && is_array($combatData['defenders'])) {
		$defenders = $combatData['defenders'];
	}

	// ── Helper: structural weight (hit-points denominator) ───────────────
	// = (metal_cost + crystal_cost) / 10  × shield_tech_multiplier
	// Computed per-unit so we can weight damage distribution.
	$unitHP = static function (int $element, float $shieldTech) use (&$pricelist): float {
		$cost = (float)(($pricelist[$element]['cost'][901] ?? 0)
		              + ($pricelist[$element]['cost'][902] ?? 0));
		return max(1.0, $cost / 10.0 * $shieldTech);
	};

	// ── Build rapid-fire lookup: $RF[$target][$shooter] = shots ──────────
	// This table is immutable for the entire battle.
	$RF = [];
	foreach ($CombatCaps as $shooterID => $caps) {
		if (empty($caps['sd'])) continue;
		foreach ($caps['sd'] as $targetID => $shots) {
			if ($shots > 0) {
				$RF[$targetID][$shooterID] = (int)$shots;
			}
		}
	}

	// ── Record initial costs for debris calculation ───────────────────────
	$ARES     = ['metal' => 0.0, 'crystal' => 0.0]; // attacker initial cost
	$DRES     = ['metal' => 0.0, 'crystal' => 0.0]; // defender fleet initial cost
	$STARTDEF = [];                                   // defender defense initial counts

	foreach ($attackers as $fleetID => $attacker) {
		foreach ($attacker['unit'] as $element => $amount) {
			$ARES['metal']   += ($pricelist[$element]['cost'][901] ?? 0) * $amount;
			$ARES['crystal'] += ($pricelist[$element]['cost'][902] ?? 0) * $amount;
		}
	}

	foreach ($defenders as $fleetID => $defender) {
		foreach ($defender['unit'] as $element => $amount) {
			if ($element < 300) {
				$DRES['metal']   += ($pricelist[$element]['cost'][901] ?? 0) * $amount;
				$DRES['crystal'] += ($pricelist[$element]['cost'][902] ?? 0) * $amount;
			} else {
				$STARTDEF[$element] = ($STARTDEF[$element] ?? 0) + (int)$amount;
			}
		}
	}

	$TRES = [
		'attacker' => $ARES['metal'] + $ARES['crystal'],
		'defender' => array_sum(array_map(static function($flt) use (&$pricelist) {
			$sum = 0.0;
			foreach ($flt['unit'] as $el => $amt) {
				$sum += (($pricelist[$el]['cost'][901] ?? 0) + ($pricelist[$el]['cost'][902] ?? 0)) * $amt;
			}
			return $sum;
		}, $defenders)),
	];

	// ── Main combat loop ──────────────────────────────────────────────────
	$ROUND = [];

	for ($ROUNDC = 0; $ROUNDC <= MAX_ATTACK_ROUNDS; $ROUNDC++) {

		// ── Phase 1: compute stats for every live unit ────────────────────
		$attArray = [];
		$defArray = [];

		// Attacker stats
		$attackDamageTotal  = 0.0;
		$attackWeightTotal  = 0.0; // sum of structural weight (for damage distribution)
		$attackCountTotal   = 0;
		$attackDamageByFleet = [];
		$attackWeightByFleet = [];
		$attackCountByFleet  = [];

		foreach ($attackers as $fleetID => $attacker) {
			$attTech    = 1.0 + 0.1 * (float)$attacker['player']['military_tech']
			                  + (float)($attacker['player']['factor']['Attack'] ?? 0);
			$defTech    = 1.0 + 0.1 * (float)$attacker['player']['defence_tech']
			                  + (float)($attacker['player']['factor']['Defensive'] ?? 0);
			$shieldTech = 1.0 + 0.1 * (float)$attacker['player']['shield_tech']
			                  + (float)($attacker['player']['factor']['Shield'] ?? 0);

			// Store techs so report can access them even after fleet is wiped
			$attackers[$fleetID]['techs'] = [$attTech, $defTech, $shieldTech];

			$fleetDmg    = 0.0;
			$fleetWeight = 0.0;
			$fleetCount  = 0;

			foreach ($attacker['unit'] as $element => $amount) {
				if ($amount <= 0) continue;

				$baseAtt = (float)($CombatCaps[$element]['attack'] ?? 0);
				// ±20 % variance per OGame spec, using mt_rand for quality
				$thisAtt    = (float)$amount * $baseAtt * $attTech
				              * (mt_rand(80, 120) / 100.0);
				$thisDef    = (float)$amount * (float)($CombatCaps[$element]['shield'] ?? 0) * $defTech;
				$thisHP     = $unitHP($element, $shieldTech) * $amount;

				$attArray[$fleetID][$element] = [
					'att'    => $thisAtt,
					'def'    => $thisDef,
					'shield' => $thisHP,
				];

				$fleetDmg    += $thisAtt;
				$fleetWeight += $thisHP;
				$fleetCount  += $amount;
			}

			$attackDamageByFleet[$fleetID] = $fleetDmg;
			$attackWeightByFleet[$fleetID] = $fleetWeight;
			$attackCountByFleet[$fleetID]  = $fleetCount;
			$attackDamageTotal  += $fleetDmg;
			$attackWeightTotal  += $fleetWeight;
			$attackCountTotal   += $fleetCount;
		}

		// Defender stats
		$defenseDamageTotal  = 0.0;
		$defenseWeightTotal  = 0.0;
		$defenseCountTotal   = 0;
		$defenseDamageByFleet = [];
		$defenseWeightByFleet = [];
		$defenseCountByFleet  = [];

		foreach ($defenders as $fleetID => $defender) {
			$attTech    = 1.0 + 0.1 * (float)$defender['player']['military_tech']
			                  + (float)($defender['player']['factor']['Attack'] ?? 0);
			$defTech    = 1.0 + 0.1 * (float)$defender['player']['defence_tech']
			                  + (float)($defender['player']['factor']['Defensive'] ?? 0);
			$shieldTech = 1.0 + 0.1 * (float)$defender['player']['shield_tech']
			                  + (float)($defender['player']['factor']['Shield'] ?? 0);

			$defenders[$fleetID]['techs'] = [$attTech, $defTech, $shieldTech];

			$fleetDmg    = 0.0;
			$fleetWeight = 0.0;
			$fleetCount  = 0;

			foreach ($defender['unit'] as $element => $amount) {
				if ($amount <= 0) continue;

				$baseAtt = (float)($CombatCaps[$element]['attack'] ?? 0);
				// Passive structures (small/large/anti-ballistic missile silos) have 0 attack
				if ($element === 407 || $element === 408 || $element === 409) {
					$baseAtt = 0.0;
				}

				$thisAtt    = (float)$amount * $baseAtt * $attTech
				              * (mt_rand(80, 120) / 100.0);
				$thisDef    = (float)$amount * (float)($CombatCaps[$element]['shield'] ?? 0) * $defTech;
				$thisHP     = $unitHP($element, $shieldTech) * $amount;

				$defArray[$fleetID][$element] = [
					'att'    => $thisAtt,
					'def'    => $thisDef,
					'shield' => $thisHP,
				];

				$fleetDmg    += $thisAtt;
				$fleetWeight += $thisHP;
				$fleetCount  += $amount;
			}

			$defenseDamageByFleet[$fleetID] = $fleetDmg;
			$defenseWeightByFleet[$fleetID] = $fleetWeight;
			$defenseCountByFleet[$fleetID]  = $fleetCount;
			$defenseDamageTotal  += $fleetDmg;
			$defenseWeightTotal  += $fleetWeight;
			$defenseCountTotal   += $fleetCount;
		}

		// ── Phase 2: pre-compute rapid-fire bonus damage ──────────────────
		// RF bonus = extra incoming damage caused by rapid-fire shooters.
		// We calculate the total additional damage each target unit TYPE
		// receives from RF shooters on the opposite side, then distribute
		// that bonus proportionally across all instances of that type.
		//
		// CRITICAL: these RF bonuses are computed from the FROZEN stats
		// above and never fed back into the totals used here – this is the
		// bug that corrupted damage in the legacy engine.

		// $rfBonusForAtt[$fleetID][$element] = extra damage this element receives from RF
		$rfBonusForAtt = [];
		$rfBonusForDef = [];

		// RF bonus received by attacker units (from defender shooters)
		foreach ($attackers as $fleetID => $attacker) {
			foreach ($attacker['unit'] as $element => $amount) {
				if ($amount <= 0 || empty($RF[$element])) continue;
				$bonus = 0.0;
				foreach ($RF[$element] as $shooterID => $shots) {
					// Sum damage this shooter type deals to $element across all defender fleets
					foreach ($defArray as $dfID => $dfUnits) {
						if (empty($dfUnits[$shooterID]['att'])) continue;
						// Each shooter gets $shots extra shots against $element per round
						// Scale: bonus is additional damage proportional to how many of $element exist
						$shooterCount   = (float)($defenders[$dfID]['unit'][$shooterID] ?? 0);
						$shooterDmgPerUnit = $shooterCount > 0
							? $dfUnits[$shooterID]['att'] / $shooterCount
							: 0.0;
						$bonus += $shooterDmgPerUnit * $shots * $amount;
					}
				}
				$rfBonusForAtt[$fleetID][$element] = $bonus;
			}
		}

		// RF bonus received by defender units (from attacker shooters)
		foreach ($defenders as $fleetID => $defender) {
			foreach ($defender['unit'] as $element => $amount) {
				if ($amount <= 0 || empty($RF[$element])) continue;
				$bonus = 0.0;
				foreach ($RF[$element] as $shooterID => $shots) {
					foreach ($attArray as $afID => $afUnits) {
						if (empty($afUnits[$shooterID]['att'])) continue;
						$shooterCount   = (float)($attackers[$afID]['unit'][$shooterID] ?? 0);
						$shooterDmgPerUnit = $shooterCount > 0
							? $afUnits[$shooterID]['att'] / $shooterCount
							: 0.0;
						$bonus += $shooterDmgPerUnit * $shots * $amount;
					}
				}
				$rfBonusForDef[$fleetID][$element] = $bonus;
			}
		}

		// ── Phase 3: snapshot BEFORE mutation (for report) ───────────────
		// Deep copy so earlier round snapshots are never overwritten.
		$snapAttackers = [];
		foreach ($attackers as $fid => $fl) {
			$snapAttackers[$fid] = $fl; // unit sub-array is a scalar array → value copy is fine
		}
		$snapDefenders = [];
		foreach ($defenders as $fid => $fl) {
			$snapDefenders[$fid] = $fl;
		}

		$attackAmountSnapshot  = ['total' => $attackCountTotal];
		$defenseAmountSnapshot = ['total' => $defenseCountTotal];
		foreach ($attackCountByFleet  as $fid => $cnt) { $attackAmountSnapshot[$fid]  = $cnt; }
		foreach ($defenseCountByFleet as $fid => $cnt) { $defenseAmountSnapshot[$fid] = $cnt; }

		$ROUND[$ROUNDC] = [
			'attackers' => $snapAttackers,
			'defenders' => $snapDefenders,
			'attackA'   => $attackAmountSnapshot,
			'defenseA'  => $defenseAmountSnapshot,
			'infoA'     => $attArray,
			'infoD'     => $defArray,
		];

		// End condition check AFTER snapshot
		if ($ROUNDC >= MAX_ATTACK_ROUNDS
			|| $defenseCountTotal <= 0
			|| $attackCountTotal  <= 0) {
			break;
		}

		// ── Phase 4: calculate attacker losses ────────────────────────────
		// Each attacker fleet receives a share of the total defender damage
		// proportional to its structural weight (not unit count).
		$attacker_n      = [];
		$attackerShield  = 0.0; // total damage absorbed by shields
		$defenderDmgDone = 0.0; // total damage dealt to attackers

		foreach ($attackers as $fleetID => $attacker) {
			$attacker_n[$fleetID] = [];

			// Weight-proportional share of incoming damage for this fleet
			$fleetWeightShare = $attackWeightTotal > 0
				? ($attackWeightByFleet[$fleetID] ?? 0.0) / $attackWeightTotal
				: 0.0;
			$incomingFleet = $defenseDamageTotal * $fleetWeightShare;

			$fleetHP = max(1.0, (float)($attackWeightByFleet[$fleetID] ?? 1.0));

			foreach ($attacker['unit'] as $element => $amount) {
				if ($amount <= 0) {
					$attacker_n[$fleetID][$element] = 0;
					continue;
				}

				$amount = (float)$amount;

				// Damage received by this unit type: weight-proportional within fleet
				$unitWeightShare = $fleetHP > 0
					? $attArray[$fleetID][$element]['shield'] / $fleetHP
					: 1.0 / max(1, count($attacker['unit']));

				$incomingUnit = $incomingFleet * $unitWeightShare;

				// Add rapid-fire bonus (pre-computed, immutable)
				$incomingUnit += (float)($rfBonusForAtt[$fleetID][$element] ?? 0.0);

				$defenderDmgDone += $incomingUnit;

				$shieldPerUnit = $attArray[$fleetID][$element]['def'] / $amount;
				$hpPerUnit     = $attArray[$fleetID][$element]['shield'] / $amount;

				// If shield per unit absorbs all incoming per unit → no hull damage
				if ($shieldPerUnit >= ($incomingUnit / $amount)) {
					$attacker_n[$fleetID][$element] = (int)round($amount);
					$attackerShield += $incomingUnit;
					continue;
				}

				// Shield absorbed fraction
				$shieldAbsorbed  = min($attArray[$fleetID][$element]['def'], $incomingUnit);
				$attackerShield += $shieldAbsorbed;
				$hullDamage      = $incomingUnit - $shieldAbsorbed;

				// Hull-breach probability: how many units are penetrated?
				// P(unit destroyed) = hull_damage_per_unit / hp_per_unit,
				// clamped to [0, 1], then we check each unit independently
				// via binomial approximation: destroyed = amount * P, with
				// ±variance from mt_rand so outcome is not deterministic.
				$hullDmgPerUnit = $hullDamage / $amount;
				$breachProb     = min(1.0, $hullDmgPerUnit / max(1.0, $hpPerUnit));

				// Apply [1,100]% variance (clamped, cannot be 0 → units are not immortal)
				$variance   = mt_rand(1, 100) / 100.0;
				$destroyed  = (int)floor($amount * $breachProb * $variance);
				$destroyed  = max(0, min((int)$amount, $destroyed));

				$attacker_n[$fleetID][$element] = (int)($amount - $destroyed);
			}
		}

		// ── Phase 5: calculate defender losses ────────────────────────────
		$defender_n      = [];
		$defenderShield  = 0.0;
		$attackerDmgDone = 0.0;

		foreach ($defenders as $fleetID => $defender) {
			$defender_n[$fleetID] = [];

			$fleetWeightShare = $defenseWeightTotal > 0
				? ($defenseWeightByFleet[$fleetID] ?? 0.0) / $defenseWeightTotal
				: 0.0;
			$incomingFleet = $attackDamageTotal * $fleetWeightShare;

			$fleetHP = max(1.0, (float)($defenseWeightByFleet[$fleetID] ?? 1.0));

			foreach ($defender['unit'] as $element => $amount) {
				if ($amount <= 0) {
					$defender_n[$fleetID][$element] = 0;
					continue;
				}

				$amount = (float)$amount;

				$unitWeightShare = $fleetHP > 0
					? $defArray[$fleetID][$element]['shield'] / $fleetHP
					: 1.0 / max(1, count($defender['unit']));

				$incomingUnit = $incomingFleet * $unitWeightShare;
				$incomingUnit += (float)($rfBonusForDef[$fleetID][$element] ?? 0.0);

				$attackerDmgDone += $incomingUnit;

				$shieldPerUnit = $defArray[$fleetID][$element]['def'] / $amount;
				$hpPerUnit     = $defArray[$fleetID][$element]['shield'] / $amount;

				if ($shieldPerUnit >= ($incomingUnit / $amount)) {
					$defender_n[$fleetID][$element] = (int)round($amount);
					$defenderShield += $incomingUnit;
					continue;
				}

				$shieldAbsorbed  = min($defArray[$fleetID][$element]['def'], $incomingUnit);
				$defenderShield += $shieldAbsorbed;
				$hullDamage      = $incomingUnit - $shieldAbsorbed;

				$hullDmgPerUnit = $hullDamage / $amount;
				$breachProb     = min(1.0, $hullDmgPerUnit / max(1.0, $hpPerUnit));

				$variance  = mt_rand(1, 100) / 100.0;
				$destroyed = (int)floor($amount * $breachProb * $variance);
				$destroyed = max(0, min((int)$amount, $destroyed));

				$defender_n[$fleetID][$element] = (int)($amount - $destroyed);
			}
		}

		// ── Phase 6: store round damage summary and apply losses ──────────
		$ROUND[$ROUNDC]['attack']       = $attackerDmgDone;
		$ROUND[$ROUNDC]['defense']      = $defenderDmgDone;
		$ROUND[$ROUNDC]['attackShield'] = $attackerShield;
		$ROUND[$ROUNDC]['defShield']    = $defenderShield;

		foreach ($attackers as $fleetID => $attacker) {
			foreach ($attacker_n[$fleetID] as $el => $amt) {
				$attackers[$fleetID]['unit'][$el] = $amt;
			}
		}
		foreach ($defenders as $fleetID => $defender) {
			foreach ($defender_n[$fleetID] as $el => $amt) {
				$defenders[$fleetID]['unit'][$el] = $amt;
			}
		}
	}

	// ── Determine winner ─────────────────────────────────────────────────
	$finalAttCount = 0;
	foreach ($attackers as $att) { $finalAttCount += array_sum($att['unit']); }
	$finalDefCount = 0;
	foreach ($defenders as $def) { $finalDefCount += array_sum($def['unit']); }

	if ($finalAttCount <= 0 && $finalDefCount > 0) {
		$won = "r"; // defender wins
	} elseif ($finalAttCount > 0 && $finalDefCount <= 0) {
		$won = "a"; // attacker wins
	} else {
		$won = "w"; // draw
	}

	// ── Debris calculation ────────────────────────────────────────────────
	// Subtract surviving units from initial cost to get losses.
	foreach ($attackers as $fleetID => $attacker) {
		foreach ($attacker['unit'] as $element => $amount) {
			$TRES['attacker'] -= (($pricelist[$element]['cost'][901] ?? 0)
			                    + ($pricelist[$element]['cost'][902] ?? 0)) * $amount;
			$ARES['metal']    -= ($pricelist[$element]['cost'][901] ?? 0) * $amount;
			$ARES['crystal']  -= ($pricelist[$element]['cost'][902] ?? 0) * $amount;
		}
	}

	$DRESDefs = ['metal' => 0.0, 'crystal' => 0.0];

	foreach ($defenders as $fleetID => $defender) {
		foreach ($defender['unit'] as $element => $amount) {
			if ($element < 300) {
				$DRES['metal']    -= ($pricelist[$element]['cost'][901] ?? 0) * $amount;
				$DRES['crystal']  -= ($pricelist[$element]['cost'][902] ?? 0) * $amount;
				$TRES['defender'] -= (($pricelist[$element]['cost'][901] ?? 0)
				                    + ($pricelist[$element]['cost'][902] ?? 0)) * $amount;
			} else {
				// Defense structures: subtract survivors, then reconstruct a fraction
				$TRES['defender'] -= (($pricelist[$element]['cost'][901] ?? 0)
				                    + ($pricelist[$element]['cost'][902] ?? 0)) * $amount;

				$lost     = ($STARTDEF[$element] ?? 0) - (int)$amount;
				$giveback = (int)round($lost * (mt_rand(DEF_REBUILD_MIN, DEF_REBUILD_MAX) / 100.0));
				$defenders[$fleetID]['unit'][$element] += $giveback;

				// Only the permanently-lost portion goes to debris
				$DRESDefs['metal']   += ($pricelist[$element]['cost'][901] ?? 0) * ($lost - $giveback);
				$DRESDefs['crystal'] += ($pricelist[$element]['cost'][902] ?? 0) * ($lost - $giveback);
			}
		}
	}

	$ARES['metal']    = max(0.0, $ARES['metal']);
	$ARES['crystal']  = max(0.0, $ARES['crystal']);
	$DRES['metal']    = max(0.0, $DRES['metal']);
	$DRES['crystal']  = max(0.0, $DRES['crystal']);
	$TRES['attacker'] = max(0.0, $TRES['attacker']);
	$TRES['defender'] = max(0.0, $TRES['defender']);

	return [
		'won'      => $won,
		'debris'   => [
			'attacker' => [
				901 => $ARES['metal']   * ($FleetTF / 100.0),
				902 => $ARES['crystal'] * ($FleetTF / 100.0),
			],
			'defender' => [
				901 => $DRES['metal']   * ($FleetTF / 100.0)
				     + $DRESDefs['metal']   * ($DefTF / 100.0),
				902 => $DRES['crystal'] * ($FleetTF / 100.0)
				     + $DRESDefs['crystal'] * ($DefTF / 100.0),
			],
		],
		'rw'       => $ROUND,
		'unitLost' => [
			'attacker' => $TRES['attacker'],
			'defender' => $TRES['defender'],
		],
	];
}