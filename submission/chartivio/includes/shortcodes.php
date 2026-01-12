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

    // Enqueue assets only when shortcode is used
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

    $output = '<div class="cvio-shortcode-wrapper" style="margin-bottom: 30px;">';
    $output .= '<div class="cvio-inner" style="' . $inner_style . '">';
    $output .= '<h3 class="cvio-title" style="margin: 0 0 15px 0; font-size: 1.25rem; font-weight: 600; color: #1e293b;">' . esc_html($post->post_title) . '</h3>';
    $output .= '<div class="cvio-container" style="' . $chart_container_style . '">';
    $output .= '<canvas id="' . esc_attr($unique_id) . '" style="width: 100%; height: 100%;"></canvas>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';

    // Inline Script to Init
    $output .= '<script>document.addEventListener("DOMContentLoaded", function() { if(typeof chartivio_init_frontend === "function") { chartivio_init_frontend(' . wp_json_encode($config) . '); } });</script>';

    return $output;
}
add_shortcode('chartivio', 'chartivio_render_shortcode');

/**
 * Enqueue Frontend Assets
 */
function chartivio_frontend_assets()
{
    // Register local Chart.js for on-demand loading
    wp_register_script('chartjs', plugins_url('../assets/js/chartjs/chart.umd.min.js', __FILE__), array(), '4.4.1', true);

    // Register plugin frontend logic, dependent on Chart.js
    wp_register_script('chartivio-frontend', plugins_url('../assets/js/chartivio.js', __FILE__), array('chartjs'), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'chartivio_frontend_assets');

/**
 * Add CSS to hide post-metadata on Frontend Single View
 */
add_action('wp_head', function () {
    if (is_singular('chartivio')) {
        echo '<style>.entry-meta, .byline, .cat-links, .post-author, .post-date { display: none !important; }</style>';
    }
});


