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

class MissionCaseTransport extends MissionFunctions implements Mission
{
	function __construct($Fleet)
	{
		$this->_fleet	= $Fleet;
	}
	
	private function getPlanetNameByFleetType(int $type, int $id): string
	{
		$db = Database::get();

		// Type: 1 = planet, 2 = debris, 3 = moon (2Moons typical)
		if ($type === 3) {
			// Some 2Moons setups reference moons via id_luna on the planet row
			$sql = 'SELECT name FROM %%PLANETS%% WHERE id = :moonId OR id_luna = :moonId LIMIT 1;';
			$name = $db->selectSingle($sql, array(':moonId' => $id), 'name');
			return (string) $name;
		}

		$sql = 'SELECT name FROM %%PLANETS%% WHERE id = :planetId;';
		$name = $db->selectSingle($sql, array(':planetId' => $id), 'name');
		return (string) $name;
	}
	
	function TargetEvent()
	{
		$startPlanetName	= $this->getPlanetNameByFleetType((int)$this->_fleet['fleet_start_type'], (int)$this->_fleet['fleet_start_id']);
		$targetPlanetName	= $this->getPlanetNameByFleetType((int)$this->_fleet['fleet_end_type'], (int)$this->_fleet['fleet_end_id']);
		
		$LNGOwner			= $this->getLanguage(NULL, $this->_fleet['fleet_owner']);

		$MessageOwner		= sprintf($LNGOwner['sys_tran_mess_owner'],
			$targetPlanetName, GetTargetAddressLink($this->_fleet, ''),
			pretty_number($this->_fleet['fleet_resource_metal']), $LNGOwner['tech'][901],
			pretty_number($this->_fleet['fleet_resource_crystal']), $LNGOwner['tech'][902],
			pretty_number($this->_fleet['fleet_resource_deuterium']), $LNGOwner['tech'][903]
		);

		PlayerUtil::sendMessage($this->_fleet['fleet_owner'], 0, $LNGOwner['sys_mess_tower'], 5,
			$LNGOwner['sys_mess_transport'], $MessageOwner, $this->_fleet['fleet_start_time'], NULL, 1, $this->_fleet['fleet_universe']);

		if ($this->_fleet['fleet_target_owner'] != $this->_fleet['fleet_owner']) 
		{
			$LNGTarget		= $this->getLanguage(NULL, $this->_fleet['fleet_target_owner']);
			$MessageTarget  = sprintf($LNGTarget['sys_tran_mess_user'],
				$startPlanetName, GetStartAddressLink($this->_fleet, ''),
				$targetPlanetName, GetTargetAddressLink($this->_fleet, ''),
				pretty_number($this->_fleet['fleet_resource_metal']), $LNGTarget['tech'][901],
				pretty_number($this->_fleet['fleet_resource_crystal']), $LNGTarget['tech'][902],
				pretty_number($this->_fleet['fleet_resource_deuterium']), $LNGTarget['tech'][903]
			);

			PlayerUtil::sendMessage($this->_fleet['fleet_target_owner'], 0, $LNGTarget['sys_mess_tower'], 5,
				$LNGTarget['sys_mess_transport'], $MessageTarget, $this->_fleet['fleet_start_time'], NULL, 1, $this->_fleet['fleet_universe']);
		}
	
		$this->StoreGoodsToPlanet();
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

		$planetName	= $this->getPlanetNameByFleetType((int)$this->_fleet['fleet_start_type'], (int)$this->_fleet['fleet_start_id']);

		$Message	= sprintf($LNG['sys_tran_mess_back'], $planetName, GetStartAddressLink($this->_fleet, ''));

		PlayerUtil::sendMessage($this->_fleet['fleet_owner'], 0, $LNG['sys_mess_tower'], 4, $LNG['sys_mess_fleetback'],
			$Message, $this->_fleet['fleet_end_time'], NULL, 1, $this->_fleet['fleet_universe']);

		$this->RestoreFleet();
	}
}