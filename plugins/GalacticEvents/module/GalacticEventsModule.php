<?php
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
// Defensive: ensure DB helper is available if module is loaded without plugin.php
require_once __DIR__ . '/../lib/GalacticEventsDb.php';

/**
 * GalacticEventsModule – GameModuleInterface v2 implementation.
 *
 * Responsibilities:
 *  - boot()          : registers all gameplay filter hooks (production, buildTime,
 *                      researchTime, energyOutput) and the sidebar_end banner hook.
 *  - beforeRequest() : injects active-event data into the Twig template context.
 *  - afterRequest()  : no-op cleanup.
 *
 * All hooks are wrapped in try/catch so a crash here never propagates to core.
 * When the plugin is disabled (settings.enabled = 0 or plugin deactivated)
 * isEnabled() returns false and ModuleManager skips the entire module.
 */
class GalacticEventsModule implements GameModuleInterface
{
    /** Cached active event for this request (null = none, false = not yet loaded) */
    private array|null|false $activeEventCache = false;

    public function getId(): string
    {
        return 'galactic_events.main';
    }

    public function isEnabled(): bool
    {
        try {
            $settings = GalacticEventsDb::get()->getSettings();
            if (empty($settings)) {
                return false;
            }
            return (bool)(int)($settings['enabled'] ?? 0);
        } catch (Throwable $e) {
            error_log('[GalacticEventsModule] isEnabled() error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function boot(GameContext $ctx): void
    {
        $this->activeEventCache = false;

        try {
            $hm = HookManager::get();

            // ── game.production filter ────────────────────────────────────────
            // $temp = array keyed by resource ID with 'plus','minus','max' sub-keys.
            // Resource IDs: 901=Metal 902=Crystal 903=Deuterium
            $hm->addFilter('game.production', function (array $temp, array $prodCtx): array {
                try {
                    $event = $this->getActiveEvent();
                    if ($event === null) {
                        return $temp;
                    }
                    $mult = $this->effectMultiplier($event);
                    $type = (string)($event['effect_type'] ?? '');

                    if ($type === 'metal_production' && isset($temp[901]['plus'])) {
                        $temp[901]['plus'] = (float)$temp[901]['plus'] * $mult;
                    } elseif ($type === 'crystal_production' && isset($temp[902]['plus'])) {
                        $temp[902]['plus'] = (float)$temp[902]['plus'] * $mult;
                    } elseif ($type === 'deuterium_production' && isset($temp[903]['plus'])) {
                        $temp[903]['plus'] = (float)$temp[903]['plus'] * $mult;
                    }
                } catch (Throwable $e) {
                    error_log('[GalacticEventsModule] game.production hook error: ' . $e->getMessage());
                }
                return $temp;
            }, 20);

            // ── game.buildTime filter ─────────────────────────────────────────
            $hm->addFilter('game.buildTime', function (mixed $time, array $buildCtx): mixed {
                try {
                    $event = $this->getActiveEvent();
                    if ($event === null) {
                        return $time;
                    }
                    if ((string)($event['effect_type'] ?? '') === 'build_time') {
                        $mult = $this->effectMultiplier($event);
                        return max(1, (int) round((float)$time * $mult));
                    }
                } catch (Throwable $e) {
                    error_log('[GalacticEventsModule] game.buildTime hook error: ' . $e->getMessage());
                }
                return $time;
            }, 20);

            // ── game.researchTime filter ──────────────────────────────────────
            $hm->addFilter('game.researchTime', function (mixed $time, array $resCtx): mixed {
                try {
                    $event = $this->getActiveEvent();
                    if ($event === null) {
                        return $time;
                    }
                    if ((string)($event['effect_type'] ?? '') === 'research_time') {
                        $mult = $this->effectMultiplier($event);
                        return max(1, (int) round((float)$time * $mult));
                    }
                } catch (Throwable $e) {
                    error_log('[GalacticEventsModule] game.researchTime hook error: ' . $e->getMessage());
                }
                return $time;
            }, 20);

            // ── game.energyOutput filter ──────────────────────────────────────
            // $energy is a numeric value; context contains planet/user.
            $hm->addFilter('game.energyOutput', function (mixed $energy, array $energyCtx): mixed {
                try {
                    $event = $this->getActiveEvent();
                    if ($event === null) {
                        return $energy;
                    }
                    if ((string)($event['effect_type'] ?? '') === 'energy_output') {
                        $mult = $this->effectMultiplier($event);
                        return (int) round((float)$energy * $mult);
                    }
                } catch (Throwable $e) {
                    error_log('[GalacticEventsModule] game.energyOutput hook error: ' . $e->getMessage());
                }
                return $energy;
            }, 20);

            // ── sidebar_end action – banner rendering ─────────────────────────
            $hm->addAction('sidebar_end', function (array $hookCtx): string {
                try {
                    if (!defined('MODE') || MODE !== 'INGAME') {
                        return '';
                    }
                    $event = $this->getActiveEvent();
                    if ($event === null) {
                        return '';
                    }
                    return $this->renderBanner($event);
                } catch (Throwable $e) {
                    error_log('[GalacticEventsModule] sidebar_end hook error: ' . $e->getMessage());
                    return '';
                }
            }, 20);

            // ── overview.after_planets – inline event card ────────────────────
            $hm->addAction('overview.after_planets', function (array $hookCtx): string {
                try {
                    if (!defined('MODE') || MODE !== 'INGAME') {
                        return '';
                    }
                    $event = $this->getActiveEvent();
                    if ($event === null) {
                        return '';
                    }
                    return $this->renderOverviewCard($event);
                } catch (Throwable $e) {
                    error_log('[GalacticEventsModule] overview.after_planets hook error: ' . $e->getMessage());
                    return '';
                }
            }, 20);

        } catch (Throwable $e) {
            error_log('[GalacticEventsModule] boot() error: ' . $e->getMessage());
        }
    }

    public function beforeRequest(GameContext $ctx): void
    {
        try {
            $event = $this->getActiveEvent();

            if (isset($GLOBALS['tplObj'])
                && is_object($GLOBALS['tplObj'])
                && method_exists($GLOBALS['tplObj'], 'assign_vars')
            ) {
                $GLOBALS['tplObj']->assign_vars([
                    'galacticEventActive'  => $event !== null,
                    'galacticEvent'        => $event ?? [],
                    'galacticEventUntilTs' => $event !== null ? (int)($event['active_until'] ?? 0) : 0,
                ], true);
            }
        } catch (Throwable $e) {
            error_log('[GalacticEventsModule] beforeRequest() error: ' . $e->getMessage());
        }
    }

    public function afterRequest(GameContext $ctx): void
    {
        // Reset per-request cache so tests/AJAX sub-requests get fresh data.
        $this->activeEventCache = false;
    }

    // ── Overview card ─────────────────────────────────────────────────────────

    /**
     * Render a full `.ov-card` compatible event card for the overview page.
     * @param array<string,mixed> $event
     */
    private function renderOverviewCard(array $event): string
    {
        $now   = defined('TIMESTAMP') ? TIMESTAMP : time();
        $until = (int)($event['active_until'] ?? 0);
        $secs  = max(0, $until - $now);
        $value = (float)($event['effect_value'] ?? 0);
        $sign  = $value >= 0 ? '+' : '';
        $name  = htmlspecialchars((string)($event['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type  = (string)($event['effect_type'] ?? '');

        $typeLabels = [
            'metal_production'     => 'Metall-Produktion',
            'crystal_production'   => 'Kristall-Produktion',
            'deuterium_production' => 'Deuterium-Produktion',
            'energy_output'        => 'Energie',
            'build_time'           => 'Bauzeit',
            'research_time'        => 'Forschungszeit',
        ];
        $typeLabel = htmlspecialchars($typeLabels[$type] ?? $type, ENT_QUOTES, 'UTF-8');

        $colorMap = [
            'metal_production'     => '#94a3b8',
            'crystal_production'   => '#a78bfa',
            'deuterium_production' => '#38bdf8',
            'energy_output'        => '#fbbf24',
            'build_time'           => '#f87171',
            'research_time'        => '#34d399',
        ];
        $color = $colorMap[$type] ?? '#38bdf8';

        $h  = (int)floor($secs / 3600);
        $m  = (int)floor(($secs % 3600) / 60);
        $s  = $secs % 60;
        $cd = sprintf('%02d:%02d:%02d', $h, $m, $s);

        $pct = min(100, max(0, (int)(($secs / max(1, $until - ($until - ($secs + 1)))) * 100)));

        return '
<div class="ov-card" id="ov-ge-card" style="border-color:' . $color . '33;background:linear-gradient(135deg,rgba(0,0,0,0.4),rgba(0,0,0,0.2));">
  <div class="ov-card-header" style="color:' . $color . ';border-bottom-color:' . $color . '22;">
    <i class="fas fa-meteor" style="color:' . $color . '"></i>
    &#x1F30C; Galaktisches Event
  </div>
  <div class="ov-card-body" style="padding:12px 14px;">
    <div style="font-size:12px;font-weight:700;color:' . $color . ';margin-bottom:6px;">' . $name . '</div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <span style="font-size:11px;color:var(--dim);">' . $typeLabel . '</span>
      <span style="font-size:13px;font-weight:900;color:' . $color . ';">' . $sign . $value . '%</span>
    </div>
    <div style="height:3px;background:rgba(255,255,255,0.07);border-radius:2px;margin-bottom:8px;overflow:hidden;">
      <div id="ov-ge-bar" style="height:100%;background:' . $color . ';border-radius:2px;width:100%;transition:width 1s linear;"></div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;">
      <span style="font-size:10px;color:var(--dim);font-family:var(--font-hud);letter-spacing:1px;">ENDET IN</span>
      <span id="ov-ge-countdown" style="font-size:13px;font-weight:700;font-family:var(--font-hud);color:' . $color . ';" data-until="' . $until . '">' . $cd . '</span>
    </div>
  </div>
</div>
<script>
(function(){
  var el=document.getElementById("ov-ge-countdown");
  var bar=document.getElementById("ov-ge-bar");
  if(!el)return;
  var until=parseInt(el.getAttribute("data-until"),10);
  var totalSecs=' . $secs . ';
  function tick(){
    var now=Math.floor(Date.now()/1000);
    var left=Math.max(0,until-now);
    var h=Math.floor(left/3600),m=Math.floor((left%3600)/60),s=left%60;
    el.textContent=(h<10?"0":"")+h+":"+(m<10?"0":"")+m+":"+(s<10?"0":"")+s;
    if(bar&&totalSecs>0)bar.style.width=Math.round(left/totalSecs*100)+"%";
    if(left>0)setTimeout(tick,1000);
    else{el.closest(".ov-card")&&(el.closest(".ov-card").style.display="none");}
  }
  tick();
})();
</script>';
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Return the active event, cached for this request.
     * @return array<string,mixed>|null
     */
    private function getActiveEvent(): ?array
    {
        if ($this->activeEventCache === false) {
            $this->activeEventCache = GalacticEventsDb::get()->getActiveEvent();
        }
        return $this->activeEventCache;
    }

    /**
     * Compute the multiplier for a given event.
     * effect_value is a percentage change, e.g.:
     *   +20  → 1.20  (bonus)
     *   -20  → 0.80  (malus)
     *   +100 → 2.00  (double)
     */
    private function effectMultiplier(array $event): float
    {
        $pct = (float)($event['effect_value'] ?? 0);
        return 1.0 + ($pct / 100.0);
    }

    /**
     * Render the ingame banner HTML for the sidebar_end hook.
     *
     * Uses a minimal Twig environment pointing at the plugin views directory.
     * The core template class registers plugin namespaces at construction time,
     * but hook callbacks run inside page-class methods where we cannot safely
     * borrow that instance. A lightweight dedicated env is the correct pattern
     * for rendering isolated HTML fragments from plugin templates.
     *
     * @param array<string,mixed> $event
     */
    private function renderBanner(array $event): string
    {
        $now   = defined('TIMESTAMP') ? TIMESTAMP : time();
        $until = (int)($event['active_until'] ?? 0);
        $secs  = max(0, $until - $now);
        $value = (float)($event['effect_value'] ?? 0);
        $sign  = $value >= 0 ? '+' : '';
        $name  = htmlspecialchars((string)($event['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type  = htmlspecialchars((string)($event['effect_type'] ?? ''), ENT_QUOTES, 'UTF-8');

        try {
            $ns = class_exists('PluginManager')
                ? PluginManager::get()->getTwigNamespaces()
                : [];

            if (!empty($ns['galactic_events']) && is_dir($ns['galactic_events'])
                && class_exists(\Twig\Environment::class)
            ) {
                $cacheDir = defined('ROOT_PATH') ? ROOT_PATH . 'cache/twig_plugins' : false;
                if ($cacheDir && !is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0775, true);
                }

                $loader = new \Twig\Loader\FilesystemLoader($ns['galactic_events']);
                $twig   = new \Twig\Environment($loader, [
                    'cache'       => $cacheDir ?: false,
                    'auto_reload' => true,
                ]);

                return $twig->render('game/event_banner.twig', [
                    'event'        => $event,
                    'name'         => $name,
                    'effect_type'  => $type,
                    'effect_value' => $value,
                    'effect_sign'  => $sign,
                    'seconds_left' => $secs,
                    'active_until' => $until,
                ]);
            }
        } catch (Throwable $e) {
            error_log('[GalacticEventsModule] renderBanner() Twig error: ' . $e->getMessage());
        }

        // Fallback: minimal inline HTML (no Twig dependency)
        $h = (int)floor($secs / 3600);
        $m = (int)floor(($secs % 3600) / 60);
        $s = $secs % 60;
        $countdown = sprintf('%02d:%02d:%02d', $h, $m, $s);

        return '<div class="ge-banner" data-until="' . $until . '" id="ge-sidebar-banner">'
            . '<div class="ge-banner__title">&#x1F30C; ' . $name . '</div>'
            . '<div class="ge-banner__effect">' . $sign . $value . '% ' . $type . '</div>'
            . '<div class="ge-banner__countdown" id="ge-countdown">' . $countdown . '</div>'
            . '</div>';
    }
}
