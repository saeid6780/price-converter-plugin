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

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu
     */
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

    /**
     * Initialize settings
     */
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
            'currency_from',
            __('Source Currency', 'price-converter-plugin'),
            array($this, 'currency_from_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'currency_to',
            __('Target Currency', 'price-converter-plugin'),
            array($this, 'currency_to_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'auto_update',
            __('Auto Update Prices', 'price-converter-plugin'),
            array($this, 'auto_update_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        add_settings_field(
            'update_interval',
            __('Update Interval', 'price-converter-plugin'),
            array($this, 'update_interval_callback'),
            'price_converter_settings',
            'price_converter_general'
        );

        // Navasan API section
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
            __('Item (rate source)', 'price-converter-plugin'),
            array($this, 'navasan_item_callback'),
            'price_converter_settings',
            'price_converter_navasan'
        );
    }

    /**
     * Enqueue admin scripts
     */
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

    /**
     * Settings page
     */
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
                        <th scope="row">
                            <label for="test_url"><?php _e('Test URL', 'price-converter-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="test_url" name="test_url" class="regular-text"
                                placeholder="https://example.com/product-page">
                            <p class="description"><?php _e('Enter a URL to test price fetching', 'price-converter-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="test_selector"><?php _e('CSS Selector (Optional)', 'price-converter-plugin'); ?></label>
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
                    <button type="button" id="test_fetch_price" class="button button-secondary">
                        <?php _e('Test Fetch Price', 'price-converter-plugin'); ?>
                    </button>
                    <button type="button" id="test_convert_price" class="button button-secondary" style="display:none;">
                        <?php _e('Convert to IRT', 'price-converter-plugin'); ?>
                    </button>
                </p>

                <div id="test_results" style="display:none;">
                    <h3><?php _e('Test Results', 'price-converter-plugin'); ?></h3>
                    <div id="test_results_content"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * General section callback
     */
    public function general_section_callback()
    {
        echo '<p>' . __('Configure the price converter settings below.', 'price-converter-plugin') . '</p>';
    }

    public function navasan_section_callback()
    {
        echo '<p>' . esc_html__(
            'If you provide a Navasan API Key, the plugin will fetch USD→IRR from their web service and automatically convert it to IRT by removing one zero.',
            'price-converter-plugin'
        ) . '</p>';
    }

    /**
     * Exchange rate callback
     */
    public function exchange_rate_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['exchange_rate']) ? $options['exchange_rate'] : 1;
        ?>
        <input type="number" step="0.01" min="0" name="price_converter_settings[exchange_rate]"
            value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Fallback exchange rate from USD to Iranian Toman (IRT) when API is not configured or unavailable.', 'price-converter-plugin'); ?>
        </p>
        <?php
    }

    /**
     * Currency from callback
     */
    public function currency_from_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['currency_from']) ? $options['currency_from'] : 'USD';
        ?>
        <select name="price_converter_settings[currency_from]">
            <option value="USD" <?php selected($value, 'USD'); ?>><?php _e('US Dollar (USD)', 'price-converter-plugin'); ?>
            </option>
            <option value="EUR" <?php selected($value, 'EUR'); ?>><?php _e('Euro (EUR)', 'price-converter-plugin'); ?></option>
            <option value="GBP" <?php selected($value, 'GBP'); ?>><?php _e('British Pound (GBP)', 'price-converter-plugin'); ?>
            </option>
            <option value="CAD" <?php selected($value, 'CAD'); ?>>
                <?php _e('Canadian Dollar (CAD)', 'price-converter-plugin'); ?>
            </option>
            <option value="AUD" <?php selected($value, 'AUD'); ?>>
                <?php _e('Australian Dollar (AUD)', 'price-converter-plugin'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Currency to callback
     */
    public function currency_to_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['currency_to']) ? $options['currency_to'] : 'IRT';
        ?>
        <input type="text" name="price_converter_settings[currency_to]" value="<?php echo esc_attr($value); ?>"
            class="regular-text" readonly />
        <p class="description"><?php _e('Target currency (Iranian Toman - IRT)', 'price-converter-plugin'); ?></p>
        <?php
    }

    /**
     * Auto update callback
     */
    public function auto_update_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['auto_update']) ? $options['auto_update'] : false;
        ?>
        <label>
            <input type="checkbox" name="price_converter_settings[auto_update]" value="1" <?php checked($value, 1); ?> />
            <?php _e('Enable automatic price updates', 'price-converter-plugin'); ?>
        </label>
        <?php
    }

    /**
     * Update interval callback
     */
    public function update_interval_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['update_interval']) ? $options['update_interval'] : 'daily';
        ?>
        <select name="price_converter_settings[update_interval]">
            <option value="hourly" <?php selected($value, 'hourly'); ?>><?php _e('Hourly', 'price-converter-plugin'); ?>
            </option>
            <option value="daily" <?php selected($value, 'daily'); ?>><?php _e('Daily', 'price-converter-plugin'); ?></option>
            <option value="weekly" <?php selected($value, 'weekly'); ?>><?php _e('Weekly', 'price-converter-plugin'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Navasan API key field
     */
    public function navasan_api_key_callback()
    {
        $options = get_option('price_converter_settings');
        $value = isset($options['navasan_api_key']) ? $options['navasan_api_key'] : '';
        ?>
        <input type="text" name="price_converter_settings[navasan_api_key]" value="<?php echo esc_attr($value); ?>"
            class="regular-text" />
        <p class="description">
            <?php _e('Enter your Navasan API Key. If set, USD→IRR will be fetched from Navasan and then converted to IRT (divide by 10).', 'price-converter-plugin'); ?>
        </p>
        <?php
    }

    /**
     * Navasan item field
     */
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
        <p class="description"><?php _e('Choose which USD rate to use from Navasan.', 'price-converter-plugin'); ?></p>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        if (isset($input['exchange_rate'])) {
            $sanitized['exchange_rate'] = floatval($input['exchange_rate']);
        }

        if (isset($input['currency_from'])) {
            $sanitized['currency_from'] = sanitize_text_field($input['currency_from']);
        }

        if (isset($input['currency_to'])) {
            $sanitized['currency_to'] = sanitize_text_field($input['currency_to']);
        }

        if (isset($input['auto_update'])) {
            $sanitized['auto_update'] = (bool) $input['auto_update'];
        }

        if (isset($input['update_interval'])) {
            $sanitized['update_interval'] = sanitize_text_field($input['update_interval']);
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
