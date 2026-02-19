<?php

declare(strict_types=1);

/**
 * 2Moons 
 * by Jan-Otto Kröpke 2009-2016
 *
 * PHP 8.3/8.4 Optimized Statbuilder for SmartMoons
 */

class statbuilder
{
	private $starttime;
	private $memory;
	private $time;
	private $recordData = [];
	private $Unis = [];

	function __construct()
	{
		$this->starttime   	= microtime(true);
		$this->memory		= [round(memory_get_usage() / 1024,1),round(memory_get_usage(true) / 1024,1)];
		$this->time   		= TIMESTAMP;

		$uniResult	= Database::get()->select("SELECT uni FROM %%CONFIG%% ORDER BY uni ASC;");
		foreach($uniResult as $uni)
		{
			$this->Unis[]	= (int) $uni['uni'];
		}
	}

	private function SomeStatsInfos(): array
	{
		return [
			'stats_time'		=> $this->time,
			'totaltime'    		=> round(microtime(true) - $this->starttime, 7),
			'memory_peak'		=> [round(memory_get_peak_usage() / 1024,1), round(memory_get_peak_usage(true) / 1024,1)],
			'initial_memory'	=> $this->memory,
			'end_memory'		=> [round(memory_get_usage() / 1024,1), round(memory_get_usage(true) / 1024,1)],
			'sql_count'			=> Database::get()->getQueryCounter(),
		];
	}
	
	private function CheckUniverseAccounts(array $UniData): void
	{
		$UniData	= $UniData + array_combine($this->Unis, array_fill(0, count($this->Unis), 0));
		foreach($UniData as $Uni => $Amount) {
			$config	= Config::get((int)$Uni);
			$config->users_amount = (int)$Amount;
			$config->save();
		}
	}
	
	private function GetUsersInfosFromDB(): array
	{
		global $resource, $reslist;
		$select_defenses	= '';
		$select_buildings	= '';
		$selected_tech		= '';
		$select_fleets		= '';
				
		foreach($reslist['build'] as $Building){
			$select_buildings	.= " p.".$resource[$Building].",";
		}
		
		foreach($reslist['tech'] as $Techno){
			$selected_tech		.= " u.".$resource[$Techno].",";
		}	
		
		foreach($reslist['fleet'] as $Fleet){
			$select_fleets		.= " SUM(p.".$resource[$Fleet].") as ".$resource[$Fleet].",";
		}	
		
		foreach($reslist['defense'] as $Defense){
			$select_defenses	.= " SUM(p.".$resource[$Defense].") as ".$resource[$Defense].",";
		}
		
		foreach($reslist['missile'] as $Defense){
			$select_defenses	.= " SUM(p.".$resource[$Defense].") as ".$resource[$Defense].",";
		}

		$database		= Database::get();
		$FlyingFleets	= [];
		$SQLFleets		= $database->select('SELECT fleet_array, fleet_owner FROM %%FLEETS%%;');
		
		foreach($SQLFleets as $CurFleets)
		{
			$FleetRec   	= explode(";", (string)$CurFleets['fleet_array']);
				
			foreach($FleetRec as $Group) {
				if (empty($Group)) continue;
				
				$Ship    	   = explode(",", $Group);
				$ownerID       = (int)$CurFleets['fleet_owner'];
				$shipID        = (int)$Ship[0];
				$shipAmount    = (float)$Ship[1];

				if(!isset($FlyingFleets[$ownerID][$shipID]))
					$FlyingFleets[$ownerID][$shipID]	= $shipAmount;
				else
					$FlyingFleets[$ownerID][$shipID]	+= $shipAmount;
			}
		}
		
		$Return['Fleets'] 	= $FlyingFleets;		
		
		// KEIN FILTER MEHR: Bots werden mit geladen
		$Return['Planets']	= $database->select('SELECT SQL_BIG_RESULT DISTINCT '.$select_buildings.' p.id, p.universe, p.id_owner, u.authlevel, u.bana, u.username FROM %%PLANETS%% as p LEFT JOIN %%USERS%% as u ON u.id = p.id_owner;');
		
		$Return['Users']	= $database->select('SELECT SQL_BIG_RESULT DISTINCT '.$selected_tech.$select_fleets.$select_defenses.' u.id, u.ally_id, u.authlevel, u.bana, u.universe, u.username, s.tech_rank AS old_tech_rank, s.build_rank AS old_build_rank, s.defs_rank AS old_defs_rank, s.fleet_rank AS old_fleet_rank, s.total_rank AS old_total_rank FROM %%USERS%% as u LEFT JOIN %%STATPOINTS%% as s ON s.stat_type = 1 AND s.id_owner = u.id LEFT JOIN %%PLANETS%% as p ON u.id = p.id_owner GROUP BY u.id;');
		
		$Return['Alliance']	= $database->select('SELECT SQL_BIG_RESULT DISTINCT a.id, a.ally_universe, s.tech_rank AS old_tech_rank, s.build_rank AS old_build_rank, s.defs_rank AS old_defs_rank, s.fleet_rank AS old_fleet_rank, s.total_rank AS old_total_rank FROM %%ALLIANCE%% as a LEFT JOIN %%STATPOINTS%% as s ON s.stat_type = 2 AND s.id_owner = a.id;');
	
		return $Return;
	}
	
	private function setRecords(int $userID, int $elementID, float $amount): void
	{
		$this->recordData[$elementID][(string)$amount][]	= $userID;
	}
	
	private function writeRecordData(): void
	{
		$QueryData	= [];
		foreach($this->recordData as $elementID => $elementArray) {
			krsort($elementArray, SORT_NUMERIC);
			$userWinner		= reset($elementArray);
			$maxAmount		= key($elementArray);
			$userWinner		= array_unique($userWinner);

			if(count($userWinner) > 3)
			{
				$keys			= (array) array_rand($userWinner, 3);
				foreach($keys as $key)
				{
					$QueryData[]	= "(".$userWinner[$key].",".$elementID.",".$maxAmount.")";
				}
			}
			else
			{
				foreach($userWinner as $userID) {
					$QueryData[]	= "(".$userID.",".$elementID.",".$maxAmount.")";
				}
			}
		}
		
		if(!empty($QueryData)) {
			$this->SaveDataIntoDB("TRUNCATE TABLE %%RECORDS%%;");
			$this->SaveDataIntoDB("INSERT INTO %%RECORDS%% (userID, elementID, level) VALUES ".implode(', ', $QueryData).";");
		}
	}
	
	private function SaveDataIntoDB(string $Data): void
	{
		$queries	= explode(';', $Data);
		$queries	= array_filter($queries);
		foreach($queries as $query)
		{
			Database::get()->nativeQuery($query);
		}
	}

	private function GetTechnoPoints(array $USER): array 
	{
		global $resource, $reslist, $pricelist;
		$TechCounts = 0;
		$TechPoints = 0;

		foreach($reslist['tech'] as $Techno) 
		{
			$level = (float)($USER[$resource[$Techno]] ?? 0);
			if($level == 0) continue;

			$TechCounts		+= $level;
            $TechPoints     +=
                ($pricelist[$Techno]['cost'][901] + $pricelist[$Techno]['cost'][902] + $pricelist[$Techno]['cost'][903])
                * $pricelist[$Techno]['factor']
                * (
                    2 * (
                        pow($pricelist[$Techno]['factor'], $level) - $pricelist[$Techno]['factor']
                    ) + 1
                );

            $this->setRecords((int)$USER['id'], (int)$Techno, $level);
		}
		
		return ['count' => $TechCounts, 'points' => ($TechPoints / Config::get()->stat_settings)];
	}

	private function GetBuildPoints(array $PLANET): array 
	{
		global $resource, $reslist, $pricelist;
		$BuildCounts = 0;
		$BuildPoints = 0;
		
		foreach($reslist['build'] as $Build)
		{
			$level = (float)($PLANET[$resource[$Build]] ?? 0);
			if($level == 0) continue;

            $BuildPoints     +=
                ($pricelist[$Build]['cost'][901] + $pricelist[$Build]['cost'][902] + $pricelist[$Build]['cost'][903])
                * $pricelist[$Build]['factor']
                * (
                    2 * (
                        pow($pricelist[$Build]['factor'], $level) - $pricelist[$Build]['factor']
                    ) + 1
                );
			
			$BuildCounts	+= $level;
			
			$this->setRecords((int)$PLANET['id_owner'], (int)$Build, $level);
		}
		return ['count' => $BuildCounts, 'points' => ($BuildPoints / Config::get()->stat_settings)];
	}

	private function GetDefensePoints(array $USER): array 
	{
		global $resource, $reslist, $pricelist;
		$DefenseCounts = 0;
		$DefensePoints = 0;
				
		foreach(array_merge($reslist['defense'], $reslist['missile']) as $Defense) {
			$amount = (float)($USER[$resource[$Defense]] ?? 0);
			if($amount == 0) continue;
			
			$Units			= $pricelist[$Defense]['cost'][901] + $pricelist[$Defense]['cost'][902] + $pricelist[$Defense]['cost'][903];
			$DefensePoints += $Units * $amount;
			$DefenseCounts += $amount;
		
			$this->setRecords((int)$USER['id'], (int)$Defense, $amount);
		}
		
		return ['count' => $DefenseCounts, 'points' => ($DefensePoints / Config::get()->stat_settings)];
	}

	private function GetFleetPoints(array $USER): array 
	{
		global $resource, $reslist, $pricelist;
		$FleetCounts = 0;
		$FleetPoints = 0;
	
		foreach($reslist['fleet'] as $Fleet) {	
			$amount = (float)($USER[$resource[$Fleet]] ?? 0);
			if($amount == 0) continue;
			
			$Units			= $pricelist[$Fleet]['cost'][901] + $pricelist[$Fleet]['cost'][902] + $pricelist[$Fleet]['cost'][903];
			$FleetPoints   += $Units * $amount;
			$FleetCounts   += $amount;
			
			$this->setRecords((int)$USER['id'], (int)$Fleet, $amount);
		}
		
		return ['count' => $FleetCounts, 'points' => ($FleetPoints / Config::get()->stat_settings)];
	}
	
	private function SetNewRanks(): void
	{
		$db = Database::get();
		foreach($this->Unis as $uni)
		{
			foreach(['tech', 'build', 'defs', 'fleet', 'total'] as $type)
			{
				$db->nativeQuery('SET @i := 0;');
				$sql = 'UPDATE %%STATPOINTS%% SET '.$type.'_rank = (SELECT @i := @i + 1)
				WHERE universe = :uni AND stat_type = :type
				ORDER BY '.$type.'_points DESC, id_owner ASC;';

				$db->update($sql, [':uni' => $uni, ':type' => 1]);

				$db->nativeQuery('SET @i := 0;');
				$db->update($sql, [':uni' => $uni, ':type' => 2]);
			}
		}
	}
	
	final public function MakeStats(): array
	{
		global $resource;
		$AllyPoints	= [];
		$UserPoints	= [];
		$TotalData	= $this->GetUsersInfosFromDB();
		
		$this->SaveDataIntoDB('TRUNCATE TABLE %%STATPOINTS%%;');

		$tableHeader = "INSERT INTO %%STATPOINTS%% (id_owner, id_ally, stat_type, universe, tech_old_rank, tech_points, tech_count, build_old_rank, build_points, build_count, defs_old_rank, defs_points, defs_count, fleet_old_rank, fleet_points, fleet_count, total_old_rank, total_points, total_count) VALUES ";

		foreach($TotalData['Planets'] as $PlanetData)
		{		
			$ownerID = (int)$PlanetData['id_owner'];
			if((in_array(Config::get()->stat, [1, 2]) && $PlanetData['authlevel'] >= Config::get()->stat_level) || !empty($PlanetData['bana'])) continue;
			
 			if(!isset($UserPoints[$ownerID])) {
				$UserPoints[$ownerID]['build']['count'] = $UserPoints[$ownerID]['build']['points'] = 0;
			}
			
			$BuildPoints = $this->GetBuildPoints($PlanetData);
			$UserPoints[$ownerID]['build']['count']  += $BuildPoints['count'];
			$UserPoints[$ownerID]['build']['points'] += $BuildPoints['points'];
		}
		
		$UniData = [];
		$i = 0;
		$FinalSQL = $tableHeader;

		foreach($TotalData['Users'] as $UserData)
		{
		    $i++;
			$userID = (int)$UserData['id'];
			$uniID  = (int)$UserData['universe'];
			$allyID = (int)$UserData['ally_id'];

			if(!isset($UniData[$uniID])) $UniData[$uniID] = 0;
			$UniData[$uniID]++;
				
			if ((in_array(Config::get()->stat, [1, 2]) && $UserData['authlevel'] >= Config::get()->stat_level) || !empty($UserData['bana']))
			{	
				$FinalSQL .= "($userID,$allyID,1,$uniID,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0), ";
				continue;
			}

			if(isset($TotalData['Fleets'][$userID])) {
				foreach($TotalData['Fleets'][$userID] as $ID => $Amount)
					$UserData[$resource[$ID]] = ($UserData[$resource[$ID]] ?? 0) + $Amount;
			}
			
			$TechnoPoints = $this->GetTechnoPoints($UserData);
			$FleetPoints  = $this->GetFleetPoints($UserData);
			$DefensePoints = $this->GetDefensePoints($UserData);
			
			$UserPoints[$userID]['fleet']['count'] 		= $FleetPoints['count'];
			$UserPoints[$userID]['fleet']['points'] 	= $FleetPoints['points'];
			$UserPoints[$userID]['defense']['count'] 	= $DefensePoints['count'];
			$UserPoints[$userID]['defense']['points']	= $DefensePoints['points'];
			$UserPoints[$userID]['techno']['count'] 	= $TechnoPoints['count'];
			$UserPoints[$userID]['techno']['points'] 	= $TechnoPoints['points'];
			
			$UserPoints[$userID]['total']['count'] 		= (float)($UserPoints[$userID]['techno']['count'] ?? 0)
														+ (float)($UserPoints[$userID]['build']['count'] ?? 0)
														+ (float)($UserPoints[$userID]['defense']['count'] ?? 0)
														+ (float)($UserPoints[$userID]['fleet']['count'] ?? 0);
																
			$UserPoints[$userID]['total']['points'] 	= (float)($UserPoints[$userID]['techno']['points'] ?? 0)
														+ (float)($UserPoints[$userID]['build']['points'] ?? 0)
														+ (float)($UserPoints[$userID]['defense']['points'] ?? 0) 
														+ (float)($UserPoints[$userID]['fleet']['points'] ?? 0);

			if($allyID != 0)
			{
				if(!isset($AllyPoints[$allyID]))
				{
					foreach(['build','fleet','defense','techno','total'] as $cat) {
						$AllyPoints[$allyID][$cat]['count'] = 0;
						$AllyPoints[$allyID][$cat]['points'] = 0;
					}				
				}
			
				foreach(['build','fleet','defense','techno','total'] as $cat) {
					$AllyPoints[$allyID][$cat]['count']  += (float)($UserPoints[$userID][$cat]['count'] ?? 0);
					$AllyPoints[$allyID][$cat]['points'] += (float)($UserPoints[$userID][$cat]['points'] ?? 0);
				}
			}
			
			$FinalSQL .= "($userID, $allyID, 1, $uniID, ".
			(int)($UserData['old_tech_rank'] ?? 0).", ".
			(float)($UserPoints[$userID]['techno']['points'] ?? 0).", ".
			(float)($UserPoints[$userID]['techno']['count'] ?? 0).", ".
			(int)($UserData['old_build_rank'] ?? 0).", ".
			(float)($UserPoints[$userID]['build']['points'] ?? 0).", ".
			(float)($UserPoints[$userID]['build']['count'] ?? 0).", ".
			(int)($UserData['old_defs_rank'] ?? 0).", ".
			(float)($UserPoints[$userID]['defense']['points'] ?? 0).", ".
			(float)($UserPoints[$userID]['defense']['count'] ?? 0).", ".
			(int)($UserData['old_fleet_rank'] ?? 0).", ".
			(float)($UserPoints[$userID]['fleet']['points'] ?? 0).", ".
			(float)($UserPoints[$userID]['fleet']['count'] ?? 0).", ".
			(int)($UserData['old_total_rank'] ?? 0).", ".
			(float)($UserPoints[$userID]['total']['points'] ?? 0).", ".
			(float)($UserPoints[$userID]['total']['count'] ?? 0)."), ";

			if ($i >= 50) {
                $this->SaveDataIntoDB(substr($FinalSQL, 0, -2).';');
                $FinalSQL = $tableHeader;
                $i = 0;
			}
		}

		if ($FinalSQL != $tableHeader) {
            $this->SaveDataIntoDB(substr($FinalSQL, 0, -2).';');
		}

		if(!empty($AllyPoints))
		{
			$AllySQL = $tableHeader;
			$j = 0;
			foreach($TotalData['Alliance'] as $AllianceData)
			{
				$j++;
				$allyID = (int)$AllianceData['id'];
				$AllySQL .= "($allyID, 0, 2, ".(int)$AllianceData['ally_universe'].", ".
				(int)($AllianceData['old_tech_rank'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['techno']['points'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['techno']['count'] ?? 0).", ".
				(int)($AllianceData['old_build_rank'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['build']['points'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['build']['count'] ?? 0).", ".
				(int)($AllianceData['old_defs_rank'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['defense']['points'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['defense']['count'] ?? 0).", ".
				(int)($AllianceData['old_fleet_rank'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['fleet']['points'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['fleet']['count'] ?? 0).", ".
				(int)($AllianceData['old_total_rank'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['total']['points'] ?? 0).", ".
				(float)($AllyPoints[$allyID]['total']['count'] ?? 0)."), ";
				
				if($j >= 50) {
					$this->SaveDataIntoDB(substr($AllySQL, 0, -2).';');
					$AllySQL = $tableHeader;
					$j = 0;
				}
			}
			if($AllySQL != $tableHeader) {
				$this->SaveDataIntoDB(substr($AllySQL, 0, -2).';');
			}
		}

		$this->SetNewRanks();
		$this->CheckUniverseAccounts($UniData);		
		$this->writeRecordData();

		return $this->SomeStatsInfos();
	}
}
