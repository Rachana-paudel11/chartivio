<?php
/**
 * Plugin Name: DearCharts
 * Description: A custom post type for managing charts with a tabbed meta box interface.
 * Version: 1.0
 * Author: Your Name
 */

// PSEUDO CODE:
// IF ABSPATH is not defined THEN
//     EXIT script to prevent direct access
// END IF
if (!defined('ABSPATH')) {
    exit;
}

// Load professional admin UI
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

/**
 * Register Custom Post Type 'dearcharts'
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
        ),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-chart-area',
        'supports' => array('title'),
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
 * Include modular files
 */
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
