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

require_once('vendor/autoload.php');

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;

class template
{
	protected string $window = 'full';
	public array $jsscript = array();
	public array $script = array();
	
	private Environment $twig;
	private array $templateVars = array();
	private string $templateDir = 'styles/templates/';
	private string $currentSubDir = '';
	
	function __construct()
	{	
		$this->twigSettings();
	}

	private function twigSettings(): void
	{
		// Setup Twig Loader with multiple namespaces for templates
		$loader = new FilesystemLoader($this->templateDir);
		
		// Add subdirectories as additional paths for better template resolution
		$loader->addPath($this->templateDir . 'login', 'login');
		$loader->addPath($this->templateDir . 'game', 'game');
		$loader->addPath($this->templateDir . 'adm', 'adm');
		$loader->addPath($this->templateDir . 'install', 'install');

		// Load plugin Twig namespaces (@pluginId/path/to/template.twig)
		if (class_exists('PluginManager')) {
			foreach (PluginManager::get()->getTwigNamespaces() as $pluginId => $absDir) {
				if (is_dir($absDir)) {
					$loader->addPath($absDir, $pluginId);
				}
			}
		}
		
		// Ensure twig cache dir exists (and is writable) - prevents silent failures
		$twigCacheDir = CACHE_PATH . 'twig/';
		if (!is_dir($twigCacheDir)) {
			@mkdir($twigCacheDir, 0775, true);
		}

		// Create Twig Environment
		$this->twig = new Environment($loader, [
			// IMPORTANT: Keep twig cache strictly inside cache/twig (never touch cache/sessions)
			'cache' => (is_dir($twigCacheDir) && is_writable($twigCacheDir)) ? $twigCacheDir : $this->getTempPath() . '/twig/',
			'debug' => true, // Set false for production!
			'auto_reload' => true,
			'strict_variables' => false,
		]);
		
		// Add custom functions if needed
		$this->addCustomFunctions();
		$this->addCustomFilters();
	}

	private function addCustomFunctions(): void
	{
		// Register allowedTo function for Twig templates (for rights/permissions checking)
		$this->twig->addFunction(new TwigFunction('allowedTo', function(string $right): bool {
			global $USER;
			// Fallback: Admins dürfen alles
			if (isset($USER['authlevel']) && $USER['authlevel'] >= AUTH_ADM) {
				return true;
			}
			// Falls Rechte-Array existiert, prüfen
			if (isset($USER['rights']) && is_array($USER['rights'])) {
				return in_array($right, $USER['rights']);
			}
			return false;
		}));
		
		// Register isModuleAvailable function for Twig templates
		$this->twig->addFunction(new TwigFunction('isModuleAvailable', function(int $moduleId): bool {
			return isModuleAvailable($moduleId);
		}));
		
		// Register constant function for Twig templates to access PHP constants
		$this->twig->addFunction(new TwigFunction('constant', function(string $name) {
			return constant($name);
		}));
		
		// Register shortly_number function for Twig templates
		$this->twig->addFunction(new TwigFunction('shortly_number', function($number, ?int $decimal = null): string {
			// Convert input to float for PHP 8.3 strict typing compatibility
			$number = (float)$number;
			return shortly_number($number, $decimal);
		}));
		
		// Register hook() function for plugin system
		$this->twig->addFunction(new TwigFunction('hook', function(string $hookName, array $context = []): string {
			if (class_exists('HookManager')) {
				return HookManager::get()->renderHook($hookName, $context);
			}
			return '';
		}, ['is_safe' => ['html']]));

		// Register common PHP math functions for Twig templates
		$this->twig->addFunction(new TwigFunction('min', function(...$values) {
			return min(...$values);
		}));
		
		$this->twig->addFunction(new TwigFunction('max', function(...$values) {
			return max(...$values);
		}));
		
		$this->twig->addFunction(new TwigFunction('abs', function($number) {
			return abs($number);
		}));
		
		$this->twig->addFunction(new TwigFunction('round', function($number, int $precision = 0, int $mode = PHP_ROUND_HALF_UP) {
			return round($number, $precision, $mode);
		}));
		
		$this->twig->addFunction(new TwigFunction('floor', function($number) {
			return floor($number);
		}));
		
		$this->twig->addFunction(new TwigFunction('ceil', function($number) {
			return ceil($number);
		}));
		
		$this->twig->addFunction(new TwigFunction('floatval', function($value) {
			return floatval($value);
		}));
		
		// Register PHP helper functions for Twig templates
		$this->twig->addFunction(new TwigFunction('empty', function($value): bool {
			return empty($value);
		}));
		
		$this->twig->addFunction(new TwigFunction('count', function($value): int {
			return is_countable($value) ? count($value) : 0;
		}));
	}

	private function addCustomFilters(): void
	{
		// Register pretty_time filter for Twig templates
		$this->twig->addFilter(new TwigFilter('time', function($seconds) {
			return pretty_time($seconds);
		}));
		
		// Register number filter alias for number_format
		$this->twig->addFilter(new TwigFilter('number', function($number, int $decimals = 0, string $decPoint = ',', string $thousandsSep = '.') {
			return number_format((float)$number, $decimals, $decPoint, $thousandsSep);
		}));
		
		// Register json filter alias for json_encode
		$this->twig->addFilter(new TwigFilter('json', function($value, int $options = 0, int $depth = 512) {
			return json_encode($value, $options, $depth);
		}));
		
		// Register pretty_number filter for clean number formatting
		$this->twig->addFilter(new TwigFilter('pretty_number', function($number, int $decimals = 0) {
			return pretty_number($number, $decimals);
		}));
		
		// Register shortly_number filter for abbreviated numbers (K, M, B, etc.)
		$this->twig->addFilter(new TwigFilter('shortly', function($number, ?int $decimals = null) {
			// Convert input to float for PHP 8.3 strict typing compatibility
			$number = (float)$number;
			return shortly_number($number, $decimals);
		}));
		
		// Register shortly_number filter (alternative name for compatibility)
		$this->twig->addFilter(new TwigFilter('shortly_number', function($number, ?int $decimals = null) {
			// Convert input to float for PHP 8.3 strict typing compatibility
			$number = (float)$number;
			return shortly_number($number, $decimals);
		}));
		
		// Register count_characters filter (counts characters in a string)
		$this->twig->addFilter(new TwigFilter('count_characters', function($value, bool $includeSpaces = false): int {
			// Always convert input to string for PHP 8.3 strict typing compatibility
			$string = (string)$value;
			
			if ($includeSpaces) {
				return mb_strlen($string);
			} else {
				return mb_strlen(preg_replace('/\s+/', '', $string));
			}
		}));
	}

	private function getTempPath(): string
	{
		require_once 'includes/libs/wcf/BasicFileUtil.class.php';
		return BasicFileUtil::getTempFolder();
	}
	
	public function assign_vars(array $var, bool $nocache = true): void
	{		
		$this->templateVars = array_merge($this->templateVars, $var);
	}

	public function loadscript(string $script): void
	{
		$this->jsscript[] = substr($script, 0, -3);
	}

	public function execscript(string $script): void
	{
		$this->script[] = $script;
	}
	
	/**
	 * Resolves a timezone value to a valid timezone string for DateTimeZone.
	 * Handles invalid values (numeric, empty, invalid) by falling back to UTC.
	 */
	private function resolveTimezoneString($userTz, $configTz = 'UTC'): string
	{
		$tz = $userTz ?? $configTz ?? 'UTC';
		
		if (!is_string($tz) || $tz === '' || is_numeric($tz)) {
			return 'UTC';
		}
		
		$tz = trim($tz);
		
		try {
			new DateTimeZone($tz);
			return $tz;
		} catch (Throwable $e) {
			return 'UTC';
		}
	}
	
	private function adm_main(): void
	{
		global $LNG, $USER;
		
		$config	= Config::get();
		
		$dateTimeServer = new DateTime("now");
		
		$timezoneString = $this->resolveTimezoneString(
			$USER['timezone'] ?? null,
			$config->timezone ?? 'UTC'
		);
		
		try {
			$dateTimeUser = new DateTime("now", new DateTimeZone($timezoneString));
		} catch (Throwable $e) {
			$dateTimeUser = $dateTimeServer;
		}

		// Build universe select for topbar
		$universeSelect = [];
		foreach (Universe::availableUniverses() as $uniId) {
			try {
				$uniConfig = Config::get($uniId);
				$universeSelect[$uniId] = sprintf('%s (ID: %d)', $uniConfig->uni_name, $uniId);
			} catch (\Throwable $e) {
				$universeSelect[$uniId] = sprintf('Universe %d', $uniId);
			}
		}
		ksort($universeSelect);

		// Support ticket count for sidebar badge
		$supportTicketCount = 0;
		try {
			if (defined('TICKETS') && isset($GLOBALS['DATABASE'])) {
				$ticketResult = $GLOBALS['DATABASE']->getFirstCell(
					"SELECT COUNT(*) FROM " . TICKETS . " WHERE universe = " . Universe::getEmulated() . " AND status = 0;"
				);
				$supportTicketCount = (int)$ticketResult;
			}
		} catch (\Throwable $e) {
			$supportTicketCount = 0;
		}

		// ── Safe-Mode notices ─────────────────────────────────────────────────
		$safeModeNotices = [];
		$safeModeLocked  = false;
		if (class_exists('PluginManager')) {
			$_pm             = PluginManager::get();
			$safeModeNotices = $_pm->getSafeModeNotices();
			$safeModeLocked  = $_pm->isSafeModeLocked();
			unset($_pm);
		}

		$this->assign_vars(array(
			'scripts'			=> $this->script,
			'title'				=> $config->game_name.' - '.$LNG['adm_cp_title'],
			'fcm_info'			=> $LNG['fcm_info'],
            'lang'    			=> $LNG->getLanguage(),
			'REV'				=> substr($config->VERSION, -4),
			'date'				=> explode("|", date('Y\|n\|j\|G\|i\|s\|Z', TIMESTAMP)),
			'Offset'			=> $dateTimeUser->getOffset() - $dateTimeServer->getOffset(),
			'VERSION'			=> $config->VERSION,
			'dpath'				=> 'styles/theme/gow/',
			'bodyclass'			=> 'full',
			// New admin layout vars
			'GET'				=> ['page' => $_GET['page'] ?? ''],
			'currentUser'		=> $USER,
			'authlevel'			=> $USER['authlevel'] ?? 0,
			'AvailableUnis'		=> $universeSelect,
			'UNI'				=> Universe::getEmulated(),
			'sid'				=> session_id(),
			'supportTicketCount'=> $supportTicketCount,
			// Safe-Mode
			'safeModeNotices'	=> $safeModeNotices,
			'safeModeLocked'	=> $safeModeLocked,
		));
	}
	
	public function show(string $file): void
	{		
		global $LNG, $THEME;

		$templatePath = $this->templateDir;
		
		if(MODE === 'INSTALL') {
			$this->currentSubDir = 'install/';
		} elseif(MODE === 'ADMIN') {
			$this->currentSubDir = 'adm/';
			$this->adm_main();
		} elseif(isset($THEME) && $THEME->isCustomTPL($file)) {
			$templatePath = $THEME->getTemplatePath();
		}

		if($this->currentSubDir !== '') {
			// Prepend the subdir path to the existing loader so plugin namespaces are preserved.
			$existingLoader = $this->twig->getLoader();
			if ($existingLoader instanceof FilesystemLoader) {
				$existingLoader->prependPath($templatePath . $this->currentSubDir);
			} else {
				$loader = new FilesystemLoader($templatePath . $this->currentSubDir);
				$this->twig->setLoader($loader);
			}
		}

		$pluginCss = [];
		$pluginJs  = [];
		if (class_exists('AssetRegistry')) {
			$currentPage = isset($_GET['page']) ? (string)$_GET['page'] : '';
			$pluginCss = AssetRegistry::get()->getCssForPage($currentPage);
			$pluginJs  = AssetRegistry::get()->getJsForPage($currentPage);
		}

		$this->assign_vars(array(
			'scripts'		=> $this->jsscript,
			'execscript'	=> implode("\n", $this->script),
			'pluginCss'		=> $pluginCss,
			'pluginJs'		=> $pluginJs,
		));

		$this->assign_vars(array(
			'LNG'			=> $LNG,
		), false);
		
		$twigFile = str_replace('.tpl', '.twig', $file);
		echo $this->twig->render($twigFile, $this->templateVars);
	}
	
	public function display(string $file): void
	{
		global $LNG;
		
		$twigFile = str_replace('.tpl', '.twig', $file);
		echo $this->twig->render($twigFile, $this->templateVars);
	}
	
	public function gotoside(string $dest, int $time = 3): void
	{
		$this->assign_vars(array(
			'gotoinsec'	=> $time,
			'goto'		=> $dest,
		));
	}
	
	public function message(string $mes, $dest = false, int $time = 3, bool $Fatal = false): void
	{
		global $LNG, $THEME;
	
		$this->assign_vars(array(
			'mes'		=> $mes,
			'fcm_info'	=> $LNG['fcm_info'],
			'Fatal'		=> $Fatal,
            'dpath'		=> $THEME->getTheme(),
		));
		
		$this->gotoside($dest !== false ? $dest : '', $time);
		$this->show('error_message_body.twig');
	}
	
	public static function printMessage(string $Message, bool $fullSide = true, ?array $redirect = NULL): void
	{
		$template = new self;
		if(!isset($redirect)) {
			$redirect = array(false, 0);
		}
		
		$template->message($Message, $redirect[0], (int)$redirect[1], !$fullSide);
		exit;
	}
	
	// Compatibility methods for Smarty-like access
	public function getTemplateDir(): array
	{
		return [$this->templateDir];
	}
	
	public function setTemplateDir(string $dir): void
	{
		$this->templateDir = rtrim($dir, '/') . '/';
		$existingLoader = $this->twig->getLoader();
		if ($existingLoader instanceof FilesystemLoader) {
			$existingLoader->prependPath($this->templateDir);
		} else {
			$loader = new FilesystemLoader($this->templateDir);
			$this->twig->setLoader($loader);
		}
	}
	
	public function getCompileDir(): string
	{
		return CACHE_PATH . 'twig/';
	}

	/**
	 * FIX: Cache leeren darf NIEMALS Sessions löschen.
	 * - löscht NUR den Twig-Cache (cache/twig/)
	 * - und lässt cache/sessions/ komplett in Ruhe
	 * - erstellt cache/twig/ bei Bedarf neu (robust auf Shared Hosting)
	 */
	public function clearAllCache(): void
	{
		$cacheDir = rtrim(CACHE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'twig';

		// Ensure cache dir exists
		if (!is_dir($cacheDir)) {
			@mkdir($cacheDir, 0775, true);
			return;
		}

		// Safety: never allow deleting outside of CACHE_PATH/twig
		$realCacheDir = realpath($cacheDir);
		$realCacheRoot = realpath(rtrim(CACHE_PATH, '/\\'));

		if ($realCacheDir === false || $realCacheRoot === false) {
			return;
		}

		// Must be exactly inside CACHE_PATH and end with /twig
		$normalizedDir = str_replace('\\', '/', $realCacheDir);
		$normalizedRoot = str_replace('\\', '/', $realCacheRoot);

		if (strpos($normalizedDir, $normalizedRoot) !== 0 || substr($normalizedDir, -5) !== '/twig') {
			// Abort if path looks suspicious
			return;
		}

		// Delete files/dirs inside twig cache only
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($realCacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $fileInfo) {
			/** @var SplFileInfo $fileInfo */
			$path = $fileInfo->getRealPath();
			if ($path === false) {
				continue;
			}

			// Extra safety: ensure every path is still within twig cache dir
			$normPath = str_replace('\\', '/', $path);
			if (strpos($normPath, $normalizedDir) !== 0) {
				continue;
			}

			if ($fileInfo->isDir()) {
				@rmdir($path);
			} else {
				@unlink($path);
			}
		}

		// Keep the twig folder itself, just make sure it's writable
		if (!is_writable($realCacheDir)) {
			@chmod($realCacheDir, 0775);
		}
	}
}