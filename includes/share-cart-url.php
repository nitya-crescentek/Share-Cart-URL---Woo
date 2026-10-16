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
         * Whether a shared cart was replaced into or added to the visitor's
         * cart on this request, so the notice can be queued.
         *
         * @var bool
         */
        private static $shared_cart_applied = false;

        /**
         * Whether this request handled a share link or an answer from the
         * replace or add popup, so the page is reloaded with a clean URL.
         *
         * @var bool
         */
        private static $reload_needed = false;

        /**
         * Whether this request is the popup's form being submitted. That POST
         * is reloaded as a GET, unlike any other form submission.
         *
         * @var bool
         */
        private static $choice_submitted = false;

        /**
         * The replace or add question shown on this page view: the share hash
         * and the shared items to list.
         *
         * @var array
         */
        private static $choice_popup = array();

        /**
         * What opening the shared cart did to the visitor's cart: 'loaded',
         * 'replaced', 'merged' or 'unchanged'. Settled once the cart has
         * loaded, since merged coupons are only applied then.
         *
         * @var string
         */
        private static $shared_cart_result = '';

        /**
         * Shared coupons still to be applied after a merge, and whether that
         * last step has run.
         *
         * @var array
         */
        private static $pending_coupons = array();

        /**
         * @var bool
         */
        private static $shared_cart_finished = false;

        /**
         * Session key holding a share hash whose replace or add question has
         * not been shown yet.
         */
        const PENDING_SESSION_KEY = 'scurl_pending_share';

        /**
         * Session key holding the share hash of the question on screen. The
         * popup's answer is only accepted for this hash, once, so going back
         * and submitting it again cannot add the shared cart a second time.
         */
        const ASKED_SESSION_KEY = 'scurl_asked_share';

        /**
         * Shared cart notice taken from the session for this page view, and
         * whether it has been printed yet.
         *
         * @var string
         */
        private static $pending_notice = '';

        /**
         * @var bool
         */
        private static $notice_printed = false;

        /**
         * Session key holding the shared cart notice until the next page view.
         */
        const NOTICE_SESSION_KEY = 'scurl_shared_cart_notice';

        /**
         * Nonce action for both AJAX endpoints.
         *
         * WooCommerce ties a guest's nonce to their cart session only when the
         * action starts with "woocommerce". Without that, every guest shares
         * one nonce, and another site could submit the email form on a guest's
         * behalf when guests are allowed to send.
         */
        const NONCE_ACTION = 'woocommerce-scurl-share-cart';

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
            add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'scurl_finish_shared_cart' ) );
            // Priority 1 runs after the wc-ajax handler (0) and before canonical redirects.
            add_action( 'template_redirect', array( __CLASS__, 'scurl_redirect_after_shared_cart' ), 1 );
            add_action( 'template_redirect', array( __CLASS__, 'scurl_take_shared_cart_notice' ), 20 );
            add_action( 'template_redirect', array( __CLASS__, 'scurl_take_pending_choice' ), 20 );
            add_action( 'wp_footer', array( __CLASS__, 'scurl_render_choice_modal' ) );
            // Before WooCommerce prints its own notices on the classic cart (10).
            add_action( 'woocommerce_before_cart', array( __CLASS__, 'scurl_print_shared_cart_notice' ), 5 );
            // After the widget filter (10), so the notice sits above the widget.
            add_filter( 'render_block', array( __CLASS__, 'scurl_print_notice_before_cart_block' ), 11, 2 );
            add_action( 'wp_ajax_generate_share_link', array( __CLASS__, 'scurl_ajax_generate_share_link' ) );
            add_action( 'wp_ajax_nopriv_generate_share_link', array( __CLASS__, 'scurl_ajax_generate_share_link' ) );

            if ( self::scurl_is_email_enabled() ) {
                add_action( 'wp_ajax_scurl_email_cart', array( __CLASS__, 'scurl_ajax_email_cart' ) );
                // Guests can only reach the endpoint when the store lets them email carts.
                if ( self::scurl_guests_can_email() ) {
                    add_action( 'wp_ajax_nopriv_scurl_email_cart', array( __CLASS__, 'scurl_ajax_email_cart' ) );
                }
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
         * Whether a customer who already has items in their cart is asked to
         * replace it or add the shared cart to it. Off by default, so a shared
         * cart simply replaces theirs.
         *
         * @return bool
         */
        public static function scurl_customers_can_choose() {
            return 'yes' === get_option( 'scurl_shared_cart_choice', 'no' );
        }

        /**
         * Whether adding a shared cart combines the quantities of items in both
         * carts, rather than keeping the larger one.
         *
         * @return bool
         */
        public static function scurl_merge_combines() {
            return 'combine' === get_option( 'scurl_shared_cart_merge_qty', 'larger' );
        }

        /**
         * Whether guests may email carts. Off by default, so only logged in
         * customers can send.
         *
         * @return bool
         */
        public static function scurl_guests_can_email() {
            return 'everyone' === get_option( 'scurl_email_cart_access', 'logged_in' );
        }

        /**
         * Whether the current visitor may email the cart.
         *
         * @return bool
         */
        public static function scurl_can_email_cart() {
            return is_user_logged_in() || self::scurl_guests_can_email();
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
         * Handle a shared cart link, or the visitor's answer to the replace or
         * add question, before WooCommerce reads the cart from the session.
         *
         * A link replaces the visitor's cart. When customers may choose and
         * the visitor already has a different cart, nothing changes yet: the
         * question is kept in the session and asked on the next page view.
         */
        public static function scurl_apply_shared_cart_session() {
            if ( self::scurl_handle_choice() ) {
                return;
            }

            // An array, as in ?share[]=1, made sanitize_file_name() throw a
            // TypeError, so any visitor could crash any page.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public share link, affects only the visitor's own cart.
            if ( ! isset( $_REQUEST['share'] ) || ! is_string( $_REQUEST['share'] ) ) {
                return;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $hash = sanitize_file_name( wp_unslash( $_REQUEST['share'] ) );
            $cart = self::scurl_read_shared_cart( $hash );

            if ( null === $cart ) {
                return;
            }

            $previous_cart = self::scurl_get_previous_cart();
            $shared_items  = isset( $cart['cart'] ) && is_array( $cart['cart'] ) ? $cart['cart'] : array();

            // There is only a choice to make when the visitor has a cart of
            // their own that differs from the shared one.
            if ( self::scurl_customers_can_choose() && ! empty( $previous_cart ) && self::scurl_cart_signature( $previous_cart ) !== self::scurl_cart_signature( $shared_items ) ) {
                WC()->session->set( self::PENDING_SESSION_KEY, $hash );
                self::$reload_needed = true;
                return;
            }

            self::scurl_replace_cart( $cart, $previous_cart );
        }

        /**
         * Apply the visitor's answer from the replace or add popup.
         *
         * The popup posts the share hash with a nonce tied to the visitor's
         * session. It is a form rather than links, so nothing a browser
         * prefetches can change the cart.
         *
         * @return bool Whether this request was an answer from the popup.
         */
        private static function scurl_handle_choice() {
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified below, once the hash it is tied to is known.
            if ( ! isset( $_POST['scurl_share_choice'], $_POST['scurl_share'], $_POST['scurl_choice_nonce'] )
                || ! is_string( $_POST['scurl_share_choice'] ) || ! is_string( $_POST['scurl_share'] ) || ! is_string( $_POST['scurl_choice_nonce'] ) ) {
                return false;
            }

            $choice = sanitize_key( wp_unslash( $_POST['scurl_share_choice'] ) );
            $hash   = sanitize_key( wp_unslash( $_POST['scurl_share'] ) );
            $nonce  = sanitize_key( wp_unslash( $_POST['scurl_choice_nonce'] ) );
            // phpcs:enable WordPress.Security.NonceVerification.Missing

            // Reload whatever the answer, so a refresh does not post it again.
            self::$reload_needed    = true;
            self::$choice_submitted = true;

            if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION . '-choice-' . $hash ) || ! self::scurl_customers_can_choose() ) {
                return true;
            }

            // One answer per question asked.
            if ( WC()->session->get( self::ASKED_SESSION_KEY ) !== $hash ) {
                return true;
            }

            WC()->session->set( self::ASKED_SESSION_KEY, null );

            // "Keep my current cart", or anything unexpected, leaves it alone.
            if ( 'replace' !== $choice && 'add' !== $choice ) {
                return true;
            }

            $cart = self::scurl_read_shared_cart( $hash );

            if ( null === $cart ) {
                return true;
            }

            if ( 'add' === $choice ) {
                self::scurl_merge_cart( $cart, self::scurl_get_previous_cart() );
            } else {
                self::scurl_replace_cart( $cart, self::scurl_get_previous_cart() );
            }

            return true;
        }

        /**
         * Read a stored shared cart.
         *
         * @param string $hash Share hash from the link.
         * @return array|null The stored session data, or null when the hash is
         *                    malformed or the file is missing or unreadable.
         */
        private static function scurl_read_shared_cart( $hash ) {
            // Stored hashes are wp_hash() output: 32 hexadecimal characters.
            if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
                return null;
            }

            $file = get_temp_dir() . $hash;

            if ( ! file_exists( $file ) ) {
                return null;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
            $contents = @file_get_contents( $file );
            if ( false === $contents || '' === $contents ) {
                return null;
            }

            // allowed_classes => false prevents PHP object injection from a
            // crafted file, which matters on hosts with a shared temp directory.
            $cart = unserialize( $contents, array( 'allowed_classes' => false ) );

            return is_array( $cart ) ? $cart : null;
        }

        /**
         * The visitor's cart items as they stand before a shared cart is
         * applied.
         *
         * A logged in customer starting a new session has no session cart
         * yet. WooCommerce would load their saved cart next, so that is the
         * cart the shared one replaces or is added to. Adding to the session
         * also keeps the saved items, which would otherwise be lost.
         *
         * @return array Cart items, keyed by cart item key.
         */
        private static function scurl_get_previous_cart() {
            $previous_cart = WC()->session->get( 'cart' );

            if ( empty( $previous_cart ) && is_user_logged_in() && apply_filters( 'woocommerce_persistent_cart_enabled', true ) ) {
                $saved_cart    = get_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
                $previous_cart = isset( $saved_cart['cart'] ) ? $saved_cart['cart'] : array();
            }

            return is_array( $previous_cart ) ? $previous_cart : array();
        }

        /**
         * Make the visitor's cart exactly the shared cart, coupons included.
         *
         * @param array $cart          Stored shared cart session data.
         * @param array $previous_cart Visitor's cart items before the change.
         */
        private static function scurl_replace_cart( $cart, $previous_cart ) {
            foreach ( self::$session_cart_keys as $key ) {
                if ( array_key_exists( $key, $cart ) ) {
                    WC()->session->set( $key, $cart[ $key ] );
                }
            }

            $shared_items = isset( $cart['cart'] ) && is_array( $cart['cart'] ) ? $cart['cart'] : array();
            $replaced     = ! empty( $previous_cart ) && self::scurl_cart_signature( $previous_cart ) !== self::scurl_cart_signature( $shared_items );

            self::$shared_cart_result  = $replaced ? 'replaced' : 'loaded';
            self::$shared_cart_applied = true;
            self::$reload_needed       = true;
        }

        /**
         * Add the shared cart to the visitor's cart.
         *
         * The visitor keeps their own coupons. The shared ones are applied
         * through WooCommerce once the cart has loaded.
         *
         * @param array $cart          Stored shared cart session data.
         * @param array $previous_cart Visitor's cart items before the change.
         */
        private static function scurl_merge_cart( $cart, $previous_cart ) {
            // Adding to an empty cart is the same as replacing it, and keeps the
            // shared cart exactly as it was, coupons included.
            if ( empty( $previous_cart ) ) {
                self::scurl_replace_cart( $cart, $previous_cart );
                return;
            }

            $shared_items = isset( $cart['cart'] ) && is_array( $cart['cart'] ) ? $cart['cart'] : array();
            $merged       = self::scurl_merge_cart_items( $previous_cart, $shared_items, self::scurl_merge_combines() );

            WC()->session->set( 'cart', $merged );

            // Without stored totals WooCommerce recalculates them once the cart
            // has loaded, which is after the shared coupons are applied.
            WC()->session->set( 'cart_totals', null );

            $current_coupons       = array_map( 'wc_format_coupon_code', (array) WC()->session->get( 'applied_coupons', array() ) );
            $shared_coupons        = isset( $cart['applied_coupons'] ) ? array_map( 'wc_format_coupon_code', (array) $cart['applied_coupons'] ) : array();
            self::$pending_coupons = array_values( array_diff( $shared_coupons, $current_coupons ) );

            self::$shared_cart_result  = self::scurl_cart_signature( $merged ) === self::scurl_cart_signature( $previous_cart ) ? 'unchanged' : 'merged';
            self::$shared_cart_applied = true;
            self::$reload_needed       = true;
        }

        /**
         * Add the shared items to the visitor's cart.
         *
         * Items only in the shared cart are copied as they are, keeping any
         * add-on data, as replacing does. An item in both carts, matched by its
         * cart item key, keeps the visitor's line with either the larger of the
         * two quantities or both added together.
         *
         * WooCommerce does not correct quantities when it loads a cart, it only
         * blocks checkout with an error, so a raised quantity is capped at what
         * the product allows: 1 when sold individually, and the stock level
         * when stock is managed without backorders. It never drops below what
         * the visitor already had.
         *
         * @param array $current Visitor's cart items, keyed by cart item key.
         * @param array $shared  Shared cart items, keyed by cart item key.
         * @param bool  $combine Add quantities together rather than keep the larger.
         * @return array
         */
        private static function scurl_merge_cart_items( $current, $shared, $combine ) {
            $merged = is_array( $current ) ? $current : array();

            foreach ( $shared as $key => $item ) {
                if ( ! is_array( $item ) || empty( $item['product_id'] ) || empty( $item['quantity'] ) ) {
                    continue;
                }

                if ( ! isset( $merged[ $key ] ) ) {
                    $merged[ $key ] = $item;
                    continue;
                }

                $have     = isset( $merged[ $key ]['quantity'] ) ? (float) $merged[ $key ]['quantity'] : 0;
                $quantity = $combine ? $have + (float) $item['quantity'] : max( $have, (float) $item['quantity'] );

                if ( $quantity > $have ) {
                    $product = wc_get_product( ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'] );
                    $max     = $product ? (float) $product->get_max_purchase_quantity() : -1;

                    if ( $max > 0 ) {
                        $quantity = max( $have, min( $quantity, $max ) );
                    }
                }

                $merged[ $key ]['quantity'] = wc_stock_amount( $quantity );
            }

            return $merged;
        }

        /**
         * Finish opening a shared cart once WooCommerce has loaded it: apply the
         * shared coupons after a merge, then queue the notice.
         *
         * @param WC_Cart $cart Loaded cart.
         */
        public static function scurl_finish_shared_cart( $cart = null ) {
            if ( ! self::$shared_cart_applied || self::$shared_cart_finished ) {
                return;
            }

            self::$shared_cart_finished = true;

            $failed_coupons = 0;

            if ( ! empty( self::$pending_coupons ) ) {
                $cart = $cart instanceof WC_Cart ? $cart : WC()->cart;

                list( $applied_coupons, $failed_coupons ) = self::scurl_apply_shared_coupons( $cart, self::$pending_coupons );

                self::$pending_coupons = array();

                if ( $applied_coupons > 0 && 'unchanged' === self::$shared_cart_result ) {
                    self::$shared_cart_result = 'merged';
                }
            }

            self::scurl_add_shared_cart_notice( self::$shared_cart_result, $failed_coupons );
        }

        /**
         * Apply the shared cart's coupons on top of the visitor's own.
         *
         * Done through WooCommerce, so validity, usage limits and individual
         * use rules all apply. Copying the codes straight into the session
         * would let an individual use coupon stack with others.
         *
         * A shared individual use coupon is skipped when the visitor already
         * has a coupon, because applying it would remove theirs.
         *
         * WooCommerce's own messages from these calls are dropped. With a
         * classic theme the Cart block never prints them, so they would turn
         * up later on another page. The shared cart notice reports failures.
         *
         * @param WC_Cart $cart  Loaded cart.
         * @param array   $codes Coupon codes the visitor does not have yet.
         * @return array Number of coupons applied, and number that failed.
         */
        private static function scurl_apply_shared_coupons( $cart, $codes ) {
            $applied = 0;
            $failed  = 0;
            $notices = function_exists( 'wc_get_notices' ) ? wc_get_notices() : null;

            foreach ( $codes as $code ) {
                $coupon = new WC_Coupon( $code );

                if ( $coupon->get_individual_use() && ! empty( $cart->get_applied_coupons() ) ) {
                    $failed++;
                    continue;
                }

                if ( $cart->apply_coupon( $code ) ) {
                    $applied++;
                } else {
                    $failed++;
                }
            }

            if ( is_array( $notices ) ) {
                wc_set_notices( $notices );
            }

            return array( $applied, $failed );
        }

        /**
         * Reduce cart items to what makes two carts the same for the customer:
         * which items, and how many of each.
         *
         * Cart item keys already encode the product, variation and item data.
         * Totals are left out, since the same items can be priced or taxed
         * differently between sessions.
         *
         * @param array $items Session cart items, keyed by cart item key.
         * @return string
         */
        private static function scurl_cart_signature( $items ) {
            $lines = array();

            foreach ( (array) $items as $key => $item ) {
                $lines[] = $key . ':' . ( isset( $item['quantity'] ) ? (float) $item['quantity'] : 0 );
            }

            sort( $lines );

            return implode( '|', $lines );
        }

        /**
         * Tell the visitor what opening the shared cart did to their cart,
         * since replacing or merging happens silently.
         *
         * The text is kept in the session, so it survives the redirect that
         * strips the share parameter, and is printed on the next page view.
         *
         * WooCommerce's own notice queue is not used. With a classic theme the
         * Cart block never prints that queue, so the notice would surface later
         * on some unrelated page instead.
         *
         * @param string $result         'loaded', 'replaced', 'merged' or 'unchanged'.
         * @param int    $failed_coupons Shared coupons that could not be applied.
         */
        private static function scurl_add_shared_cart_notice( $result, $failed_coupons = 0 ) {
            if ( 'yes' !== get_option( 'scurl_shared_cart_notice', 'yes' ) ) {
                return;
            }

            switch ( $result ) {
                case 'replaced':
                    $notice = __( 'The shared cart has been loaded and has replaced the items that were in your cart.', 'share-cart-for-woocommerce' );
                    break;
                case 'merged':
                    $notice = __( 'The shared cart has been added to your cart.', 'share-cart-for-woocommerce' );
                    break;
                case 'unchanged':
                    $notice = __( 'Your cart already has everything from the shared cart.', 'share-cart-for-woocommerce' );
                    break;
                default:
                    $notice = __( 'The shared cart has been loaded.', 'share-cart-for-woocommerce' );
            }

            if ( $failed_coupons > 0 ) {
                $notice .= ' ' . sprintf(
                    /* translators: %d: number of coupons */
                    _n( '%d coupon from the shared cart could not be applied.', '%d coupons from the shared cart could not be applied.', $failed_coupons, 'share-cart-for-woocommerce' ),
                    $failed_coupons
                );
            }

            /**
             * Filter the notice shown when a shared cart is opened.
             *
             * @param string $notice   Notice text.
             * @param bool   $replaced Whether the shared cart replaced other items.
             * @param string $result   What happened: 'loaded', 'replaced', 'merged' or 'unchanged'.
             */
            $notice = (string) apply_filters( 'scurl_shared_cart_notice_text', $notice, 'replaced' === $result, $result );

            if ( '' !== $notice ) {
                WC()->session->set( self::NOTICE_SESSION_KEY, $notice );
            }
        }

        /**
         * Move the shared cart notice out of the session on the page view that
         * follows the share link.
         *
         * Taken once, whether or not the page is a cart, so it can never show
         * up later somewhere unrelated. Runs after the share redirect, which
         * exits, so the request that loads the cart never takes it.
         */
        public static function scurl_take_shared_cart_notice() {
            if ( ! function_exists( 'WC' ) || ! WC()->session ) {
                return;
            }

            $notice = WC()->session->get( self::NOTICE_SESSION_KEY );

            if ( empty( $notice ) ) {
                return;
            }

            self::$pending_notice = (string) $notice;
            WC()->session->set( self::NOTICE_SESSION_KEY, null );
        }

        /**
         * Build the shared cart notice markup, once per page.
         *
         * Uses WooCommerce's notice template, so it matches the store's other
         * notices, including the block styled ones in block themes.
         *
         * @return string
         */
        private static function scurl_get_shared_cart_notice_html() {
            if ( '' === self::$pending_notice || self::$notice_printed || ! function_exists( 'wc_print_notice' ) ) {
                return '';
            }

            self::$notice_printed = true;

            // Buffered rather than using wc_print_notice()'s return flag, which
            // only exists from WooCommerce 7.7.
            ob_start();
            wc_print_notice( esc_html( self::$pending_notice ), 'notice' );

            return '<div class="woocommerce scurl-shared-cart-notice">' . ob_get_clean() . '</div>';
        }

        /**
         * Print the shared cart notice above the classic cart.
         */
        public static function scurl_print_shared_cart_notice() {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in scurl_get_shared_cart_notice_html().
            echo self::scurl_get_shared_cart_notice_html();
        }

        /**
         * Print the shared cart notice above the Cart block, which fires none
         * of the classic cart hooks.
         *
         * @param string $block_content Rendered block HTML.
         * @param array  $block         Parsed block.
         * @return string
         */
        public static function scurl_print_notice_before_cart_block( $block_content, $block ) {
            if ( '' === self::$pending_notice || empty( $block['blockName'] ) || 'woocommerce/cart' !== $block['blockName'] ) {
                return $block_content;
            }

            return self::scurl_get_shared_cart_notice_html() . $block_content;
        }

        /**
         * Reload the page without the share parameter once the shared cart is
         * in the session, or its replace or add question is waiting there.
         *
         * Left in the address bar, the parameter restored the shared cart again
         * on every refresh. WooCommerce's remove item and update cart actions
         * also redirect back to the referring URL, so the recipient's edits
         * were undone straight away.
         *
         * The session and cart cookies are saved on shutdown, which still runs
         * after the redirect, as it does for WooCommerce's own add to cart
         * redirect.
         */
        public static function scurl_redirect_after_shared_cart() {
            if ( ! self::$reload_needed ) {
                return;
            }

            $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

            // Never redirect another form submission, it would lose the posted
            // data. The popup's own form is reloaded as a GET, so a refresh
            // does not post the answer again.
            if ( 'GET' !== $method && 'HEAD' !== $method && ! self::$choice_submitted ) {
                return;
            }

            // add-to-cart is dropped too, as WooCommerce's own redirect does, so
            // a link that also adds a product does not add it a second time.
            $location = remove_query_arg( array( 'share', 'add-to-cart' ) );

            wp_safe_redirect( wp_validate_redirect( $location, wc_get_cart_url() ), self::$choice_submitted ? 303 : 302 );
            exit;
        }

        /**
         * Take a waiting replace or add question out of the session on the page
         * view that follows the share link, and prepare the popup.
         *
         * Taken once. Closing the popup, reloading or moving to another page
         * leaves the visitor's cart as it is. Opening the link again asks again.
         */
        public static function scurl_take_pending_choice() {
            if ( ! function_exists( 'WC' ) || ! WC()->session ) {
                return;
            }

            $hash = WC()->session->get( self::PENDING_SESSION_KEY );

            if ( empty( $hash ) ) {
                return;
            }

            WC()->session->set( self::PENDING_SESSION_KEY, null );

            $cart = self::scurl_read_shared_cart( (string) $hash );

            if ( null === $cart || ! self::scurl_customers_can_choose() ) {
                return;
            }

            WC()->session->set( self::ASKED_SESSION_KEY, (string) $hash );

            self::$choice_popup = array(
                'hash'  => (string) $hash,
                'items' => self::scurl_describe_items( isset( $cart['cart'] ) ? $cart['cart'] : array() ),
            );
        }

        /**
         * Product names and quantities of shared cart items, for the popup.
         *
         * @param array $items Shared cart items.
         * @return array List of arrays with 'name' and 'quantity'.
         */
        private static function scurl_describe_items( $items ) {
            $lines = array();

            foreach ( (array) $items as $item ) {
                if ( ! is_array( $item ) || empty( $item['product_id'] ) ) {
                    continue;
                }

                $product = wc_get_product( ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'] );

                // A product deleted since the cart was shared is left out here,
                // as WooCommerce leaves it out of the cart.
                if ( ! $product ) {
                    continue;
                }

                $lines[] = array(
                    'name'     => $product->get_name(),
                    'quantity' => isset( $item['quantity'] ) ? wc_stock_amount( $item['quantity'] ) : 1,
                );
            }

            return $lines;
        }

        /**
         * Print the replace or add popup in the footer.
         *
         * Printed visible, so it works without JavaScript: every button submits
         * the form. With JavaScript, closing it or choosing to keep the cart
         * just hides it, since the question has already left the session.
         */
        public static function scurl_render_choice_modal() {
            if ( empty( self::$choice_popup ) ) {
                return;
            }

            $hash  = self::$choice_popup['hash'];
            $items = self::$choice_popup['items'];
            $shown = array_slice( $items, 0, 10 );
            $more  = count( $items ) - count( $shown );
            ?>
            <div class="scurl-email-modal scurl-choice-modal" id="scurl-choice-modal">
                <div class="scurl-email-backdrop" data-scurl-close></div>
                <div class="scurl-email-dialog" role="dialog" aria-modal="true" aria-labelledby="scurl-choice-title" aria-describedby="scurl-choice-question">
                    <button type="button" class="scurl-email-close" data-scurl-close aria-label="<?php esc_attr_e( 'Close', 'share-cart-for-woocommerce' ); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                    <h2 class="scurl-email-title" id="scurl-choice-title"><?php esc_html_e( 'Open shared cart', 'share-cart-for-woocommerce' ); ?></h2>
                    <?php if ( ! empty( $shown ) ) : ?>
                        <p class="scurl-email-intro"><?php esc_html_e( 'This link contains:', 'share-cart-for-woocommerce' ); ?></p>
                        <ul class="scurl-choice-items">
                            <?php foreach ( $shown as $line ) : ?>
                                <li><?php echo esc_html( $line['name'] ); ?> &times; <?php echo esc_html( $line['quantity'] ); ?></li>
                            <?php endforeach; ?>
                            <?php if ( $more > 0 ) : ?>
                                <li>
                                    <?php
                                    /* translators: %d: number of further items in the shared cart */
                                    echo esc_html( sprintf( _n( 'and %d more item', 'and %d more items', $more, 'share-cart-for-woocommerce' ), $more ) );
                                    ?>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="scurl-email-intro" id="scurl-choice-question"><?php esc_html_e( 'You already have items in your cart. What would you like to do?', 'share-cart-for-woocommerce' ); ?></p>
                    <form class="scurl-choice-form" method="post" action="<?php echo esc_url( remove_query_arg( array( 'share', 'add-to-cart' ) ) ); ?>">
                        <input type="hidden" name="scurl_share" value="<?php echo esc_attr( $hash ); ?>" />
                        <input type="hidden" name="scurl_choice_nonce" value="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION . '-choice-' . $hash ) ); ?>" />
                        <p class="scurl-choice-actions">
                            <button type="submit" class="button scurl-choice-btn" name="scurl_share_choice" value="replace"><?php esc_html_e( 'Replace my cart', 'share-cart-for-woocommerce' ); ?></button>
                            <button type="submit" class="button scurl-choice-btn" name="scurl_share_choice" value="add"><?php esc_html_e( 'Add to my cart', 'share-cart-for-woocommerce' ); ?></button>
                        </p>
                        <p class="scurl-choice-keep-row">
                            <button type="submit" class="scurl-choice-keep" name="scurl_share_choice" value="keep" data-scurl-close><?php esc_html_e( 'Keep my current cart', 'share-cart-for-woocommerce' ); ?></button>
                        </p>
                    </form>
                </div>
            </div>
            <?php
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
            check_ajax_referer( self::NONCE_ACTION, 'nonce' );

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
            // Always on the cart page. A cart that loads empty has no widget,
            // but restoring a removed item brings the widget back over AJAX,
            // and its email button needs the popup to be there.
            if ( ! self::$email_modal_needed && ! ( function_exists( 'is_cart' ) && is_cart() ) ) {
                return;
            }

            // Logged in customers can switch between their account email and
            // another address. Guests just get an empty field.
            $account_email = '';
            if ( is_user_logged_in() ) {
                $account_email = wp_get_current_user()->user_email;
            }

            $can_email = self::scurl_can_email_cart();

            // Guests who may not send are pointed at the My Account page, where
            // WooCommerce shows its login and, when enabled, registration forms.
            if ( ! $can_email ) {
                $has_account_page = wc_get_page_id( 'myaccount' ) > 0;
                $account_url      = $has_account_page ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
                $can_register     = $has_account_page && 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' );
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
                    <?php if ( ! $can_email ) : ?>
                        <p class="scurl-email-intro">
                            <?php
                            if ( $can_register ) {
                                esc_html_e( 'Please log in or create an account to email this cart.', 'share-cart-for-woocommerce' );
                            } else {
                                esc_html_e( 'Please log in to email this cart.', 'share-cart-for-woocommerce' );
                            }
                            ?>
                        </p>
                        <p class="scurl-email-submit-row">
                            <a class="button scurl-email-login" href="<?php echo esc_url( $account_url ); ?>">
                                <?php
                                if ( $can_register ) {
                                    esc_html_e( 'Log in or register', 'share-cart-for-woocommerce' );
                                } else {
                                    esc_html_e( 'Log in', 'share-cart-for-woocommerce' );
                                }
                                ?>
                            </a>
                        </p>
                    <?php else : ?>
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
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        /**
         * Count an email send against the sender's hourly allowance, and the
         * store's when a store wide cap is set.
         *
         * Logged in customers are counted by account, which stays accurate
         * behind a CDN or load balancer, where many visitors can share an IP.
         * Guests, when allowed, can only be counted by IP address.
         *
         * The store wide cap is off by default. On a busy store a fixed total
         * would start turning away genuine customers, and with logins required
         * every abuser needs an account of their own anyway.
         *
         * Counts are kept per clock hour. A single transient refreshed on every
         * send would never expire while sends kept coming.
         *
         * Nothing here runs on page views, only when Send is pressed, so the
         * transient writes stay negligible even on a high traffic store. With a
         * persistent object cache they never touch the database.
         *
         * @return bool True when an allowance is used up.
         */
        private static function scurl_email_rate_limited() {
            if ( is_user_logged_in() ) {
                $sender = 'u' . get_current_user_id();
            } else {
                $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

                /**
                 * Filter the guest IP address used for the per sender email cap.
                 *
                 * Only used when guests may email carts. Behind a proxy or CDN,
                 * REMOTE_ADDR is the proxy's address, so guests share one
                 * allowance. Return the real visitor IP from the header your
                 * proxy sets, and only when the site is actually behind that
                 * proxy, since the header can be forged otherwise.
                 *
                 * @param string $ip REMOTE_ADDR.
                 */
                $ip = (string) apply_filters( 'scurl_email_client_ip', $ip );

                // An IPv6 visitor usually holds a whole /64, so count the network
                // rather than the address, or rotating addresses resets the cap.
                // IPv4 mapped addresses (::ffff:a.b.c.d) are left whole.
                if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
                    $packed = inet_pton( $ip );

                    if ( false !== $packed && 0 !== strpos( $packed, str_repeat( "\0", 10 ) . "\xff\xff" ) ) {
                        $ip = bin2hex( substr( $packed, 0, 8 ) ) . '/64';
                    }
                }

                // A keyed hash, so the stored name cannot be turned back into the
                // address the way a plain md5 of an IPv4 address can.
                $sender = wp_hash( $ip );
            }

            $hour       = gmdate( 'YmdH' );
            $sender_key = 'scurl_email_' . $hour . '_' . $sender;
            $store_key  = 'scurl_email_' . $hour . '_store';

            /**
             * Filter how many cart emails one customer, or one guest IP
             * address, may send per hour.
             *
             * @param int $limit Default 5. 0 removes the cap.
             */
            $limit = (int) apply_filters( 'scurl_email_rate_limit', 5 );

            /**
             * Filter how many cart emails the whole store may send per hour.
             *
             * @param int $limit Default 0, no store wide cap.
             */
            $store_limit = (int) apply_filters( 'scurl_email_store_rate_limit', 0 );

            $count       = (int) get_transient( $sender_key );
            $store_count = $store_limit > 0 ? (int) get_transient( $store_key ) : 0;

            if ( ( $limit > 0 && $count >= $limit ) || ( $store_limit > 0 && $store_count >= $store_limit ) ) {
                return true;
            }

            set_transient( $sender_key, $count + 1, HOUR_IN_SECONDS );

            if ( $store_limit > 0 ) {
                set_transient( $store_key, $store_count + 1, HOUR_IN_SECONDS );
            }

            return false;
        }

        /**
         * Whether the visitor's note contains a link or anything a mail client
         * would turn into one.
         *
         * The note is sent from the store's own address, so a link in it would
         * let anyone send a phishing message that appears to come from the
         * store. Mail clients link bare domains and email addresses as well as
         * full URLs, so those are refused too.
         *
         * @param string $message Sanitized note.
         * @return bool
         */
        private static function scurl_message_has_link( $message ) {
            // Invisible characters keep a link readable while splitting it so
            // the patterns miss it, so they are removed before matching.
            // Ideographic and full width full stops are deliberately not read
            // as dots: they end ordinary sentences in Chinese and Japanese.
            $text = preg_replace( '/[\x{00AD}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $message );

            // A failed match means the text could not be read, for example
            // invalid UTF-8 on a site with another charset. Refuse it rather
            // than let it through unchecked.
            if ( null === $text ) {
                return true;
            }

            $patterns = array(
                // Any scheme: http://, https://, ftp:// and so on.
                '~\b[a-z][a-z0-9+.\-]*://~i',
                // Bare IPv4 address.
                '~\b\d{1,3}(?:\.\d{1,3}){3}\b~',
                // Domain names, including www., IDNs and those inside an email
                // address. \p{M} keeps scripts written with combining marks,
                // such as Devanagari, inside the match.
                '~[\p{L}\p{M}\p{N}\-]+(?:\.[\p{L}\p{M}\p{N}\-]+)*\.\p{L}[\p{L}\p{M}]+~iu',
            );

            foreach ( $patterns as $pattern ) {
                // 1 is a match, false is an error. Both refuse the note.
                if ( 0 !== preg_match( $pattern, $text ) ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * AJAX endpoint that emails the current cart link.
         *
         * The link is built here from the visitor's own session, never taken
         * from the request, so the form cannot be used to send arbitrary links.
         */
        public static function scurl_ajax_email_cart() {
            check_ajax_referer( self::NONCE_ACTION, 'nonce' );

            // The guest hook is not registered unless guests are allowed, so
            // this only matters if that changes mid request. Checked anyway.
            if ( ! self::scurl_can_email_cart() ) {
                wp_send_json_error( array( 'message' => __( 'Please log in to email this cart.', 'share-cart-for-woocommerce' ) ) );
            }

            $email = isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

            if ( '' === $email || ! is_email( $email ) ) {
                wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'share-cart-for-woocommerce' ) ) );
            }

            $message = isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
            $message = trim( mb_substr( $message, 0, self::EMAIL_MESSAGE_MAX_LENGTH ) );

            if ( '' !== $message && self::scurl_message_has_link( $message ) ) {
                wp_send_json_error( array( 'message' => __( 'Please remove links and web addresses from the message.', 'share-cart-for-woocommerce' ) ) );
            }

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

            $body = '<p>' . esc_html(
                /* translators: %s: site name */
                sprintf( __( 'Here is the link to your cart at %s. Open it to restore your items and continue shopping.', 'share-cart-for-woocommerce' ), $site_name )
            ) . '</p>';
            $body .= '<p><a href="' . esc_url( $share_url ) . '">' . esc_html__( 'View your cart', 'share-cart-for-woocommerce' ) . '</a></p>';
            $body .= '<p style="word-break:break-all;font-size:12px;">' . esc_html( $share_url ) . '</p>';

            // The note comes after the store's own text and is labelled as the
            // sender's, so it cannot pass for a message from the store.
            if ( '' !== $message ) {
                $body .= '<p style="margin-bottom:4px;"><strong>' . esc_html__( 'Note from the sender:', 'share-cart-for-woocommerce' ) . '</strong></p>';
                $body .= '<div style="border-left:3px solid #dcdcde;padding-left:12px;margin:0 0 16px;">' . nl2br( esc_html( $message ) ) . '</div>';
            }

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

            // Names the address rather than saying "check your inbox", since the
            // cart may have gone to someone else.
            /* translators: %s: recipient email address */
            wp_send_json_success( array( 'message' => sprintf( __( 'Email sent to %s.', 'share-cart-for-woocommerce' ), $email ) ) );
        }

        public static function scurl_scripts_and_styles(){
            wp_enqueue_script('scurl-script', SCURL_PLUGIN_PATH . 'assets/js/scurl.js', array('jquery'), SCURL_VERSION, true);
            wp_localize_script('scurl-script', 'share_cart_ajax', array(
                'ajax_url'    => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
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
