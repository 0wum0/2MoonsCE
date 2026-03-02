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
 * 2MoonsCE Combat Engine v3.0
 * ============================================================
 *
 * v2.0 improvements (retained):
 *   1. Structural-weight damage distribution
 *   2. Correct rapid-fire (pre-computed, immutable totals)
 *   3. mt_rand hull-breach probability clamped to [1,100]
 *   4. Deep-copy round snapshots
 *   5. Always-set tech multipliers
 *   6. Named-constant defense reconstruction
 *   7. Strict int/float arithmetic
 *
 * v3.0 new systems:
 *
 * A. TACTICAL FORMATIONS
 *    Caller may pass 'formation' key on each fleet entry:
 *      'offensive'  => +12% attack, -8%  shield
 *      'defensive'  => -8%  attack, +15% shield
 *      'flanking'   => +7%  attack, +5%  shield, ignore 30% of enemy shields
 *      'scattered'  => no bonus, but critical-hit chance doubled
 *      (default: no modifier)
 *
 * B. CRITICAL HITS
 *    Each unit group has a 5% per-round chance of landing a critical
 *    hit that doubles its attack output that round.  'scattered'
 *    formation raises this to 10%.  Critical events are recorded in
 *    the round meta for display in the battle report.
 *
 * C. MORALE SYSTEM
 *    When a side loses >50% of its initial structural weight, its
 *    morale breaks.  Each subsequent round the broken side suffers
 *    -8% attack (cumulative, max -40%).  Morale state is tracked
 *    per side and recorded in round meta.
 *
 * D. SHIP-CLASS SYNERGIES
 *    Fleet composition bonuses applied once per round after formation:
 *      Battleship (202) + Destroyer (215)     => +8% attack for fleet
 *      Cruiser (206)    + Light Fighter (204)  => +5% shield for fleet
 *      Battlecruiser (211) + Bomber (211 pair) => +6% attack
 *      Destroyer (215)  alone ≥10 units        => +4% hull (HP bonus)
 *    Synergies stack additively, capped at +25% per stat.
 *
 * E. EXTENDED ROUND META (new 'meta' sub-key per round)
 *    Each $ROUND[n]['meta'] contains:
 *      - 'att_dmg_raw'    total attacker damage before crits
 *      - 'att_dmg_final'  total attacker damage after crits
 *      - 'def_dmg_raw'    same for defender
 *      - 'def_dmg_final'
 *      - 'att_shield_abs' damage absorbed by attacker shields
 *      - 'def_shield_abs' damage absorbed by defender shields
 *      - 'att_hull_dmg'   hull damage dealt to attackers
 *      - 'def_hull_dmg'   hull damage dealt to defenders
 *      - 'att_rf_bonus'   total RF bonus damage received by attackers
 *      - 'def_rf_bonus'   total RF bonus damage received by defenders
 *      - 'att_crits'      list of [fleetID, elementID] that crit'd
 *      - 'def_crits'      same for defenders
 *      - 'att_morale'     attacker morale multiplier this round
 *      - 'def_morale'     defender morale multiplier
 *      - 'att_formation'  formation name(s) active
 *      - 'def_formation'
 *      - 'synergies_att'  active synergy names for attackers
 *      - 'synergies_def'  active synergy names for defenders
 *      - 'efficiency'     struct ['att'=>float, 'def'=>float]
 *                         = damage_dealt / (own_initial_cost/1000)
 *
 * Return value is BACKWARD COMPATIBLE: 'won','debris','rw','unitLost'
 * are unchanged.  New 'meta' top-level key carries battle summary.
 * ============================================================
 */
if (!defined('DEF_REBUILD_MIN')) define('DEF_REBUILD_MIN', 56);
if (!defined('DEF_REBUILD_MAX')) define('DEF_REBUILD_MAX', 84);
if (!defined('CRIT_HIT_CHANCE'))  define('CRIT_HIT_CHANCE',  5);   // % base crit chance
if (!defined('CRIT_HIT_MULT'))    define('CRIT_HIT_MULT',    2.0); // crit damage multiplier
if (!defined('MORALE_THRESHOLD')) define('MORALE_THRESHOLD', 0.50);// 50% weight lost → morale breaks
if (!defined('MORALE_PENALTY'))   define('MORALE_PENALTY',   0.08);// -8% attack per broken round
if (!defined('MORALE_CAP'))       define('MORALE_CAP',        0.40);// max -40% attack total

// ── Formation definitions ─────────────────────────────────────────────────
// Each entry: [attMul, shieldMul, shieldPenetration, critBonus]
if (!defined('FORMATIONS_DEFINED')) {
	define('FORMATIONS_DEFINED', 1);
	define('FORMATION_OFFENSIVE', serialize(['att'=>1.12, 'shd'=>0.92, 'pen'=>0.0,  'crit'=>0]));
	define('FORMATION_DEFENSIVE', serialize(['att'=>0.92, 'shd'=>1.15, 'pen'=>0.0,  'crit'=>0]));
	define('FORMATION_FLANKING',  serialize(['att'=>1.07, 'shd'=>1.05, 'pen'=>0.30, 'crit'=>0]));
	define('FORMATION_SCATTERED', serialize(['att'=>1.0,  'shd'=>1.0,  'pen'=>0.0,  'crit'=>5]));
}

// ── Ship-class synergy definitions ────────────────────────────────────────
// [required_ids => [minCount], bonus_att, bonus_shd, bonus_hp, label]
if (!defined('SYNERGIES_DEFINED')) {
	define('SYNERGIES_DEFINED', 1);
	define('SHIP_SYNERGIES', serialize([
		['ids'=>[202,215], 'min'=>[1,1],  'att'=>0.08, 'shd'=>0.0,  'hp'=>0.0,  'label'=>'Battlegroup'],
		['ids'=>[206,204], 'min'=>[1,1],  'att'=>0.0,  'shd'=>0.05, 'hp'=>0.0,  'label'=>'Escort Screen'],
		['ids'=>[211,213], 'min'=>[1,1],  'att'=>0.06, 'shd'=>0.0,  'hp'=>0.0,  'label'=>'Strike Wing'],
		['ids'=>[215],     'min'=>[10],   'att'=>0.0,  'shd'=>0.0,  'hp'=>0.04, 'label'=>'Destroyer Vanguard'],
		['ids'=>[202,206,215],'min'=>[1,1,1],'att'=>0.05,'shd'=>0.05,'hp'=>0.0, 'label'=>'Full Battle Fleet'],
	]));
}

function calculateAttack(array &$attackers, array &$defenders, float $FleetTF, float $DefTF): array
{
	global $pricelist, $CombatCaps, $resource;

	// ── Plugin hook ───────────────────────────────────────────────────────
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

	// ── Helper: structural HP per unit ───────────────────────────────────
	$unitHP = static function (int $element, float $shieldTech) use (&$pricelist): float {
		$cost = (float)(($pricelist[$element]['cost'][901] ?? 0)
		              + ($pricelist[$element]['cost'][902] ?? 0));
		return max(1.0, $cost / 10.0 * $shieldTech);
	};

	// ── Helper: resolve formation parameters for a fleet ─────────────────
	$getFormation = static function (array $fleet): array {
		$name = strtolower((string)($fleet['formation'] ?? ''));
		$map  = [
			'offensive' => unserialize(FORMATION_OFFENSIVE),
			'defensive' => unserialize(FORMATION_DEFENSIVE),
			'flanking'  => unserialize(FORMATION_FLANKING),
			'scattered' => unserialize(FORMATION_SCATTERED),
		];
		return $map[$name] ?? ['att'=>1.0,'shd'=>1.0,'pen'=>0.0,'crit'=>0];
	};

	// ── Helper: compute synergy bonuses for a fleet's unit composition ────
	$getSynergies = static function (array $units): array {
		$synDefs = unserialize(SHIP_SYNERGIES);
		$bonusAtt = 0.0; $bonusShd = 0.0; $bonusHp = 0.0;
		$labels   = [];
		foreach ($synDefs as $syn) {
			$active = true;
			foreach ($syn['ids'] as $i => $id) {
				if (($units[$id] ?? 0) < $syn['min'][$i]) { $active = false; break; }
			}
			if (!$active) continue;
			$bonusAtt += $syn['att'];
			$bonusShd += $syn['shd'];
			$bonusHp  += $syn['hp'];
			$labels[]  = $syn['label'];
		}
		// Cap each bonus at +25%
		return [
			'att'    => min(0.25, $bonusAtt),
			'shd'    => min(0.25, $bonusShd),
			'hp'     => min(0.25, $bonusHp),
			'labels' => $labels,
		];
	};

	// ── Build rapid-fire lookup ───────────────────────────────────────────
	$RF = [];
	foreach ($CombatCaps as $shooterID => $caps) {
		if (empty($caps['sd'])) continue;
		foreach ($caps['sd'] as $targetID => $shots) {
			if ($shots > 0) {
				$RF[$targetID][$shooterID] = (int)$shots;
			}
		}
	}

	// ── Initial cost accounting ───────────────────────────────────────────
	$ARES     = ['metal' => 0.0, 'crystal' => 0.0];
	$DRES     = ['metal' => 0.0, 'crystal' => 0.0];
	$STARTDEF = [];

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

	// ── Morale tracking ───────────────────────────────────────────────────
	// Structural weight at battle start (for morale threshold)
	$initAttWeight = 0.0;
	foreach ($attackers as $fl) {
		foreach ($fl['unit'] as $el => $amt) {
			$initAttWeight += (($pricelist[$el]['cost'][901] ?? 0) + ($pricelist[$el]['cost'][902] ?? 0)) / 10.0 * $amt;
		}
	}
	$initDefWeight = 0.0;
	foreach ($defenders as $fl) {
		foreach ($fl['unit'] as $el => $amt) {
			$initDefWeight += (($pricelist[$el]['cost'][901] ?? 0) + ($pricelist[$el]['cost'][902] ?? 0)) / 10.0 * $amt;
		}
	}
	$initAttWeight = max(1.0, $initAttWeight);
	$initDefWeight = max(1.0, $initDefWeight);

	$attMoraleBrokenRounds = 0; // rounds attacker morale has been broken
	$defMoraleBrokenRounds = 0;

	// ── Main combat loop ──────────────────────────────────────────────────
	$ROUND = [];

	for ($ROUNDC = 0; $ROUNDC <= MAX_ATTACK_ROUNDS; $ROUNDC++) {

		// ── Phase 1: compute stats for every live unit ────────────────────
		$attArray = [];
		$defArray = [];

		// Attacker stats
		$attackDamageTotal   = 0.0;
		$attackWeightTotal   = 0.0;
		$attackCountTotal    = 0;
		$attackDamageByFleet = [];
		$attackWeightByFleet = [];
		$attackCountByFleet  = [];

		// Collect attacker crits and synergy labels for meta
		$attCrits       = [];
		$attSynLabels   = [];
		$attFormLabels  = [];

		foreach ($attackers as $fleetID => $attacker) {
			$attTech    = 1.0 + 0.1 * (float)$attacker['player']['military_tech']
			                  + (float)($attacker['player']['factor']['Attack'] ?? 0);
			$defTech    = 1.0 + 0.1 * (float)$attacker['player']['defence_tech']
			                  + (float)($attacker['player']['factor']['Defensive'] ?? 0);
			$shieldTech = 1.0 + 0.1 * (float)$attacker['player']['shield_tech']
			                  + (float)($attacker['player']['factor']['Shield'] ?? 0);
			$attackers[$fleetID]['techs'] = [$attTech, $defTech, $shieldTech];

			// Formation & synergy multipliers
			$form   = $getFormation($attacker);
			$syn    = $getSynergies($attacker['unit']);
			$fAttMul = $form['att'] + $syn['att']; // combined attack multiplier offset (+1 base already in form)
			$fShdMul = $form['shd'] + $syn['shd']; // combined shield multiplier offset
			$fHpMul  = 1.0 + $syn['hp'];
			$critBase = CRIT_HIT_CHANCE + (int)$form['crit']; // % chance

			if (!empty($attacker['formation'])) {
				$attFormLabels[] = ucfirst($attacker['formation']);
			}
			foreach ($syn['labels'] as $sl) {
				$attSynLabels[] = $sl;
			}

			$fleetDmg    = 0.0;
			$fleetWeight = 0.0;
			$fleetCount  = 0;

			foreach ($attacker['unit'] as $element => $amount) {
				if ($amount <= 0) continue;

				$baseAtt = (float)($CombatCaps[$element]['attack'] ?? 0);
				// ±20% variance
				$thisAtt = (float)$amount * $baseAtt * $attTech
				           * $fAttMul
				           * (mt_rand(80, 120) / 100.0);

				// Critical hit check
				if (mt_rand(1, 100) <= $critBase) {
					$thisAtt *= CRIT_HIT_MULT;
					$attCrits[] = [$fleetID, $element];
				}

				$thisDef = (float)$amount * (float)($CombatCaps[$element]['shield'] ?? 0)
				           * $defTech * $fShdMul;
				$thisHP  = $unitHP($element, $shieldTech) * $amount * $fHpMul;

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

		$defCrits      = [];
		$defSynLabels  = [];
		$defFormLabels = [];

		foreach ($defenders as $fleetID => $defender) {
			$attTech    = 1.0 + 0.1 * (float)$defender['player']['military_tech']
			                  + (float)($defender['player']['factor']['Attack'] ?? 0);
			$defTech    = 1.0 + 0.1 * (float)$defender['player']['defence_tech']
			                  + (float)($defender['player']['factor']['Defensive'] ?? 0);
			$shieldTech = 1.0 + 0.1 * (float)$defender['player']['shield_tech']
			                  + (float)($defender['player']['factor']['Shield'] ?? 0);
			$defenders[$fleetID]['techs'] = [$attTech, $defTech, $shieldTech];

			$form   = $getFormation($defender);
			$syn    = $getSynergies($defender['unit']);
			$fAttMul = $form['att'] + $syn['att'];
			$fShdMul = $form['shd'] + $syn['shd'];
			$fHpMul  = 1.0 + $syn['hp'];
			$critBase = CRIT_HIT_CHANCE + (int)$form['crit'];

			if (!empty($defender['formation'])) {
				$defFormLabels[] = ucfirst($defender['formation']);
			}
			foreach ($syn['labels'] as $sl) {
				$defSynLabels[] = $sl;
			}

			$fleetDmg    = 0.0;
			$fleetWeight = 0.0;
			$fleetCount  = 0;

			foreach ($defender['unit'] as $element => $amount) {
				if ($amount <= 0) continue;

				$baseAtt = (float)($CombatCaps[$element]['attack'] ?? 0);
				if ($element === 407 || $element === 408 || $element === 409) {
					$baseAtt = 0.0;
				}

				$thisAtt = (float)$amount * $baseAtt * $attTech
				           * $fAttMul
				           * (mt_rand(80, 120) / 100.0);

				if (mt_rand(1, 100) <= $critBase) {
					$thisAtt *= CRIT_HIT_MULT;
					$defCrits[] = [$fleetID, $element];
				}

				$thisDef = (float)$amount * (float)($CombatCaps[$element]['shield'] ?? 0)
				           * $defTech * $fShdMul;
				$thisHP  = $unitHP($element, $shieldTech) * $amount * $fHpMul;

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

		// ── Phase 1b: Morale check & penalty ─────────────────────────────
		// Current structural weight
		$curAttWeight = $attackWeightTotal;
		$curDefWeight = $defenseWeightTotal;

		// Morale breaks when >50% of initial weight is gone
		$attMoraleMul = 1.0;
		if ($initAttWeight > 0 && $curAttWeight < $initAttWeight * (1.0 - MORALE_THRESHOLD)) {
			$attMoraleBrokenRounds++;
			$penalty = min(MORALE_CAP, $attMoraleBrokenRounds * MORALE_PENALTY);
			$attMoraleMul = 1.0 - $penalty;
			// Apply morale penalty to all computed attacker damage
			foreach ($attArray as $fid => &$units) {
				foreach ($units as $el => &$stats) {
					$stats['att'] *= $attMoraleMul;
				}
			}
			unset($units, $stats);
			// Recompute totals after penalty
			$attackDamageTotal = 0.0;
			foreach ($attArray as $fid => $units) {
				$sum = array_sum(array_column($units, 'att'));
				$attackDamageByFleet[$fid] = $sum;
				$attackDamageTotal += $sum;
			}
		}

		$defMoraleMul = 1.0;
		if ($initDefWeight > 0 && $curDefWeight < $initDefWeight * (1.0 - MORALE_THRESHOLD)) {
			$defMoraleBrokenRounds++;
			$penalty = min(MORALE_CAP, $defMoraleBrokenRounds * MORALE_PENALTY);
			$defMoraleMul = 1.0 - $penalty;
			foreach ($defArray as $fid => &$units) {
				foreach ($units as $el => &$stats) {
					$stats['att'] *= $defMoraleMul;
				}
			}
			unset($units, $stats);
			$defenseDamageTotal = 0.0;
			foreach ($defArray as $fid => $units) {
				$sum = array_sum(array_column($units, 'att'));
				$defenseDamageByFleet[$fid] = $sum;
				$defenseDamageTotal += $sum;
			}
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

		// ── Phase 3: snapshot BEFORE mutation (for report) ──────────────
		$snapAttackers = [];
		foreach ($attackers as $fid => $fl) { $snapAttackers[$fid] = $fl; }
		$snapDefenders = [];
		foreach ($defenders as $fid => $fl) { $snapDefenders[$fid] = $fl; }

		$attackAmountSnapshot  = ['total' => $attackCountTotal];
		$defenseAmountSnapshot = ['total' => $defenseCountTotal];
		foreach ($attackCountByFleet  as $fid => $cnt) { $attackAmountSnapshot[$fid]  = $cnt; }
		foreach ($defenseCountByFleet as $fid => $cnt) { $defenseAmountSnapshot[$fid] = $cnt; }

		// Flanking penetration: reduce effective enemy shield by pen %
		// Applied to the damage totals before the loss calculation.
		// We collect the max pen value across all attacker/defender fleets.
		$attPen = 0.0; // attacker shield penetration (reduces defender shield effectiveness)
		foreach ($attackers as $fl) {
			$f = $getFormation($fl);
			if ($f['pen'] > $attPen) $attPen = $f['pen'];
		}
		$defPen = 0.0;
		foreach ($defenders as $fl) {
			$f = $getFormation($fl);
			if ($f['pen'] > $defPen) $defPen = $f['pen'];
		}

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
		$attacker_n      = [];
		$attackerShield  = 0.0;
		$attackerHull    = 0.0;
		$defenderDmgDone = 0.0;
		$attRFTotal      = 0.0;

		foreach ($attackers as $fleetID => $attacker) {
			$attacker_n[$fleetID] = [];

			$fleetWeightShare = $attackWeightTotal > 0
				? ($attackWeightByFleet[$fleetID] ?? 0.0) / $attackWeightTotal
				: 0.0;
			$incomingFleet = $defenseDamageTotal * $fleetWeightShare;
			$fleetHP = max(1.0, (float)($attackWeightByFleet[$fleetID] ?? 1.0));

			// Defender flanking: reduce this fleet's effective shield by $defPen
			$penFactor = max(0.0, 1.0 - $defPen);

			foreach ($attacker['unit'] as $element => $amount) {
				if ($amount <= 0) {
					$attacker_n[$fleetID][$element] = 0;
					continue;
				}
				$amount = (float)$amount;

				$unitWeightShare = $fleetHP > 0
					? $attArray[$fleetID][$element]['shield'] / $fleetHP
					: 1.0 / max(1, count($attacker['unit']));

				$incomingUnit  = $incomingFleet * $unitWeightShare;
				$rfBonus       = (float)($rfBonusForAtt[$fleetID][$element] ?? 0.0);
				$incomingUnit += $rfBonus;
				$attRFTotal   += $rfBonus;
				$defenderDmgDone += $incomingUnit;

				// Effective shield reduced by penetration
				$effectiveShield = $attArray[$fleetID][$element]['def'] * $penFactor;
				$shieldPerUnit   = $effectiveShield / $amount;
				$hpPerUnit       = $attArray[$fleetID][$element]['shield'] / $amount;

				if ($shieldPerUnit >= ($incomingUnit / $amount)) {
					$attacker_n[$fleetID][$element] = (int)round($amount);
					$attackerShield += $incomingUnit;
					continue;
				}

				$shieldAbsorbed  = min($effectiveShield, $incomingUnit);
				$attackerShield += $shieldAbsorbed;
				$hullDamage      = $incomingUnit - $shieldAbsorbed;
				$attackerHull   += $hullDamage;

				$breachProb = min(1.0, ($hullDamage / $amount) / max(1.0, $hpPerUnit));
				$variance   = mt_rand(1, 100) / 100.0;
				$destroyed  = max(0, min((int)$amount, (int)floor($amount * $breachProb * $variance)));

				$attacker_n[$fleetID][$element] = (int)($amount - $destroyed);
			}
		}

		// ── Phase 5: calculate defender losses ────────────────────────────
		$defender_n      = [];
		$defenderShield  = 0.0;
		$defenderHull    = 0.0;
		$attackerDmgDone = 0.0;
		$defRFTotal      = 0.0;

		foreach ($defenders as $fleetID => $defender) {
			$defender_n[$fleetID] = [];

			$fleetWeightShare = $defenseWeightTotal > 0
				? ($defenseWeightByFleet[$fleetID] ?? 0.0) / $defenseWeightTotal
				: 0.0;
			$incomingFleet = $attackDamageTotal * $fleetWeightShare;
			$fleetHP = max(1.0, (float)($defenseWeightByFleet[$fleetID] ?? 1.0));

			// Attacker flanking: reduce defender's effective shield
			$penFactor = max(0.0, 1.0 - $attPen);

			foreach ($defender['unit'] as $element => $amount) {
				if ($amount <= 0) {
					$defender_n[$fleetID][$element] = 0;
					continue;
				}
				$amount = (float)$amount;

				$unitWeightShare = $fleetHP > 0
					? $defArray[$fleetID][$element]['shield'] / $fleetHP
					: 1.0 / max(1, count($defender['unit']));

				$incomingUnit  = $incomingFleet * $unitWeightShare;
				$rfBonus       = (float)($rfBonusForDef[$fleetID][$element] ?? 0.0);
				$incomingUnit += $rfBonus;
				$defRFTotal   += $rfBonus;
				$attackerDmgDone += $incomingUnit;

				$effectiveShield = $defArray[$fleetID][$element]['def'] * $penFactor;
				$shieldPerUnit   = $effectiveShield / $amount;
				$hpPerUnit       = $defArray[$fleetID][$element]['shield'] / $amount;

				if ($shieldPerUnit >= ($incomingUnit / $amount)) {
					$defender_n[$fleetID][$element] = (int)round($amount);
					$defenderShield += $incomingUnit;
					continue;
				}

				$shieldAbsorbed  = min($effectiveShield, $incomingUnit);
				$defenderShield += $shieldAbsorbed;
				$hullDamage      = $incomingUnit - $shieldAbsorbed;
				$defenderHull   += $hullDamage;

				$breachProb = min(1.0, ($hullDamage / $amount) / max(1.0, $hpPerUnit));
				$variance   = mt_rand(1, 100) / 100.0;
				$destroyed  = max(0, min((int)$amount, (int)floor($amount * $breachProb * $variance)));

				$defender_n[$fleetID][$element] = (int)($amount - $destroyed);
			}
		}

		// ── Phase 6: store round summary + meta and apply losses ─────────
		$ROUND[$ROUNDC]['attack']       = $attackerDmgDone;
		$ROUND[$ROUNDC]['defense']      = $defenderDmgDone;
		$ROUND[$ROUNDC]['attackShield'] = $attackerShield;
		$ROUND[$ROUNDC]['defShield']    = $defenderShield;

		// Efficiency = damage dealt / (own initial weight * 1000) — dimensionless score
		$attEfficiency = $initAttWeight > 0 ? round($attackerDmgDone / ($initAttWeight * 10.0), 2) : 0.0;
		$defEfficiency = $initDefWeight > 0 ? round($defenderDmgDone / ($initDefWeight * 10.0), 2) : 0.0;

		$ROUND[$ROUNDC]['meta'] = [
			'att_dmg_final'  => round($attackerDmgDone, 2),
			'def_dmg_final'  => round($defenderDmgDone, 2),
			'att_shield_abs' => round($attackerShield, 2),
			'def_shield_abs' => round($defenderShield, 2),
			'att_hull_dmg'   => round($defenderHull,  2), // hull damage dealt TO defenders
			'def_hull_dmg'   => round($attackerHull,  2), // hull damage dealt TO attackers
			'att_rf_bonus'   => round($defRFTotal, 2),    // RF bonus received by defenders (from attackers)
			'def_rf_bonus'   => round($attRFTotal, 2),    // RF bonus received by attackers (from defenders)
			'att_crits'      => $attCrits,
			'def_crits'      => $defCrits,
			'att_morale'     => round($attMoraleMul, 3),
			'def_morale'     => round($defMoraleMul, 3),
			'att_formation'  => array_unique($attFormLabels),
			'def_formation'  => array_unique($defFormLabels),
			'synergies_att'  => array_unique($attSynLabels),
			'synergies_def'  => array_unique($defSynLabels),
			'efficiency'     => ['att' => $attEfficiency, 'def' => $defEfficiency],
		];

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

	// ── Top-level battle meta ─────────────────────────────────────────────
	$roundsPlayed = count($ROUND);
	$totalAttDmg  = 0.0; $totalDefDmg = 0.0;
	$totalCritsAtt = 0;  $totalCritsDef = 0;
	foreach ($ROUND as $r) {
		$totalAttDmg   += $r['meta']['att_dmg_final'] ?? 0;
		$totalDefDmg   += $r['meta']['def_dmg_final'] ?? 0;
		$totalCritsAtt += count($r['meta']['att_crits'] ?? []);
		$totalCritsDef += count($r['meta']['def_crits'] ?? []);
	}

	$battleMeta = [
		'rounds'          => $roundsPlayed,
		'total_att_dmg'   => round($totalAttDmg, 2),
		'total_def_dmg'   => round($totalDefDmg, 2),
		'total_crits_att' => $totalCritsAtt,
		'total_crits_def' => $totalCritsDef,
		'att_morale_breaks' => $attMoraleBrokenRounds,
		'def_morale_breaks' => $defMoraleBrokenRounds,
		'engine'          => 'v3.0',
	];

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
		'rw'        => $ROUND,
		'unitLost'  => [
			'attacker' => $TRES['attacker'],
			'defender' => $TRES['defender'],
		],
		'meta'      => $battleMeta,
	];
}