<?php
/**
 * Plugin Name: DearCharts
 * Description: A custom post type for managing charts with a tabbed meta box interface.
 * Version: 1.1
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
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
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
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
 * Include Module Files
 */
// REQUIRE admin settings module (meta boxes, admin UI, data persistence)
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

// REQUIRE shortcodes module (frontend shortcode and chart rendering)
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
