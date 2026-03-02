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

class ArrayUtil
{
	static public function combineArrayWithSingleElement(array $keys, mixed $var): array
	{
		if(empty($keys))
		{
			return array();
		}
		return array_combine($keys, array_fill(0, count($keys), $var));
	}

	static public function combineArrayWithKeyElements(array $keys, array $var): array
	{
		$temp	= array();
		foreach($keys as $key)
		{
			if(isset($var[$key]))
			{
				$temp[$key]	= $var[$key];
			}
			else
			{
				$temp[$key]	= $key;
			}
		}
		
		return $temp;
	}
	
	// http://www.php.net/manual/en/function.array-key-exists.php#81659
	static public function arrayKeyExistsRecursive(string|int $needle, array $haystack): bool
	{
		$result = array_key_exists($needle, $haystack);
		
		if ($result)
		{
			return $result;
		}
		
		foreach ($haystack as $v)
		{
			if (is_array($v))
			{
				$result = self::arrayKeyExistsRecursive($needle, $v);
			}
			
			if ($result)
			{
				return $result;
			}
		}
		
		return $result;
	}
}