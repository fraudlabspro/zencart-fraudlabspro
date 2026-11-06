<?php

require_once __DIR__ . '/../fraudlabspro/FraudLabsPro.php';

class zcObserverFraudlabsproProcess extends base
{
    public function __construct()
    {
        $this->attach($this, ['NOTIFY_CHECKOUT_PROCESS_BEFORE_CART_RESET']);
    }

    public function update(&$callingClass, $notifier, $orderNo)
    {
        global $db;

        if ($notifier !== 'NOTIFY_CHECKOUT_PROCESS_BEFORE_CART_RESET' || empty($orderNo)) {
            return;
        }

        if (!defined('FRAUDLABSPRO_ENABLED') || FRAUDLABSPRO_ENABLED !== 'true') {
            return;
        }

        $orderId = (int) $orderNo;
        $orderRow = $db->Execute('SELECT * FROM ' . TABLE_ORDERS . ' WHERE orders_id = ' . $orderId . ' LIMIT 1');

        if ($orderRow->EOF) {
            return;
        }

        $order = $orderRow->fields;
        $apiKey = defined('FRAUDLABSPRO_LICENSE') ? FRAUDLABSPRO_LICENSE : '';

        if ($apiKey === '') {
            return;
        }

        $customer = $db->Execute(
            'SELECT customers_firstname, customers_lastname FROM ' . TABLE_CUSTOMERS
            . ' WHERE customers_id = ' . (int) $order['customers_id'] . ' LIMIT 1'
        );

        $email = (string) ($order['customers_email_address'] ?? '');
        $emailAt = strpos($email, '@');
        $emailDomain = ($emailAt === false) ? '' : substr($email, $emailAt + 1);

        $shipName = trim((string) ($order['delivery_name'] ?? ''));
        $shipFirst = $shipName;
        $shipLast = '';
        $spacePos = strpos($shipName, ' ');

        if ($spacePos !== false) {
            $shipFirst = substr($shipName, 0, $spacePos);
            $shipLast = substr($shipName, $spacePos + 1);
        }

        $businessName = $order['billing_company'] ?? '';

        if ($businessName === '') {
            $businessName = $order['customers_company'] ?? '';
        }

        $cardDigits = preg_replace('/\D/', '', (string) ($order['cc_number'] ?? ''));
        $binNumber = strlen($cardDigits) >= 6 ? substr($cardDigits, 0, 6) : '';

        $quantity = 0;
        $products = $db->Execute(
            'SELECT products_quantity FROM ' . TABLE_ORDERS_PRODUCTS
            . ' WHERE orders_id = ' . $orderId
        );

        foreach ($products as $product) {
            $quantity += (int) $product['products_quantity'];
        }

        $fields = [
            'key' => $apiKey,
            'format' => 'json',
            'ip' => FraudLabsPro::clientIp(),
            'first_name' => $customer->fields['customers_firstname'] ?? '',
            'last_name' => $customer->fields['customers_lastname'] ?? '',
            'bill_to' => $businessName,
            'bill_addr' => $order['billing_street_address'] ?? '',
            'bill_city' => $order['billing_city'] ?? '',
            'bill_state' => $order['billing_state'] ?? '',
            'bill_zip_code' => $order['billing_postcode'] ?? '',
            'bill_country' => $this->countryCode($order['billing_country'] ?? ''),
            'ship_first_name' => $shipFirst,
            'ship_last_name' => $shipLast,
            'ship_addr' => $order['delivery_street_address'] ?? '',
            'ship_city' => $order['delivery_city'] ?? '',
            'ship_state' => $order['delivery_state'] ?? '',
            'ship_zip_code' => $order['delivery_postcode'] ?? '',
            'ship_country' => $this->countryCode($order['delivery_country'] ?? ''),
            'ship_method' => $this->shipMethod($order),
            'user_phone' => $order['customers_telephone'] ?? '',
            'email' => $email,
            'email_domain' => $emailDomain,
            'email_hash' => FraudLabsPro::hash($email),
            'user_order_id' => $orderId,
            'amount' => $this->formatAmount($order),
            'quantity' => $quantity,
            'currency' => $order['currency'] ?? '',
            'department' => STORE_NAME,
            'payment_gateway' => $order['payment_module_code'] ?? '',
            'payment_mode' => $this->paymentMode($order['payment_module_code'] ?? ''),
            'bin_no' => $binNumber,
            'flp_checksum' => $_COOKIE['flp_checksum'] ?? '',
            'username' => $email,
            'coupon_code' => $order['coupon_code'] ?? '',
            'user_order_memo' => '',
            'source' => 'zencart',
            'source_version' => FraudLabsPro::VERSION,
        ];

        $json = FraudLabsPro::screen($fields);

        if ($json === null) {
            return;
        }

        $geo = $json->ip_geolocation ?? null;
        $bill = $json->billing_address ?? null;
        $ship = $json->shipping_address ?? null;
        $mail = $json->email_address ?? null;
        $card = $json->credit_card ?? null;
        $device = $json->device ?? null;
        $user = $json->username ?? null;
        $phone = $json->phone_number ?? null;
        $error = $json->error ?? null;
        $usageType = $geo->usage_type ?? null;

        if (is_array($usageType)) {
            $usageType = implode(',', $usageType);
        }

        $sqlData = [
            'order_id' => $orderId,
            'api_key' => $apiKey,
            'ip_address' => $geo->ip ?? null,
            'ip_continent' => $geo->continent ?? null,
            'ip_country' => $geo->country_code ?? null,
            'ip_country_name' => $geo->country_name ?? null,
            'ip_region' => $geo->region ?? null,
            'ip_city' => $geo->city ?? null,
            'ip_latitude' => $geo->latitude ?? null,
            'ip_longitude' => $geo->longitude ?? null,
            'ip_zip' => $geo->zip_code ?? null,
            'ip_timezone' => $geo->timezone ?? null,
            'ip_isp_name' => $geo->isp_name ?? null,
            'ip_domain' => $geo->domain ?? null,
            'ip_netspeed' => $geo->netspeed ?? null,
            'ip_mobile_mnc' => $geo->mobile_mnc ?? null,
            'ip_mobile_mcc' => $geo->mobile_mcc ?? null,
            'ip_mobile_brand' => $geo->mobile_brand ?? null,
            'ip_elevation' => $geo->elevation ?? null,
            'ip_usage_type' => $usageType,
            'is_proxy_ip_address' => FraudLabsPro::flag($geo->is_proxy ?? null),
            'distance_in_km' => $bill->ip_distance_in_km ?? null,
            'distance_in_mile' => $bill->ip_distance_in_mile ?? null,
            'is_country_match' => FraudLabsPro::flag($bill->is_ip_country_match ?? null),
            'is_address_ship_forward' => FraudLabsPro::flag($ship->is_address_ship_forward ?? null),
            'is_bill_ship_city_match' => FraudLabsPro::flag($ship->is_bill_city_match ?? null),
            'is_bill_ship_state_match' => FraudLabsPro::flag($ship->is_bill_state_match ?? null),
            'is_bill_ship_country_match' => FraudLabsPro::flag($ship->is_bill_country_match ?? null),
            'is_bill_ship_postal_match' => FraudLabsPro::flag($ship->is_bill_postcode_match ?? null),
            'is_bill_ship_embargo' => FraudLabsPro::flag($ship->is_export_controlled_country ?? null),
            'is_free_email' => FraudLabsPro::flag($mail->is_free ?? null),
            'is_domain_exist' => FraudLabsPro::flag($mail->is_domain_exist ?? null),
            'is_disposable_email' => FraudLabsPro::flag($mail->is_disposable ?? null),
            'is_new_domain_name' => FraudLabsPro::flag($mail->is_new_domain_name ?? null),
            'is_disposable_phone' => FraudLabsPro::flag($phone->is_disposable ?? null),
            'is_bin_found' => FraudLabsPro::flag($card->is_bin_exist ?? null),
            'is_bin_country_match' => FraudLabsPro::flag($card->is_bin_country_match ?? null),
            'is_bin_prepaid' => FraudLabsPro::flag($card->is_prepaid ?? null),
            'cc_issuing_bank' => $card->card_issuing_bank ?? null,
            'cc_issuing_country' => $card->card_issuing_country ?? null,
            'is_ip_blacklist' => FraudLabsPro::flag($geo->is_in_blacklist ?? null),
            'is_email_blacklist' => FraudLabsPro::flag($mail->is_in_blacklist ?? null),
            'is_credit_card_blacklist' => FraudLabsPro::flag($card->is_in_blacklist ?? null),
            'is_device_blacklist' => FraudLabsPro::flag($device->is_in_blacklist ?? null),
            'is_user_blacklist' => FraudLabsPro::flag($user->is_in_blacklist ?? null),
            'is_user_high_risk' => FraudLabsPro::flag($user->is_high_risk ?? null),
            'is_phone_blacklist' => FraudLabsPro::flag($phone->is_in_blacklist ?? null),
            'is_device_malware' => FraudLabsPro::flag($device->is_malware_exploit ?? null),
            'is_ship_blacklist' => FraudLabsPro::flag($ship->is_in_blacklist ?? null),
            'flp_id' => $json->fraudlabspro_id ?? null,
            'flp_score' => $json->fraudlabspro_score ?? null,
            'flp_status' => $json->fraudlabspro_status ?? null,
            'flp_error_code' => $error->error_code ?? null,
            'flp_message' => $error->error_message ?? null,
            'flp_credits' => $json->remaining_credits ?? null,
            'flp_distribution' => FraudLabsPro::VERSION,
        ];

        zen_db_perform(TABLE_ORDERS_FRAUDLABSPRO, $sqlData);
    }

    private function countryCode(string $country): string
    {
        global $db;

        $country = trim($country);

        if (strlen($country) === 2) {
            return strtoupper($country);
        }

        if ($country === '') {
            return '';
        }

        $record = $db->Execute(
            'SELECT countries_iso_code_2 FROM ' . TABLE_COUNTRIES
            . " WHERE countries_name = '" . $db->prepare_input($country) . "' LIMIT 1"
        );

        return $record->EOF ? '' : $record->fields['countries_iso_code_2'];
    }

    private function shipMethod(array $order): string
    {
        $haystack = strtolower(
            (string) ($order['shipping_module_code'] ?? '') . ' ' . (string) ($order['shipping_method'] ?? '')
        );

        foreach (['pickup', 'pick-up', 'pick up', 'collect'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return 'local_pickup';
            }
        }

        return 'standard_delivery';
    }

    private function formatAmount(array $order): string
    {
        global $db;

        $currency = $db->Execute(
            'SELECT decimal_places FROM ' . TABLE_CURRENCIES
            . " WHERE code = '" . $db->prepare_input((string) ($order['currency'] ?? '')) . "' LIMIT 1"
        );

        $decimals = $currency->EOF ? 2 : (int) $currency->fields['decimal_places'];

        return number_format((float) ($order['order_total'] ?? 0), $decimals, '.', '');
    }

    private function paymentMode(string $moduleCode): string
    {
        switch ($moduleCode) {
            case 'moneyorder':
                return 'moneyorder';

            case 'coinbasepp':
            case 'plisio':
                return 'bitcoin';

            case 'paypal':
            case 'paypaldp':
            case 'paypalwpp':
            case 'paypalr':
                return 'paypal';

            case 'cod':
            case 'purchaseorder':
                return 'cod';

            case 'authorizenet':
            case 'qbms':
            case 'authorizenet_aim':
            case 'linkpoint_api':
            case 'square':
            case 'square_webpay':
            case 'stripe':
                return 'creditcard';

            default:
                return 'others';
        }
    }
}
