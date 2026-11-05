CREATE TABLE IF NOT EXISTS `orders_fraudlabspro` (
  `order_id` int unsigned NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `ip_continent` varchar(20) DEFAULT NULL,
  `ip_country` char(2) DEFAULT NULL,
  `ip_country_name` varchar(100) DEFAULT NULL,
  `ip_region` varchar(64) DEFAULT NULL,
  `ip_city` varchar(64) DEFAULT NULL,
  `ip_latitude` varchar(21) DEFAULT NULL,
  `ip_longitude` varchar(21) DEFAULT NULL,
  `ip_zip` varchar(20) DEFAULT NULL,
  `ip_timezone` varchar(64) DEFAULT NULL,
  `ip_isp_name` varchar(100) DEFAULT NULL,
  `ip_domain` varchar(100) DEFAULT NULL,
  `ip_netspeed` varchar(100) DEFAULT NULL,
  `ip_mobile_mnc` varchar(100) DEFAULT NULL,
  `ip_mobile_mcc` varchar(100) DEFAULT NULL,
  `ip_mobile_brand` varchar(100) DEFAULT NULL,
  `ip_elevation` varchar(10) DEFAULT NULL,
  `ip_usage_type` varchar(100) DEFAULT NULL,
  `is_proxy_ip_address` char(1) DEFAULT NULL,
  `distance_in_km` varchar(10) DEFAULT NULL,
  `distance_in_mile` varchar(10) DEFAULT NULL,
  `is_country_match` char(1) DEFAULT NULL,
  `is_address_ship_forward` char(1) DEFAULT NULL,
  `is_bill_ship_city_match` char(1) DEFAULT NULL,
  `is_bill_ship_state_match` char(1) DEFAULT NULL,
  `is_bill_ship_country_match` char(1) DEFAULT NULL,
  `is_bill_ship_postal_match` char(1) DEFAULT NULL,
  `is_bill_ship_embargo` char(1) DEFAULT NULL,
  `is_free_email` char(1) DEFAULT NULL,
  `is_domain_exist` char(1) DEFAULT NULL,
  `is_new_domain_name` char(1) DEFAULT NULL,
  `is_bin_found` char(1) DEFAULT NULL,
  `is_bin_country_match` char(1) DEFAULT NULL,
  `is_bin_prepaid` char(1) DEFAULT NULL,
  `is_ip_blacklist` char(1) DEFAULT NULL,
  `is_email_blacklist` char(1) DEFAULT NULL,
  `is_credit_card_blacklist` char(1) DEFAULT NULL,
  `is_device_blacklist` char(1) DEFAULT NULL,
  `is_user_blacklist` char(1) DEFAULT NULL,
  `is_device_malware` char(1) DEFAULT NULL,
  `is_ship_blacklist` char(1) DEFAULT NULL,
  `is_phone_blacklist` char(1) DEFAULT NULL,
  `is_user_high_risk` char(1) DEFAULT NULL,
  `is_disposable_email` char(1) DEFAULT NULL,
  `is_disposable_phone` char(1) DEFAULT NULL,
  `cc_issuing_bank` varchar(100) DEFAULT NULL,
  `cc_issuing_country` char(2) DEFAULT NULL,
  `flp_id` varchar(32) DEFAULT NULL,
  `flp_score` char(3) DEFAULT NULL,
  `flp_status` varchar(16) DEFAULT NULL,
  `flp_error_code` varchar(16) DEFAULT NULL,
  `flp_message` varchar(255) DEFAULT NULL,
  `flp_credits` varchar(16) DEFAULT NULL,
  `api_key` varchar(64) DEFAULT NULL,
  `flp_distribution` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `configuration_group` (`configuration_group_title`, `configuration_group_description`, `sort_order`, `visible`)
SELECT 'FraudLabs Pro', 'FraudLabs Pro fraud screening settings', 100, 0
WHERE NOT EXISTS (SELECT 1 FROM `configuration_group` WHERE `configuration_group_title` = 'FraudLabs Pro');

INSERT INTO `configuration` (`configuration_title`, `configuration_key`, `configuration_value`, `configuration_description`, `configuration_group_id`, `sort_order`, `last_modified`, `date_added`, `use_function`, `set_function`, `val_function`)
SELECT 'Enabled', 'FRAUDLABSPRO_ENABLED', 'true', 'Enable or disable FraudLabs Pro module.', configuration_group_id, 50, NOW(), NOW(), NULL, 'fraudlabspro_cfg_yes_no(array(\'true\', \'false\'), ', NULL
FROM `configuration_group` WHERE `configuration_group_title` = 'FraudLabs Pro' LIMIT 1
ON DUPLICATE KEY UPDATE `configuration_title` = VALUES(`configuration_title`), `configuration_description` = VALUES(`configuration_description`), `set_function` = VALUES(`set_function`), `configuration_group_id` = VALUES(`configuration_group_id`);

INSERT INTO `configuration` (`configuration_title`, `configuration_key`, `configuration_value`, `configuration_description`, `configuration_group_id`, `sort_order`, `last_modified`, `date_added`, `use_function`, `set_function`, `val_function`)
SELECT 'License Key', 'FRAUDLABSPRO_LICENSE', '', 'Enter your FraudLabs Pro License Key. You can register a free license key at <a href="https://www.fraudlabspro.com/sign-up" target="_blank">https://www.fraudlabspro.com/sign-up</a> if you do not have one.', configuration_group_id, 51, NOW(), NOW(), NULL, NULL, NULL
FROM `configuration_group` WHERE `configuration_group_title` = 'FraudLabs Pro' LIMIT 1
ON DUPLICATE KEY UPDATE `configuration_title` = VALUES(`configuration_title`), `configuration_description` = VALUES(`configuration_description`), `set_function` = VALUES(`set_function`), `configuration_group_id` = VALUES(`configuration_group_id`);

INSERT INTO `configuration` (`configuration_title`, `configuration_key`, `configuration_value`, `configuration_description`, `configuration_group_id`, `sort_order`, `last_modified`, `date_added`, `use_function`, `set_function`, `val_function`)
SELECT 'Enable Agent Javascript', 'FRAUDLABSPRO_AGENTJS', 'false', 'Enable FraudLabs Pro <a href="https://www.fraudlabspro.com/developer/javascript" target="_blank">Agent Javascript</a>. This installs a cookie during checkout that attaches the client Agent, Browser, Operating System and Device Type to the transaction. This is optional, but when disabled the API cannot report whether the device has been blacklisted.<br><br><em>Yes</em> - Enable Agent Javascript<br><em>No</em> - Disable Agent Javascript', configuration_group_id, 52, NOW(), NOW(), NULL, 'fraudlabspro_cfg_yes_no(array(\'true\', \'false\'),', NULL
FROM `configuration_group` WHERE `configuration_group_title` = 'FraudLabs Pro' LIMIT 1
ON DUPLICATE KEY UPDATE `configuration_title` = VALUES(`configuration_title`), `configuration_description` = VALUES(`configuration_description`), `set_function` = VALUES(`set_function`), `configuration_group_id` = VALUES(`configuration_group_id`);

INSERT INTO `configuration` (`configuration_title`, `configuration_key`, `configuration_value`, `configuration_description`, `configuration_group_id`, `sort_order`, `last_modified`, `date_added`, `use_function`, `set_function`, `val_function`)
SELECT 'Trust Proxy Headers', 'FRAUDLABSPRO_TRUST_PROXY', 'false', 'Set to <em>Yes</em> only if the store runs behind a reverse proxy or CDN that overwrites the client IP headers (Cloudflare, Sucuri, etc.). When enabled, the module reads the client address from X-Forwarded-For / CF-Connecting-IP. When disabled, only REMOTE_ADDR is used, so a customer cannot choose the IP that gets screened.<br><br><em>Yes</em> - Trust proxy headers<br><em>No</em> - Use REMOTE_ADDR only', configuration_group_id, 53, NOW(), NOW(), NULL, 'fraudlabspro_cfg_yes_no(array(\'true\', \'false\'),', NULL
FROM `configuration_group` WHERE `configuration_group_title` = 'FraudLabs Pro' LIMIT 1
ON DUPLICATE KEY UPDATE `configuration_title` = VALUES(`configuration_title`), `configuration_description` = VALUES(`configuration_description`), `set_function` = VALUES(`set_function`), `configuration_group_id` = VALUES(`configuration_group_id`);

INSERT INTO `configuration` (`configuration_title`, `configuration_key`, `configuration_value`, `configuration_description`, `configuration_group_id`, `sort_order`, `last_modified`, `date_added`, `use_function`, `set_function`, `val_function`)
SELECT 'IP Override (testing)', 'FRAUDLABSPRO_IP_OVERRIDE', '', 'If set, this IP address is sent to FraudLabs Pro instead of the visitor\'s IP address. The real address is still stored with the order. Leave empty for normal operation and use only for testing and debugging.', configuration_group_id, 54, NOW(), NOW(), NULL, NULL, NULL
FROM `configuration_group` WHERE `configuration_group_title` = 'FraudLabs Pro' LIMIT 1
ON DUPLICATE KEY UPDATE `configuration_title` = VALUES(`configuration_title`), `configuration_description` = VALUES(`configuration_description`), `set_function` = VALUES(`set_function`), `configuration_group_id` = VALUES(`configuration_group_id`);

DELETE FROM `admin_pages` WHERE `page_key` = 'fraudLabsPro';

INSERT INTO `admin_pages` (`page_key`, `language_key`, `main_page`, `page_params`, `menu_key`, `display_on_menu`, `sort_order`)
SELECT 'fraudLabsPro', 'BOX_TOOLS_FRAUDLABSPRO', 'FILENAME_CONFIGURATION', CONCAT('gID=', configuration_group_id), 'tools', 'Y', 80
FROM `configuration_group` WHERE `configuration_group_title` = 'FraudLabs Pro' LIMIT 1;
