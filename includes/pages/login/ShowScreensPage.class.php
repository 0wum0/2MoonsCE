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


class ShowScreensPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct() 
	{
		parent::__construct();
        $this->setWindow('light');
	}
	
	function show() 
	{
		$screenshots	= array();
		$directoryIterator = new DirectoryIterator('styles/resource/images/login/screens/');
        foreach ($directoryIterator as $fileInfo)
		{
			/** @var $fileInfo DirectoryIterator */
			if (!$fileInfo->isFile())
			{
				continue;
            }			
			
			$thumbnail = 'styles/resource/images/login/screens/'.$fileInfo->getFilename();
			if(file_exists('styles/resource/images/login/screens/thumbnails/'.$fileInfo->getFilename()))
			{
				$thumbnail = 'styles/resource/images/login/screens/thumbnails/'.$fileInfo->getFilename();
			}
			
			$screenshots[]	= array(
				'path' 		=> 'styles/resource/images/login/screens/'.$fileInfo->getFilename(),
				'thumbnail' => $thumbnail,
			);
		}
		
		$this->assign(array(
			'screenshots' => $screenshots
		));

		$this->display('page.screens.default.tpl');
	}
}
