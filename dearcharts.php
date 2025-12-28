<?php
/**
 * Plugin Name: DearCharts
 * Description: A custom post type for managing charts with a tabbed meta box interface.
 * Version: 1.0
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Custom Post Type 'dearcharts'
 */
function dearcharts_register_cpt()
{
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

    if (function_exists('register_post_type')) {
        register_post_type('dearcharts', $args);
    }
}
add_action('init', 'dearcharts_register_cpt');

/**
 * Include modular files
 */
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
