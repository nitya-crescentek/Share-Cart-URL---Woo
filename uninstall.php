<?php
/**
 * Remove the plugin's data when it is deleted from the Plugins screen.
 *
 * Deactivating the plugin keeps everything. Only deleting it runs this file.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete the settings and email rate limit counters of the current site.
 */
function scurl_uninstall_site() {
    global $wpdb;

    $options = array(
        'scurl_button_position',
        'scurl_button_text',
        'scurl_native_share_enabled',
        'scurl_shared_cart_choice',
        'scurl_shared_cart_merge_qty',
        'scurl_shared_cart_notice',
        'scurl_email_cart_enabled',
        'scurl_email_cart_access',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // The rate limit transients are named per hour and per sender, so they
    // can only be found by prefix. With a persistent object cache they are
    // not in the database at all, and simply expire within the hour.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No API deletes transients by prefix.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_scurl_email_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_scurl_email_' ) . '%'
        )
    );
}

/**
 * Delete the saved share links from the server's temporary directory.
 *
 * That directory is shared with other software, so a file is only removed
 * when its name is a 32 character share hash and its content starts the way
 * every shared cart does: the serialized session array, opening with the
 * cart key.
 */
function scurl_uninstall_share_files() {
    $files = glob( trailingslashit( get_temp_dir() ) . str_repeat( '[0-9a-f]', 32 ), GLOB_NOSORT );

    if ( empty( $files ) ) {
        return;
    }

    $signature = 'a:5:{s:4:"cart";';

    foreach ( $files as $file ) {
        if ( ! is_file( $file ) || ! is_readable( $file ) ) {
            continue;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file, only the first bytes are read.
        $head = file_get_contents( $file, false, null, 0, strlen( $signature ) );

        if ( $signature === $head ) {
            wp_delete_file( $file );
        }
    }
}

if ( is_multisite() ) {
    // Settings are stored per site, whether the plugin was network activated
    // or activated site by site.
    $scurl_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

    foreach ( $scurl_site_ids as $scurl_site_id ) {
        switch_to_blog( $scurl_site_id );
        scurl_uninstall_site();
        restore_current_blog();
    }
} else {
    scurl_uninstall_site();
}

// One temporary directory serves every site on the network, so this runs once.
scurl_uninstall_share_files();
