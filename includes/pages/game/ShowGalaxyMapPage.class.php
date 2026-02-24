<?php

declare(strict_types=1);

/**
 * SmartMoons Galaxy Map Page Controller
 * 
 * Installation:
 *   1. Copy this file to:    includes/pages/ShowGalaxyMapPage.class.php
 *   2. Copy galaxy_map_api.php to: includes/pages/galaxy_map_api.php
 *   3. Copy page_galaxyMap_default.twig to: styles/templates/game/page.galaxyMap.default.twig
 *   4. Register the page in game.php or the router:
 *        'galaxyMap' => ['ShowGalaxyMapPage', 'show']
 *   5. Add navigation link in your nav template:
 *        <a href="game.php?page=galaxyMap">🌌 Galaxy Map</a>
 */

class ShowGalaxyMapPage extends AbstractGamePage
{
    public static $requireModule = MODULE_RESEARCH; // Use any existing module gate

    function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        global $USER, $PLANET;

        // No extra data needed – all data is loaded via API calls from JavaScript
        // We just pass user/planet context for JS variables

        $this->assign([
            'USER'   => $USER,
            'PLANET' => $PLANET,
        ]);

        $this->display('page.galaxyMap.default.twig');
    }
}
