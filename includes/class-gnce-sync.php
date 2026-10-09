<?php

if (!defined('ABSPATH')) {
    exit;
}

class GNCE_Sync
{
    private $db;
    private $api;

    public function __construct()
    {
        $this->db = new GNCE_DB();
        $client_id = get_option('gnce_once_api_client_id');
        $secret = get_option('gnce_once_api_secret');
        $this->api = new GNCE_API_Client($client_id, $secret);

        add_action('gnce_sync_cron', [$this, 'gnce_trigger_sync_all']);
	    add_action( 'gnce_sync_batch', [ $this, 'gnce_process_batch' ], 10, 2 );
        add_action('gnce_sync_single', [$this, 'gnce_sync_single_row']);
        add_action('gnce_verify_quota_after_renewal', [$this, 'gnce_verify_quota_after_renewal_callback'], 10, 2);
        add_filter('cron_schedules', [$this, 'gnce_add_cron_intervals']);
    }

    public function gnce_add_cron_intervals($schedules)
    {
        if (!isset($schedules['twicedaily'])) {
            $schedules['twicedaily'] = array(
                'interval' => 43200,
                'display' => __('Twice Daily', 'gnce-1nce-products')
            );
        }
        if (!isset($schedules['2hours'])) {
            $schedules['2hours'] = array(
                'interval' => 2 * HOUR_IN_SECONDS,
                'display' => __('Every 2 Hours', 'gnce-1nce-products')
            );
        }
        if (!isset($schedules['4hours'])) {
            $schedules['4hours'] = array(
                'interval' => 4 * HOUR_IN_SECONDS,
                'display' => __('Every 4 Hours', 'gnce-1nce-products')
            );
        }
        return $schedules;
    }

    public function gnce_setup_cron()
    {
        $interval = get_option('gnce_api_sync_interval', 'hourly');
	    gnce_log( 'GNCE_Sync::gnce_setup_cron. Interval:', $interval );

        if ($interval === 'never') {
            wp_clear_scheduled_hook('gnce_sync_cron');
            return;
        }

        if (!wp_next_scheduled('gnce_sync_cron')) {
            // Schedule first run 1 minute in the future
            wp_schedule_event(time() + 60, $interval, 'gnce_sync_cron');
        }
    }

    public function gnce_clear_cron()
    {
	    gnce_log( 'GNCE_Sync::gnce_clear_cron.' );
        wp_clear_scheduled_hook('gnce_sync_cron');
    }

    public function gnce_reschedule_cron()
    {
        wp_clear_scheduled_hook('gnce_sync_cron');
        $interval = get_option('gnce_api_sync_interval', 'hourly');
	    gnce_log( 'GNCE_Sync::gnce_reschedule_cron. New Interval:', $interval );

        if ($interval === 'never') {
            return;
        }

        // Schedule first run 1 minute in the future to ensure visibility and prevent immediate execution loops
        wp_schedule_event(time() + 60, $interval, 'gnce_sync_cron');
    }

	public function gnce_trigger_sync_all( $manual = false )
    {
	    if ( $manual ) {
		    gnce_log( 'GNCE_Sync::gnce_trigger_sync_all. Manual' );
	    } else {
		    $interval = get_option( 'gnce_api_sync_interval', 'hourly' );
		    gnce_log( 'GNCE_Sync::gnce_trigger_sync_all. Recurrence:', $interval );
	    }
        update_option('gnce_last_sync_run', current_time('mysql'));
        $total = $this->db->count_iccids();
        $batch_size = 10;
        for ($i = 0; $i < $total; $i += $batch_size) {
            wp_schedule_single_event(time() + ($i / $batch_size) * 60, 'gnce_sync_batch', [$i, $batch_size]);
        }
    }

	public function gnce_process_batch( $offset = 0, $limit = 10 )
    {
	    gnce_log( 'GNCE_Sync::gnce_process_batch. Offset:', $offset, 'Limit:', $limit );
        $rows = $this->db->get_iccids([
            'number' => $limit,
            'offset' => $offset
        ]);

        foreach ($rows as $row) {
            $this->gnce_sync_single_row($row['id']);
        }
    }

    public function gnce_sync_single_row($id)
    {
	    gnce_log( 'GNCE_Sync::gnce_sync_single_row for ID:', $id );
        $row = $this->db->get_iccid($id);
        if (!$row) return false;

        try {
            $data_quota = $this->api->gnce_get_sim_data_quota($row['iccid']);
            $sms_quota = $this->api->gnce_get_sim_sms_quota($row['iccid']);

	        $quota_mb  = $data_quota['remainingVolume'] ?? $data_quota['volume'] ?? $data_quota['remaining_volume'] ?? 0;
	        $quota_sms = $sms_quota['remainingVolume'] ?? $sms_quota['volume'] ?? $sms_quota['remaining_sms'] ?? 0;

	        $effective_threshold_mb  = ( ! empty( $row['enableThresholdMB'] ) && isset( $row['thresholdMB'] ) ) ? intval( $row['thresholdMB'] ) : intval( get_option( 'gnce_threshold_mb', 250 ) );
	        $effective_threshold_sms = ( ! empty( $row['enableThresholdSMS'] ) && isset( $row['thresholdSMS'] ) ) ? intval( $row['thresholdSMS'] ) : intval( get_option( 'gnce_threshold_sms', 50 ) );

            $update_data = [
	            'quotaMB'  => $quota_mb,
	            'quotaSMS' => $quota_sms,
                'lastQuotaUpdated' => current_time('mysql'),
                'error' => ''
            ];

	        if ( $quota_mb > $effective_threshold_mb || $quota_sms > $effective_threshold_sms ) {
		        $update_data['countNotificationSent'] = 0;
	        }

            $this->db->update_iccid($id, $update_data);

            // Check thresholds and notify
            $notifier = new GNCE_Notifications();
            $notifier->gnce_check_and_notify($id);

            return true;
        } catch (Exception $e) {
            $this->db->update_iccid($id, ['error' => substr($e->getMessage(), 0, 50)]);
            return false;
        }
    }

    public function gnce_verify_quota_after_renewal_callback($order_id, $iccid)
    {
	    gnce_log( 'GNCE_Sync::gnce_verify_quota_after_renewal_callback. Order ID:', $order_id, 'ICCID:', $iccid );
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        try {
            $data_quota = $this->api->gnce_get_sim_data_quota($iccid);
            $remaining_data = $data_quota['remainingVolume'] ?? $data_quota['volume'] ?? $data_quota['remaining_volume'] ?? 0;

            $sms_quota = $this->api->gnce_get_sim_sms_quota($iccid);
            $remaining_sms = $sms_quota['remainingVolume'] ?? $sms_quota['volume'] ?? $sms_quota['remaining_sms'] ?? 0;

            $order->add_order_note(sprintf(__('Verified Quota for ICCID %s after renewal: %s MB, %s SMS', 'gnce-1nce-products'), $iccid, $remaining_data, $remaining_sms));
        } catch (Exception $e) {
            $order->add_order_note(sprintf(__('Failed to verify quota for ICCID %s: %s', 'gnce-1nce-products'), $iccid, $e->getMessage()));
        }
    }
}
