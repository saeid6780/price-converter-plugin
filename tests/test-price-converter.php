<?php
/**
 * Basic tests for Price Converter Plugin
 *
 * @package Price_Converter_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Price_Converter_Test
{

    /**
     * Run basic tests
     */
    public static function run_tests()
    {
        $tests = array(
            'test_plugin_loaded' => array(__CLASS__, 'test_plugin_loaded'),
            'test_woocommerce_required' => array(__CLASS__, 'test_woocommerce_required'),
            'test_settings_exist' => array(__CLASS__, 'test_settings_exist'),
            'test_price_conversion_irt' => array(__CLASS__, 'test_price_conversion_irt'),
        );

        $results = array();

        foreach ($tests as $test_name => $test_method) {
            try {
                $result = call_user_func($test_method);
                $results[$test_name] = array(
                    'status' => 'PASS',
                    'message' => $result
                );
            } catch (Exception $e) {
                $results[$test_name] = array(
                    'status' => 'FAIL',
                    'message' => $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Test if plugin is loaded
     */
    public static function test_plugin_loaded()
    {
        if (!class_exists('Price_Converter')) {
            throw new Exception('Price_Converter class not found');
        }
        return 'Plugin loaded successfully';
    }

    /**
     * Test if WooCommerce is required
     */
    public static function test_woocommerce_required()
    {
        if (!class_exists('WooCommerce')) {
            throw new Exception('WooCommerce is required but not active');
        }
        return 'WooCommerce is active';
    }

    /**
     * Test if settings exist
     */
    public static function test_settings_exist()
    {
        $settings = get_option('price_converter_settings');
        if (!$settings) {
            throw new Exception('Plugin settings not found');
        }
        return 'Settings exist: ' . json_encode($settings);
    }

    /**
     * Test price conversion to IRT
     */
    public static function test_price_conversion_irt()
    {
        $converter = new Price_Converter();

        // Force exchange rate for test
        update_option('price_converter_settings', array(
            'exchange_rate' => 50000, // 1 USD = 50,000 IRT (example)
            'currency_from' => 'USD',
            'currency_to' => 'IRT',
            'auto_update' => false,
            'update_interval' => 'daily',
        ));

        $converted_price = $converter->convert_to_toman(2); // 2 USD

        if ($converted_price !== 100000) {
            throw new Exception('Price conversion failed. Expected 100000, got: ' . $converted_price);
        }

        return 'Price conversion to IRT working: ' . $converted_price . ' IRT';
    }
}

// Run tests if accessed directly
if (defined('WP_CLI') && WP_CLI) {
    $results = Price_Converter_Test::run_tests();

    foreach ($results as $test_name => $result) {
        $status = $result['status'];
        $message = $result['message'];

        if ($status === 'PASS') {
            WP_CLI::success("✓ {$test_name}: {$message}");
        } else {
            WP_CLI::error("✗ {$test_name}: {$message}");
        }
    }
}
