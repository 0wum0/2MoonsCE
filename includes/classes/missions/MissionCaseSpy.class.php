<?php

declare(strict_types=1);

/**
 *  SmartMoons / 2Moons Community Edition (2MoonsCE)
 *
 *  Based on the original 2Moons project:
 *
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
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
 * MissionCaseSpy – handles spy mission (fleet_mission = 6)
 *
 * FIXES vs previous version:
 *
 *  1. CRASH / EMPTY REPORT FIX: The ob_start() + $template->display() +
 *     ob_get_clean() pattern produces an EMPTY string when the template
 *     engine throws an exception or the template file does not exist.
 *     The message was then silently sent with empty body.  Fixed by:
 *       a) Wrapping the render in try/catch.
 *       b) Falling back to a plain-text / minimal HTML report if the
 *          template render fails (so the spy report is NEVER empty).
 *       c) Validating that $spyReport is non-empty before sending.
 *
 *  2. CRASH FIX: $senderUser['spy_tech'] and $targetUser['spy_tech']
 *     were used directly in max() without a fallback.  If the column is
 *     null (new account) this caused a type error in PHP 8.3 strict mode.
 *     Fixed with explicit (int) casts.
 *
 *  3. REGRESSION FIX: The spy probe destruction path updated der_metal /
 *     der_crystal using $config->Fleet_Cdr but $config was not initialised
 *     in TargetEvent().  Added Config::get() call.
 *
 *  4. CORRECTNESS: The "probe destroyed" debris update used
 *     $fleetAmount * pricelist[210]['cost'][901/902].  $fleetAmount was the
 *     float-adjusted effective spy count, not the actual ship count.  For
 *     debris purposes the ACTUAL ship count should be used.  Fixed by
 *     separating $fleetAmount (logic) from $actualShipCount (debris).
 *
 *  5. SAFETY: All $resource / $reslist accesses guarded with isset checks.
 */
class MissionCaseSpy extends MissionFunctions implements Mission
{
    // Do NOT redeclare $_fleet — parent has it without type hint.

    public function __construct(array $Fleet)
    {
        $this->_fleet = $Fleet;
    }

    public function TargetEvent()
    {
        global $pricelist, $reslist, $resource;

        $db = Database::get();

        // ── Load sender & target ──────────────────────────────────────────
        $sql        = 'SELECT * FROM %%USERS%% WHERE id = :userId;';
        $senderUser  = $db->selectSingle($sql, [':userId' => $this->_fleet['fleet_owner']]);
        $targetUser  = $db->selectSingle($sql, [':userId' => $this->_fleet['fleet_target_owner']]);

        $sql           = 'SELECT * FROM %%PLANETS%% WHERE id = :planetId;';
        $targetPlanet   = $db->selectSingle($sql, [':planetId' => $this->_fleet['fleet_end_id']]);

        $sql              = 'SELECT name FROM %%PLANETS%% WHERE id = :planetId;';
        $senderPlanetName  = $db->selectSingle($sql, [':planetId' => $this->_fleet['fleet_start_id']], 'name');

        $LNG = $this->getLanguage($senderUser['lang']);

        $senderUser['factor'] = getFactors($senderUser, 'basic', (int)$this->_fleet['fleet_start_time']);
        $targetUser['factor'] = getFactors($targetUser, 'basic', (int)$this->_fleet['fleet_start_time']);

        // ── Update target planet resources to current time ────────────────
        $planetUpdater = new ResourceUpdate();
        [$targetUser, $targetPlanet] = $planetUpdater->CalcResource(
            $targetUser,
            $targetPlanet,
            true,
            (int)$this->_fleet['fleet_start_time']
        );

        // ── Add fleet units in holding orbit to planet totals ─────────────
        $sql = 'SELECT * FROM %%FLEETS%%
                WHERE fleet_end_id    = :planetId
                AND   fleet_mission   = 5
                AND   fleet_end_stay  > :time;';

        $targetStayFleets = $db->select($sql, [
            ':planetId' => $this->_fleet['fleet_end_id'],
            ':time'     => $this->_fleet['fleet_start_time'],
        ]);

        foreach ($targetStayFleets as $fleetRow) {
            $fleetData = FleetFunctions::unserialize($fleetRow['fleet_array']);
            foreach ($fleetData as $shipId => $shipAmount) {
                if (isset($resource[$shipId])) {
                    $targetPlanet[$resource[$shipId]] = ($targetPlanet[$resource[$shipId]] ?? 0) + $shipAmount;
                }
            }
        }

        // ── Spy tech calculations ─────────────────────────────────────────
        // FIX: explicit int casts to avoid PHP 8.3 type error on null
        $actualShipCount = (float)$this->_fleet['fleet_amount'];
        $fleetAmount     = $actualShipCount * (1.0 + (float)($senderUser['factor']['SpyPower'] ?? 0.0));

        $senderSpyTech  = max(1, (int)($senderUser['spy_tech'] ?? 0));
        $targetSpyTech  = max(1, (int)($targetUser['spy_tech']  ?? 0));

        $techDifference = abs($senderSpyTech - $targetSpyTech);
        $MinAmount      = ($senderSpyTech > $targetSpyTech ? -1 : 1)
                        * pow($techDifference * SPY_DIFFENCE_FACTOR, 2);

        $SpyFleet  = $fleetAmount >= $MinAmount;
        $SpyDef    = $fleetAmount >= $MinAmount + 1 * SPY_VIEW_FACTOR;
        $SpyBuild  = $fleetAmount >= $MinAmount + 3 * SPY_VIEW_FACTOR;
        $SpyTechno = $fleetAmount >= $MinAmount + 5 * SPY_VIEW_FACTOR;

        // ── Assemble spy data ─────────────────────────────────────────────
        $classIDs        = [];
        $classIDs[900]   = array_merge(
            $reslist['resstype'][1] ?? [],
            $reslist['resstype'][2] ?? []
        );

        if ($SpyFleet) {
            $classIDs[200] = $reslist['fleet'] ?? [];
        }
        if ($SpyDef) {
            $classIDs[400] = array_merge(
                $reslist['defense'] ?? [],
                $reslist['missile'] ?? []
            );
        }
        if ($SpyBuild) {
            $classIDs[0] = $reslist['build'] ?? [];
        }
        if ($SpyTechno) {
            $classIDs[100] = $reslist['tech'] ?? [];
        }

        $targetChance = mt_rand(0, (int)min(
            ($fleetAmount / 4) * floor($targetSpyTech / $senderSpyTech),
            100
        ));
        $spyChance = mt_rand(0, 100);
        $spyData   = [];

        foreach ($classIDs as $classID => $elementIDs) {
            foreach ($elementIDs as $elementID) {
                if (!isset($resource[$elementID])) {
                    continue;
                }
                if (isset($targetUser[$resource[$elementID]])) {
                    $spyData[$classID][$elementID] = $targetUser[$resource[$elementID]];
                } else {
                    $spyData[$classID][$elementID] = $targetPlanet[$resource[$elementID]] ?? 0;
                }
            }

            if ((int)($senderUser['spyMessagesMode'] ?? 0) === 1) {
                $spyData[$classID] = array_filter($spyData[$classID] ?? []);
            }
        }

        // ── Render spy report via Twig template ───────────────────────────
        // FIX: wrapped in try/catch with plain-HTML fallback so the report
        //      is never silently empty.
        $spyReport = '';

        try {
            require_once 'includes/classes/class.template.php';
            $template = new template();
            $template->setTemplateDir('styles/templates/game');

            $template->assign_vars([
                'spyData'      => $spyData,
                'targetPlanet' => $targetPlanet,
                'targetChance' => $targetChance,
                'spyChance'    => $spyChance,
                'isBattleSim'  => (defined('ENABLE_SIMULATOR_LINK') && ENABLE_SIMULATOR_LINK == true
                                   && function_exists('isModuleAvailable')
                                   && isModuleAvailable(MODULE_SIMULATOR)),
                'title'        => sprintf(
                    $LNG['sys_mess_head'],
                    $targetPlanet['name'],
                    $targetPlanet['galaxy'],
                    $targetPlanet['system'],
                    $targetPlanet['planet'],
                    _date($LNG['php_tdformat'], (int)$this->_fleet['fleet_end_time'], $targetUser['timezone'], $LNG)
                ),
                'LNG' => $LNG,
            ]);

            ob_start();
            $template->display('shared.mission.spyReport.twig');
            $rendered = (string)ob_get_clean();

            if (trim($rendered) !== '') {
                $spyReport = $rendered;
            }
        } catch (Throwable $e) {
            // Template render failed – fall through to plain-text fallback
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // ── Plain-text fallback if template produced nothing ──────────────
        if ($spyReport === '') {
            $spyReport = $this->_buildPlainSpyReport(
                $spyData,
                $targetPlanet,
                $targetChance,
                $spyChance,
                $LNG
            );
        }

        // ── Send spy report to probe owner ────────────────────────────────
        PlayerUtil::sendMessage(
            (int)$this->_fleet['fleet_owner'],
            0,
            $LNG['sys_mess_qg'],
            0,
            $LNG['sys_mess_spy_report'],
            $spyReport,
            (int)$this->_fleet['fleet_start_time'],
            null,
            1,
            (int)$this->_fleet['fleet_universe']
        );

        // ── Send detection notification to target ─────────────────────────
        $LNG_target    = $this->getLanguage($targetUser['lang']);
        $targetMessage = $LNG_target['sys_mess_spy_ennemyfleet'] . ' ' . (string)$senderPlanetName;

        if ((int)$this->_fleet['fleet_start_type'] === 3) {
            $targetMessage .= $LNG_target['sys_mess_spy_report_moon'] . ' ';
        }

        $linkTemplate = '<a href="game.php?page=galaxy&amp;galaxy=%1$s&amp;system=%2$s">[%1$s:%2$s:%3$s]</a> %7$s'
                      . "\n        "
                      . '%8$s <a href="game.php?page=galaxy&amp;galaxy=%4$s&amp;system=%5$s">[%4$s:%5$s:%6$s]</a> %9$s';

        $targetMessage .= sprintf(
            $linkTemplate,
            $this->_fleet['fleet_start_galaxy'],
            $this->_fleet['fleet_start_system'],
            $this->_fleet['fleet_start_planet'],
            $this->_fleet['fleet_end_galaxy'],
            $this->_fleet['fleet_end_system'],
            $this->_fleet['fleet_end_planet'],
            $LNG_target['sys_mess_spy_seen_at'],
            $targetPlanet['name'],
            $LNG_target['sys_mess_spy_seen_at2']
        );

        PlayerUtil::sendMessage(
            (int)$this->_fleet['fleet_target_owner'],
            0,
            $LNG_target['sys_mess_spy_control'],
            0,
            $LNG_target['sys_mess_spy_activity'],
            $targetMessage,
            (int)$this->_fleet['fleet_start_time'],
            null,
            1,
            (int)$this->_fleet['fleet_universe']
        );

        // ── Probe destruction check ───────────────────────────────────────
        if ($targetChance >= $spyChance) {
            // FIX: $config was not initialised before – added here
            $config   = Config::get((int)$this->_fleet['fleet_universe']);
            $whereCol = ((int)$this->_fleet['fleet_end_type'] === 3) ? 'id_luna' : 'id';

            // FIX: use $actualShipCount (real ship count) for debris, not
            //      $fleetAmount which is the tech-adjusted spy effectiveness.
            $sql = 'UPDATE %%PLANETS%% SET
                    der_metal   = der_metal   + :metal,
                    der_crystal = der_crystal + :crystal
                    WHERE ' . $whereCol . ' = :planetId;';

            $db->update($sql, [
                ':metal'    => $actualShipCount * ($pricelist[210]['cost'][901] ?? 0) * $config->Fleet_Cdr / 100.0,
                ':crystal'  => $actualShipCount * ($pricelist[210]['cost'][902] ?? 0) * $config->Fleet_Cdr / 100.0,
                ':planetId' => $this->_fleet['fleet_end_id'],
            ]);

            $this->KillFleet();
        } else {
            $this->setState(FLEET_RETURN);
            $this->SaveFleet();
        }
    }

    public function EndStayEvent()
    {
        return;
    }

    public function ReturnEvent()
    {
        $this->RestoreFleet();
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Build a minimal HTML spy report when the Twig template fails.
     * This guarantees the player always receives something useful.
     */
    private function _buildPlainSpyReport(
        array  $spyData,
        array  $targetPlanet,
        int    $targetChance,
        int    $spyChance,
        array  $LNG
    ): string {
        global $resource;

        $lines = [];
        $lines[] = '<div class="spyReport spyReportFallback">';
        $lines[] = '<h3>' . htmlspecialchars((string)($targetPlanet['name'] ?? '')) . ' '
                 . '[' . (int)$targetPlanet['galaxy'] . ':'
                 . (int)$targetPlanet['system']        . ':'
                 . (int)$targetPlanet['planet']        . ']</h3>';

        foreach ($spyData as $classID => $elements) {
            if (empty($elements)) {
                continue;
            }
            $lines[] = '<ul>';
            foreach ($elements as $elementID => $value) {
                $label = isset($LNG['tech'][$elementID])
                    ? htmlspecialchars((string)$LNG['tech'][$elementID])
                    : 'ID ' . (int)$elementID;
                $lines[] = '<li>' . $label . ': ' . pretty_number((float)$value) . '</li>';
            }
            $lines[] = '</ul>';
        }

        // Detection probability hint
        if ($targetChance > 0) {
            $lines[] = '<p><small>'
                     . sprintf($LNG['sys_spy_chance'] ?? 'Detection chance: %d%%', $targetChance)
                     . '</small></p>';
        }

        $lines[] = '</div>';

        return implode("\n", $lines);
    }
}
