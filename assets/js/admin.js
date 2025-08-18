jQuery(document).ready(function ($) {

    // Test price fetching functionality
    $('#test_fetch_price').on('click', function () {
        var url = $('#test_url').val();
        var selector = $('#test_selector').val();

        if (!url) {
            alert('Please enter a URL to test.');
            return;
        }

        var $button = $(this);
        var $spinner = $('#fetch_price_spinner');
        var $results = $('#test_results');
        var $resultsContent = $('#test_results_content');

        $button.prop('disabled', true);
        $spinner.show();
        $results.hide();

        $.ajax({
            url: priceConverterAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'price_converter_fetch_price',
                nonce: priceConverterAjax.nonce,
                url: url,
                selector: selector
            },
            success: function (response) {
                if (response.success) {
                    var price = response.data.price;
                    var currency = response.data.currency;

                    $resultsContent.html(
                        '<div class="notice notice-success">' +
                        '<p><strong>Price fetched successfully!</strong></p>' +
                        '<p>Price: ' + price + ' ' + currency + '</p>' +
                        '</div>'
                    );

                    // Show convert button
                    $('#test_convert_price').show().data('price', price).data('currency', currency);

                } else {
                    $resultsContent.html(
                        '<div class="notice notice-error">' +
                        '<p><strong>Error:</strong> ' + response.data + '</p>' +
                        '</div>'
                    );
                }

                $results.show();
            },
            error: function () {
                $resultsContent.html(
                    '<div class="notice notice-error">' +
                    '<p><strong>Error:</strong> Failed to fetch price. Please try again.</p>' +
                    '</div>'
                );
                $results.show();
            },
            complete: function () {
                $button.prop('disabled', false);
                $spinner.hide();
            }
        });
    });

    // Test price conversion
    $('#test_convert_price').on('click', function () {
        var price = $(this).data('price');
        var currency = $(this).data('currency');

        if (!price) {
            alert('Please fetch a price first.');
            return;
        }

        var $button = $(this);
        var $resultsContent = $('#test_results_content');

        $button.prop('disabled', true);

        $.ajax({
            url: priceConverterAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'price_converter_convert_price',
                nonce: priceConverterAjax.nonce,
                price: price,
                currency: currency
            },
            success: function (response) {
                if (response.success) {
                    var originalPrice = response.data.original_price;
                    var convertedPrice = response.data.converted_price;
                    var currency = response.data.currency;

                    $resultsContent.html(
                        '<div class="notice notice-success">' +
                        '<p><strong>Price converted successfully!</strong></p>' +
                        '<p>Original Price: ' + originalPrice + ' USD</p>' +
                        '<p>Converted Price: ' + convertedPrice.toLocaleString() + ' ' + currency + '</p>' +
                        '</div>'
                    );
                } else {
                    $resultsContent.html(
                        '<div class="notice notice-error">' +
                        '<p><strong>Error:</strong> ' + response.data + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function () {
                $resultsContent.html(
                    '<div class="notice notice-error">' +
                    '<p><strong>Error:</strong> Failed to convert price. Please try again.</p>' +
                    '</div>'
                );
            },
            complete: function () {
                $button.prop('disabled', false);
            }
        });
    });

    // Note: Pricing tab JS is now inline within WooCommerce pricing UI output

    // Variation-specific functionality
    $(document).on('click', '.pc_fetch_price_variation', function () {
        var loop = $(this).data('loop');
        var url = $('#price_converter_source_url_' + loop).val();
        var selector = $('#price_converter_source_selector_' + loop).val();

        if (!url) {
            alert('Please enter a source URL for this variation.');
            return;
        }

        var $btn = $(this);
        var $spinner = $btn.siblings('.spinner');

        $btn.prop('disabled', true);
        $spinner.show();

        $.ajax({
            url: priceConverterAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'price_converter_fetch_price',
                nonce: priceConverterAjax.nonce,
                url: url,
                selector: selector
            },
            success: function (response) {
                if (response.success) {
                    var fetched = response.data.price;
                    $('#price_converter_base_price_' + loop).val(fetched).trigger('change');

                    // Update the converted price display
                    updateVariationIrt(loop);
                } else {
                    alert(response.data || 'Error fetching price');
                }
            },
            error: function () {
                alert('Error occurred while fetching price');
            },
            complete: function () {
                $btn.prop('disabled', false);
                $spinner.hide();
            }
        });
    });

    // Update variation IRT price when base price or currency changes
    $(document).on('input change', '[id^="price_converter_base_price_"], [id^="price_converter_base_currency_"], [id^="price_converter_interest_mode_"], [id^="price_converter_interest_value_"]', function () {
        var id = $(this).attr('id');
        var loop = id.match(/\d+$/)[0];
        updateVariationIrt(loop);
    });

    // Function to update variation IRT price
    function updateVariationIrt(loop) {
        var amount = parseFloat($('#price_converter_base_price_' + loop).val() || '0');
        var currency = $('#price_converter_base_currency_' + loop).val() || 'USD';
        var interestMode = $('#price_converter_interest_mode_' + loop).val() || 'inherit';
        var interestValue = parseFloat($('#price_converter_interest_value_' + loop).val() || '0');

        if (amount > 0) {
            $.ajax({
                url: priceConverterAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'price_converter_convert_price',
                    nonce: priceConverterAjax.nonce,
                    price: amount,
                    currency: currency,
                    interest_mode: interestMode,
                    interest_value: interestValue
                },
                success: function (response) {
                    if (response.success) {
                        var val = response.data.converted_price;
                        $('#pc_converted_irt_' + loop).val(val.toLocaleString());
                    }
                }
            });
        } else {
            $('#pc_converted_irt_' + loop).val('');
        }
    }
});
