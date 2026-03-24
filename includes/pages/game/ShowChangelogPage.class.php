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


class ShowChangelogPage extends AbstractGamePage
{
	public static $requireModule = 0;

	function __construct() 
	{
		parent::__construct();
	}
	
	function show() 
	{
        include_once ROOT_PATH.'includes/libs/Parsedown/Parsedown.php';

        $changelogFile = ROOT_PATH.'CHANGELOG.md';
        $rawContent = file_exists($changelogFile) ? file_get_contents($changelogFile) : '';

        $parsedown = new Parsedown();

        $html = $parsedown->text($rawContent !== false ? $rawContent : '');

        if (HTTP::_GP('ajax', 0)) {
            // Render inline for Dialog.open() — no full layout
            echo '<div style="padding:20px 24px;max-height:520px;overflow-y:auto;font-family:monospace;font-size:13px;color:#c8e6ff;background:#020510;line-height:1.7;">';
            echo '<style>.cl-wrap h1,.cl-wrap h2,.cl-wrap h3{color:#00d4ff;font-family:monospace;margin:14px 0 6px;} .cl-wrap h1{font-size:16px;border-bottom:1px solid rgba(0,212,255,.2);padding-bottom:6px;} .cl-wrap h2{font-size:14px;} .cl-wrap h3{font-size:13px;color:#a0c8f0;} .cl-wrap ul{margin:4px 0 8px 18px;} .cl-wrap li{margin-bottom:3px;} .cl-wrap code{background:rgba(0,212,255,.1);border:1px solid rgba(0,212,255,.2);border-radius:3px;padding:1px 5px;font-size:11px;} .cl-wrap a{color:#00d4ff;} .cl-wrap hr{border-color:rgba(0,212,255,.15);margin:10px 0;}</style>';
            echo '<div class="cl-wrap">' . $html . '</div>';
            echo '</div>';
            exit;
        }

		$this->assign(array(
			'ChangelogList'	=> $html,
		));
		
		$this->display('page.changelog.default.twig');
	}
}