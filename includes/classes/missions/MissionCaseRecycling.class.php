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

class MissionCaseRecycling extends MissionFunctions implements Mission
{
	function __construct($Fleet)
	{
		$this->_fleet	= $Fleet;
	}
	
	function TargetEvent()
	{	
		global $pricelist, $resource;
		
		$resourceIDs	= array(901, 902, 903, 921);
		$debrisIDs		= array(901, 902);
		$resQuery		= array();
		$collectQuery	= array();
		
		$collectedGoods = array();
		foreach($debrisIDs as $debrisID)
		{
			$collectedGoods[$debrisID] = 0;
			$resQuery[]	= 'der_'.$resource[$debrisID];
		}

		$sql	= 'SELECT '.implode(',', $resQuery).', ('.implode(' + ', $resQuery).') as total
		FROM %%PLANETS%% WHERE id = :planetId;';

		$targetData	= Database::get()->selectSingle($sql, array(
			':planetId'	=> $this->_fleet['fleet_end_id']
		));

		if(!empty($targetData['total']))
		{
			$sql				= 'SELECT * FROM %%USERS%% WHERE id = :userId;';
			$targetUser			= Database::get()->selectSingle($sql, array(
				':userId'	=> $this->_fleet['fleet_owner']
			));

			$targetUserFactors	= getFactors($targetUser);
			$shipStorageFactor	= 1 + $targetUserFactors['ShipStorage'];
		
			// Get fleet capacity
			$fleetData			= FleetFunctions::unserialize($this->_fleet['fleet_array']);

			$recyclerStorage	= 0;
			$otherFleetStorage	= 0;

			foreach ($fleetData as $shipId => $shipAmount)
			{
				if ($shipId == 209 || $shipId == 219)
				{
					$recyclerStorage   += $pricelist[$shipId]['capacity'] * $shipAmount;
				}
				else
				{
					$otherFleetStorage += $pricelist[$shipId]['capacity'] * $shipAmount;
				}
			}
			
			$recyclerStorage	*= $shipStorageFactor;
			$otherFleetStorage	*= $shipStorageFactor;

			$incomingGoods		= 0;
			foreach($resourceIDs as $resourceID)
			{
				$incomingGoods	+= $this->_fleet['fleet_resource_'.$resource[$resourceID]];
			}

			// FIX: other ships can only help with debris if they still have free capacity
			$freeOtherStorage = max(0, $otherFleetStorage - $incomingGoods);
			$totalStorage = $recyclerStorage + $freeOtherStorage;

			$param	= array(
				':planetId'	=> $this->_fleet['fleet_end_id']
			);

			// fast way
			$collectFactor	= min(1, $totalStorage / $targetData['total']);
			foreach($debrisIDs as $debrisID)
			{
				$fleetColName	= 'fleet_resource_'.$resource[$debrisID];
				$debrisColName	= 'der_'.$resource[$debrisID];

				$collectedGoods[$debrisID]			= (int) ceil($targetData[$debrisColName] * $collectFactor);
				$collectQuery[]						= $debrisColName.' = GREATEST(0, '.$debrisColName.' - :'.$resource[$debrisID].')';
				$param[':'.$resource[$debrisID]]	= $collectedGoods[$debrisID];

				$this->UpdateFleet($fleetColName, $this->_fleet[$fleetColName] + $collectedGoods[$debrisID]);
			}

			$sql	= 'UPDATE %%PLANETS%% SET '.implode(',', $collectQuery).' WHERE id = :planetId;';
			Database::get()->update($sql, $param);
		}
		
		$LNG		= $this->getLanguage(NULL, $this->_fleet['fleet_owner']);
		
		$Message 	= sprintf($LNG['sys_recy_gotten'], 
			pretty_number($collectedGoods[901]), $LNG['tech'][901],
			pretty_number($collectedGoods[902]), $LNG['tech'][902]
		);

		PlayerUtil::sendMessage($this->_fleet['fleet_owner'], 0, $LNG['sys_mess_tower'], 5,
			$LNG['sys_recy_report'], $Message, $this->_fleet['fleet_start_time'], NULL, 1, $this->_fleet['fleet_universe']);

		$this->setState(FLEET_RETURN);
		$this->SaveFleet();
	}
	
	function EndStayEvent()
	{
		return;
	}
	
	function ReturnEvent()
	{
		$LNG		= $this->getLanguage(NULL, $this->_fleet['fleet_owner']);

		$sql		= 'SELECT name FROM %%PLANETS%% WHERE id = :planetId;';
		$planetName	= Database::get()->selectSingle($sql, array(
			':planetId'	=> $this->_fleet['fleet_start_id'],
		), 'name');
	
		$Message	= sprintf($LNG['sys_tran_mess_owner'],
			$planetName, GetStartAddressLink($this->_fleet, ''),
			pretty_number($this->_fleet['fleet_resource_metal']), $LNG['tech'][901],
			pretty_number($this->_fleet['fleet_resource_crystal']), $LNG['tech'][902],
			pretty_number($this->_fleet['fleet_resource_deuterium']), $LNG['tech'][903]
		);

		PlayerUtil::sendMessage($this->_fleet['fleet_owner'], 0, $LNG['sys_mess_tower'], 4, $LNG['sys_mess_fleetback'],
			$Message, $this->_fleet['fleet_end_time'], NULL, 1, $this->_fleet['fleet_universe']);

		$this->RestoreFleet();
	}
}