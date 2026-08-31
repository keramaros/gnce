<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. Display the ICCID input field on products that require it
 */
add_action('woocommerce_before_add_to_cart_button', 'gnce_add_iccid_field_to_product_page');
function gnce_add_iccid_field_to_product_page()
{
    global $product;

    if (get_post_meta($product->get_id(), '_requires_1nce_iccid', true) !== 'yes') {
        return;
    }

    echo '<div class="iccid-field-wrapper" style="margin-bottom: 20px;">';
    echo '<label for="sim_iccid"><strong>' . __('SIM ICCID:', 'gnce-1nce-products') . '</strong> <abbr class="required" title="' . esc_attr__('required', 'gnce-1nce-products') . '">*</abbr></label><br>';
    echo '<div class="search-input-wrapper" style="margin-top: 10px;">';
    echo '<input type="text" id="sim_iccid" name="sim_iccid" placeholder="' . esc_attr__('Enter 19-digit ICCID', 'gnce-1nce-products') . '" required style="width: 100%; max-width: 300px; margin-bottom: 0;" />';
    echo '<button type="button" id="search_iccid_btn" class="button">' . __('Search', 'gnce-1nce-products') . '</button>';
    echo '</div>';
    echo '<img src="/wp-admin/images/spinner-2x.gif" id="iccid_spinner" class="api-spinner" alt="Loading..." style="display:none; margin: 10px 0;"/>';
    echo '<div id="iccid_status_message"></div>';
    echo '</div>';
}

add_action('wp_footer', 'gnce_add_iccid_search_js');
function gnce_add_iccid_search_js()
{
    if (!is_product()) return;

    global $product;
    if (!$product || get_post_meta($product->get_id(), '_requires_1nce_iccid', true) !== 'yes') {
        return;
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('#search_iccid_btn').on('click', function (e) {
                e.preventDefault();
                var iccid = $('#sim_iccid').val();
                var $message = $('#iccid_status_message');
                var $spinner = $('#iccid_spinner');
                var $button = $(this);

                if (iccid.length < 1) {
                    $message.html('<span style="color: red;"><?php _e('Please enter an ICCID.', 'gnce-1nce-products'); ?></span>');
                    return;
                }

                $spinner.show();
                $button.prop('disabled', true);
                $message.hide().html('');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'gnce_handle_1nce_search',
                        query: iccid
                    },
                    success: function (response) {
                        $spinner.hide();
                        $button.prop('disabled', false);
                        if (response.success) {
                            $message.show().html(response.data);
                        } else {
                            $message.show().html('<div class="gnce-error">' + response.data + '</div>');
                        }
                    },
                    error: function () {
                        $spinner.hide();
                        $button.prop('disabled', false);
                        $message.show().html('<div class="gnce-error"><?php _e('An error occurred while searching.', 'gnce-1nce-products'); ?></div>');
                    }
                });
            });
        });
    </script>
    <?php
}

/**
 * 2. Validate ICCID server-side during add-to-cart action
 */
add_filter('woocommerce_add_to_cart_validation', 'gnce_validate_iccid_on_add_to_cart', 10, 3);
function gnce_validate_iccid_on_add_to_cart($passed, $product_id, $quantity)
{
// Only apply validation for products that require it
    if (get_post_meta($product_id, '_requires_1nce_iccid', true) === 'yes') {
        if ($quantity > 1) {
            wc_add_notice(__('The quantity for this product cannot be more than one.', 'gnce-1nce-products'), 'error');
            return false;
        }

        if (empty($_POST['sim_iccid'])) {
            wc_add_notice(__('Please enter a SIM ICCID.', 'gnce-1nce-products'), 'error');
            return false;
        }

        $iccid = sanitize_text_field($_POST['sim_iccid']);

        // Check if this ICCID is already in the cart
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['sim_iccid']) && $cart_item['sim_iccid'] === $iccid) {
                wc_add_notice(__('This SIM ICCID is already in your cart.', 'gnce-1nce-products'), 'error');
                return false;
            }
        }

        $is_valid = gnce_verify_1nce_iccid($iccid);

        if (!$is_valid) {
            wc_add_notice(__('The entered SIM ICCID is invalid or could not be verified with 1NCE.', 'gnce-1nce-products'), 'error');
            return false;
        }
    }

    return $passed;
}

/**
 * 3. Verify ICCID length and format, throwing notifications on failure
 */
function gnce_verify_1nce_iccid($iccid)
{
    // Check if it contains only numbers and is between 19 and 20 digits long
    if (!preg_match('/^\d{19,20}$/', $iccid)) {
        wc_add_notice(__('Invalid ICCID format. A 1NCE SIM ICCID must be 19 to 20 digits long containing numbers only.', 'gnce-1nce-products'), 'error');
        return false;
    }

    // Call 1NCE API REST call verification
    try {
        $transient_key = GNCE_TRANSIENT_SIM_ICCID . "_" . $iccid;
        $sims = get_transient($transient_key);

        if (false === $sims) {
            $clientId = get_option('gnce_once_api_client_id');
            $clientSecret = get_option('gnce_once_api_secret');
            $client = new GNCE_API_Client($clientId, $clientSecret);
            $sims = $client->gnce_get_sims();
            set_transient($transient_key, $sims, GNCE_TRANSIENT_SIM_ICCID_LIFETIME);
        }

        $found = false;
        foreach ($sims as $sim) {
            if ($sim['iccid'] === $iccid) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

    } catch (Exception $e) {
        return false;
    }

    return true;
}

/**
 * 4. Save the ICCID into cart item data
 */
add_filter('woocommerce_add_cart_item_data', 'gnce_save_iccid_into_cart_item', 10, 3);
function gnce_save_iccid_into_cart_item($cart_item_data, $product_id, $variation_id)
{
    if (get_post_meta($product_id, '_requires_1nce_iccid', true) === 'yes' && isset($_POST['sim_iccid'])) {
        $cart_item_data['sim_iccid'] = sanitize_text_field($_POST['sim_iccid']);
        $cart_item_data['unique_key'] = md5(microtime() . rand());
    }
    return $cart_item_data;
}

/**
 * 5. Display the ICCID in Cart/Checkout and save to Order Meta
 */
add_filter('woocommerce_get_item_data', 'gnce_display_iccid_in_cart', 10, 2);
function gnce_display_iccid_in_cart($item_data, $cart_item)
{
    if (isset($cart_item['sim_iccid'])) {
        $item_data[] = array(
                'key' => __('SIM ICCID', 'gnce-1nce-products'),
                'value' => sanitize_text_field($cart_item['sim_iccid']),
        );
    }
    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'gnce_save_iccid_to_order_line_item', 10, 4);
function gnce_save_iccid_to_order_line_item($item, $cart_item_key, $values, $order)
{
    if (isset($values['sim_iccid'])) {
        $item->add_meta_data('_sim_iccid', $values['sim_iccid']);
    }
}

/**
 * 6. Renew SIM Data Quota when order payment is complete
 */
add_action('woocommerce_payment_complete', 'gnce_renew_quota_on_payment_complete');
function gnce_renew_quota_on_payment_complete($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $clientId = get_option('gnce_once_api_client_id');
    $clientSecret = get_option('gnce_once_api_secret');
    $paymentMethod = get_option('gnce_once_api_payment_method', 'creditcard');
    $client = new GNCE_API_Client($clientId, $clientSecret);

    foreach ($order->get_items() as $item_id => $item) {
        $iccid = $item->get_meta('_sim_iccid');
        if ($iccid) {
            try {
                // Fetch and record current quota before renewal
                try {
                    $data_quota = $client->gnce_get_sim_data_quota($iccid);
                    $remaining_data_before = $data_quota['remainingVolume'] ?? $data_quota['volume'] ?? $data_quota['remaining_volume'] ?? 0;

                    $sms_quota = $client->gnce_get_sim_sms_quota($iccid);
                    $remaining_sms_before = $sms_quota['remainingVolume'] ?? $sms_quota['volume'] ?? $sms_quota['remaining_sms'] ?? 0;

                    $order->add_order_note(sprintf(__('Current Quota for ICCID %s before renewal: %s MB, %s SMS', 'gnce-1nce-products'), $iccid, $remaining_data_before, $remaining_sms_before));
                } catch (Exception $e_quota) {
                    $order->add_order_note(sprintf(__('Could not fetch current quota for ICCID %s before renewal.', 'gnce-1nce-products'), $iccid));
                }

                $message = $client->gnce_top_up_sim_quota($iccid, $paymentMethod);
                $comment = sprintf(__('1NCE Quota Renewal for ICCID %s: %s', 'gnce-1nce-products'), $iccid, $message);
                $order->add_order_note($comment);

                // Schedule verification
                $interval_value = get_option('gnce_quota_verification_interval', '1');
                if ($interval_value !== 'never') {
                    $interval_hours = (int)$interval_value;
                    wp_schedule_single_event(time() + ($interval_hours * HOUR_IN_SECONDS), 'gnce_verify_quota_after_renewal', [$order_id, $iccid]);
                    $order->add_order_note(sprintf(__('Scheduled verification of quota renewal in %d hour(s).', 'gnce-1nce-products'), $interval_hours));
                }

            } catch (Exception $e) {
                $order->add_order_note(sprintf(__('1NCE Quota Renewal Error for ICCID %s: %s', 'gnce-1nce-products'), $iccid, $e->getMessage()));
            }

            // Clear the related transient
            delete_transient(GNCE_TRANSIENT_SIM_ICCID . "_" . $iccid);
        }
    }
}

/**
 * 7. Force quantity to 1 for SIM ICCID products
 */
add_filter('woocommerce_is_sold_individually', 'gnce_iccid_product_is_sold_individually', 10, 2);
function gnce_iccid_product_is_sold_individually($individually, $product)
{
    if (get_post_meta($product->get_id(), '_requires_1nce_iccid', true) === 'yes') {
        return true;
    }
    return $individually;
}

/**
 * 8. Remove quantity selector in cart for SIM ICCID products
 */
add_filter('woocommerce_cart_item_quantity', 'gnce_iccid_cart_item_quantity', 10, 3);
function gnce_iccid_cart_item_quantity($product_quantity, $cart_item_key, $cart_item)
{
    if (isset($cart_item['sim_iccid'])) {
        return '1';
    }
    return $product_quantity;
}
