<?php
require_once __DIR__ . '/../../../../catalog/includes/classes/fraudlabspro/FraudLabsPro.php';

if (!function_exists('flp_result')) {
    function flp_result($value): string
    {
        return FraudLabsPro::result($value, FRAUDLABS_YES, FRAUDLABS_NO, FRAUDLABS_NA);
    }
}

if (!defined('FRAUDLABSPRO_ENABLED') || FRAUDLABSPRO_ENABLED !== 'true' || !isset($oID)) {
    return;
}

$oID = (int) $oID;

$check_fraudlabspro = $db->Execute(
    'SELECT * FROM ' . TABLE_ORDERS_FRAUDLABSPRO . ' WHERE order_id = ' . $oID . ' LIMIT 1'
);

if ($check_fraudlabspro->EOF) {
    return;
}

$flp = $check_fraudlabspro->fields;

if (isset($_POST['flp_result'], $_POST['flp_id'])) {
    $feedbackAction = (int) $_POST['flp_result'];
    $transactionId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $_POST['flp_id']);
    $notes = zen_db_prepare_input(is_string($_POST['notes'] ?? '') ? $_POST['notes'] : '');

    if (in_array($feedbackAction, [1, 2, 3], true) && $transactionId !== '') {
        $apiKey = $flp['api_key'] ?? '';

        if ($apiKey === '' && defined('FRAUDLABSPRO_LICENSE')) {
            $apiKey = FRAUDLABSPRO_LICENSE;
        }

        $payload = [
            'key' => $apiKey,
            'id' => $transactionId,
            'format' => 'json',
            'source' => 'zencart',
        ];

        switch ($feedbackAction) {
            case 1:
                $payload['action'] = 'APPROVE';
                $newStatus = 'APPROVE';
                break;

            case 2:
                $payload['action'] = 'REJECT';
                $newStatus = 'REJECT';
                break;

            default:
                $payload['action'] = 'REJECT_BLACKLIST';
                $payload['note'] = $notes;
                $newStatus = 'REJECT';
                break;
        }

        $response = FraudLabsPro::feedback($payload);

        if ($response !== null && empty($response->error)) {
            zen_db_perform(
                TABLE_ORDERS_FRAUDLABSPRO,
                ['flp_status' => $newStatus],
                'update',
                'order_id = ' . $oID
            );

            $flp['flp_status'] = $newStatus;
        }
    }
}

switch ($flp['flp_status']) {
    case 'REVIEW':
        $flp_status = '<span style="font-size:1.2em;font-weight:bold;color:#FFA500">Review</span>';
        break;

    case 'REJECT':
        $flp_status = '<span style="font-size:1.2em;font-weight:bold;color:#f00">Reject</span>';
        break;

    case 'APPROVE':
        $flp_status = '<span style="font-size:1.2em;font-weight:bold;color:#336600">Approve</span>';
        break;

    default:
        $flp_status = '-';
}

$location = [$flp['ip_city'], $flp['ip_region'], $flp['ip_country_name'], $flp['ip_continent']];
$location = array_filter($location, function ($value) {
    return trim((string) $value) !== '';
});
$location_str = implode(', ', $location);

$flpId = (string) ($flp['flp_id'] ?? '');
$flpScore = (string) ($flp['flp_score'] ?? '');
$ipAddress = (string) ($flp['ip_address'] ?? '');
$ipCountry = (string) ($flp['ip_country'] ?? '');
$ccCountry = (string) ($flp['cc_issuing_country'] ?? '');
?>
<style>
    .flp-panel { margin: 24px 0; }
    .flp-panel > tbody > tr > th { background-color: #AB1B1C; padding: 10px 14px; }
    .flp-panel > tbody > tr > td { padding: 10px 12px; vertical-align: top; }
    .flp-panel .flp-side { width: 20%; padding: 24px 16px; text-align: center; vertical-align: middle; }
    .flp-panel .flp-side img { margin-bottom: 8px; }
    .flp-panel .flp-side p { margin: 0 0 14px; }
    .flp-panel .flp-side .form-group { margin: 0 0 14px; }
    .flp-panel .flp-side .btn { margin: 3px; }
    .flp-panel .flp-meta { margin-top: 12px; line-height: 1.7; color: #555; }
    .flp-panel .nav-tabs { border-bottom: 0; }
    .flp-panel .nav-tabs > li > a { padding: 8px 14px; }
    .flp-panel .tab-content { border: 1px solid #CCC; border-top: 0; padding: 4px 12px 8px; }
    .flp-panel .tab-content .table { margin-bottom: 0; }
    .flp-panel .tab-content .table > tbody > tr > th,
    .flp-panel .tab-content .table > tbody > tr > td { padding: 10px 12px; border-top: 1px solid #EEE; vertical-align: top; line-height: 1.5; }
    .flp-panel .tab-content .table > tbody > tr:first-child > th,
    .flp-panel .tab-content .table > tbody > tr:first-child > td { border-top: 0; }
    .flp-panel .tab-content .table > tbody > tr > th { font-weight: 600; color: #333; }
    .flp-panel .tab-content pre { margin: 0; padding: 12px; border: 0; background: transparent; }
</style>
<table class="table flp-panel">
    <tr>
        <th colspan="10">
            <a href="https://www.fraudlabspro.com/?r=zencart" target="_blank">
                <img src="https://cdn.fraudlabspro.com/assets/img/logo-200x36.png" width="163" height="20" border="0" align="absMiddle" />
            </a>
        </th>
    </tr>
    <tr>
        <td class="flp-side">
            <img src="https://fraudlabspro.hexa-soft.com/images/fraudscore/fraudlabsproscore<?= zen_output_string_protected($flpScore) ?>.png" width="110" height="86" border="0">
            <p><?= $flp_status ?></p>

            <p>
                <strong><?= FRAUDLABS_TRANSACTION_ID ?></strong><br>
                <a href="https://www.fraudlabspro.com/merchant/transaction-details/<?= rawurlencode($flpId) ?>" target="_blank"><?= zen_output_string_protected($flpId) ?></a>
            </p>

            <?php
            echo zen_draw_form('flp_feedback', FILENAME_ORDERS, zen_get_all_get_params(['action']) . 'action=edit', 'post', '', true);
            echo zen_draw_hidden_field('flp_result', '1');
            echo zen_draw_hidden_field('order_id', $oID);
            echo zen_draw_hidden_field('flp_id', $flpId);
            echo zen_draw_hidden_field('notes', '');

            echo '<div class="form-group">';

            if ($flp['flp_status'] === 'REVIEW' || $flp['flp_status'] === 'REJECT') {
                echo '<button id="btn-approve" class="btn btn-primary">' . FRAUDLABS_ACCEPT . '</button>';
            }

            if ($flp['flp_status'] === 'REVIEW' || $flp['flp_status'] === 'APPROVE') {
                echo '<button id="btn-reject" class="btn btn-primary">' . FRAUDLABS_REJECT . '</button>';
            }

            echo '<button id="btn-blacklist" class="btn btn-primary">' . FRAUDLABS_BLACKLIST . '</button>';
            echo '</div>';
            echo '</form>';
            ?>

            <script>
                $(function() {
                    $('#btn-approve').on('click', function(e) {
                        e.preventDefault();

                        if (confirm('Confirm to approve this order?')) {
                            $('input[name=flp_result]').val(1);
                            $('form[name=flp_feedback]').submit();
                        }
                    });

                    $('#btn-reject').on('click', function(e) {
                        e.preventDefault();

                        if (confirm('Confirm to reject this order?')) {
                            $('input[name=flp_result]').val(2);
                            $('form[name=flp_feedback]').submit();
                        }
                    });

                    $('#btn-blacklist').on('click', function(e) {
                        e.preventDefault();

                        var notes = prompt('Please enter the reason(s) for blacklisting this order. (Optional)');

                        if (notes === null) {
                            return;
                        }

                        $('input[name=flp_result]').val(3);
                        $('input[name=notes]').val(notes);
                        $('form[name=flp_feedback]').submit();
                    });
                });
            </script>
            <div class="flp-meta">
                <small><?= FRAUDLABS_REMAINING ?>: <strong><?= zen_output_string_protected($flp['flp_credits'] ?? '') ?></strong> <?= FRAUDLABS_REMAIN_AFTER ?></small><br>
                <small><?= FRAUDLABS_VERSION ?>: <strong><?= zen_output_string_protected($flp['flp_distribution'] ?? '') ?></strong></small>
            </div>
        </td>
        <td>
            <ul class="nav nav-tabs">
                <li class="active"><a data-toggle="tab" href="#ip-geo"><?= FRAUDLABS_HEADING_IP ?></a></li>
                <li><a data-toggle="tab" href="#email-validation"><?= FRAUDLABS_HEADING_EMAIL ?></a></li>
                <li><a data-toggle="tab" href="#cc-validation"><?= FRAUDLABS_HEADING_CC ?></a></li>
                <li><a data-toggle="tab" href="#billing-validation"><?= FRAUDLABS_HEADING_BILL ?></a></li>
                <li><a data-toggle="tab" href="#blacklist-validation"><?= FRAUDLABS_HEADING_BLACKLIST ?></a></li>
                <?php if (zen_not_null($flp['flp_message']) && zen_not_null($flp['flp_error_code'])) { ?>
                    <li><a data-toggle="tab" href="#flp-error"><?= FRAUDLABS_HEADING_ERROR ?></a></li>
                <?php } ?>
            </ul>

            <div class="tab-content">
                <div id="ip-geo" class="tab-pane fade in active">
                    <table class="table" style="margin-bottom: 0px;">
                        <tr>
                            <th style="width: 15%;border-top:none;"><?= FRAUDLABS_IP_CLIENT ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_CLIENT ?>"></i></th>
                            <td style="width: 35%;border-top:none;"><?= flp_result($ipAddress) ?>&nbsp;&nbsp;&nbsp;<?php if (zen_not_null($ipAddress)) { ?><button class="btn btn-xs btn-default" onclick="window.open('https://geolocation.com/<?= rawurlencode($ipAddress) ?>','_blank')">IP Details</button><?php } ?></td>
                            <th style="width: 15%;border-top:none;"><?= FRAUDLABS_ISP_DOMAIN ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_DOMAIN ?>"></i></th>
                            <td style="width: 35%;border-top:none;"><?= flp_result($flp['ip_domain'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_IP_LOCATION ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_LOCATION ?>"></i></th>
                            <td style="width: 35%;">
                                <?= flp_result($location_str) ?>
                                <?php if ($ipCountry !== '') { ?>
                                    <img src="https://flagcdn.com/h20/<?= rawurlencode(strtolower($ipCountry)) ?>.png" alt="<?= zen_output_string_protected($ipCountry) ?>">
                                <?php } ?>
                                <br>
                                <?php if (zen_not_null($flp['ip_latitude'] ?? '') && zen_not_null($flp['ip_longitude'] ?? '')) { ?>
                                    <small><?= zen_output_string_protected($flp['ip_latitude']) ?>, <?= zen_output_string_protected($flp['ip_longitude']) ?></small>
                                <?php } ?>
                            </td>
                            <th style="width: 15%;"><?= FRAUDLABS_ISP_SPEED ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_NETSPEED ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['ip_netspeed'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_ISP ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_ISP ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['ip_isp_name'] ?? '') ?></td>
                            <th style="width: 15%;"><?= FRAUDLABS_IP_USAGE ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_USAGE ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['ip_usage_type'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_TIMEZONE ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_TIMEZONE ?>"></i></th>
                            <td style="width: 35%;" colspan="3"><?= flp_result($flp['ip_timezone'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_IP_ELEVATION ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_ELEVATION ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['ip_elevation'] ?? '') ?></td>
                            <th style="width: 15%;"><?= FRAUDLABS_DISTANCE ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_DISTANCE ?>"></i></th>
                            <td style="width: 35%;">
                                <?php if (zen_not_null($flp['distance_in_mile'] ?? '') && zen_not_null($flp['distance_in_km'] ?? '')) { ?>
                                    <?= zen_output_string_protected($flp['distance_in_mile']) ?> <?= FRAUDLABS_DISTANCE_MI_ABBR ?> / <?= zen_output_string_protected($flp['distance_in_km']) ?> <?= FRAUDLABS_DISTANCE_KM_ABBR ?>
                                <?php } else { ?>
                                    <em>- <?= FRAUDLABS_NA ?> -</em>
                                <?php } ?>
                            </td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_MOBILE_MNC . '/' . FRAUDLABS_MOBILE_MCC ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_MMNC ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['ip_mobile_mnc'] ?? '') ?> / <?= flp_result($flp['ip_mobile_mcc'] ?? '') ?></td>
                            <th style="width: 15%;"><?= FRAUDLABS_MOBILE_BRAND ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_MBRAND ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['ip_mobile_brand'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>

                <div id="email-validation" class="tab-pane fade">
                    <table class="table" style="margin-bottom: 0px;">
                        <tr>
                            <th style="width: 15%;border-top:none;"><?= FRAUDLABS_EMAIL_FREE ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_EMAIL_FREE ?>"></i></th>
                            <td style="width: 35%;border-top:none;"><?= flp_result($flp['is_free_email'] ?? '') ?></td>
                            <th style="width: 15%;border-top:none;"><?= FRAUDLABS_EMAIL_DOMAIN_NEW ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_EMAIL_NEW_DOMAIN ?>"></i></th>
                            <td style="width: 35%;border-top:none;"><?= flp_result($flp['is_new_domain_name'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_DISPOSABLE_EMAIL ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_EMAIL_DISPOSABLE ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['is_disposable_email'] ?? '') ?></td>
                            <th style="width: 15%;"><?= FRAUDLABS_DISPOSABLE_PHONE ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_PHONE_DISPOSABLE ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['is_disposable_phone'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_IP_PROXY ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_IP_PROXY ?>"></i></th>
                            <td style="width: 35%;" colspan="3"><?= flp_result($flp['is_proxy_ip_address'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_SHIP_FORWARD ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_SHIP_FORWARD ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['is_address_ship_forward'] ?? '') ?></td>
                            <th style="width: 15%;"><?= FRAUDLABS_SHIP_EMBARGO ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_EMBARGO ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['is_bill_ship_embargo'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 15%;"><?= FRAUDLABS_DOMAIN_EMAIL_EXISTS ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_EMAIL_DOMAIN_EXIST ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['is_domain_exist'] ?? '') ?></td>
                            <th style="width: 15%;"><?= FRAUDLABS_MALWARE ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_MALWARE ?>"></i></th>
                            <td style="width: 35%;"><?= flp_result($flp['is_device_malware'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>

                <div id="cc-validation" class="tab-pane fade">
                    <table class="table" style="margin-bottom: 0px;">
                        <tr>
                            <th style="width: 20%;border-top:none;"><?= FRAUDLABS_BIN_FOUND ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_FOUND_BIN_EXPLAIN ?>"></i></th>
                            <td style="width: 80%;border-top:none;"><?= flp_result($flp['is_bin_found'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 20%;"><?= FRAUDLABS_BIN_MATCH ?></th>
                            <td style="width: 80%;"><?= flp_result($flp['is_bin_country_match'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 20%;"><?= FRAUDLABS_BIN_PREPAID ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_CC_PREPAID ?>"></i></th>
                            <td style="width: 80%;" colspan="3"><?= flp_result($flp['is_bin_prepaid'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 20%;"><?= FRAUDLABS_CC_ISSUER ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_CC_ISSUER ?>"></i></th>
                            <td style="width: 80%;" colspan="3"><?= flp_result($flp['cc_issuing_bank'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 20%;"><?= FRAUDLABS_CC_ISSUE_COUNTRY ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_CC_ISSUE_COUNTRY ?>"></i></th>
                            <td style="width: 80%;" colspan="3"><?php if ($ccCountry !== '') { ?><?= zen_output_string_protected(FraudLabsPro::countryName($ccCountry)) ?> (<?= zen_output_string_protected($ccCountry) ?>) <img src="https://flagcdn.com/w20/<?= rawurlencode(strtolower($ccCountry)) ?>.png" alt="<?= zen_output_string_protected($ccCountry) ?>"><?php } else { ?><em>- <?= FRAUDLABS_NA ?> -</em><?php } ?></td>
                        </tr>
                    </table>
                </div>

                <div id="billing-validation" class="tab-pane fade">
                    <table class="table" style="margin-bottom: 0px;">
                        <tr>
                            <th style="width: 25%;border-top:none;"><?= FRAUDLABS_BILLING_COUNTRY_MATCH_IP ?>&nbsp;&nbsp;<i class="fas fa-info-circle" title="<?= FRAUDLABS_EXPLAIN_BILLING_MATCH_IP ?>"></i></th>
                            <td style="width: 75%;border-top:none;"><?= flp_result($flp['is_country_match'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BILLING_CITY_MATCH_IP ?></th>
                            <td style="width: 75%;"><?= flp_result($flp['is_bill_ship_city_match'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BILLING_STATE_MATCH_IP ?></th>
                            <td style="width: 75%;"><?= flp_result($flp['is_bill_ship_state_match'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BILLING_COUNTRY_MATCH_SHIP ?></th>
                            <td style="width: 75%;"><?= flp_result($flp['is_bill_ship_country_match'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BILLING_ZIP_MATCH_SHIP ?></th>
                            <td style="width: 75%;" colspan="3"><?= flp_result($flp['is_bill_ship_postal_match'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>

                <div id="blacklist-validation" class="tab-pane fade">
                    <table class="table" style="margin-bottom: 0px;">
                        <tr>
                            <th style="width: 25%;border-top:none;"><?= FRAUDLABS_BLACKLIST_IP ?></th>
                            <td style="width: 75%;border-top:none;"><?= flp_result($flp['is_ip_blacklist'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BLACKLIST_EMAIL ?></th>
                            <td style="width: 75%;"><?= flp_result($flp['is_email_blacklist'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BLACKLIST_CC ?></th>
                            <td style="width: 75%;"><?= flp_result($flp['is_credit_card_blacklist'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BLACKLIST_DEVICE ?></th>
                            <td style="width: 75%;"><?= flp_result($flp['is_device_blacklist'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BLACKLIST_SHIP ?></th>
                            <td style="width: 75%;" colspan="3"><?= flp_result($flp['is_ship_blacklist'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th style="width: 25%;"><?= FRAUDLABS_BLACKLIST_PHONE ?></th>
                            <td style="width: 75%;" colspan="3"><?= flp_result($flp['is_phone_blacklist'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>

                <?php if (zen_not_null($flp['flp_message'] ?? '') && zen_not_null($flp['flp_error_code'] ?? '')) { ?>
                    <div id="flp-error" class="tab-pane fade">
                        <?php
                        $flp_error = $flp['flp_error_code'];
                        $flp_error_msg = $flp['flp_message'];
                        $flp_error_detail = '';

                        switch ($flp_error) {
                            case 10001:
                                $flp_error_detail = FRAUDLABS_ERR_API_KEY;
                                break;

                            case 10006:
                                $flp_error_detail = FRAUDLABS_ERR_API_EXPIRED;
                                break;

                            case 10007:
                                $flp_error_detail = FRAUDLABS_ERR_INSUFF_CREDIT;
                                break;

                            case 10003:
                                $flp_error_detail = FRAUDLABS_ERR_INV_BILLING_COUNTRY;
                                break;

                            case 10004:
                                $flp_error_detail = FRAUDLABS_ERR_INV_SHIPPING_COUNTRY;
                                break;

                            case 10002:
                                $flp_error_detail = FRAUDLABS_ERR_INV_IP_ADDRESS;
                                break;

                            case 204:
                                $flp_error_detail = FRAUDLABS_ERR_INV_EMAIL_HASH;
                                break;

                            case 10009:
                                $flp_error_detail = FRAUDLABS_ERR_QTY_TOO_LARGE;
                                break;

                            case 10010:
                                $flp_error_detail = FRAUDLABS_ERR_ORD_ID_TOO_BIG;
                                break;
                        }
                        ?>
                        <div id="error" class="tab-pane fade in active">
                            <pre style="margin-bottom: 0px;">
<?= FRAUDLABS_ERR_ERRNUMBER . ' ' . zen_output_string_protected($flp_error) . "\n" ?>
<?= FRAUDLABS_ERR_ERRMSG . ' ' . zen_output_string_protected($flp_error_msg) . "\n\n" ?>
<?= zen_output_string_protected($flp_error_detail) ?></pre>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </td>
    </tr>
</table>
