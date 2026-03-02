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

class HTTPRequest
{
	private $url		= NULL;
	private $content	= NULL;
	private $ch			= NULL;

	public function __construct($url = NULL)
	{
		$this->url = $url;
	}

	public function send()
	{
		if(function_exists("curl_init"))
		{
			$this->ch	= curl_init($this->url);
			curl_setopt($this->ch, CURLOPT_HTTPGET, true);
			curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; 2Moons/".Config::get()->VERSION."; +http://2moons.cc)");
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
				"Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3",
				"Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4",
			));
			
			$this->content	= curl_exec($this->ch);
			curl_close($this->ch);
		}
	}
	
	public function getResponse()
	{
		$this->send();
		return $this->content;
	}
}