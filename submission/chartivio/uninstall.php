<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Chartivio
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete all 'chartivio' posts and their associated meta data.
 * 
 * We use get_posts() to retrieve all charts and wp_delete_post() to force delete them.
 * wp_delete_post() handles the cleanup of post_meta automatically.
 */
$chartivio_posts = get_posts(array(
    'post_type' => 'chartivio',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids' // Only get IDs for better performance
));

if (!empty($chartivio_posts)) {
    foreach ($chartivio_posts as $post_id) {
        // True parameter forces deletion (bypassing trash)
        wp_delete_post($post_id, true);
    }
}
