<?php

class zcObserverFraudlabsproAdminOrder extends base
{
    public function __construct()
    {
        $this->attach($this, ['NOTIFY_ADMIN_ORDERS_AFTER_STATUS_LISTING']);
    }

    public function update(&$callingClass, $notifier, $orderId, &$additionalContent)
    {
        global $db;

        if ($notifier !== 'NOTIFY_ADMIN_ORDERS_AFTER_STATUS_LISTING' || empty($orderId)) {
            return;
        }

        $oID = (int) $orderId;

        ob_start();
        include __DIR__ . '/../../modules/fraudlabspro/summary.php';
        $output = ob_get_clean();

        if ($output !== '') {
            $additionalContent = ($additionalContent === false ? '' : (string) $additionalContent)
                . '<div class="row noprint">' . $output . '</div>';
        }
    }
}
