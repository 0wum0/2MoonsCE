<?php

declare(strict_types=1);

/**
 * ShowPluginAdminPage.php
 * Plugin System v1 – Admin Panel
 * Handles: list, ZIP upload/install, activate, deactivate, uninstall
 */

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

    if ($action === 'activate' && $id !== '') {
        try {
            $pm->activate($id);
            $message = 'Plugin "' . htmlspecialchars($id) . '" aktiviert.';
        } catch (Throwable $e) {
            $error = 'Fehler beim Aktivieren: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'deactivate' && $id !== '') {
        try {
            $pm->deactivate($id);
            $message = 'Plugin "' . htmlspecialchars($id) . '" deaktiviert.';
        } catch (Throwable $e) {
            $error = 'Fehler beim Deaktivieren: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'uninstall' && $id !== '') {
        try {
            $pm->uninstall($id);
            $message = 'Plugin "' . htmlspecialchars($id) . '" deinstalliert.';
        } catch (Throwable $e) {
            $error = 'Fehler beim Deinstallieren: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'installLocal' && $id !== '') {
        try {
            $manifest = $pm->readManifest(ROOT_PATH . 'plugins/' . $id);
            $pm->install($manifest);
            $message = 'Plugin "' . htmlspecialchars($manifest['name']) . '" installiert.';
        } catch (Throwable $e) {
            $error = 'Installationsfehler: ' . htmlspecialchars($e->getMessage());
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
            } catch (Throwable $e) {
                $error = 'Installationsfehler: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Load plugin list ──────────────────────────────────────────────────────

    $installedPlugins = $pm->getAllPlugins();

    // Scan filesystem for plugins not yet in DB
    $scanned = $pm->scanPluginsDir();
    $installedIds = array_column($installedPlugins, 'id');

    $uninstalledPlugins = [];
    foreach ($scanned as $scannedId => $manifest) {
        if (!in_array($scannedId, $installedIds, true)) {
            $uninstalledPlugins[] = $manifest;
        }
    }

    // ── Render ────────────────────────────────────────────────────────────────

    $template = new template();
    $template->assign_vars([
        'message'            => $message,
        'error'              => $error,
        'installedPlugins'   => $installedPlugins,
        'uninstalledPlugins' => $uninstalledPlugins,
    ]);
    $template->show('PluginAdminPage.twig');
}
