<?php
/**
 * Plugin Health Test Script
 * 
 * This script tests the Price Converter plugin to ensure it's working correctly
 * and not causing 503 errors.
 * 
 * Place this file in your WordPress root directory and access it via:
 * https://yoursite.com/test-plugin-health.php
 */

// Prevent direct access without WordPress
if (!defined('ABSPATH')) {
    // Load WordPress if not already loaded
    if (!file_exists('wp-config.php')) {
        die('WordPress not found. Please place this file in your WordPress root directory.');
    }

    require_once('wp-config.php');
    require_once('wp-load.php');
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions to run this test.');
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Converter Plugin Health Check</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .content {
            padding: 40px;
        }

        .test-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .test-section h2 {
            color: #23282d;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .test-section h3 {
            color: #23282d;
            margin: 20px 0 15px 0;
            font-size: 1.2em;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .status-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .status-item h4 {
            margin-bottom: 15px;
            color: #23282d;
            font-size: 1.1em;
        }

        .status-ok {
            border-left: 4px solid #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        }

        .status-error {
            border-left: 4px solid #dc3545;
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        }

        .status-warning {
            border-left: 4px solid #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }

        .status-info {
            border-left: 4px solid #17a2b8;
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        }

        .status-gray {
            border-left: 4px solid #6c757d;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .status-icon {
            font-size: 1.5em;
            margin-right: 10px;
            vertical-align: middle;
        }

        .status-text {
            font-weight: 500;
        }

        .currencies-list {
            list-style: none;
            margin: 15px 0;
        }

        .currencies-list li {
            background: white;
            padding: 10px 15px;
            margin: 8px 0;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .currencies-list code {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #495057;
        }

        .test-results {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin: 20px 0;
        }

        .test-results h3 {
            margin-top: 0;
            color: #23282d;
        }

        .recommendations {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .recommendations h2 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        .recommendations ul {
            list-style: none;
            margin: 0;
        }

        .recommendations li {
            padding: 8px 0;
            border-bottom: 1px solid #bbdefb;
        }

        .recommendations li:last-child {
            border-bottom: none;
        }

        .quick-fixes {
            background: #fff3e0;
            border: 1px solid #ffcc02;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .quick-fixes h2 {
            color: #f57c00;
            margin-bottom: 15px;
        }

        .quick-fixes ol {
            margin-left: 20px;
        }

        .quick-fixes li {
            margin: 10px 0;
            line-height: 1.5;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .dashicons {
            font-family: dashicons;
            font-style: normal;
            font-weight: normal;
            font-variant: normal;
            text-transform: none;
            line-height: 1;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .dashicons-yes-alt:before {
            content: "\f504";
        }

        .dashicons-warning:before {
            content: "\f534";
        }

        .dashicons-info:before {
            content: "\f348";
        }

        .dashicons-money-alt:before {
            content: "\f538";
        }

        .dashicons-admin-tools:before {
            content: "\f107";
        }

        .dashicons-chart-line:before {
            content: "\f238";
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 10px;
            }

            .content {
                padding: 20px;
            }

            .status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="content">
            <?php
            // Test 1: Check if plugin is active
            echo '<div class="test-section">';
            echo '<h2>1. Plugin Status</h2>';
            if (is_plugin_active('price-converter-plugin/price-converter-plugin.php')) {
                echo '<div class="status-item status-ok">';
                echo '<span class="dashicons dashicons-yes-alt status-icon"></span>';
                echo '<span class="status-text">Plugin is active and running</span>';
                echo '</div>';
            } else {
                echo '<div class="status-item status-error">';
                echo '<span class="dashicons dashicons-warning status-icon"></span>';
                echo '<span class="status-text">Plugin is not active</span>';
                echo '</div>';
            }
            echo '</div>';

            // Test 2: Check if classes are loaded
            echo '<div class="test-section">';
            echo '<h2>2. Class Loading</h2>';
            echo '<div class="status-grid">';

            if (class_exists('Price_Converter')) {
                echo '<div class="status-item status-ok">';
                echo '<h4>Main Class</h4>';
                echo '<span class="dashicons dashicons-yes-alt status-icon"></span>';
                echo '<span class="status-text">Price_Converter class loaded</span>';
                echo '</div>';
            } else {
                echo '<div class="status-item status-error">';
                echo '<h4>Main Class</h4>';
                echo '<span class="dashicons dashicons-warning status-icon"></span>';
                echo '<span class="status-text">Price_Converter class not loaded</span>';
                echo '</div>';
            }

            if (class_exists('Price_Converter_WooCommerce')) {
                echo '<div class="status-item status-ok">';
                echo '<h4>WooCommerce Integration</h4>';
                echo '<span class="dashicons dashicons-yes-alt status-icon"></span>';
                echo '<span class="status-text">WooCommerce class loaded</span>';
                echo '</div>';
            } else {
                echo '<div class="status-item status-error">';
                echo '<h4>WooCommerce Integration</h4>';
                echo '<span class="dashicons dashicons-warning status-icon"></span>';
                echo '<span class="status-text">WooCommerce class not loaded</span>';
                echo '</div>';
            }

            if (class_exists('Price_Converter_Admin')) {
                echo '<div class="status-item status-ok">';
                echo '<h4>Admin Interface</h4>';
                echo '<span class="dashicons dashicons-yes-alt status-icon"></span>';
                echo '<span class="status-text">Admin class loaded</span>';
                echo '</div>';
            } else {
                echo '<div class="status-item status-error">';
                echo '<h4>Admin Interface</h4>';
                echo '<span class="dashicons dashicons-warning status-icon"></span>';
                echo '<span class="status-text">Admin class not loaded</span>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';

            // Test 3: Check settings
            echo '<div class="test-section">';
            echo '<h2>3. Plugin Settings</h2>';
            $settings = get_option('price_converter_settings', array());

            echo '<div class="status-grid">';
            echo '<div class="status-item status-info">';
            echo '<h4>Exchange Rate</h4>';
            echo '<span class="status-text">' . (isset($settings['exchange_rate']) ? $settings['exchange_rate'] : 'Not set') . '</span>';
            echo '</div>';

            echo '<div class="status-item status-info">';
            echo '<h4>Fallback Mode</h4>';
            echo '<span class="status-text">' . (isset($settings['fallback_mode']) ? $settings['fallback_mode'] : 'enabled') . '</span>';
            echo '</div>';

            echo '<div class="status-item status-info">';
            echo '<h4>API Key</h4>';
            echo '<span class="status-text">' . (isset($settings['navasan_api_key']) && !empty($settings['navasan_api_key']) ? 'Set' : 'Not set') . '</span>';
            echo '</div>';
            echo '</div>';

            // Test custom currencies
            if (isset($settings['custom_currencies']) && !empty($settings['custom_currencies'])) {
                try {
                    $custom_currencies = json_decode($settings['custom_currencies'], true);
                    if (is_array($custom_currencies) && !empty($custom_currencies)) {
                        echo '<div class="status-item status-ok">';
                        echo '<h4>Custom Currencies</h4>';
                        echo '<span class="status-text">' . count($custom_currencies) . ' currencies configured</span>';
                        echo '<ul class="currencies-list">';
                        foreach ($custom_currencies as $code => $rate) {
                            echo '<li>';
                            echo '<code>' . esc_html($code) . '</code>';
                            echo '<span>' . number_format($rate, 0, '.', ',') . ' IRT</span>';
                            echo '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    } else {
                        echo '<div class="status-item status-warning">';
                        echo '<h4>Custom Currencies</h4>';
                        echo '<span class="status-text">Invalid format</span>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="status-item status-error">';
                    echo '<h4>Custom Currencies</h4>';
                    echo '<span class="status-text">Error: ' . esc_html($e->getMessage()) . '</span>';
                    echo '</div>';
                }
            } else {
                echo '<div class="status-item status-gray">';
                echo '<h4>Custom Currencies</h4>';
                echo '<span class="status-text">None configured</span>';
                echo '</div>';
            }
            echo '</div>';

            // Test 4: Check transients
            echo '<div class="test-section">';
            echo '<h2>4. System Status</h2>';
            $api_processing = get_transient('price_converter_api_processing');
            $latest_rates = get_transient('price_converter_latest_rates');

            echo '<div class="status-grid">';
            echo '<div class="status-item ' . ($api_processing ? 'status-warning' : 'status-ok') . '">';
            echo '<h4>API Processing Flag</h4>';
            echo '<span class="status-text">' . ($api_processing ? 'Active' : 'Inactive') . '</span>';
            echo '</div>';

            echo '<div class="status-item ' . ($latest_rates ? 'status-ok' : 'status-gray') . '">';
            echo '<h4>Latest Rates Cache</h4>';
            echo '<span class="status-text">' . ($latest_rates ? 'Cached (' . count($latest_rates) . ' items)' : 'Not cached') . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Test 5: Test API connectivity (if API key is set)
            echo '<div class="test-section">';
            echo '<h2>5. API Connectivity Test</h2>';
            if (!empty($settings['navasan_api_key'])) {
                echo '<p>Testing API connectivity...</p>';

                $url = add_query_arg(array('api_key' => $settings['navasan_api_key']), 'http://api.navasan.tech/latest/');
                $response = wp_remote_get($url, array('timeout' => 10));

                if (is_wp_error($response)) {
                    echo '<div class="status-item status-error">';
                    echo '<span class="dashicons dashicons-warning status-icon"></span>';
                    echo '<span class="status-text">API Error: ' . $response->get_error_message() . '</span>';
                    echo '</div>';
                } else {
                    $response_code = wp_remote_retrieve_response_code($response);
                    if ($response_code === 200) {
                        echo '<div class="status-item status-ok">';
                        echo '<span class="dashicons dashicons-yes-alt status-icon"></span>';
                        echo '<span class="status-text">API is accessible (HTTP ' . $response_code . ')</span>';
                        echo '</div>';
                    } else {
                        echo '<div class="status-item status-warning">';
                        echo '<span class="dashicons dashicons-warning status-icon"></span>';
                        echo '<span class="status-text">API returned HTTP ' . $response_code . '</span>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<div class="status-item status-gray">';
                echo '<span class="dashicons dashicons-info status-icon"></span>';
                echo '<span class="status-text">No API key configured - skipping API test</span>';
                echo '</div>';
            }
            echo '</div>';

            // Test 6: Check for errors in error log
            echo '<div class="test-section">';
            echo '<h2>6. Recent Errors</h2>';
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file)) {
                $log_content = file_get_contents($log_file);
                $price_converter_errors = array();

                // Look for recent Price Converter errors (last 100 lines)
                $lines = explode("\n", $log_content);
                $recent_lines = array_slice($lines, -100);

                foreach ($recent_lines as $line) {
                    if (strpos($line, 'Price Converter') !== false) {
                        $price_converter_errors[] = $line;
                    }
                }

                if (!empty($price_converter_errors)) {
                    echo '<div class="status-item status-warning">';
                    echo '<h4>Found ' . count($price_converter_errors) . ' recent Price Converter errors:</h4>';
                    echo '<ul class="currencies-list">';
                    foreach (array_slice($price_converter_errors, -5) as $error) { // Show last 5
                        echo '<li>' . esc_html($error) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="status-item status-ok">';
                    echo '<span class="dashicons dashicons-yes-alt status-icon"></span>';
                    echo '<span class="status-text">No recent Price Converter errors found</span>';
                    echo '</div>';
                }
            } else {
                echo '<div class="status-item status-gray">';
                echo '<span class="dashicons dashicons-info status-icon"></span>';
                echo '<span class="status-text">Debug log not found</span>';
                echo '</div>';
            }
            echo '</div>';

            // Test 7: Performance test
            echo '<div class="test-section">';
            echo '<h2>7. Performance Test</h2>';
            $start_time = microtime(true);

            // Simulate a simple price conversion
            try {
                $test_price = 100;
                $test_currency = 'USD';

                // This should not cause any issues
                $settings = get_option('price_converter_settings', array());
                $exchange_rate = isset($settings['exchange_rate']) ? floatval($settings['exchange_rate']) : 1.0;
                $converted = $test_price * $exchange_rate;

                $end_time = microtime(true);
                $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
            
                echo '<div class="status-item status-ok">';
                echo '<h4>Price Conversion Test</h4>';
                echo '<span class="status-text">Completed in ' . round($execution_time, 2) . 'ms</span>';
                echo '<div class="test-results">';
                echo '<strong>Test Result:</strong> $' . $test_price . ' USD = ' . round($converted, 2) . ' IRT';
                echo '</div>';
                echo '</div>';

            } catch (Exception $e) {
                echo '<div class="status-item status-error">';
                echo '<h4>Price Conversion Test</h4>';
                echo '<span class="status-text">Failed: ' . $e->getMessage() . '</span>';
                echo '</div>';
            }

            // Test custom currency conversion
            echo '<h3>Custom Currency Test</h3>';
            if (isset($settings['custom_currencies']) && !empty($settings['custom_currencies'])) {
                try {
                    $custom_currencies = json_decode($settings['custom_currencies'], true);
                    if (is_array($custom_currencies) && !empty($custom_currencies)) {
                        $test_currency = array_keys($custom_currencies)[0]; // Use first custom currency
                        $test_rate = $custom_currencies[$test_currency];
                        $test_amount = 1;
                        $converted_custom = $test_amount * $test_rate;

                        echo '<div class="status-item status-ok">';
                        echo '<h4>Custom Currency Test</h4>';
                        echo '<span class="status-text">Completed successfully</span>';
                        echo '<div class="test-results">';
                        echo '<strong>Test Result:</strong> ' . $test_amount . ' ' . $test_currency . ' = ' . number_format($converted_custom, 0, '.', ',') . ' IRT';
                        echo '</div>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="status-item status-error">';
                    echo '<h4>Custom Currency Test</h4>';
                    echo '<span class="status-text">Failed: ' . $e->getMessage() . '</span>';
                    echo '</div>';
                }
            } else {
                echo '<div class="status-item status-gray">';
                echo '<span class="dashicons dashicons-info status-icon"></span>';
                echo '<span class="status-text">No custom currencies configured - skipping test</span>';
                echo '</div>';
            }
            echo '</div>';

            // Test 8: Recommendations
            echo '<div class="recommendations">';
            echo '<h2>8. Recommendations</h2>';
            echo '<ul>';

            if (empty($settings['navasan_api_key'])) {
                echo '<li><span class="dashicons dashicons-warning" style="color: #ffc107; margin-right: 8px;"></span>Consider setting up an API key for automatic rate updates</li>';
            } else {
                echo '<li><span class="dashicons dashicons-yes-alt" style="color: #28a745; margin-right: 8px;"></span>API key is configured</li>';
            }

            if (isset($settings['custom_currencies']) && !empty($settings['custom_currencies'])) {
                echo '<li><span class="dashicons dashicons-yes-alt" style="color: #28a745; margin-right: 8px;"></span>Custom currencies are configured</li>';
            } else {
                echo '<li><span class="dashicons dashicons-info" style="color: #17a2b8; margin-right: 8px;"></span>Consider adding custom currencies for specific needs (crypto, precious metals, etc.)</li>';
            }

            if (isset($settings['fallback_mode']) && $settings['fallback_mode'] === 'disabled') {
                echo '<li><span class="dashicons dashicons-info" style="color: #17a2b8; margin-right: 8px;"></span>Plugin is running in fallback mode (price conversion disabled)</li>';
            } else {
                echo '<li><span class="dashicons dashicons-yes-alt" style="color: #28a745; margin-right: 8px;"></span>Plugin is running in normal mode</li>';
            }

            if ($api_processing) {
                echo '<li><span class="dashicons dashicons-warning" style="color: #ffc107; margin-right: 8px;"></span>API processing flag is stuck - this might indicate an issue</li>';
            } else {
                echo '<li><span class="dashicons dashicons-yes-alt" style="color: #28a745; margin-right: 8px;"></span>API processing flag is clear</li>';
            }

            echo '</ul>';
            echo '</div>';

            echo '<div class="quick-fixes">';
            echo '<h2>9. Quick Fixes</h2>';
            echo '<p>If you\'re experiencing 503 errors:</p>';
            echo '<ol>';
            echo '<li>Go to WooCommerce → Price Converter settings</li>';
            echo '<li>Set "Fallback Mode" to "Disabled" to temporarily disable price conversion</li>';
            echo '<li>Check your server error logs for specific error messages</li>';
            echo '<li>Ensure your server has sufficient memory and execution time limits</li>';
            echo '</ol>';
            echo '</div>';
            ?>
        </div>

        <div class="footer">
            <p><em>Test completed at: <?php echo date('Y-m-d H:i:s'); ?></em></p>
            <p><a href="<?php echo admin_url('admin.php?page=price-converter-settings'); ?>">Go to Plugin Settings</a>
            </p>
            <p>&copy; <?php echo date('Y'); ?> <a href="https://emjaysepahi.com" target="_blank">Emjay Sepahi</a>
                (emjaysepahi.com) - All rights reserved</p>
        </div>
    </div>
</body>

</html>