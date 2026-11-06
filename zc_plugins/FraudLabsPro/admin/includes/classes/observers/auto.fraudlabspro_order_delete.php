<?php

class zcObserverFraudlabsproOrderDelete extends base
{
    public function __construct()
    {
        $this->attach($this, ['NOTIFIER_ADMIN_ZEN_REMOVE_ORDER']);
    }

    public function update(&$callingClass, $notifier, $paramsArray, $orderNo)
    {
        global $db;

        if ($notifier !== 'NOTIFIER_ADMIN_ZEN_REMOVE_ORDER') {
            return;
        }

        $orderId = (int) $orderNo;

        if ($orderId === 0) {
            return;
        }

        $db->Execute('DELETE FROM ' . TABLE_ORDERS_FRAUDLABSPRO . ' WHERE order_id = ' . $orderId);
    }
}
