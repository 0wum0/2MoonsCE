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

class CacheFile {
	private $path;
	public function __construct()
	{
		$this->path	= is_writable(CACHE_PATH) ? CACHE_PATH : $this->getTempPath();
	}

	private function getTempPath()
	{
		require_once 'includes/libs/wcf/BasicFileUtil.class.php';
		return BasicFileUtil::getTempFolder();
	}

	public function store($Key, $Value) {
		return file_put_contents($this->path.'cache.'.$Key.'.php', $Value);
	}
	
	public function open($Key) {
		if(!file_exists($this->path.'cache.'.$Key.'.php'))
			return false;
			
		return file_get_contents($this->path.'cache.'.$Key.'.php');
	}
	
	public function flush($Key) {
		if(!file_exists($this->path.'cache.'.$Key.'.php'))
			return false;
		
		return unlink($this->path.'cache.'.$Key.'.php');
	}
}