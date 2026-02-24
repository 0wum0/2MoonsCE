<?php

declare(strict_types=1);

/**
 * ShowSystemDebugPage.php
 * Admin Debug Panel – v2 System Inspector
 *
 * Displays:
 *   A) Plugins   – installed list from DB + manifest summary
 *   B) Hooks     – all registered actions/filters with handler details
 *   C) Assets    – all registered CSS/JS from AssetRegistry
 *   D) Modules   – loaded v2 GameModules with enabled status + origin
 *
 * Access: AUTH_ADM only (full admin).
 */

function ShowSystemDebugPage(): void
{
    global $USER;

    // ── Auth guard ────────────────────────────────────────────────────────────
    // Only full admins (AUTH_ADM) may access this page.
    if ((int)$USER['authlevel'] !== AUTH_ADM) {
        HTTP::redirectTo('admin.php');
    }

    // ── A) Plugins ────────────────────────────────────────────────────────────
    $pm               = PluginManager::get();
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
        'plugins'   => $installedPlugins,
        'hooks'     => $hooks,
        'cssAssets' => $cssAssets,
        'jsAssets'  => $jsAssets,
        'modules'   => $modules,
        'hasModuleManager' => class_exists('ModuleManager'),
    ]);
    $template->show('SystemDebugPage.twig');
}
