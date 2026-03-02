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

class Session
{
	static private ?Session $obj = null;
	static private bool $iniSet = false;
	private ?array $data = null;
	private bool $saved = false;

	/**
	 * Set PHP session settings
	 */
	static public function init(): bool
	{
		if (self::$iniSet === true) {
			return false;
		}
		self::$iniSet = true;

		if (session_status() === PHP_SESSION_ACTIVE) {
			return false;
		}

		ini_set('session.use_cookies', '1');
		ini_set('session.use_only_cookies', '1');
		ini_set('session.use_trans_sid', '0');
		ini_set('session.auto_start', '0');
		ini_set('session.serialize_handler', 'php');
		ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
		ini_set('session.gc_probability', '1');
		ini_set('session.gc_divisor', '1000');
		@ini_set('session.bug_compat_warn', '0');
		@ini_set('session.bug_compat_42', '0');
		ini_set('session.cookie_httponly', '1');

		// ✅ CRITICAL FIX:
		// NEVER trust CACHE_PATH here, because on some hosts it's "/cache/" (server root).
		// Force sessions into PROJECT cache dir.
		$projectCacheDir = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
		$sessionPath     = $projectCacheDir . 'sessions' . DIRECTORY_SEPARATOR;

		self::ensureDir($projectCacheDir);
		self::ensureDir($sessionPath);

		// If not writable -> fallback to temp (but keep stable)
		if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
			$tmp        = self::getTempPath();
			$sessionPath = rtrim($tmp, '/\\') . DIRECTORY_SEPARATOR . '2moons_sessions' . DIRECTORY_SEPARATOR;
			self::ensureDir($sessionPath);
		}

		ini_set('session.save_path', $sessionPath);

		// keep upload_tmp_dir safe too (some hosts warn if invalid)
		if (is_dir($sessionPath) && is_writable($sessionPath)) {
			@ini_set('upload_tmp_dir', $sessionPath);
		}

		$HTTP_ROOT = MODE === 'INSTALL' ? dirname(HTTP_ROOT) : HTTP_ROOT;

		session_set_cookie_params(SESSION_LIFETIME, $HTTP_ROOT, null, HTTPS, true);
		session_cache_limiter('nocache');
		session_name('2Moons');

		return true;
	}

	private static function ensureDir(string $path): void
	{
		if (!is_dir($path)) {
			@mkdir($path, 0775, true);
		}
	}

	static private function getTempPath(): string
	{
		require_once 'includes/libs/wcf/BasicFileUtil.class.php';
		return BasicFileUtil::getTempFolder();
	}

	/**
	 * Get client IP address
	 */
	static public function getClientIp(): string
	{
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			return (string) $_SERVER['HTTP_CLIENT_IP'];
		}
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			return (string) $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
			return (string) $_SERVER['HTTP_X_FORWARDED'];
		}
		if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
			return (string) $_SERVER['HTTP_FORWARDED_FOR'];
		}
		if (!empty($_SERVER['HTTP_FORWARDED'])) {
			return (string) $_SERVER['HTTP_FORWARDED'];
		}
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			return (string) $_SERVER['REMOTE_ADDR'];
		}
		return 'UNKNOWN';
	}

	/**
	 * Create an empty session
	 */
	static public function create(): self
	{
		if (!self::existsActiveSession()) {
			self::$obj = new self;
			register_shutdown_function(array(self::$obj, 'save'));

			@session_start();
		}

		return self::$obj;
	}

	/**
	 * Wake an active session
	 */
	static public function load()
	{
		if (!self::existsActiveSession()) {
			self::init();

			if (session_status() !== PHP_SESSION_ACTIVE) {
				@session_start();
			}

			if (isset($_SESSION['obj'])) {
				$un = @unserialize((string) $_SESSION['obj']);
				if ($un instanceof self) {
					self::$obj = $un;
					register_shutdown_function(array(self::$obj, 'save'));
				} else {
					self::create();
				}
			} else {
				self::create();
			}
		}

		return self::$obj;
	}

	static public function existsActiveSession()
	{
		return isset(self::$obj);
	}

	public function __construct()
	{
		self::init();
	}

	public function __sleep()
	{
		return array('data');
	}

	public function __wakeup()
	{
	}

	public function __set($name, $value)
	{
		$this->data[$name] = $value;
	}

	public function __get($name)
	{
		return $this->data[$name] ?? null;
	}

	public function __isset($name)
	{
		return isset($this->data[$name]);
	}

	public function save()
	{
		// Prevent double execution: once explicitly, once via shutdown handler
		if ($this->saved) {
			return;
		}
		$this->saved = true;

		$sessionId = session_id();
		if (empty($sessionId)) {
			return;
		}

		// sessions require a valid user.
		if ($this->data === null || !isset($this->data['userId']) || empty($this->data['userId'])) {
			$this->delete();
			return;
		}

		$userIpAddress = self::getClientIp();

		$sql = 'REPLACE INTO %%SESSION%% SET
		sessionID	= :sessionId,
		userID		= :userId,
		lastonline	= :lastActivity,
		userIP		= :userAddress;';

		$db = Database::get();

		$db->replace($sql, array(
			':sessionId'    => session_id(),
			':userId'       => $this->data['userId'] ?? null,
			':lastActivity' => TIMESTAMP,
			':userAddress'  => $userIpAddress,
		));

		$sql = 'UPDATE %%USERS%% SET
		onlinetime	= :lastActivity,
		user_lastip  = :userAddress
		WHERE id = :userId;';

		$db->update($sql, array(
			':userAddress'  => $userIpAddress,
			':lastActivity' => TIMESTAMP,
			':userId'       => $this->data['userId'] ?? null,
		));

		$this->data['lastActivity']  = TIMESTAMP;
		$this->data['sessionId']     = session_id();
		$this->data['userIpAddress'] = $userIpAddress;
		$this->data['requestPath']   = $this->getRequestPath();

		$_SESSION['obj'] = serialize($this);

		@session_write_close();

		// Disconnect PDO so no open cursor/statement remains in the PHP shutdown sequence.
		// On LiteSpeed, the shutdown handler fires while PDO still holds internal MySQL
		// protocol state, which causes "Cannot execute queries while other unbuffered
		// queries are active" (error 2014) on the next query.
		$db->disconnect();
	}

	public function delete()
	{
		$sql = 'DELETE FROM %%SESSION%% WHERE sessionID = :sessionId;';
		$db  = Database::get();

		$db->delete($sql, array(
			':sessionId' => session_id(),
		));

		@session_destroy();
	}

	public function isValidSession()
	{
		if ($this->data === null || !isset($this->data['userIpAddress']) ||
			$this->compareIpAddress($this->data['userIpAddress'], self::getClientIp(), COMPARE_IP_BLOCKS) === false) {
			return false;
		}

		if ($this->data === null || !isset($this->data['lastActivity']) ||
			$this->data['lastActivity'] < TIMESTAMP - SESSION_LIFETIME) {
			return false;
		}

		$sql = 'SELECT COUNT(*) as record FROM %%SESSION%% WHERE sessionID = :sessionId;';
		$db  = Database::get();

		$sessionCount = $db->selectSingle($sql, array(
			':sessionId' => session_id(),
		), 'record');

		return $sessionCount != 0;
	}

	public function selectActivePlanet()
	{
		$httpData = HTTP::_GP('cp', 0);

		if (!empty($httpData)) {
			$sql = 'SELECT id FROM %%PLANETS%% WHERE id = :planetId AND id_owner = :userId;';

			$db       = Database::get();
			$planetId = $db->selectSingle($sql, array(
				':userId'   => $this->data['userId'] ?? null,
				':planetId' => $httpData,
			), 'id');

			if (!empty($planetId)) {
				$this->data['planetId'] = $planetId;
			}
		}
	}

	private function getRequestPath()
	{
		return HTTP_ROOT . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
	}

	private function compareIpAddress($ip1, $ip2, $blockCount)
	{
		if (strpos((string)$ip2, ':') !== false && strpos((string)$ip1, ':') !== false) {
			$s_ip = $this->short_ipv6((string)$ip1, (int)$blockCount);
			$u_ip = $this->short_ipv6((string)$ip2, (int)$blockCount);
		} else {
			$s_ip = implode('.', array_slice(explode('.', (string)$ip1), 0, (int)$blockCount));
			$u_ip = implode('.', array_slice(explode('.', (string)$ip2), 0, (int)$blockCount));
		}

		return ($s_ip == $u_ip);
	}

	private function short_ipv6($ip, $length)
	{
		if ($length < 1) {
			return '';
		}

		$blocks = substr_count($ip, ':') + 1;
		if ($blocks < 9) {
			$ip = str_replace('::', ':' . str_repeat('0000:', 9 - $blocks), $ip);
		}
		if (isset($ip[0]) && $ip[0] == ':') {
			$ip = '0000' . $ip;
		}
		if ($length < 4) {
			$ip = implode(':', array_slice(explode(':', $ip), 0, 1 + $length));
		}

		return $ip;
	}
}