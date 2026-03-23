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
class ShowPassEncripterPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowPassEncripterPage');
    }

    protected function run(): void
    {
        global $LNG;

        $password = HTTP::_GP('md5q', '', true);

        $this->assign([
            'md5_md5'          => $password,
            'md5_enc'          => PlayerUtil::cryptPassword($password),
            'et_md5_encripter' => $LNG['et_md5_encripter'],
            'et_encript'       => $LNG['et_encript'],
            'et_result'        => $LNG['et_result'],
            'et_pass'          => $LNG['et_pass'],
        ]);

        $this->show('PassEncripterPage.twig');
    }
}