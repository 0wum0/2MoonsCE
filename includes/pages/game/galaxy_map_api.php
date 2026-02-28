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

declare(strict_types=1);

define('MODE', 'INGAME');
require_once '../../common.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$action = HTTP::_GP('action', '');

switch ($action) {
    case 'galaxy_data':
        echo json_encode(getGalaxyData());
        break;
    case 'fleet_data':
        echo json_encode(getFleetData());
        break;
    case 'player_card':
        echo json_encode(getPlayerCard());
        break;
    default:
        echo json_encode(['error' => 'Unknown action']);
}

// ─────────────────────────────────────────────────────────────────────────────
// GALAXY DATA: All planets with owner, alliance, debris, moon info
// ─────────────────────────────────────────────────────────────────────────────
function getGalaxyData(): array
{
    global $USER;
    $db = Database::get();

    // Alliance color palette - deterministic from ally_id
    $allianceColors = [
        '#00d4ff', '#ff6b35', '#7fff00', '#ff1493', '#ffd700',
        '#00ffcc', '#ff4500', '#9400d3', '#00bfff', '#ff69b4',
        '#adff2f', '#ff8c00', '#1e90ff', '#dc143c', '#32cd32'
    ];

    $sql = "SELECT 
        p.id, p.name, p.galaxy, p.system, p.planet, p.planet_type,
        p.image, p.diameter, p.temp_max, p.temp_min,
        p.metal, p.crystal, p.deuterium,
        p.der_metal, p.der_crystal,
        p.id_luna, p.destruyed,
        u.id as user_id, u.username, u.ally_id, u.onlinetime,
        u.wons, u.loos,
        a.ally_name, a.ally_tag,
        sp.total_points, sp.total_rank
        FROM %%PLANETS%% p
        LEFT JOIN %%USERS%% u ON u.id = p.id_owner
        LEFT JOIN %%ALLIANCE%% a ON a.id = u.ally_id
        LEFT JOIN %%STATPOINTS%% sp ON sp.id_owner = u.id AND sp.stat_type = 1
        WHERE p.universe = :universe
          AND p.planet_type = 1
          AND p.destruyed = 0
          AND p.id_owner IS NOT NULL
        ORDER BY p.galaxy, p.system, p.planet;";

    $planets = $db->select($sql, [':universe' => Universe::current()]);

    $result = [];
    foreach ($planets as $p) {
        $colorIdx = ($p['ally_id'] > 0) ? (($p['ally_id'] - 1) % count($allianceColors)) : -1;
        $allyColor = ($colorIdx >= 0) ? $allianceColors[$colorIdx] : null;
        $isOwn = ($p['user_id'] == $USER['id']);
        $isAlly = ($p['ally_id'] > 0 && $p['ally_id'] == $USER['ally_id']);
        $hasDebris = ($p['der_metal'] > 0 || $p['der_crystal'] > 0);
        $hasMoon = ($p['id_luna'] > 0);
        $online = (time() - $p['onlinetime']) < 900; // 15min

        $result[] = [
            'id'          => (int)$p['id'],
            'name'        => $p['name'],
            'galaxy'      => (int)$p['galaxy'],
            'system'      => (int)$p['system'],
            'planet'      => (int)$p['planet'],
            'image'       => $p['image'],
            'diameter'    => (int)$p['diameter'],
            'temp_max'    => (int)$p['temp_max'],
            'user_id'     => (int)$p['user_id'],
            'username'    => $p['username'],
            'ally_id'     => (int)$p['ally_id'],
            'ally_name'   => $p['ally_name'] ?? '',
            'ally_tag'    => $p['ally_tag'] ?? '',
            'ally_color'  => $allyColor,
            'total_rank'  => (int)$p['total_rank'],
            'total_points'=> (int)$p['total_points'],
            'is_own'      => $isOwn,
            'is_ally'     => $isAlly,
            'has_moon'    => $hasMoon,
            'has_debris'  => $hasDebris,
            'debris_metal'   => (float)$p['der_metal'],
            'debris_crystal' => (float)$p['der_crystal'],
            'online'      => $online,
        ];
    }

    return [
        'planets'   => $result,
        'current_user_id'  => (int)$USER['id'],
        'current_ally_id'  => (int)$USER['ally_id'],
        'universe'  => Universe::current(),
        'timestamp' => time(),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// FLEET DATA: Active fleet movements with start/end coordinates
// ─────────────────────────────────────────────────────────────────────────────
function getFleetData(): array
{
    global $USER;
    $db = Database::get();

    $now = time();

    $sql = "SELECT 
        f.fleet_id, f.fleet_owner, f.fleet_mission, f.fleet_mess,
        f.fleet_start_galaxy, f.fleet_start_system, f.fleet_start_planet, f.fleet_start_type,
        f.fleet_end_galaxy,   f.fleet_end_system,   f.fleet_end_planet,   f.fleet_end_type,
        f.fleet_start_time, f.fleet_end_time, f.fleet_end_stay,
        f.fleet_amount, f.fleet_target_owner,
        u.username as owner_name, u.ally_id,
        a.ally_tag
        FROM %%FLEETS%% f
        LEFT JOIN %%USERS%% u ON u.id = f.fleet_owner
        LEFT JOIN %%ALLIANCE%% a ON a.id = u.ally_id
        WHERE f.fleet_universe = :universe
          AND f.fleet_end_time > :now
          AND f.fleet_mission != 10
        ORDER BY f.fleet_start_time ASC
        LIMIT 300;";

    $fleets = $db->select($sql, [
        ':universe' => Universe::current(),
        ':now'      => $now,
    ]);

    $missionColors = [
        1  => '#ff3030', // Attack
        2  => '#ff6600', // ACS Attack
        3  => '#00ccff', // Transport
        4  => '#00ff88', // Deploy
        5  => '#8888ff', // Hold/Ally
        6  => '#ffff00', // Spy
        7  => '#00ff44', // Colonize
        8  => '#ff9900', // Recycle
        9  => '#ff0066', // Destroy
        11 => '#cc00ff', // DM Expedition
        15 => '#ff88ff', // Expedition
    ];

    $result = [];
    foreach ($fleets as $f) {
        $isOwn = ((int)$f['fleet_owner'] === (int)$USER['id']);
        $isHostile = (!$isOwn && in_array((int)$f['fleet_mission'], [1,2,6,9,10]));
        $color = $missionColors[(int)$f['fleet_mission']] ?? '#ffffff';
        if ($isHostile) $color = '#ff0000';
        if ($isOwn)     $color = '#00ff88';

        // Progress 0..1
        $total    = max(1, $f['fleet_end_time'] - $f['fleet_start_time']);
        $elapsed  = $now - $f['fleet_start_time'];
        $progress = min(1.0, max(0.0, $elapsed / $total));

        $result[] = [
            'id'       => (int)$f['fleet_id'],
            'mission'  => (int)$f['fleet_mission'],
            'state'    => (int)$f['fleet_mess'],
            'amount'   => (int)$f['fleet_amount'],
            'owner'    => $f['owner_name'],
            'ally_tag' => $f['ally_tag'] ?? '',
            'is_own'   => $isOwn,
            'is_hostile' => $isHostile,
            'color'    => $color,
            'start' => [
                'g' => (int)$f['fleet_start_galaxy'],
                's' => (int)$f['fleet_start_system'],
                'p' => (int)$f['fleet_start_planet'],
            ],
            'end' => [
                'g' => (int)$f['fleet_end_galaxy'],
                's' => (int)$f['fleet_end_system'],
                'p' => (int)$f['fleet_end_planet'],
            ],
            'progress'   => round($progress, 4),
            'start_time' => (int)$f['fleet_start_time'],
            'end_time'   => (int)$f['fleet_end_time'],
        ];
    }

    return [
        'fleets'    => $result,
        'timestamp' => $now,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// PLAYER CARD: Rich player data for popup
// ─────────────────────────────────────────────────────────────────────────────
function getPlayerCard(): array
{
    global $USER;
    $db  = Database::get();
    $pid = HTTP::_GP('player_id', 0);

    if (!$pid) return ['error' => 'No player id'];

    $sql = "SELECT 
        u.id, u.username, u.galaxy, u.system, u.planet,
        u.wons, u.loos, u.draws, u.kbmetal, u.kbcrystal,
        u.lostunits, u.desunits, u.ally_id, u.onlinetime,
        p.name as planet_name,
        sp.total_rank, sp.total_points, sp.fleet_rank, sp.fleet_points,
        sp.tech_rank, sp.tech_points, sp.build_rank, sp.build_points,
        sp.defs_rank, sp.defs_points,
        a.ally_name, a.ally_tag
        FROM %%USERS%% u
        INNER JOIN %%PLANETS%% p ON p.id = u.id_planet
        LEFT JOIN %%STATPOINTS%% sp ON sp.id_owner = u.id AND sp.stat_type = 1
        LEFT JOIN %%ALLIANCE%% a ON a.id = u.ally_id
        WHERE u.id = :pid AND u.universe = :universe;";

    $data = $db->selectSingle($sql, [
        ':pid'      => $pid,
        ':universe' => Universe::current(),
    ]);

    if (!$data) return ['error' => 'Player not found'];

    $totalFights = $data['wons'] + $data['loos'] + $data['draws'];
    $winPct = $totalFights > 0 ? round(100 / $totalFights * $data['wons'], 1) : 0;
    $online = (time() - $data['onlinetime']) < 900;

    return [
        'id'           => (int)$data['id'],
        'username'     => $data['username'],
        'planet_name'  => $data['planet_name'],
        'galaxy'       => (int)$data['galaxy'],
        'system'       => (int)$data['system'],
        'planet'       => (int)$data['planet'],
        'ally_id'      => (int)$data['ally_id'],
        'ally_name'    => $data['ally_name'] ?? '',
        'ally_tag'     => $data['ally_tag'] ?? '',
        'total_rank'   => (int)$data['total_rank'],
        'total_points' => number_format((int)$data['total_points']),
        'fleet_rank'   => (int)$data['fleet_rank'],
        'fleet_points' => number_format((int)$data['fleet_points']),
        'tech_rank'    => (int)$data['tech_rank'],
        'tech_points'  => number_format((int)$data['tech_points']),
        'build_rank'   => (int)$data['build_rank'],
        'build_points' => number_format((int)$data['build_points']),
        'defs_rank'    => (int)$data['defs_rank'],
        'defs_points'  => number_format((int)$data['defs_points']),
        'wons'         => (int)$data['wons'],
        'loos'         => (int)$data['loos'],
        'draws'        => (int)$data['draws'],
        'win_pct'      => $winPct,
        'kbmetal'      => number_format((int)$data['kbmetal']),
        'kbcrystal'    => number_format((int)$data['kbcrystal']),
        'lostunits'    => number_format((int)$data['lostunits']),
        'desunits'     => number_format((int)$data['desunits']),
        'online'       => $online,
        'is_self'      => ((int)$data['id'] === (int)$USER['id']),
    ];
}
