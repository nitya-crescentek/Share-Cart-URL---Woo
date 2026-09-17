<?php
if ( ! defined( 'ABSPATH' ) ) {
    die( esc_html__( "No direct access!", 'share-cart-for-woocommerce' ) );
}

if ( ! class_exists( 'SCURL_Settings' ) ) {

    class SCURL_Settings {

        public function __construct() {
            // Only initialize in the admin area when WooCommerce is active.
            if ( is_admin() && in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
                add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
                add_action( 'woocommerce_settings_tabs_share_cart_url', array( $this, 'settings_tab_content' ) );
                add_action( 'woocommerce_update_options_share_cart_url', array( $this, 'update_settings' ) );
            }
        }

        /**
         * Add a new settings tab to WooCommerce.
         *
         * @param array $settings_tabs
         * @return array
         */
        public function add_settings_tab( $settings_tabs ) {
            $settings_tabs['share_cart_url'] = esc_html__( 'Share Cart', 'share-cart-for-woocommerce' );
            return $settings_tabs;
        }

        /**
         * Render the settings tab content.
         */
        public function settings_tab_content() {
            woocommerce_admin_fields( $this->get_settings() );
        }

        /**
         * Save settings for the Share Cart tab.
         */
        public function update_settings() {
            woocommerce_update_options( $this->get_settings() );
        }

        /**
         * Define the settings fields.
         *
         * @return array
         */
        public function get_settings() {
            $settings = array(
                'section_title' => array(
                    'name' => esc_html__( 'Share Cart Button Settings', 'share-cart-for-woocommerce' ),
                    'type' => 'title',
                    'desc' => esc_html__( 'Configure the position of the share cart button on the cart page.', 'share-cart-for-woocommerce' ),
                    'id'   => 'scurl_settings_section_title'
                ),
                'button_position' => array(
                    'name'    => esc_html__( 'Button Position', 'share-cart-for-woocommerce' ),
                    'type'    => 'select',
                    'options' => array(
                        'woocommerce_before_cart_table'            => esc_html__( 'Before Cart Table', 'share-cart-for-woocommerce' ),
                        'woocommerce_before_cart_contents'           => esc_html__( 'Before Cart Contents', 'share-cart-for-woocommerce' ),
                        'woocommerce_after_cart'                     => esc_html__( 'After Cart', 'share-cart-for-woocommerce' ),
                        'woocommerce_before_cart_totals'             => esc_html__( 'Before Cart Totals', 'share-cart-for-woocommerce' ),
                        'woocommerce_after_cart_totals'              => esc_html__( 'After Cart Totals', 'share-cart-for-woocommerce' ),
                        'woocommerce_proceed_to_checkout'            => esc_html__( 'Proceed to Checkout', 'share-cart-for-woocommerce' ),
                        'woocommerce_cart_totals_after_order_total'  => esc_html__( 'Cart Totals After Order Total', 'share-cart-for-woocommerce' ),
                        'woocommerce_cart_totals_before_order_total' => esc_html__( 'Cart Totals Before Order Total', 'share-cart-for-woocommerce' ),
                        'woocommerce_cart_totals_after_shipping'     => esc_html__( 'Cart Totals After Shipping', 'share-cart-for-woocommerce' ),
                        'woocommerce_cart_coupon'                    => esc_html__( 'Cart Coupon', 'share-cart-for-woocommerce' ),
                        'hide'                    => esc_html__( 'Hide', 'share-cart-for-woocommerce' ),
                    ),
                    'desc'    => esc_html__( 'Select the hook position where the share cart button will appear on the cart page.', 'share-cart-for-woocommerce' ),
                    'id'      => 'scurl_button_position'
                ),
                'shortcode_info' => array(
                    'name' => esc_html__( 'Use Shortcode', 'share-cart-for-woocommerce' ),
                    'type' => 'title',
                    'desc' => sprintf(
                        /* translators: %s is the shortcode wrapped in <code> */
                        esc_html__( 'You can also use the shortcode %s to display the share cart button anywhere on your site.', 'share-cart-for-woocommerce' ),
                        '<code>[share_cart_url]</code>'
                    ),
                    'id'   => 'scurl_shortcode_info'
                ),
                'section_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'scurl_settings_section_end'
                )
            );
            return $settings;
        }
    }

    new SCURL_Settings();
}
