<?php
/**
 * Shortcode Rendering & Frontend Assets
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render chartivio Shortcode
 * PSEUDOCODE: 
 * 1. Extract post ID from shortcode attributes.
 * 2. Retrieve chart data source and aesthetic settings from database.
 * 3. Generate a unique ID for the chart container to allow multiple charts on one page.
 * 4. Pass configuration to the centralized frontend JS initializer.
 */
function chartivio_render_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'id' => '',
        'width' => '100%',
        'height' => '400px',
        'max_width' => '100%'
    ), $atts, 'chartivio');

    $post_id = intval($atts['id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'chartivio') {
        return '';
    }

    // Only render for published charts. Allow rendering for previews when the current user can edit the post.
    $can_preview = (function_exists('is_preview') && is_preview() && current_user_can('edit_post', $post_id));
    if ($post->post_status !== 'publish' && !$can_preview) {
        return '';
    }

    // Retrieve Data
    $manual_data = get_post_meta($post_id, '_chartivio_manual_data', true);
    $csv_url = get_post_meta($post_id, '_chartivio_csv_url', true);
    $active_source = get_post_meta($post_id, '_chartivio_active_source', true) ?: ((!empty($csv_url)) ? 'csv' : 'manual');

    // Aesthetic Settings
    $chart_type = get_post_meta($post_id, '_chartivio_type', true) ?: (get_post_meta($post_id, '_chartivio_is_donut', true) === '1' ? 'doughnut' : 'pie');
    $legend_pos = get_post_meta($post_id, '_chartivio_legend_pos', true) ?: 'top';
    $palette_key = get_post_meta($post_id, '_chartivio_palette', true) ?: 'default';
    $xaxis_label = get_post_meta($post_id, '_chartivio_xaxis_label', true);
    $yaxis_label = get_post_meta($post_id, '_chartivio_yaxis_label', true);

    // Ensure a stable, unique ID per page render (avoids changing on each reload)
    static $chartivio_instance_counter = 0;
    $chartivio_instance_counter++;
    $unique_id = 'chartivio-' . $post_id . '-' . $chartivio_instance_counter;

    // Enqueue Chart.js and plugin frontend scripts
    wp_enqueue_script('chartjs');
    wp_enqueue_script('chartivio-frontend');

    // Prepare Config for JS
    $config = array(
        'id' => $unique_id,
        'type' => $chart_type,
        'legendPos' => $legend_pos,
        'palette' => $palette_key,
        'xaxisLabel' => $xaxis_label,
        'yaxisLabel' => $yaxis_label,
        'source' => $active_source,
        'csvUrl' => $csv_url,
        'manualData' => $manual_data
    );

    // Output Container
    $inner_style = "width: " . esc_attr($atts['width']) . "; max-width: " . esc_attr($atts['max_width']) . "; margin: 0 auto; text-align: left;";
    $chart_container_style = "position: relative; height: " . esc_attr($atts['height']) . "; width: 100%;";

    $output = '<div class="chartivio-shortcode-wrapper" style="margin-bottom: 30px;">';
    $output .= '<div class="chartivio-inner" style="' . $inner_style . '">';
    $output .= '<h3 class="chartivio-title" style="margin: 0 0 15px 0; font-size: 1.25rem; font-weight: 600; color: #1e293b;">' . esc_html($post->post_title) . '</h3>';
    $output .= '<div class="chartivio-container" style="' . $chart_container_style . '">';
    $output .= '<canvas id="' . esc_attr($unique_id) . '" data-config="' . esc_attr(wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) . '" style="display: block; max-width: 100%; max-height: 100%;"></canvas>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';

    /**
     * Dynamic Chart Initialization via wp_add_inline_script()
     * 
     * Per WordPress.org Plugin Guidelines:
     * https://developer.wordpress.org/plugins/javascript/
     * 
     * Using wp_add_inline_script() to handle inline JavaScript for dynamic,
     * content-specific data (unique canvas ID and chart configuration per instance).
     * This is the proper WordPress-compliant approach recommended by the plugin review.
     */
    wp_add_inline_script(
        'chartivio-frontend',
        'chartivio_init_chart(' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', ' . wp_json_encode($unique_id) . ');'
    );

    return $output;
}
add_shortcode('chartivio', 'chartivio_render_shortcode');

/**
 * Enqueue Frontend Assets
 */
function chartivio_frontend_assets()
{
    // Register Chart.js v4.5.1 from local files
    wp_register_script('chartjs', CHARTIVIO_URL . 'assets/js/chartjs/chart.umd.min.js', array(), '4.5.1', true);

    // Register plugin frontend logic, dependent on Chart.js
    wp_register_script('chartivio-frontend', CHARTIVIO_URL . 'assets/js/chartivio.js', array('chartjs'), '1.0.1', true);

    // Register Frontend CSS
    wp_register_style('chartivio-frontend-style', CHARTIVIO_URL . 'assets/css/frontend-style.css', array(), '1.0.0');

    // Always enqueue CSS so it's available whenever shortcode is used
    wp_enqueue_style('chartivio-frontend-style');
}
add_action('wp_enqueue_scripts', 'chartivio_frontend_assets');


