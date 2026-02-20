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
 * @copyright 2016 Jan-Otto Kröpke
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

function getFactors(array $USER, string $Type = 'basic', ?int $TIME = null): array {
	global $PLANET, $resource, $pricelist, $reslist;
	if(empty($TIME))
		$TIME	= TIMESTAMP;
	
	$bonusList	= BuildFunctions::getBonusList();
	$factor		= ArrayUtil::combineArrayWithSingleElement($bonusList, 0);
	
	foreach($reslist['bonus'] as $elementID) {
		$bonus = $pricelist[$elementID]['bonus'];
		
		if (isset($PLANET[$resource[$elementID]])) {
			$elementLevel = $PLANET[$resource[$elementID]];
		} elseif (isset($USER[$resource[$elementID]])) {
			$elementLevel = $USER[$resource[$elementID]];
		} else {
			continue;
		}
		
		if(in_array($elementID, $reslist['dmfunc'])) {
			if(DMExtra($elementLevel, $TIME, false, true)) {
				continue;
			}
			
			foreach($bonusList as $bonusKey)
			{
				$factor[$bonusKey]	+= $bonus[$bonusKey][0];
			}
		} else {
			foreach($bonusList as $bonusKey)
			{
				$factor[$bonusKey]	+= $elementLevel * $bonus[$bonusKey][0];
			}
		}
	}
	
	return $factor;
}

function getPlanets(array $USER): array
{
	if(isset($USER['PLANETS']))
		return $USER['PLANETS'];

	$order = $USER['planet_sort_order'] == 1 ? "DESC" : "ASC" ;

	$sql = "SELECT id, name, galaxy, system, planet, planet_type, image, b_building, b_building_id
			FROM %%PLANETS%% WHERE id_owner = :userId AND destruyed = :destruyed ORDER BY ";

	switch($USER['planet_sort'])
	{
		case 0:
			$sql	.= 'id '.$order;
			break;
		case 1:
			$sql	.= 'galaxy, system, planet, planet_type '.$order;
			break;
		case 2:
			$sql	.= 'name '.$order;
			break;
	}

	$planetsResult = Database::get()->select($sql, array(
		':userId'		=> $USER['id'],
		':destruyed'	=> 0
   	));
	
	$planetsList = array();

	foreach($planetsResult as $planetRow) {
		$planetsList[$planetRow['id']]	= $planetRow;
	}

	return $planetsList;
}

function get_timezone_selector(): array {
	// New Timezone Selector, better support for changes in tzdata (new russian timezones, e.g.)
	// http://www.php.net/manual/en/datetimezone.listidentifiers.php
	
	$timezones = array();
	$timezone_identifiers = DateTimeZone::listIdentifiers();

	foreach($timezone_identifiers as $value )
	{
		if ( preg_match( '/^(America|Antartica|Arctic|Asia|Atlantic|Europe|Indian|Pacific)\//', $value ) )
		{
			$ex		= explode('/',$value); //obtain continent,city
			$city	= isset($ex[2])? $ex[1].' - '.$ex[2]:$ex[1]; //in case a timezone has more than one
			$timezones[$ex[0]][$value] = str_replace('_', ' ', $city);
		}
	}
	return $timezones; 
}

function locale_date_format(string $format, int $time, $LNG = null): string
{
	// Workaround for locale Names.

	if(!isset($LNG)) {
		global $LNG;
	}
	
	$weekDay	= (int)date('w', $time);
	$months		= (int)date('n', $time) - 1;
	
	$format     = str_replace(array('D', 'M'), array('$D$', '$M$'), $format);
	$format		= str_replace('$D$', addcslashes($LNG['week_day'][$weekDay], 'A..z'), $format);
	$format		= str_replace('$M$', addcslashes($LNG['months'][$months], 'A..z'), $format);
	
	return $format;
}

function _date(string $format, mixed $time = null, ?string $toTimeZone = null, $LNG = null): string
{
	// Handle false, null, empty string, and cast safely to int
	if ($time === false || $time === null || $time === '') {
		$time = TIMESTAMP;
	} else {
		$time = (int)floor((float)$time);
	}

	if(isset($toTimeZone))
	{
		$date = new DateTime();
		// PHP 8.3 always has setTimestamp
		$date->setTimestamp($time);
		
		$time	-= $date->getOffset();
		
		// Normalize timezone string for PHP 8.3+
		$timezoneString = $toTimeZone;
		if (!is_string($timezoneString) || $timezoneString === '' || is_numeric($timezoneString)) {
			$timezoneString = 'UTC';
		} else {
			$timezoneString = trim($timezoneString);
		}
		
		try {
			$date->setTimezone(new DateTimeZone($timezoneString));
		} catch (Throwable $e) {
			// Fallback to UTC if timezone is invalid
			try {
				$date->setTimezone(new DateTimeZone('UTC'));
			} catch (Throwable $e2) {
				// This should never happen, but just in case
			}
		}
		$time	+= $date->getOffset();
	}
	
	$format	= locale_date_format($format, $time, $LNG);
	return date($format, $time);
}

function ValidateAddress(string $address): bool {
	// filter_var always exists in PHP 8.3
	return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
}

function message(string $mes, string $dest = "", string $time = "3", bool $topnav = false): void
{
	require_once('includes/classes/class.template.php');
	$template = new template();
	$template->message($mes, $dest, $time, !$topnav);
	exit;
}

function CalculateMaxPlanetFields(array $planet): int
{
	global $resource;
	return (int)($planet['field_max'] + ($planet[$resource[33]] * FIELDS_BY_TERRAFORMER) + ($planet[$resource[41]] * FIELDS_BY_MOONBASIS_LEVEL));
}

function pretty_time(int|float $seconds): string
{
	global $LNG;
	
	/*$day	= floor($seconds / 86400);
	$hour	= floor($seconds / 3600 % 24);
	$minute	= floor($seconds / 60 % 60);
	$second	= floor($seconds % 60);*/
	$day	= floor($seconds / 86400);
	$hour	= floor(floor($seconds / 3600) % 24);
	$minute	= floor(floor($seconds / 60) % 60);
	$second	= floor($seconds % 60);

	$time  = '';

	if($day > 0) {
		$time .= sprintf('%d%s ', $day, $LNG['short_day']);
	}

	return $time.sprintf('%02d%s %02d%s %02d%s',
		$hour, $LNG['short_hour'],
		$minute, $LNG['short_minute'],
		$second, $LNG['short_second']
	);
}

function pretty_fly_time(int|float $seconds): string
{
	$hour	= (int)floor($seconds / 3600);
	$minute	= (int)floor(floor($seconds / 60) % 60);
	$second	= (int)floor($seconds % 60);

	return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

function GetStartAddressLink(array $FleetRow, string $FleetType = ''): string
{
	return '<a href="game.php?page=galaxy&amp;galaxy='.$FleetRow['fleet_start_galaxy'].'&amp;system='.$FleetRow['fleet_start_system'].'" class="'. $FleetType .'">['.$FleetRow['fleet_start_galaxy'].':'.$FleetRow['fleet_start_system'].':'.$FleetRow['fleet_start_planet'].']</a>';
}

function GetTargetAddressLink(array $FleetRow, string $FleetType = ''): string
{
	return '<a href="game.php?page=galaxy&amp;galaxy='.$FleetRow['fleet_end_galaxy'].'&amp;system='.$FleetRow['fleet_end_system'].'" class="'. $FleetType .'">['.$FleetRow['fleet_end_galaxy'].':'.$FleetRow['fleet_end_system'].':'.$FleetRow['fleet_end_planet'].']</a>';
}

function BuildPlanetAddressLink(array $CurrentPlanet): string
{
	return '<a href="game.php?page=galaxy&amp;galaxy='.$CurrentPlanet['galaxy'].'&amp;system='.$CurrentPlanet['system'].'">['.$CurrentPlanet['galaxy'].':'.$CurrentPlanet['system'].':'.$CurrentPlanet['planet'].']</a>';
}

/**
 * Normalize numeric input that may come as string from DB/explode/etc.
 * Returns float (0.0 on invalid input).
 */
function tp_normalize_number(int|float|string $value): float
{
	if (is_string($value)) {
		$value = trim($value);

		if ($value === '') {
			return 0.0;
		}

		$value = str_replace(' ', '', $value);

		// Handle "1.234,56" (EU) and "1,234.56" (US)
		if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
			// If last comma is after last dot => EU format
			if (strrpos($value, ',') > strrpos($value, '.')) {
				$value = str_replace('.', '', $value);
				$value = str_replace(',', '.', $value);
			} else {
				// US: commas are thousands separators
				$value = str_replace(',', '', $value);
			}
		} elseif (strpos($value, ',') !== false && strpos($value, '.') === false) {
			// "1234,56" -> decimal comma
			$value = str_replace(',', '.', $value);
		}

		if (!is_numeric($value)) {
			return 0.0;
		}

		return (float)$value;
	}

	return (float)$value;
}

function pretty_number(int|float|string $n, int $dec = 0): string
{
	$n = tp_normalize_number($n);
	return number_format((float)floatToString($n, $dec), $dec, ',', '.');
}

function GetUserByID(int $userId, string|array $GetInfo = "*"): array|false
{
	if(is_array($GetInfo))
	{
		$GetOnSelect = implode(', ', $GetInfo);
	}
	else
	{
		$GetOnSelect = $GetInfo;
	}

	$sql = 'SELECT '.$GetOnSelect.' FROM %%USERS%% WHERE id = :userId';

	$User = Database::get()->selectSingle($sql, array(
		':userId'	=> $userId
	));

	return $User;
}

function makebr(string $text): string
{
    // XHTML FIX - now always use nl2br in PHP 8.3
    return nl2br($text, false); 
}

function CheckNoobProtec(array $OwnerPlayer, array $TargetPlayer, array $Player): array
{
	$config	= Config::get();
	if(
		$config->noobprotection == 0 
		|| $config->noobprotectiontime == 0 
		|| $config->noobprotectionmulti == 0 
		|| $Player['banaday'] > TIMESTAMP
		|| $Player['onlinetime'] < TIMESTAMP - INACTIVE
	) {
		return array('NoobPlayer' => false, 'StrongPlayer' => false);
	}
	
	return array(
		'NoobPlayer' => (
			/* WAHR: 
				Wenn Spieler mehr als 25000 Punkte hat UND
				Wenn ZielSpieler weniger als 80% der Punkte des Spieler hat.
				ODER weniger als 5.000 hat.
			*/
			// Addional Comment: Letzteres ist eigentlich sinnfrei, bitte testen.a
			($TargetPlayer['total_points'] <= $config->noobprotectiontime) && // Default: 25.000
			($OwnerPlayer['total_points'] > $TargetPlayer['total_points'] * $config->noobprotectionmulti)
		), 
		'StrongPlayer' => (
			/* WAHR: 
				Wenn Spieler weniger als 5000 Punkte hat UND
				Mehr als das funfache der eigende Punkte hat
			*/
			($OwnerPlayer['total_points'] < $config->noobprotectiontime) && // Default: 5.000
			($OwnerPlayer['total_points'] * $config->noobprotectionmulti < $TargetPlayer['total_points'])
		),
	);
}

function shortly_number(int|float $number, ?int $decial = null): string
{
	$negate	= $number < 0 ? -1 : 1;
	$number	= abs($number);
    $unit	= array("", "K", "M", "B", "T", "Q", "Q+", "S", "S+", "O", "N");
	$key	= 0;
	
	if($number >= 1000000) {
		++$key;
		while($number >= 1000000)
		{
			++$key;
			$number = $number / 1000000;
		}
	} elseif($number >= 1000) {
		++$key;
		$number = $number / 1000;
	}
	
	$decial	= !is_numeric($decial) ? ((int) (((int)$number != $number) && $key != 0 && $number != 0 && $number < 100)) : $decial;
	return pretty_number($negate * $number, $decial).' '.$unit[$key];
}

function floatToString(int|float|string $number, int $Pro = 0, bool $output = false): string
{
	$number = tp_normalize_number($number);

	$out = sprintf('%.' . $Pro . 'f', (float)$number);
	return $output ? str_replace(',', '.', $out) : $out;
}

function isModuleAvailable($ID): bool
{
	// Handle null or empty values
	if (empty($ID)) {
		return false;
	}
	
	global $USER;
	$modules	= explode(';', Config::get()->moduls);

	if(!isset($modules[$ID]))
	{
		$modules[$ID] = 1;
	}

	return $modules[$ID] == 1 || (isset($USER['authlevel']) && $USER['authlevel'] > AUTH_USR);
}

function ClearCache(): void
{
	// Immer absolut arbeiten (ROOT_PATH), sonst knallt scandir je nach Working Directory
	$baseCacheDir		= rtrim(ROOT_PATH, '/').'/cache/';
	$templatesCacheDir	= $baseCacheDir.'templates/';

	// Sicherstellen, dass die Cache-Ordner existieren (damit scandir nicht failt)
	if(!is_dir($baseCacheDir)) {
		@mkdir($baseCacheDir, 0775, true);
	}

	if(!is_dir($templatesCacheDir)) {
		@mkdir($templatesCacheDir, 0775, true);
	}

	// Wichtig: Sessions niemals löschen (wenn session.save_path im Cache liegt)
	$protectedDirs = array(
		'sessions',
	);

	// Dateien im cache/ löschen (aber keine Ordner und keine geschützten Unterordner)
	$cacheItems = @scandir($baseCacheDir);
	if($cacheItems !== false) {
		foreach($cacheItems as $item) {
			if($item === '.' || $item === '..' || $item === '.htaccess') {
				continue;
			}

			$fullPath = $baseCacheDir.$item;

			// Geschützte Ordner überspringen
			if(is_dir($fullPath) && in_array($item, $protectedDirs, true)) {
				continue;
			}

			// Ordner generell nicht löschen (2Moons Cache ist dateibasiert)
			if(is_dir($fullPath)) {
				continue;
			}

			@unlink($fullPath);
		}
	}

	// Dateien im cache/templates/ löschen
	$templateItems = @scandir($templatesCacheDir);
	if($templateItems !== false) {
		foreach($templateItems as $item) {
			if($item === '.' || $item === '..' || $item === '.htaccess') {
				continue;
			}

			$fullPath = $templatesCacheDir.$item;

			if(is_dir($fullPath)) {
				continue;
			}

			@unlink($fullPath);
		}
	}

	// Twig/Template Cache (falls deine Template-Klasse noch extra cached)
	$template = new template();
	$template->clearAllCache();

	require_once 'includes/classes/Cronjob.class.php';
	Cronjob::reCalculateCronjobs();

	$sql = 'UPDATE %%PLANETS%% SET eco_hash = :ecoHash;';
	Database::get()->update($sql, array(
		':ecoHash' => ''
	));

	clearstatcache();

	$config  = Config::get();
	$version = explode('.', $config->VERSION);
	$config->VERSION = $version[0].'.'.$version[1].'.'.'git';
	$config->save();
}

function allowedTo(string $side): bool
{
	global $USER;
	return ($USER['authlevel'] == AUTH_ADM || (isset($USER['rights']) && $USER['rights'][$side] == 1));
}

function isactiveDMExtra(int $Extra, int $Time): bool {
	return $Time - $Extra <= 0;
}

function DMExtra(int $Extra, int $Time, mixed $true, mixed $false): mixed {
	return isactiveDMExtra($Extra, $Time) ? $true : $false;
}

function getRandomString(): string {
	return md5(uniqid());
}

function isVacationMode(array $USER): bool
{
	return ($USER['urlaubs_modus'] == 1);
}

function clearGIF(): void {
	header('Cache-Control: no-cache');
	header('Content-type: image/gif');
	header('Content-length: 43');
	header('Expires: 0');
	echo("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	exit;
}


function friendly_severity(int $severity): string {
    $names = [];

    $consts = array_flip(array_slice(get_defined_constants(true)['Core'], 0, 15, true));

    foreach ($consts as $code => $name) {
        if ($severity & $code) $names []= $name;
    }

    return join(' | ', $names);
}



/*
 * Handler for exceptions
 *
 * @param object
 * @return Exception
 */
function exceptionHandler(\Throwable $exception): void
{
	/** @var $exception ErrorException|Exception */

	if(!headers_sent()) {
		if (!class_exists('HTTP', false)) {
			require_once('includes/classes/HTTP.class.php');
		}
		
		HTTP::sendHeader('HTTP/1.1 503 Service Unavailable');
	}

	if(method_exists($exception, 'getSeverity')) {
		$errno	= friendly_severity($exception->getSeverity());
	} else {
		$errno	= E_USER_ERROR;
	}
	
	$errorType = array(
		'E_ERROR'				=> 'ERROR',
		'E_WARNING'			=> 'WARNING',
		'E_PARSE'				=> 'PARSING ERROR',
		'E_NOTICE'			=> 'NOTICE',
		'E_CORE_ERROR'		=> 'CORE ERROR',
		'E_CORE_WARNING'   	=> 'CORE WARNING',
		'E_COMPILE_ERROR'		=> 'COMPILE ERROR',
		'E_COMPILE_WARNING'	=> 'COMPILE WARNING',
		'E_USER_ERROR'		=> 'USER ERROR',
		'E_USER_WARNING'		=> 'USER WARNING',
		'E_USER_NOTICE'		=> 'USER NOTICE',
		'E_STRICT'			=> 'STRICT NOTICE',
		'E_RECOVERABLE_ERROR'	=> 'RECOVERABLE ERROR',
		'E_DEPRECATED'		=> 'DEPRECATED'
	);
	
	if(file_exists(ROOT_PATH.'install/VERSION'))
	{
		$VERSION	= file_get_contents(ROOT_PATH.'install/VERSION').' (FILE)';
	}
	else
	{
		$VERSION	= 'UNKNOWN';
	}
	$gameName	= '-';
	
	if(MODE !== 'INSTALL')
	{
		try
		{
			$config		= Config::get();
			$gameName	= $config->game_name;
			$VERSION	= $config->VERSION;
		} catch(Throwable $e) {
		}
	}
	
	
	$DIR		= MODE == 'INSTALL' ? '..' : '.';
	$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
	$requestUrl = PROTOCOL.HTTP_HOST.$requestUri;
	ob_start();
	echo '<!DOCTYPE html>
<!--[if lt IE 7 ]> <html lang="de" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>    <html lang="de" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>    <html lang="de" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>    <html lang="de" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="de" class="no-js"> <!--<![endif]-->
<head>
	<title>'.$gameName.'</title>
	<meta name="generator" content="2Moons '.$VERSION.'">
	<!-- 
		This website is powered by 2Moons '.$VERSION.'
		2Moons is a free Space Browsergame initially created by Jan Kröpke and licensed under GNU/GPL.
		2Moons is copyright 2009-2013 of Jan Kröpke. Extensions are copyright of their respective owners.
		Information and contribution at http://2moons.cc/
	-->
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" type="text/css" href="'.$DIR.'/styles/resource/css/base/boilerplate.css?v='.$VERSION.'">
	<link rel="stylesheet" type="text/css" href="'.$DIR.'/styles/resource/css/ingame/main.css?v='.$VERSION.'">
	<link rel="stylesheet" type="text/css" href="'.$DIR.'/styles/resource/css/base/jquery.css?v='.$VERSION.'">
	<link rel="stylesheet" type="text/css" href="'.$DIR.'/styles/theme/gow/formate.css?v='.$VERSION.'">
	<link rel="shortcut icon" href="./favicon.ico" type="image/x-icon">
	<script type="text/javascript">
	var ServerTimezoneOffset = -3600;
	var serverTime 	= new Date(2012, 2, 12, 14, 43, 36);
	var startTime	= serverTime.getTime();
	var localTime 	= serverTime;
	var localTS 	= startTime;
	var Gamename	= document.title;
	var Ready		= "Fertig";
	var Skin		= "'.$DIR.'/styles/theme/gow/";
	var Lang		= "de";
	var head_info	= "Information";
	var auth		= 3;
	var days 		= ["So","Mo","Di","Mi","Do","Fr","Sa"] 
	var months 		= ["Jan","Feb","Mar","Apr","Mai","Jun","Jul","Aug","Sep","Okt","Nov","Dez"] ;
	var tdformat	= "[M] [D] [d] [H]:[i]:[s]";
	var queryString	= "";

	setInterval(function() {
		serverTime.setSeconds(serverTime.getSeconds()+1);
	}, 1000);
	</script>
	<script type="text/javascript" src="'.$DIR.'/scripts/base/jquery.js?v=2123"></script>
	<script type="text/javascript" src="'.$DIR.'/scripts/base/jquery.ui.js?v=2123"></script>
	<script type="text/javascript" src="'.$DIR.'/scripts/base/jquery.cookie.js?v=2123"></script>
	<script type="text/javascript" src="'.$DIR.'/scripts/base/jquery.fancybox.js?v=2123"></script>
	<script type="text/javascript" src="'.$DIR.'/scripts/base/jquery.validationEngine.js?v=2123"></script>
	<script type="text/javascript" src="'.$DIR.'/scripts/base/tooltip.js?v=2123"></script>
	<script type="text/javascript" src="'.$DIR.'/scripts/game/base.js?v=2123"></script>
</head>
<body id="overview" class="full">
<table width="960">
	<tr>
		<th></th>
	</tr>
	<tr>
		<td class="left">
			<b>Message: </b>'.$exception->getMessage().'<br>
			<b>File: </b>'.$exception->getFile().'<br>
			<b>Line: </b>'.$exception->getLine().'<br>
			<b>URL: </b>'.$requestUrl.'<br>
			<b>PHP-Version: </b>'.PHP_VERSION.'<br>
			<b>PHP-API: </b>'.php_sapi_name().'<br>
			<b>2Moons Version: </b>'.$VERSION.'<br>
			<b>Debug Backtrace:</b><br>'.makebr(htmlspecialchars($exception->getTraceAsString())).'
		</td>
	</tr>
</table>
</body>
</html>';

	echo str_replace(array('\\', ROOT_PATH, substr(ROOT_PATH, 0, 15)), array('/', '/', 'FILEPATH '), ob_get_clean());
	
	$errorText	= date("[d-M-Y H:i:s]", TIMESTAMP).': "'.strip_tags($exception->getMessage())."\"\r\n";
	$errorText	.= 'File: '.$exception->getFile().' | Line: '.$exception->getLine()."\r\n";
	$errorText	.= 'URL: '.$requestUrl.' | Version: '.$VERSION."\r\n";
	$errorText	.= "Stack trace:\r\n";
	$errorText	.= str_replace(ROOT_PATH, '/', htmlspecialchars(str_replace('\\', '/',$exception->getTraceAsString())))."\r\n";
	
	if(is_writable('includes/error.log'))
	{
		file_put_contents('includes/error.log', $errorText, FILE_APPEND);
	}
}
/*
 *
 * @throws ErrorException
 *
 * @return bool If its an hidden error.
 *
 */
function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    if (!($errno & error_reporting())) {
        return false;
    }
	
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

// array_replace_recursive exists natively in PHP 8.3 - no workaround needed
