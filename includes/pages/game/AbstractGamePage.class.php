<?php

declare(strict_types=1);
/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

abstract class AbstractGamePage
{
	/**
	 * reference of the template object
	 * @var template
	 */
	protected $tplObj;

	/**
	 * reference of the template object
	 * @var ResourceUpdate
	 */
	protected $ecoObj;
	protected $window;
	protected $disableEcoSystem = false;

	protected function __construct() {

		if(!AJAX_REQUEST)
		{
			$this->setWindow('full');
			if(!$this->disableEcoSystem)
			{
				$this->ecoObj	= new ResourceUpdate();
				$this->ecoObj->CalcResource();
			}
			$this->initTemplate();
		} else {
			$this->setWindow('ajax');
		}
	}

	protected function initTemplate() {
		if(isset($this->tplObj))
			return true;

		$this->tplObj	= new template;
		list($tplDir)	= $this->tplObj->getTemplateDir();
		$this->tplObj->setTemplateDir($tplDir.'game/');
		return true;
	}

	protected function setWindow($window) {
		$this->window	= $window;
	}

	protected function getWindow() {
		return $this->window;
	}

	protected function getQueryString() {
		$queryString	= array();
		$page			= HTTP::_GP('page', '');

		if(!empty($page)) {
			$queryString['page']	= $page;
		}

		$mode			= HTTP::_GP('mode', '');
		if(!empty($mode)) {
			$queryString['mode']	= $mode;
		}

		return http_build_query($queryString);
	}

	protected function getCronjobsTodo()
	{
		require_once 'includes/classes/Cronjob.class.php';

		$this->assign(array(
			'cronjobs'		=> Cronjob::getNeedTodoExecutedJobs()
		));
	}

	protected function getNavigationData()
	{
		global $PLANET, $LNG, $USER, $THEME, $resource, $reslist;

		$config			= Config::get();
		$db 			= Database::get();

		$PlanetSelect	= array();

		if(isset($USER['PLANETS'])) {
			$USER['PLANETS']	= getPlanets($USER);
		}

		foreach($USER['PLANETS'] as $PlanetQuery)
		{
			$PlanetSelect[$PlanetQuery['id']]	= $PlanetQuery['name'].(($PlanetQuery['planet_type'] == 3) ? " (" . $LNG['fcm_moon'] . ")":"")." [".$PlanetQuery['galaxy'].":".$PlanetQuery['system'].":".$PlanetQuery['planet']."]";
		}

		$resourceTable	= array();
		$resourceSpeed	= $config->resource_multiplier;
		foreach($reslist['resstype'][1] as $resourceID)
		{
			$resourceTable[$resourceID]['name']			= $resource[$resourceID];
			$resourceTable[$resourceID]['current']		= $PLANET[$resource[$resourceID]];
			$resourceTable[$resourceID]['max']			= $PLANET[$resource[$resourceID].'_max'];
			if($USER['urlaubs_modus'] == 1 || $PLANET['planet_type'] != 1)
			{
				$resourceTable[$resourceID]['production']	= $PLANET[$resource[$resourceID].'_perhour'];
			}
			else
			{
				$resourceTable[$resourceID]['production']	= $PLANET[$resource[$resourceID].'_perhour'] + $config->{$resource[$resourceID].'_basic_income'} * $resourceSpeed;
			}
		}

		foreach($reslist['resstype'][2] as $resourceID)
		{
			$resourceTable[$resourceID]['name']			= $resource[$resourceID];
			$resourceTable[$resourceID]['used']			= $PLANET[$resource[$resourceID].'_used'];
			$resourceTable[$resourceID]['max']			= $PLANET[$resource[$resourceID]];
		}

		foreach($reslist['resstype'][3] as $resourceID)
		{
			$resourceTable[$resourceID]['name']			= $resource[$resourceID];
			$resourceTable[$resourceID]['current']		= $USER[$resource[$resourceID]];
		}

		/**
		 * Addition for moving planet with arrows
		 * Ajout pour le déplacement de planète avec les flèches
		**/
		$previousPlanet = $db->selectSingle("SELECT id FROM %%PLANETS%% WHERE id < :planetID AND id_owner = :userID AND destruyed = '0' ORDER BY id DESC LIMIT 1 ;", array(':planetID' => $PLANET['id'], ':userID' => $USER['id']));
		$nextPlanet = $db->selectSingle("SELECT id FROM %%PLANETS%% WHERE id > :planetID AND id_owner = :userID AND destruyed = '0' ORDER BY id ASC LIMIT 1 ;", array(':planetID' => $PLANET['id'], ':userID' => $USER['id']));

		$themeSettings	= $THEME->getStyleSettings();

		// Pre-encode fleet data as safe JSON — avoids Twig HTML escaping breaking JS.
		// Use UTF-8 substitution + fallback to avoid broken header JS when one value has invalid encoding.
		$fleetMovements = $this->getGlobalFleetMovements();
		$fleetMovementList = array_values($fleetMovements);
		$globalFleetJSON = json_encode(
			$fleetMovementList,
			JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
		);
		if($globalFleetJSON === false)
		{
			$globalFleetJSON = '[]';
		}

		$forumNotifCount = 0;
		try {
			if (!class_exists('Forum')) {
				require_once ROOT_PATH . 'includes/classes/Forum.class.php';
			}
			$forumObj = new Forum();
			$forumNotifCount = $forumObj->getForumNotificationCount((int)$USER['id']);
		} catch (Throwable $e) {
			$forumNotifCount = 0;
		}

		$this->assign(array(
			'PlanetSelect'		=> $PlanetSelect,
			'globalFleetMovements' => $fleetMovements,
			'globalFleetMovementsList' => $fleetMovementList,
			'globalFleetJSON'	=> $globalFleetJSON,
			'new_message' 		=> $USER['messages'],
			'newMessageCount'	=> (int) $USER['messages'],
			'buildQueue'		=> $this->getGlobalBuildQueues(),
			'recentMessages'	=> $this->getRecentMessages(),
			'forum_notif_count'	=> $forumNotifCount,
			'vacation'			=> $USER['urlaubs_modus'] ? _date($LNG['php_tdformat'], $USER['urlaubs_until'], $USER['timezone']) : false,
			'delete'			=> $USER['db_deaktjava'] ? sprintf($LNG['tn_delete_mode'], _date($LNG['php_tdformat'], $USER['db_deaktjava'] + ($config->del_user_manually * 86400)), $USER['timezone']) : false,
			'darkmatter'		=> $USER['darkmatter'],
			'current_pid'		=> $PLANET['id'],
			'image'				=> $PLANET['image'],
			'resourceTable'		=> $resourceTable,
			'shortlyNumber'		=> $themeSettings['TOPNAV_SHORTLY_NUMBER'],
			'closed'			=> !$config->game_disable,
			'hasBoard'			=> filter_var($config->forum_url, FILTER_VALIDATE_URL),
			'hasAdminAccess'	=> !empty(Session::load()->adminAccess),
			'hasGate'			=> $PLANET[$resource[43]] > 0,
			'username'			=> $USER['username'],
			'previousPlanet'	=> (!empty($previousPlanet))?$previousPlanet['id']:$PLANET['id'],
			'nextPlanet'		=> (!empty($nextPlanet))?$nextPlanet['id']:$PLANET['id'],
		));
	}

	protected function getGlobalFleetMovements(): array
	{
		global $USER, $PLANET;

		if(!isModuleAvailable(MODULE_FLEET_TABLE) || empty($USER['id']) || empty($PLANET['id']))
		{
			return array();
		}

		require_once 'includes/classes/class.FlyingFleetsTable.php';

		$fleetTableObj = new FlyingFleetsTable;
		$fleetTableObj->setUser((int) $USER['id']);
		$fleetTableObj->setPlanet((int) $PLANET['id']);

		return $fleetTableObj->renderTable();
	}

	/**
	 * Get build queue data for header notifications.
	 * Returns ALL items in building/research/hangar queues + recent messages.
	 */
	protected function getGlobalBuildQueues(): array
	{
		global $USER, $PLANET, $LNG, $resource;

		$queues = array(
			'building' => false,
			'research' => false,
			'hangar'   => false,
		);

		// --- Gebäude-Bauschleife (alle Items) ---
		if (!empty($PLANET['b_building']) && $PLANET['b_building'] > TIMESTAMP) {
			$bQueue = @unserialize($PLANET['b_building_id']);
			if (!empty($bQueue) && isset($bQueue[0])) {
				$items = array();
				$totalBuildTime = 0;
				foreach ($bQueue as $idx => $entry) {
					$itemDuration = isset($entry[2]) ? (int) $entry[2] : 0;
					$totalBuildTime += $itemDuration;
					$items[] = array(
						'id'       => (int) $entry[0],
						'name'     => $LNG['tech'][$entry[0]] ?? 'Gebäude #'.$entry[0],
						'level'    => (int) $entry[1],
						'duration' => $itemDuration,
						'endTime'  => isset($entry[3]) ? (int) $entry[3] : 0,
					);
				}
				$timeLeft = max(0, (int) $PLANET['b_building'] - TIMESTAMP);
				$queues['building'] = array(
					'items'     => $items,
					'endTime'   => (int) $PLANET['b_building'],
					'timeLeft'  => $timeLeft,
					'totalTime' => max(1, $totalBuildTime),
					'elapsed'   => max(0, $totalBuildTime - $timeLeft),
					'count'     => count($bQueue),
				);
			}
		}

		// --- Forschung (alle Items) ---
		if (!empty($USER['b_tech']) && $USER['b_tech'] > TIMESTAMP) {
			$tQueue = @unserialize($USER['b_tech_queue']);
			if (!empty($tQueue) && isset($tQueue[0])) {
				$items = array();
				$totalResearchTime = 0;
				foreach ($tQueue as $idx => $entry) {
					$itemDuration = isset($entry[2]) ? (int) $entry[2] : 0;
					$totalResearchTime += $itemDuration;
					$items[] = array(
						'id'       => (int) $entry[0],
						'name'     => $LNG['tech'][$entry[0]] ?? 'Forschung #'.$entry[0],
						'level'    => (int) $entry[1],
						'duration' => $itemDuration,
					);
				}
				$timeLeft = max(0, (int) $USER['b_tech'] - TIMESTAMP);
				$queues['research'] = array(
					'items'     => $items,
					'endTime'   => (int) $USER['b_tech'],
					'timeLeft'  => $timeLeft,
					'totalTime' => max(1, $totalResearchTime),
					'elapsed'   => max(0, $totalResearchTime - $timeLeft),
					'count'     => count($tQueue),
				);
			}
		}

		// --- Hangar (alle Einheiten-Typen) ---
		if (!empty($PLANET['b_hangar_id'])) {
			$hQueue = @unserialize($PLANET['b_hangar_id']);
			if (!empty($hQueue) && isset($hQueue[0])) {
				require_once 'includes/classes/class.BuildFunctions.php';
				$items = array();
				$totalItems = 0;
				foreach ($hQueue as $entry) {
					$cnt = (int) $entry[1];
					$totalItems += $cnt;
					$items[] = array(
						'id'    => (int) $entry[0],
						'name'  => $LNG['tech'][$entry[0]] ?? 'Einheit #'.$entry[0],
						'count' => $cnt,
					);
				}
				$totalTime = BuildFunctions::getBuildingTime($USER, $PLANET, $hQueue[0][0]) * $hQueue[0][1];
				$elapsed   = !empty($PLANET['b_hangar']) ? (int) $PLANET['b_hangar'] : 0;

				$queues['hangar'] = array(
					'items'      => $items,
					'totalTime'  => $totalTime,
					'elapsed'    => $elapsed,
					'timeLeft'   => max(0, $totalTime - $elapsed),
					'totalItems' => $totalItems,
					'count'      => count($hQueue),
				);
			}
		}

		return $queues;
	}

	/**
	 * Get recent unread messages for header notification preview.
	 * Returns up to 5 messages with subject and text preview.
	 */
	protected function getRecentMessages(): array
	{
		global $USER;
		$db = Database::get();

		$sql = "SELECT message_id, message_sender, message_subject, message_text, message_time, message_type
				FROM %%MESSAGES%%
				WHERE message_owner = :userId AND message_unread = 1
				ORDER BY message_time DESC
				LIMIT 5;";

		$result = $db->select($sql, array(':userId' => $USER['id']));

		$messages = array();
		foreach ($result as $row) {
			$preview = strip_tags($row['message_text']);
			if (mb_strlen($preview) > 120) {
				$preview = mb_substr($preview, 0, 120) . '...';
			}
			$messages[] = array(
				'id'      => (int) $row['message_id'],
				'sender'  => (int) $row['message_sender'],
				'subject' => $row['message_subject'],
				'preview' => $preview,
				'time'    => (int) $row['message_time'],
				'type'    => (int) $row['message_type'],
			);
		}
		return $messages;
	}

	/**
	 * Resolves a timezone value to a valid timezone string for DateTimeZone.
	 * Handles invalid values (numeric, empty, invalid) by falling back to UTC.
	 * 
	 * @param mixed $userTz The user timezone value to resolve
	 * @param mixed $configTz The config timezone value as fallback
	 * @return string A valid timezone string
	 */
	private function resolveTimezoneString($userTz, $configTz = 'UTC'): string
	{
		// Priority: userTz -> configTz -> 'UTC'
		$tz = $userTz ?? $configTz ?? 'UTC';
		
		// Reject numeric, empty, or non-string values
		if (!is_string($tz) || $tz === '' || is_numeric($tz)) {
			return 'UTC';
		}
		
		// Clean and validate
		$tz = trim($tz);
		
		// Try to validate by attempting to create DateTimeZone
		try {
			new DateTimeZone($tz);
			return $tz;
		} catch (Throwable $e) {
			return 'UTC';
		}
	}

	protected function getPageData()
	{
		global $USER, $THEME;

		if($this->getWindow() === 'full') {
			$this->getNavigationData();
			$this->getCronjobsTodo();
		}

		$config	= Config::get();
		
		$dateTimeServer		= new DateTime("now");
		
		// Resolve timezone with robust handling for PHP 8.3+
		$timezoneString = $this->resolveTimezoneString(
			$USER['timezone'] ?? null,
			$config->timezone ?? 'UTC'
		);
		
		try {
			$dateTimeUser	= new DateTime("now", new DateTimeZone($timezoneString));
		} catch (Throwable $e) {
			// Ultimate fallback: use server time if timezone creation still fails
			$dateTimeUser	= $dateTimeServer;
		}

		$this->assign(array(
			'vmode'				=> $USER['urlaubs_modus'],
			'authlevel'			=> $USER['authlevel'],
			'userID'			=> $USER['id'],
			'bodyclass'			=> $this->getWindow(),
			'game_name'			=> $config->game_name,
			'uni_name'			=> $config->uni_name,
			'ga_active'			=> $config->ga_active,
			'ga_key'			=> $config->ga_key,
			'debug'				=> $config->debug,
			'VERSION'			=> $config->VERSION,
			'date'				=> explode("|", date('Y\|n\|j\|G\|i\|s\|Z', TIMESTAMP)),
			'isPlayerCardActive' => isModuleAvailable(MODULE_PLAYERCARD),
			'REV'				=> substr($config->VERSION, -4),
			'Offset'			=> $dateTimeUser->getOffset() - $dateTimeServer->getOffset(),
			'queryString'		=> $this->getQueryString(),
			'themeSettings'		=> $THEME->getStyleSettings(),
		));
	}
	protected function printMessage($message, $redirectButtons = NULL, $redirect = NULL, $fullSide = true)
	{
		$this->assign(array(
			'message'			=> $message,
			'redirectButtons'	=> $redirectButtons,
		));

		if(isset($redirect)) {
			$this->tplObj->gotoside($redirect[0], $redirect[1]);
		}

		if(!$fullSide) {
			$this->setWindow('popup');
		}

		$this->display('error.default.tpl');
	}

	protected function save() {
		if(isset($this->ecoObj)) {
			$this->ecoObj->SavePlanetToDB();
		}
	}

	protected function assign($array, $nocache = true) {
		$this->tplObj->assign_vars($array, $nocache);
	}

	protected function display($file) {
		global $THEME, $LNG, $USER;

		$this->save();

		if($this->getWindow() !== 'ajax') {
			$this->getPageData();
		}

		HookManager::get()->doAction('beforeController', ['page' => HTTP::_GP('page', '')]);

		$currentPage = HTTP::_GP('page', 'overview');

		$pluginCss = [];
		$pluginJs  = [];
		if (class_exists('AssetRegistry')) {
			$pluginCss = AssetRegistry::get()->getCssForPage($currentPage);
			$pluginJs  = AssetRegistry::get()->getJsForPage($currentPage);
		}

		$this->assign(array(
			'lang'    		=> $LNG->getLanguage(),
			'dpath'			=> $THEME->getTheme(),
			'scripts'		=> $this->tplObj->jsscript,
			'execscript'	=> implode("\n", $this->tplObj->script),
			'basepath'		=> PROTOCOL.HTTP_HOST.HTTP_BASE,
			'servertime'	=> _date("M D d H:i:s", TIMESTAMP, $USER['timezone']),
			'pluginCss'		=> $pluginCss,
			'pluginJs'		=> $pluginJs,
		));

		$this->assign(array(
			'LNG'			=> $LNG,
		), false);

		HookManager::get()->doAction('afterController', ['page' => $currentPage]);

		// Flush any output buffer opened by the front controller (e.g. game.php ob_start())
		// so the text/html Content-Type header is actually sent before Twig outputs.
		while (ob_get_level() > 0) { ob_end_flush(); }

		// Convert .tpl extension to .twig
		$twigFile = str_replace('.tpl', '.twig', $file);
		
		// Render the page template directly - Twig will handle the extends directive
		$this->tplObj->display($twigFile);
		exit;
	}

	protected function sendJSON($data) {
		$this->save();
		echo json_encode($data);
		exit;
	}

	/**
	 * Send a successful AJAX response including current resource values.
	 * Replaces bare sendJSON(['ok'=>true]) for queue/action pages so the
	 * JS resource bar can update without a separate poll request.
	 *
	 * @param array $extra  Additional keys merged into the response.
	 */
	protected function sendAjaxSuccess(array $extra = []): void
	{
		global $PLANET, $USER, $resource, $reslist;

		$this->save();

		$config = Config::get();
		$resourceSpeed = $config->resource_multiplier;
		$resources = [];

		foreach ($reslist['resstype'][1] as $resourceID) {
			$name = $resource[$resourceID];
			$current    = (float) ($PLANET[$name] ?? 0);
			$max        = (float) ($PLANET[$name . '_max'] ?? 0);
			if ($USER['urlaubs_modus'] == 1 || $PLANET['planet_type'] != 1) {
				$production = (float) ($PLANET[$name . '_perhour'] ?? 0);
			} else {
				$production = (float) ($PLANET[$name . '_perhour'] ?? 0)
				            + (float) ($config->{$name . '_basic_income'} ?? 0) * $resourceSpeed;
			}
			$resources[$name] = [
				'current'    => $current,
				'max'        => $max,
				'production' => $production,
			];
		}

		$response = array_merge(['ok' => true, 'resources' => $resources], $extra);
		echo json_encode($response);
		exit;
	}

	/**
	 * Send current planet resource state as JSON for AJAX resource bar refresh.
	 * Called when ajax=resources is present in the request (detected by game.php).
	 * Returns a map of resource name → {current, max, production}.
	 */
	public function sendResourceJSON(): void
	{
		global $PLANET, $USER, $resource, $reslist;

		$this->save();

		$config = Config::get();
		$resourceSpeed = $config->resource_multiplier;
		$out = [];

		foreach ($reslist['resstype'][1] as $resourceID) {
			$name = $resource[$resourceID];
			$current    = (float) ($PLANET[$name] ?? 0);
			$max        = (float) ($PLANET[$name . '_max'] ?? 0);
			if ($USER['urlaubs_modus'] == 1 || $PLANET['planet_type'] != 1) {
				$production = (float) ($PLANET[$name . '_perhour'] ?? 0);
			} else {
				$production = (float) ($PLANET[$name . '_perhour'] ?? 0)
				            + (float) ($config->{$name . '_basic_income'} ?? 0) * $resourceSpeed;
			}
			$out[$name] = [
				'current'    => $current,
				'max'        => $max,
				'production' => $production,
			];
		}

		while (ob_get_level() > 0) { ob_end_clean(); }
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($out, JSON_UNESCAPED_UNICODE);
		exit;
	}

	protected function redirectTo($url) {
		$this->save();
		HTTP::redirectTo($url);
		exit;
	}
}