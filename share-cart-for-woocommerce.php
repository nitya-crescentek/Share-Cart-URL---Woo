<?php
/**
 * Plugin Name: Share Cart for WooCommerce
 * Description: Share Cart URL for WooCommerce enables customers to share their cart URL directly from the WooCommerce cart page.
 * Version: 1.3
 * Author: Nitya Saha
 * Author URI: https://nitya.codesocials.com
 * Text Domain: share-cart-for-woocommerce
 * Requires plugins: woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    die( esc_html__( "No direct access!", 'share-cart-for-woocommerce' ) );
}

define( 'SCURL_VERSION', '1.3');
define( 'SCURL_PLUGIN_FILE', __FILE__ );
// Kept for backward compatibility. Despite the name, this is a URL, not a path.
define( 'SCURL_PLUGIN_PATH', plugin_dir_url(__FILE__) );

/**
 * Check whether WooCommerce is available.
 *
 * Replaces the previous active_plugins scan, which failed when WooCommerce was
 * network activated on multisite or installed in a non-standard folder.
 *
 * @return bool
 */
function scurl_is_woocommerce_active() {
    if ( class_exists( 'WooCommerce' ) ) {
        return true;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // is_plugin_active() covers network activated plugins internally.
    return is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Declare compatibility with WooCommerce features.
 *
 * HPOS (custom order tables) is fully supported: this plugin never touches
 * order storage. Cart/Checkout blocks are intentionally not declared yet,
 * the block based cart is targeted for a later release.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SCURL_PLUGIN_FILE, true );
    }
} );

/**
 * Plugin activation hook.
 * Checks if WooCommerce is active, otherwise deactivates this plugin.
 * Also sets default options.
 */
function scurl_plugin_activation() {
    // Check if WooCommerce is active.
    if ( ! scurl_is_woocommerce_active() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'This plugin requires WooCommerce to be installed and active.', 'share-cart-for-woocommerce' ) );
    }
}
register_activation_hook( __FILE__, 'scurl_plugin_activation' );

if ( ! class_exists( 'SCURL_Main' ) ) {
    class SCURL_Main {

        private $plugin_basename;

        public function __construct() {
            // Get the plugin basename.
            $this->plugin_basename = plugin_basename( __FILE__ );
            // Initialize the plugin when plugins are loaded.
            add_action( 'plugins_loaded', array( $this, 'init' ) );
        }

        public function init() {
            // Ensure WooCommerce is active before running plugin code.
            if ( ! scurl_is_woocommerce_active() ) {
                return;
            }

            require_once plugin_dir_path( __FILE__ ) . 'includes/setting.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/share-cart-url.php';

            // Set default option for button position if not already set.
            if ( false === get_option( 'scurl_button_position' ) ) {
                update_option( 'scurl_button_position', 'woocommerce_before_cart_table' );
            }

            if ( false === get_option( 'scurl_native_share_enabled' ) ) {
                update_option( 'scurl_native_share_enabled', 'no' );
            }

            // Add settings link on the plugins page.
            add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'insert_view_logs_link' ) );
            add_filter( 'plugin_row_meta', array( $this, 'addon_plugin_links' ), 10, 2 );
        }

        /**
         * Add a settings link to the plugin's action links.
         *
         * @param array $links
         * @return array
         */
        public function insert_view_logs_link( $links ) {
            $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=share_cart_url' ) ) . '">' . esc_html__( 'Settings', 'share-cart-for-woocommerce' ) . '</a>';
            array_unshift( $links, $settings_link );
            return $links;
        }

        public function addon_plugin_links( $links, $file ) {
            if ( $file === $this->plugin_basename ) {
                $links[] = __( '<a href="https://buymeacoffee.com/nityasaha" style="font-weight:bold;color:#00d300;font-size:15px;">Donate</a>', 'share-cart-for-woocommerce' );
                $links[] = __( 'Made with Love ❤️', 'share-cart-for-woocommerce' );
            }
    
            return $links;
        }
    }
}

// Instantiate the main plugin class.
new SCURL_Main();
