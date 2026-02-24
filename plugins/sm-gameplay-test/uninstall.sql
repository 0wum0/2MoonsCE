-- sm-gameplay-test uninstall.sql
-- Removes building ID 900 (Advanced Metal Mine) from the game.

-- 1. Remove requirements
DELETE FROM %%VARS_REQUIRE%% WHERE `elementID` = 900;

-- 2. Remove building definition
DELETE FROM %%VARS%% WHERE `elementID` = 900;

-- 3. Drop planet columns (data loss – intentional on uninstall)
ALTER TABLE %%PLANETS%%
    DROP COLUMN IF EXISTS `smgt_advanced_mine`,
    DROP COLUMN IF EXISTS `smgt_advanced_mine_porcent`;
