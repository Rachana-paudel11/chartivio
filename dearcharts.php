<?php
/**
 * Plugin Name: DearCharts
 * Plugin URI:  https://example.com/dearcharts
 * Description: Professional Data Visualization for WordPress with Zero-Latency Previews.
 * Version:     1.0.0
 * Author:      Rachana Paudel
 * Author URI:  https://example.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dearcharts
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define('DEARCHARTS_PATH', plugin_dir_path(__FILE__));

/**
 * Register Custom Post Type 'dearcharts'
 * PSEUDOCODE: Create a data structure to store chart title, data, and settings.
 */
function dearcharts_register_cpt()
{
    // PSEUDO CODE:
    // DEFINE labels for the custom post type (Name: DearCharts, Singular: DearChart)
    // DEFINE arguments: public, show UI, show in menu, icon, supports title
    $args = array(
        'labels' => array(
            'name' => 'DearCharts',
            'singular_name' => 'DearChart',
            'add_new' => 'Add New Chart',
            'add_new_item' => 'Add New Chart',
            'edit_item' => 'Edit Chart',
            'all_items' => 'All Charts'
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'menu_icon' => 'dashicons-chart-area',
        'supports' => array('title'),
        'has_archive' => false,

    );

    // IF register_post_type function exists THEN
    //     REGISTER 'dearcharts' post type with arguments
    // END IF
    if (function_exists('register_post_type')) {
        register_post_type('dearcharts', $args);
    }
}
// HOOK dearcharts_register_cpt to 'init' action
add_action('init', 'dearcharts_register_cpt');

/**
 * Force 1-column layout for dearcharts admin screen
 */
add_filter('get_user_option_screen_layout_dearcharts', function ($columns) {
    return 1;
});

/**
 * Hide Screen Options tab for dearcharts admin screen
 */
add_filter('screen_options_show_screen', function ($show_screen, $screen) {
    if ($screen->post_type === 'dearcharts') {
        return false;
    }
    return $show_screen;
}, 10, 2);

/**
 * Include Module Files
 */
// REQUIRE admin settings module (meta boxes, admin UI, data persistence)
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

// REQUIRE shortcodes module (frontend shortcode and chart rendering)
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';

/**
 * Add Custom Columns to Admin List
 */
add_filter('manage_edit-dearcharts_columns', 'dearcharts_add_admin_columns');
function dearcharts_add_admin_columns($columns)
{
    // Insert new columns after the checkbox
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['dearcharts_shortcode'] = 'Shortcode';
    $new_columns['dearcharts_id'] = 'ID';
    $new_columns['dearcharts_type'] = 'Type';
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

/**
 * Populate Custom Columns
 */
add_action('manage_dearcharts_posts_custom_column', 'dearcharts_populate_admin_columns', 10, 2);
function dearcharts_populate_admin_columns($column, $post_id)
{
    switch ($column) {
        case 'dearcharts_shortcode':
            echo '<code style="user-select:all;">[dearchart id="' . $post_id . '"]</code>';
            break;
        case 'dearcharts_id':
            echo $post_id;
            break;
        case 'dearcharts_type':
            $type = get_post_meta($post_id, '_dearcharts_type', true);
            echo ucfirst($type ?: 'Default');
            break;
    }
}
