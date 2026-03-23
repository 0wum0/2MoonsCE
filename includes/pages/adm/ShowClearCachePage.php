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

// @admin-migrated (Phase 7 — AbstractAdminPage + CacheService)
require_once 'includes/pages/adm/CacheService.php';

/**
 * Clears all known cache directories and shows a result message.
 * Cache logic lives in CacheService; this class only formats the message.
 */
class ShowClearCachePage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowClearCachePage');
    }

    protected function run(): void
    {
        global $LNG;

        $res = CacheService::clearAll();

        $msg = $LNG['cc_cache_clear'] ?? 'Cache wurde geleert.';
        $msg .= '<br><br><small style="opacity:.8;">'
              . 'Gelöscht: ' . (int) $res['cleared_files'] . ' Dateien, '
              . (int) $res['cleared_dirs'] . ' Ordner'
              . ($res['skipped'] ? ' | Übersprungen: ' . (int) $res['skipped'] : '')
              . '</small>';

        if (!empty($res['errors'])) {
            $msg .= '<br><br><small style="color:#f87171;">'
                  . 'Hinweise: ' . htmlspecialchars(implode(' | ', $res['errors']), ENT_QUOTES, 'UTF-8')
                  . '</small>';
        }

        $this->message($msg);
    }
}