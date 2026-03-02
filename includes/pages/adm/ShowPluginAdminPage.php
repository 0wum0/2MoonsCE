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

/**
 * Resolve the real filesystem path to a plugin directory by its id.
 * Plugin folder names may differ in casing from the id stored in the DB
 * (e.g. folder 'GalacticEvents' vs id 'galactic_events').
 * Scans plugins/ for a sub-directory whose manifest.json declares the given id.
 * Returns null if not found.
 */
function resolvePluginDir(string $pluginId): ?string
{
    $base = ROOT_PATH . 'plugins/';
    if (!is_dir($base)) {
        return null;
    }
    foreach (scandir($base) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $dir = $base . $entry;
        if (!is_dir($dir)) {
            continue;
        }
        $mf = $dir . '/manifest.json';
        if (!file_exists($mf)) {
            continue;
        }
        $data = json_decode((string)file_get_contents($mf), true);
        if (is_array($data) && isset($data['id']) && (string)$data['id'] === $pluginId) {
            return $dir;
        }
    }
    return null;
}

function ShowPluginAdminPage(): void
{
    global $LNG;

    $pm     = PluginManager::get();
    $action = HTTP::_GP('action', '');
    $id     = HTTP::_GP('pluginId', '');
    $id     = preg_replace('/[^a-z0-9\-_]/', '', strtolower($id));

    $message = '';
    $error   = '';

    // ── Actions ──────────────────────────────────────────────────────────────

    $cacheChanged = false;

    if ($action === 'activate' && $id !== '') {
        try {
            $pm->activate($id);
            $message = 'Plugin "' . htmlspecialchars($id) . '" aktiviert.';
            $cacheChanged = true;
        } catch (Throwable $e) {
            $error = 'Fehler beim Aktivieren: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'deactivate' && $id !== '') {
        try {
            $pm->deactivate($id);
            $message = 'Plugin "' . htmlspecialchars($id) . '" deaktiviert.';
            $cacheChanged = true;
        } catch (Throwable $e) {
            $error = 'Fehler beim Deaktivieren: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'uninstall' && $id !== '') {
        try {
            $pm->uninstall($id);
            $message = 'Plugin "' . htmlspecialchars($id) . '" deinstalliert.';
            $cacheChanged = true;
        } catch (Throwable $e) {
            $error = 'Fehler beim Deinstallieren: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'installLocal' && $id !== '') {
        try {
            $pluginDir = resolvePluginDir($id) ?? (ROOT_PATH . 'plugins/' . $id);
            $manifest = $pm->readManifest($pluginDir);
            $pm->install($manifest);
            $message = 'Plugin "' . htmlspecialchars($manifest['name']) . '" installiert.';
            $cacheChanged = true;
        } catch (Throwable $e) {
            $error = 'Installationsfehler: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'saveConfig' && $id !== '') {
        try {
            $pluginDir = resolvePluginDir($id) ?? (ROOT_PATH . 'plugins/' . $id);
            $manifest  = $pm->readManifest($pluginDir);
            $settings  = is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [];
            $newConfig = [];
            foreach ($settings as $field) {
                $key  = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($field['key'] ?? '')));
                $type = (string) ($field['type'] ?? 'text');
                if ($key === '') {
                    continue;
                }
                $raw = $_POST['cfg_' . $key] ?? null;
                if ($type === 'bool' || $type === 'checkbox') {
                    $newConfig[$key] = ($raw === '1' || $raw === 'true' || $raw === 'on') ? true : false;
                } elseif ($type === 'int' || $type === 'number') {
                    $newConfig[$key] = (int) $raw;
                } elseif ($type === 'float') {
                    $newConfig[$key] = (float) $raw;
                } else {
                    $newConfig[$key] = (string) ($raw ?? '');
                }
            }
            $pm->setAllConfig($id, $newConfig);
            $message = 'Einstellungen für Plugin "' . htmlspecialchars($id) . '" gespeichert.';
        } catch (Throwable $e) {
            $error = 'Fehler beim Speichern: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'upload' && isset($_FILES['plugin_zip'])) {
        $file = $_FILES['plugin_zip'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload-Fehler (Code ' . $file['error'] . ').';
        } elseif (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['zip'], true)) {
            $error = 'Nur ZIP-Dateien sind erlaubt.';
        } else {
            $tmpPath = $file['tmp_name'];
            try {
                $manifest = $pm->installFromZip($tmpPath);
                $pm->install($manifest);
                $message = 'Plugin "' . htmlspecialchars($manifest['name']) . '" (v' . htmlspecialchars($manifest['version']) . ') erfolgreich installiert.';
                $cacheChanged = true;
            } catch (Throwable $e) {
                $error = 'Installationsfehler: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    // Flush vars cache so VarsBuildCache picks up any DB changes from plugin SQL migrations
    if ($cacheChanged) {
        Cache::get()->flush('vars');
    }

    // ── Load plugin list ──────────────────────────────────────────────────────

    $installedPlugins = $pm->getAllPlugins();

    // Scan filesystem first so we can do case-insensitive folder lookup below.
    // scanPluginsDir() keys manifests by the id field inside manifest.json, which
    // matches the DB id — independent of the actual folder name casing.
    $scanned = $pm->scanPluginsDir();

    // Enrich each installed plugin with settings schema + current config.
    // Use the scanned manifest (keyed by plugin id) to avoid folder-name casing
    // mismatches (e.g. folder 'GalacticEvents' vs id 'galactic_events').
    foreach ($installedPlugins as &$pluginRow) {
        $pid = (string) $pluginRow['id'];
        try {
            // Prefer the already-scanned manifest (avoids folder-name casing issue).
            // Fall back to direct path lookup for plugins whose folder name matches id.
            if (isset($scanned[$pid])) {
                $mf = $scanned[$pid];
            } else {
                $mf = $pm->readManifest(ROOT_PATH . 'plugins/' . $pid);
            }
            $pluginRow['settings']    = is_array($mf['settings'] ?? null) ? $mf['settings'] : [];
            $pluginRow['description'] = (string) ($mf['description'] ?? '');
            $pluginRow['author']      = (string) ($mf['author']      ?? '');
        } catch (Throwable $e) {
            $pluginRow['settings']    = [];
            $pluginRow['description'] = '';
            $pluginRow['author']      = '';
        }
        $pluginRow['config'] = $pm->getAllConfig($pid);
    }
    unset($pluginRow);
    $installedIds = array_column($installedPlugins, 'id');

    $uninstalledPlugins = [];
    foreach ($scanned as $scannedId => $manifest) {
        if (!in_array($scannedId, $installedIds, true)) {
            $uninstalledPlugins[] = $manifest;
        }
    }

    // ── Hook debug summary ────────────────────────────────────────────────────

    $hookDebug = ['actions' => [], 'filters' => []];
    if (class_exists('HookManager')) {
        $hm = HookManager::get();
        foreach ($hm->getRegisteredActions() as $hookName => $priorities) {
            $count = 0;
            foreach ($priorities as $cbs) { $count += count($cbs); }
            $hookDebug['actions'][] = ['name' => $hookName, 'count' => $count];
        }
        foreach ($hm->getRegisteredFilters() as $hookName => $priorities) {
            $count = 0;
            foreach ($priorities as $cbs) { $count += count($cbs); }
            $hookDebug['filters'][] = ['name' => $hookName, 'count' => $count];
        }
    }

    // ── Build plugin-id → admin route page name map ───────────────────────────

    // adminRoutes keys are page names (e.g. 'plugin_galactic_events')
    // We need to match them back to plugin ids by checking the registered file paths.
    $adminRouteMap = [];
    foreach ($pm->getAdminRoutes() as $pageName => $route) {
        // File path contains plugins/<FolderName>/ — extract folder name from path.
        // Normalise to lowercase so it matches plugin IDs regardless of folder casing
        // (e.g. 'GalacticEvents' folder → id 'galactic_events').
        $pluginsDir = str_replace('\\', '/', ROOT_PATH . 'plugins/');
        $filePath   = str_replace('\\', '/', $route['file']);
        if (str_starts_with($filePath, $pluginsDir)) {
            $rel   = substr($filePath, strlen($pluginsDir));
            $parts = explode('/', $rel);
            if (!empty($parts[0])) {
                $adminRouteMap[strtolower($parts[0])] = $pageName;
            }
        }
    }

    // Attach admin_page to each installed plugin
    foreach ($installedPlugins as &$pluginRow) {
        $pluginRow['admin_page'] = $adminRouteMap[strtolower((string)$pluginRow['id'])] ?? '';
    }
    unset($pluginRow);

    // ── Render ────────────────────────────────────────────────────────────────

    $template = new template();
    $template->assign_vars([
        'message'            => $message,
        'error'              => $error,
        'installedPlugins'   => $installedPlugins,
        'uninstalledPlugins' => $uninstalledPlugins,
        'hookDebug'          => $hookDebug,
    ]);
    $template->show('PluginAdminPage.twig');
}
