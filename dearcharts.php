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
 * Render DearChart Shortcode
 */
function dearcharts_render_shortcode($atts)
{
    // PSEUDO CODE:
    // EXTRACT 'id' from shortcode attributes, default to empty string
    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts, 'dearchart');

    // GET post object by ID
    $post_id = intval($atts['id']);
    $post = get_post($post_id);

    // IF post does not exist OR post type is not 'dearcharts' THEN
    //     RETURN empty string (do nothing)
    // END IF
    if (!$post || $post->post_type !== 'dearcharts') {
        return '';
    }

    // Retrieve Data
    $manual_data = get_post_meta($post_id, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post_id, '_dearcharts_csv_url', true);
    $type = get_post_meta($post_id, '_dearcharts_type', true) ?: 'pie';

    // Prioritize CSV if available
    $active_source = get_post_meta($post_id, '_dearcharts_active_source', true) ?: ((!empty($csv_url)) ? 'csv' : 'manual');

    // GENERATE unique ID for the canvas element
    $unique_id = 'dearchart-' . $post_id . '-' . uniqid();

    // PREPARE configuration object for JavaScript
    $config = array(
        'id' => $unique_id,
        'type' => $type,
        'source' => $active_source,
        'csvUrl' => $csv_url,
        'manualData' => $manual_data
    );

    // CONSTRUCT HTML output:
    // - Wrapper div
    // - Canvas element with unique ID
    $output = '<div class="dearchart-container" style="position: relative; width: 100%; max-width: 600px; height: 400px; margin: 0 auto;">';
    $output .= '<canvas id="' . esc_attr($unique_id) . '"></canvas>';
    $output .= '</div>';

    // Enrollment logic
    $output .= '<script>';
    $output .= 'jQuery(document).ready(function($) {';
    $output .= '  if(typeof dearcharts_render_chart_js === "function") {';
    $output .= '    // Check if we need to fetch CSV or just render
                    if ("' . $active_source . '" === "csv" && "' . esc_url($csv_url) . '") {
                        fetch("' . esc_url($csv_url) . '").then(res => res.text()).then(text => {
                            const lines = text.trim().split(/\\r\\n|\\n/);
                            let labels = [], datasets = [{ label: "Data", data: [] }];
                            const headParts = lines[0].split(",");
                            if (headParts.length > 1) {
                                datasets = [];
                                for (let i = 1; i < headParts.length; i++) datasets.push({ label: headParts[i].trim(), data: [] });
                            }
                            for (let r = 1; r < lines.length; r++) {
                                const rowParts = lines[r].split(",");
                                if (rowParts.length < 1) continue;
                                labels.push(rowParts[0].trim());
                                for (let c = 0; c < datasets.length; c++) datasets[c].data.push(parseFloat(rowParts[c + 1]) || 0);
                            }
                            dearcharts_render_chart_js_frontend("' . $unique_id . '", labels, datasets, "' . $type . '");
                        });
                    } else {
                        // Manual Parsing for Frontend
                        const rawData = ' . json_encode($manual_data) . ';
                        let labels = [], datasets = [];
                        if (rawData && rawData.length > 0) {
                             const headers = rawData[0];
                             for (let i = 1; i < headers.length; i++) datasets.push({ label: headers[i], data: [] });
                             for (let r = 1; r < rawData.length; r++) {
                                 labels.push(rawData[r][0]);
                                 for (let c = 0; c < datasets.length; c++) datasets[c].data.push(parseFloat(rawData[r][c+1]) || 0);
                             }
                        }
                        dearcharts_render_chart_js_frontend("' . $unique_id . '", labels, datasets, "' . $type . '");
                    }
    ';
    $output .= '  }';
    $output .= '});';
    $output .= '</script>';

    // RETURN the HTML output
    return $output;
}
// REGISTER shortcode 'dearchart'
add_shortcode('dearchart', 'dearcharts_render_shortcode');

/**
 * Enqueue Frontend Assets
 */
function dearcharts_frontend_assets()
{
    // PSEUDO CODE:
    // ENQUEUE Chart.js from CDN
    // ENQUEUE jQuery
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
    wp_enqueue_script('jquery');
}
// HOOK to 'wp_enqueue_scripts'
add_action('wp_enqueue_scripts', 'dearcharts_frontend_assets');

/**
 * Frontend Shared JS Logic
 */
function dearcharts_footer_js()
{
    ?>
    <script>
        function dearcharts_render_chart_js_frontend(canvasId, l, d, type) {
            var ctx = document.getElementById(canvasId).getContext('2d');
            const palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
            d.forEach((set, i) => {
                let colors = (d.length > 1) ? palette[i % palette.length] : l.map((_, j) => palette[j % palette.length]);
                set.backgroundColor = colors;
                set.borderColor = colors;
                set.borderWidth = 1;
                set.tension = 0.3;
            });
            new Chart(ctx, {
                type: type === 'horizontalBar' ? 'bar' : type,
                data: { labels: l, datasets: d },
                options: {
                    indexAxis: type === 'horizontalBar' ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'top' } },
                    scales: (['bar', 'horizontalBar', 'line'].includes(type)) ? { y: { beginAtZero: true } } : {}
                }
            });
        }
    </script>
    <?php
}
// HOOK to 'wp_footer'
add_action('wp_footer', 'dearcharts_footer_js');

/**
 * Enqueue Admin Scripts for DearCharts.
 */
function dearcharts_admin_scripts($hook)
{
    global $post;
    // PSEUDO CODE:
    // CHECK if current page is post-new.php or post.php
    // CHECK if post type is 'dearcharts'
    // ENQUEUE Chart.js
    // ENQUEUE WordPress media scripts (for CSV upload)
    if ($hook == 'post-new.php' || $hook == 'post.php') {
        if ($post && 'dearcharts' === $post->post_type) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_scripts');

