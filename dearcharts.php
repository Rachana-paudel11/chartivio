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
<<<<<<< Updated upstream
=======

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
    ), $atts, 'dearchart');

    $post_id = intval($atts['id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'dearcharts') {
        return 'Chart title not found';
    }

    $title = get_the_title($post_id);

    return '<h2 class="dearcharts-title">' . esc_html($title) . '</h2>';
}
add_shortcode('dearchart', 'dearcharts_shortcode_title');

/**
 * Add Chart ID and Shortcode columns to the dearcharts post type list.
 */
function dearcharts_add_id_column($columns)
{
    $columns['id'] = 'ID';
    $columns['shortcode'] = 'Shortcode';
    return $columns;
}
add_filter('manage_dearcharts_posts_columns', 'dearcharts_add_id_column');

/**
 * Display the content for custom columns.
 */
function dearcharts_custom_column_content($column, $post_id)
{
    switch ($column) {
        case 'id':
            echo $post_id;
            break;
        case 'shortcode':
            echo '<code>[dearchart id="' . $post_id . '"]</code>';
            break;
    }
}
add_action('manage_dearcharts_posts_custom_column', 'dearcharts_custom_column_content', 10, 2);

/**
 * Add Meta Box for Shortcodes.
 */
function dearcharts_add_meta_boxes()
{
    add_meta_box(
        'dearcharts_shortcodes',
        'Chart Shortcodes',
        'dearcharts_shortcodes_meta_box_html',
        'dearcharts',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'dearcharts_add_meta_boxes');

/**
 * HTML for Shortcodes Meta Box.
 */
function dearcharts_shortcodes_meta_box_html($post)
{
    ?>
    <p>Use these shortcodes to embed this chart:</p>
    <p>
        <label>Shortcode:</label><br>
        <code>[dearchart id="<?php echo esc_attr($post->ID); ?>"]</code>
    </p>
    <?php
}
>>>>>>> Stashed changes
