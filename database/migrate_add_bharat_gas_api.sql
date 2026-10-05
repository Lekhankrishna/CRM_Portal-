-- "Bharat Gas Advanced" (Nexora API's Bharat gas connection lookup,
-- /api/bharat/gas-connection-bharat-paanel) - Bharat Gas has no existing
-- locateme.services-backed equivalent in this CRM, so this is its only
-- lookup tool (aside from the "Bharat Gas" tab on All Gas, a different
-- vendor - tracekart.in). Per-account access flag, off by default like
-- every other tool, a monthly limit (every search counts, found or not -
-- see includes/nexora_client.php's NEXORA_COUNT_EVERY_SEARCH), and its own
-- read-through cache table.
ALTER TABLE users
  ADD COLUMN bharat_gas_api_access TINYINT(1) NOT NULL DEFAULT 0 AFTER hp_gas_api_monthly_limit,
  ADD COLUMN bharat_gas_api_monthly_limit SMALLINT UNSIGNED NOT NULL DEFAULT 50 AFTER bharat_gas_api_access;

CREATE TABLE IF NOT EXISTS `search_cache_bharat_gas_api` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `search_key` CHAR(32) NOT NULL,
  `search_key_display` VARCHAR(255) NOT NULL,
  `result_json` LONGTEXT NOT NULL,
  `searched_by` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_search_key` (`search_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
