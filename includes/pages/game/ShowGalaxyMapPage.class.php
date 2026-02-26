<?php

declare(strict_types=1);

/**
 * SmartMoons Galaxy Map Page Controller
 *
 * Routes:
 *   game.php?page=galaxyMap              → show()   – renders the 3D map page
 *   game.php?page=galaxyMap&mode=fleets  → fleets() – JSON: active fleet movements
 *   game.php?page=galaxyMap&mode=galaxy  → galaxy() – JSON: all planets in universe
 *   game.php?page=galaxyMap&mode=card    → card()   – JSON: player card data
 */

require_once 'includes/classes/class.GalaxyRows.php';

class ShowGalaxyMapPage extends AbstractGamePage
{
    public static $requireModule = 0;

    public static $defaultController = 'show';

    private const MISSION_NAMES = [
        1  => 'ATTACK',
        2  => 'ACS_ATTACK',
        3  => 'TRANSPORT',
        4  => 'DEPLOY',
        5  => 'HOLD',
        6  => 'ESPIONAGE',
        7  => 'COLONY',
        8  => 'RECYCLE',
        9  => 'DESTROY',
        10 => 'MISSILE',
        11 => 'EXPEDITION',
        15 => 'EXPEDITION',
    ];

    public function __construct()
    {
        $mode = HTTP::_GP('mode', 'show');
        if (in_array($mode, ['fleets', 'galaxy', 'card'], true)) {
            // JSON API modes: skip eco resource calc, go directly to ajax window
            $this->setWindow('ajax');
        } else {
            parent::__construct();
        }
    }

    // ── Page render ──────────────────────────────────────────────
    public function show(): void
    {
        global $USER, $PLANET;

        $this->assign([
            'USER'   => $USER,
            'PLANET' => $PLANET,
        ]);

        $this->display('page.galaxyMap.default.twig');
    }

    // ── JSON: fleet movements ────────────────────────────────────
    public function fleets(): void
    {
        global $USER;

        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');

        if (empty($USER['id'])) {
            echo json_encode(['error' => 'not authenticated', 'fleets' => [], 'server_time' => time(), 'count' => 0]);
            exit;
        }

        $db  = Database::get();
        $now = TIMESTAMP;

        $sql = 'SELECT
            f.fleet_id,
            f.fleet_owner,
            f.fleet_target_owner,
            f.fleet_mission,
            f.fleet_start_galaxy,
            f.fleet_start_system,
            f.fleet_start_planet,
            f.fleet_end_galaxy,
            f.fleet_end_system,
            f.fleet_end_planet,
            f.fleet_start_time,
            f.fleet_end_time,
            f.fleet_mess,
            own.username  AS owner_name,
            own.ally_id   AS owner_ally_id
        FROM %%FLEETS%% f
        LEFT JOIN %%USERS%% own ON own.id = f.fleet_owner
        WHERE f.fleet_start_time <= :now
          AND f.fleet_end_time   >  :now
          AND f.fleet_mission    <> 10
        ORDER BY f.fleet_start_time ASC
        LIMIT 500;';

        $rows = $db->select($sql, [':now' => $now]);

        $fleets = [];
        foreach ($rows as $r) {
            $startTime = (int) $r['fleet_start_time'];
            $endTime   = (int) $r['fleet_end_time'];
            $duration  = max(1, $endTime - $startTime);
            $elapsed   = max(0, $now - $startTime);
            $progress  = min(1.0, $elapsed / $duration);

            $isOwn     = ((int) $r['fleet_owner'] === (int) $USER['id']);
            $isHostile = (!$isOwn
                && (int) $r['fleet_target_owner'] === (int) $USER['id']
                && in_array((int) $r['fleet_mission'], [1, 2, 6, 9, 10], true));
            $isAlly    = (!$isOwn && !$isHostile
                && (int) ($r['owner_ally_id'] ?? 0) > 0
                && (int) ($r['owner_ally_id'] ?? 0) === (int) ($USER['ally_id'] ?? 0));

            if ($isOwn) {
                $color = '#00d4ff';
            } elseif ($isHostile) {
                $color = '#e8304a';
            } elseif ($isAlly) {
                $color = '#a855f7';
            } elseif ((int) $r['fleet_mission'] === 6) {
                $color = '#ffee00';
            } elseif ((int) $r['fleet_mission'] === 8) {
                $color = '#ff9900';
            } else {
                $color = '#6688aa';
            }

            $missionName = self::MISSION_NAMES[(int) $r['fleet_mission']] ?? 'UNKNOWN';
            // Show owner name for own/hostile/ally fleets; hide for foreign
            $ownerDisplay = ($isOwn || $isHostile || $isAlly)
                ? ($r['owner_name'] ?? '?')
                : '???';

            $fleets[] = [
                'id'           => (int) $r['fleet_id'],
                'owner_id'     => (int) $r['fleet_owner'],
                'owner_name'   => $ownerDisplay,
                'mission'      => (int) $r['fleet_mission'],
                'mission_name' => $missionName,
                'start'        => [
                    'g' => (int) $r['fleet_start_galaxy'],
                    's' => (int) $r['fleet_start_system'],
                    'p' => (int) $r['fleet_start_planet'],
                ],
                'end'          => [
                    'g' => (int) $r['fleet_end_galaxy'],
                    's' => (int) $r['fleet_end_system'],
                    'p' => (int) $r['fleet_end_planet'],
                ],
                'start_time'   => $startTime,
                'end_time'     => $endTime,
                'progress'     => round($progress, 6),
                'remaining'    => max(0, $endTime - $now),
                'is_own'       => $isOwn,
                'is_hostile'   => $isHostile,
                'is_ally'      => $isAlly,
                'color'        => $color,
            ];
        }

        echo json_encode([
            'fleets'       => $fleets,
            'server_time'  => $now,
            'count'        => count($fleets),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // ── JSON: all planets ────────────────────────────────────────
    public function galaxy(): void
    {
        global $USER;

        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');

        if (empty($USER['id'])) {
            echo json_encode(['error' => 'not authenticated', 'planets' => [], 'server_time' => time(), 'count' => 0]);
            exit;
        }

        $db = Database::get();

        $sql = 'SELECT
            p.id, p.galaxy, p.system, p.planet,
            p.name, p.image, p.der_metal, p.der_crystal, p.id_luna,
            p.destruyed,
            u.id          AS user_id,
            u.username,
            u.ally_id,
            u.onlinetime,
            a.ally_tag,
            a.ally_name,
            s.total_rank,
            s.total_points
        FROM %%PLANETS%% p
        LEFT JOIN %%USERS%%     u ON u.id = p.id_owner
        LEFT JOIN %%ALLIANCE%%  a ON a.id = u.ally_id
        LEFT JOIN %%STATPOINTS%% s ON s.id_owner = u.id AND s.stat_type = 1
        WHERE p.planet_type = 1
          AND p.destruyed   = 0
          AND u.id IS NOT NULL
        ORDER BY p.galaxy, p.system, p.planet
        LIMIT 50000;';

        $rows = $db->select($sql, []);

        $planets     = [];
        $allyColors  = [];

        foreach ($rows as $r) {
            $allyId = (int) ($r['ally_id'] ?? 0);

            if ($allyId > 0 && !isset($allyColors[$allyId])) {
                srand($allyId * 2654435761);
                $allyColors[$allyId] = sprintf(
                    '#%02x%02x%02x',
                    (int) (130 + rand(0, 105)),
                    (int) (80  + rand(0, 80)),
                    (int) (180 + rand(0, 60))
                );
                srand();
            }

            $userId   = (int) ($r['user_id'] ?? 0);
            $isOwn    = ($userId === (int) $USER['id']);
            $isAlly   = (!$isOwn && $allyId > 0 && $allyId === (int) $USER['ally_id']);
            $hasDebris = ((float) ($r['der_metal'] ?? 0) + (float) ($r['der_crystal'] ?? 0)) > 0;
            $hasMoon  = !empty($r['id_luna']);

            $onlineTime = (int) ($r['onlinetime'] ?? 0);
            $online     = ($onlineTime > TIMESTAMP - 900);

            $planets[] = [
                'id'           => (int) $r['id'],
                'galaxy'       => (int) $r['galaxy'],
                'system'       => (int) $r['system'],
                'planet'       => (int) $r['planet'],
                'name'         => $r['name'] ?? 'Unknown',
                'image'        => $r['image'] ?? '',
                'user_id'      => $userId,
                'username'     => $r['username'] ?? 'Unknown',
                'ally_id'      => $allyId,
                'ally_tag'     => $r['ally_tag'] ?? '',
                'ally_name'    => $r['ally_name'] ?? '',
                'ally_color'   => $allyColors[$allyId] ?? null,
                'total_rank'   => (int) ($r['total_rank']   ?? 0),
                'total_points' => (int) ($r['total_points'] ?? 0),
                'is_own'       => $isOwn,
                'is_ally'      => $isAlly,
                'has_moon'     => $hasMoon,
                'has_debris'   => $hasDebris,
                'online'       => $online,
            ];
        }

        echo json_encode([
            'planets'         => $planets,
            'current_user_id' => (int) $USER['id'],
            'current_ally_id' => (int) ($USER['ally_id'] ?? 0),
            'server_time'     => TIMESTAMP,
            'count'           => count($planets),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // ── JSON: player card ────────────────────────────────────────
    public function card(): void
    {
        global $USER;

        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');

        if (empty($USER['id'])) {
            echo json_encode(['error' => 'not authenticated']);
            exit;
        }

        $targetId = max(1, (int) HTTP::_GP('player_id', 0));
        if ($targetId === 0) {
            echo json_encode(['error' => 'missing player_id']);
            exit;
        }

        $db = Database::get();

        $sql = 'SELECT
            u.id, u.username, u.ally_id, u.onlinetime,
            u.wons, u.loos, u.draws,
            a.ally_tag, a.ally_name,
            sp.total_rank, sp.total_points,
            sf.total_rank   AS fleet_rank,   sf.total_points AS fleet_points,
            st.total_rank   AS tech_rank,    st.total_points AS tech_points,
            sb.total_rank   AS build_rank,   sb.total_points AS build_points,
            sd.total_rank   AS defs_rank,    sd.total_points AS defs_points,
            p.galaxy, p.system, p.planet
        FROM %%USERS%% u
        LEFT JOIN %%ALLIANCE%%   a  ON a.id  = u.ally_id
        LEFT JOIN %%STATPOINTS%% sp ON sp.id_owner = u.id AND sp.stat_type = 1
        LEFT JOIN %%STATPOINTS%% sf ON sf.id_owner = u.id AND sf.stat_type = 3
        LEFT JOIN %%STATPOINTS%% st ON st.id_owner = u.id AND st.stat_type = 2
        LEFT JOIN %%STATPOINTS%% sb ON sb.id_owner = u.id AND sb.stat_type = 4
        LEFT JOIN %%STATPOINTS%% sd ON sd.id_owner = u.id AND sd.stat_type = 5
        LEFT JOIN %%PLANETS%%    p  ON p.id_owner  = u.id AND p.planet_type = 1
        WHERE u.id = :uid
        LIMIT 1;';

        $r = $db->selectSingle($sql, [':uid' => $targetId]);

        if (empty($r)) {
            echo json_encode(['error' => 'player not found']);
            exit;
        }

        $total  = max(1, (int) ($r['wons'] ?? 0) + (int) ($r['loos'] ?? 0) + (int) ($r['draws'] ?? 0));
        $winPct = round(100 * ((int) ($r['wons'] ?? 0)) / $total, 1);
        $online = ((int) ($r['onlinetime'] ?? 0)) > TIMESTAMP - 900;

        echo json_encode([
            'id'           => (int) $r['id'],
            'username'     => $r['username'] ?? '',
            'ally_id'      => (int) ($r['ally_id'] ?? 0),
            'ally_tag'     => $r['ally_tag']  ?? '',
            'ally_name'    => $r['ally_name'] ?? '',
            'galaxy'       => (int) ($r['galaxy'] ?? 1),
            'system'       => (int) ($r['system'] ?? 1),
            'planet'       => (int) ($r['planet'] ?? 1),
            'total_rank'   => (int) ($r['total_rank']    ?? 0),
            'total_points' => number_format((float) ($r['total_points']  ?? 0)),
            'fleet_rank'   => (int) ($r['fleet_rank']    ?? 0),
            'fleet_points' => number_format((float) ($r['fleet_points']  ?? 0)),
            'tech_rank'    => (int) ($r['tech_rank']     ?? 0),
            'tech_points'  => number_format((float) ($r['tech_points']   ?? 0)),
            'build_rank'   => (int) ($r['build_rank']    ?? 0),
            'build_points' => number_format((float) ($r['build_points']  ?? 0)),
            'defs_rank'    => (int) ($r['defs_rank']     ?? 0),
            'defs_points'  => number_format((float) ($r['defs_points']   ?? 0)),
            'wons'         => (int) ($r['wons']  ?? 0),
            'loos'         => (int) ($r['loos']  ?? 0),
            'draws'        => (int) ($r['draws'] ?? 0),
            'win_pct'      => $winPct,
            'online'       => $online,
            'is_self'      => ((int) $r['id'] === (int) $USER['id']),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
