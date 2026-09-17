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

        public function __construct() {
            $this->init();
        }

        public function init() {
            // Get the button position (hook) from settings. Default to 'woocommerce_before_cart_table'.
            $position = get_option( 'scurl_button_position', 'woocommerce_before_cart_table' );

            if ( $position !== 'hide' ) {
                add_action( $position, array( __CLASS__, 'scurl_render_share_cart_interface' ) );
            }
            add_shortcode( 'share_cart_url', array( __CLASS__, 'scurl_shortcode' ) );

            add_action( 'woocommerce_load_cart_from_session', array( __CLASS__, 'scurl_apply_shared_cart_session' ), 1 );
            add_action( 'wp_ajax_generate_share_link', array( __CLASS__, 'scurl_ajax_generate_share_link' ) );
            add_action( 'wp_ajax_nopriv_generate_share_link', array( __CLASS__, 'scurl_ajax_generate_share_link' ) );

            add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scurl_scripts_and_styles' ) );
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
                        <button type="button" class="button scurl-copy-btn"><?php esc_html_e( 'Copy', 'share-cart-for-woocommerce' ); ?></button>
                        <button type="button" class="button scurl-native-share-btn" hidden><?php esc_html_e( 'Share', 'share-cart-for-woocommerce' ); ?></button>
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
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in scurl_get_share_cart_html().
            echo self::scurl_get_share_cart_html();
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
                ),
            ));

            wp_enqueue_style('scurl-style', SCURL_PLUGIN_PATH . 'assets/css/scurl.css', array(), SCURL_VERSION);
        }
    }

    new SCURL_Share_Cart_URL();
}
