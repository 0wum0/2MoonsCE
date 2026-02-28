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

class HTTP {
	
	static public function redirectTo(string $URL, bool $external = false): never
	{
		if($external)
		{
			self::sendHeader('Location', $URL);
		}
		else
		{
			self::sendHeader('Location', HTTP_PATH.$URL);
		}
		exit;
	}

	static public function sendHeader(string $name, ?string $value = NULL): void
	{
		header($name.(!is_null($value) ? ': '.$value : ''));
	}

	static public function redirectToUniverse(int $universe): never
	{
		HTTP::redirectTo(PROTOCOL.HTTP_HOST.HTTP_BASE."uni".$universe."/".HTTP_FILE, true);
	}

	static public function sendCookie(string $name, string $value = "", ?int $toTime = NULL): void
	{
		setcookie($name, $value, $toTime);
	}
	
	static public function _GP(string $name, mixed $default, bool|int|float $multibyte = false, bool $highnum = false): mixed
	{
		$multibyte	= (bool) $multibyte;

		if(!isset($_REQUEST[$name]))
		{
			return $default;
		}

		if(is_float($default) || $highnum)
		{
			return (float) $_REQUEST[$name];
		}
		
		if(is_int($default))
		{
			return (int) $_REQUEST[$name];			
		}

		if(is_string($default))
		{
			return self::_quote($_REQUEST[$name], $multibyte);
		}
		
		if(is_array($default) && is_array($_REQUEST[$name]))
		{
			return self::_quoteArray($_REQUEST[$name], $multibyte, !empty($default) && $default[0] === 0);
		}
		
		return $default;
	}

	private static function _quoteArray(array $var, bool $multibyte, bool $onlyNumbers = false): array
	{
		$data	= array();
		foreach($var as $key => $value)
		{
			if(is_array($value))
			{
				$data[$key]	= self::_quoteArray($value, $multibyte);
			}
			elseif($onlyNumbers)
			{
				$data[$key]	= (int) $value;
			}
			else
			{
				$data[$key]	= self::_quote($value, $multibyte);
			}
		}

		return $data;
	}

	private static function _quote(mixed $var, bool $multibyte): string
	{
		$var	= str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", ''), $var);
		$var	= htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
		$var	= trim($var);

		if ($multibyte) {
			if (!preg_match('/^./u', $var))
			{
				$var = '';
			}
		}
		else
		{
			$var = preg_replace('/[\x80-\xFF]/', '?', $var); // no multibyte, allow only ASCII (0-127)
		}

		return $var;
	}
}