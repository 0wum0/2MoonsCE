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
        // Note: game.production/production.calculate passes a numeric value per
        // resource type; accept mixed to avoid fatal type errors.
        HookManager::get()->addFilter(
            'production.calculate',
            function (mixed $production, mixed $prodCtx) use ($ctx): mixed {
                if (!is_numeric($production) || $ctx->get('smr_doctrine') !== 'economy') {
                    return $production;
                }
                $bonus = $this->getSetting('doctrine_prod_bonus', 5);
                return (float)$production * (1.0 + $bonus / 100.0);
            },
            20
        );

        // Also bridge to game.production (legacy alias)
        HookManager::get()->addFilter(
            'game.production',
            function (mixed $production, mixed $prodCtx) use ($ctx): mixed {
                if (!is_numeric($production) || $ctx->get('smr_doctrine') !== 'economy') {
                    return $production;
                }
                $bonus = $this->getSetting('doctrine_prod_bonus', 5);
                return (float)$production * (1.0 + $bonus / 100.0);
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
