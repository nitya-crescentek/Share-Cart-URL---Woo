/**
 * Share Cart for WooCommerce
 *
 * The share URL is rendered into the markup by PHP, so the clipboard write can
 * run synchronously inside the click handler. Safari and iOS only allow a
 * clipboard write while the user gesture is still active, so copying from an
 * AJAX callback silently fails there.
 */
jQuery(function ($) {

    var i18n = (window.share_cart_ajax && share_cart_ajax.i18n) || {};

    /**
     * Legacy copy path for browsers without the async clipboard API, or for
     * pages served over plain HTTP where it is unavailable.
     *
     * @param {string} text
     * @return {boolean} Whether the copy actually succeeded.
     */
    function legacyCopy(text) {
        var textarea = document.createElement('textarea');
        var succeeded = false;

        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        textarea.style.top = (window.pageYOffset || document.documentElement.scrollTop) + 'px';
        // 16px or larger stops iOS from zooming when the field is focused.
        textarea.style.fontSize = '16px';
        textarea.style.padding = '0';
        textarea.style.border = '0';
        textarea.style.margin = '0';

        document.body.appendChild(textarea);

        try {
            // iOS ignores select() on its own and needs an explicit range.
            var range = document.createRange();
            range.selectNodeContents(textarea);

            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            textarea.setSelectionRange(0, textarea.value.length);
            succeeded = document.execCommand('copy');
        } catch (e) {
            succeeded = false;
        }

        document.body.removeChild(textarea);

        return succeeded;
    }

    /**
     * Copy text to the clipboard. Must be called synchronously from a user
     * gesture, otherwise Safari rejects it.
     *
     * @param {string} text
     * @return {Promise}
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return legacyCopy(text) ? Promise.resolve() : Promise.reject();
    }

    /**
     * Reveal the link panel and report the outcome of the copy.
     *
     * @param {jQuery} $widget
     * @param {boolean} copied
     */
    function showResult($widget, copied) {
        var $output = $widget.find('.scurl-share-output');
        var $input = $widget.find('.scurl-share-input');

        $output.prop('hidden', false);
        $widget.find('.scurl-share-feedback').text(copied ? i18n.copied : i18n.copy_failed);

        if (navigator.share) {
            $widget.find('.scurl-native-share-btn').prop('hidden', false);
        }

        // When the copy failed, pre-select the link so it can be copied by hand.
        if (!copied && $input.length) {
            try {
                $input.trigger('focus');
                $input[0].setSelectionRange(0, $input.val().length);
            } catch (e) {}
        }
    }

    /**
     * Copy this widget's URL and update the UI.
     *
     * @param {jQuery} $widget
     */
    function handleCopy($widget) {
        var url = $widget.data('share-url');

        if (!url) {
            return;
        }

        // Called synchronously here, while the click gesture is still active.
        copyToClipboard(url).then(function () {
            showResult($widget, true);
        }).catch(function () {
            showResult($widget, false);
        });
    }

    $(document.body).on('click', '.scurl-share-btn, .scurl-copy-btn', function (e) {
        e.preventDefault();
        handleCopy($(this).closest('.scurl-share-cart'));
    });

    // Native share sheet. On mobile this is usually what people actually want.
    $(document.body).on('click', '.scurl-native-share-btn', function (e) {
        e.preventDefault();

        var $widget = $(this).closest('.scurl-share-cart');
        var url = $widget.data('share-url');

        if (!navigator.share || !url) {
            return;
        }

        navigator.share({
            title: (window.share_cart_ajax && share_cart_ajax.share_title) || document.title,
            url: url
        }).catch(function () {
            // The user dismissed the share sheet. Nothing to do.
        });
    });

    // Show the native share button up front where it is supported.
    if (navigator.share) {
        $('.scurl-native-share-btn').prop('hidden', false);
    }

    /**
     * After a cart update the contents changed, so the previous URL points at a
     * stale cart. Refresh it in the background; the markup keeps working with
     * its server rendered value if the request fails.
     */
    $(document.body).on('updated_wc_div updated_cart_totals', function () {
        var $widgets = $('.scurl-share-cart');

        if (!$widgets.length) {
            return;
        }

        // Collapse any panel left open from before the update.
        $widgets.find('.scurl-share-output').prop('hidden', true);
        $widgets.find('.scurl-share-feedback').text('');

        $.ajax({
            url: share_cart_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_share_link',
                nonce: share_cart_ajax.nonce
            }
        }).done(function (response) {
            if (response && response.success && response.data.url) {
                $('.scurl-share-cart')
                    .attr('data-share-url', response.data.url)
                    .data('share-url', response.data.url)
                    .find('.scurl-share-input').val(response.data.url);
            }
        });
    });
});
