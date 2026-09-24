<?php
if ( ! defined( 'ABSPATH' ) ) {
    die("No direct access!");
}

if ( ! class_exists( 'SCURL_Share_Cart_URL' ) ) {

    class SCURL_Share_Cart_URL {

        /**
         * Session keys copied into, and restored from, a shared cart.
         *
         * @var array
         */
        private static $session_cart_keys = array(
            'cart', 'cart_totals', 'applied_coupons', 'coupon_discount_totals', 'coupon_discount_tax_totals'
        );

        /**
         * Counter used so only the first rendered widget carries the legacy
         * element IDs. Prevents duplicate IDs when the shortcode and the hook
         * position are both in use, while keeping existing custom CSS working.
         *
         * @var int
         */
        private static $instance_count = 0;

        /**
         * Whether a classic cart hook already rendered the widget on this
         * request. The Cart block fallback checks this so a page holding both
         * the block and the classic cart does not get two copies.
         *
         * @var bool
         */
        private static $rendered_on_hook = false;

        /**
         * Positions that sit above the cart. Everything else in the setting
         * renders below it. Used only by the Cart block fallback, where the
         * fine grained classic positions have nowhere to attach.
         *
         * @var array
         */
        private static $positions_above_cart = array(
            'woocommerce_before_cart_table',
            'woocommerce_before_cart_contents',
            'woocommerce_cart_coupon',
        );

        /**
         * Whether a widget with the email button was rendered, so the footer
         * knows to print the email popup. The popup is printed once, outside
         * the cart form, so cart fragment refreshes never replace it.
         *
         * @var bool
         */
        private static $email_modal_needed = false;

        /**
         * Longest note, in characters, accepted in the email popup.
         */
        const EMAIL_MESSAGE_MAX_LENGTH = 500;

        public function __construct() {
            $this->init();
        }

        public function init() {
            // Get the button position (hook) from settings. Default to 'woocommerce_before_cart_table'.
            $position = get_option( 'scurl_button_position', 'woocommerce_before_cart_table' );

            if ( $position !== 'hide' ) {
                add_action( $position, array( __CLASS__, 'scurl_render_share_cart_interface' ) );
                // The Cart block does not fire any of the classic cart hooks, so
                // the widget has to be attached to the block itself there.
                add_filter( 'render_block', array( __CLASS__, 'scurl_render_in_cart_block' ), 10, 2 );
            }
            add_shortcode( 'share_cart_url', array( __CLASS__, 'scurl_shortcode' ) );

            add_action( 'woocommerce_load_cart_from_session', array( __CLASS__, 'scurl_apply_shared_cart_session' ), 1 );
            add_action( 'wp_ajax_generate_share_link', array( __CLASS__, 'scurl_ajax_generate_share_link' ) );
            add_action( 'wp_ajax_nopriv_generate_share_link', array( __CLASS__, 'scurl_ajax_generate_share_link' ) );

            if ( self::scurl_is_email_enabled() ) {
                add_action( 'wp_ajax_scurl_email_cart', array( __CLASS__, 'scurl_ajax_email_cart' ) );
                add_action( 'wp_ajax_nopriv_scurl_email_cart', array( __CLASS__, 'scurl_ajax_email_cart' ) );
                add_action( 'wp_footer', array( __CLASS__, 'scurl_render_email_modal' ) );
            }

            add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scurl_scripts_and_styles' ) );
        }

        /**
         * Whether the "Email this cart" button is switched on in settings.
         *
         * @return bool
         */
        public static function scurl_is_email_enabled() {
            return 'yes' === get_option( 'scurl_email_cart_enabled', 'no' );
        }

        /**
         * Serialize the shareable part of the current WooCommerce session.
         *
         * @return string Serialized cart session, or an empty string when unavailable.
         */
        public static function scurl_get_session_cart() {
            if ( ! function_exists( 'WC' ) || ! WC()->session ) {
                return '';
            }

            $cart_session = array();
            foreach ( self::$session_cart_keys as $key ) {
                $cart_session[ $key ] = WC()->session->get( $key );
            }
            return serialize( $cart_session );
        }

        /**
         * Build (and persist) the share URL for the current cart.
         *
         * The hash is derived from the cart contents, so an unchanged cart always
         * produces the same URL and reuses the same stored file.
         *
         * @return string Share URL, or an empty string when the cart cannot be shared.
         */
        public static function scurl_generate_share_url() {
            if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
                return '';
            }

            $session_cart = self::scurl_get_session_cart();
            if ( '' === $session_cart ) {
                return '';
            }

            $hash = wp_hash( $session_cart );
            $file = get_temp_dir() . $hash;

            // Only write when the file is missing; identical carts reuse it.
            if ( ! file_exists( $file ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                if ( false === @file_put_contents( $file, $session_cart ) ) {
                    return '';
                }
            }

            $share_url = add_query_arg( 'share', $hash, wc_get_cart_url() );

            /**
             * Filter the generated share URL.
             *
             * @param string $share_url The share URL.
             * @param string $hash      The cart hash.
             */
            return apply_filters( 'scurl_share_url', $share_url, $hash );
        }

        /**
         * Restore a shared cart into the current session.
         */
        public static function scurl_apply_shared_cart_session() {

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public share link, affects only the visitor's own cart.
            if ( ! isset( $_REQUEST['share'] ) ) {
                return;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $hash = sanitize_file_name( wp_unslash( $_REQUEST['share'] ) );

            // Stored hashes are wp_hash() output: 32 hexadecimal characters.
            if ( ! preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
                return;
            }

            $file = get_temp_dir() . $hash;

            if ( ! file_exists( $file ) ) {
                return;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
            $contents = @file_get_contents( $file );
            if ( false === $contents || '' === $contents ) {
                return;
            }

            // allowed_classes => false prevents PHP object injection from a
            // crafted file, which matters on hosts with a shared temp directory.
            $cart = unserialize( $contents, array( 'allowed_classes' => false ) );

            if ( ! is_array( $cart ) ) {
                return;
            }

            foreach ( self::$session_cart_keys as $key ) {
                if ( array_key_exists( $key, $cart ) ) {
                    WC()->session->set( $key, $cart[ $key ] );
                }
            }
        }

        /**
         * Resolve the button label.
         *
         * @return string
         */
        public static function scurl_get_button_text() {
            $text = (string) get_option( 'scurl_button_text', '' );

            if ( '' === trim( $text ) ) {
                $text = __( 'Share this cart', 'share-cart-for-woocommerce' );
            }

            return apply_filters( 'scurl_button_text', $text );
        }

        /**
         * Build the share widget markup.
         *
         * The share URL is rendered into the markup up front so the copy action
         * can run synchronously inside the click event. Safari and iOS reject
         * clipboard writes issued from an async callback, which is why copying
         * previously failed on Apple devices.
         *
         * @return string
         */
        public static function scurl_get_share_cart_html() {
            $share_url = self::scurl_generate_share_url();

            if ( '' === $share_url ) {
                return '';
            }

            self::$instance_count++;
            $is_first = ( 1 === self::$instance_count );
            $native_share_enabled = 'yes' === get_option( 'scurl_native_share_enabled', 'no' );
            $email_enabled        = self::scurl_is_email_enabled();

            if ( $email_enabled ) {
                self::$email_modal_needed = true;
            }

            ob_start();
            ?>
            <div class="scurl-share-cart" data-share-url="<?php echo esc_attr( $share_url ); ?>">
                <button type="button" class="button scurl-share-btn"<?php echo $is_first ? ' id="share-cart-btn"' : ''; ?>>
                    <?php echo esc_html( self::scurl_get_button_text() ); ?>
                </button>
                <div class="scurl-share-output"<?php echo $is_first ? ' id="share-cart-url"' : ''; ?> hidden>
                    <div class="scurl-share-row">
                        <input type="text" class="scurl-share-input" value="<?php echo esc_attr( $share_url ); ?>" readonly
                            aria-label="<?php esc_attr_e( 'Shared cart link', 'share-cart-for-woocommerce' ); ?>" />
                        <span class="scurl-share-actions">
                            <button type="button" class="scurl-icon-btn scurl-copy-btn" aria-label="<?php esc_attr_e( 'Copy link', 'share-cart-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Copy link', 'share-cart-for-woocommerce' ); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                            <?php if ( $email_enabled ) : ?>
                                <button type="button" class="scurl-icon-btn scurl-email-btn" aria-haspopup="dialog" aria-controls="scurl-email-modal" aria-label="<?php esc_attr_e( 'Email this cart', 'share-cart-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Email this cart', 'share-cart-for-woocommerce' ); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                        <path d="m22 7-10 6L2 7"></path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                            <?php if ( $native_share_enabled ) : ?>
                                <button type="button" class="scurl-icon-btn scurl-native-share-btn" aria-label="<?php esc_attr_e( 'Share link', 'share-cart-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Share link', 'share-cart-for-woocommerce' ); ?>" hidden>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <circle cx="18" cy="5" r="3"></circle>
                                        <circle cx="6" cy="12" r="3"></circle>
                                        <circle cx="18" cy="19" r="3"></circle>
                                        <path d="M8.59 13.51 15.42 17.49"></path>
                                        <path d="M15.41 6.51 8.59 10.49"></path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="scurl-share-feedback" role="status" aria-live="polite"></span>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Render the widget on a WooCommerce hook.
         */
        public static function scurl_render_share_cart_interface() {
            $html = self::scurl_get_share_cart_html();

            if ( '' === $html ) {
                return;
            }

            self::$rendered_on_hook = true;

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in scurl_get_share_cart_html().
            echo $html;
        }

        /**
         * Render the widget alongside the WooCommerce Cart block.
         *
         * The block cart is a React app. None of the classic cart hooks
         * (woocommerce_before_cart_table and friends) are fired by it, which is
         * why the Button Position setting appeared to do nothing on a block
         * cart while the shortcode kept working.
         *
         * The block's server output is only a placeholder skeleton that the
         * script replaces on hydration, so anything injected inside it is
         * discarded. The widget is placed around the block instead, above or
         * below it according to the chosen position.
         *
         * @param string $block_content Rendered block HTML.
         * @param array  $block         Parsed block.
         * @return string
         */
        public static function scurl_render_in_cart_block( $block_content, $block ) {
            if ( empty( $block['blockName'] ) || 'woocommerce/cart' !== $block['blockName'] ) {
                return $block_content;
            }

            if ( self::$rendered_on_hook ) {
                return $block_content;
            }

            $html = self::scurl_get_share_cart_html();

            if ( '' === $html ) {
                return $block_content;
            }

            self::$rendered_on_hook = true;

            $position = get_option( 'scurl_button_position', 'woocommerce_before_cart_table' );

            if ( in_array( $position, self::$positions_above_cart, true ) ) {
                return $html . $block_content;
            }

            return $block_content . $html;
        }

        /**
         * Shortcode handler.
         *
         * Returns markup rather than echoing it, so the widget appears where the
         * shortcode sits instead of being pushed to the top of the content.
         *
         * @return string
         */
        public static function scurl_shortcode() {
            return self::scurl_get_share_cart_html();
        }

        /**
         * AJAX endpoint used to refresh the share URL after the cart changes.
         */
        public static function scurl_ajax_generate_share_link() {
            check_ajax_referer( 'scurl_share_cart_nonce', 'nonce' );

            $share_url = self::scurl_generate_share_url();

            if ( '' === $share_url ) {
                wp_send_json_error( array( 'message' => __( 'Your cart is empty or the link could not be created.', 'share-cart-for-woocommerce' ) ) );
            }

            wp_send_json_success( array( 'url' => esc_url_raw( $share_url ) ) );
        }

        /**
         * Print the "Email this cart" popup in the footer.
         *
         * Printed once per page and only when a widget with the email button
         * was rendered, so pages without the widget stay untouched.
         */
        public static function scurl_render_email_modal() {
            if ( ! self::$email_modal_needed ) {
                return;
            }

            // Logged in customers can switch between their account email and
            // another address. Guests just get an empty field.
            $account_email = '';
            if ( is_user_logged_in() ) {
                $account_email = wp_get_current_user()->user_email;
            }
            ?>
            <div class="scurl-email-modal" id="scurl-email-modal" hidden>
                <div class="scurl-email-backdrop" data-scurl-close></div>
                <div class="scurl-email-dialog" role="dialog" aria-modal="true" aria-labelledby="scurl-email-title">
                    <button type="button" class="scurl-email-close" data-scurl-close aria-label="<?php esc_attr_e( 'Close', 'share-cart-for-woocommerce' ); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                    <h2 class="scurl-email-title" id="scurl-email-title"><?php esc_html_e( 'Email this cart', 'share-cart-for-woocommerce' ); ?></h2>
                    <p class="scurl-email-intro"><?php esc_html_e( 'We will send a link to this cart so you can pick up where you left off.', 'share-cart-for-woocommerce' ); ?></p>
                    <form class="scurl-email-form" novalidate>
                        <p class="scurl-email-field">
                            <span class="scurl-email-label-row">
                                <label for="scurl-email-to"><?php esc_html_e( 'Email address', 'share-cart-for-woocommerce' ); ?> <span aria-hidden="true">*</span></label>
                                <?php if ( '' !== $account_email ) : ?>
                                    <span class="scurl-email-recipient" role="group" aria-label="<?php esc_attr_e( 'Send to', 'share-cart-for-woocommerce' ); ?>">
                                        <button type="button" class="scurl-email-recipient-btn" data-scurl-recipient="self" data-scurl-email="<?php echo esc_attr( $account_email ); ?>" aria-pressed="true"><?php esc_html_e( 'You', 'share-cart-for-woocommerce' ); ?></button>
                                        <button type="button" class="scurl-email-recipient-btn" data-scurl-recipient="other" aria-pressed="false"><?php esc_html_e( 'Other', 'share-cart-for-woocommerce' ); ?></button>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <input type="email" id="scurl-email-to" name="email" required autocomplete="email" value="<?php echo esc_attr( $account_email ); ?>" />
                        </p>
                        <p class="scurl-email-field">
                            <label for="scurl-email-message"><?php esc_html_e( 'Message (optional)', 'share-cart-for-woocommerce' ); ?></label>
                            <textarea id="scurl-email-message" name="message" rows="4" maxlength="<?php echo esc_attr( self::EMAIL_MESSAGE_MAX_LENGTH ); ?>" placeholder="<?php esc_attr_e( 'Add a note to include in the email', 'share-cart-for-woocommerce' ); ?>"></textarea>
                        </p>
                        <p class="scurl-email-submit-row">
                            <button type="submit" class="button scurl-email-submit"><?php esc_html_e( 'Send', 'share-cart-for-woocommerce' ); ?></button>
                        </p>
                        <p class="scurl-email-feedback" role="status" aria-live="polite"></p>
                    </form>
                </div>
            </div>
            <?php
        }

        /**
         * Count an email send against the visitor's hourly allowance.
         *
         * The endpoint is public, so without a cap it could be used to relay
         * mail from the store to any address.
         *
         * @return bool True when the visitor has used up their allowance.
         */
        private static function scurl_email_rate_limited() {
            $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
            $key = 'scurl_email_' . md5( $ip );

            /**
             * Filter how many cart emails one visitor may send per hour.
             *
             * @param int $limit Default 5.
             */
            $limit = (int) apply_filters( 'scurl_email_rate_limit', 5 );
            $count = (int) get_transient( $key );

            if ( $limit > 0 && $count >= $limit ) {
                return true;
            }

            set_transient( $key, $count + 1, HOUR_IN_SECONDS );
            return false;
        }

        /**
         * AJAX endpoint that emails the current cart link.
         *
         * The link is built here from the visitor's own session, never taken
         * from the request, so the form cannot be used to send arbitrary links.
         */
        public static function scurl_ajax_email_cart() {
            check_ajax_referer( 'scurl_share_cart_nonce', 'nonce' );

            $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

            if ( '' === $email || ! is_email( $email ) ) {
                wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'share-cart-for-woocommerce' ) ) );
            }

            $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
            $message = trim( mb_substr( $message, 0, self::EMAIL_MESSAGE_MAX_LENGTH ) );

            $share_url = self::scurl_generate_share_url();

            if ( '' === $share_url ) {
                wp_send_json_error( array( 'message' => __( 'Your cart is empty or the link could not be created.', 'share-cart-for-woocommerce' ) ) );
            }

            if ( self::scurl_email_rate_limited() ) {
                wp_send_json_error( array( 'message' => __( 'Too many emails sent. Please try again later.', 'share-cart-for-woocommerce' ) ) );
            }

            $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

            /* translators: %s: site name */
            $subject = sprintf( __( 'Your cart from %s', 'share-cart-for-woocommerce' ), $site_name );
            $heading = __( 'Your saved cart', 'share-cart-for-woocommerce' );

            $body = '';
            if ( '' !== $message ) {
                $body .= '<p>' . nl2br( esc_html( $message ) ) . '</p>';
            }
            $body .= '<p>' . esc_html(
                /* translators: %s: site name */
                sprintf( __( 'Here is the link to your cart at %s. Open it to restore your items and continue shopping.', 'share-cart-for-woocommerce' ), $site_name )
            ) . '</p>';
            $body .= '<p><a href="' . esc_url( $share_url ) . '">' . esc_html__( 'View your cart', 'share-cart-for-woocommerce' ) . '</a></p>';
            $body .= '<p style="word-break:break-all;font-size:12px;">' . esc_html( $share_url ) . '</p>';

            /**
             * Filter the cart email before it is sent.
             *
             * @param array $email_args {
             *     @type string $subject Email subject.
             *     @type string $heading Heading shown in the WooCommerce email template.
             *     @type string $body    HTML body, before the template is applied.
             * }
             * @param string $share_url The cart link.
             * @param string $message   The visitor's note, already sanitized.
             */
            $email_args = apply_filters( 'scurl_email_cart_args', array(
                'subject' => $subject,
                'heading' => $heading,
                'body'    => $body,
            ), $share_url, $message );

            // The WooCommerce mailer wraps the body in the store's email
            // template, so the message matches the shop's other emails.
            $mailer = WC()->mailer();
            $sent   = $mailer->send(
                $email,
                $email_args['subject'],
                $mailer->wrap_message( $email_args['heading'], $email_args['body'] )
            );

            if ( ! $sent ) {
                wp_send_json_error( array( 'message' => __( 'The email could not be sent. Please try again later.', 'share-cart-for-woocommerce' ) ) );
            }

            wp_send_json_success( array( 'message' => __( 'Email sent. Check your inbox for the cart link.', 'share-cart-for-woocommerce' ) ) );
        }

        public static function scurl_scripts_and_styles(){
            wp_enqueue_script('scurl-script', SCURL_PLUGIN_PATH . 'assets/js/scurl.js', array('jquery'), SCURL_VERSION, true);
            wp_localize_script('scurl-script', 'share_cart_ajax', array(
                'ajax_url'    => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('scurl_share_cart_nonce'),
                'share_title' => get_bloginfo( 'name' ),
                'i18n'        => array(
                    'copied'      => __( 'Link copied to clipboard.', 'share-cart-for-woocommerce' ),
                    'copy_failed' => __( 'Press and hold the link above to copy it.', 'share-cart-for-woocommerce' ),
                    'refresh_err' => __( 'Could not refresh the link. Please reload the page.', 'share-cart-for-woocommerce' ),
                    'sending'     => __( 'Sending…', 'share-cart-for-woocommerce' ),
                    'send'        => __( 'Send', 'share-cart-for-woocommerce' ),
                    'email_bad'   => __( 'Please enter a valid email address.', 'share-cart-for-woocommerce' ),
                    'email_err'   => __( 'The email could not be sent. Please try again later.', 'share-cart-for-woocommerce' ),
                ),
            ));

            wp_enqueue_style('scurl-style', SCURL_PLUGIN_PATH . 'assets/css/scurl.css', array(), SCURL_VERSION);
        }
    }

    new SCURL_Share_Cart_URL();
}
