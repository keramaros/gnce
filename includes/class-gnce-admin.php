<?php

if (!defined('ABSPATH')) {
    exit;
}

class GNCE_Admin
{
    private $db;

    public function __construct()
    {
        $this->db = new GNCE_DB();
        add_action('admin_menu', [$this, 'gnce_add_menu']);
        add_action('admin_init', [$this, 'gnce_register_settings']);
        add_action('update_option_gnce_api_sync_interval', [$this, 'gnce_after_sync_interval_update'], 10, 3);
        add_action('add_option_gnce_api_sync_interval', [$this, 'gnce_after_sync_interval_update'], 10, 2);
        add_action('admin_head', [$this, 'gnce_admin_styles']);
    }

    public function gnce_admin_styles()
    {
        echo '<style>
            #toplevel_page_gnce-iccids .wp-menu-image img {
                width: 20px;
                height: 20px;
                padding-top: 4px;
                filter: brightness(10);
            }
        </style>';
    }

    public function gnce_after_sync_interval_update()
    {
        gnce_log( 'GNCE_Admin::gnce_after_sync_interval_update triggered.' );
        $sync = new GNCE_Sync();
        $sync->gnce_reschedule_cron();
    }

    public function gnce_add_menu()
    {
        add_menu_page(
                __('1NCE', 'gnce-1nce-products'),
                __('1NCE', 'gnce-1nce-products'),
                'manage_options',
                'gnce-iccids',
                [$this, 'gnce_render_list_page'],
                GNCE_URL . 'icon.png'
        );

        add_submenu_page(
                'gnce-iccids',
                __('ICCIDs', 'gnce-1nce-products'),
                __('ICCIDs', 'gnce-1nce-products'),
                'manage_options',
                'gnce-iccids',
                [$this, 'gnce_render_list_page']
        );

        add_submenu_page(
                'gnce-iccids',
                __('Add ICCID', 'gnce-1nce-products'),
                __('Add ICCID', 'gnce-1nce-products'),
                'manage_options',
                'gnce-add-iccid',
                [$this, 'gnce_render_add_edit_page']
        );

        add_submenu_page(
                'gnce-iccids',
                __('Settings', 'gnce-1nce-products'),
                __('Settings', 'gnce-1nce-products'),
                'manage_options',
                'gnce-settings',
                [$this, 'gnce_render_settings_page']
        );

        add_submenu_page(
                'gnce-iccids',
                __('Shortcode', 'gnce-1nce-products'),
                __('Shortcode', 'gnce-1nce-products'),
                'manage_options',
                'gnce-shortcode',
                [$this, 'gnce_render_shortcode_page']
        );

        add_submenu_page(
                'gnce-iccids',
                __('Products', 'gnce-1nce-products'),
                __('Products', 'gnce-1nce-products'),
                'manage_options',
                'gnce-1nce-products',
                [$this, 'gnce_render_products_page']
        );
    }

    public function gnce_register_settings()
    {
        register_setting('gnce_settings_group', 'gnce_once_api_client_id', 'sanitize_text_field');
        register_setting('gnce_settings_group', 'gnce_once_api_secret', 'sanitize_text_field');
        register_setting('gnce_settings_group', 'gnce_once_api_payment_method', 'sanitize_text_field');
        register_setting('gnce_settings_group', 'gnce_notification_frequency', 'sanitize_text_field');
        register_setting( 'gnce_settings_group', 'gnce_notification_limit', 'absint' );
        register_setting( 'gnce_settings_group', 'gnce_threshold_mb', 'absint' );
        register_setting( 'gnce_settings_group', 'gnce_threshold_sms', 'absint' );
        register_setting('gnce_settings_group', 'gnce_api_sync_interval', 'sanitize_text_field');
        register_setting('gnce_settings_group', 'gnce_email_template_subject', 'sanitize_text_field');
        register_setting('gnce_settings_group', 'gnce_email_template_body', 'wp_kses_post');
        register_setting('gnce_settings_group', 'gnce_sms_template', 'sanitize_textarea_field');
        register_setting('gnce_settings_group', 'gnce_quota_verification_interval', 'sanitize_text_field');
        register_setting( 'gnce_settings_group', 'gnce_enable_logging', 'sanitize_text_field' );

        add_settings_section('gnce_api_section', __('API Settings', 'gnce-1nce-products'), [$this, 'gnce_api_section_callback'], 'gnce-settings');
        add_settings_field('gnce_once_api_client_id', __('Client ID', 'gnce-1nce-products'), [$this, 'gnce_render_text_field'], 'gnce-settings', 'gnce_api_section', ['label_for' => 'gnce_once_api_client_id']);
        add_settings_field('gnce_once_api_secret', __('Client Secret', 'gnce-1nce-products'), [$this, 'gnce_render_password_field'], 'gnce-settings', 'gnce_api_section', ['label_for' => 'gnce_once_api_secret']);
        add_settings_field('gnce_once_api_payment_method', __('Payment Method', 'gnce-1nce-products'), [$this, 'gnce_render_payment_method_field'], 'gnce-settings', 'gnce_api_section', ['label_for' => 'gnce_once_api_payment_method']);

        add_settings_section('gnce_sync_section', __('Sync & Notifications', 'gnce-1nce-products'), [$this, 'gnce_sync_section_callback'], 'gnce-settings');
        add_settings_field('gnce_api_sync_interval', __('API Sync Interval', 'gnce-1nce-products'), [$this, 'gnce_render_sync_interval_field'], 'gnce-settings', 'gnce_sync_section', ['label_for' => 'gnce_api_sync_interval']);
        add_settings_field( 'gnce_threshold_mb', __( 'Threshold (MB)', 'gnce-1nce-products' ), [ $this, 'gnce_render_threshold_mb_field' ], 'gnce-settings', 'gnce_sync_section', [ 'label_for' => 'gnce_threshold_mb' ] );
        add_settings_field( 'gnce_threshold_sms', __( 'Threshold (SMS)', 'gnce-1nce-products' ), [ $this, 'gnce_render_threshold_sms_field' ], 'gnce-settings', 'gnce_sync_section', [ 'label_for' => 'gnce_threshold_sms' ] );
        add_settings_field('gnce_notification_frequency', __('Notification Frequency', 'gnce-1nce-products'), [$this, 'gnce_render_notification_frequency_field'], 'gnce-settings', 'gnce_sync_section', ['label_for' => 'gnce_notification_frequency']);
        add_settings_field( 'gnce_notification_limit', __( 'Notification Limit', 'gnce-1nce-products' ), [ $this, 'gnce_render_notification_limit_field' ], 'gnce-settings', 'gnce_sync_section', [ 'label_for' => 'gnce_notification_limit' ] );
        add_settings_field('gnce_quota_verification_interval', __('Quota Verification Interval', 'gnce-1nce-products'), [$this, 'gnce_render_quota_verification_field'], 'gnce-settings', 'gnce_sync_section', ['label_for' => 'gnce_quota_verification_interval']);
        add_settings_field( 'gnce_enable_logging', __( 'Enable Debug Logging', 'gnce-1nce-products' ), [ $this, 'gnce_render_checkbox_field' ], 'gnce-settings', 'gnce_sync_section', [ 'label_for' => 'gnce_enable_logging' ] );

        add_settings_section('gnce_templates_section', __('Templates', 'gnce-1nce-products'), [$this, 'gnce_templates_section_callback'], 'gnce-settings');
        add_settings_field('gnce_email_template_subject', __('Email Subject', 'gnce-1nce-products'), [$this, 'gnce_render_text_field'], 'gnce-settings', 'gnce_templates_section', ['label_for' => 'gnce_email_template_subject']);
        add_settings_field('gnce_email_template_body', __('Email Body', 'gnce-1nce-products'), [$this, 'gnce_render_textarea_field'], 'gnce-settings', 'gnce_templates_section', ['label_for' => 'gnce_email_template_body']);
        add_settings_field('gnce_sms_template', __('SMS Text', 'gnce-1nce-products'), [$this, 'gnce_render_textarea_field'], 'gnce-settings', 'gnce_templates_section', ['label_for' => 'gnce_sms_template']);
    }

    public function gnce_render_text_field($args)
    {
        $default = '';
        $description = '';
        if ($args['label_for'] === 'gnce_email_template_subject') {
            $default = __('1NCE Quota Alert', 'gnce-1nce-products');
            $description = __('The subject of the email sent when quota is low.', 'gnce-1nce-products');
        } elseif ($args['label_for'] === 'gnce_once_api_client_id') {
            $description = __('Your 1NCE API Client ID.', 'gnce-1nce-products');
        }
        $value = get_option($args['label_for'], $default);
        echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" value="' . esc_attr($value) . '" class="regular-text">';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    public function gnce_render_password_field($args)
    {
        $value = get_option($args['label_for'], '');
        echo '<input type="password" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('Your 1NCE API Client Secret.', 'gnce-1nce-products') . '</p>';
    }

    public function gnce_render_textarea_field($args)
    {
        $value = get_option($args['label_for']);
        $description = '';
        if ($args['label_for'] === 'gnce_email_template_body') {
            $description = __('The body of the email. You can use placeholders like {iccid}, {quotaMB}, {quotaSMS}.', 'gnce-1nce-products');
        } elseif ($args['label_for'] === 'gnce_sms_template') {
            $description = __('The text of the SMS. You can use placeholders like {iccid}, {quotaMB}, {quotaSMS}.', 'gnce-1nce-products');
        }
        echo '<textarea id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    public function gnce_render_payment_method_field()
    {
        $value = get_option('gnce_once_api_payment_method', 'creditcard');
        $options = [
                'banktransfer' => __('Bank Transfer', 'gnce-1nce-products'),
                'creditcard' => __('Credit Card', 'gnce-1nce-products'),
                'monthlyinvoice' => __('Monthly Invoice', 'gnce-1nce-products'),
                'boleto' => __('Boleto', 'gnce-1nce-products'),
        ];
        echo '<select name="gnce_once_api_payment_method" id="gnce_once_api_payment_method">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Select the payment method to use for automated renewals.', 'gnce-1nce-products') . '</p>';
    }

    public function gnce_render_sync_interval_field()
    {
        $value = get_option('gnce_api_sync_interval', 'hourly');
        $options = [
                'never' => __('Never', 'gnce-1nce-products'),
                'hourly' => __('Hourly', 'gnce-1nce-products'),
                'twicedaily' => __('Twice Daily', 'gnce-1nce-products'),
                'daily' => __('Daily', 'gnce-1nce-products'),
        ];
        echo '<select name="gnce_api_sync_interval" id="gnce_api_sync_interval">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('How often to sync SIM card data with the 1NCE API.', 'gnce-1nce-products') . '</p>';
    }

    public function gnce_render_threshold_mb_field() {
        $value = get_option( 'gnce_threshold_mb', 250 );
        echo '<input type="number" id="gnce_threshold_mb" name="gnce_threshold_mb" value="' . esc_attr( $value ) . '" min="0" class="small-text">';
        echo '<p class="description">' . __( 'Global default data threshold in MB. Used during sync unless overridden per entry.', 'gnce-1nce-products' ) . '</p>';
    }

    public function gnce_render_threshold_sms_field() {
        $value = get_option( 'gnce_threshold_sms', 50 );
        echo '<input type="number" id="gnce_threshold_sms" name="gnce_threshold_sms" value="' . esc_attr( $value ) . '" min="0" class="small-text">';
        echo '<p class="description">' . __( 'Global default SMS threshold count. Used during sync unless overridden per entry.', 'gnce-1nce-products' ) . '</p>';
    }

    public function gnce_render_notification_frequency_field()
    {
        $value = get_option('gnce_notification_frequency', 'daily');
        $options = [
                'never' => __('Never', 'gnce-1nce-products'),
                'daily' => __('Daily', 'gnce-1nce-products'),
                '3days' => __('Every 3 Days', 'gnce-1nce-products'),
                'weekly' => __('Weekly', 'gnce-1nce-products'),
        ];
        echo '<select name="gnce_notification_frequency" id="gnce_notification_frequency">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('How often to send low quota notifications for the same SIM card.', 'gnce-1nce-products') . '</p>';
    }

    public function gnce_render_notification_limit_field() {
        $value = get_option( 'gnce_notification_limit', 3 );
        echo '<input type="number" id="gnce_notification_limit" name="gnce_notification_limit" value="' . esc_attr( $value ) . '" min="1" class="small-text">';
        echo '<p class="description">' . __( 'Maximum number of notifications to send for a SIM card when threshold is breached.', 'gnce-1nce-products' ) . '</p>';
    }

    public function gnce_render_quota_verification_field()
    {
        $value = get_option('gnce_quota_verification_interval', '1');
        $options = [
                'never' => __('Never', 'gnce-1nce-products'),
                '1' => __('1 Hour', 'gnce-1nce-products'),
                '2' => __('2 Hours', 'gnce-1nce-products'),
                '4' => __('4 Hours', 'gnce-1nce-products'),
        ];
        echo '<select name="gnce_quota_verification_interval" id="gnce_quota_verification_interval">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Verification delay after a successful renewal order to confirm the new quota.', 'gnce-1nce-products') . '</p>';
    }

    public function gnce_render_checkbox_field( $args ) {
        $value = get_option( $args['label_for'], '0' );
        echo '<label><input type="checkbox" id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( $args['label_for'] ) . '" value="1" ' . checked( '1', $value, false ) . '> ' . __( 'Enable writing debug events to log file.', 'gnce-1nce-products' ) . '</label> ';
        echo '<a href="' . esc_url( GNCE_URL . 'gnce_debug.log' ) . '" target="_blank">' . __( 'gnce_debug.log', 'gnce-1nce-products' ) . '</a>';
    }

    public function gnce_api_section_callback()
    {
        echo '<p>' . __('Configure your 1NCE API credentials and default payment settings.', 'gnce-1nce-products') . '</p>';
    }

    public function gnce_sync_section_callback()
    {
        echo '<p>' . __('Manage how often your data is synchronized and how notifications are handled.', 'gnce-1nce-products') . '</p>';
    }

    public function gnce_templates_section_callback()
    {
        echo '<p>' . __('Customize the messages sent to your customers when their quota is low.', 'gnce-1nce-products') . '</p>';
    }

    public function gnce_render_list_page()
    {
        $this->gnce_handle_list_actions();

        if (isset($_GET['message']) && $_GET['message'] === 'added') {
            echo '<div class="updated notice is-dismissible"><p>' . __('ICCID added.', 'gnce-1nce-products') . '</p></div>';
        }

        $list_table = new GNCE_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('ICCIDs', 'gnce-1nce-products'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=gnce-add-iccid')); ?>" class="page-title-action"><?php _e('Add New', 'gnce-1nce-products'); ?></a>
            <form method="get" id="gnce-iccids-form">
                <input type="hidden" name="page" value="gnce-iccids">
                <?php
                $list_table->search_box(__('Search ICCIDs', 'gnce-1nce-products'), 'iccid-search');
                $list_table->display();
                ?>
            </form>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var form = document.getElementById('gnce-iccids-form');
                    if (!form) return;

                    form.addEventListener('submit', function (e) {
                        var topAction = document.getElementById('bulk-action-selector-top');
                        var bottomAction = document.getElementById('bulk-action-selector-bottom');
                        var action = (topAction && topAction.value !== '-1') ? topAction.value : (bottomAction ? bottomAction.value : '-1');

                        // Remove any previous dynamic inputs
                        var oldMb = document.getElementById('bulk_threshold_mb_value_input');
                        if (oldMb) oldMb.remove();
                        var oldSms = document.getElementById('bulk_threshold_sms_value_input');
                        if (oldSms) oldSms.remove();

                        if (action === 'bulk-set-threshold-mb') {
                            var checkedBoxes = form.querySelectorAll('input[name="iccid_id[]"]:checked');
                            if (checkedBoxes.length === 0) return;
                            var val = prompt("<?php echo esc_js( __( 'Enter new Threshold (MB) value:', 'gnce-1nce-products' ) ); ?>", "250");
                            if (val === null) {
                                e.preventDefault();
                                return;
                            }
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'bulk_threshold_mb_value';
                            input.id = 'bulk_threshold_mb_value_input';
                            input.value = val;
                            form.appendChild(input);
                        } else if (action === 'bulk-set-threshold-sms') {
                            var checkedBoxes = form.querySelectorAll('input[name="iccid_id[]"]:checked');
                            if (checkedBoxes.length === 0) return;
                            var val = prompt("<?php echo esc_js( __( 'Enter new Threshold (SMS) value:', 'gnce-1nce-products' ) ); ?>", "50");
                            if (val === null) {
                                e.preventDefault();
                                return;
                            }
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'bulk_threshold_sms_value';
                            input.id = 'bulk_threshold_sms_value_input';
                            input.value = val;
                            form.appendChild(input);
                        }
                    });
                });
            </script>
        </div>
        <?php
    }

    private function gnce_handle_list_actions()
    {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;

        // Handle Single Actions
        if ($action === 'delete' && $id) {
            check_admin_referer('gnce_delete_iccid_' . $id);
            $this->db->delete_iccid($id);
            echo '<div class="updated notice is-dismissible"><p>' . __('ICCID deleted.', 'gnce-1nce-products') . '</p></div>';
        }

        if ($action === 'sync' && $id) {
            check_admin_referer('gnce_sync_iccid_' . $id);
            $sync = new GNCE_Sync();
            $result = $sync->gnce_sync_single_row($id);
            if ($result) {
                echo '<div class="updated notice is-dismissible"><p>' . __('ICCID synced successfully.', 'gnce-1nce-products') . '</p></div>';
            } else {
                echo '<div class="error notice is-dismissible"><p>' . __('ICCID sync failed.', 'gnce-1nce-products') . '</p></div>';
            }
        }

        // Handle Bulk Actions
        $bulk_action = '';
        if (isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1') {
            $bulk_action = $_REQUEST['action'];
        } elseif (isset($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1') {
            $bulk_action = $_REQUEST['action2'];
        }

        $ids = isset($_REQUEST['iccid_id']) ? array_map('absint', (array)$_REQUEST['iccid_id']) : [];

        if ($bulk_action && !empty($ids)) {
            if ($bulk_action === 'bulk-delete') {
                check_admin_referer('bulk-iccids');
                foreach ($ids as $bulk_id) {
                    $this->db->delete_iccid($bulk_id);
                }
                echo '<div class="updated notice is-dismissible"><p>' . sprintf(__('%d ICCIDs deleted.', 'gnce-1nce-products'), count($ids)) . '</p></div>';
            }

            if ($bulk_action === 'bulk-sync') {
                check_admin_referer('bulk-iccids');
                $sync = new GNCE_Sync();
                $success_count = 0;
                foreach ($ids as $bulk_id) {
                    if ($sync->gnce_sync_single_row($bulk_id)) {
                        $success_count++;
                    }
                }
                echo '<div class="updated notice is-dismissible"><p>' . sprintf(__('%d ICCIDs synced successfully.', 'gnce-1nce-products'), $success_count) . '</p></div>';
                if ($success_count < count($ids)) {
                    echo '<div class="error notice is-dismissible"><p>' . sprintf(__('%d ICCIDs failed to sync.', 'gnce-1nce-products'), count($ids) - $success_count) . '</p></div>';
                }
            }

            if ( $bulk_action === 'bulk-set-threshold-mb' ) {
                check_admin_referer( 'bulk-iccids' );
                if ( isset( $_REQUEST['bulk_threshold_mb_value'] ) && is_numeric( $_REQUEST['bulk_threshold_mb_value'] ) ) {
                    $threshold_mb = absint( $_REQUEST['bulk_threshold_mb_value'] );
                    foreach ( $ids as $bulk_id ) {
                        $this->db->update_iccid( $bulk_id, [ 'thresholdMB' => $threshold_mb, 'enableThresholdMB' => 1 ] );
                    }
                    echo '<div class="updated notice is-dismissible"><p>' . sprintf( __( 'Threshold (MB) updated to %d for %d ICCIDs.', 'gnce-1nce-products' ), $threshold_mb, count( $ids ) ) . '</p></div>';
                } else {
                    echo '<div class="error notice is-dismissible"><p>' . __( 'Invalid threshold value provided for Threshold (MB).', 'gnce-1nce-products' ) . '</p></div>';
                }
            }

            if ( $bulk_action === 'bulk-set-threshold-sms' ) {
                check_admin_referer( 'bulk-iccids' );
                if ( isset( $_REQUEST['bulk_threshold_sms_value'] ) && is_numeric( $_REQUEST['bulk_threshold_sms_value'] ) ) {
                    $threshold_sms = absint( $_REQUEST['bulk_threshold_sms_value'] );
                    foreach ( $ids as $bulk_id ) {
                        $this->db->update_iccid( $bulk_id, [ 'thresholdSMS' => $threshold_sms, 'enableThresholdSMS' => 1 ] );
                    }
                    echo '<div class="updated notice is-dismissible"><p>' . sprintf( __( 'Threshold (SMS) updated to %d for %d ICCIDs.', 'gnce-1nce-products' ), $threshold_sms, count( $ids ) ) . '</p></div>';
                } else {
                    echo '<div class="error notice is-dismissible"><p>' . __( 'Invalid threshold value provided for Threshold (SMS).', 'gnce-1nce-products' ) . '</p></div>';
                }
            }

            if ( $bulk_action === 'bulk-enable-notify-email' ) {
                check_admin_referer( 'bulk-iccids' );
                foreach ( $ids as $bulk_id ) {
                    $this->db->update_iccid( $bulk_id, [ 'notifyByEmail' => 1 ] );
                }
                echo '<div class="updated notice is-dismissible"><p>' . sprintf( __( 'Notify by Email enabled for %d ICCIDs.', 'gnce-1nce-products' ), count( $ids ) ) . '</p></div>';
            }

            if ( $bulk_action === 'bulk-disable-notify-email' ) {
                check_admin_referer( 'bulk-iccids' );
                foreach ( $ids as $bulk_id ) {
                    $this->db->update_iccid( $bulk_id, [ 'notifyByEmail' => 0 ] );
                }
                echo '<div class="updated notice is-dismissible"><p>' . sprintf( __( 'Notify by Email disabled for %d ICCIDs.', 'gnce-1nce-products' ), count( $ids ) ) . '</p></div>';
            }

            if ( $bulk_action === 'bulk-enable-notify-sms' ) {
                check_admin_referer( 'bulk-iccids' );
                foreach ( $ids as $bulk_id ) {
                    $this->db->update_iccid( $bulk_id, [ 'notifyBySMS' => 1 ] );
                }
                echo '<div class="updated notice is-dismissible"><p>' . sprintf( __( 'Notify by SMS enabled for %d ICCIDs.', 'gnce-1nce-products' ), count( $ids ) ) . '</p></div>';
            }

            if ( $bulk_action === 'bulk-disable-notify-sms' ) {
                check_admin_referer( 'bulk-iccids' );
                foreach ( $ids as $bulk_id ) {
                    $this->db->update_iccid( $bulk_id, [ 'notifyBySMS' => 0 ] );
                }
                echo '<div class="updated notice is-dismissible"><p>' . sprintf( __( 'Notify by SMS disabled for %d ICCIDs.', 'gnce-1nce-products' ), count( $ids ) ) . '</p></div>';
            }
        }
    }

    public function gnce_render_add_edit_page()
    {
        $id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;

        if (isset($_POST['gnce_save_iccid'])) {
            check_admin_referer('gnce_save_iccid_nonce');
            $data = [
                    'iccid' => sanitize_text_field($_POST['iccid']),
                    'name' => sanitize_text_field($_POST['name']),
                    'phone' => sanitize_text_field($_POST['phone']),
                    'email' => sanitize_email($_POST['email']),
                    'thresholdMB' => absint($_POST['thresholdMB']),
                    'thresholdSMS' => absint($_POST['thresholdSMS']),
                    'enableThresholdMB'  => isset( $_POST['enableThresholdMB'] ) ? 1 : 0,
                    'enableThresholdSMS' => isset( $_POST['enableThresholdSMS'] ) ? 1 : 0,
                    'notifyBySMS' => isset($_POST['notifyBySMS']) ? 1 : 0,
                    'notifyByEmail' => isset($_POST['notifyByEmail']) ? 1 : 0,
            ];

            $id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;

            if ($this->db->iccid_exists($data['iccid'], $id)) {
                echo '<div class="error notice is-dismissible"><p>' . __('Error: This ICCID already exists in the database.', 'gnce-1nce-products') . '</p></div>';
            } elseif ($id) {
                $this->db->update_iccid($id, $data);

                // Verify with API and store quotas
                $sync = new GNCE_Sync();
                $synced = $sync->gnce_sync_single_row($id);

                if ($synced) {
                    echo '<div class="updated notice is-dismissible"><p>' . __('ICCID updated and quotas synced.', 'gnce-1nce-products') . '</p></div>';
                } else {
                    echo '<div class="error notice is-dismissible"><p>' . __('ICCID updated, but API verification/sync failed. Please check the ICCID and API settings.', 'gnce-1nce-products') . '</p></div>';
                }
            } else {
                $new_id = $this->db->insert_iccid($data);
                if ($new_id) {
                    // Verify with API and store quotas
                    $sync = new GNCE_Sync();
                    $synced = $sync->gnce_sync_single_row($new_id);

                    if ($synced) {
                        wp_safe_redirect(admin_url('admin.php?page=gnce-iccids&message=added'));
                        exit;
                    } else {
                        echo '<div class="error notice is-dismissible"><p>' . __('ICCID added, but API verification/sync failed. Please check the ICCID and API settings.', 'gnce-1nce-products') . '</p></div>';
                    }
                }
            }
        }

        $global_threshold_mb  = get_option( 'gnce_threshold_mb', 250 );
        $global_threshold_sms = get_option( 'gnce_threshold_sms', 50 );

        $item = $id ? $this->db->get_iccid($id) : [
                'iccid' => '', 'name' => '', 'phone' => '', 'email' => '',
                'thresholdMB'        => $global_threshold_mb,
                'thresholdSMS'       => $global_threshold_sms,
                'enableThresholdMB'  => 0,
                'enableThresholdSMS' => 0,
                'notifyBySMS'        => 1,
                'notifyByEmail'      => 1
        ];

        if (!$item && $id) {
            echo '<div class="error"><p>' . __('ICCID not found.', 'gnce-1nce-products') . '</p></div>';
            return;
        }

        ?>
        <style>
            .form-table td p {
                display: block;
            }
        </style>
        <div class="wrap">
            <h1><?php echo $id ? __('Edit ICCID', 'gnce-1nce-products') : __('Add ICCID', 'gnce-1nce-products'); ?></h1>
            <form method="post" id="poststuff">
                <?php wp_nonce_field('gnce_save_iccid_nonce'); ?>

                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle" style="border-bottom: 1px solid #dcdcde;"><span><?php _e('ICCID Details', 'gnce-1nce-products'); ?></span></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th><label for="iccid"><?php _e('ICCID', 'gnce-1nce-products'); ?></label></th>
                                        <td>
                                            <input name="iccid" id="iccid" type="text" value="<?php echo esc_attr($item['iccid']); ?>" class="regular-text" required>
                                            <p class="description"><?php _e('Enter the 19 or 20 digit ICCID of the SIM card.', 'gnce-1nce-products'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="name"><?php _e('Name', 'gnce-1nce-products'); ?></label></th>
                                        <td>
                                            <input name="name" id="name" type="text" value="<?php echo esc_attr($item['name']); ?>" class="regular-text" required>
                                            <p class="description"><?php _e('Provide the name of the person who owns or uses this SIM card.', 'gnce-1nce-products'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="phone"><?php _e('Phone', 'gnce-1nce-products'); ?></label></th>
                                        <td>
                                            <input name="phone" id="phone" type="text" value="<?php echo esc_attr($item['phone']); ?>" class="regular-text">
                                            <p class="description"><?php _e('The phone number where notifications for this SIM will be sent.', 'gnce-1nce-products'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="email"><?php _e('Email', 'gnce-1nce-products'); ?></label></th>
                                        <td>
                                            <input name="email" id="email" type="email" value="<?php echo esc_attr($item['email']); ?>" class="regular-text">
                                            <p class="description"><?php _e('The email address where notifications for this SIM will be sent.', 'gnce-1nce-products'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="thresholdMB"><?php _e('Threshold (MB)', 'gnce-1nce-products'); ?></label></th>
                                        <td>
                                            <label><input name="enableThresholdMB" type="checkbox" value="1" <?php checked( ! empty( $item['enableThresholdMB'] ), 1 ); ?>> <?php _e( 'Custom threshold', 'gnce-1nce-products' ); ?></label>
                                            <input name="thresholdMB" id="thresholdMB" type="number" value="<?php echo esc_attr( $item['thresholdMB'] ?? $global_threshold_mb ); ?>" class="small-text">
                                            <p class="description"><?php printf( __( 'Enable to override the global threshold (%d MB). Notify when data quota falls below this value (in MB).', 'gnce-1nce-products' ), $global_threshold_mb ); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="thresholdSMS"><?php _e('Threshold (SMS)', 'gnce-1nce-products'); ?></label></th>
                                        <td>
                                            <label><input name="enableThresholdSMS" type="checkbox" value="1" <?php checked( ! empty( $item['enableThresholdSMS'] ), 1 ); ?>> <?php _e( 'Custom threshold', 'gnce-1nce-products' ); ?></label>
                                            <input name="thresholdSMS" id="thresholdSMS" type="number" value="<?php echo esc_attr( $item['thresholdSMS'] ?? $global_threshold_sms ); ?>" class="small-text">
                                            <p class="description"><?php printf( __( 'Enable to override the global threshold (%d SMS). Notify when SMS quota falls below this value.', 'gnce-1nce-products' ), $global_threshold_sms ); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Notifications', 'gnce-1nce-products'); ?></th>
                                        <td>
                                            <label><input name="notifyByEmail" type="checkbox" value="1" <?php checked($item['notifyByEmail'], 1); ?>> <?php _e('Notify by Email', 'gnce-1nce-products'); ?></label><br>
                                            <label><input name="notifyBySMS" type="checkbox" value="1" <?php checked($item['notifyBySMS'], 1); ?>> <?php _e('Notify by SMS', 'gnce-1nce-products'); ?></label>
                                            <p class="description"><?php _e('Select the methods for receiving quota threshold notifications.', 'gnce-1nce-products'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle" style="border-bottom: 1px solid #dcdcde;"><span><?php _e('Save', 'gnce-1nce-products'); ?></span></h2>

                            <div id="submitdiv">
                                <div class="inside">
                                    <div id="minor-publishing">
                                        <div id="misc-publishing-actions">
                                            <?php if ($id) : ?>
                                                <div class="misc-pub-section">
                                                    <span class="dashicons dashicons-calendar"></span> <?php _e('Created on:', 'gnce-1nce-products'); ?><br> <b><?php echo mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item['created']); ?></b>
                                                </div>
                                                <div class="misc-pub-section">
                                                    <span class="dashicons dashicons-chart-bar"></span> <?php _e('Current Quota:', 'gnce-1nce-products'); ?><br> <b><?php echo $item['quotaMB']; ?> MB / <?php echo $item['quotaSMS']; ?> SMS</b>
                                                </div>
                                                <div class="misc-pub-section">
                                                    <span class="dashicons dashicons-update"></span> <?php _e('Last Sync:', 'gnce-1nce-products'); ?><br> <b><?php echo $item['lastQuotaUpdated'] ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item['lastQuotaUpdated']) : __('Never', 'gnce-1nce-products'); ?></b>
                                                </div>
                                                <div class="misc-pub-section">
                                                    <span class="dashicons dashicons-email"></span> <?php _e('Last Notification:', 'gnce-1nce-products'); ?><br> <b><?php echo $item['lastNotificationSent'] ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item['lastNotificationSent']) : __('Never', 'gnce-1nce-products'); ?></b>
                                                </div>
                                                <div class="misc-pub-section">
                                                    <span class="dashicons dashicons-megaphone"></span> <?php _e( 'Notifications Sent:', 'gnce-1nce-products' ); ?><br> <b><?php echo isset( $item['countNotificationSent'] ) ? intval( $item['countNotificationSent'] ) : 0; ?></b>
                                                </div>
                                                <?php if ( ! empty( $item['error'] ) ) : ?>
                                                    <div class="misc-pub-section" style="color: #d63638;">
                                                        <span class="dashicons dashicons-warning" style="color: #d63638;"></span> <?php _e( 'Status/Error:', 'gnce-1nce-products' ); ?><br> <b><?php echo esc_html( $item['error'] ); ?></b>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="major-publishing-actions">
                                <div id="publishing-action">
                                    <?php submit_button(__('Save ICCID', 'gnce-1nce-products'), 'primary', 'gnce_save_iccid', false); ?>
                                </div>
                                <div class="clear"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function gnce_render_settings_page()
    {
        if (isset($_POST['gnce_manual_sync_all'])) {
            check_admin_referer('gnce_manual_sync_all_nonce');
            $sync = new GNCE_Sync();
            $sync->gnce_trigger_sync_all( true );
            echo '<div class="updated"><p>' . __('Sync process triggered.', 'gnce-1nce-products') . '</p></div>';
        }

        if (isset($_POST['gnce_reset_plugin'])) {
            check_admin_referer('gnce_reset_plugin_nonce');
            $this->gnce_handle_reset();
            echo '<div class="updated"><p>' . __('Plugin reset successfully. All settings and tables have been removed.', 'gnce-1nce-products') . '</p></div>';
            echo '<script>setTimeout(function(){ window.location.href="' . admin_url('admin.php?page=gnce-settings') . '"; }, 2000);</script>';
        }
        ?>
        <style>
            .form-table td p {
                display: block;
            }
        </style>
        <div class="wrap">
            <h1><?php _e('1NCE Settings', 'gnce-1nce-products'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('gnce_settings_group');
                do_settings_sections('gnce-settings');
                submit_button();
                ?>
            </form>

            <hr>
            <h2><?php _e('Cron Status', 'gnce-1nce-products'); ?></h2>
            <table class="widefat fixed" style="width: auto; min-width: 400px;">
                <thead>
                <tr>
                    <th><?php _e('Cron Hook', 'gnce-1nce-products'); ?></th>
                    <th><?php _e('Last Run', 'gnce-1nce-products'); ?></th>
                    <th><?php _e('Next Run', 'gnce-1nce-products'); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><code>gnce_sync_cron</code></td>
                    <td>
                        <?php
                        $last_run = get_option('gnce_last_sync_run');
                        echo $last_run ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last_run) : __('Never', 'gnce-1nce-products');
                        ?>
                    </td>
                    <td>
                        <?php
                        $next_run = wp_next_scheduled('gnce_sync_cron');
                        if ($next_run) {
                            if (function_exists('wp_date')) {
                                echo wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_run);
                            } else {
                                echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run + (get_option('gmt_offset') * HOUR_IN_SECONDS));
                            }
                        } else {
                            _e('Not Scheduled', 'gnce-1nce-products');
                        }
                        ?>
                    </td>
                </tr>
                </tbody>
            </table>

            <hr>
            <h2><?php _e('Manual Sync', 'gnce-1nce-products'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('gnce_manual_sync_all_nonce'); ?>
                <?php submit_button(__('Trigger Manual Sync for All', 'gnce-1nce-products'), 'secondary', 'gnce_manual_sync_all'); ?>
            </form>

            <hr>
            <h2 style="color: #d63638;"><?php _e('Danger Zone', 'gnce-1nce-products'); ?></h2>
            <p><?php _e('Warning: This will delete all plugin settings and the ICCID database table. This action cannot be undone.', 'gnce-1nce-products'); ?></p>
            <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset the plugin? All data will be lost.', 'gnce-1nce-products')); ?>');">
                <?php wp_nonce_field('gnce_reset_plugin_nonce'); ?>
                <?php submit_button(__('Reset Plugin & Delete All Data', 'gnce-1nce-products'), 'delete', 'gnce_reset_plugin'); ?>
            </form>
        </div>
        <?php
    }

    public function gnce_render_shortcode_page()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Shortcode Instructions', 'gnce-1nce-products'); ?></h1>
            <div class="card">
                <h2><?php _e('How to use', 'gnce-1nce-products'); ?></h2>
                <p><?php _e('To display the 1NCE SIM status and quota check form on any page or post, use the following shortcode:', 'gnce-1nce-products'); ?></p>
                <code>[1nce]</code>
                <p><?php _e('This shortcode will render a search form where users can enter their ICCID to view their current status, remaining data, and remaining SMS quota.', 'gnce-1nce-products'); ?></p>
            </div>
        </div>
        <?php
    }

    public function gnce_render_products_page()
    {
        $table = new GNCE_Product_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('1NCE Products', 'gnce-1nce-products'); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
                <?php
                $table->search_box(__('Search Products', 'gnce-1nce-products'), 'product');
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }

    private function gnce_handle_reset()
    {
        // Delete all options prefixed with gnce_
        global $wpdb;
        $options = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'gnce\_%'", ARRAY_A);
        foreach ($options as $option) {
            delete_option($option['option_name']);
        }

        // Drop the custom table
        $this->db->drop_table();

        // Clear cron
        $sync = new GNCE_Sync();
        $sync->gnce_clear_cron();

        // Clear log file
        $log_file = defined( 'GNCE_PATH' ) ? GNCE_PATH . 'gnce_debug.log' : dirname( __DIR__ ) . '/gnce_debug.log';
        if ( file_exists( $log_file ) ) {
            file_put_contents( $log_file, '' );
        }
    }
}
