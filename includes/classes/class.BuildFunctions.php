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

class BuildFunctions
{
    static $bonusList	= array(
        'Attack',
        'Defensive',
        'Shield',
        'BuildTime',
        'ResearchTime',
        'ShipTime',
        'DefensiveTime',
        'Resource',
        'Energy',
        'ResourceStorage',
        'ShipStorage',
        'FlyTime',
        'FleetSlots',
        'Planets',
        'SpyPower',
        'Expedition',
        'GateCoolTime',
        'MoreFound',
    );

    public static function getBonusList()
    {
        return self::$bonusList;
    }

    public static function getRestPrice($USER, $PLANET, $Element, $elementPrice = NULL)
    {
        global $resource;

        if(!isset($elementPrice)) {
            $elementPrice	= self::getElementPrice($USER, $PLANET, $Element);
        }

        $overflow	= array();

        foreach ($elementPrice as $resType => $resPrice) {
            $available = 0;

            if (isset($PLANET[$resource[$resType]])) {
                $available = $PLANET[$resource[$resType]];
            } elseif (isset($USER[$resource[$resType]])) {
                $available = $USER[$resource[$resType]];
            }

            $overflow[$resType] = max($resPrice - floor((float)$available), 0);
        }

        return $overflow;
    }

    /**
     * Builds/Tech = level based
     * Fleet/Defense/Missiles = amount based
     */
    public static function getElementPrice($USER, $PLANET, $Element, $forDestroy = false, $forLevel = NULL)
    {
        global $pricelist, $resource, $reslist;

        // Determine whether this element is count-based (ships/def/missiles) or level-based (build/tech)
        $isCountBased = in_array($Element, $reslist['fleet']) || in_array($Element, $reslist['defense']) || in_array($Element, $reslist['missile']);

        // Amount for count-based elements (ships/defense/missiles)
        $amount = 1;

        // Level for level-based elements (buildings/tech)
        $level = 0;

        if ($isCountBased) {
            // forLevel means: "how many units to price"
            if (isset($forLevel)) {
                $amount = (int)$forLevel;
            }
            // else: $amount stays 1 — always price a single unit for display/buyability checks.
            // Ships/defense/missiles do not scale with existing count; reading the planet's
            // current stock as "amount" caused costs to be 0 when the planet had 0 units.

            // In classic 2Moons, ships/def/missiles do not scale with "level".
            $level = 0;
        } else {
            // level-based: forLevel means "target level"
            if (isset($forLevel)) {
                $level = (int)$forLevel;
            } elseif (isset($PLANET[$resource[$Element]])) {
                $level = (int)$PLANET[$resource[$Element]];
            } elseif (isset($USER[$resource[$Element]])) {
                $level = (int)$USER[$resource[$Element]];
            } else {
                return array();
            }
        }

        $price = array();

        foreach ($reslist['ressources'] as $resType)
        {
            if (!isset($pricelist[$Element]['cost'][$resType])) {
                continue;
            }

            $base = (float)$pricelist[$Element]['cost'][$resType];
            if ($base <= 0) {
                continue;
            }

            $cost = $base;

            // Apply factor scaling for level-based elements (and only if factor makes sense)
            if (!$isCountBased && isset($pricelist[$Element]['factor']) && $pricelist[$Element]['factor'] != 0 && $pricelist[$Element]['factor'] != 1) {
                $cost *= pow((float)$pricelist[$Element]['factor'], $level);
            }

            // Multiply by amount for ships/def/missiles
            if ($isCountBased) {
                $cost *= max(0, $amount);
            }

            if ($forDestroy === true) {
                $cost /= 2;
            }

            $price[$resType] = $cost;
        }

        return $price;
    }

    public static function isTechnologieAccessible($USER, $PLANET, $Element)
    {
        global $requeriments, $resource;

        if(!isset($requeriments[$Element])) {
            return true;
        }

        foreach($requeriments[$Element] as $ReqElement => $EleLevel)
        {
            if (
                (isset($USER[$resource[$ReqElement]]) && $USER[$resource[$ReqElement]] < $EleLevel) ||
                (isset($PLANET[$resource[$ReqElement]]) && $PLANET[$resource[$ReqElement]] < $EleLevel)
            ) {
                return false;
            }
        }

        return true;
    }

    public static function getBuildingTime($USER, $PLANET, $Element, $elementPrice = NULL, $forDestroy = false, $forLevel = NULL)
    {
        global $resource, $reslist, $requeriments;

        $config	= Config::get($USER['universe']);
        $time   = 0;

        if(!isset($elementPrice)) {
            $elementPrice	= self::getElementPrice($USER, $PLANET, $Element, $forDestroy, $forLevel);
        }

        $elementCost = 0;

        if(isset($elementPrice[901])) {
            $elementCost += $elementPrice[901];
        }
        if(isset($elementPrice[902])) {
            $elementCost += $elementPrice[902];
        }

        if (in_array($Element, $reslist['build'])) {
            $time = $elementCost / ($config->game_speed * (1 + $PLANET[$resource[14]])) * pow(0.5, $PLANET[$resource[15]]) * (1 + $USER['factor']['BuildTime']);
        } elseif (in_array($Element, $reslist['fleet'])) {
            $time = $elementCost / ($config->game_speed * (1 + $PLANET[$resource[21]])) * pow(0.5, $PLANET[$resource[15]]) * (1 + $USER['factor']['ShipTime']);
        } elseif (in_array($Element, $reslist['defense'])) {
            $time = $elementCost / ($config->game_speed * (1 + $PLANET[$resource[21]])) * pow(0.5, $PLANET[$resource[15]]) * (1 + $USER['factor']['DefensiveTime']);
        } elseif (in_array($Element, $reslist['tech'])) {

            if(is_numeric($PLANET[$resource[31].'_inter'])) {
                $Level = $PLANET[$resource[31]];
            } else {
                $Level = 0;
                foreach($PLANET[$resource[31].'_inter'] as $Levels)
                {
                    if(!isset($requeriments[$Element][31]) || $Levels >= $requeriments[$Element][31]) {
                        $Level += $Levels;
                    }
                }
            }

            $time = $elementCost / (1000 * (1 + $Level)) / ($config->game_speed / 2500) * pow(1 - $config->factor_university / 100, $PLANET[$resource[6]]) * (1 + $USER['factor']['ResearchTime']);
        }

        if($forDestroy) {
            $time = floor($time * 1300);
        } else {
            $time = floor($time * 3600);
        }

        $time = max($time, $config->min_build_time);

        // Plugin System v1.1 – build time filter hook
        $level = $forLevel ?? (isset($PLANET[$resource[$Element]]) ? (int)$PLANET[$resource[$Element]] : 0);
        $time  = (int) HookManager::get()->applyFilters('game.buildTime', $time, [
            'element' => $Element,
            'level'   => $level,
            'destroy' => $forDestroy,
        ]);

        return max($time, $config->min_build_time);
    }

    public static function isElementBuyable($USER, $PLANET, $Element, $elementPrice = NULL, $forDestroy = false, $forLevel = NULL)
    {
        if (!isset($elementPrice)) {
            $elementPrice = self::getElementPrice($USER, $PLANET, $Element, $forDestroy, $forLevel);
        }
        if (empty($elementPrice)) {
            return false;
        }
        $rest = self::getRestPrice($USER, $PLANET, $Element, $elementPrice);
        return count(array_filter($rest)) === 0;
    }

    public static function getMaxConstructibleElements($USER, $PLANET, $Element, $elementPrice = NULL)
    {
        global $resource, $reslist;

        if(!isset($elementPrice)) {
            $elementPrice = self::getElementPrice($USER, $PLANET, $Element);
        }

        $maxElement = array();

        foreach($elementPrice as $resourceID => $price)
        {
            if($price <= 0) {
                continue;
            }

            if(isset($PLANET[$resource[$resourceID]]))
            {
                $maxElement[] = floor($PLANET[$resource[$resourceID]] / $price);
            }
            elseif(isset($USER[$resource[$resourceID]]))
            {
                $maxElement[] = floor($USER[$resource[$resourceID]] / $price);
            }
            else
            {
                throw new Exception("Unknown Ressource ".$resourceID." at element ".$Element.".");
            }
        }

        if(in_array($Element, $reslist['one'])) {
            $maxElement[] = 1;
        }

        if(empty($maxElement)) {
            return 0;
        }

        return max(0, (int)min($maxElement));
    }

    public static function getMaxConstructibleRockets($USER, $PLANET, $Missiles = NULL)
    {
        global $resource, $reslist;

        if(!isset($Missiles))
        {
            $Missiles = array();

            foreach($reslist['missile'] as $elementID)
            {
                $Missiles[$elementID] = $PLANET[$resource[$elementID]];
            }
        }

        $BuildArray = !empty($PLANET['b_hangar_id']) ? unserialize($PLANET['b_hangar_id']) : array();

        // IMPORTANT: universe-aware config here
        $config = Config::get($USER['universe']);
        $siloFactor = isset($config->silo_factor) ? (float)$config->silo_factor : 1.0;

        $MaxMissiles = $PLANET[$resource[44]] * 10 * max($siloFactor, 1);

        foreach($BuildArray as $ElementArray) {
            if(isset($Missiles[$ElementArray[0]])) {
                $Missiles[$ElementArray[0]] += $ElementArray[1];
            }
        }

        $ActuMissiles  = $Missiles[502] + (2 * $Missiles[503]);
        $MissilesSpace = max(0, $MaxMissiles - $ActuMissiles);

        return array(
            502	=> $MissilesSpace,
            503	=> floor($MissilesSpace / 2),
        );
    }

    public static function getAvalibleBonus($Element)
    {
        global $pricelist;

        $elementBonus = array();

        if (!isset($pricelist[$Element]['bonus']) || !is_array($pricelist[$Element]['bonus'])) {
            return $elementBonus;
        }

        foreach(self::$bonusList as $bonus)
        {
            if (!isset($pricelist[$Element]['bonus'][$bonus][0])) {
                continue;
            }

            $temp = (float) $pricelist[$Element]['bonus'][$bonus][0];
            if (empty($temp)) {
                continue;
            }

            $elementBonus[$bonus] = $pricelist[$Element]['bonus'][$bonus];
        }

        return $elementBonus;
    }
}