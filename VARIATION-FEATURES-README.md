# Price Converter Plugin - Variation Features

## Overview

The Price Converter Plugin has been enhanced with comprehensive support for WooCommerce variable products. Each variation can now have its own price converter settings, and each product can have a unique interest rate that overrides the default settings.

## New Features

### 1. Per-Product Interest Rate Settings

Each product can now have its own interest rate configuration that overrides the default settings:

- **Interest Mode**: Choose between percentage, fixed amount, or inherit from default settings
- **Interest Value**: Set the specific interest value for the product
- **Inheritance**: Products can inherit default settings or override them completely

### 2. Variation-Specific Price Converter Settings

Each variation within a variable product can have its own:

- **Base Price**: Individual base price in the selected currency
- **Base Currency**: Currency for the variation (USD, EUR, GBP, etc.)
- **Source URL**: Optional URL to fetch prices from for this specific variation
- **CSS Selector**: Optional selector for price extraction from the source URL
- **Interest Settings**: Individual interest mode and value for the variation

### 3. Smart Inheritance System

The plugin implements a hierarchical inheritance system:

1. **Variation Level**: Individual variation settings (highest priority)
2. **Product Level**: Parent product settings
3. **Default Level**: General plugin settings (lowest priority)

## How to Use

### Setting Up Per-Product Interest Rates

1. **Edit a Product**: Go to WooCommerce → Products → Edit Product
2. **Navigate to Pricing Tab**: Find the "Price Converter Settings" section
3. **Configure Interest Settings**:
   - **Interest Mode**: Select from:
     - "Use Default Settings" - Inherits from general settings
     - "No Interest" - No interest applied
     - "Percentage (%)" - Percentage-based interest
     - "Fixed Amount" - Fixed amount interest
   - **Interest Value**: Enter the interest value (percentage or fixed amount)
4. **Save Changes**: Click "Update" to save the product

### Setting Up Variation-Specific Settings

1. **Edit a Variable Product**: Go to WooCommerce → Products → Edit Product
2. **Navigate to Variations Tab**: Click on "Variations" in the product data panel
3. **For Each Variation**: Scroll down to find the "Price Converter Settings" section
4. **Configure Variation Settings**:
   - **Base Price**: Set the base price for this variation
   - **Base Currency**: Select the currency for this variation
   - **Source URL**: Optional URL for price fetching
   - **CSS Selector**: Optional selector for price extraction
   - **Interest Mode**: Choose from:
     - "Inherit from Product" - Uses parent product settings
     - "No Interest" - No interest applied
     - "Percentage (%)" - Percentage-based interest
     - "Fixed Amount" - Fixed amount interest
   - **Interest Value**: Set the interest value for this variation
5. **Save Changes**: Click "Save changes" for each variation

### Understanding the Inheritance System

```
Variation Settings (Highest Priority)
    ↓
Product Settings
    ↓
Default Plugin Settings (Lowest Priority)
```

**Example Scenario:**
- Default plugin interest: 5% percentage
- Product A interest: 10% percentage
- Variation A1 interest: "Inherit from Product" (uses 10%)
- Variation A2 interest: 15% percentage (overrides to 15%)

## Technical Implementation

### New Meta Fields

#### Product Level
- `_price_converter_interest_mode`: Interest mode for the product
- `_price_converter_interest_value`: Interest value for the product

#### Variation Level
- `_price_converter_base_price`: Base price for the variation
- `_price_converter_base_currency`: Base currency for the variation
- `_price_converter_source_url`: Source URL for price fetching
- `_price_converter_source_selector`: CSS selector for price extraction
- `_price_converter_interest_mode`: Interest mode for the variation
- `_price_converter_interest_value`: Interest value for the variation

### New Hooks

- `woocommerce_product_after_variable_attributes`: Adds variation fields
- `woocommerce_save_product_variation`: Saves variation data

### Enhanced Methods

- `apply_interest()`: Now supports product-specific and variation-specific settings
- `filter_product_price()`: Enhanced to handle per-product interest rates
- `add_variation_pricing_fields()`: New method for variation fields
- `save_variation_data()`: New method for saving variation data

## CSS Classes for Styling

### Product Level
- `.price-converter-section`: Main product settings container
- `.price-converter-row`: Row container for fields
- `.price-converter-field`: Individual field container
- `.price-converter-input`: Input field styling
- `.price-converter-select`: Select field styling

### Variation Level
- `.price-converter-variation-section`: Variation settings container
- `.price-converter-variation-row`: Variation row container
- `.price-converter-variation-field`: Variation field container
- `.price-converter-variation-input`: Variation input styling
- `.price-converter-variation-select`: Variation select styling

## JavaScript Functionality

### Product Level
- Real-time price conversion updates
- Interest rate calculations
- AJAX price fetching

### Variation Level
- Individual variation price updates
- Variation-specific AJAX calls
- Dynamic interest calculations

## Testing

Use the included `test-variation-features.php` file to verify that all new functionality is working correctly:

1. Upload the test file to your WordPress root directory
2. Access it via browser: `yoursite.com/test-variation-features.php`
3. Review the test results to ensure all features are properly loaded

## Compatibility

- **WordPress**: 5.0+
- **WooCommerce**: 5.0+
- **PHP**: 7.4+
- **Browser**: Modern browsers with JavaScript enabled

## Troubleshooting

### Common Issues

1. **Variation fields not showing**: Ensure the product is set as "Variable product"
2. **Interest not applying**: Check that interest mode is not set to "none"
3. **Prices not converting**: Verify that base price and currency are set
4. **AJAX errors**: Check browser console for JavaScript errors

### Debug Mode

Enable WordPress debug mode to see detailed error logs:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For technical support or feature requests, please refer to the main plugin documentation or create an issue in the plugin repository.

## Changelog

### **v2.2.0**
- ✅ Added support for WooCommerce variable products — you can now set base prices and currencies for each variation, and the plugin will automatically calculate converted prices per variation.
- ✅ Implemented hierarchical price parameter inheritance — variations can inherit interest mode and value from the parent product when not explicitly set.
- ✅ Enhanced variation edit UI — added a live price preview field that updates in real-time using AJAX whenever relevant inputs (base price, currency, interest settings) change.
- ✅ Fixed variable product price range display issues by integrating with woocommerce_get_variation_prices_hash.
- ✅ Standardized translation file structure, added missing .mo files, and enabled proper Loco Translate syncing.

### Version 2.1.0
- Added per-product interest rate settings
- Added variation-specific price converter settings
- Implemented smart inheritance system
- Enhanced admin interface for variations
- Added comprehensive testing and documentation

### Previous Versions
- See main plugin changelog for earlier features
