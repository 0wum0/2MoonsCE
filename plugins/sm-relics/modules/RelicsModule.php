<?php

declare(strict_types=1);

/**
 * RelicsModule – sm-relics v2 GameModuleInterface
 *
 * Auto-loaded by PluginManager::loadPluginModules() via manifest["modules"].
 *
 * Hooks:
 *   FILTER game.buildTime        – Industrie doctrine: -X% all build times
 *                                  Forschungs doctrine: -X% tech build times only
 *   FILTER production.calculate  – Wirtschafts doctrine: +X% production
 *   beforeRequest                – loads active doctrine into GameContext bag
 *                                  (war doctrine bonus available to combat code)
 */
class RelicsModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'sm-relics.relics';
    }

    public function isEnabled(): bool
    {
        try {
            $row = Database::get()->selectSingle(
                'SELECT `v` FROM %%RELICS_SETTINGS%% WHERE `k` = :k;',
                [':k' => 'enabled']
            );
            return ($row !== null && isset($row['v'])) ? ((int) $row['v'] === 1) : true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function boot(GameContext $ctx): void
    {
        // ── game.buildTime filter ─────────────────────────────────────────────
        HookManager::get()->addFilter(
            'game.buildTime',
            function (int|float $time, array $buildCtx) use ($ctx): int|float {
                $doctrine = $ctx->get('smr_doctrine');
                if ($doctrine === null) {
                    return $time;
                }

                if ($doctrine === 'industry') {
                    $bonus = $this->getSetting('doctrine_build_bonus', 10);
                    $time  = $time * (1.0 - $bonus / 100.0);
                } elseif ($doctrine === 'research') {
                    $elementId = (int) ($buildCtx['element'] ?? 0);
                    $isTech    = isset($GLOBALS['reslist']['tech'])
                        && in_array($elementId, (array) $GLOBALS['reslist']['tech'], true);
                    if ($isTech) {
                        $bonus = $this->getSetting('doctrine_research_bonus', 10);
                        $time  = $time * (1.0 - $bonus / 100.0);
                    }
                }

                return max(1, (int) round((float) $time));
            },
            20
        );

        // ── production.calculate filter ───────────────────────────────────────
        HookManager::get()->addFilter(
            'production.calculate',
            function (float $production, array $prodCtx) use ($ctx): float {
                if ($ctx->get('smr_doctrine') !== 'economy') {
                    return $production;
                }
                $bonus = $this->getSetting('doctrine_prod_bonus', 5);
                return $production * (1.0 + $bonus / 100.0);
            },
            20
        );

        // Also bridge to game.production (legacy alias)
        HookManager::get()->addFilter(
            'game.production',
            function (float $production, array $prodCtx) use ($ctx): float {
                if ($ctx->get('smr_doctrine') !== 'economy') {
                    return $production;
                }
                $bonus = $this->getSetting('doctrine_prod_bonus', 5);
                return $production * (1.0 + $bonus / 100.0);
            },
            20
        );
    }

    public function beforeRequest(GameContext $ctx): void
    {
        if (empty($ctx->user['id'])) {
            return;
        }
        $userId = (int) $ctx->user['id'];
        try {
            $row = Database::get()->selectSingle(
                'SELECT `doctrine` FROM %%RELICS_USER%% WHERE `user_id` = :uid;',
                [':uid' => $userId]
            );
            $doctrine = ($row !== null && !empty($row['doctrine'])) ? $row['doctrine'] : null;
            $ctx->set('smr_doctrine', $doctrine);

            if ($doctrine === 'war') {
                $bonus = $this->getSetting('doctrine_combat_bonus', 5);
                $ctx->set('smr_war_bonus', $bonus);
            }
        } catch (Throwable $e) {
            $ctx->set('smr_doctrine', null);
        }
    }

    public function afterRequest(GameContext $ctx): void
    {
        // no-op
    }

    private function getSetting(string $key, int $default): int
    {
        try {
            $row = Database::get()->selectSingle(
                'SELECT `v` FROM %%RELICS_SETTINGS%% WHERE `k` = :k;',
                [':k' => $key]
            );
            return ($row !== null && isset($row['v'])) ? (int) $row['v'] : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}
