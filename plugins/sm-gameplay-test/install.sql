-- sm-gameplay-test install.sql
-- Adds building ID 900 (Advanced Metal Mine) to the game.
-- %%PLANETS%%, %%VARS%%, %%VARS_REQUIRE%% are resolved by PluginManager::runSqlFile().

-- 1. Add planet columns for building 900
ALTER TABLE %%PLANETS%%
    ADD COLUMN IF NOT EXISTS `smgt_advanced_mine` tinyint(3) unsigned NOT NULL DEFAULT '0',
    ADD COLUMN IF NOT EXISTS `smgt_advanced_mine_porcent` tinyint(3) unsigned NOT NULL DEFAULT '10';

-- 2. Insert building definition into vars
INSERT IGNORE INTO %%VARS%%
    (`elementID`, `name`, `class`, `onPlanetType`, `onePerPlanet`, `factor`, `maxLevel`,
     `cost901`, `cost902`, `cost903`, `cost911`, `cost921`,
     `consumption1`, `consumption2`, `speedTech`, `speed1`, `speed2`,
     `speed2Tech`, `speed2onLevel`, `speed3Tech`, `speed3onLevel`,
     `capacity`, `attack`, `defend`, `timeBonus`,
     `bonusAttack`, `bonusDefensive`, `bonusShield`,
     `bonusBuildTime`, `bonusResearchTime`, `bonusShipTime`, `bonusDefensiveTime`,
     `bonusResource`, `bonusEnergy`, `bonusResourceStorage`, `bonusShipStorage`,
     `bonusFlyTime`, `bonusFleetSlots`, `bonusPlanets`, `bonusSpyPower`,
     `bonusExpedition`, `bonusGateCoolTime`, `bonusMoreFound`,
     `bonusAttackUnit`, `bonusDefensiveUnit`, `bonusShieldUnit`,
     `bonusBuildTimeUnit`, `bonusResearchTimeUnit`, `bonusShipTimeUnit`, `bonusDefensiveTimeUnit`,
     `bonusResourceUnit`, `bonusEnergyUnit`, `bonusResourceStorageUnit`, `bonusShipStorageUnit`,
     `bonusFlyTimeUnit`, `bonusFleetSlotsUnit`, `bonusPlanetsUnit`, `bonusSpyPowerUnit`,
     `bonusExpeditionUnit`, `bonusGateCoolTimeUnit`, `bonusMoreFoundUnit`,
     `speedFleetFactor`,
     `production901`, `production902`, `production903`, `production911`, `production921`,
     `storage901`, `storage902`, `storage903`)
VALUES
    (900, 'smgt_advanced_mine', 0, '1', 0, 1.50, 30,
     80000, 40000, 20000, 0, 0,
     NULL, NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, NULL,
     0.00, 0.00, 0.00,
     0.00, 0.00, 0.00, 0.00,
     0.00, 0.00, 0.00, 0.00,
     0.00, 0.00, 0.00, 0.00,
     0.00, 0.00, 0.00,
     0, 0, 0,
     0, 0, 0, 0,
     0, 0, 0, 0,
     0, 0, 0, 0,
     0, 0, 0,
     NULL,
     '(30 * $BuildLevel * pow((1.1), $BuildLevel)) * (0.1 * $BuildLevelFactor)',
     NULL, NULL,
     '-(10 * $BuildLevel * pow((1.1), $BuildLevel)) * (0.1 * $BuildLevelFactor)',
     NULL,
     NULL, NULL, NULL);

-- 3. Insert requirements: Metal Mine level 15, Energy Tech level 5
INSERT IGNORE INTO %%VARS_REQUIRE%% (`elementID`, `requireID`, `requireLevel`) VALUES
    (900, 1,   15),
    (900, 113,  5);
