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
});
