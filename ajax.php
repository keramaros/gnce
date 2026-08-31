<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_gnce_handle_1nce_search', 'gnce_handle_1nce_search');
add_action('wp_ajax_nopriv_gnce_handle_1nce_search', 'gnce_handle_1nce_search');

function gnce_handle_1nce_search()
{
    try {
        $iccid = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        if (strlen($iccid) < 18) {
            wp_send_json_success('<div class="gnce-error">' . __("ICCID should be at least 19 character long.", 'gnce-1nce-products') . '</div>');
            return;
        }

        $transient_key = GNCE_TRANSIENT_SIM_ICCID . "_" . $iccid;
        $sims = get_transient($transient_key);

        $clientId = get_option('gnce_once_api_client_id');
        $clientSecret = get_option('gnce_once_api_secret');
        $client = new GNCE_API_Client($clientId, $clientSecret);
        if (false === $sims) {
            $sims = $client->gnce_get_sims();
            set_transient($transient_key, $sims, GNCE_TRANSIENT_SIM_ICCID_LIFETIME);
        }

        foreach ($sims as $sim) {
            if ($sim['iccid'] !== $iccid) continue;
            $status = $sim['status'] ?? __('N/A', 'gnce-1nce-products');

            $quota = $client->gnce_get_sim_data_quota($iccid);
            $sms_quota = $client->gnce_get_sim_sms_quota($iccid);

            // The API returns remaining volume in 'remainingVolume' field for the quota endpoint
            $remainingMb = $quota['remainingVolume'] ?? $quota['volume'] ?? $quota['remaining_volume'] ?? __('Unknown', 'gnce-1nce-products');
            $remainingSms = $sms_quota['remainingVolume'] ?? $sms_quota['volume'] ?? $sms_quota['remaining_volume'] ?? __('Unknown', 'gnce-1nce-products');

            if (is_numeric($remainingMb)) {
                $remainingMb = round($remainingMb, 2) . ' ' . __('MB', 'gnce-1nce-products');
            }

            $message = sprintf(
                '<div class="gnce-results-list">
                    <span class="gnce-result-item"><span class="gnce-result-label">%s:</span> %s</span>
                    <span class="gnce-result-item"><span class="gnce-result-label">%s:</span> %s</span>
                    <span class="gnce-result-item"><span class="gnce-result-label">%s:</span> %s</span>
                </div>',
                __('Status', 'gnce-1nce-products'),
                esc_html($status),
                __('Remaining Data', 'gnce-1nce-products'),
                esc_html($remainingMb),
                __('Remaining SMS', 'gnce-1nce-products'),
                esc_html($remainingSms)
            );
            wp_send_json_success($message);
            return;
        }

        $response_text = __("ICCID not found.", 'gnce-1nce-products');
        wp_send_json_success('<div class="gnce-error">' . $response_text . " ($iccid)</div>");
    } catch (Exception $e) {
        wp_send_json_error('<div class="gnce-error">' . $e->getMessage() . '</div>');
        return;
    }
}
