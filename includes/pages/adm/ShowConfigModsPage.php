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
if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowConfigModsPage()
{
    global $LNG;
    $config = Config::get(Universe::getEmulated());

    if (!empty($_POST))
    {
        $config_before = array(
            'expedition_limit_res'				=> $config->expedition_limit_res,
            'expedition_limit_res_active'				=> $config->expedition_limit_res_active,
        );

        $expedition_limit_res_active 				= isset($_POST['expedition_limit_res_active']) && $_POST['expedition_limit_res_active'] == 'on' ? 1 : 0;

        $expedition_limit_res				= HTTP::_GP('expedition_limit_res', 0);

        $config_after = array(
            'expedition_limit_res'				=> $expedition_limit_res,
            'expedition_limit_res_active'				=> $expedition_limit_res_active,
        );

        foreach($config_after as $key => $value)
        {
            $config->$key	= $value;
        }
        $config->save();

        $LOG = new Log(3);
        $LOG->target = 0;
        $LOG->old = $config_before;
        $LOG->new = $config_after;
        $LOG->save();
    }

    $template	= new template();

    $template->assign_vars(array(
        'expedition_limit_res'					=> $config->expedition_limit_res,
        'expedition_limit_res_active'				=> $config->expedition_limit_res_active,
    ));

    $template->show('ConfigModsBody.tpl');
}