<?php
/**
 * Plugin Name: Chartivio
 * Description: A plugin to create charts and bars for data visualization.
 * Author: Rachana Paudel
 * Plugin URI: https://wordpress.org/plugins/chartivio/
 * Author URI: https://profiles.wordpress.org/rachanapaudel26/
 * Version: 1.0.1
 * Text Domain: chartivio
 * Domain Path: /language
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Constants
define('CHARTIVIO_PATH', plugin_dir_path(__FILE__));
define('CHARTIVIO_URL', plugin_dir_url(__FILE__));

/**
 * Register Custom Post Type 'chartivio'
 */
function chartivio_register_cpt()
{
    $args = array(
        'labels' => array(
            'name' => 'Chartivio',
            'singular_name' => 'Chartivio Chart',
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

    if (function_exists('register_post_type')) {
        register_post_type('chartivio', $args);
    }
}
add_action('init', 'chartivio_register_cpt');

/**
 * Register Submenu Page
 */
add_action('admin_menu', 'chartivio_register_how_to_use_page');
function chartivio_register_how_to_use_page()
{
    add_submenu_page(
        'edit.php?post_type=chartivio', // Parent slug (CPT URL)
        'How to Use',
        'How to Use',
        'manage_options',
        'chartivio-how-to-use',
        'chartivio_render_how_to_use_page'
    );
}

/**
 * Force 1-column layout for chartivio admin screen
 */
add_filter('get_user_option_screen_layout_chartivio', function ($columns) {
    return 1;
});

/**
 * Hide Screen Options tab for chartivio admin screen
 */
add_filter('screen_options_show_screen', function ($show_screen, $screen) {
    if ($screen->post_type === 'chartivio') {
        return false;
    }
    return $show_screen;
}, 10, 2);

/**
 * Enqueue Assets for Post List
 */
function chartivio_admin_list_assets($hook)
{
    $screen = get_current_screen();
    if ($screen && ($screen->id === 'edit-chartivio' || $screen->id === 'chartivio_page_chartivio-how-to-use')) {
        // Enqueue Google Fonts
        wp_enqueue_style('chartivio-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', array(), '1.0.1');

        wp_enqueue_style('chartivio-admin-style', plugins_url('assets/css/admin-style.css', __FILE__), array(), '1.0.1');
        wp_enqueue_script('chartivio-admin-list', plugins_url('assets/js/admin-list.js', __FILE__), array('jquery'), '1.0.1', true);
        wp_localize_script('chartivio-admin-list', 'cvio_admin_vars', array(
            'how_to_use_url' => esc_url(admin_url('edit.php?post_type=chartivio&page=chartivio-how-to-use'))
        ));
    }
}
add_action('admin_enqueue_scripts', 'chartivio_admin_list_assets');

/**
 * Include Module Files
 */
require_once CHARTIVIO_PATH . 'includes/admin-settings.php';
require_once CHARTIVIO_PATH . 'includes/shortcodes.php';
require_once CHARTIVIO_PATH . 'includes/how-to-use.php';

/**
 * Add Custom Columns to Admin List
 */
add_filter('manage_edit-chartivio_columns', 'chartivio_add_admin_columns');
function chartivio_add_admin_columns($columns)
{
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['chartivio_shortcode'] = 'Shortcode';
    $new_columns['chartivio_type'] = 'Type';
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

/**
 * Populate Custom Columns
 */
add_action('manage_chartivio_posts_custom_column', 'chartivio_populate_admin_columns', 10, 2);
function chartivio_populate_admin_columns($column, $post_id)
{
    switch ($column) {
        case 'chartivio_shortcode':
            $sc = '[chartivio id="' . $post_id . '"]';
            echo '<div class="cvio-shortcode-pill" onclick="cvioCopyList(this, \'' . esc_js($sc) . '\')" title="Click to copy">';
            echo '<code>' . esc_html($sc) . '</code>';
            echo '<span class="dashicons dashicons-admin-page cvio-copy-icon"></span>';
            echo '</div>';
            break;
        case 'chartivio_type':
            $type = get_post_meta($post_id, '_chartivio_type', true) ?: 'pie';
            $icon = 'dashicons-chart-pie';
            if ($type === 'bar')
                $icon = 'dashicons-chart-bar';
            if ($type === 'line')
                $icon = 'dashicons-chart-line';
            if ($type === 'doughnut')
                $icon = 'dashicons-chart-pie';

            echo '<div class="cvio-type-badge">';
            echo '<span class="dashicons ' . esc_attr($icon) . '"></span> ';
            echo '<span>' . esc_html(ucfirst($type)) . '</span>';
            echo '</div>';
            break;
    }
}

