-- Caps how many Tata DTH searches each agent can run per calendar month -
-- one shared distributor Siebel PRM login drives every search, so this
-- exists to keep one agent from hammering the account into a lockout/rate-
-- limit that would affect everyone, same reasoning as
-- migrate_add_hp_gas_monthly_limit.sql. Default 5/month, editable per agent
-- from Admin > Agents; admins bypass this entirely (see tata_dth_api.php).
ALTER TABLE `users`
  ADD COLUMN `tata_dth_monthly_limit` SMALLINT UNSIGNED NOT NULL DEFAULT 5 AFTER `tata_dth_access`;
