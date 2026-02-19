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

require_once 'includes/classes/cache/builder/BuildCache.interface.php';
require_once 'includes/classes/cache/resource/CacheFile.class.php';

class Cache
{
	private ?CacheFile $cacheResource = NULL;
	private array $cacheBuilder = array();
	private array $cacheObj = array();

	static private ?Cache $obj = NULL;

	static public function get(): Cache
	{
		if(is_null(self::$obj))
		{
			self::$obj	= new self;
		}

		return self::$obj;
	}

	private function __construct() {
		$this->cacheResource = new CacheFile();
	}
	
	public function add(string $Key, string $ClassName): void {
		$this->cacheBuilder[$Key]	= $ClassName;
	}

	public function getData(string $Key, bool $rebuild = true): array {
		if(!isset($this->cacheObj[$Key]) && !$this->load($Key))
		{
			if($rebuild)
			{
				$this->buildCache($Key);
			}
			else
			{
				return array();
			}
		}
		return $this->cacheObj[$Key];
	}

	public function flush(string $Key): bool {
		if(!isset($this->cacheObj[$Key]) && !$this->load($Key))
			$this->buildCache($Key);
		
		$this->cacheResource->flush($Key);
		return $this->buildCache($Key);
	}

	public function load(string $Key): bool {
		$cacheData	= $this->cacheResource->open($Key);
		
		if($cacheData === false)
			return false;
			
		$cacheData	= unserialize($cacheData);
		if($cacheData === false)
			return false;
		
		$this->cacheObj[$Key] = $cacheData;
		return true;
	}

	public function buildCache(string $Key): bool {
		$className		= $this->cacheBuilder[$Key];

		$path			= 'includes/classes/cache/builder/'.$className.'.class.php';
		require_once $path;

		/** @var $cacheBuilder BuildCache */
		$cacheBuilder	= new $className();
		$cacheData		= $cacheBuilder->buildCache();
		$cacheData		= (array) $cacheData;
		$this->cacheObj[$Key] = $cacheData;
		$cacheData		= serialize($cacheData);
		$this->cacheResource->store($Key, $cacheData);
		return true;
	}
}