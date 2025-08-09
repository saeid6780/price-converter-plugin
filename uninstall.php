<?php
/**
 * Uninstall Price Converter Plugin
 *
 * @package Price_Converter_Plugin
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('price_converter_settings');

// Delete product meta data
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_price_converter_%'");

// Drop custom table if it exists
$table_name = $wpdb->prefix . 'price_converter_history';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear any scheduled events
wp_clear_scheduled_hook('price_converter_update_prices');
