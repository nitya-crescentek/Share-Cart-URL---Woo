jQuery(document).ready(function ($) {
    function initShareCartButton() {
        $('#share-cart-btn').off('click').on('click', function (e) {
            e.preventDefault();
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Generating...');

            $.ajax({
                url: share_cart_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_share_link',
                    nonce: share_cart_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var shareUrl = response.data.url;

                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(shareUrl).then(function () {
                                $('#share-cart-url').html('<span>Link copied to clipboard: ' + shareUrl + '</span>')
                                .css({
                                    'margin-bottom': '15px',
                                    'background': 'rgb(241, 241, 241)',
                                    'padding': '0.6em 1em'
                                });
                                $btn.hide();
                            }).catch(function() {
                                fallbackCopy(shareUrl);
                            });
                        } else {
                            fallbackCopy(shareUrl);
                        }
                    } else {
                        alert('Failed to generate share link. Please try again.');
                        $btn.prop('disabled', false).text('Share this cart');
                    }
                },
                error: function() {
                    alert('Error generating share link. Please try again.');
                    $btn.prop('disabled', false).text('Share this cart');
                }
            });
        });
    }

    function fallbackCopy(shareUrl) {
        var tempInput = $('<input>').val(shareUrl).appendTo('body').select();
        document.execCommand("copy");
        tempInput.remove();

        $('#share-cart-url')
            .html('<span>Link copied to clipboard - ' + shareUrl + '</span>')
            .css({
                'margin-bottom': '15px',
                'background': 'rgb(241, 241, 241)',
                'padding': '0.6em 1em'
            });

        $('#share-cart-btn').hide();
    }

    // Initialize on page load
    initShareCartButton();

    // Re-initialize after cart updates
    $(document.body).on('updated_wc_div', function () {
        $('#share-cart-url').empty();
        $('#share-cart-btn').show().prop('disabled', false).text('Share this cart');
        initShareCartButton(); // Re-bind the event handler
    });
});