<?php
/**
 * Test file for Price Converter Plugin Variation Features
 * 
 * This file tests the new functionality:
 * 1. Per-product interest rate settings
 * 2. Variation-specific price converter settings
 * 3. Inheritance of settings from parent products
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Load WordPress
require_once ABSPATH . 'wp-load.php';

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    die('WooCommerce is not active. Please activate WooCommerce first.');
}

// Check if our plugin is active
if (!class_exists('Price_Converter')) {
    die('Price Converter Plugin is not active. Please activate the plugin first.');
}

echo "<h1>Price Converter Plugin - Variation Features Test</h1>\n";

// Test 1: Check if new meta fields are accessible
echo "<h2>Test 1: Meta Fields Accessibility</h2>\n";

$test_product_id = 1; // You may need to change this to an actual product ID
$test_variation_id = 2; // You may need to change this to an actual variation ID

// Test product meta fields
$product_interest_mode = get_post_meta($test_product_id, '_price_converter_interest_mode', true);
$product_interest_value = get_post_meta($test_product_id, '_price_converter_interest_value', true);

echo "Product {$test_product_id} Interest Mode: " . ($product_interest_mode ?: 'Not set') . "\n";
echo "Product {$test_product_id} Interest Value: " . ($product_interest_value ?: 'Not set') . "\n";

// Test variation meta fields
$variation_base_price = get_post_meta($test_variation_id, '_price_converter_base_price', true);
$variation_base_currency = get_post_meta($test_variation_id, '_price_converter_base_currency', true);
$variation_interest_mode = get_post_meta($test_variation_id, '_price_converter_interest_mode', true);
$variation_interest_value = get_post_meta($test_variation_id, '_price_converter_interest_value', true);

echo "Variation {$test_variation_id} Base Price: " . ($variation_base_price ?: 'Not set') . "\n";
echo "Variation {$test_variation_id} Base Currency: " . ($variation_base_currency ?: 'Not set') . "\n";
echo "Variation {$test_variation_id} Interest Mode: " . ($variation_interest_mode ?: 'Not set') . "\n";
echo "Variation {$test_variation_id} Interest Value: " . ($variation_interest_value ?: 'Not set') . "\n";

// Test 2: Check plugin settings
echo "<h2>Test 2: Plugin Settings</h2>\n";

$settings = get_option('price_converter_settings', array());
echo "Default Interest Mode: " . (isset($settings['interest_mode']) ? $settings['interest_mode'] : 'Not set') . "\n";
echo "Default Interest Value: " . (isset($settings['interest_value']) ? $settings['interest_value'] : 'Not set') . "\n";
echo "Exchange Rate: " . (isset($settings['exchange_rate']) ? $settings['exchange_rate'] : 'Not set') . "\n";

// Test 3: Check if hooks are properly registered
echo "<h2>Test 3: Hook Registration</h2>\n";

$hooks = array(
    'woocommerce_product_after_variable_attributes' => has_action('woocommerce_product_after_variable_attributes'),
    'woocommerce_save_product_variation' => has_action('woocommerce_save_product_variation'),
    'woocommerce_product_options_pricing' => has_action('woocommerce_product_options_pricing'),
    'woocommerce_process_product_meta' => has_action('woocommerce_process_product_meta')
);

foreach ($hooks as $hook => $priority) {
    echo "Hook '{$hook}': " . ($priority ? "Registered (Priority: {$priority})" : "Not registered") . "\n";
}

// Test 4: Check if classes are loaded
echo "<h2>Test 4: Class Loading</h2>\n";

$classes = array(
    'Price_Converter' => class_exists('Price_Converter'),
    'Price_Converter_Admin' => class_exists('Price_Converter_Admin'),
    'Price_Converter_WooCommerce' => class_exists('Price_Converter_WooCommerce')
);

foreach ($classes as $class => $exists) {
    echo "Class '{$class}': " . ($exists ? "Loaded" : "Not loaded") . "\n";
}

// Test 5: Check if methods exist
echo "<h2>Test 5: Method Availability</h2>\n";

if (class_exists('Price_Converter_WooCommerce')) {
    $woo_class = new Price_Converter_WooCommerce();
    $methods = array(
        'add_variation_pricing_fields' => method_exists($woo_class, 'add_variation_pricing_fields'),
        'save_variation_data' => method_exists($woo_class, 'save_variation_data'),
        'apply_interest' => method_exists($woo_class, 'apply_interest')
    );

    foreach ($methods as $method => $exists) {
        echo "Method '{$method}': " . ($exists ? "Available" : "Not available") . "\n";
    }
}

echo "<h2>Test Complete</h2>\n";
echo "If you see any 'Not set' or 'Not available' messages, you may need to:\n";
echo "1. Create a product and variation first\n";
echo "2. Set some values in the admin interface\n";
echo "3. Check if the plugin is properly activated\n";

echo "<p><strong>Note:</strong> This is a basic test. For full functionality testing, please use the WooCommerce admin interface.</p>\n";
?>