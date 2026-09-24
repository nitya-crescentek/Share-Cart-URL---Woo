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

    /**
     * "Email this cart" popup. It is printed once in the footer and shared by
     * every widget on the page.
     */
    var $emailModal = $('#scurl-email-modal');
    var emailOpener = null;

    function emailFocusable() {
        return $emailModal.find('.scurl-email-dialog')
            .find('button, input, textarea')
            .filter(':visible:not(:disabled)');
    }

    function openEmailModal(opener) {
        emailOpener = opener;

        $emailModal.find('.scurl-email-feedback').text('').removeClass('is-error is-success');
        $emailModal.prop('hidden', false);
        $(document.body).addClass('scurl-email-open');

        var $to = $emailModal.find('#scurl-email-to');
        ($to.val() ? $emailModal.find('#scurl-email-message') : $to).trigger('focus');
    }

    function closeEmailModal() {
        if ($emailModal.prop('hidden')) {
            return;
        }

        $emailModal.prop('hidden', true);
        $(document.body).removeClass('scurl-email-open');

        // Hand focus back to the button that opened the popup, if it is still
        // in the page after any cart refresh.
        if (emailOpener && document.body.contains(emailOpener)) {
            emailOpener.focus();
        }
        emailOpener = null;
    }

    $(document.body).on('click', '.scurl-email-btn', function (e) {
        e.preventDefault();
        openEmailModal(this);
    });

    /**
     * Mark the "You" or "Other" link as the active one.
     *
     * @param {string} recipient 'self' or 'other'.
     */
    function setRecipient(recipient) {
        $emailModal.find('.scurl-email-recipient-btn').each(function () {
            $(this).attr('aria-pressed', $(this).attr('data-scurl-recipient') === recipient ? 'true' : 'false');
        });
    }

    // "You" fills in the account email, "Other" clears the field for a
    // different address. Only rendered for logged in customers.
    $emailModal.on('click', '.scurl-email-recipient-btn', function (e) {
        e.preventDefault();

        var recipient = $(this).attr('data-scurl-recipient');
        var $to = $emailModal.find('#scurl-email-to');

        $to.val(recipient === 'self' ? $(this).attr('data-scurl-email') : '');
        setRecipient(recipient);
        $emailModal.find('.scurl-email-feedback').text('').removeClass('is-error is-success');
        $to.trigger('focus');
    });

    // Keep the links in step with what is typed, so editing the account email
    // by hand switches to "Other" and typing it back switches to "You".
    $emailModal.on('input', '#scurl-email-to', function () {
        var accountEmail = $emailModal.find('[data-scurl-recipient="self"]').attr('data-scurl-email');

        if (accountEmail) {
            setRecipient($.trim($(this).val()).toLowerCase() === accountEmail.toLowerCase() ? 'self' : 'other');
        }
    });

    $emailModal.on('click', '[data-scurl-close]', function (e) {
        e.preventDefault();
        closeEmailModal();
    });

    $(document).on('keydown', function (e) {
        if ($emailModal.prop('hidden')) {
            return;
        }

        if (e.key === 'Escape') {
            closeEmailModal();
            return;
        }

        // Keep Tab inside the popup while it is open.
        if (e.key === 'Tab') {
            var $items = emailFocusable();

            if (!$items.length) {
                return;
            }

            var first = $items[0];
            var last = $items[$items.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    $emailModal.on('submit', '.scurl-email-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submit = $form.find('.scurl-email-submit');
        var $feedback = $form.find('.scurl-email-feedback');
        var $to = $form.find('#scurl-email-to');
        var email = $.trim($to.val());

        $feedback.text('').removeClass('is-error is-success');

        if (!email || !$to[0].checkValidity()) {
            $feedback.text(i18n.email_bad).addClass('is-error');
            $to.trigger('focus');
            return;
        }

        $submit.prop('disabled', true).text(i18n.sending);

        // The server builds the link from the current session, so no URL is
        // sent from here.
        $.ajax({
            url: share_cart_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'scurl_email_cart',
                nonce: share_cart_ajax.nonce,
                email: email,
                message: $form.find('#scurl-email-message').val()
            }
        }).done(function (response) {
            var message = response && response.data && response.data.message;

            if (response && response.success) {
                $feedback.text(message).addClass('is-success');
                $form.find('#scurl-email-message').val('');
            } else {
                $feedback.text(message || i18n.email_err).addClass('is-error');
            }
        }).fail(function () {
            $feedback.text(i18n.email_err).addClass('is-error');
        }).always(function () {
            $submit.prop('disabled', false).text(i18n.send);
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
    function refreshShareUrls() {
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
    }

    // Classic cart and mini cart fragments.
    $(document.body).on('updated_wc_div updated_cart_totals', refreshShareUrls);

    /**
     * Build a value that changes whenever the shareable part of the block cart
     * changes. Prices and shipping are left out, since they do not travel in
     * the share link.
     *
     * @param {Object} cart Store API cart data.
     * @return {string}
     */
    function cartSignature(cart) {
        var items = (cart.items || []).map(function (item) {
            return item.key + ':' + item.quantity;
        }).join('|');

        var coupons = (cart.coupons || []).map(function (coupon) {
            return coupon.code;
        }).join('|');

        return items + '#' + coupons;
    }

    /**
     * The Cart block never fires the jQuery events above, so watch its own data
     * store instead. Returns false while the store is still unavailable, so the
     * caller can try again once the block scripts have loaded.
     *
     * @return {boolean} Whether the watcher was attached.
     */
    function watchBlockCart() {
        if (!window.wp || !wp.data || typeof wp.data.subscribe !== 'function') {
            return false;
        }

        var store = wp.data.select('wc/store/cart');

        if (!store || typeof store.getCartData !== 'function') {
            return false;
        }

        var signature = null;

        wp.data.subscribe(function () {
            var cart = wp.data.select('wc/store/cart');

            // Ignore the placeholder cart served before the first request
            // resolves, otherwise the initial load looks like a change.
            if (!cart.hasFinishedResolution('getCartData')) {
                return;
            }

            var next = cartSignature(cart.getCartData());

            if (signature === null) {
                signature = next;
                return;
            }

            if (next === signature) {
                return;
            }

            signature = next;
            refreshShareUrls();
        });

        return true;
    }

    if (!watchBlockCart()) {
        // Our script and the block scripts both load in the footer, so the
        // store may not be registered yet. Give it a few seconds, then stop.
        var attempts = 0;
        var timer = setInterval(function () {
            attempts++;

            if (watchBlockCart() || attempts > 20) {
                clearInterval(timer);
            }
        }, 250);
    }
});
