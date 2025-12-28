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
 */
function dearcharts_register_cpt()
{
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

    if (function_exists('register_post_type')) {
        register_post_type('dearcharts', $args);
    }
}
add_action('init', 'dearcharts_register_cpt');

/**
 * Include Files
 */
require_once DEARCHARTS_PATH . 'includes/admin-settings.php';
require_once DEARCHARTS_PATH . 'includes/shortcodes.php';
