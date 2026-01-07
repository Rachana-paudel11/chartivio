<?php
/**
 * Plugin Name: DearCharts
 * Description: A custom post type for managing charts with a tabbed meta box interface.
 * Version: 1.0
 * Author: Rachana Paudel
 * Text Domain: dearcharts
 * Domain Path: /language
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
        register_post_type('dearcharts', $args);
    }
}
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
 * Premium Styling for Post List
 */
function dearcharts_admin_list_styles()
{
    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-dearcharts') {
        ?>
        <style>
            .wp-list-table th#dearcharts_shortcode {
                width: 300px;
            }

            .wp-list-table th#dearcharts_type {
                width: 120px;
            }

            .dc-shortcode-pill {
                display: flex;
                align-items: center;
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 4px 8px;
                max-width: fit-content;
                cursor: pointer;
                transition: all 0.2s;
            }

            .dc-shortcode-pill:hover {
                background: #e2e8f0;
                border-color: #cbd5e1;
            }

            .dc-shortcode-pill code {
                background: transparent !important;
                border: none !important;
                padding: 0 !important;
                color: #1e293b !important;
                font-weight: 600 !important;
                font-family: inherit !important;
                margin-right: 8px !important;
            }

            .dc-copy-icon {
                color: #64748b;
                font-size: 16px;
            }

            .dc-type-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 12px;
                background: #eff6ff;
                color: #2563eb;
                border: 1px solid #dbeafe;
            }

            .dc-type-badge .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                line-height: 16px;
            }

            .wp-list-table tr:hover {
                background-color: #f8fafc !important;
            }

            .wp-list-table .column-title strong a {
                color: #1e293b !important;
                font-size: 14px;
            }
        </style>
        <script>
            function dcCopyList(btn, text) {
                navigator.clipboard.writeText(text).then(function () {
                    const original = btn.innerHTML;
                    btn.innerHTML = '<span>Copied!</span>';
                    btn.style.background = '#dcfce7';
                    btn.style.borderColor = '#86efac';
                    setTimeout(() => {
                        btn.innerHTML = original;
                        btn.style.background = '';
                        btn.style.borderColor = '';
                    }, 2000);
                });
            }
        </script>
        <?php
    }
}
add_action('admin_head', 'dearcharts_admin_list_styles');

/**
 * Include Module Files
 */
require_once DEARCHARTS_PATH . 'includes/admin-settings.php';
require_once DEARCHARTS_PATH . 'includes/shortcodes.php';

/**
 * Add Custom Columns to Admin List
 */
add_filter('manage_edit-dearcharts_columns', 'dearcharts_add_admin_columns');
function dearcharts_add_admin_columns($columns)
{
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['dearcharts_shortcode'] = 'Shortcode';
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
            $sc = '[dearchart id="' . $post_id . '"]';
            echo '<div class="dc-shortcode-pill" onclick="dcCopyList(this, \'' . esc_js($sc) . '\')" title="Click to copy">';
            echo '<code>' . esc_html($sc) . '</code>';
            echo '<span class="dashicons dashicons-admin-page dc-copy-icon"></span>';
            echo '</div>';
            break;
        case 'dearcharts_type':
            $type = get_post_meta($post_id, '_dearcharts_type', true) ?: 'pie';
            $icon = 'dashicons-chart-pie';
            if ($type === 'bar')
                $icon = 'dashicons-chart-bar';
            if ($type === 'line')
                $icon = 'dashicons-chart-line';
            if ($type === 'doughnut')
                $icon = 'dashicons-chart-pie';

            echo '<div class="dc-type-badge">';
            echo '<span class="dashicons ' . esc_attr($icon) . '"></span> ';
            echo '<span>' . esc_html(ucfirst($type)) . '</span>';
            echo '</div>';
            break;
    }
}
