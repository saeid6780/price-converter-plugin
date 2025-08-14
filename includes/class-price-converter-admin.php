<?php
/**
 * Admin functionality for Price Converter Plugin
 *
 * @package Price_Converter_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Price_Converter_Admin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Price Converter Settings', 'price-converter-plugin'),
            __('Price Converter', 'price-converter-plugin'),
            'manage_woocommerce',
            'price-converter-settings',
            array($this, 'settings_page')
        );
    }

    public function init_settings()
    {
        register_setting('price_converter_settings', 'price_converter_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'price_converter_general',
            __('General Settings', 'price-converter-plugin'),
            array($this, 'general_section_callback'),
            'price_converter_settings'
        );

        add_settings_field(
            'exchange_rate',
            __('Exchange Rate (USD to IRT)', 'price-converter-plugin'),
            array($this, 'exchange_rate_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'price_interval',
            __('Price Update Interval', 'price-converter-plugin'),
            array($this, 'price_interval_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'custom_rates',
            __('Custom Currency Rates (JSON)', 'price-converter-plugin'),
            array($this, 'custom_rates_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'custom_currencies',
            __('Custom Currencies', 'price-converter-plugin'),
            array($this, 'custom_currencies_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'interest_mode',
            __('Interest Mode', 'price-converter-plugin'),
            array($this, 'interest_mode_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'interest_value',
            __('Interest Value', 'price-converter-plugin'),
            array($this, 'interest_value_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_section(
            'price_converter_navasan',
            __('Navasan Web Service (optional)', 'price-converter-plugin'),
            array($this, 'navasan_section_callback'),
            'price_converter_settings'
        );

        add_settings_field(
            'navasan_api_key',
            __('API Key', 'price-converter-plugin'),
            array($this, 'navasan_api_key_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'navasan_item',
            __('Default Item (USD source)', 'price-converter-plugin'),
            array($this, 'navasan_item_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'fallback_mode',
            __('Fallback Mode', 'price-converter-plugin'),
            array($this, 'fallback_mode_callback'),
            'price_converter_settings',
            'price_converter_general'
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook === 'woocommerce_page_price-converter-settings' || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script(
                'price-converter-admin',
                PRICE_CONVERTER_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                PRICE_CONVERTER_PLUGIN_VERSION,
                true
            );
            wp_localize_script('price-converter-admin', 'priceConverterAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('price_converter_nonce'),
                'strings' => array(
                    'fetching' => __('Fetching price...', 'price-converter-plugin'),
                    'converting' => __('Converting price...', 'price-converter-plugin'),
                    'error' => __('Error occurred', 'price-converter-plugin'),
                    'success' => __('Price fetched successfully', 'price-converter-plugin')
                )
            ));
            wp_enqueue_style(
                'price-converter-admin',
                PRICE_CONVERTER_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                PRICE_CONVERTER_PLUGIN_VERSION
            );
        }
    }

    public function settings_page()
    {
        $locale = get_locale();
        $is_persian = in_array($locale, array('fa_IR', 'fa_AF', 'ps_AF'));
        ?>
        <div class="wrap price-converter-admin">
            <div class="price-converter-content">
                <div class="price-converter-main">
                    <form method="post" action="options.php" class="price-converter-form">
                        <?php
                        settings_fields('price_converter_settings');
                        do_settings_sections('price_converter_settings');
                        ?>
                        <div class="price-converter-submit">
                            <?php submit_button(__('Save Settings', 'price-converter-plugin'), 'primary', 'submit', false); ?>
                            <span class="spinner" style="float:none;margin:0 10px;"></span>
                        </div>
                    </form>
                </div>

                <div class="price-converter-sidebar">
                    <div class="price-converter-widget">
                        <h3><span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Quick Actions', 'price-converter-plugin'); ?></h3>
                        <div class="price-converter-actions">
                            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-cart"></span>
                                <?php _e('Manage Products', 'price-converter-plugin'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=wc-settings'); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php _e('WooCommerce Settings', 'price-converter-plugin'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="price-converter-widget">
                        <h3><span class="dashicons dashicons-info"></span>
                            <?php _e('Help & Support', 'price-converter-plugin'); ?></h3>
                        <div class="price-converter-help">
                            <p><?php _e('Need help? Check our documentation or contact support.', 'price-converter-plugin'); ?>
                            </p>
                            <a href="#" class="button button-secondary"
                                onclick="window.open('https://github.com/your-username/price-converter-plugin', '_blank')">
                                <span class="dashicons dashicons-book"></span>
                                <?php _e('Documentation', 'price-converter-plugin'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="price-converter-widget">
                        <h3><span class="dashicons dashicons-chart-line"></span>
                            <?php _e('Status Overview', 'price-converter-plugin'); ?></h3>
                        <div class="price-converter-status">
                            <?php
                            $settings = get_option('price_converter_settings', array());
                            $api_key = isset($settings['navasan_api_key']) && !empty($settings['navasan_api_key']);
                            $custom_currencies = isset($settings['custom_currencies']) && !empty($settings['custom_currencies']);
                            $fallback_mode = isset($settings['fallback_mode']) ? $settings['fallback_mode'] : 'enabled';
                            $price_interval = isset($settings['price_interval']) ? $settings['price_interval'] : '1hour';
                            ?>
                            <div class="status-item <?php echo $api_key ? 'status-ok' : 'status-warning'; ?>">
                                <span class="dashicons dashicons-<?php echo $api_key ? 'yes-alt' : 'warning'; ?>"></span>
                                <span><?php _e('API Connection:', 'price-converter-plugin'); ?>
                                    <?php echo $api_key ? __('Connected', 'price-converter-plugin') : __('Not Configured', 'price-converter-plugin'); ?></span>
                            </div>
                            <div class="status-item <?php echo $custom_currencies ? 'status-ok' : 'status-info'; ?>">
                                <span class="dashicons dashicons-<?php echo $custom_currencies ? 'yes-alt' : 'info'; ?>"></span>
                                <span><?php _e('Custom Currencies:', 'price-converter-plugin'); ?>
                                    <?php echo $custom_currencies ? __('Configured', 'price-converter-plugin') : __('None Set', 'price-converter-plugin'); ?></span>
                            </div>
                            <div
                                class="status-item <?php echo $fallback_mode === 'enabled' ? 'status-ok' : 'status-warning'; ?>">
                                <span
                                    class="dashicons dashicons-<?php echo $fallback_mode === 'enabled' ? 'yes-alt' : 'warning'; ?>"></span>
                                <span><?php _e('Price Conversion:', 'price-converter-plugin'); ?>
                                    <?php echo $fallback_mode === 'enabled' ? __('Active', 'price-converter-plugin') : __('Disabled', 'price-converter-plugin'); ?></span>
                            </div>
                            <div class="status-item status-info">
                                <span class="dashicons dashicons-clock"></span>
                                <span><?php _e('Update Interval:', 'price-converter-plugin'); ?>
                                    <?php echo $this->get_interval_label($price_interval); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="price-converter-test-section">
                <div class="test-section-header">
                    <h2><span class="dashicons dashicons-testing"></span>
                        <?php _e('Test Price Fetching', 'price-converter-plugin'); ?></h2>
                    <p><?php _e('Test the price fetching functionality with a URL to ensure everything is working correctly.', 'price-converter-plugin'); ?>
                    </p>
                </div>

                <div class="test-section-content">
                    <div class="test-form-row">
                        <div class="test-form-group">
                            <label for="test_url"><?php _e('Test URL', 'price-converter-plugin'); ?></label>
                            <input type="url" id="test_url" name="test_url" class="regular-text"
                                placeholder="https://example.com/product-page">
                            <p class="description"><?php _e('Enter a URL to test price fetching', 'price-converter-plugin'); ?>
                            </p>
                        </div>

                        <div class="test-form-group">
                            <label
                                for="test_selector"><?php _e('CSS Selector or data-qa (Optional)', 'price-converter-plugin'); ?></label>
                            <input type="text" id="test_selector" name="test_selector" class="regular-text"
                                placeholder=".price, #price, data-qa=&quot;price&quot;">
                            <p class="description">
                                <?php _e('CSS selector or data-qa attribute for price extraction', 'price-converter-plugin'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="test-actions">
                        <button type="button" id="test_fetch_price" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Test Fetch Price', 'price-converter-plugin'); ?>
                        </button>
                        <button type="button" id="test_convert_price" class="button button-secondary" style="display:none;">
                            <span class="dashicons dashicons-calculator"></span>
                            <?php _e('Convert to IRT', 'price-converter-plugin'); ?>
                        </button>
                        <span id="pc_fetch_spinner" class="spinner" style="float:none;margin:0 10px;display:none;"></span>
                    </div>

                    <div id="test_results" class="test-results" style="display:none;">
                        <h3><?php _e('Test Results', 'price-converter-plugin'); ?></h3>
                        <div id="test_results_content"></div>
                    </div>
                </div>
            </div>

            <div class="price-converter-footer">
                <p>&copy; <?php echo date('Y'); ?> <a href="https://emjaysepahi.com" target="_blank">Emjay Sepahi</a>
                    (emjaysepahi.com) - <?php _e('All rights reserved', 'price-converter-plugin'); ?></p>
            </div>
        </div>

        <style>
            .price-converter-admin {
                max-width: 1200px;
                margin: 20px 0;
            }

            .price-converter-content {
                display: flex;
                gap: 30px;
            }

            .price-converter-main {
                flex: 2;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .price-converter-sidebar {
                flex: 1;
            }

            .price-converter-widget {
                background: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                margin-bottom: 20px;
            }

            .price-converter-widget h3 {
                margin-top: 0;
                color: #23282d;
                border-bottom: 2px solid #667eea;
                padding-bottom: 10px;
            }

            .price-converter-widget .dashicons {
                color: #667eea;
                margin-right: 8px;
            }

            .price-converter-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .price-converter-actions .button {
                text-align: left;
                padding: 12px 15px;
                height: auto;
                line-height: 1.4;
            }

            .price-converter-actions .dashicons {
                margin-right: 8px;
                vertical-align: middle;
            }

            .price-converter-status {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .status-item {
                display: flex;
                align-items: center;
                padding: 12px;
                border-radius: 6px;
                background: #f8f9fa;
            }

            .status-item.status-ok {
                background: #d4edda;
                color: #155724;
                border-left: 4px solid #28a745;
            }

            .status-item.status-warning {
                background: #fff3cd;
                color: #856404;
                border-left: 4px solid #ffc107;
            }

            .status-item.status-info {
                background: #d1ecf1;
                color: #0c5460;
                border-left: 4px solid #17a2b8;
            }

            .status-item .dashicons {
                margin-right: 10px;
                font-size: 1.2em;
            }

            .price-converter-submit {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e1e1e1;
                text-align: center;
            }

            .price-converter-test-section {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                margin-top: 30px;
            }

            .test-section-header {
                text-align: center;
                margin-bottom: 30px;
            }

            .test-section-header h2 {
                color: #23282d;
                margin-bottom: 10px;
            }

            .test-section-header .dashicons {
                color: #667eea;
                margin-right: 10px;
            }

            .test-form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-bottom: 20px;
            }

            .test-form-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #23282d;
            }

            .test-form-group input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e1e1e1;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.3s ease;
            }

            .test-form-group input:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .test-form-group .description {
                margin-top: 8px;
                color: #666;
                font-size: 13px;
            }

            .test-actions {
                text-align: center;
                margin-bottom: 30px;
            }

            .test-actions .button {
                margin: 0 10px;
                padding: 12px 20px;
                height: auto;
                font-size: 14px;
            }

            .test-actions .dashicons {
                margin-right: 8px;
                vertical-align: middle;
            }

            .test-results {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 6px;
                border-left: 4px solid #667eea;
            }

            .test-results h3 {
                margin-top: 0;
                color: #23282d;
            }

            .price-converter-footer {
                background: #f8f9fa;
                padding: 20px;
                text-align: center;
                border-top: 1px solid #e9ecef;
                margin-top: 30px;
                border-radius: 10px;
            }

            .price-converter-footer a {
                color: #667eea;
                text-decoration: none;
                font-weight: 500;
            }

            .price-converter-footer a:hover {
                text-decoration: underline;
            }

            @media (max-width: 768px) {
                .price-converter-content {
                    flex-direction: column;
                }

                .test-form-row {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    public function general_section_callback()
    {
        echo '<p>' . __('Configure the price converter settings below.', 'price-converter-plugin') . '</p>';
    }

    public function navasan_section_callback()
    {
        echo '<p>' . esc_html__('If you provide a Navasan API Key, the plugin will fetch USD→IRR (and others) and convert to IRT by removing one zero.', 'price-converter-plugin') . '</p>';
    }

    public function exchange_rate_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['exchange_rate']) ? $options['exchange_rate'] : 1;
        ?>
        <input type="number" step="0.01" min="0" name="price_converter_settings[exchange_rate]"
            value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Fallback USD→IRT when API/rates are unavailable.', 'price-converter-plugin'); ?></p>
        <?php
    }

    public function custom_rates_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['custom_rates']) ? $options['custom_rates'] : '';
        ?>
        <textarea name="price_converter_settings[custom_rates]" rows="6" class="large-text"
            placeholder='{"EUR":60000,"GBP":70000,"AED":15000}'><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('JSON map of per-currency rates to IRT, used when API does not provide that currency.', 'price-converter-plugin'); ?>
        </p>
        <?php
    }

    public function custom_currencies_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['custom_currencies']) ? $options['custom_currencies'] : '';

        if (!empty($value)) {
            try {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && !empty($decoded)) {
                    echo '<div class="price-converter-current-currencies">';
                    echo '<h4><span class="dashicons dashicons-yes-alt"></span> ' . __('Current Custom Currencies', 'price-converter-plugin') . '</h4>';
                    echo '<div class="currencies-grid">';
                    foreach ($decoded as $code => $rate) {
                        echo '<div class="currency-item">';
                        echo '<span class="currency-code">' . esc_html($code) . '</span>';
                        echo '<span class="currency-rate">' . number_format($rate, 0, '.', ',') . ' IRT</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="price-converter-error">';
                echo '<h4><span class="dashicons dashicons-warning"></span> ' . __('Error Reading Custom Currencies', 'price-converter-plugin') . '</h4>';
                echo '<p>' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        ?>
        <div class="price-converter-input-group">
            <textarea name="price_converter_settings[custom_currencies]" rows="8"
                class="large-text code price-converter-textarea" placeholder='{
  "BTC": 1500000000,
  "ETH": 100000000,
  "GOLD": 5000000,
  "SILVER": 50000
}'>        <?php echo esc_textarea($value); ?></textarea>

            <div class="price-converter-help-section">
                <h4><span class="dashicons dashicons-info"></span> <?php _e('How to Use', 'price-converter-plugin'); ?></h4>
                <p><?php _e('Add custom currencies with their exchange rates to IRT. Format: {"CURRENCY_CODE": EXCHANGE_RATE}. Example: {"BTC": 1500000000} means 1 BTC = 1,500,000,000 IRT.', 'price-converter-plugin'); ?>
                </p>

                <div class="examples-section">
                    <h5><?php _e('Popular Examples:', 'price-converter-plugin'); ?></h5>
                    <div class="examples-grid">
                        <div class="example-item">
                            <code>{"BTC": 1500000000}</code>
                            <span class="example-label">Bitcoin</span>
                        </div>
                        <div class="example-item">
                            <code>{"ETH": 100000000}</code>
                            <span class="example-label">Ethereum</span>
                        </div>
                        <div class="example-item">
                            <code>{"GOLD": 5000000}</code>
                            <span class="example-label">Gold per gram</span>
                        </div>
                        <div class="example-item">
                            <code>{"SILVER": 50000}</code>
                            <span class="example-label">Silver per gram</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .price-converter-current-currencies {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                border: 1px solid #c3e6cb;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .price-converter-current-currencies h4 {
                margin: 0 0 15px 0;
                color: #155724;
                font-size: 16px;
                font-weight: 600;
            }

            .price-converter-current-currencies .dashicons {
                color: #28a745;
                margin-right: 8px;
            }

            .currencies-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }

            .currency-item {
                background: white;
                padding: 15px;
                border-radius: 6px;
                border: 1px solid #c3e6cb;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .currency-code {
                font-weight: 600;
                color: #155724;
                font-size: 16px;
            }

            .currency-rate {
                color: #666;
                font-size: 14px;
            }

            .price-converter-error {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                border: 1px solid #f5c6cb;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .price-converter-error h4 {
                margin: 0 0 10px 0;
                color: #721c24;
                font-size: 16px;
                font-weight: 600;
            }

            .price-converter-error .dashicons {
                color: #dc3545;
                margin-right: 8px;
            }

            .price-converter-input-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                align-items: start;
            }

            .price-converter-textarea {
                border: 2px solid #e1e1e1 !important;
                border-radius: 8px !important;
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace !important;
                font-size: 13px !important;
                line-height: 1.4 !important;
                transition: border-color 0.3s ease !important;
            }

            .price-converter-textarea:focus {
                border-color: #667eea !important;
                outline: none !important;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
            }

            .price-converter-help-section {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 20px;
            }

            .price-converter-help-section h4 {
                margin: 0 0 15px 0;
                color: #23282d;
                font-size: 16px;
                font-weight: 600;
                border-bottom: 2px solid #667eea;
                padding-bottom: 10px;
            }

            .price-converter-help-section .dashicons {
                color: #667eea;
                margin-right: 8px;
            }

            .price-converter-help-section p {
                color: #666;
                margin-bottom: 20px;
                line-height: 1.5;
            }

            .examples-section h5 {
                margin: 0 0 15px 0;
                color: #23282d;
                font-size: 14px;
                font-weight: 600;
            }

            .examples-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }

            .example-item {
                background: white;
                padding: 12px;
                border-radius: 6px;
                border: 1px solid #dee2e6;
                text-align: center;
            }

            .example-item code {
                display: block;
                background: #e9ecef;
                padding: 8px;
                border-radius: 4px;
                font-size: 12px;
                margin-bottom: 8px;
                color: #495057;
            }

            .example-label {
                display: block;
                color: #666;
                font-size: 12px;
                font-style: italic;
            }

            @media (max-width: 768px) {
                .price-converter-input-group {
                    grid-template-columns: 1fr;
                }

                .currencies-grid {
                    grid-template-columns: 1fr;
                }

                .examples-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    public function interest_mode_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['interest_mode']) ? $options['interest_mode'] : 'none';
        ?>
        <select name="price_converter_settings[interest_mode]">
            <option value="none" <?php selected($value, 'none'); ?>><?php _e('None', 'price-converter-plugin'); ?></option>
            <option value="percent" <?php selected($value, 'percent'); ?>><?php _e('Percent', 'price-converter-plugin'); ?>
            </option>
            <option value="fixed" <?php selected($value, 'fixed'); ?>>
                <?php _e('Fixed Amount (IRT)', 'price-converter-plugin'); ?>
            </option>
        </select>
        <p class="description"><?php _e('Select interest mode to apply on converted prices.', 'price-converter-plugin'); ?></p>
        <?php
    }

    public function interest_value_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['interest_value']) ? $options['interest_value'] : 0;
        ?>
        <input type="number" step="0.01" min="0" name="price_converter_settings[interest_value]"
            value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('If Percent mode: value is percentage. If Fixed mode: value is IRT amount.', 'price-converter-plugin'); ?>
        </p>
        <?php
    }

    public function price_interval_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['price_interval']) ? $options['price_interval'] : 'daily';
        ?>
        <select name="price_converter_settings[price_interval]">
            <option value="30min" <?php selected($value, '30min'); ?>><?php _e('30 Minutes', 'price-converter-plugin'); ?>
            </option>
            <option value="1hour" <?php selected($value, '1hour'); ?>><?php _e('1 Hour', 'price-converter-plugin'); ?></option>
            <option value="2hour" <?php selected($value, '2hour'); ?>><?php _e('2 Hours', 'price-converter-plugin'); ?></option>
            <option value="4hour" <?php selected($value, '4hour'); ?>><?php _e('4 Hours', 'price-converter-plugin'); ?></option>
            <option value="6hour" <?php selected($value, '6hour'); ?>><?php _e('6 Hours', 'price-converter-plugin'); ?></option>
            <option value="12hour" <?php selected($value, '12hour'); ?>><?php _e('12 Hours', 'price-converter-plugin'); ?>
            </option>
            <option value="daily" <?php selected($value, 'daily'); ?>><?php _e('Daily', 'price-converter-plugin'); ?></option>
            <option value="weekly" <?php selected($value, 'weekly'); ?>><?php _e('Weekly', 'price-converter-plugin'); ?>
            </option>
            <option value="monthly" <?php selected($value, 'monthly'); ?>><?php _e('Monthly', 'price-converter-plugin'); ?>
            </option>
        </select>
        <p class="description">
            <?php _e('Select how often prices should be updated from external sources.', 'price-converter-plugin'); ?></p>
        <?php
    }

    private function get_interval_label($interval)
    {
        $labels = array(
            '30min' => __('30 Minutes', 'price-converter-plugin'),
            '1hour' => __('1 Hour', 'price-converter-plugin'),
            '2hour' => __('2 Hours', 'price-converter-plugin'),
            '4hour' => __('4 Hours', 'price-converter-plugin'),
            '6hour' => __('6 Hours', 'price-converter-plugin'),
            '12hour' => __('12 Hours', 'price-converter-plugin'),
            'daily' => __('Daily', 'price-converter-plugin'),
            'weekly' => __('Weekly', 'price-converter-plugin'),
            'monthly' => __('Monthly', 'price-converter-plugin')
        );
        return isset($labels[$interval]) ? $labels[$interval] : $interval;
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();
        if (isset($input['exchange_rate'])) {
            $sanitized['exchange_rate'] = floatval($input['exchange_rate']);
        }
        if (isset($input['price_interval'])) {
            $sanitized['price_interval'] = sanitize_text_field($input['price_interval']);
        }
        if (isset($input['custom_rates'])) {
            $sanitized['custom_rates'] = wp_kses_post($input['custom_rates']);
        }
        if (isset($input['custom_currencies'])) {
            $custom_currencies = trim($input['custom_currencies']);
            if (!empty($custom_currencies)) {
                $decoded = json_decode($custom_currencies, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    add_settings_error(
                        'price_converter_settings',
                        'invalid_custom_currencies',
                        __('Custom currencies must be valid JSON format.', 'price-converter-plugin')
                    );
                    $sanitized['custom_currencies'] = '';
                } else {
                    $valid_currencies = array();
                    foreach ($decoded as $code => $rate) {
                        if (is_string($code) && !empty($code) && is_numeric($rate) && $rate > 0) {
                            $valid_currencies[strtoupper(trim($code))] = floatval($rate);
                        }
                    }
                    $sanitized['custom_currencies'] = json_encode($valid_currencies, JSON_PRETTY_PRINT);
                }
            } else {
                $sanitized['custom_currencies'] = '';
            }
        }
        if (isset($input['interest_mode'])) {
            $mode = in_array($input['interest_mode'], array('none', 'percent', 'fixed'), true) ? $input['interest_mode'] : 'none';
            $sanitized['interest_mode'] = $mode;
        }
        if (isset($input['interest_value'])) {
            $sanitized['interest_value'] = floatval($input['interest_value']);
        }
        if (isset($input['navasan_api_key'])) {
            $sanitized['navasan_api_key'] = sanitize_text_field($input['navasan_api_key']);
        }
        if (isset($input['navasan_item'])) {
            $sanitized['navasan_item'] = sanitize_text_field($input['navasan_item']);
        }
        if (isset($input['fallback_mode'])) {
            $sanitized['fallback_mode'] = in_array($input['fallback_mode'], array('enabled', 'disabled'), true) ? $input['fallback_mode'] : 'enabled';
        }
        return $sanitized;
    }

    public function navasan_api_key_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['navasan_api_key']) ? $options['navasan_api_key'] : '';
        ?>
        <input type="text" name="price_converter_settings[navasan_api_key]" value="<?php echo esc_attr($value); ?>"
            class="regular-text" />
        <p class="description">
            <?php _e('Enter your Navasan API key for real-time exchange rates.', 'price-converter-plugin'); ?></p>
        <?php
    }

    public function navasan_item_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['navasan_item']) ? $options['navasan_item'] : '';
        ?>
        <input type="text" name="price_converter_settings[navasan_item]" value="<?php echo esc_attr($value); ?>"
            class="regular-text" />
        <p class="description"><?php _e('Enter the default item for USD source (e.g., "usd").', 'price-converter-plugin'); ?>
        </p>
        <?php
    }

    public function fallback_mode_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['fallback_mode']) ? $options['fallback_mode'] : 'enabled';
        ?>
        <select name="price_converter_settings[fallback_mode]">
            <option value="enabled" <?php selected($value, 'enabled'); ?>><?php _e('Enabled', 'price-converter-plugin'); ?>
            </option>
            <option value="disabled" <?php selected($value, 'disabled'); ?>><?php _e('Disabled', 'price-converter-plugin'); ?>
            </option>
        </select>
        <p class="description"><?php _e('Enable or disable fallback mode for price conversion.', 'price-converter-plugin'); ?>
        </p>
        <?php
    }
}
