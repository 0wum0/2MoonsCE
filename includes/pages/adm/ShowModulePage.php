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
class ShowModulePage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowModulePage');
    }

    protected function run(): void
    {
        global $LNG;

        $config  = Config::get(Universe::getEmulated());
        $modules = explode(';', $config->moduls);

        if (isset($_GET['mode'])) {
            $modules[HTTP::_GP('id', 0)] = ($_GET['mode'] === 'aktiv') ? 1 : 0;
            $config->moduls = implode(';', $modules);
            $config->save();
            ClearCache();
        }

        $moduleList = [];
        foreach (range(0, MODULE_AMOUNT - 1) as $id) {
            $moduleList[$id] = [
                'name'  => $LNG['modul_' . $id],
                'state' => $modules[$id] ?? 1,
            ];
        }
        asort($moduleList);

        $this->assign([
            'Modules'             => $moduleList,
            'mod_module'          => $LNG['mod_module'],
            'mod_info'            => $LNG['mod_info'],
            'mod_active'          => $LNG['mod_active'],
            'mod_deactive'        => $LNG['mod_deactive'],
            'mod_change_active'   => $LNG['mod_change_active'],
            'mod_change_deactive' => $LNG['mod_change_deactive'],
        ]);

        $this->show('ModulePage.twig');
    }
}