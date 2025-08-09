<?php
/**
 * Main Price Converter Class
 *
 * @package Price_Converter_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Price_Converter
{
    /**
     * Cached latest rates from Navasan (decoded array)
     */
    private $latest_rates_cache = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Initialize admin functionality
        if (is_admin()) {
            new Price_Converter_Admin();
        }

        // Initialize WooCommerce integration
        new Price_Converter_WooCommerce();

        // Add AJAX handlers
        add_action('wp_ajax_price_converter_fetch_price', array($this, 'ajax_fetch_price'));
        add_action('wp_ajax_price_converter_convert_price', array($this, 'ajax_convert_price'));

        // Add REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Fetch price from external URL
     * If URL is empty, returns WP_Error to indicate nothing to fetch
     */
    public function fetch_price_from_url($url, $selector = '')
    {
        if (empty($url)) {
            return new WP_Error('no_url', 'No URL provided');
        }

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL format');
        }

        // Fetch the webpage content
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            return new WP_Error('empty_response', 'No content received from URL');
        }

        // Extract price using regex patterns
        $price = $this->extract_price_from_content($body, $selector);

        if (!$price) {
            return new WP_Error('price_not_found', 'No price found on the page');
        }

        return $price;
    }

    /**
     * Extract price from HTML content
     */
    private function extract_price_from_content($content, $selector = '')
    {
        // If a CSS selector is provided, try to use it first
        if (!empty($selector)) {
            // Simple DOM parsing for the selector
            $price = $this->extract_price_by_selector($content, $selector);
            if ($price) {
                return $price;
            }
        }

        // Common price patterns
        $patterns = array(
            // USD patterns
            '/\$(\d+(?:,\d{3})*(?:\.\d{2})?)/',
            '/USD\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/',
            // EUR patterns
            '/€(\d+(?:,\d{3})*(?:\.\d{2})?)/',
            '/EUR\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/',
            // General currency patterns
            '/(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(USD|EUR|GBP|CAD|AUD)/',
            // Price with currency symbols
            '/(\d+(?:,\d{3})*(?:\.\d{2})?)/'
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $price = $matches[1];
                // Clean up the price
                $price = str_replace(',', '', $price);
                return floatval($price);
            }
        }

        return false;
    }

    /**
     * Extract price using CSS selector
     */
    private function extract_price_by_selector($content, $selector)
    {
        // Simple implementation - in production, use a proper DOM parser
        if (strpos($selector, '.') === 0) {
            $class = substr($selector, 1);
            $pattern = '/<[^>]*class=["\'][^"\']*' . preg_quote($class, '/') . '[^"\']*["\'][^>]*>([^<]+)<\/[^>]*>/i';
        } elseif (strpos($selector, '#') === 0) {
            $id = substr($selector, 1);
            $pattern = '/<[^>]*id=["\']' . preg_quote($id, '/') . '["\'][^>]*>([^<]+)<\/[^>]*>/i';
        } else {
            $pattern = '/<' . preg_quote($selector, '/') . '[^>]*>([^<]+)<\/' . preg_quote($selector, '/') . '>/i';
        }

        if (preg_match($pattern, $content, $matches)) {
            $price_text = trim($matches[1]);
            // Extract numeric value
            if (preg_match('/(\d+(?:,\d{3})*(?:\.\d{2})?)/', $price_text, $price_matches)) {
                $price = str_replace(',', '', $price_matches[1]);
                return floatval($price);
            }
        }

        return false;
    }

    /**
     * Fetch latest rates JSON from Navasan once and cache in transient
     */
    private function fetch_latest_rates()
    {
        if ($this->latest_rates_cache !== null) {
            return $this->latest_rates_cache;
        }
        $settings = get_option('price_converter_settings', array());
        $api_key = isset($settings['navasan_api_key']) ? trim($settings['navasan_api_key']) : '';
        if (empty($api_key)) {
            $this->latest_rates_cache = array();
            return $this->latest_rates_cache;
        }

        $cached = get_transient('price_converter_latest_rates');
        if ($cached) {
            $this->latest_rates_cache = $cached;
            return $this->latest_rates_cache;
        }

        $url = add_query_arg(
            array(
                'api_key' => rawurlencode($api_key),
            ),
            'http://api.navasan.tech/latest/'
        );
        $response = wp_remote_get($url, array('timeout' => 20));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->latest_rates_cache = array();
            return $this->latest_rates_cache;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = array();
        }
        set_transient('price_converter_latest_rates', $data, 120); // 2 minutes
        $this->latest_rates_cache = $data;
        return $this->latest_rates_cache;
    }

    /**
     * Candidate item keys for a currency
     */
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
            // fallbacks default to the code itself
        );
        if (isset($map[$c])) {
            return $map[$c];
        }
        return array($c);
    }

    /**
     * Get USD->IRT exchange rate using get_currency_to_irt_rate for compatibility
     */
    public function get_usd_to_irt_rate()
    {
        return $this->get_currency_to_irt_rate('USD');
    }

    /**
     * Resolve currency->IRT rate.
     * - IRR: 0.1
     * - IRT: 1
     * - If API configured: read latest rates; pick candidate; value is IRR per unit -> divide by 10
     * - Else: fallback to settings: exchange_rate (USD->IRT) or custom_rates JSON
     */
    public function get_currency_to_irt_rate($currency)
    {
        $code = strtoupper(trim((string) $currency));
        if ($code === 'IRT') {
            return 1.0;
        }
        if ($code === 'IRR') {
            return 0.1;
        }

        $settings = get_option('price_converter_settings', array());
        $api_key = isset($settings['navasan_api_key']) ? trim($settings['navasan_api_key']) : '';

        if (!empty($api_key)) {
            $latest = $this->fetch_latest_rates();
            if (!empty($latest)) {
                $candidates = $this->get_item_candidates_for_currency($code);
                foreach ($candidates as $item) {
                    if (isset($latest[$item]) && isset($latest[$item]['value'])) {
                        $irr_value = $latest[$item]['value'];
                        $irr = floatval(str_replace(',', '', (string) $irr_value));
                        if ($irr > 0) {
                            return round($irr / 10, 6);
                        }
                    }
                }
            }
        }

        // Custom per-currency rates JSON (direct IRT per unit)
        if (isset($settings['custom_rates']) && !empty($settings['custom_rates'])) {
            $map = json_decode($settings['custom_rates'], true);
            if (is_array($map) && isset($map[$code]) && floatval($map[$code]) > 0) {
                return floatval($map[$code]);
            }
        }

        // Fallback to USD rate
        $usd_rate = isset($settings['exchange_rate']) ? floatval($settings['exchange_rate']) : 1.0;
        return $usd_rate;
    }

    private function apply_tax_to_irt($amount_irt)
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

    /**
     * Convert amount to Iranian Toman (IRT) from a given currency
     */
    public function convert_to_toman_from_currency($amount, $currency)
    {
        $rate_irt = $this->get_currency_to_irt_rate($currency);
        $converted_price = floatval($amount) * $rate_irt;
        $converted_price = $this->apply_tax_to_irt($converted_price);
        return round($converted_price, 0);
    }

    /**
     * Backward-compatible USD method
     */
    public function convert_to_toman($priceUsd)
    {
        return $this->convert_to_toman_from_currency($priceUsd, 'USD');
    }

    /**
     * AJAX handler for fetching price
     */
    public function ajax_fetch_price()
    {
        check_ajax_referer('price_converter_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $url = isset($_POST['url']) ? sanitize_url($_POST['url']) : '';
        $selector = isset($_POST['selector']) ? sanitize_text_field($_POST['selector']) : '';

        if (empty($url)) {
            wp_send_json_error(__('No URL provided', 'price-converter-plugin'));
        }

        $price = $this->fetch_price_from_url($url, $selector);

        if (is_wp_error($price)) {
            wp_send_json_error($price->get_error_message());
        } else {
            wp_send_json_success(array(
                'price' => $price,
                'currency' => 'USD' // Default currency
            ));
        }
    }

    /**
     * AJAX handler for converting price
     */
    public function ajax_convert_price()
    {
        check_ajax_referer('price_converter_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $price = floatval($_POST['price']);
        $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'USD';

        $converted_price = $this->convert_to_toman_from_currency($price, $currency);

        wp_send_json_success(array(
            'original_price' => $price,
            'converted_price' => $converted_price,
            'currency' => 'IRT'
        ));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        register_rest_route('price-converter/v1', '/fetch-price', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_fetch_price'),
            'permission_callback' => array($this, 'rest_permission_callback'),
        ));

        register_rest_route('price-converter/v1', '/convert-price', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_convert_price'),
            'permission_callback' => array($this, 'rest_permission_callback'),
        ));
    }

    /**
     * REST API permission callback
     */
    public function rest_permission_callback()
    {
        return current_user_can('manage_woocommerce');
    }

    /**
     * REST API fetch price endpoint
     */
    public function rest_fetch_price($request)
    {
        $url = $request->get_param('url');
        $selector = $request->get_param('selector');

        $price = $this->fetch_price_from_url($url, $selector);

        if (is_wp_error($price)) {
            return new WP_Error('fetch_failed', $price->get_error_message(), array('status' => 400));
        }

        return array(
            'price' => $price,
            'currency' => 'USD'
        );
    }

    /**
     * REST API convert price endpoint
     */
    public function rest_convert_price($request)
    {
        $price = floatval($request->get_param('price'));
        $currency = $request->get_param('currency') ?: 'USD';
        $converted_price = $this->convert_to_toman_from_currency($price, $currency);

        return array(
            'original_price' => $price,
            'converted_price' => $converted_price,
            'currency' => 'IRT'
        );
    }
}
