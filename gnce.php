<?php

/*
Plugin Name: 1NCE management
Description: 1NCE ICCID management and WooCommerce integration.
Version: 2.0
Author: Keramaros Antonios
Author URI: https://keramaros.gr
Text Domain: gnce-1nce-products
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('GNCE_PATH', plugin_dir_path(__FILE__));
define('GNCE_URL', plugin_dir_url(__FILE__));
const GNCE_TRANSIENT_SIM_ICCID = 'gnce_transient_sim_iccid';
const GNCE_TRANSIENT_SIM_ICCID_LIFETIME = MINUTE_IN_SECONDS;

// Autoloader or Manual Includes
require_once GNCE_PATH . 'includes/class-gnce-db.php';
require_once GNCE_PATH . 'includes/class-gnce-api-client.php';
require_once GNCE_PATH . 'includes/class-gnce-list-table.php';
require_once GNCE_PATH . 'includes/class-gnce-product-list-table.php';
require_once GNCE_PATH . 'includes/class-gnce-sync.php';
require_once GNCE_PATH . 'includes/class-gnce-notifications.php';
require_once GNCE_PATH . 'includes/class-gnce-admin.php';

// Other Files
require_once GNCE_PATH . 'ajax.php';
require_once GNCE_PATH . 'shortcode.php';
require_once GNCE_PATH . 'woocommerce.php';

// Plugin Activation
register_activation_hook(__FILE__, 'gnce_plugin_activation');
function gnce_plugin_activation()
{
    $db = new GNCE_DB();
    $db->create_table();

    $sync = new GNCE_Sync();
    $sync->gnce_setup_cron();

    // Set default settings
    if (get_option('gnce_email_template_subject') === false) {
        update_option('gnce_email_template_subject', __('1NCE Quota Alert', 'gnce-1nce-products'));
    }
    if (get_option('gnce_email_template_body') === false) {
        update_option('gnce_email_template_body', __("Hello {name},\n\nYour ICCID {iccid} has breached the threshold.\nCurrent Data: {quotaMB} MB\nCurrent SMS: {quotaSMS} SMS\n\nPlease top up your account.", 'gnce-1nce-products'));
    }
    if (get_option('gnce_sms_template') === false) {
        update_option('gnce_sms_template', __('1NCE Alert: ICCID {iccid} quota low. Data: {quotaMB} MB, SMS: {quotaSMS}.', 'gnce-1nce-products'));
    }
}

// Plugin Deactivation
register_deactivation_hook(__FILE__, 'gnce_plugin_deactivation');
function gnce_plugin_deactivation()
{
    $sync = new GNCE_Sync();
    $sync->gnce_clear_cron();
}

// Initialize Admin
if (is_admin()) {
    new GNCE_Admin();
}

// Initialize Sync to handle crons
new GNCE_Sync();

// Load Textdomain
add_action('init', 'gnce_load_textdomain');
function gnce_load_textdomain()
{
    load_plugin_textdomain('gnce-1nce-products', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/**
 * Enqueue public styles
 */
add_action('wp_enqueue_scripts', 'gnce_enqueue_public_styles');
function gnce_enqueue_public_styles()
{
    wp_enqueue_style('gnce-public', GNCE_URL . 'assets/css/gnce-public.css', array(), '2.0');
    wp_enqueue_style('gnce-loop-styles', GNCE_URL . 'assets/css/loop-styles.css', array(), '2.0');
}

/**
 * Redirect to settings page after activation
 */
add_action('activated_plugin', 'gnce_redirect_on_activation');
function gnce_redirect_on_activation($plugin)
{
    if ($plugin === plugin_basename(__FILE__)) {
        exit(wp_safe_redirect(admin_url('admin.php?page=gnce-settings')));
    }
}
