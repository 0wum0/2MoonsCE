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

// @admin-migrated (Phase 10 — AbstractAdminPage)

/**
 * Resolves a timezone value to a valid timezone string for DateTimeZone.
 * Handles invalid values (numeric, empty, invalid) by falling back to UTC.
 *
 * @param mixed $tz The timezone value to resolve
 * @param string $fallback The fallback timezone string
 * @return string A valid timezone string
 */
function resolveTimezoneString($tz, string $fallback = 'UTC'): string
{
	if ($tz === null) {
		$tz = $fallback;
	}
	if (!is_string($tz) || $tz === '' || is_numeric($tz)) {
		return $fallback;
	}
	$tz = trim($tz);
	try {
		new DateTimeZone($tz);
		return $tz;
	} catch (Throwable $e) {
		error_log('[ShowInformationPage] Invalid timezone "' . $tz . '", falling back to "' . $fallback . '": ' . $e->getMessage());
		return $fallback;
	}
}

class ShowInformationPage extends AbstractAdminPage
{
	public function __construct()
	{
		parent::__construct('ShowInformationPage');
	}

	protected function run(): void
	{
	global $LNG, $USER;

	$config = Config::get();

	if (@file_exists(ini_get('error_log')))
		$Lines = count(file(ini_get('error_log')));
	else
		$Lines = 0;

	$serverTimezone = resolveTimezoneString($config->timezone ?? null, date_default_timezone_get());
	$userTimezone   = resolveTimezoneString($USER['timezone'] ?? null, date_default_timezone_get());
	$phpTimezone    = resolveTimezoneString(ini_get('date.timezone'), date_default_timezone_get());

	try {
		$dateTimeZoneServer = new DateTimeZone($serverTimezone);
	} catch (Throwable $e) {
		error_log('[ShowInformationPage] DateTimeZone creation failed for server timezone "' . $serverTimezone . '": ' . $e->getMessage());
		$dateTimeZoneServer = new DateTimeZone(date_default_timezone_get());
	}
	try {
		$dateTimeZoneUser = new DateTimeZone($userTimezone);
	} catch (Throwable $e) {
		error_log('[ShowInformationPage] DateTimeZone creation failed for user timezone "' . $userTimezone . '": ' . $e->getMessage());
		$dateTimeZoneUser = new DateTimeZone(date_default_timezone_get());
	}
	try {
		$dateTimeZonePHP = new DateTimeZone($phpTimezone);
	} catch (Throwable $e) {
		error_log('[ShowInformationPage] DateTimeZone creation failed for PHP timezone "' . $phpTimezone . '": ' . $e->getMessage());
		$dateTimeZonePHP = new DateTimeZone(date_default_timezone_get());
	}

	$dateTimeServer = new DateTime("now", $dateTimeZoneServer);
	$dateTimeUser   = new DateTime("now", $dateTimeZoneUser);
	$dateTimePHP    = new DateTime("now", $dateTimeZonePHP);

	$db        = Database::get();
	$dbVersion = $db->selectSingle("SELECT dbVersion FROM %%SYSTEM%%;", [], 'dbVersion');

	$pdo = $db->getHandle();
	$mysqlClientVersion = $pdo ? $pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION) : '-';
	$mysqlServerVersion = $pdo ? $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) : '-';

	$this->assign(array(
		'info_information'	=> sprintf($LNG['info_information'], 'https://github.com/jkroepke/2Moons/issues'),
		'info'				=> $_SERVER['SERVER_SOFTWARE'],
		'vPHP'				=> PHP_VERSION,
		'vAPI'				=> PHP_SAPI,
		'vGame'				=> $config->VERSION . (file_exists(ROOT_PATH . '/.git/ORIG_HEAD') ? ' (' . trim(file_get_contents(ROOT_PATH . '/.git/ORIG_HEAD')) . ')' : ''),
		'vMySQLc'			=> $mysqlClientVersion,
		'vMySQLs'			=> $mysqlServerVersion,
		'root'				=> $_SERVER['SERVER_NAME'],
		'gameroot'			=> $_SERVER['SERVER_NAME'] . str_replace('/admin.php', '', $_SERVER['PHP_SELF']),
		'json'				=> function_exists('json_encode') ? 'Ja' : 'Nein',
		'bcmath'			=> extension_loaded('bcmath') ? 'Ja' : 'Nein',
		'curl'				=> extension_loaded('curl') ? 'Ja' : 'Nein',
		'browser'			=> $_SERVER['HTTP_USER_AGENT'],
		'safemode'			=> ini_get('safe_mode') ? 'Ja' : 'Nein',
		'memory'			=> ini_get('memory_limit'),
		'suhosin'			=> ini_get('suhosin.request.max_value_length') ? 'Ja' : 'Nein',
		'log_errors'		=> ini_get('log_errors') ? 'Aktiv' : 'Inaktiv',
		'errorlog'			=> ini_get('error_log'),
		'errorloglines'		=> $Lines,
		'dbVersion'			=> $dbVersion,
		'php_tz'			=> $dateTimePHP->getOffset() / 3600,
		'conf_tz'			=> $dateTimeServer->getOffset() / 3600,
		'user_tz'			=> $dateTimeUser->getOffset() / 3600,
	));

	$this->show('ShowInformationPage.twig');
	}
}
