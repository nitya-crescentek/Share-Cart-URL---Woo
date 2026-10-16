<?php
if ( ! defined( 'ABSPATH' ) ) {
    die( esc_html__( "No direct access!", 'share-cart-for-woocommerce' ) );
}

if ( ! class_exists( 'SCURL_Settings' ) ) {

    class SCURL_Settings {

        public function __construct() {
            // Only initialize in the admin area when WooCommerce is active.
            if ( is_admin() && scurl_is_woocommerce_active() ) {
                add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
                add_action( 'woocommerce_sections_share_cart_url', array( $this, 'output_sections' ) );
                add_action( 'woocommerce_settings_tabs_share_cart_url', array( $this, 'settings_tab_content' ) );
                add_action( 'woocommerce_update_options_share_cart_url', array( $this, 'update_settings' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
            }
        }

        /**
         * Enqueue styles for the Share Cart settings tab.
         *
         * @param string $hook_suffix Current admin page hook suffix.
         */
        public function enqueue_admin_styles( $hook_suffix ) {
            if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
                return;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only check of which settings tab is on screen.
            if ( ! isset( $_GET['tab'] ) || 'share_cart_url' !== sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
                return;
            }

            wp_enqueue_style(
                'scurl-admin-style',
                SCURL_PLUGIN_PATH . 'assets/css/scurl-admin.css',
                array(),
                SCURL_VERSION
            );
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
         * Sub pages of the Share Cart tab, shown as links under the tabs the way
         * WooCommerce's own tabs do. The first one has an empty key, so it is
         * the page the tab opens on.
         *
         * @return array Section key => label.
         */
        public function get_sections() {
            return array(
                ''         => esc_html__( 'Buttons', 'share-cart-for-woocommerce' ),
                'advanced' => esc_html__( 'Advanced', 'share-cart-for-woocommerce' ),
                'messages' => esc_html__( 'Messages', 'share-cart-for-woocommerce' ),
            );
        }

        /**
         * The sub page on screen, read from the section parameter WooCommerce
         * puts in $current_section. Unknown values fall back to the first page.
         *
         * @return string
         */
        public function get_current_section() {
            global $current_section;

            $section = is_string( $current_section ) ? $current_section : '';

            return array_key_exists( $section, $this->get_sections() ) ? $section : '';
        }

        /**
         * Print the sub page links, using WooCommerce's own markup so they look
         * the same as on its tabs.
         */
        public function output_sections() {
            $sections = $this->get_sections();
            $current  = $this->get_current_section();
            $last     = array_key_last( $sections );

            echo '<ul class="subsubsub">';

            foreach ( $sections as $id => $label ) {
                $url = admin_url( 'admin.php?page=wc-settings&tab=share_cart_url&section=' . sanitize_title( $id ) );

                printf(
                    '<li><a href="%1$s" class="%2$s">%3$s</a> %4$s </li>',
                    esc_url( $url ),
                    $current === $id ? 'current' : '',
                    esc_html( $label ),
                    $last === $id ? '' : '|'
                );
            }

            echo '</ul><br class="clear" />';
        }

        /**
         * Render the settings tab content.
         */
        public function settings_tab_content() {
            woocommerce_admin_fields( $this->get_settings() );
        }

        /**
         * Save settings for the Share Cart tab.
         *
         * Only the fields of the sub page being saved are passed, so saving one
         * page never resets a checkbox on another.
         */
        public function update_settings() {
            woocommerce_update_options( $this->get_settings() );
        }

        /**
         * Define the settings fields of one sub page.
         *
         * Each 'title' must be closed by its own 'sectionend'. Without that, the
         * second heading is emitted inside the still open table and the browser
         * moves it above the fields.
         *
         * @param string|null $section Sub page key. Defaults to the one on screen.
         * @return array
         */
        public function get_settings( $section = null ) {
            if ( null === $section ) {
                $section = $this->get_current_section();
            }

            if ( 'advanced' === $section ) {
                return array(
                    'shared_cart_title' => array(
                        'name' => esc_html__( 'Opening a Shared Cart', 'share-cart-for-woocommerce' ),
                        'type' => 'title',
                        'desc' => esc_html__( 'What happens to a customer\'s own cart when they open a shared cart link.', 'share-cart-for-woocommerce' ),
                        'id'   => 'scurl_shared_cart_section_title'
                    ),
                    // WooCommerce renders the checkbox inside its <label> and ignores a
                    // 'label' key, so with desc_tip on the label is empty. The aria-label
                    // is what names the control for screen readers.
                    'shared_cart_choice' => array(
                        'name'              => esc_html__( 'Let Customers Choose', 'share-cart-for-woocommerce' ),
                        'type'              => 'checkbox',
                        'desc'              => esc_html__( 'When a customer who already has items in their cart opens a shared cart link, ask whether to replace their cart or add the shared items to it. When off, a shared cart always replaces the customer\'s cart.', 'share-cart-for-woocommerce' ),
                        'desc_tip'          => true,
                        'default'           => 'no',
                        'id'                => 'scurl_shared_cart_choice',
                        'custom_attributes' => array(
                            'aria-label' => esc_attr__( 'Let customers choose to replace or add to their cart', 'share-cart-for-woocommerce' ),
                        )
                    ),
                    'shared_cart_merge_qty' => array(
                        'name'     => esc_html__( 'Quantity When Adding', 'share-cart-for-woocommerce' ),
                        'type'     => 'select',
                        'options'  => array(
                            'larger'  => esc_html__( 'Keep the larger quantity', 'share-cart-for-woocommerce' ),
                            'combine' => esc_html__( 'Combine quantities', 'share-cart-for-woocommerce' ),
                        ),
                        'desc'     => esc_html__( 'Used when a customer chooses to add a shared cart to theirs and an item is in both carts. Keeping the larger quantity means adding the same link twice never doubles it. Combining adds the two quantities together. Quantities never go above what the product allows.', 'share-cart-for-woocommerce' ),
                        'desc_tip' => true,
                        'default'  => 'larger',
                        'id'       => 'scurl_shared_cart_merge_qty'
                    ),
                    'shared_cart_end' => array(
                        'type' => 'sectionend',
                        'id'   => 'scurl_shared_cart_section_end'
                    ),
                    'email_title' => array(
                        'name' => esc_html__( 'Email This Cart', 'share-cart-for-woocommerce' ),
                        'type' => 'title',
                        'desc' => esc_html__( 'Applies when the Email Cart Button is turned on under Buttons.', 'share-cart-for-woocommerce' ),
                        'id'   => 'scurl_email_section_title'
                    ),
                    'email_cart_access' => array(
                        'name'     => esc_html__( 'Who Can Email Carts', 'share-cart-for-woocommerce' ),
                        'type'     => 'select',
                        'options'  => array(
                            'logged_in' => esc_html__( 'Logged in customers only', 'share-cart-for-woocommerce' ),
                            'everyone'  => esc_html__( 'Everyone, including guests', 'share-cart-for-woocommerce' ),
                        ),
                        'desc'     => esc_html__( 'Guests who are not allowed to send see a link to log in or register on the My Account page instead of the email form. Letting guests send means anyone can use your store to email a cart link to any address.', 'share-cart-for-woocommerce' ),
                        'desc_tip' => true,
                        'default'  => 'logged_in',
                        'id'       => 'scurl_email_cart_access'
                    ),
                    'email_end' => array(
                        'type' => 'sectionend',
                        'id'   => 'scurl_email_section_end'
                    ),
                );
            }

            if ( 'messages' === $section ) {
                return array(
                    'messages_title' => array(
                        'name' => esc_html__( 'Messages', 'share-cart-for-woocommerce' ),
                        'type' => 'title',
                        'desc' => esc_html__( 'Text customers see on the share button and after opening a shared cart.', 'share-cart-for-woocommerce' ),
                        'id'   => 'scurl_messages_section_title'
                    ),
                    'button_text' => array(
                        'name'        => esc_html__( 'Button Text', 'share-cart-for-woocommerce' ),
                        'type'        => 'text',
                        'placeholder' => esc_attr__( 'Share this cart', 'share-cart-for-woocommerce' ),
                        'desc'        => esc_html__( 'Label for the share button. Leave empty to use the default.', 'share-cart-for-woocommerce' ),
                        'desc_tip'    => true,
                        'default'     => '',
                        'id'          => 'scurl_button_text'
                    ),
                    'shared_cart_notice' => array(
                        'name'              => esc_html__( 'Shared Cart Notice', 'share-cart-for-woocommerce' ),
                        'type'              => 'checkbox',
                        'desc'              => esc_html__( 'Show a notice after a shared cart is loaded, saying whether it replaced the customer\'s cart or was added to it.', 'share-cart-for-woocommerce' ),
                        'desc_tip'          => true,
                        'default'           => 'yes',
                        'id'                => 'scurl_shared_cart_notice',
                        'custom_attributes' => array(
                            'aria-label' => esc_attr__( 'Show the shared cart notice', 'share-cart-for-woocommerce' ),
                        )
                    ),
                    'messages_end' => array(
                        'type' => 'sectionend',
                        'id'   => 'scurl_messages_section_end'
                    ),
                );
            }

            $position_desc = esc_html__( 'Select the hook position where the share cart button will appear on the cart page.', 'share-cart-for-woocommerce' );

            // The Cart block is a React app and fires none of the classic cart
            // hooks, so say what the positions actually do there.
            $cart_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'cart' ) : 0;

            if ( $cart_page_id > 0 && has_block( 'woocommerce/cart', $cart_page_id ) ) {
                $position_desc .= '<br />' . esc_html__( 'Your cart page uses the WooCommerce Cart block, which does not support the classic cart hooks. The first three positions place the button above the cart and the rest place it below. Use the shortcode if you need it somewhere else.', 'share-cart-for-woocommerce' );
            }

            return array(
                'section_title' => array(
                    'name' => esc_html__( 'Share Cart Buttons', 'share-cart-for-woocommerce' ),
                    'type' => 'title',
                    'desc' => esc_html__( 'Choose where the share button appears and which buttons are shown beside it.', 'share-cart-for-woocommerce' ),
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
                    'desc'    => $position_desc,
                    'id'      => 'scurl_button_position'
                ),
                // WooCommerce renders the checkbox inside its <label> and ignores a
                // 'label' key, so with desc_tip on the label is empty. The aria-label
                // is what names the control for screen readers.
                'native_share_enabled' => array(
                    'name'              => esc_html__( 'Native Share Button', 'share-cart-for-woocommerce' ),
                    'type'              => 'checkbox',
                    'desc'              => esc_html__( 'Show the native share button beside the copy button. It only appears in browsers that support native sharing.', 'share-cart-for-woocommerce' ),
                    'desc_tip'          => true,
                    'default'           => 'no',
                    'id'                => 'scurl_native_share_enabled',
                    'custom_attributes' => array(
                        'aria-label' => esc_attr__( 'Enable the native share button', 'share-cart-for-woocommerce' ),
                    )
                ),
                'email_cart_enabled' => array(
                    'name'              => esc_html__( 'Email Cart Button', 'share-cart-for-woocommerce' ),
                    'type'              => 'checkbox',
                    'desc'              => esc_html__( 'Show an email button beside the share button. Customers enter their email address and an optional note, and receive the cart link by email.', 'share-cart-for-woocommerce' ),
                    'desc_tip'          => true,
                    'default'           => 'no',
                    'id'                => 'scurl_email_cart_enabled',
                    'custom_attributes' => array(
                        'aria-label' => esc_attr__( 'Enable the email cart button', 'share-cart-for-woocommerce' ),
                    )
                ),
                'section_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'scurl_settings_section_end'
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
                'shortcode_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'scurl_shortcode_section_end'
                )
            );
        }
    }

    new SCURL_Settings();
}
