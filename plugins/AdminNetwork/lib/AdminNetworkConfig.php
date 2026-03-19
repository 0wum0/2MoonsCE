<?php
/**
 * 2MoonsCE — AdminNetwork: Config helper
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 */

declare(strict_types=1);

class AdminNetworkConfig
{
    private static ?array $cache = null;

    public static function get(bool $fresh = false): array
    {
        if ($fresh) self::$cache = null;

        if (self::$cache !== null) return self::$cache;

        $pm = PluginManager::get();
        self::$cache = [
            'hub_url'       => (string)$pm->getConfig('AdminNetwork', 'hub_url',        'https://2moonsce.makeit.uno/hub/'),
            'instance_key'  => (string)$pm->getConfig('AdminNetwork', 'instance_key',   ''),
            'instance_name' => (string)$pm->getConfig('AdminNetwork', 'instance_name',  ''),
            'poll_interval' => max(5, (int)$pm->getConfig('AdminNetwork', 'poll_interval', 10)),
        ];
        return self::$cache;
    }
}
