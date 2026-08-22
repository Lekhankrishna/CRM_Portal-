-- Adds a per-account permission flag gating visibility of and direct access
-- to the Tata DTH Search menu item / tata_dth.php / tata_dth_api.php. Same
-- pattern as migrate_add_hp_gas_access.sql - DEFAULT 0, opt-in per agent
-- from Admin > Agents.
ALTER TABLE `users`
  ADD COLUMN `tata_dth_access` TINYINT(1) NOT NULL DEFAULT 0 AFTER `advanced_search_monthly_limit`;
