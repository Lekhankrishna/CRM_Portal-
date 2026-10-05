-- "All Gas" (tracekart.in Skip Trace "Gas Connection", 2026-10-05): per-
-- account access flag, off by default like every other tool (granted from
-- Admin > Agents), plus its own read-through cache table in the same shape
-- as migrate_add_search_cache_tables.sql's, and a monthly limit per
-- provider (Indane/Bharat/HP) since each is billed separately on the
-- shared tracekart.in account.
ALTER TABLE users
  ADD COLUMN all_gas_access TINYINT(1) NOT NULL DEFAULT 0 AFTER advanced_search_monthly_limit,
  ADD COLUMN all_gas_indane_monthly_limit SMALLINT UNSIGNED NOT NULL DEFAULT 50 AFTER all_gas_access,
  ADD COLUMN all_gas_bharat_monthly_limit SMALLINT UNSIGNED NOT NULL DEFAULT 50 AFTER all_gas_indane_monthly_limit,
  ADD COLUMN all_gas_hp_monthly_limit     SMALLINT UNSIGNED NOT NULL DEFAULT 50 AFTER all_gas_bharat_monthly_limit;

CREATE TABLE IF NOT EXISTS `search_cache_all_gas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `search_key` CHAR(32) NOT NULL,
  `search_key_display` VARCHAR(255) NOT NULL,
  `result_json` LONGTEXT NOT NULL,
  `searched_by` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_search_key` (`search_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
