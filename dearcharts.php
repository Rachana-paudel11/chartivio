<?php
/**
 * Plugin Name: DearCharts
 * Plugin URI: https://example.com/dearcharts
 * Description: A basic WordPress plugin header for DearCharts.
 * Version: 1.0.0
 * Author: Rachana Paudel
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: dearcharts
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register DearCharts Custom Post Type.
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
 * Shortcode to display DearChart title.
 * Usage: [dearchart_title id="123"]
 */
function dearcharts_shortcode_title($atts)
{
    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts, 'dearchart_title');

    $post_id = intval($atts['id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'dearcharts') {
        return 'Chart title not found';
    }

    $title = get_the_title($post_id);

    return '<h2 class="dearcharts-title">' . esc_html($title) . '</h2>';
}
add_shortcode('dearchart_title', 'dearcharts_shortcode_title');

/**
 * Add Chart ID column to the dearcharts post type list.
 */
function dearcharts_add_id_column($columns)
{
    $columns['id'] = 'ID';
    return $columns;
}
add_filter('manage_dearcharts_posts_columns', 'dearcharts_add_id_column');

/**
 * Display the ID for the custom column.
 */
function dearcharts_show_id_column($column, $post_id)
{
    if ($column === 'id') {
        echo $post_id;
    }
}
add_action('manage_dearcharts_posts_custom_column', 'dearcharts_show_id_column', 10, 2);
