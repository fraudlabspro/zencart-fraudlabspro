<?php

require_once __DIR__ . '/../../../../catalog/includes/classes/fraudlabspro/FraudLabsPro.php';

class zcObserverFraudlabsproSettings extends base
{
    private array $previous = [];

    public function __construct()
    {
        global $db;

        $this->attach($this, ['NOTIFY_ADMIN_CONFIG_CHANGE']);

        if (!defined('TABLE_CONFIGURATION')) {
            return;
        }

        $rows = $db->Execute(
            'SELECT configuration_key, configuration_value FROM ' . TABLE_CONFIGURATION
            . " WHERE configuration_key IN ('FRAUDLABSPRO_LICENSE', 'FRAUDLABSPRO_IP_OVERRIDE')"
        );

        foreach ($rows as $row) {
            $this->previous[$row['configuration_key']] = (string) $row['configuration_value'];
        }
    }

    public function update(&$callingClass, $notifier, $configurationKey)
    {
        global $db, $messageStack;

        if ($notifier !== 'NOTIFY_ADMIN_CONFIG_CHANGE' || !in_array($configurationKey, ['FRAUDLABSPRO_LICENSE', 'FRAUDLABSPRO_IP_OVERRIDE'], true)) {
            return;
        }

        $row = $db->Execute(
            'SELECT configuration_value FROM ' . TABLE_CONFIGURATION
            . " WHERE configuration_key = '" . $db->prepare_input($configurationKey) . "' LIMIT 1"
        );

        if ($row->EOF) {
            return;
        }

        $value = trim((string) $row->fields['configuration_value']);

        if ($value === '') {
            return;
        }

        if ($configurationKey === 'FRAUDLABSPRO_IP_OVERRIDE') {
            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return;
            }

            $this->restore($configurationKey);
            $messageStack->add_session(FRAUDLABS_IP_OVERRIDE_INVALID, 'error');

            return;
        }

        if (FraudLabsPro::validateLicense($value) !== false) {
            return;
        }

        $this->restore($configurationKey);
        $messageStack->add_session(FRAUDLABS_LICENSE_REJECTED, 'error');
    }

    private function restore(string $configurationKey): void
    {
        global $db;

        $db->Execute(
            'UPDATE ' . TABLE_CONFIGURATION
            . " SET configuration_value = '" . $db->prepare_input($this->previous[$configurationKey] ?? '') . "', last_modified = NOW()"
            . " WHERE configuration_key = '" . $db->prepare_input($configurationKey) . "'"
        );
    }
}
