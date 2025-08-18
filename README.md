# Price Converter Plugin - Enhanced UI & Multilingual Support

## 🚀 **New Features & Improvements**

### **1. Price Update Interval Settings**
- **New Setting**: Added configurable price update intervals
- **Options**: 30min, 1hour, 2hour, 4hour, 6hour, 12hour, daily, weekly, monthly
- **Purpose**: Control how often the plugin fetches exchange rates from APIs
- **Location**: Admin Settings → General Settings → Price Update Interval

### **2. Interest System (Replaced Tax)**
- **Change**: Renamed "Tax" to "Interest" throughout the plugin
- **Modes**: None, Percent, Fixed Amount (IRT)
- **Purpose**: Apply interest rates to converted prices
- **Location**: Admin Settings → General Settings → Interest Mode & Interest Value

### **3. Enhanced User Interface**
- **Removed**: Large header section for cleaner look
- **Added**: Professional footer with copyright information
- **Improved**: Better spacing, typography, and visual hierarchy
- **Status Overview**: Real-time display of plugin status including update interval

### **4. Multilingual Support**
- **Languages**: Persian (فارسی) and English
- **Auto-detection**: Based on WordPress dashboard language
- **Persian Support**: Full RTL support for Persian users
- **Language Files**: Located in `/languages/` directory

### **5. Copyright & Attribution**
- **Footer**: Added professional copyright footer
- **Credit**: Emjay Sepahi (emjaysepahi.com)
- **License**: GPL v2 or later

## 🔧 **Technical Changes**

### **Files Modified:**
1. **`includes/class-price-converter-admin.php`**
   - Added price interval settings
   - Changed tax to interest
   - Removed header section
   - Added copyright footer
   - Enhanced UI styling

2. **`includes/class-price-converter-woocommerce.php`**
   - Updated method names: `apply_tax()` → `apply_interest()`
   - Updated debug information
   - Enhanced product settings interface

3. **`includes/class-price-converter.php`**
   - Updated method names: `apply_tax_to_irt()` → `apply_interest()`
   - Enhanced error handling

4. **`price-converter-plugin.php`**
   - Added textdomain loading for translations
   - Enhanced plugin initialization

5. **`test-plugin-health.php`**
   - Removed header section
   - Added copyright footer
   - Enhanced styling

### **New Language Files:**
- **`languages/fa_IR.po`** - Persian translations
- **`languages/en_US.po`** - English translations

## 🌐 **Language Support**

### **Persian (فارسی)**
- **Locale**: `fa_IR`, `fa_AF`, `ps_AF`
- **Features**: Full RTL support, Persian translations
- **Usage**: Set WordPress dashboard to Persian

### **English**
- **Locale**: `en_US`, `en_GB`, etc.
- **Features**: Default language, fallback support
- **Usage**: Default WordPress language

## 📱 **UI Improvements**

### **Admin Settings Page:**
- Cleaner, more professional design
- Better organized settings sections
- Enhanced status overview widget
- Improved form styling and spacing

### **Product Settings:**
- Better organized price converter fields
- Enhanced visual feedback
- Improved error handling display

### **Health Check Script:**
- Professional appearance
- Better organized test sections
- Enhanced status indicators
- Copyright footer

## ⚙️ **Configuration**

### **Price Update Interval:**
```php
// Available options
'30min'    // 30 minutes
'1hour'    // 1 hour (default)
'2hour'    // 2 hours
'4hour'    // 4 hours
'6hour'    // 6 hours
'12hour'   // 12 hours
'daily'    // Daily
'weekly'   // Weekly
'monthly'  // Monthly
```

### **Interest Settings:**
```php
// Interest modes
'none'     // No interest applied
'percent'  // Percentage-based interest
'fixed'    // Fixed amount in IRT
```

## 🚀 **Installation & Setup**

1. **Upload Plugin**: Upload to `/wp-content/plugins/price-converter-plugin/`
2. **Activate**: Activate through WordPress admin
3. **Configure**: Go to WooCommerce → Price Converter
4. **Language**: Set WordPress dashboard language for translations
5. **Test**: Use the health check script to verify functionality

## 🔍 **Testing**

### **Health Check Script:**
- **File**: `test-plugin-health.php`
- **Usage**: Place in WordPress root directory
- **Access**: `https://yoursite.com/test-plugin-health.php`
- **Features**: Comprehensive plugin testing and diagnostics

## 📞 **Support & Documentation**

- **Developer**: Emjay Sepahi
- **Website**: [emjaysepahi.com](https://emjaysepahi.com)
- **Plugin**: [GitHub Repository](https://github.com/emjayi/price-converter-plugin)
- **License**: GPL v2 or later

## 🔄 **Version History**

### **v2.1.0** (Current)
- ✅ Added price update interval settings
- ✅ Changed tax system to interest system
- ✅ Enhanced UI and removed header
- ✅ Added multilingual support (Persian/English)
- ✅ Added copyright footer
- ✅ Improved error handling and debugging

### **v2.0.0**
- ✅ Fixed 503 errors
- ✅ Added custom currencies support
- ✅ Enhanced data-qa fetching
- ✅ Improved API handling and fallbacks

### **v1.0.0**
- ✅ Basic price conversion functionality
- ✅ WooCommerce integration
- ✅ External price fetching

---

**© 2024 Emjay Sepahi (emjaysepahi.com) - All rights reserved**
