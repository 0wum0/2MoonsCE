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

        $changelogFile = ROOT_PATH.'CHANGES.md';
        $rawContent = file_exists($changelogFile) ? file_get_contents($changelogFile) : '';

        $parsedown = new Parsedown();

		$this->assign(array(
			'ChangelogList'	=> $parsedown->text($rawContent !== false ? $rawContent : ''),
		));
		
		$this->display('page.changelog.default.tpl');
	}
}