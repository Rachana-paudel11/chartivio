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
 *
 * PSEUDO CODE:
 * FUNCTION dearcharts_register_cpt
 *   DEFINE args = [
 *     labels => [name => 'DearCharts', singular_name => 'DearChart'],
 *     public => true,
 *     show_ui => true,
 *     show_in_menu => true,
 *     menu_icon => 'dashicons-chart-area',
 *     supports => ['title']
 *   ]
 *   IF function 'register_post_type' exists THEN
 *     CALL register_post_type('dearcharts', args)
 *   END IF
 * END FUNCTION
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
 * Usage: [dearchart id="123"]
 *
 * PSEUDO CODE:
 * FUNCTION dearcharts_shortcode(attributes)
 *   MERGE defaults (id => '') with attributes
 *   GET post_id from attributes['id'] as integer
 *   GET post object by post_id
 *   IF post doesn't exist OR post_type is not 'dearcharts' THEN
 *     RETURN 'Chart title not found'
 *   END IF
 *   GET title of the post
 *   RETURN formatted HTML header with the title
 * END FUNCTION
 */
function dearcharts_shortcode($atts)
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
add_shortcode('dearchart', 'dearcharts_shortcode');

/**
 * Add Chart ID and Shortcode columns to the dearcharts post type list.
 *
 * PSEUDO CODE:
 * FUNCTION dearcharts_add_id_column(columns)
 *   ADD 'id' => 'ID' to columns array
 *   ADD 'shortcode' => 'Shortcode' to columns array
 *   RETURN columns array
 * END FUNCTION
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
 *
 * PSEUDO CODE:
 * FUNCTION dearcharts_custom_column_content(column_name, post_id)
 *   SWITCH column_name
 *     CASE 'id':
 *       PRINT post_id
 *       BREAK
 *     CASE 'shortcode':
 *       PRINT formatted shortcode string '[dearchart id="..."]'
 *       BREAK
 *   END SWITCH
 * END FUNCTION
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
 * Add Meta Boxes: Shortcode Help.
 *
 * PSEUDO CODE:
 * FUNCTION dearcharts_add_all_meta_boxes
 *   CALL add_meta_box(
 *     id: 'dearcharts_shortcodes',
 *     title: 'Chart Shortcodes',
 *     callback: 'dearcharts_shortcodes_meta_box_html',
 *     screen: 'dearcharts',
 *     context: 'side',
 *     priority: 'high'
 *   )
 * END FUNCTION
 */
function dearcharts_add_all_meta_boxes()
{
    // Shortcode Help
    add_meta_box(
        'dearcharts_shortcodes',
        'Chart Shortcodes',
        'dearcharts_shortcodes_meta_box_html',
        'dearcharts',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'dearcharts_add_all_meta_boxes');

/**
 * HTML for Shortcodes Meta Box.
 *
 * PSEUDO CODE:
 * FUNCTION dearcharts_shortcodes_meta_box_html(post_object)
 *   IF post_status is 'auto-draft' THEN
 *     PRINT message 'Please publish or save draft to get the shortcode.'
 *     RETURN
 *   END IF
 *   PRINT instructions 'Use this shortcode to embed this chart:'
 *   PRINT label 'Shortcode:' and input showing '[dearchart id="..."]'
 * END FUNCTION
 */
function dearcharts_shortcodes_meta_box_html($post)
{
    if ($post->post_status === 'auto-draft') {
        echo '<p>Please publish or save draft to get the shortcode.</p>';
        return;
    }
    ?>
        <p>Use this shortcode to embed this chart:</p>
        <p>
            <label>Shortcode:</label><br>
            <code>[dearchart id="<?php echo esc_attr($post->ID); ?>"]</code>
        </p>
        <?php
}
