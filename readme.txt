=== Share Cart for WooCommerce ===
Contributors: nityasaha
Donate link: https://buymeacoffee.com/nityasaha
Tags: woocommerce, cart, share, shopping cart, share-cart
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.4
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
* Email This Cart - Optional button that emails the cart link, with a personal note, using your store's WooCommerce email template
* Native Share - Optional button that opens the device share sheet on supporting browsers
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
4. The URL can be shared via messaging apps or social media, or sent from your store with the Email this cart button
5. Recipients click the link and see the exact cart contents
6. The shared products are loaded into the recipient's cart, replacing what was in it, and a notice confirms it (this can be turned off)

= Email This Cart =

Turn on Email Cart Button under WooCommerce > Settings > Share Cart. When a customer clicks the share button, an envelope icon appears beside the copy icon. It opens a popup where the customer enters an email address and an optional note of up to 500 characters, and the cart link is sent to that address.

* By default only logged in customers can send. Guests see a link to log in or register on the My Account page instead of the form. To let guests send as well, change the Who Can Email Carts setting
* Logged in customers can send the link to their own account email with one click, or choose Other to send it to someone else
* The email is sent through WooCommerce, using the From name and address, header image, colours and footer text set in WooCommerce > Settings > Emails
* The link is always built on the server from the customer's own cart, so the form cannot be used to send any other link
* Links, web addresses and email addresses are not allowed in the note, and the note appears below the cart link, labelled as written by the sender, so the form cannot be used to send phishing messages in your store's name
* Each customer can send up to 5 emails per hour, counted per account, so the limit stays accurate on busy stores behind a CDN or load balancer. Guests, when allowed, are counted per IP address
* Off by default

Developers can change the subject, heading and body of the email with the `scurl_email_cart_args` filter. See the FAQ for examples.

= Configuration Options =

Navigate to WooCommerce > Settings > Share Cart to access:

* Button Position - Select from multiple WooCommerce cart hooks
* Button Text - Change the button label without writing any code
* Button Display - Control when and where the share button appears
* Native Share Button - Show a share sheet button on browsers that support it (off by default)
* Shared Cart Notice - Show a notice when a shared cart link is opened (on by default)
* Email Cart Button - Let customers email the cart link to themselves or someone else (off by default)
* Who Can Email Carts - Logged in customers only (default), or everyone including guests
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

This plugin respects user privacy and follows WordPress security best practices. A shared cart is saved as a small file in the server's temporary directory, named by a hash of the cart contents. Only the products, quantities and coupons are shared. No customer account, address or payment details are included.

When a customer uses Email this cart, the email address and note are used only to send that email and are not saved by the plugin. To limit sending, the plugin keeps a count of emails per customer account for one hour. If guests are allowed to send, a hashed copy of the guest's IP address is kept for one hour instead.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "Share Cart for WooCommerce"
4. Click Install Now then Activate
5. Go to WooCommerce > Settings > Share Cart to configure

= Manual Installation =

1. Download the plugin ZIP file
2. Upload the `share-cart-for-woocommerce` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu in WordPress
4. Navigate to WooCommerce > Settings > Share Cart to configure

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher

Note: This plugin requires WooCommerce to be installed and active. If WooCommerce is not active, activation is stopped and a message explains that WooCommerce is required.

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

The plugin checks for WooCommerce during activation. If WooCommerce is not installed or active, activation is stopped and a message explains that WooCommerce is required. If WooCommerce is deactivated later, the share button simply stops appearing until it is active again.

= Can I customize the appearance of the share cart button? =

Yes! You can easily customize the button's appearance using CSS. Add custom styles through your theme's stylesheet, the WordPress Customizer, or a custom CSS plugin. The button has specific CSS classes for easy targeting.

= Does this plugin work with my theme? =

Share Cart for WooCommerce is designed to work with any WooCommerce-compatible theme. The plugin uses WooCommerce's standard hooks and follows WordPress coding standards, ensuring broad compatibility.

= Will shared carts expire? =

Shared cart URLs do not expire on a fixed schedule. They are stored in the server temporary directory, which some hosting environments clear periodically, so very old links may stop working. Configurable expiry and dedicated storage are planned for a future release.

= Can recipients modify the shared cart? =

Yes. The shared products replace the recipient's cart, and they can then change quantities, remove items or add more products like any other cart. Opening the same link again loads the shared cart again.

= Can I turn off the "shared cart loaded" notice? =

Yes. When someone opens a share link, a notice confirms the shared cart was loaded and says whether it replaced the items already in their cart. Turn it off with Shared Cart Notice under WooCommerce > Settings > Share Cart, or change the wording with the `scurl_shared_cart_notice_text` filter.

= Does this work with variable products? =

Absolutely! The plugin preserves all product variations, quantities, and configurations when sharing cart URLs.

= Is the plugin translation ready? =

Yes, Share Cart for WooCommerce is fully translation-ready and compatible with WPML and other multilingual plugins.

= Does it work on mobile devices? =

Yes! The plugin is fully responsive and works seamlessly on smartphones, tablets, and desktop devices.

= Can customers share empty carts? =

The share button only appears when there are items in the cart, preventing confusion from sharing empty carts.

= How do I let customers email their cart? =

Go to WooCommerce > Settings > Share Cart, turn on Email Cart Button and save. When a customer clicks the share button, an envelope icon appears next to the copy icon. Clicking it opens a popup where they enter an email address and an optional note, and the cart link is sent to that address.

By default only logged in customers can send. Guests who open the popup see a link to log in or register on the My Account page. To let guests send as well, set Who Can Email Carts to "Everyone, including guests". That means anyone can use your store to email a cart link to any address, so keep the rate limit in place.

= How can I customize the cart email? =

The email is wrapped in your WooCommerce email template, so the From name and address, header image, colours and footer text all come from WooCommerce > Settings > Emails.

To change the subject, heading or body, use the `scurl_email_cart_args` filter. Add the code to your child theme's `functions.php` file or a code snippets plugin.

The filter receives three values:

* `$args` - an array with `subject`, `heading` (the title shown in the email header) and `body` (the HTML placed inside the template)
* `$share_url` - the cart link
* `$message` - the customer's note as plain text, or an empty string. It never contains links. Escape it before adding it to the body, and keep it below your own text with a label showing it was written by the sender, so it cannot be mistaken for a message from your store.

Change the subject and heading:

```php
add_filter( 'scurl_email_cart_args', function ( $args, $share_url, $message ) {
    $args['subject'] = 'Your saved cart is waiting';
    $args['heading'] = 'Ready when you are';

    return $args;
}, 10, 3 );
```

Replace the body with your own layout, including a list of the products in the cart:

```php
add_filter( 'scurl_email_cart_args', function ( $args, $share_url, $message ) {
    $items = '';
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $items .= sprintf(
            '<li>%s &times; %d</li>',
            esc_html( $cart_item['data']->get_name() ),
            absint( $cart_item['quantity'] )
        );
    }

    $body  = '<p>Here is what is in your cart:</p>';
    $body .= '<ul>' . $items . '</ul>';
    $body .= '<p><a href="' . esc_url( $share_url ) . '">Open your cart</a></p>';

    if ( '' !== $message ) {
        $body .= '<p><strong>Note from the sender:</strong><br>' . nl2br( esc_html( $message ) ) . '</p>';
    }

    $args['body'] = $body;

    return $args;
}, 10, 3 );
```

The header and footer come from the WooCommerce templates `emails/email-header.php` and `emails/email-footer.php`. You can override them in your theme, but the change then applies to every WooCommerce email.

= Can I change how many emails a customer can send? =

Yes. By default each customer can send 5 cart emails per hour, counted per account. There is no store-wide limit unless you set one. Change the per customer limit with the `scurl_email_rate_limit` filter, and add a store-wide hourly limit with the `scurl_email_store_rate_limit` filter:

```php
add_filter( 'scurl_email_rate_limit', function () {
    return 10;
} );

add_filter( 'scurl_email_store_rate_limit', function () {
    return 200;
} );
```

Returning `0` from `scurl_email_rate_limit` removes the per customer limit, which is not recommended on a live store. A store-wide limit stops every customer from sending once it is reached, so set it well above your normal hourly volume.

If you let guests send, guests are told apart by IP address. If your site is behind Cloudflare or another proxy, every guest can appear to have the proxy's address and share one allowance. Return the real address from the header your proxy sets with the `scurl_email_client_ip` filter. Only do this when the site really is behind that proxy, because anyone can send the header otherwise.

```php
add_filter( 'scurl_email_client_ip', function ( $ip ) {
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
    }

    return $ip;
} );
```

= What happens to my data if I delete the plugin? =

Deactivating the plugin keeps everything. Deleting it from the Plugins screen removes its settings, the email rate limit counters and the saved share links, on every site of a multisite network. Share links that were already sent stop working.

= Which hooks are available for developers? =

* `scurl_button_text` - filter the share button label
* `scurl_share_url` - filter the generated share link. Receives the URL and the cart hash.
* `scurl_email_cart_args` - filter the subject, heading and body of the cart email
* `scurl_email_rate_limit` - filter how many cart emails one customer, or one guest IP address, may send per hour. Default 5.
* `scurl_email_store_rate_limit` - filter how many cart emails the whole store may send per hour. Default 0, no limit.
* `scurl_email_client_ip` - filter the guest IP address used for the limit when guests may send
* `scurl_shared_cart_notice_text` - filter the notice shown when a shared cart is loaded. Receives the text and whether it replaced other items. Return plain text, since the notice is escaped before it is shown.

= How do I get support? =

For support, please use the WordPress.org support forum for this plugin. We monitor and respond to questions regularly.

== Screenshots ==

1. Cart Page with Share Button
2. Share Cart Settings Tab under WooCommerce settings
3. Cart Page with generated link and buttons.
4. Email this cart popup

== Changelog ==

= 1.4 =
* Added: "Email this cart" button. When enabled in settings, an envelope icon appears beside the share icon and opens a popup where customers enter their email address and an optional message, then receive the cart link by email. Off by default.
* Added: cart emails use the WooCommerce email template, and the `scurl_email_cart_args` filter can change the subject, heading or body.
* Added: Who Can Email Carts setting. Only logged in customers can send by default, and guests see a link to log in or register on the My Account page. Stores can allow guests to send as well.
* Added: a notice when a shared cart link is opened, saying the shared cart was loaded and whether it replaced the items already in the cart. It works on the classic cart and the Cart block, and can be turned off in settings.
* Security: the emailed link is always built on the server from the visitor's own cart.
* Security: links, web addresses and email addresses are refused in the email note, and the note is shown below the cart link, labelled as written by the sender, so the form cannot be used to send phishing messages in the store's name.
* Security: sending is limited to 5 emails per hour per customer account, or per IP address for guests when they are allowed (filterable with `scurl_email_rate_limit`). An optional store-wide hourly limit can be set with `scurl_email_store_rate_limit`. Stores behind a proxy that allow guests can supply the real visitor IP with `scurl_email_client_ip`.
* Added: deleting the plugin now removes its settings, email rate limit counters and saved share links. Deactivating keeps them.
* Fixed: opening a share link now loads the cart and then reloads the page without the share parameter. Previously the shared cart was restored again on every refresh, and on the classic cart removing an item or updating the cart brought the shared cart straight back.

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

= 1.4 =
Adds an optional Email this cart button, off by default, and a notice when a shared cart is opened. Also fixes shared carts being restored again on refresh, which undid the recipient's changes.

= 1.3 =
Important security update. Also fixes copying the share link on iPhone, iPad and Safari. Update is strongly recommended for all users.

= 1.1 =
compatibility and enhanced documentation and improved user guidance. Recommended update for all users.

= 1.0 =
Initial release - Install now to enable cart sharing functionality on your WooCommerce store.