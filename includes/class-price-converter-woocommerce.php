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

        // Frontend price overrides
        add_filter('woocommerce_product_get_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_variation_get_sale_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_get_price_html', array($this, 'filter_price_html'), 999, 2);

        add_filter('woocommerce_get_variation_prices_hash', array($this, 'filter_variation_prices_hash'), 999, 3);
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
        $cached = get_transient('price_converter_latest_rates');
        if ($cached && is_array($cached)) {
            return $cached;
        }
        $url = add_query_arg(array('api_key' => rawurlencode($api_key)), 'http://api.navasan.tech/latest/');
        $response = wp_remote_get($url, array('timeout' => 20));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return array();
        }
        set_transient('price_converter_latest_rates', $data, 120);
        return $data;
    }

    private function get_currency_rate_irt($currency)
    {
        $currency = strtoupper((string) $currency);
        $settings = get_option('price_converter_settings', array());

        // If API configured, try latest rates for this currency
        $api_key = isset($settings['navasan_api_key']) ? trim($settings['navasan_api_key']) : '';
        if (!empty($api_key)) {
            $latest = $this->ensure_latest_rates_loaded();
            if (!empty($latest)) {
                foreach ($this->get_item_candidates_for_currency($currency) as $item) {
                    if (isset($latest[$item]['value'])) {
                        $irr = floatval(str_replace(',', '', (string) $latest[$item]['value']));
                        if ($irr > 0) {
                            return round($irr / 10, 6);
                        }
                    }
                }
            }
        }

        // Custom map fallback
        $mapJson = isset($settings['custom_rates']) ? $settings['custom_rates'] : '';
        if (!empty($mapJson)) {
            $map = json_decode($mapJson, true);
            if (is_array($map) && isset($map[$currency]) && floatval($map[$currency]) > 0) {
                return floatval($map[$currency]);
            }
        }

        // Fallback to USD setting
        return isset($settings['exchange_rate']) ? floatval($settings['exchange_rate']) : 1.0;
    }

    private function apply_tax($amount_irt)
    {
        $settings = get_option('price_converter_settings', array());
        $mode = isset($settings['tax_mode']) ? $settings['tax_mode'] : 'none';
        $value = isset($settings['tax_value']) ? floatval($settings['tax_value']) : 0.0;
        $amount = floatval($amount_irt);
        if ($mode === 'percent' && $value !== 0.0) {
            $amount = $amount * (1 + ($value / 100));
        } elseif ($mode === 'fixed' && $value !== 0.0) {
            $amount = $amount + $value;
        }
        return $amount;
    }

    public function filter_product_price($price, $product)
    {
        $product_id = $product->get_id();

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
            $raw_regular = $product->get_regular_price();
            $amount = $raw_regular !== '' ? floatval($raw_regular) : 0.0;
            if ($amount > 0) {
                $store_currency = get_option('woocommerce_currency', 'USD');
                $rate = $this->get_currency_rate_irt($store_currency);
                $converted = round($this->apply_tax($amount * $rate), 0);
                return (string) $converted;
            }
            return $price;
        }

        $amount = floatval($base_price);
        $currency = $base_currency ? strtoupper($base_currency) : 'USD';
        if ($amount > 0) {
            $rate = $this->get_currency_rate_irt($currency);
            $converted = round($this->apply_tax($amount * $rate), 0);
            return (string) $converted;
        }
        return $price;
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

        echo '<div class="options_group">';

        woocommerce_wp_text_input(array(
            'id' => 'price_converter_base_price',
            'label' => __('Base Price', 'price-converter-plugin'),
            'desc_tip' => true,
            'description' => __('Set the product base price in the selected currency below. It will be converted to Toman (IRT) for display.', 'price-converter-plugin'),
            'type' => 'number',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'value' => $base_price,
        ));

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

        echo '<p class="form-field">'
            . '<label for="price_converter_base_currency">' . esc_html__('Base Currency', 'price-converter-plugin') . '</label>'
            . '<select id="price_converter_base_currency" name="price_converter_base_currency">';
        foreach ($currencies as $code => $label) {
            $selected = selected($base_currency, $code, false);
            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>'
            . '<span class="description"> ' . esc_html__('Configure non-USD rates in plugin settings, or set API key.', 'price-converter-plugin') . '</span>'
            . '</p>';

        // Source fields and Fetch
        woocommerce_wp_text_input(array(
            'id' => 'price_converter_source_url',
            'label' => __('Source URL (optional)', 'price-converter-plugin'),
            'desc_tip' => true,
            'description' => __('Page to fetch price from (expects price text).', 'price-converter-plugin'),
            'value' => $source_url,
            'placeholder' => 'https://example.com/product-page'
        ));
        woocommerce_wp_text_input(array(
            'id' => 'price_converter_source_selector',
            'label' => __('CSS Selector (optional)', 'price-converter-plugin'),
            'desc_tip' => true,
            'description' => __('Optional CSS selector for the price element (e.g., .price, #price).', 'price-converter-plugin'),
            'value' => $source_selector,
            'placeholder' => '.price, #price, span.price'
        ));
        echo '<p class="form-field">'
            . '<label>' . esc_html__('Actions', 'price-converter-plugin') . '</label>'
            . '<button type="button" id="pc_fetch_price_pricing" class="button">' . esc_html__('Fetch Price', 'price-converter-plugin') . '</button>'
            . '<span id="pc_fetch_spinner" class="spinner" style="float:none;margin-top:0;display:none;"></span>'
            . '</p>';

        $amount = $base_price !== '' ? floatval($base_price) : 0;
        $rate = $this->get_currency_rate_irt($base_currency);
        $irt = $amount ? round($this->apply_tax($amount * $rate), 0) : '';
        echo '<p class="form-field">'
            . '<label>' . esc_html__('Converted Price (IRT)', 'price-converter-plugin') . '</label>'
            . '<input type="text" id="pc_converted_irt" readonly value="' . esc_attr($irt ? number_format($irt, 0, '.', ',') : '') . '" />'
            . '</p>';

        echo '</div>';

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
}
