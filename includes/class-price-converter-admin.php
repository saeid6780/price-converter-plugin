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
            'custom_rates',
            __('Custom Currency Rates (JSON)', 'price-converter-plugin'),
            array($this, 'custom_rates_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'tax_mode',
            __('Tax Mode', 'price-converter-plugin'),
            array($this, 'tax_mode_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'tax_value',
            __('Tax Value', 'price-converter-plugin'),
            array($this, 'tax_value_callback'),
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
            'price_converter_navasan'
        );

        add_settings_field(
            'navasan_item',
            __('Default Item (USD source)', 'price-converter-plugin'),
            array($this, 'navasan_item_callback'),
            'price_converter_settings',
            'price_converter_navasan'
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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('price_converter_settings');
                do_settings_sections('price_converter_settings');
                submit_button();
                ?>
            </form>

            <div class="price-converter-test-section">
                <h2><?php _e('Test Price Fetching', 'price-converter-plugin'); ?></h2>
                <p><?php _e('Test the price fetching functionality with a URL:', 'price-converter-plugin'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="test_url"><?php _e('Test URL', 'price-converter-plugin'); ?></label></th>
                        <td>
                            <input type="url" id="test_url" name="test_url" class="regular-text"
                                placeholder="https://example.com/product-page">
                            <p class="description"><?php _e('Enter a URL to test price fetching', 'price-converter-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="test_selector"><?php _e('CSS Selector (Optional)', 'price-converter-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="test_selector" name="test_selector" class="regular-text"
                                placeholder=".price, #price, span.price">
                            <p class="description">
                                <?php _e('CSS selector to target specific price element', 'price-converter-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" id="test_fetch_price"
                        class="button button-secondary"><?php _e('Test Fetch Price', 'price-converter-plugin'); ?></button>
                    <button type="button" id="test_convert_price" class="button button-secondary"
                        style="display:none;"><?php _e('Convert to IRT', 'price-converter-plugin'); ?></button>
                </p>
                <div id="test_results" style="display:none;">
                    <h3><?php _e('Test Results', 'price-converter-plugin'); ?></h3>
                    <div id="test_results_content"></div>
                </div>
            </div>
        </div>
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

    public function tax_mode_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['tax_mode']) ? $options['tax_mode'] : 'none';
        ?>
        <select name="price_converter_settings[tax_mode]">
            <option value="none" <?php selected($value, 'none'); ?>><?php _e('None', 'price-converter-plugin'); ?></option>
            <option value="percent" <?php selected($value, 'percent'); ?>><?php _e('Percent', 'price-converter-plugin'); ?>
            </option>
            <option value="fixed" <?php selected($value, 'fixed'); ?>>
                <?php _e('Fixed Amount (IRT)', 'price-converter-plugin'); ?>
            </option>
        </select>
        <p class="description"><?php _e('Select tax mode to apply on converted prices.', 'price-converter-plugin'); ?></p>
        <?php
    }

    public function tax_value_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['tax_value']) ? $options['tax_value'] : 0;
        ?>
        <input type="number" step="0.01" min="0" name="price_converter_settings[tax_value]"
            value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('If Percent mode: value is percentage. If Fixed mode: value is IRT amount.', 'price-converter-plugin'); ?>
        </p>
        <?php
    }

    public function navasan_api_key_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['navasan_api_key']) ? $options['navasan_api_key'] : '';
        ?>
        <input type="text" name="price_converter_settings[navasan_api_key]" value="<?php echo esc_attr($value); ?>"
            class="regular-text" />
        <p class="description">
            <?php _e('Enter your Navasan API Key. If set, rates are fetched automatically.', 'price-converter-plugin'); ?>
        </p>
        <?php
    }

    public function navasan_item_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['navasan_item']) ? $options['navasan_item'] : 'usd_sell';
        $items = array(
            'usd_sell' => __('USD Tehran (Sell)', 'price-converter-plugin'),
            'usd_buy' => __('USD Tehran (Buy)', 'price-converter-plugin'),
            'harat_naghdi_buy' => __('USD Herat (Cash Buy)', 'price-converter-plugin'),
            'harat_naghdi_sell' => __('USD Herat (Cash Sell)', 'price-converter-plugin'),
        );
        ?>
        <select name="price_converter_settings[navasan_item]">
            <?php foreach ($items as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e('Default USD item to use when mapping fails.', 'price-converter-plugin'); ?></p>
        <?php
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();
        if (isset($input['exchange_rate'])) {
            $sanitized['exchange_rate'] = floatval($input['exchange_rate']);
        }
        if (isset($input['custom_rates'])) {
            $sanitized['custom_rates'] = wp_kses_post($input['custom_rates']);
        }
        if (isset($input['tax_mode'])) {
            $mode = in_array($input['tax_mode'], array('none', 'percent', 'fixed'), true) ? $input['tax_mode'] : 'none';
            $sanitized['tax_mode'] = $mode;
        }
        if (isset($input['tax_value'])) {
            $sanitized['tax_value'] = floatval($input['tax_value']);
        }
        if (isset($input['navasan_api_key'])) {
            $sanitized['navasan_api_key'] = sanitize_text_field($input['navasan_api_key']);
        }
        if (isset($input['navasan_item'])) {
            $sanitized['navasan_item'] = sanitize_text_field($input['navasan_item']);
        }
        return $sanitized;
    }
}
