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

class AdminStatsService
{
    private static ?AdminStatsService $instance = null;
    private int $universe;

    private function __construct(int $universe)
    {
        $this->universe = $universe;
    }

    public static function getInstance(int $universe = 0): self
    {
        if ($universe === 0) {
            $universe = Universe::getEmulated();
        }
        if (self::$instance === null || self::$instance->universe !== $universe) {
            self::$instance = new self($universe);
        }
        return self::$instance;
    }

    /**
     * Berechnet Zeitstempel für den gewünschten Zeitraum
     */
    private function getPeriodTimestamp(string $period): int
    {
        return match ($period) {
            'day'   => TIMESTAMP - 86400,
            'week'  => TIMESTAMP - 604800,
            'month' => TIMESTAMP - 2592000,
            'year'  => TIMESTAMP - 31536000,
            default => TIMESTAMP - 86400,
        };
    }

    /**
     * Extract a COUNT(*) result from a selectSingle row.
     * COUNT(*) always returns a row — a missing 'cnt' key is a programming error.
     */
    private function extractCount(array $row, string $context): int
    {
        if (!isset($row['cnt'])) {
            error_log('[AdminStatsService] Missing cnt key in result (' . $context . ')');
        }
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Spieler online: aktuell online / gesamt
     */
    public function getPlayersOnline(): array
    {
        $db = Database::get();

        // Online = Aktivität in den letzten 15 Minuten
        $onlineThreshold = TIMESTAMP - 900;

        $total = $db->selectSingle(
            "SELECT COUNT(*) as cnt FROM %%USERS%% WHERE universe = :uni;",
            [':uni' => $this->universe]
        );

        $online = $db->selectSingle(
            "SELECT COUNT(*) as cnt FROM %%USERS%% WHERE universe = :uni AND onlinetime > :threshold;",
            [':uni' => $this->universe, ':threshold' => $onlineThreshold]
        );

        return [
            'online' => $this->extractCount($online, 'getPlayersOnline/online'),
            'total'  => $this->extractCount($total,  'getPlayersOnline/total'),
        ];
    }

    /**
     * Registrierungen im Zeitraum
     */
    public function getRegistrations(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $result = $db->selectSingle(
            "SELECT COUNT(*) as cnt FROM %%USERS%% WHERE universe = :uni AND register_time > :since;",
            [':uni' => $this->universe, ':since' => $since]
        );

        return [
            'count'  => $this->extractCount($result, 'getRegistrations'),
            'period' => $period,
        ];
    }

    /**
     * Flotten verschickt im Zeitraum.
     * Tries %%LOG_FLEETS%% first (optional table); falls back to active %%FLEETS%%.
     * Returns -1 when neither table is available (sentinel: data not available).
     */
    public function getFleetsSent(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        // Primary: fleet log table (may not exist in all installations)
        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%LOG_FLEETS%% WHERE fleet_universe = :uni AND fleet_start_time > :since;",
                [':uni' => $this->universe, ':since' => $since]
            );
            $count = $this->extractCount($result, 'getFleetsSent/log_fleets');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getFleetsSent: log_fleets unavailable, trying fleets table. ' . $e->getMessage());

            // Fallback: active fleet table
            try {
                $result = $db->selectSingle(
                    "SELECT COUNT(*) as cnt FROM %%FLEETS%% WHERE fleet_universe = :uni AND start_time > :since;",
                    [':uni' => $this->universe, ':since' => $since]
                );
                $count = $this->extractCount($result, 'getFleetsSent/fleets_fallback');
            } catch (\Throwable $e2) {
                error_log('[AdminStatsService] getFleetsSent: fleets fallback also failed. ' . $e2->getMessage());
                $count = -1; // sentinel: data not available
            }
        }

        return [
            'count'  => $count,
            'period' => $period,
        ];
    }

    /**
     * Gegründete Allianzen im Zeitraum.
     * Returns -1 when the alliance table is not available (sentinel: data not available).
     */
    public function getAlliancesFounded(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%ALLIANCE%% WHERE ally_universe = :uni AND ally_register_time > :since;",
                [':uni' => $this->universe, ':since' => $since]
            );
            $count = $this->extractCount($result, 'getAlliancesFounded');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getAlliancesFounded: table unavailable. ' . $e->getMessage());
            $count = -1; // sentinel: data not available
        }

        return [
            'count'  => $count,
            'period' => $period,
        ];
    }

    /**
     * Kämpfe/Kampfberichte im Zeitraum.
     * Tries %%TOPKB%% first; falls back to %%RW%%.
     * Returns -1 when neither table is available (sentinel: data not available).
     */
    public function getCombats(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%TOPKB%% WHERE universe = :uni AND `time` > :since;",
                [':uni' => $this->universe, ':since' => $since]
            );
            $count = $this->extractCount($result, 'getCombats/topkb');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getCombats: topkb unavailable, trying rw fallback. ' . $e->getMessage());

            // Fallback: battle reports table
            try {
                $result = $db->selectSingle(
                    "SELECT COUNT(*) as cnt FROM %%RW%% WHERE 1;",
                    []
                );
                $count = $this->extractCount($result, 'getCombats/rw_fallback');
            } catch (\Throwable $e2) {
                error_log('[AdminStatsService] getCombats: rw fallback also failed. ' . $e2->getMessage());
                $count = -1; // sentinel: data not available
            }
        }

        return [
            'count'  => $count,
            'period' => $period,
        ];
    }

    /**
     * Nachrichten im Zeitraum.
     * Tries with universe filter first; falls back without it.
     * Returns -1 when the messages table is not available (sentinel: data not available).
     */
    public function getMessages(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%MESSAGES%% WHERE message_time > :since AND message_universe = :uni;",
                [':since' => $since, ':uni' => $this->universe]
            );
            $count = $this->extractCount($result, 'getMessages/with_universe');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getMessages: universe-scoped query failed, trying fallback. ' . $e->getMessage());

            // Fallback: without universe filter (older schema may lack the column)
            try {
                $result = $db->selectSingle(
                    "SELECT COUNT(*) as cnt FROM %%MESSAGES%% WHERE message_time > :since;",
                    [':since' => $since]
                );
                $count = $this->extractCount($result, 'getMessages/no_universe_fallback');
            } catch (\Throwable $e2) {
                error_log('[AdminStatsService] getMessages: fallback also failed. ' . $e2->getMessage());
                $count = -1; // sentinel: data not available
            }
        }

        return [
            'count'  => $count,
            'period' => $period,
        ];
    }

    /**
     * Multiaccounts / Verdachts-IPs.
     * Returns -1 for both values when the query fails (sentinel: data not available).
     */
    public function getMultiaccountFlags(string $period): array
    {
        $db = Database::get();

        try {
            // Count IPs shared by more than one user account
            $result = $db->select(
                "SELECT user_lastip, COUNT(*) as cnt FROM %%USERS%% WHERE universe = :uni GROUP BY user_lastip HAVING COUNT(*) > 1;",
                [':uni' => $this->universe]
            );
            $flaggedIps   = count($result);
            $flaggedUsers = 0;
            foreach ($result as $row) {
                if (!isset($row['cnt'])) {
                    error_log('[AdminStatsService] getMultiaccountFlags: missing cnt in row');
                }
                $flaggedUsers += (int) ($row['cnt'] ?? 0);
            }
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getMultiaccountFlags: query failed. ' . $e->getMessage());
            $flaggedIps   = -1; // sentinel: data not available
            $flaggedUsers = -1; // sentinel: data not available
        }

        return [
            'flagged_ips'   => $flaggedIps,
            'flagged_users' => $flaggedUsers,
            'period'        => $period,
        ];
    }

    /**
     * Top 5 Spieler nach Punkten.
     * Returns empty array on failure (table must exist; logs the error).
     */
    public function getTopPlayers(int $limit = 5): array
    {
        $db = Database::get();

        try {
            $result = $db->select(
                "SELECT u.id, u.username, COALESCE(s.total_points, 0) as points, COALESCE(s.total_rank, 0) as `rank`
                 FROM %%USERS%% u
                 LEFT JOIN %%STATPOINTS%% s ON s.id_owner = u.id AND s.stat_type = 1
                 WHERE u.universe = :uni
                 ORDER BY points DESC
                 LIMIT " . (int) $limit . ";",
                [':uni' => $this->universe]
            );
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getTopPlayers: query failed. ' . $e->getMessage());
            $result = [];
        }

        return $result;
    }

    /**
     * Aktivste Spieler (nach letztem Login).
     * Returns empty array on failure (table must exist; logs the error).
     */
    public function getMostActivePlayers(int $limit = 5): array
    {
        $db = Database::get();

        try {
            $result = $db->select(
                "SELECT id, username, onlinetime
                 FROM %%USERS%%
                 WHERE universe = :uni
                 ORDER BY onlinetime DESC
                 LIMIT " . (int) $limit . ";",
                [':uni' => $this->universe]
            );
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getMostActivePlayers: query failed. ' . $e->getMessage());
            $result = [];
        }

        return $result;
    }

    /**
     * Gesperrte Spieler.
     * Returns -1 when the query fails (sentinel: data not available).
     */
    public function getBannedPlayers(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%USERS%% WHERE universe = :uni AND bana = 1;",
                [':uni' => $this->universe]
            );
            $count = $this->extractCount($result, 'getBannedPlayers');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getBannedPlayers: query failed. ' . $e->getMessage());
            $count = -1; // sentinel: data not available
        }

        return ['count' => $count];
    }

    /**
     * Support Tickets offen.
     * Returns -1 when the tickets table is not available (sentinel: data not available).
     */
    public function getOpenTickets(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%TICKETS%% WHERE universe = :uni AND status = 0;",
                [':uni' => $this->universe]
            );
            $count = $this->extractCount($result, 'getOpenTickets');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getOpenTickets: table unavailable. ' . $e->getMessage());
            $count = -1; // sentinel: data not available
        }

        return ['count' => $count];
    }

    /**
     * Fliegende Flotten aktuell.
     * Returns -1 when the query fails (sentinel: data not available).
     */
    public function getFlyingFleets(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%FLEETS%% WHERE fleet_universe = :uni;",
                [':uni' => $this->universe]
            );
            $count = $this->extractCount($result, 'getFlyingFleets');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getFlyingFleets: query failed. ' . $e->getMessage());
            $count = -1; // sentinel: data not available
        }

        return ['count' => $count];
    }

    /**
     * Planeten Gesamt.
     * Returns -1 when the query fails (sentinel: data not available).
     */
    public function getPlanetsTotal(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%PLANETS%% WHERE universe = :uni;",
                [':uni' => $this->universe]
            );
            $count = $this->extractCount($result, 'getPlanetsTotal');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getPlanetsTotal: query failed. ' . $e->getMessage());
            $count = -1; // sentinel: data not available
        }

        return ['count' => $count];
    }

    /**
     * Allianzen Gesamt.
     * Returns -1 when the alliance table is not available (sentinel: data not available).
     */
    public function getAlliancesTotal(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%ALLIANCE%% WHERE ally_universe = :uni;",
                [':uni' => $this->universe]
            );
            $count = $this->extractCount($result, 'getAlliancesTotal');
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getAlliancesTotal: table unavailable. ' . $e->getMessage());
            $count = -1; // sentinel: data not available
        }

        return ['count' => $count];
    }

    // ===== CHART DATA METHODS =====

    /**
     * Aktivitätsverlauf (Registrierungen als Proxy für Zeitreihe).
     * Returns empty chart data on failure (logs the error).
     */
    public function getActivityTimeline(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $groupBy = match ($period) {
            'day'   => "FROM_UNIXTIME(onlinetime, '%H')",
            'week'  => "FROM_UNIXTIME(onlinetime, '%Y-%m-%d')",
            'month' => "FROM_UNIXTIME(onlinetime, '%Y-%m-%d')",
            'year'  => "FROM_UNIXTIME(onlinetime, '%Y-%m')",
            default => "FROM_UNIXTIME(onlinetime, '%H')",
        };

        try {
            $result = $db->select(
                "SELECT {$groupBy} as label, COUNT(*) as value
                 FROM %%USERS%%
                 WHERE universe = :uni AND onlinetime > :since
                 GROUP BY label
                 ORDER BY label ASC;",
                [':uni' => $this->universe, ':since' => $since]
            );
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getActivityTimeline: query failed. ' . $e->getMessage());
            $result = [];
        }

        return $this->formatChartData($result);
    }

    /**
     * Registrierungen Zeitreihe.
     * Returns empty chart data on failure (logs the error).
     */
    public function getRegistrationTimeline(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $groupBy = match ($period) {
            'day'   => "FROM_UNIXTIME(register_time, '%H')",
            'week'  => "FROM_UNIXTIME(register_time, '%Y-%m-%d')",
            'month' => "FROM_UNIXTIME(register_time, '%Y-%m-%d')",
            'year'  => "FROM_UNIXTIME(register_time, '%Y-%m')",
            default => "FROM_UNIXTIME(register_time, '%H')",
        };

        try {
            $result = $db->select(
                "SELECT {$groupBy} as label, COUNT(*) as value
                 FROM %%USERS%%
                 WHERE universe = :uni AND register_time > :since
                 GROUP BY label
                 ORDER BY label ASC;",
                [':uni' => $this->universe, ':since' => $since]
            );
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getRegistrationTimeline: query failed. ' . $e->getMessage());
            $result = [];
        }

        return $this->formatChartData($result);
    }

    /**
     * Flottenstarts Zeitreihe.
     * Tries %%LOG_FLEETS%% first; falls back to %%FLEETS%%.
     * Returns empty chart data when neither table is available (logs the error).
     */
    public function getFleetTimeline(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $groupBy = match ($period) {
            'day'   => "FROM_UNIXTIME(fleet_start_time, '%H')",
            'week'  => "FROM_UNIXTIME(fleet_start_time, '%Y-%m-%d')",
            'month' => "FROM_UNIXTIME(fleet_start_time, '%Y-%m-%d')",
            'year'  => "FROM_UNIXTIME(fleet_start_time, '%Y-%m')",
            default => "FROM_UNIXTIME(fleet_start_time, '%H')",
        };

        // Primary: fleet log table (may not exist in all installations)
        try {
            $result = $db->select(
                "SELECT {$groupBy} as label, COUNT(*) as value
                 FROM %%LOG_FLEETS%%
                 WHERE fleet_universe = :uni AND fleet_start_time > :since
                 GROUP BY label
                 ORDER BY label ASC;",
                [':uni' => $this->universe, ':since' => $since]
            );
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getFleetTimeline: log_fleets unavailable, trying fleets fallback. ' . $e->getMessage());

            try {
                $result = $db->select(
                    "SELECT {$groupBy} as label, COUNT(*) as value
                     FROM %%FLEETS%%
                     WHERE fleet_universe = :uni AND start_time > :since
                     GROUP BY label
                     ORDER BY label ASC;",
                    [':uni' => $this->universe, ':since' => $since]
                );
            } catch (\Throwable $e2) {
                error_log('[AdminStatsService] getFleetTimeline: fleets fallback also failed. ' . $e2->getMessage());
                $result = [];
            }
        }

        return $this->formatChartData($result);
    }

    /**
     * Kämpfe Zeitreihe.
     * Returns empty chart data on failure (logs the error).
     */
    public function getCombatTimeline(string $period): array
    {
        $db    = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $groupBy = match ($period) {
            'day'   => "FROM_UNIXTIME(`time`, '%H')",
            'week'  => "FROM_UNIXTIME(`time`, '%Y-%m-%d')",
            'month' => "FROM_UNIXTIME(`time`, '%Y-%m-%d')",
            'year'  => "FROM_UNIXTIME(`time`, '%Y-%m')",
            default => "FROM_UNIXTIME(`time`, '%H')",
        };

        try {
            $result = $db->select(
                "SELECT {$groupBy} as label, COUNT(*) as value
                 FROM %%TOPKB%%
                 WHERE universe = :uni AND `time` > :since
                 GROUP BY label
                 ORDER BY label ASC;",
                [':uni' => $this->universe, ':since' => $since]
            );
        } catch (\Throwable $e) {
            error_log('[AdminStatsService] getCombatTimeline: query failed. ' . $e->getMessage());
            $result = [];
        }

        return $this->formatChartData($result);
    }

    /**
     * Format chart data for JavaScript consumption.
     */
    private function formatChartData(array $rows): array
    {
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            if (!isset($row['label'], $row['value'])) {
                error_log('[AdminStatsService] formatChartData: row missing label or value key');
            }
            $labels[] = (string) ($row['label'] ?? '');
            $values[] = (int) ($row['value'] ?? 0);
        }
        return ['labels' => $labels, 'values' => $values];
    }

    // ===== REPORT / BILANZ =====

    /**
     * Kompletter Report für einen Zeitraum
     */
    public function getFullReport(string $period): array
    {
        $players = $this->getPlayersOnline();
        $registrations = $this->getRegistrations($period);
        $fleets = $this->getFleetsSent($period);
        $alliances = $this->getAlliancesFounded($period);
        $combats = $this->getCombats($period);
        $messages = $this->getMessages($period);
        $multi = $this->getMultiaccountFlags($period);
        $topPlayers = $this->getTopPlayers(5);
        $activePlayers = $this->getMostActivePlayers(5);
        $banned = $this->getBannedPlayers();
        $tickets = $this->getOpenTickets();
        $flyingFleets = $this->getFlyingFleets();
        $planets = $this->getPlanetsTotal();
        $alliancesTotal = $this->getAlliancesTotal();

        return [
            'players_online' => $players,
            'registrations' => $registrations,
            'fleets_sent' => $fleets,
            'alliances_founded' => $alliances,
            'combats' => $combats,
            'messages' => $messages,
            'multiaccount_flags' => $multi,
            'top_players' => $topPlayers,
            'active_players' => $activePlayers,
            'banned_players' => $banned,
            'open_tickets' => $tickets,
            'flying_fleets' => $flyingFleets,
            'planets_total' => $planets,
            'alliances_total' => $alliancesTotal,
            'period' => $period,
        ];
    }

    /**
     * Alle Chart-Daten für einen Zeitraum
     */
    public function getFullChartData(string $period): array
    {
        return [
            'activity' => $this->getActivityTimeline($period),
            'registrations' => $this->getRegistrationTimeline($period),
            'fleets' => $this->getFleetTimeline($period),
            'combats' => $this->getCombatTimeline($period),
        ];
    }
}
