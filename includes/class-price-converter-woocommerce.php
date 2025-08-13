<?php
/**
 * WooCommerce integration for Price Converter Plugin
 *
 * @package Price_Converter_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Price_Converter_WooCommerce
{

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'remove_sidebar_meta_box'), 100);
        add_action('woocommerce_product_options_pricing', array($this, 'add_pricing_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data'));

        // Clear any stuck transients on initialization
        $this->clear_stuck_transients();

        // Check if fallback mode is enabled
        $settings = get_option('price_converter_settings', array());
        $fallback_mode = isset($settings['fallback_mode']) ? $settings['fallback_mode'] : 'enabled';

        if ($fallback_mode === 'disabled') {
            // In fallback mode, only show admin fields but don't modify prices
            error_log('Price Converter: Running in fallback mode - price conversion disabled');
            return;
        }

        // Frontend price overrides - with safety checks to prevent infinite loops
        add_filter('woocommerce_product_get_price', array($this, 'filter_product_price_safe'), 999, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'filter_product_price_safe'), 999, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'filter_product_price_safe'), 999, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'filter_product_price_safe'), 999, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'filter_product_price_safe'), 999, 2);
        add_filter('woocommerce_product_variation_get_sale_price', array($this, 'filter_product_price_safe'), 999, 2);
        add_filter('woocommerce_get_price_html', array($this, 'filter_price_html_safe'), 999, 2);

        add_filter('woocommerce_get_variation_prices_hash', array($this, 'filter_variation_prices_hash'), 999, 3);
    }

    /**
     * Clear any stuck transients that might cause issues
     */
    private function clear_stuck_transients()
    {
        // Clear any stuck processing flags
        delete_transient('price_converter_api_processing');

        // Clear old rate data if it's too old
        $cached_rates = get_transient('price_converter_latest_rates');
        if ($cached_rates && is_array($cached_rates)) {
            $cache_time = get_option('_transient_timeout_price_converter_latest_rates', 0);
            if ($cache_time && (time() - $cache_time) > 600) { // 10 minutes
                delete_transient('price_converter_latest_rates');
            }
        }
    }

    public function remove_sidebar_meta_box()
    {
        remove_meta_box('price-converter-meta-box', 'product', 'side');
    }

    private function get_item_candidates_for_currency($currency)
    {
        $c = strtolower(trim($currency));
        $map = array(
            'usd' => array('usd_sell', 'usd'),
            'eur' => array('eur_sell', 'eur'),
            'gbp' => array('gbp_sell', 'gbp'),
            'aed' => array('aed_sell', 'dirham_dubai', 'aed'),
            'try' => array('try'),
            'cny' => array('cny'),
            'jpy' => array('jpy'),
            'rub' => array('rub'),
        );
        return isset($map[$c]) ? $map[$c] : array($c);
    }

    private function ensure_latest_rates_loaded()
    {
        $settings = get_option('price_converter_settings', array());
        $api_key = isset($settings['navasan_api_key']) ? trim($settings['navasan_api_key']) : '';
        if (empty($api_key)) {
            return array();
        }

        // Check if we're already processing to prevent multiple simultaneous calls
        if (get_transient('price_converter_api_processing')) {
            return array();
        }

        $cached = get_transient('price_converter_latest_rates');
        if ($cached && is_array($cached)) {
            return $cached;
        }

        // Set processing flag to prevent multiple simultaneous calls
        set_transient('price_converter_api_processing', true, 30);

        try {
            $url = add_query_arg(array('api_key' => rawurlencode($api_key)), 'http://api.navasan.tech/latest/');
            $response = wp_remote_get($url, array(
                'timeout' => 15,
                'sslverify' => false,
                'user-agent' => 'WordPress/PriceConverter/1.0'
            ));

            if (is_wp_error($response)) {
                error_log('Price Converter API Error: ' . $response->get_error_message());
                delete_transient('price_converter_api_processing');
                return array();
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                error_log('Price Converter API Error: HTTP ' . $response_code);
                delete_transient('price_converter_api_processing');
                return array();
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                error_log('Price Converter API Error: Empty response body');
                delete_transient('price_converter_api_processing');
                return array();
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                error_log('Price Converter API Error: Invalid JSON response');
                delete_transient('price_converter_api_processing');
                return array();
            }

            set_transient('price_converter_latest_rates', $data, 300); // 5 minutes cache
            delete_transient('price_converter_api_processing');
            return $data;

        } catch (Exception $e) {
            error_log('Price Converter Exception: ' . $e->getMessage());
            delete_transient('price_converter_api_processing');
            return array();
        }
    }

    private function get_currency_rate_irt($currency)
    {
        try {
            $currency = strtoupper((string) $currency);
            if (empty($currency)) {
                return 1.0;
            }

            $settings = get_option('price_converter_settings', array());

            // If API configured, try latest rates for this currency
            $api_key = isset($settings['navasan_api_key']) ? trim($settings['navasan_api_key']) : '';
            if (!empty($api_key)) {
                try {
                    $latest = $this->ensure_latest_rates_loaded();
                    if (!empty($latest) && is_array($latest)) {
                        foreach ($this->get_item_candidates_for_currency($currency) as $item) {
                            if (isset($latest[$item]['value'])) {
                                $irr = floatval(str_replace(',', '', (string) $latest[$item]['value']));
                                if ($irr > 0) {
                                    return round($irr, 6); // Remove division by 10 to preserve Toman value
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('Price Converter Error in API rate fetching: ' . $e->getMessage());
                }
            }

            // Custom map fallback
            try {
                $mapJson = isset($settings['custom_rates']) ? $settings['custom_rates'] : '';
                if (!empty($mapJson)) {
                    $map = json_decode($mapJson, true);
                    if (is_array($map) && isset($map[$currency]) && floatval($map[$currency]) > 0) {
                        return floatval($map[$currency]);
                    }
                }
            } catch (Exception $e) {
                error_log('Price Converter Error in custom rates parsing: ' . $e->getMessage());
            }

            // Custom currencies fallback (new feature)
            try {
                $customCurrenciesJson = isset($settings['custom_currencies']) ? $settings['custom_currencies'] : '';
                if (!empty($customCurrenciesJson)) {
                    $customCurrencies = json_decode($customCurrenciesJson, true);
                    if (is_array($customCurrencies) && isset($customCurrencies[$currency]) && floatval($customCurrencies[$currency]) > 0) {
                        return floatval($customCurrencies[$currency]);
                    }
                }
            } catch (Exception $e) {
                error_log('Price Converter Error in custom currencies parsing: ' . $e->getMessage());
            }

            // Fallback to USD setting
            try {
                $fallback_rate = isset($settings['exchange_rate']) ? floatval($settings['exchange_rate']) : 1.0;
                if ($fallback_rate > 0) {
                    return $fallback_rate;
                }
            } catch (Exception $e) {
                error_log('Price Converter Error in fallback rate: ' . $e->getMessage());
            }

            // Ultimate fallback
            return 1.0;

        } catch (Exception $e) {
            error_log('Price Converter Error in get_currency_rate_irt: ' . $e->getMessage());
            return 1.0; // Return 1.0 as ultimate fallback
        }
    }

    private function apply_interest($amount_irt)
    {
        try {
            $settings = get_option('price_converter_settings', array());
            $mode = isset($settings['interest_mode']) ? $settings['interest_mode'] : 'none';
            $value = isset($settings['interest_value']) ? floatval($settings['interest_value']) : 0.0;
            $amount = floatval($amount_irt);

            if ($amount <= 0) {
                return $amount;
            }

            if ($mode === 'percent' && $value > 0) {
                $amount = $amount * (1 + ($value / 100));
            } elseif ($mode === 'fixed' && $value > 0) {
                $amount = $amount + $value;
            }

            return max(0, $amount); // Ensure non-negative result

        } catch (Exception $e) {
            error_log('Price Converter Error in apply_interest: ' . $e->getMessage());
            return $amount_irt; // Return original amount on error
        }
    }

    public function filter_product_price($price, $product)
    {
        // Validate inputs
        if (!$product || !is_object($product) || !method_exists($product, 'get_id')) {
            return $price;
        }

        try {
            $product_id = $product->get_id();
            if (!$product_id) {
                return $price;
            }

            $base_price = get_post_meta($product_id, '_price_converter_base_price', true);
            $base_currency = get_post_meta($product_id, '_price_converter_base_currency', true);

            if ($base_price === '' || $base_price === null) {
                $legacy_usd = get_post_meta($product_id, '_price_converter_usd_price', true);
                if ($legacy_usd !== '' && $legacy_usd !== null) {
                    $base_price = $legacy_usd;
                    $base_currency = 'USD';
                }
            }

            if ($base_price === '' || $base_price === null) {
                $parent_id = method_exists($product, 'get_parent_id') ? $product->get_parent_id() : 0;
                if ($parent_id) {
                    $base_price = get_post_meta($parent_id, '_price_converter_base_price', true);
                    $base_currency = get_post_meta($parent_id, '_price_converter_base_currency', true);
                    if ($base_price === '' || $base_price === null) {
                        $legacy_usd = get_post_meta($parent_id, '_price_converter_usd_price', true);
                        if ($legacy_usd !== '' && $legacy_usd !== null) {
                            $base_price = $legacy_usd;
                            $base_currency = 'USD';
                        }
                    }
                }
            }

            if ($base_price === '' || $base_price === null) {
                // Fallback: convert store-regular price
                try {
                    $raw_regular = $product->get_regular_price();
                    $amount = $raw_regular !== '' ? floatval($raw_regular) : 0.0;
                    if ($amount > 0) {
                        $store_currency = get_option('woocommerce_currency', 'USD');
                        $rate = $this->get_currency_rate_irt($store_currency);
                        if ($rate > 0) {
                            $converted = round($this->apply_interest($amount * $rate), 0);
                            return (string) $converted;
                        }
                    }
                } catch (Exception $e) {
                    error_log('Price Converter Error in fallback conversion: ' . $e->getMessage());
                }
                return $price;
            }

            $amount = floatval($base_price);
            $currency = $base_currency ? strtoupper($base_currency) : 'USD';
            if ($amount > 0) {
                try {
                    $rate = $this->get_currency_rate_irt($currency);
                    if ($rate > 0) {
                        $converted = round($this->apply_interest($amount * $rate), 0);
                        return (string) $converted;
                    }
                } catch (Exception $e) {
                    error_log('Price Converter Error in rate conversion: ' . $e->getMessage());
                }
            }
            return $price;

        } catch (Exception $e) {
            error_log('Price Converter Error in filter_product_price: ' . $e->getMessage());
            return $price; // Return original price on any error
        }
    }

    public function filter_price_html($price_html, $product)
    {
        if ($product->is_type('variable')) {
            // Rely on variation getter filters to adjust range
            return $price_html;
        }
        $converted = $product->get_price();
        if ($converted !== '') {
            return wc_price((float) $converted);
        }
        return $price_html;
    }

    public function filter_variation_prices_hash($hash, $product, $display)
    {
        if (!is_array($hash)) {
            $hash = (array) $hash;
        }
        $parent_currency = get_post_meta($product->get_id(), '_price_converter_base_currency', true);
        $hash['pc_parent_currency'] = (string) ($parent_currency ?: get_option('woocommerce_currency', 'USD'));
        $hash['pc_parent_amount'] = (string) get_post_meta($product->get_id(), '_price_converter_base_price', true);
        $hash['pc_store_currency'] = (string) get_option('woocommerce_currency', 'USD');
        $settings = get_option('price_converter_settings', array());
        $hash['pc_usd_rate_fallback'] = (string) (isset($settings['exchange_rate']) ? $settings['exchange_rate'] : '');
        $latest = get_transient('price_converter_latest_rates');
        if (is_array($latest) && isset($latest['usd_sell']['value'])) {
            $hash['pc_usd_latest'] = (string) $latest['usd_sell']['value'];
        }
        return $hash;
    }

    public function add_pricing_fields()
    {
        global $post;

        $base_price = get_post_meta($post->ID, '_price_converter_base_price', true);
        $base_currency = strtoupper((string) get_post_meta($post->ID, '_price_converter_base_currency', true));
        if ($base_currency === '') {
            $base_currency = 'USD';
        }
        $source_url = get_post_meta($post->ID, '_price_converter_source_url', true);
        $source_selector = get_post_meta($post->ID, '_price_converter_source_selector', true);

        echo '<div class="options_group price-converter-section">';
        echo '<h4 class="price-converter-title"><span class="dashicons dashicons-money-alt"></span> ' . __('Price Converter Settings', 'price-converter-plugin') . '</h4>';
        echo '<p class="price-converter-description">' . __('Configure how this product\'s price should be converted to Iranian Toman (IRT).', 'price-converter-plugin') . '</p>';

        // Base Price and Currency Section
        echo '<div class="price-converter-row">';
        echo '<div class="price-converter-field">';
        woocommerce_wp_text_input(array(
            'id' => 'price_converter_base_price',
            'label' => __('Base Price', 'price-converter-plugin'),
            'desc_tip' => true,
            'description' => __('Set the product base price in the selected currency. It will be converted to Toman (IRT) for display.', 'price-converter-plugin'),
            'type' => 'number',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'value' => $base_price,
            'class' => 'price-converter-input',
        ));
        echo '</div>';

        echo '<div class="price-converter-field">';
        $currencies = array(
            'USD' => __('US Dollar (USD)', 'price-converter-plugin'),
            'EUR' => __('Euro (EUR)', 'price-converter-plugin'),
            'GBP' => __('British Pound (GBP)', 'price-converter-plugin'),
            'CAD' => __('Canadian Dollar (CAD)', 'price-converter-plugin'),
            'AUD' => __('Australian Dollar (AUD)', 'price-converter-plugin'),
            'AED' => __('UAE Dirham (AED)', 'price-converter-plugin'),
            'TRY' => __('Turkish Lira (TRY)', 'price-converter-plugin'),
            'CNY' => __('Chinese Yuan (CNY)', 'price-converter-plugin'),
            'JPY' => __('Japanese Yen (JPY)', 'price-converter-plugin'),
            'RUB' => __('Russian Ruble (RUB)', 'price-converter-plugin'),
            'IRR' => __('Iranian Rial (IRR)', 'price-converter-plugin'),
            'IRT' => __('Iranian Toman (IRT)', 'price-converter-plugin'),
        );

        // Add custom currencies from settings
        $settings = get_option('price_converter_settings', array());
        if (isset($settings['custom_currencies']) && !empty($settings['custom_currencies'])) {
            try {
                $custom_currencies = json_decode($settings['custom_currencies'], true);
                if (is_array($custom_currencies)) {
                    foreach ($custom_currencies as $code => $rate) {
                        if (!empty($code) && is_string($code)) {
                            $currencies[strtoupper($code)] = sprintf(__('Custom %s (%s)', 'price-converter-plugin'), strtoupper($code), strtoupper($code));
                        }
                    }
                }
            } catch (Exception $e) {
                // Silently handle JSON errors
            }
        }

        echo '<p class="form-field price-converter-select-field">';
        echo '<label for="price_converter_base_currency">' . esc_html__('Base Currency', 'price-converter-plugin') . '</label>';
        echo '<select id="price_converter_base_currency" name="price_converter_base_currency" class="price-converter-select">';
        foreach ($currencies as $code => $label) {
            $selected = selected($base_currency, $code, false);
            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<span class="description">' . esc_html__('Configure non-USD rates in plugin settings, or set API key.', 'price-converter-plugin') . '</span>';
        echo '</p>';
        echo '</div>';
        echo '</div>';

        // Source URL and Selector Section
        echo '<div class="price-converter-row">';
        echo '<div class="price-converter-field">';
        woocommerce_wp_text_input(array(
            'id' => 'price_converter_source_url',
            'label' => __('Source URL (optional)', 'price-converter-plugin'),
            'desc_tip' => true,
            'description' => __('Page to fetch price from (expects price text).', 'price-converter-plugin'),
            'value' => $source_url,
            'placeholder' => 'https://example.com/product-page',
            'class' => 'price-converter-input',
        ));
        echo '</div>';

        echo '<div class="price-converter-field">';
        woocommerce_wp_text_input(array(
            'id' => 'price_converter_source_selector',
            'label' => __('CSS Selector or data-qa (optional)', 'price-converter-plugin'),
            'desc_tip' => true,
            'description' => __('CSS selector (e.g., .price, #price) or data-qa attribute (e.g., data-qa="price", data-qa="amount").', 'price-converter-plugin'),
            'value' => $source_selector,
            'placeholder' => '.price, #price, data-qa="price", data-qa="amount"',
            'class' => 'price-converter-input',
        ));
        echo '</div>';
        echo '</div>';

        // Actions Section
        echo '<div class="price-converter-actions-section">';
        echo '<p class="form-field">';
        echo '<label>' . esc_html__('Actions', 'price-converter-plugin') . '</label>';
        echo '<button type="button" id="pc_fetch_price_pricing" class="button button-secondary price-converter-btn">';
        echo '<span class="dashicons dashicons-download"></span> ' . esc_html__('Fetch Price', 'price-converter-plugin');
        echo '</button>';
        echo '<span id="pc_fetch_spinner" class="spinner" style="float:none;margin:0 10px;display:none;"></span>';
        echo '<span class="price-converter-help-text">' . esc_html__('Click to automatically fetch the price from the source URL.', 'price-converter-plugin') . '</span>';
        echo '</p>';
        echo '</div>';

        // Converted Price Display
        $amount = $base_price !== '' ? floatval($base_price) : 0;
        $rate = $this->get_currency_rate_irt($base_currency);
        $irt = $amount ? round($this->apply_interest($amount * $rate), 0) : '';

        echo '<div class="price-converter-result">';
        echo '<p class="form-field">';
        echo '<label>' . esc_html__('Converted Price (IRT)', 'price-converter-plugin') . '</label>';
        echo '<input type="text" id="pc_converted_irt" readonly value="' . esc_attr($irt ? number_format($irt, 0, '.', ',') : '') . '" class="price-converter-result-input" />';
        echo '<span class="description">' . esc_html__('This shows the converted price in Iranian Toman (IRT). The conversion uses the current exchange rate for the selected currency.', 'price-converter-plugin') . '</span>';
        echo '</p>';
        echo '</div>';

        // Add debug information for developers
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $debug_settings = get_option('price_converter_settings', array());
            $debug_info = array(
                'base_price' => $base_price,
                'base_currency' => $base_currency,
                'exchange_rate' => $rate,
                'converted_before_interest' => $amount * $rate,
                'interest_mode' => isset($debug_settings['interest_mode']) ? $debug_settings['interest_mode'] : 'none',
                'interest_value' => isset($debug_settings['interest_value']) ? $debug_settings['interest_value'] : 0,
                'final_converted' => $irt
            );
            echo '<div class="price-converter-debug">';
            echo '<p class="form-field">';
            echo '<label><strong>Debug Info (WP_DEBUG enabled):</strong></label>';
            echo '<pre class="price-converter-debug-content">' . esc_html(json_encode($debug_info, JSON_PRETTY_PRINT)) . '</pre>';
            echo '</p>';
            echo '</div>';
        }

        echo '</div>';

        // Add CSS for better styling
        echo '<style>
        .price-converter-section {
            background: #f9f9f9;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .price-converter-title {
            margin: 0 0 15px 0;
            color: #23282d;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .price-converter-title .dashicons {
            color: #667eea;
            margin-right: 8px;
        }
        
        .price-converter-description {
            color: #666;
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .price-converter-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .price-converter-field {
            min-width: 0;
        }
        
        .price-converter-input {
            width: 100% !important;
            padding: 8px 12px !important;
            border: 2px solid #e1e1e1 !important;
            border-radius: 6px !important;
            font-size: 14px !important;
            transition: border-color 0.3s ease !important;
        }
        
        .price-converter-input:focus {
            border-color: #667eea !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
        }
        
        .price-converter-select {
            width: 100% !important;
            padding: 8px 12px !important;
            border: 2px solid #e1e1e1 !important;
            border-radius: 6px !important;
            font-size: 14px !important;
            background: white !important;
        }
        
        .price-converter-select:focus {
            border-color: #667eea !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
        }
        
        .price-converter-actions-section {
            background: #f0f8ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .price-converter-btn {
            padding: 10px 15px !important;
            height: auto !important;
            font-size: 14px !important;
            background: #667eea !important;
            border-color: #667eea !important;
            color: white !important;
        }
        
        .price-converter-btn:hover {
            background: #5a6fd8 !important;
            border-color: #5a6fd8 !important;
        }
        
        .price-converter-btn .dashicons {
            margin-right: 8px;
            vertical-align: middle;
        }
        
        .price-converter-help-text {
            display: block;
            margin-top: 8px;
            color: #666;
            font-size: 13px;
            font-style: italic;
        }
        
        .price-converter-result {
            background: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .price-converter-result-input {
            background: white !important;
            border: 2px solid #28a745 !important;
            color: #155724 !important;
            font-weight: 600 !important;
            text-align: center !important;
        }
        
        .price-converter-debug {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #0073aa;
        }
        
        .price-converter-debug-content {
            background: white;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-size: 11px;
            margin: 10px 0 0 0;
            overflow-x: auto;
        }
        
        @media (max-width: 768px) {
            .price-converter-row {
                grid-template-columns: 1fr;
            }
        }
        </style>';

        ?>
        <script type="text/javascript">
            jQuery(function ($) {
                function updateIrt() {
                    var amount = parseFloat($('#price_converter_base_price').val() || '0');
                    var currency = $('#price_converter_base_currency').val() || 'USD';
                    if (amount > 0) {
                        $.post(ajaxurl, {
                            action: 'price_converter_convert_price',
                            nonce: '<?php echo esc_js(wp_create_nonce('price_converter_nonce')); ?>',
                            price: amount,
                            currency: currency
                        }, function (resp) {
                            if (resp && resp.success) {
                                var val = resp.data.converted_price;
                                $('#pc_converted_irt').val(val.toLocaleString());
                            }
                        });
                    } else {
                        $('#pc_converted_irt').val('');
                    }
                }
                $('#price_converter_base_price, #price_converter_base_currency').on('input change', updateIrt);
                updateIrt();

                $('#pc_fetch_price_pricing').on('click', function () {
                    var url = $('#price_converter_source_url').val();
                    var selector = $('#price_converter_source_selector').val();
                    if (!url) {
                        alert('<?php echo esc_js(__('Please enter a source URL or set the price manually.', 'price-converter-plugin')); ?>');
                        return;
                    }
                    var $btn = $(this), $sp = $('#pc_fetch_spinner');
                    $btn.prop('disabled', true); $sp.show();
                    $.post(ajaxurl, {
                        action: 'price_converter_fetch_price',
                        nonce: '<?php echo esc_js(wp_create_nonce('price_converter_nonce')); ?>',
                        url: url,
                        selector: selector
                    }, function (resp) {
                        if (resp && resp.success) {
                            var fetched = resp.data.price;
                            $('#price_converter_base_price').val(fetched).trigger('change');
                        } else {
                            alert(resp && resp.data ? resp.data : 'Error');
                        }
                    }).always(function () { $btn.prop('disabled', false); $sp.hide(); });
                });
            });
        </script>
        <?php
    }

    public function save_product_data($post_id)
    {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['price_converter_base_price'])) {
            update_post_meta($post_id, '_price_converter_base_price', wc_format_decimal($_POST['price_converter_base_price']));
        }
        if (isset($_POST['price_converter_base_currency'])) {
            update_post_meta($post_id, '_price_converter_base_currency', sanitize_text_field($_POST['price_converter_base_currency']));
        }
        if (isset($_POST['price_converter_source_url'])) {
            update_post_meta($post_id, '_price_converter_source_url', sanitize_text_field($_POST['price_converter_source_url']));
        }
        if (isset($_POST['price_converter_source_selector'])) {
            update_post_meta($post_id, '_price_converter_source_selector', sanitize_text_field($_POST['price_converter_source_selector']));
        }
        wc_delete_product_transients($post_id);
    }

    /**
     * Safe version of filter_product_price with infinite loop protection
     */
    public function filter_product_price_safe($price, $product)
    {
        // Prevent infinite loops by checking if we're already processing this product
        static $processing_products = array();

        if (!$product || !is_object($product)) {
            return $price;
        }

        $product_id = $product->get_id();
        if (!$product_id || in_array($product_id, $processing_products)) {
            return $price;
        }

        // Add to processing list
        $processing_products[] = $product_id;

        try {
            $result = $this->filter_product_price($price, $product);

            // Remove from processing list
            $key = array_search($product_id, $processing_products);
            if ($key !== false) {
                unset($processing_products[$key]);
            }

            return $result;

        } catch (Exception $e) {
            error_log('Price Converter Error in filter_product_price_safe: ' . $e->getMessage());

            // Remove from processing list
            $key = array_search($product_id, $processing_products);
            if ($key !== false) {
                unset($processing_products[$key]);
            }

            return $price; // Return original price on error
        }
    }

    /**
     * Safe version of filter_price_html with error handling
     */
    public function filter_price_html_safe($price_html, $product)
    {
        if (!$product || !is_object($product)) {
            return $price_html;
        }

        try {
            if ($product->is_type('variable')) {
                // Rely on variation getter filters to adjust range
                return $price_html;
            }

            $converted = $product->get_price();
            if ($converted !== '' && $converted !== null) {
                return wc_price((float) $converted);
            }
            return $price_html;

        } catch (Exception $e) {
            error_log('Price Converter Error in filter_price_html_safe: ' . $e->getMessage());
            return $price_html; // Return original price HTML on error
        }
    }
}
