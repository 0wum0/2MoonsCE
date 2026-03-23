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

// @admin-migrated (Phase 4 — AbstractAdminPage)
class ShowDisclamerPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowDisclamerPage');
    }

    protected function run(): void
    {
        global $LNG;

        $config = Config::get(Universe::getEmulated());

        if (!empty($_POST)) {
            $configBefore = [
                'disclamerAddress' => $config->disclamerAddress,
                'disclamerPhone'   => $config->disclamerPhone,
                'disclamerMail'    => $config->disclamerMail,
                'disclamerNotice'  => $config->disclamerNotice,
            ];

            $configAfter = [
                'disclamerAddress' => HTTP::_GP('disclaimerAddress', '', true),
                'disclamerPhone'   => HTTP::_GP('disclaimerPhone',   '', true),
                'disclamerMail'    => HTTP::_GP('disclaimerMail',    '', true),
                'disclamerNotice'  => HTTP::_GP('disclaimerNotice',  '', true),
            ];

            foreach ($configAfter as $key => $value) {
                $config->$key = $value;
            }
            $config->save();

            $log         = new Log(3);
            $log->target = 5;
            $log->old    = $configBefore;
            $log->new    = $configAfter;
            $log->save();
        }

        $this->assign([
            'disclaimerAddress'    => $config->disclamerAddress,
            'disclaimerPhone'      => $config->disclamerPhone,
            'disclaimerMail'       => $config->disclamerMail,
            'disclaimerNotice'     => $config->disclamerNotice,
            'se_server_parameters' => $LNG['mu_disclaimer'],
            'se_save_parameters'   => $LNG['se_save_parameters'],
            'se_disclaimerAddress' => $LNG['se_disclaimerAddress'],
            'se_disclaimerPhone'   => $LNG['se_disclaimerPhone'],
            'se_disclaimerMail'    => $LNG['se_disclaimerMail'],
            'se_disclaimerNotice'  => $LNG['se_disclaimerNotice'],
        ]);

        $this->show('DisclamerConfigBody.twig');
    }
}
