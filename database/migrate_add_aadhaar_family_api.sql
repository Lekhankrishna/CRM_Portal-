-- "Aadhaar to Family Advanced" (Nexora API's Aadhaar -> ration card + family
-- members lookup, /api/bharat/aadhaar-family). Per-account access flag, off
-- by default like every other tool (granted from Admin > Agents), a monthly
-- limit (every search counts, found or not - see
-- includes/nexora_client.php's NEXORA_COUNT_EVERY_SEARCH), and its own
-- read-through cache table.
ALTER TABLE users
  ADD COLUMN aadhaar_family_api_access TINYINT(1) NOT NULL DEFAULT 0 AFTER all_gas_hp_monthly_limit,
  ADD COLUMN aadhaar_family_api_monthly_limit SMALLINT UNSIGNED NOT NULL DEFAULT 50 AFTER aadhaar_family_api_access;

CREATE TABLE IF NOT EXISTS `search_cache_aadhaar_family_api` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `search_key` CHAR(32) NOT NULL,
  `search_key_display` VARCHAR(255) NOT NULL,
  `result_json` LONGTEXT NOT NULL,
  `searched_by` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_search_key` (`search_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
