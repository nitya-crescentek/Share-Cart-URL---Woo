=== Share Cart for WooCommerce ===
Contributors: nityasaha
Donate link: https://buymeacoffee.com/nityasaha
Tags: woocommerce, cart, share, shopping cart, share-cart
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.3
Requires PHP: 7.4
Requires plugins: woocommerce
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enable customers to share WooCommerce carts via URL for collaborative shopping, gift planning, and social commerce.

== Description ==

Share Cart for WooCommerce is a powerful yet lightweight plugin that revolutionizes the shopping experience by enabling customers to share their WooCommerce cart contents with a single click. Perfect for collaborative shopping, gift registries, wish lists, and social commerce strategies.

= Why Share Cart for WooCommerce? =

In today's social shopping environment, customers want to share their finds with friends, family, and colleagues. This plugin makes cart sharing effortless, helping you:

* Increase Sales - Enable group purchases and collaborative decision-making
* Boost Customer Engagement - Make shopping more social and interactive
* Reduce Cart Abandonment - Allow customers to save and share carts for later
* Enhance User Experience - Simplify gift planning and wish list creation
* Improve Marketing - Leverage social sharing for organic promotion

= Key Features =

* One-Click Cart Sharing - Generate shareable URLs instantly from the cart page
* Flexible Button Placement - Choose from multiple hook positions to match your theme
* Seamless WooCommerce Integration - Native integration with WooCommerce settings
* Easy Configuration - Simple settings interface in WooCommerce admin
* Lightweight & Fast - Minimal impact on site performance
* Mobile Responsive - Works perfectly on all devices
* Developer Friendly - Clean code with hooks for customization
* Translation Ready - Fully compatible with WPML and multilingual setups

= Perfect For =

* Gift Planning - Share cart contents with gift givers
* Group Purchases - Collaborate on bulk orders with friends or colleagues
* Event Shopping - Plan purchases for weddings, parties, or corporate events
* Wish Lists - Create and share shopping wish lists
* Sales Teams - B2B stores can share quotes and product selections
* Social Commerce - Enable customers to share finds on social media
* Customer Support - Help customers by reviewing their cart remotely

= How It Works =

1. Customer adds products to their WooCommerce cart
2. A "Share Cart" button appears on the cart page (position customizable)
3. Clicking the button generates a unique shareable URL
4. The URL can be copied and shared via email, messaging apps, or social media
5. Recipients click the link and see the exact cart contents
6. Products are automatically added to the recipient's cart

= Configuration Options =

Navigate to WooCommerce > Settings > Share Cart to access:

* Button Position - Select from multiple WooCommerce cart hooks
* Button Text - Change the button label without writing any code
* Button Display - Control when and where the share button appears
* Easy Customization - Style the button with custom CSS

Available button positions include:
* Before Cart Table
* Before Cart Contents
* After Cart
* Before Cart Totals
* After Cart Totals
* Proceed to Checkout
* Cart Totals Before / After Order Total
* Cart Totals After Shipping
* Cart Coupon
* Hide (use the shortcode instead)

= Privacy & Security =

This plugin respects user privacy and follows WordPress security best practices. Cart URLs are generated using WooCommerce's built-in functionality, ensuring data security and compliance.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "Share Cart for WooCommerce"
4. Click Install Now then Activate
5. Go to WooCommerce > Settings > Share Cart to configure

= Manual Installation =

1. Download the plugin ZIP file
2. Upload the `share-cart-url-for-woocommerce` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu in WordPress
4. Navigate to WooCommerce > Settings > Share Cart to configure

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher

Note: This plugin requires WooCommerce to be installed and active. If WooCommerce is not active, the plugin will automatically deactivate with an admin notice.

= First-Time Setup =

1. After activation, visit WooCommerce > Settings
2. Click on the Share Cart tab
3. Select your preferred button position from the dropdown
4. Click Save Changes
5. Visit your cart page to see the share button in action

== Frequently Asked Questions ==

= How do I change the position of the share cart button? =

Navigate to WooCommerce > Settings > Share Cart after activation. Select your desired hook position from the dropdown menu and save your settings. You can choose from multiple positions including before/after cart table, before/after cart totals, and more.

= What happens if WooCommerce is not active? =

The plugin automatically checks for WooCommerce during activation. If WooCommerce is not installed or active, the plugin will deactivate itself and display an admin notice informing you that WooCommerce is required.

= Can I customize the appearance of the share cart button? =

Yes! You can easily customize the button's appearance using CSS. Add custom styles through your theme's stylesheet, the WordPress Customizer, or a custom CSS plugin. The button has specific CSS classes for easy targeting.

= Does this plugin work with my theme? =

Share Cart for WooCommerce is designed to work with any WooCommerce-compatible theme. The plugin uses WooCommerce's standard hooks and follows WordPress coding standards, ensuring broad compatibility.

= Will shared carts expire? =

Shared cart URLs do not expire on a fixed schedule. They are stored in the server temporary directory, which some hosting environments clear periodically, so very old links may stop working. Configurable expiry and dedicated storage are planned for a future release.

= Can recipients modify the shared cart? =

Yes, once products are added to the recipient's cart via the shared URL, they can modify quantities, remove items, or add additional products just like a normal shopping cart.

= Does this work with variable products? =

Absolutely! The plugin preserves all product variations, quantities, and configurations when sharing cart URLs.

= Is the plugin translation ready? =

Yes, Share Cart for WooCommerce is fully translation-ready and compatible with WPML and other multilingual plugins.

= Does it work on mobile devices? =

Yes! The plugin is fully responsive and works seamlessly on smartphones, tablets, and desktop devices.

= Can customers share empty carts? =

The share button only appears when there are items in the cart, preventing confusion from sharing empty carts.

= How do I get support? =

For support, please use the WordPress.org support forum for this plugin. We monitor and respond to questions regularly.

== Screenshots ==

1. Cart Page with Share Button
2. Share Cart Settings Tab under WooCommerce settings
3. Cart Page with generated link and buttons.

== Changelog ==

= 1.3 =
* Security: removed an unused cart price override code path that allowed the submitted cart form to change product prices. All users should update.
* Security: shared cart data is now restored without instantiating objects, and the share key is strictly validated before any file is read.
* Fixed: copying the share link now works on iPhone, iPad and Safari on macOS. The link is prepared in advance so the copy happens inside the click itself, which is what Safari requires.
* Fixed: the success message is now only shown when the link was genuinely copied. Previously it appeared even when the copy had failed.
* Added: the link is always shown in a selectable field with its own Copy button, so it can be copied manually if the browser blocks clipboard access.
* Added: native share sheet support on mobile devices via the Web Share API.
* Added: Button Text setting to change the button label from WooCommerce settings.
* Added: declared compatibility with WooCommerce High Performance Order Storage (HPOS).
* Fixed: the share button is no longer displayed when the cart is empty.
* Fixed: the shortcode now renders in place instead of being pushed to the top of the content.
* Fixed: the Use Shortcode heading no longer appears above the Button Position field on the settings screen.
* Fixed: share links were malformed on sites using plain permalinks.
* Fixed: WooCommerce is now detected correctly when it is network activated on multisite.
* Improved: button text and all on screen messages are now translatable.
* Improved: the share button no longer submits the cart form when JavaScript is unavailable.
* Compatibility with WordPress 7.1 and current WooCommerce releases.
* Fixed: the Button Position setting had no effect on cart pages built with the WooCommerce Cart block, because that block fires none of the classic cart hooks. The button is now placed above or below the block instead. Only the shortcode worked there before.
* Fixed: the share link is now refreshed when the cart changes on a Cart block page, so a link copied after editing the cart matches what is in it.
* Added: native share button for users, with enable and disable option. It is off by default.
* Improved: the copy and share controls are now compact icon buttons that carry their own styling, so they stay consistent whatever the theme does to buttons.

= 1.2 =
* Compatibility with 6.9
* Fixed issue: link generation on iPhones

= 1.1 =
* Compatibility
* Enhanced readme documentation with SEO optimization
* Improved feature descriptions and use cases
* Added comprehensive FAQ section
* Updated compatibility information
* Minor bug fixes and performance improvements

= 1.0 =
* Initial release of Share Cart for WooCommerce
* Added WooCommerce settings tab for plugin configuration
* Implemented customizable hook positions for button display
* Added WooCommerce dependency check on activation
* Cart URL sharing functionality
* Mobile-responsive design
* Translation-ready code

== Upgrade Notice ==

= 1.3 =
Important security update. Also fixes copying the share link on iPhone, iPad and Safari. Update is strongly recommended for all users.

= 1.1 =
compatibility and enhanced documentation and improved user guidance. Recommended update for all users.

= 1.0 =
Initial release - Install now to enable cart sharing functionality on your WooCommerce store.