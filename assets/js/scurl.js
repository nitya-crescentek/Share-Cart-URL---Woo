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
     * Detect iOS, including iPadOS which reports itself as a Mac.
     *
     * @return {boolean}
     */
    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    /**
     * Select the whole value of a text field.
     *
     * iOS refuses to select a readonly field, and needs the value exposed as
     * editable content before a range will take. Every other browser just needs
     * the field focused, and the range dance actively breaks it there, because
     * a field's value is not a child node.
     *
     * @param {HTMLInputElement|HTMLTextAreaElement} el
     */
    function selectField(el) {
        if (isIOS()) {
            var wasReadOnly = el.readOnly;
            var wasEditable = el.contentEditable;

            el.contentEditable = 'true';
            el.readOnly = false;

            var range = document.createRange();
            range.selectNodeContents(el);

            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            el.setSelectionRange(0, 999999);

            el.contentEditable = wasEditable;
            el.readOnly = wasReadOnly;
            return;
        }

        try {
            el.focus({ preventScroll: true });
        } catch (e) {
            el.focus();
        }

        el.select();

        try {
            el.setSelectionRange(0, el.value.length);
        } catch (e) {}
    }

    /**
     * Legacy copy path, used when the async clipboard API is unavailable. That
     * includes every page served over plain HTTP, where isSecureContext is false.
     *
     * @param {string} text
     * @param {HTMLInputElement} [field] Visible field holding the same text.
     * @return {boolean} Whether the copy actually succeeded.
     */
    function legacyCopy(text, field) {
        var el = field;
        var temporary = false;
        var succeeded = false;

        // Prefer the field already on screen. A detached or off-screen element
        // is refused by some browsers, and cannot be selected on iOS at all.
        if (!el || el.value !== text) {
            el = document.createElement('textarea');
            el.value = text;
            el.setAttribute('readonly', '');
            el.style.position = 'fixed';
            el.style.top = '0';
            el.style.left = '0';
            el.style.width = '1px';
            el.style.height = '1px';
            el.style.padding = '0';
            el.style.border = '0';
            el.style.margin = '0';
            // 16px or larger stops iOS from zooming when the field is focused.
            el.style.fontSize = '16px';
            document.body.appendChild(el);
            temporary = true;
        }

        try {
            selectField(el);
            succeeded = document.execCommand('copy');
        } catch (e) {
            succeeded = false;
        }

        if (temporary) {
            document.body.removeChild(el);
        }

        return succeeded;
    }

    /**
     * Copy text to the clipboard. Must be called synchronously from a user
     * gesture, otherwise Safari rejects it.
     *
     * @param {string} text
     * @param {HTMLInputElement} [field] Visible field holding the same text.
     * @return {Promise}
     */
    function copyToClipboard(text, field) {
        if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(function () {
                // Permission denied or a blocked context. Try the old way before
                // giving up, while the gesture is still live.
                return legacyCopy(text, field) ? Promise.resolve() : Promise.reject();
            });
        }

        return legacyCopy(text, field) ? Promise.resolve() : Promise.reject();
    }

    /**
     * Report the outcome of the copy.
     *
     * @param {jQuery} $widget
     * @param {boolean} copied
     */
    function showResult($widget, copied) {
        $widget.find('.scurl-share-feedback').text(copied ? i18n.copied : i18n.copy_failed);

        // When the copy failed, leave the link selected so it can be copied by hand.
        if (!copied) {
            var field = $widget.find('.scurl-share-input')[0];

            if (field) {
                try {
                    selectField(field);
                } catch (e) {}
            }
        }
    }

    /**
     * Copy this widget's URL and update the UI.
     *
     * @param {jQuery} $widget
     */
    function handleCopy($widget) {
        // Read the attribute directly. jQuery's .data() caches its first read,
        // which would go stale after the cart is updated.
        var url = $widget.attr('data-share-url');

        if (!url) {
            return;
        }

        // Reveal the panel synchronously, so the field is on screen and
        // selectable before the copy runs.
        $widget.find('.scurl-share-output').prop('hidden', false);

        if (navigator.share) {
            $widget.find('.scurl-native-share-btn').prop('hidden', false);
        }

        var field = $widget.find('.scurl-share-input')[0];

        // Called synchronously here, while the click gesture is still active.
        copyToClipboard(url, field).then(function () {
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
        var url = $widget.attr('data-share-url');

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
                    .find('.scurl-share-input').val(response.data.url);
            }
        });
    });
});
