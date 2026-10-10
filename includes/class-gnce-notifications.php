<?php

if (!defined('ABSPATH')) {
    exit;
}

class GNCE_Notifications
{
    private $db;

    public function __construct()
    {
        $this->db = new GNCE_DB();
    }

    public function gnce_check_and_notify($id)
    {
        $row = $this->db->get_iccid($id);
        if (!$row) return;

	    $effective_threshold_mb  = ( ! empty( $row['enableThresholdMB'] ) && isset( $row['thresholdMB'] ) ) ? intval( $row['thresholdMB'] ) : intval( get_option( 'gnce_threshold_mb', 250 ) );
	    $effective_threshold_sms = ( ! empty( $row['enableThresholdSMS'] ) && isset( $row['thresholdSMS'] ) ) ? intval( $row['thresholdSMS'] ) : intval( get_option( 'gnce_threshold_sms', 50 ) );

	    $reasons = [];
	    if ( isset( $row['quotaMB'] ) && $row['quotaMB'] < $effective_threshold_mb ) {
		    $reasons[] = sprintf( 'Quota MB (%s MB) is below threshold (%s MB)', $row['quotaMB'], $effective_threshold_mb );
	    }
	    if ( isset( $row['quotaSMS'] ) && $row['quotaSMS'] < $effective_threshold_sms ) {
		    $reasons[] = sprintf( 'Quota SMS (%s SMS) is below threshold (%s SMS)', $row['quotaSMS'], $effective_threshold_sms );
	    }

	    if ( empty( $reasons ) ) {
		    gnce_log( sprintf( 'GNCE_Notifications: No notification sent for ID %s (ICCID: %s). Quota MB: %s MB (Threshold: %s MB), Quota SMS: %s SMS (Threshold: %s SMS). No thresholds breached.', $id, $row['iccid'] ?? '', $row['quotaMB'] ?? 'N/A', $effective_threshold_mb, $row['quotaSMS'] ?? 'N/A', $effective_threshold_sms ) );

		    return;
        }

	    $reason_str    = implode( '; ', $reasons );

	    if ( ! $this->gnce_can_send_notification( $row ) ) {
		    gnce_log( sprintf( 'GNCE_Notifications: Notification skipped (frequency/limit restriction) for ID %s (ICCID: %s). Reason for breach: %s. Current Quota: MB=%s, SMS=%s. Thresholds: MB=%s, SMS=%s.', $id, $row['iccid'] ?? '', $reason_str, $row['quotaMB'] ?? 'N/A', $row['quotaSMS'] ?? 'N/A', $effective_threshold_mb, $effective_threshold_sms ) );

		    return;
	    }

        $success = true;
        $error_msg = '';
	    $sent_channels = [];

        if ($row['notifyByEmail'] && !empty($row['email'])) {
            try {
                $this->gnce_send_email($row);
	            $sent_channels[] = 'Email (to: ' . $row['email'] . ')';
            } catch (Exception $e) {
                $success = false;
                $error_msg .= 'Email Error: ' . substr($e->getMessage(), 0, 20) . '; ';
            }
        }

        if ($row['notifyBySMS'] && !empty($row['phone'])) {
            try {
                $this->gnce_send_sms($row);
	            $sent_channels[] = 'SMS (to: ' . $row['phone'] . ')';
            } catch (Exception $e) {
                $success = false;
                $error_msg .= 'SMS Error: ' . substr($e->getMessage(), 0, 20) . '; ';
            }
        }

	    if ( ! empty( $sent_channels ) ) {
		    gnce_log( sprintf(
			    'GNCE_Notifications: Notification sent [%s] for ID %s (ICCID: %s). Why: %s. Quota MB: %s MB (Threshold: %s MB), Quota SMS: %s SMS (Threshold: %s SMS).',
			    implode( ', ', $sent_channels ),
			    $id,
			    $row['iccid'] ?? '',
			    $reason_str,
			    $row['quotaMB'] ?? 'N/A',
			    $effective_threshold_mb,
			    $row['quotaSMS'] ?? 'N/A',
			    $effective_threshold_sms
		    ) );
	    } elseif ( empty( $row['notifyByEmail'] ) && empty( $row['notifyBySMS'] ) ) {
		    gnce_log( sprintf( 'GNCE_Notifications: Threshold breached but no notification channels enabled for ID %s (ICCID: %s). Reason: %s. Quota MB: %s MB (Threshold: %s MB), Quota SMS: %s SMS (Threshold: %s SMS).', $id, $row['iccid'] ?? '', $reason_str, $row['quotaMB'] ?? 'N/A', $effective_threshold_mb, $row['quotaSMS'] ?? 'N/A', $effective_threshold_sms ) );
	    }

        if ($success) {
	        $count = isset( $row['countNotificationSent'] ) ? intval( $row['countNotificationSent'] ) + 1 : 1;
	        $this->db->update_iccid( $id, [
		        'lastNotificationSent'  => current_time( 'mysql' ),
		        'countNotificationSent' => $count,
		        'error'                 => ''
	        ] );
        } else {
            $this->db->update_iccid($id, ['error' => substr($error_msg, 0, 50)]);
	        gnce_log( sprintf( 'GNCE_Notifications: Notification error for ID %s: %s', $id, $error_msg ) );
        }
    }

    private function gnce_can_send_notification($row)
    {
	    $limit = intval( get_option( 'gnce_notification_limit', 3 ) );
	    if ( $limit > 0 && isset( $row['countNotificationSent'] ) && intval( $row['countNotificationSent'] ) >= $limit ) {
		    return false;
	    }

        $frequency = get_option('gnce_notification_frequency', 'daily');
        if ($frequency === 'never') return false;

        if (!$row['lastNotificationSent']) return true;

        $last_sent = strtotime($row['lastNotificationSent']);
        $now = current_time('timestamp');

        switch ($frequency) {
            case 'daily':
                return ($now - $last_sent) >= DAY_IN_SECONDS;
            case '3days':
                return ($now - $last_sent) >= (3 * DAY_IN_SECONDS);
            case 'weekly':
                return ($now - $last_sent) >= WEEK_IN_SECONDS;
            default:
                return ($now - $last_sent) >= DAY_IN_SECONDS;
        }
    }

    private function gnce_send_email($row)
    {
        $subject = get_option('gnce_email_template_subject');
        $body = get_option('gnce_email_template_body');

        $body = str_replace('{iccid}', $row['iccid'], $body);
        $body = str_replace('{name}', $row['name'], $body);
        $body = str_replace('{quotaMB}', $row['quotaMB'], $body);
        $body = str_replace('{quotaSMS}', $row['quotaSMS'], $body);

        $body = nl2br($body);
        $headers = array('Content-Type: text/html; charset=UTF-8');

	    if ( get_option( 'gnce_stop_all_emails', '0' ) == '1' ) {
		    return;
	    }

        $sent = wp_mail($row['email'], $subject, $body, $headers);
        if (!$sent) {
            throw new Exception(__('wp_mail failed', 'gnce-1nce-products'));
        }
    }

    private function gnce_send_sms($row)
    {
        $template = get_option('gnce_sms_template');
        $message = str_replace('{iccid}', $row['iccid'], $template);
        $message = str_replace('{name}', $row['name'], $message);
        $message = str_replace('{quotaMB}', $row['quotaMB'], $message);
        $message = str_replace('{quotaSMS}', $row['quotaSMS'], $message);

	    if ( get_option( 'gnce_stop_all_sms', '0' ) == '1' ) {
		    return;
	    }

        // Dummy SMS API Call Placeholder
        $this->gnce_dummy_sms_api($row['phone'], $message);
    }

    private function gnce_dummy_sms_api($phone, $message)
    {
        // Mocking the call
        error_log("GNCE Dummy SMS to $phone: $message");
        return true;
    }
}
