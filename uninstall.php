<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package DearCharts
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete all 'dearcharts' posts and their associated meta data.
 * 
 * We use get_posts() to retrieve all charts and wp_delete_post() to force delete them.
 * wp_delete_post() handles the cleanup of post_meta automatically.
 */
$dearcharts_posts = get_posts(array(
    'post_type' => 'dearcharts',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids' // Only get IDs for better performance
));

if (!empty($dearcharts_posts)) {
    foreach ($dearcharts_posts as $post_id) {
        // True parameter forces deletion (bypassing trash)
        wp_delete_post($post_id, true);
    }
}
