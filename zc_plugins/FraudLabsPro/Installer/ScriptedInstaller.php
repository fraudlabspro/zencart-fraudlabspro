<?php

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected function executeInstall()
    {
        $groupId = $this->registerSettingsPage();
        $yesNo = "fraudlabspro_cfg_yes_no(['true', 'false'], ";

        $settings = [
            'FRAUDLABSPRO_ENABLED' => [
                'configuration_title' => 'Enabled',
                'configuration_value' => 'true',
                'configuration_description' => 'Enable or disable FraudLabs Pro module.',
                'configuration_group_id' => $groupId,
                'sort_order' => 50,
                'set_function' => $yesNo,
            ],
            'FRAUDLABSPRO_LICENSE' => [
                'configuration_title' => 'License Key',
                'configuration_value' => '',
                'configuration_description' => 'Enter your FraudLabs Pro License Key. You can register a free license key at <a href="https://www.fraudlabspro.com/sign-up" target="_blank">https://www.fraudlabspro.com/sign-up</a> if you do not have one.',
                'configuration_group_id' => $groupId,
                'sort_order' => 51,
            ],
            'FRAUDLABSPRO_AGENTJS' => [
                'configuration_title' => 'Enable Agent Javascript',
                'configuration_value' => 'false',
                'configuration_description' => 'Enable FraudLabs Pro <a href="https://www.fraudlabspro.com/developer/javascript" target="_blank">Agent Javascript</a>. This installs a cookie during checkout that attaches the client Agent, Browser, Operating System and Device Type to the transaction. This is optional, but when disabled the API cannot report whether the device has been blacklisted.<br><br><em>Yes</em> - Enable Agent Javascript<br><em>No</em> - Disable Agent Javascript',
                'configuration_group_id' => $groupId,
                'sort_order' => 52,
                'set_function' => $yesNo,
            ],
            'FRAUDLABSPRO_TRUST_PROXY' => [
                'configuration_title' => 'Trust Proxy Headers',
                'configuration_value' => 'false',
                'configuration_description' => 'Set to <em>Yes</em> only if the store runs behind a reverse proxy or CDN that overwrites the client IP headers (Cloudflare, Sucuri, etc.). When enabled, the module reads the client address from X-Forwarded-For / CF-Connecting-IP. When disabled, only REMOTE_ADDR is used, so a customer cannot choose the IP that gets screened.<br><br><em>Yes</em> - Trust proxy headers<br><em>No</em> - Use REMOTE_ADDR only',
                'configuration_group_id' => $groupId,
                'sort_order' => 53,
                'set_function' => $yesNo,
            ],
            'FRAUDLABSPRO_IP_OVERRIDE' => [
                'configuration_title' => 'IP Override (testing)',
                'configuration_value' => '',
                'configuration_description' => "If set, this IP address is sent to FraudLabs Pro instead of the visitor's IP address. The real address is still stored with the order. Leave empty for normal operation and use only for testing and debugging.",
                'configuration_group_id' => $groupId,
                'sort_order' => 54,
            ],
        ];

        foreach ($settings as $key => $properties) {
            $this->addConfigurationKey($key, $properties);
        }

        foreach ($settings as $key => $properties) {
            unset($properties['configuration_value']);
            $this->updateConfigurationKey($key, $properties);
        }

        $this->ensureSchema();

        parent::executeInstall();

        return true;
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(['fraudLabsPro']);

        $this->deleteConfigurationKeys([
            'FRAUDLABSPRO_ENABLED',
            'FRAUDLABSPRO_LICENSE',
            'FRAUDLABSPRO_AGENTJS',
            'FRAUDLABSPRO_TRUST_PROXY',
            'FRAUDLABSPRO_IP_OVERRIDE',
        ]);

        $this->deleteConfigurationGroup('FraudLabs Pro');

        $this->executeInstallerSql('DROP TABLE IF EXISTS ' . DB_PREFIX . 'orders_fraudlabspro');

        parent::executeUninstall();

        return true;
    }

    protected function executeUpgrade($oldVersion)
    {
        $this->registerSettingsPage();
        $this->ensureSchema();

        return true;
    }

    private function registerSettingsPage(): int
    {
        $groupId = $this->getOrCreateConfigGroupId('FraudLabs Pro', 'FraudLabs Pro fraud screening settings', 100);
        $this->updateConfigurationGroup($groupId, ['visible' => 0]);

        $this->executeInstallerSql(
            'UPDATE ' . TABLE_CONFIGURATION
            . ' SET configuration_group_id = ' . (int) $groupId
            . " WHERE configuration_key IN ('FRAUDLABSPRO_ENABLED', 'FRAUDLABSPRO_LICENSE', 'FRAUDLABSPRO_AGENTJS', 'FRAUDLABSPRO_TRUST_PROXY')"
        );

        zen_deregister_admin_pages(['fraudLabsPro']);
        zen_register_admin_page(
            'fraudLabsPro',
            'BOX_TOOLS_FRAUDLABSPRO',
            'FILENAME_CONFIGURATION',
            'gID=' . $groupId,
            'tools',
            'Y',
            80
        );

        return $groupId;
    }

    private function ensureSchema(): void
    {
        $table = DB_PREFIX . 'orders_fraudlabspro';

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS {$table} (
                order_id int unsigned NOT NULL,
                ip_address varchar(45) DEFAULT NULL,
                ip_continent varchar(20) DEFAULT NULL,
                ip_country char(2) DEFAULT NULL,
                ip_country_name varchar(100) DEFAULT NULL,
                ip_region varchar(64) DEFAULT NULL,
                ip_city varchar(64) DEFAULT NULL,
                ip_latitude varchar(21) DEFAULT NULL,
                ip_longitude varchar(21) DEFAULT NULL,
                ip_zip varchar(20) DEFAULT NULL,
                ip_timezone varchar(64) DEFAULT NULL,
                ip_isp_name varchar(100) DEFAULT NULL,
                ip_domain varchar(100) DEFAULT NULL,
                ip_netspeed varchar(100) DEFAULT NULL,
                ip_mobile_mnc varchar(100) DEFAULT NULL,
                ip_mobile_mcc varchar(100) DEFAULT NULL,
                ip_mobile_brand varchar(100) DEFAULT NULL,
                ip_elevation varchar(10) DEFAULT NULL,
                ip_usage_type varchar(100) DEFAULT NULL,
                is_proxy_ip_address char(1) DEFAULT NULL,
                distance_in_km varchar(10) DEFAULT NULL,
                distance_in_mile varchar(10) DEFAULT NULL,
                is_country_match char(1) DEFAULT NULL,
                is_address_ship_forward char(1) DEFAULT NULL,
                is_bill_ship_city_match char(1) DEFAULT NULL,
                is_bill_ship_state_match char(1) DEFAULT NULL,
                is_bill_ship_country_match char(1) DEFAULT NULL,
                is_bill_ship_postal_match char(1) DEFAULT NULL,
                is_bill_ship_embargo char(1) DEFAULT NULL,
                is_free_email char(1) DEFAULT NULL,
                is_domain_exist char(1) DEFAULT NULL,
                is_new_domain_name char(1) DEFAULT NULL,
                is_bin_found char(1) DEFAULT NULL,
                is_bin_country_match char(1) DEFAULT NULL,
                is_bin_prepaid char(1) DEFAULT NULL,
                is_ip_blacklist char(1) DEFAULT NULL,
                is_email_blacklist char(1) DEFAULT NULL,
                is_credit_card_blacklist char(1) DEFAULT NULL,
                is_device_blacklist char(1) DEFAULT NULL,
                is_user_blacklist char(1) DEFAULT NULL,
                is_device_malware char(1) DEFAULT NULL,
                is_ship_blacklist char(1) DEFAULT NULL,
                is_phone_blacklist char(1) DEFAULT NULL,
                is_user_high_risk char(1) DEFAULT NULL,
                is_disposable_email char(1) DEFAULT NULL,
                is_disposable_phone char(1) DEFAULT NULL,
                cc_issuing_bank varchar(100) DEFAULT NULL,
                cc_issuing_country char(2) DEFAULT NULL,
                flp_id varchar(32) DEFAULT NULL,
                flp_score char(3) DEFAULT NULL,
                flp_status varchar(16) DEFAULT NULL,
                flp_error_code varchar(16) DEFAULT NULL,
                flp_message varchar(255) DEFAULT NULL,
                flp_credits varchar(16) DEFAULT NULL,
                api_key varchar(64) DEFAULT NULL,
                flp_distribution varchar(16) DEFAULT NULL,
                PRIMARY KEY (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $primary = $this->executeInstallerSelectQuery("SHOW INDEX FROM {$table} WHERE Key_name = 'PRIMARY'");

        if (!$primary->EOF) {
            return;
        }

        $legacy = $this->executeInstallerSelectQuery("SHOW INDEX FROM {$table} WHERE Key_name = 'order_id'");

        if (!$legacy->EOF) {
            $this->executeInstallerSql("ALTER TABLE {$table} DROP INDEX `order_id`");
        }

        $this->executeInstallerSql(
            "ALTER TABLE {$table}
                MODIFY order_id int unsigned NOT NULL,
                MODIFY ip_address varchar(45) DEFAULT NULL,
                MODIFY ip_continent varchar(20) DEFAULT NULL,
                MODIFY ip_country char(2) DEFAULT NULL,
                MODIFY ip_country_name varchar(100) DEFAULT NULL,
                MODIFY ip_region varchar(64) DEFAULT NULL,
                MODIFY ip_city varchar(64) DEFAULT NULL,
                MODIFY ip_latitude varchar(21) DEFAULT NULL,
                MODIFY ip_longitude varchar(21) DEFAULT NULL,
                MODIFY ip_zip varchar(20) DEFAULT NULL,
                MODIFY ip_timezone varchar(64) DEFAULT NULL,
                MODIFY ip_isp_name varchar(100) DEFAULT NULL,
                MODIFY ip_domain varchar(100) DEFAULT NULL,
                MODIFY ip_netspeed varchar(100) DEFAULT NULL,
                MODIFY ip_mobile_mnc varchar(100) DEFAULT NULL,
                MODIFY ip_mobile_mcc varchar(100) DEFAULT NULL,
                MODIFY ip_mobile_brand varchar(100) DEFAULT NULL,
                MODIFY ip_elevation varchar(10) DEFAULT NULL,
                MODIFY ip_usage_type varchar(100) DEFAULT NULL,
                MODIFY is_proxy_ip_address char(1) DEFAULT NULL,
                MODIFY distance_in_km varchar(10) DEFAULT NULL,
                MODIFY distance_in_mile varchar(10) DEFAULT NULL,
                MODIFY is_country_match char(1) DEFAULT NULL,
                MODIFY is_address_ship_forward char(1) DEFAULT NULL,
                MODIFY is_bill_ship_city_match char(1) DEFAULT NULL,
                MODIFY is_bill_ship_state_match char(1) DEFAULT NULL,
                MODIFY is_bill_ship_country_match char(1) DEFAULT NULL,
                MODIFY is_bill_ship_postal_match char(1) DEFAULT NULL,
                MODIFY is_bill_ship_embargo char(1) DEFAULT NULL,
                MODIFY is_free_email char(1) DEFAULT NULL,
                MODIFY is_domain_exist char(1) DEFAULT NULL,
                MODIFY is_new_domain_name char(1) DEFAULT NULL,
                MODIFY is_bin_found char(1) DEFAULT NULL,
                MODIFY is_bin_country_match char(1) DEFAULT NULL,
                MODIFY is_bin_prepaid char(1) DEFAULT NULL,
                MODIFY is_ip_blacklist char(1) DEFAULT NULL,
                MODIFY is_email_blacklist char(1) DEFAULT NULL,
                MODIFY is_credit_card_blacklist char(1) DEFAULT NULL,
                MODIFY is_device_blacklist char(1) DEFAULT NULL,
                MODIFY is_user_blacklist char(1) DEFAULT NULL,
                MODIFY is_device_malware char(1) DEFAULT NULL,
                MODIFY is_ship_blacklist char(1) DEFAULT NULL,
                MODIFY is_phone_blacklist char(1) DEFAULT NULL,
                MODIFY is_user_high_risk char(1) DEFAULT NULL,
                MODIFY is_disposable_email char(1) DEFAULT NULL,
                MODIFY is_disposable_phone char(1) DEFAULT NULL,
                MODIFY cc_issuing_bank varchar(100) DEFAULT NULL,
                MODIFY cc_issuing_country char(2) DEFAULT NULL,
                MODIFY flp_id varchar(32) DEFAULT NULL,
                MODIFY flp_score char(3) DEFAULT NULL,
                MODIFY flp_status varchar(16) DEFAULT NULL,
                MODIFY flp_error_code varchar(16) DEFAULT NULL,
                MODIFY flp_message varchar(255) DEFAULT NULL,
                MODIFY flp_credits varchar(16) DEFAULT NULL,
                MODIFY api_key varchar(64) DEFAULT NULL,
                MODIFY flp_distribution varchar(16) DEFAULT NULL"
        );

        $this->executeInstallerSql("ALTER TABLE {$table} ADD PRIMARY KEY (`order_id`)");
    }
}
