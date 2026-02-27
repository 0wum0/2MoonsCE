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

if (defined('COMMON_PHP_LOADED')) {
	return;
}
define('COMMON_PHP_LOADED', true);

if (isset($_POST['GLOBALS']) || isset($_GET['GLOBALS'])) {
	exit('You cannot set the GLOBALS-array from outside the script.');
}

$composerAutoloader = __DIR__.'/../vendor/autoload.php';

if (file_exists($composerAutoloader)) {
    require_once $composerAutoloader;
}

if (function_exists('mb_internal_encoding')) {
	mb_internal_encoding("UTF-8");
}

ignore_user_abort(true);
error_reporting(E_ALL & ~E_STRICT);

// If date.timezone is invalid
date_default_timezone_set(@date_default_timezone_get());

ini_set('display_errors', '1');
header('Content-Type: text/html; charset=UTF-8');
if (!defined('TIMESTAMP')) {
    define('TIMESTAMP', time());
}
	
require_once 'includes/constants.php';

ini_set('log_errors', '1');
ini_set('error_log', 'includes/error.log');

require_once 'includes/GeneralFunctions.php';
set_exception_handler('exceptionHandler');
set_error_handler('errorHandler');

require_once 'includes/classes/ArrayUtil.class.php';
require_once 'includes/classes/Cache.class.php';
require_once 'includes/classes/Database.class.php';
require_once 'includes/classes/Config.class.php';
require_once 'includes/classes/class.FleetFunctions.php';
require_once 'includes/classes/HTTP.class.php';

require_once 'includes/classes/Language.class.php';

require_once 'includes/classes/PlayerUtil.class.php';
require_once 'includes/classes/Session.class.php';
require_once 'includes/classes/Universe.class.php';
require_once 'includes/classes/MathCaptcha.class.php';
require_once 'includes/classes/RegistrationRateLimit.class.php';

require_once 'includes/classes/class.theme.php';
require_once 'includes/classes/class.template.php';

require_once 'includes/classes/HookManager.class.php';
require_once 'includes/classes/AssetRegistry.class.php';
require_once 'includes/classes/PluginManager.class.php';
require_once __DIR__ . '/classes/ElementRegistry.class.php';

// ── v2 Full Modular Gameplay Engine ──────────────────────────────────────────
require_once __DIR__ . '/classes/GameModuleInterface.php';
require_once __DIR__ . '/classes/GameContext.class.php';
require_once __DIR__ . '/classes/ModuleManager.class.php';
require_once __DIR__ . '/classes/modules/ProductionModule.class.php';
require_once __DIR__ . '/classes/modules/QueueModule.class.php';

// Register core wrapper modules (priority 10 = before plugin modules at 100)
ModuleManager::get()->register(new ProductionModule(), 10);
ModuleManager::get()->register(new QueueModule(), 10);
// ─────────────────────────────────────────────────────────────────────────────

// Say Browsers to Allow ThirdParty Cookies (Thanks to morktadela)
HTTP::sendHeader('P3P', 'CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
define('AJAX_REQUEST', HTTP::_GP('ajax', 0));

$THEME		= new Theme();

if (MODE === 'INSTALL')
{
	return;
}

if(!file_exists('includes/config.php') || filesize('includes/config.php') === 0) {
	HTTP::redirectTo('install/index.php');
}

try {
    $sql	= "SELECT dbVersion FROM %%SYSTEM%%;";

    $dbVersion	= Database::get()->selectSingle($sql, array(), 'dbVersion');

    $dbNeedsUpgrade = $dbVersion < DB_VERSION_REQUIRED;
} catch (Exception $e) {
    $dbNeedsUpgrade = true;
}

if ($dbNeedsUpgrade) {
    HTTP::redirectTo('install/index.php?mode=upgrade');
}

if(defined('DATABASE_VERSION') && DATABASE_VERSION === 'OLD')
{
	/* For our old Admin panel */
	require_once 'includes/classes/Database_BC.class.php';
	$DATABASE	= new Database_BC();
	
	$dbTableNames	= Database::get()->getDbTableNames();
	$dbTableNames	= array_combine($dbTableNames['keys'], $dbTableNames['names']);
	
	foreach($dbTableNames as $dbAlias => $dbName)
	{
		define(substr($dbAlias, 2, -2), $dbName);
	}	
}

$config = Config::get();
date_default_timezone_set($config->timezone);

if (MODE === 'INGAME' || MODE === 'ADMIN')
{
	$session	= Session::load();

	if(!$session->isValidSession())
	{
	    $session->delete();
		HTTP::redirectTo('index.php?code=3');
	}

	require_once 'includes/vars.php';
	require_once 'includes/classes/class.BuildFunctions.php';
	require_once 'includes/classes/class.PlanetRessUpdate.php';
	
	if(!AJAX_REQUEST && MODE === 'INGAME' && isModuleAvailable(MODULE_FLEET_EVENTS)) {
		require('includes/FleetHandler.php');
	}
	
	$db		= Database::get();

	$sql	= "SELECT 
	user.*,
	COUNT(message.message_id) as messages
	FROM %%USERS%% as user
	LEFT JOIN %%MESSAGES%% as message ON message.message_owner = user.id AND message.message_unread = :unread
	WHERE user.id = :userId
	GROUP BY message.message_owner;";
	
	$USER	= $db->selectSingle($sql, array(
		':unread'	=> 1,
		':userId'	=> $session->userId
	));
	
	if(empty($USER))
	{
		HTTP::redirectTo('index.php?code=3');
	}
	
	$LNG	= new Language($USER['lang']);
	$LNG->includeData(array('L18N', 'INGAME', 'TECH', 'CUSTOM'));
	$THEME->setUserTheme($USER['dpath']);

	PluginManager::get()->loadActivePlugins();

	// ── Plugin System v1.2 – Dynamic Element Registry Bridge ─────────────────
	// Order:
	//   1. loadActivePlugins() above has already included all plugin.php files,
	//      so plugins may have called registerElementsCallback().
	//   2. Boot the registry from the legacy cache arrays (once per request).
	//   3. Dispatch registerElements() to all plugins that registered a callback.
	//   4. Export updated arrays back into the legacy globals (only if plugins
	//      actually registered new elements – Invariant A).
	//   5. Run the existing v1.1 filter hooks on top (backwards-compatible).
	if (MODE === 'INGAME' || MODE === 'ADMIN') {
		$_reg = ElementRegistry::get();
		$_reg->bootFromLegacyArrays(
			$resource,
			$pricelist,
			$reslist,
			$requeriments,
			['CombatCaps' => $CombatCaps, 'ProdGrid' => $ProdGrid]
		);

		// Let plugins register new elements via the registry API
		PluginManager::get()->dispatchRegisterElements($_reg);

		// Always normalize reslist['allow'] keys to int so that
		// $reslist['allow'][$PLANET['planet_type']] works correctly
		// (VarsBuildCache produces string keys via explode()).
		$reslist = $_reg->exportLegacyReslist($reslist);

		// Always export all arrays – exportLegacyPricelist is merge-additive
		// (skips _fromLegacy elements) so this is safe even with no active plugins.
		$pricelist    = $_reg->exportLegacyPricelist($pricelist);
		$resource     = $_reg->exportLegacyResourceMap($resource);
		$requeriments = $_reg->exportLegacyRequirements($requeriments);
		$CombatCaps   = $_reg->exportLegacyCombatCaps($CombatCaps);
		$ProdGrid     = $_reg->exportLegacyProdGrid($ProdGrid);
		unset($_reg);

		// ── Plugin System v1.1 – Game Data Hooks (still active) ───────────────
		// applyFilters() is a no-op when no filter is registered for a hook.
		$_ghook       = HookManager::get();
		$resource     = $_ghook->applyFilters('game.resourceMap',  $resource);
		$pricelist    = $_ghook->applyFilters('game.pricelist',    $pricelist);
		$requeriments = $_ghook->applyFilters('game.requirements', $requeriments);
		$ProdGrid     = $_ghook->applyFilters('game.prodGrid',     $ProdGrid);
		$CombatCaps   = $_ghook->applyFilters('game.combatCaps',   $CombatCaps);
		$reslist      = $_ghook->applyFilters('game.reslist',      $reslist);
		unset($_ghook);
	}
	// ─────────────────────────────────────────────────────────────────────────

	if($config->game_disable == 0 && $USER['authlevel'] == AUTH_USR) {
		ShowErrorPage::printError($LNG['sys_closed_game'].'<br><br>'.$config->close_reason, false);
	}

	if($USER['bana'] == 1) {
		ShowErrorPage::printError("<font size=\"6px\">".$LNG['css_account_banned_message']."</font><br><br>".sprintf($LNG['css_account_banned_expire'], _date($LNG['php_tdformat'], $USER['banaday'], $USER['timezone']))."<br><br>".$LNG['css_goto_homeside'], false);
	}
	
	if (MODE === 'INGAME')
	{
		$universeAmount	= count(Universe::availableUniverses());
		if(Universe::current() != $USER['universe'] && $universeAmount > 1)
		{
			HTTP::redirectToUniverse($USER['universe']);
		}

		$session->selectActivePlanet();

		$sql	= "SELECT * FROM %%PLANETS%% WHERE id = :planetId;";
		$PLANET	= $db->selectSingle($sql, array(
			':planetId'	=> $session->planetId,
		));

		if(empty($PLANET))
		{
			$sql	= "SELECT * FROM %%PLANETS%% WHERE id = :planetId;";
			$PLANET	= $db->selectSingle($sql, array(
				':planetId'	=> $USER['id_planet'],
			));
			
			if(empty($PLANET))
			{
				throw new Exception("Main Planet does not exist!");
			}
			else
			{
				$session->planetId = $USER['id_planet'];
			}
		}
		
		$USER['factor']		= getFactors($USER);
		$USER['PLANETS']	= getPlanets($USER);

		// ── Plugin System v1.2 – Planet data defaults for registry-only elements
		// Injects 0-defaults for any nameKey that is not a real DB column, so that
		// $PLANET[$resource[$Element]] never triggers E_WARNING for missing keys.
		$PLANET = HookManager::get()->applyFilters('game.planet', $PLANET);

		// ── v2 ModuleManager boot (INGAME) ────────────────────────────────────
		// USER, PLANET, resource, pricelist etc. are all fully populated here.
		$_modCtx = GameContext::fromGlobals();
		ModuleManager::get()->boot($_modCtx);
		ModuleManager::get()->beforeRequest($_modCtx);
		unset($_modCtx);
		// ─────────────────────────────────────────────────────────────────────
	}
	elseif (MODE === 'ADMIN')
	{
		error_reporting(E_ERROR | E_WARNING | E_PARSE);
		
		// Fix: robust handling for empty/null/invalid rights in PHP 8.3
		if (!empty($USER['rights']) && is_string($USER['rights'])) {
			$decoded = @unserialize($USER['rights']);
			if ($decoded === false && $USER['rights'] !== 'b:0;') {
				$USER['rights'] = [];
			} else {
				$USER['rights'] = $decoded;
			}
		} else {
			$USER['rights'] = [];
		}

		$LNG->includeData(array('ADMIN', 'CUSTOM'));

		// ── v2 ModuleManager boot (ADMIN) ─────────────────────────────────────
		$_modCtx = GameContext::fromGlobals();
		ModuleManager::get()->boot($_modCtx);
		ModuleManager::get()->beforeRequest($_modCtx);
		unset($_modCtx);
		// ─────────────────────────────────────────────────────────────────────
	}
}
elseif(MODE === 'LOGIN')
{
	$LNG	= new Language();
	$LNG->getUserAgentLanguage();
	$LNG->includeData(array('L18N', 'INGAME', 'PUBLIC', 'CUSTOM'));
}
elseif(MODE === 'CHAT')
{
	$session	= Session::load();

	if(!$session->isValidSession())
	{
		HTTP::redirectTo('index.php?code=3');
	}
}
