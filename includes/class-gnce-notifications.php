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

        $threshold_breached = false;
        if ($row['quotaMB'] < $row['thresholdMB'] || $row['quotaSMS'] < $row['thresholdSMS']) {
            $threshold_breached = true;
        }

        if (!$threshold_breached) return;

        if (!$this->gnce_can_send_notification($row)) return;

        $success = true;
        $error_msg = '';

        if ($row['notifyByEmail'] && !empty($row['email'])) {
            try {
                $this->gnce_send_email($row);
            } catch (Exception $e) {
                $success = false;
                $error_msg .= 'Email Error: ' . substr($e->getMessage(), 0, 20) . '; ';
            }
        }

        if ($row['notifyBySMS'] && !empty($row['phone'])) {
            try {
                $this->gnce_send_sms($row);
            } catch (Exception $e) {
                $success = false;
                $error_msg .= 'SMS Error: ' . substr($e->getMessage(), 0, 20) . '; ';
            }
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
