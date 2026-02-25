<?php

declare(strict_types=1);

/**
 * CoreQoLModule – GameModuleInterface v2 implementation.
 *
 * Responsibilities:
 *  - boot()          : no gameplay filter hooks needed (QoL is purely frontend).
 *  - beforeRequest() : injects qolConfig into tplObj so qol.js knows the API
 *                      endpoint and CSRF token without hardcoding them.
 *  - afterRequest()  : no-op.
 */
class CoreQoLModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'core_qol_pack.main';
    }

    public function isEnabled(): bool
    {
        // Always enabled when the plugin is active and we are in INGAME mode.
        return defined('MODE') && MODE === 'INGAME';
    }

    public function boot(GameContext $ctx): void
    {
        // No gameplay hooks. Frontend only.
    }

    public function beforeRequest(GameContext $ctx): void
    {
        try {
            if (!isset($GLOBALS['tplObj'])
                || !is_object($GLOBALS['tplObj'])
                || !method_exists($GLOBALS['tplObj'], 'assign_vars')
            ) {
                return;
            }

            // Provide qol.js with the resource-sync endpoint and a nonce.
            $apiUrl = defined('SERVER_URL')
                ? rtrim((string) SERVER_URL, '/') . '/game.php'
                : 'game.php';

            $GLOBALS['tplObj']->assign_vars([
                'qolConfig' => [
                    'apiUrl'       => $apiUrl,
                    'syncInterval' => 10000,
                    'autosaveMs'   => 5000,
                ],
                'coreQoLActive' => true,
            ], true);
        } catch (Throwable $e) {
            error_log('[CoreQoLModule] beforeRequest() error: ' . $e->getMessage());
        }
    }

    public function afterRequest(GameContext $ctx): void {}
}
