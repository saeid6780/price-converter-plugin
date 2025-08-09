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

    /**
     * Constructor
     */
    public function __construct()
    {
        // Remove sidebar meta box; we will use pricing panel
        add_action('add_meta_boxes', array($this, 'remove_sidebar_meta_box'), 100);

        // Add fields into pricing tab
        add_action('woocommerce_product_options_pricing', array($this, 'add_pricing_fields'));

        // Save fields
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data'));

        // Override WooCommerce price getters so front-end uses converted IRT
        add_filter('woocommerce_product_get_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'filter_product_price'), 999, 2);
        add_filter('woocommerce_product_variation_get_sale_price', array($this, 'filter_product_price'), 999, 2);

        // Ensure variation prices cache varies with our rate/base
        add_filter('woocommerce_get_variation_prices_hash', array($this, 'filter_variation_prices_hash'), 999, 3);
    }

    public function remove_sidebar_meta_box()
    {
        remove_meta_box('price-converter-meta-box', 'product', 'side');
    }

    /**
     * Compute currency->IRT rate from transient (USD) or custom settings
     */
    private function get_currency_rate_irt($currency)
    {
        $currency = strtoupper((string) $currency);
        $settings = get_option('price_converter_settings', array());

        if ($currency === 'USD' || $currency === '') {
            $rate = get_transient('price_converter_usd_to_irt');
            if ($rate) {
                return floatval($rate);
            }
            return isset($settings['exchange_rate']) ? floatval($settings['exchange_rate']) : 1.0;
        }

        // Custom rates mapping (per 1 unit -> IRT)
        $mapJson = isset($settings['custom_rates']) ? $settings['custom_rates'] : '';
        if (!empty($mapJson)) {
            $map = json_decode($mapJson, true);
            if (is_array($map) && isset($map[$currency]) && floatval($map[$currency]) > 0) {
                return floatval($map[$currency]);
            }
        }

        // Fallback to USD rate if not found
        $rate = get_transient('price_converter_usd_to_irt');
        if ($rate) {
            return floatval($rate);
        }
        return isset($settings['exchange_rate']) ? floatval($settings['exchange_rate']) : 1.0;
    }

    /**
     * Price filter: if base is set, return converted IRT
     */
    public function filter_product_price($price, $product)
    {
        $product_id = $product->get_id();
        $base_price = get_post_meta($product_id, '_price_converter_base_price', true);
        $base_currency = get_post_meta($product_id, '_price_converter_base_currency', true);

        if ($base_price === '' || $base_price === null) {
            // For variations, try parent
            $parent_id = method_exists($product, 'get_parent_id') ? $product->get_parent_id() : 0;
            if ($parent_id) {
                $base_price = get_post_meta($parent_id, '_price_converter_base_price', true);
                $base_currency = get_post_meta($parent_id, '_price_converter_base_currency', true);
            }
        }

        $amount = $base_price !== '' ? floatval($base_price) : 0.0;
        $currency = $base_currency ? strtoupper($base_currency) : 'USD';

        if ($amount > 0) {
            $rate = $this->get_currency_rate_irt($currency);
            $converted = round($amount * $rate, 0);
            return (string) $converted;
        }
        return $price;
    }

    /**
     * Make variation prices hash depend on our conversion so Woo recalculates ranges
     */
    public function filter_variation_prices_hash($hash, $product, $display)
    {
        if (!is_array($hash)) {
            $hash = (array) $hash;
        }
        $parent_currency = get_post_meta($product->get_id(), '_price_converter_base_currency', true);
        $hash['pc_rate_irt'] = (string) $this->get_currency_rate_irt($parent_currency ?: 'USD');
        $hash['pc_parent_currency'] = (string) ($parent_currency ?: 'USD');
        $hash['pc_parent_amount'] = (string) get_post_meta($product->get_id(), '_price_converter_base_price', true);
        return $hash;
    }

    /**
     * Add custom fields in the pricing section (base amount + currency selector)
     */
    public function add_pricing_fields()
    {
        global $post;

        $base_price = get_post_meta($post->ID, '_price_converter_base_price', true);
        $base_currency = strtoupper((string) get_post_meta($post->ID, '_price_converter_base_currency', true));
        if ($base_currency === '') {
            $base_currency = 'USD';
        }

        echo '<div class="options_group">';

        // Base price (manual input)
        woocommerce_wp_text_input(array(
            'id' => 'price_converter_base_price',
            'label' => __('Base Price', 'price-converter-plugin'),
            'desc_tip' => true,
            'description' => __('Set the product base price in the selected currency below. It will be converted to Toman (IRT) for display.', 'price-converter-plugin'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            ),
            'value' => $base_price,
        ));

        // Currency selector
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
            . '<span class="description"> ' . esc_html__('For non-USD currencies, configure rates in plugin settings.', 'price-converter-plugin') . '</span>'
            . '</p>';

        // Show converted IRT preview using current rate
        $amount = $base_price !== '' ? floatval($base_price) : 0;
        $rate = $this->get_currency_rate_irt($base_currency);
        $irt = $amount ? round($amount * $rate, 0) : '';

        echo '<p class="form-field">'
            . '<label>' . esc_html__('Converted Price (IRT)', 'price-converter-plugin') . '</label>'
            . '<input type="text" id="pc_converted_irt" readonly value="' . esc_attr($irt ? number_format($irt, 0, '.', ',') : '') . '" />'
            . '</p>';

        echo '</div>';

        // Inline script to auto convert preview
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
            });
        </script>
        <?php
    }

    /**
     * Save product data
     */
    public function save_product_data($post_id)
    {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save base price and currency
        if (isset($_POST['price_converter_base_price'])) {
            update_post_meta($post_id, '_price_converter_base_price', wc_format_decimal($_POST['price_converter_base_price']));
        }
        if (isset($_POST['price_converter_base_currency'])) {
            update_post_meta($post_id, '_price_converter_base_currency', sanitize_text_field($_POST['price_converter_base_currency']));
        }

        wc_delete_product_transients($post_id);
    }
}
