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
            'day' => TIMESTAMP - 86400,
            'week' => TIMESTAMP - 604800,
            'month' => TIMESTAMP - 2592000,
            'year' => TIMESTAMP - 31536000,
            default => TIMESTAMP - 86400,
        };
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
            'online' => (int)($online['cnt'] ?? 0),
            'total' => (int)($total['cnt'] ?? 0),
        ];
    }

    /**
     * Registrierungen im Zeitraum
     */
    public function getRegistrations(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $result = $db->selectSingle(
            "SELECT COUNT(*) as cnt FROM %%USERS%% WHERE universe = :uni AND register_time > :since;",
            [':uni' => $this->universe, ':since' => $since]
        );

        return [
            'count' => (int)($result['cnt'] ?? 0),
            'period' => $period,
        ];
    }

    /**
     * Flotten verschickt im Zeitraum (aus log_fleets oder fleets)
     */
    public function getFleetsSent(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        // Versuche log_fleets Tabelle
        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%LOG_FLEETS%% WHERE fleet_universe = :uni AND fleet_start_time > :since;",
                [':uni' => $this->universe, ':since' => $since]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Fallback: aktive Flotten
            try {
                $result = $db->selectSingle(
                    "SELECT COUNT(*) as cnt FROM %%FLEETS%% WHERE fleet_universe = :uni AND start_time > :since;",
                    [':uni' => $this->universe, ':since' => $since]
                );
                $count = (int)($result['cnt'] ?? 0);
            } catch (\Exception $e2) {
                $count = -1; // n/a
            }
        }

        return [
            'count' => $count,
            'period' => $period,
        ];
    }

    /**
     * Gegründete Allianzen im Zeitraum
     */
    public function getAlliancesFounded(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%ALLIANCE%% WHERE ally_universe = :uni AND ally_register_time > :since;",
                [':uni' => $this->universe, ':since' => $since]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            $count = -1; // n/a
        }

        return [
            'count' => $count,
            'period' => $period,
        ];
    }

    /**
     * Kämpfe/Kampfberichte im Zeitraum
     */
    public function getCombats(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%TOPKB%% WHERE universe = :uni AND `time` > :since;",
                [':uni' => $this->universe, ':since' => $since]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Fallback: Kampfberichte (raports)
            try {
                $result = $db->selectSingle(
                    "SELECT COUNT(*) as cnt FROM %%RW%% WHERE 1;",
                    []
                );
                $count = (int)($result['cnt'] ?? 0);
            } catch (\Exception $e2) {
                $count = -1; // n/a
            }
        }

        return [
            'count' => $count,
            'period' => $period,
        ];
    }

    /**
     * Nachrichten im Zeitraum
     */
    public function getMessages(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%MESSAGES%% WHERE message_time > :since AND message_universe = :uni;",
                [':since' => $since, ':uni' => $this->universe]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Fallback ohne Universe Filter
            try {
                $result = $db->selectSingle(
                    "SELECT COUNT(*) as cnt FROM %%MESSAGES%% WHERE message_time > :since;",
                    [':since' => $since]
                );
                $count = (int)($result['cnt'] ?? 0);
            } catch (\Exception $e2) {
                $count = -1;
            }
        }

        return [
            'count' => $count,
            'period' => $period,
        ];
    }

    /**
     * Multiaccounts / Verdachts-IPs
     */
    public function getMultiaccountFlags(string $period): array
    {
        $db = Database::get();

        try {
            // Zähle IPs die von mehreren Usern genutzt werden
            $result = $db->select(
                "SELECT user_lastip, COUNT(*) as cnt FROM %%USERS%% WHERE universe = :uni GROUP BY user_lastip HAVING COUNT(*) > 1;",
                [':uni' => $this->universe]
            );
            $flaggedIps = count($result);
            $flaggedUsers = 0;
            foreach ($result as $row) {
                $flaggedUsers += (int)$row['cnt'];
            }
        } catch (\Exception $e) {
            $flaggedIps = -1;
            $flaggedUsers = -1;
        }

        return [
            'flagged_ips' => $flaggedIps,
            'flagged_users' => $flaggedUsers,
            'period' => $period,
        ];
    }

    /**
     * Top 5 Spieler nach Punkten
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
                 LIMIT " . (int)$limit . ";",
                [':uni' => $this->universe]
            );
        } catch (\Exception $e) {
            $result = [];
        }

        return $result;
    }

    /**
     * Aktivste Spieler (nach letztem Login)
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
                 LIMIT " . (int)$limit . ";",
                [':uni' => $this->universe]
            );
        } catch (\Exception $e) {
            $result = [];
        }

        return $result;
    }

    /**
     * Gesperrte Spieler
     */
    public function getBannedPlayers(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%USERS%% WHERE universe = :uni AND bana = 1;",
                [':uni' => $this->universe]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            $count = -1;
        }

        return ['count' => $count];
    }

    /**
     * Support Tickets offen
     */
    public function getOpenTickets(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%TICKETS%% WHERE universe = :uni AND status = 0;",
                [':uni' => $this->universe]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            $count = -1;
        }

        return ['count' => $count];
    }

    /**
     * Fliegende Flotten aktuell
     */
    public function getFlyingFleets(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%FLEETS%% WHERE fleet_universe = :uni;",
                [':uni' => $this->universe]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            $count = -1;
        }

        return ['count' => $count];
    }

    /**
     * Planeten Gesamt
     */
    public function getPlanetsTotal(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%PLANETS%% WHERE universe = :uni;",
                [':uni' => $this->universe]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            $count = -1;
        }

        return ['count' => $count];
    }

    /**
     * Allianzen Gesamt
     */
    public function getAlliancesTotal(): array
    {
        $db = Database::get();

        try {
            $result = $db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%ALLIANCE%% WHERE ally_universe = :uni;",
                [':uni' => $this->universe]
            );
            $count = (int)($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            $count = -1;
        }

        return ['count' => $count];
    }

    // ===== CHART DATA METHODS =====

    /**
     * Aktivitätsverlauf (Registrierungen als Proxy für Zeitreihe)
     */
    public function getActivityTimeline(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $groupBy = match ($period) {
            'day' => "FROM_UNIXTIME(onlinetime, '%H')",
            'week' => "FROM_UNIXTIME(onlinetime, '%Y-%m-%d')",
            'month' => "FROM_UNIXTIME(onlinetime, '%Y-%m-%d')",
            'year' => "FROM_UNIXTIME(onlinetime, '%Y-%m')",
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
        } catch (\Exception $e) {
            $result = [];
        }

        return $this->formatChartData($result);
    }

    /**
     * Registrierungen Zeitreihe
     */
    public function getRegistrationTimeline(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $groupBy = match ($period) {
            'day' => "FROM_UNIXTIME(register_time, '%H')",
            'week' => "FROM_UNIXTIME(register_time, '%Y-%m-%d')",
            'month' => "FROM_UNIXTIME(register_time, '%Y-%m-%d')",
            'year' => "FROM_UNIXTIME(register_time, '%Y-%m')",
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
        } catch (\Exception $e) {
            $result = [];
        }

        return $this->formatChartData($result);
    }

    /**
     * Flottenstarts Zeitreihe
     */
    public function getFleetTimeline(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $groupBy = match ($period) {
            'day' => "FROM_UNIXTIME(fleet_start_time, '%H')",
            'week' => "FROM_UNIXTIME(fleet_start_time, '%Y-%m-%d')",
            'month' => "FROM_UNIXTIME(fleet_start_time, '%Y-%m-%d')",
            'year' => "FROM_UNIXTIME(fleet_start_time, '%Y-%m')",
            default => "FROM_UNIXTIME(fleet_start_time, '%H')",
        };

        // Versuche log_fleets zuerst
        try {
            $result = $db->select(
                "SELECT {$groupBy} as label, COUNT(*) as value
                 FROM %%LOG_FLEETS%%
                 WHERE fleet_universe = :uni AND fleet_start_time > :since
                 GROUP BY label
                 ORDER BY label ASC;",
                [':uni' => $this->universe, ':since' => $since]
            );
        } catch (\Exception $e) {
            try {
                $result = $db->select(
                    "SELECT {$groupBy} as label, COUNT(*) as value
                     FROM %%FLEETS%%
                     WHERE fleet_universe = :uni AND start_time > :since
                     GROUP BY label
                     ORDER BY label ASC;",
                    [':uni' => $this->universe, ':since' => $since]
                );
            } catch (\Exception $e2) {
                $result = [];
            }
        }

        return $this->formatChartData($result);
    }

    /**
     * Kämpfe Zeitreihe
     */
    public function getCombatTimeline(string $period): array
    {
        $db = Database::get();
        $since = $this->getPeriodTimestamp($period);

        $groupBy = match ($period) {
            'day' => "FROM_UNIXTIME(`time`, '%H')",
            'week' => "FROM_UNIXTIME(`time`, '%Y-%m-%d')",
            'month' => "FROM_UNIXTIME(`time`, '%Y-%m-%d')",
            'year' => "FROM_UNIXTIME(`time`, '%Y-%m')",
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
        } catch (\Exception $e) {
            $result = [];
        }

        return $this->formatChartData($result);
    }

    /**
     * Format chart data for JavaScript
     */
    private function formatChartData(array $rows): array
    {
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = $row['label'] ?? '';
            $values[] = (int)($row['value'] ?? 0);
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
