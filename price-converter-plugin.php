<?php
/**
 * Plugin Name: Price Converter to Iranian Toman
 * Plugin URI: https://github.com/emjayi/price-converter-plugin
 * Description: A WordPress plugin that converts prices from various sources (like PlayStation website) to Iranian Toman and integrates with WooCommerce.
 * Version: 2.1.0
 * Author: Emjay Sepahi, Saeid6780
 * License: GPL v2 or later
 * Text Domain: price-converter-plugin
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PRICE_CONVERTER_PLUGIN_VERSION', '2.1.0');
define('PRICE_CONVERTER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PRICE_CONVERTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PRICE_CONVERTER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
function price_converter_check_woocommerce()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' .
                __('Price Converter to Iranian Toman requires WooCommerce to be installed and activated.', 'price-converter-plugin') .
                '</p></div>';
        });
        return false;
    }
    return true;
}

// Add Iranian Toman (IRT) currency
add_filter('woocommerce_currencies', function ($currencies) {
    $currencies['IRT'] = __('Iranian Toman', 'price-converter-plugin');
    return $currencies;
});

add_filter('woocommerce_currency_symbol', function ($currency_symbol, $currency) {
    if ($currency === 'IRT') {
        $currency_symbol = __('T', 'price-converter-plugin'); // You can change to \'ت\' or \'تومان\'
    }
    return $currency_symbol;
}, 10, 2);

// Initialize the plugin
function price_converter_init()
{
    if (!price_converter_check_woocommerce()) {
        return;
    }

    // Load text domain for translations
    load_plugin_textdomain(
        'price-converter-plugin',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Add error handling for plugin initialization
    try {
        // Load plugin classes
        require_once PRICE_CONVERTER_PLUGIN_PATH . 'includes/class-price-converter.php';
        require_once PRICE_CONVERTER_PLUGIN_PATH . 'includes/class-price-converter-admin.php';
        require_once PRICE_CONVERTER_PLUGIN_PATH . 'includes/class-price-converter-woocommerce.php';

        // Initialize the main plugin class
        new Price_Converter();

    } catch (Exception $e) {
        // Log the error and show admin notice
        error_log('Price Converter Plugin Error: ' . $e->getMessage());
        add_action('admin_notices', function () use ($e) {
            echo '<div class="notice notice-error"><p>' .
                __('Price Converter Plugin failed to initialize: ', 'price-converter-plugin') .
                esc_html($e->getMessage()) . '</p></div>';
        });
        return;
    }
}
add_action('plugins_loaded', 'price_converter_init');

// Activation hook
register_activation_hook(__FILE__, 'price_converter_activate');
function price_converter_activate()
{
    // Create default options
    $default_options = array(
        'exchange_rate' => 1, // USD -> IRT
        'currency_from' => 'USD',
        'currency_to' => 'IRT',
        'auto_update' => false,
        'update_interval' => 'daily',
        'interest_mode' => 'none', // Default interest mode
        'interest_value' => 0.0 // Default interest value
    );

    add_option('price_converter_settings', $default_options);

    // Create custom table for price history if needed
    global $wpdb;
    $table_name = $wpdb->prefix . 'price_converter_history';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        original_price decimal(10,2) NOT NULL,
        converted_price decimal(15,2) NOT NULL,
        source_url varchar(500) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY product_id (product_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'price_converter_deactivate');
function price_converter_deactivate()
{
    // Clear scheduled events
    wp_clear_scheduled_hook('price_converter_update_prices');
}
