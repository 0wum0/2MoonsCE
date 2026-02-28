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

function ShowSystemDebugPage(): void
{
    global $USER;

    // ── Auth guard ────────────────────────────────────────────────────────────
    // Only full admins (AUTH_ADM) may access this page.
    if ((int)$USER['authlevel'] !== AUTH_ADM) {
        HTTP::redirectTo('admin.php');
    }

    $pm = PluginManager::get();

    // ── Action: clear safe-mode lock ──────────────────────────────────────────
    $action = HTTP::_GP('action', '');
    if ($action === 'clearSafeModeLock') {
        $pm->clearSafeModeLock();
        HTTP::redirectTo('admin.php?page=systemDebug&cleared=1');
    }

    $safeModeCleared  = HTTP::_GP('cleared', 0) === 1;
    $safeModeLocked   = $pm->isSafeModeLocked();
    $safeModeInfo     = $pm->getSafeModeLockInfo();

    // ── A) Plugins ────────────────────────────────────────────────────────────
    $installedPlugins = $pm->getAllPlugins();

    // Enrich each DB row with manifest data (name, description, author, modules)
    foreach ($installedPlugins as &$row) {
        $id  = (string)$row['id'];
        $dir = ROOT_PATH . 'plugins/' . $id . '/';
        $manifestPath = $dir . 'manifest.json';
        $row['_dir']      = 'plugins/' . $id . '/';
        $row['_manifest'] = [];
        $row['_modules']  = [];
        if (file_exists($manifestPath)) {
            $raw = file_get_contents($manifestPath);
            if ($raw !== false) {
                $manifest = json_decode($raw, true);
                if (is_array($manifest)) {
                    $row['_manifest'] = $manifest;
                    if (!empty($manifest['modules']) && is_array($manifest['modules'])) {
                        $row['_modules'] = $manifest['modules'];
                    }
                }
            }
        }
    }
    unset($row);

    // ── B) Hooks ──────────────────────────────────────────────────────────────
    $hm = HookManager::get();

    $rawActions = $hm->getRegisteredActions();
    $rawFilters = $hm->getRegisteredFilters();

    // Build a flat list: [{hookName, type, priority, signature, handlerCount}]
    $hooks = [];

    foreach ($rawActions as $hookName => $priorityGroups) {
        ksort($priorityGroups);
        $totalHandlers = 0;
        $handlers = [];
        foreach ($priorityGroups as $priority => $callbacks) {
            foreach ($callbacks as $cb) {
                $totalHandlers++;
                $handlers[] = [
                    'priority'  => $priority,
                    'signature' => HookManager::callbackSignature($cb),
                ];
            }
        }
        $hooks[] = [
            'name'     => $hookName,
            'type'     => 'action',
            'count'    => $totalHandlers,
            'handlers' => $handlers,
        ];
    }

    foreach ($rawFilters as $hookName => $priorityGroups) {
        ksort($priorityGroups);
        $totalHandlers = 0;
        $handlers = [];
        foreach ($priorityGroups as $priority => $callbacks) {
            foreach ($callbacks as $cb) {
                $totalHandlers++;
                $handlers[] = [
                    'priority'  => $priority,
                    'signature' => HookManager::callbackSignature($cb),
                ];
            }
        }
        $hooks[] = [
            'name'     => $hookName,
            'type'     => 'filter',
            'count'    => $totalHandlers,
            'handlers' => $handlers,
        ];
    }

    // Sort hooks alphabetically by name
    usort($hooks, static fn($a, $b) => strcmp($a['name'], $b['name']));

    // ── C) Assets ─────────────────────────────────────────────────────────────
    $ar        = AssetRegistry::get();
    $cssAssets = $ar->getAllCssAssets();
    $jsAssets  = $ar->getAllJsAssets();

    // ── D) Modules ────────────────────────────────────────────────────────────
    $modules = [];

    if (class_exists('ModuleManager')) {
        $mm  = ModuleManager::get();
        $ids = $mm->getRegisteredIds();

        // We need to access the registry to get priority + enabled state.
        // ModuleManager exposes getRegisteredIds() and has().
        // To get priority we use reflection (read-only, debug only).
        $registry = [];
        try {
            $ref      = new ReflectionObject($mm);
            $regProp  = $ref->getProperty('registry');
            $regProp->setAccessible(true);
            $registry = $regProp->getValue($mm);
        } catch (Throwable $e) {
            // Reflection failed – degrade gracefully
        }

        // Determine which module ids come from plugins (priority 100) vs core (priority 10)
        $pluginModuleIds = [];
        foreach ($installedPlugins as $pluginRow) {
            foreach ($pluginRow['_modules'] as $relPath) {
                $pluginModuleIds[] = basename($relPath, '.php');
            }
        }

        foreach ($ids as $moduleId) {
            $entry    = $registry[$moduleId] ?? null;
            $priority = $entry ? (int)$entry['priority'] : 0;
            /** @var \GameModuleInterface|null $moduleObj */
            $moduleObj = $entry ? $entry['module'] : null;
            $enabled   = false;
            $className = $moduleObj ? get_class($moduleObj) : '?';

            if ($moduleObj !== null) {
                try {
                    $enabled = $moduleObj->isEnabled();
                } catch (Throwable $e) {
                    $enabled = false;
                }
            }

            // Determine origin: core (priority ≤ 10) or plugin
            $origin = ($priority <= 10) ? 'core' : 'plugin';

            $modules[] = [
                'id'       => $moduleId,
                'class'    => $className,
                'priority' => $priority,
                'enabled'  => $enabled,
                'origin'   => $origin,
            ];
        }

        // Sort by priority asc, then id
        usort($modules, static fn($a, $b) => $a['priority'] !== $b['priority']
            ? $a['priority'] <=> $b['priority']
            : strcmp($a['id'], $b['id'])
        );
    }

    // ── Render ────────────────────────────────────────────────────────────────
    $template = new template();
    $template->assign_vars([
        'plugins'          => $installedPlugins,
        'hooks'            => $hooks,
        'cssAssets'        => $cssAssets,
        'jsAssets'         => $jsAssets,
        'modules'          => $modules,
        'hasModuleManager' => class_exists('ModuleManager'),
        'safeModeLocked'   => $safeModeLocked,
        'safeModeInfo'     => $safeModeInfo,
        'safeModeCleared'  => $safeModeCleared,
    ]);
    $template->show('SystemDebugPage.twig');
}
