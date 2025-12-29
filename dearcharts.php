<?php
/**
 * Plugin Name: DearCharts
 * Description: A professional custom post type for managing charts with a split-screen live preview.
 * Version: 1.1
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load custom logic
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';

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
            'edit_item' => 'Edit Chart',
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
 * Enqueue Admin Scripts for DearCharts
 */
function dearcharts_admin_scripts($hook)
{
    global $post;
    if ($hook == 'post-new.php' || $hook == 'post.php') {
        if ($post && 'dearcharts' === $post->post_type) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_scripts');

/**
 * Add Custom Columns to Admin List
 */
add_filter('manage_dearcharts_posts_columns', function ($columns) {
    $columns['shortcode'] = 'Shortcode';
    return $columns;
});

add_action('manage_dearcharts_posts_custom_column', function ($column, $post_id) {
    if ($column === 'shortcode') {
        echo '<code>[dearchart id="' . $post_id . '"]</code>';
    }
}, 10, 2);
