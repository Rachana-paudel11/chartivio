<?php
/**
 * Shortcode Rendering & Frontend Assets
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render DearChart Shortcode
 * PSEUDOCODE: 
 * 1. Extract post ID from shortcode attributes.
 * 2. Retrieve chart data source and aesthetic settings from database.
 * 3. Generate a unique ID for the chart container to allow multiple charts on one page.
 * 4. Pass configuration to the centralized frontend JS initializer.
 */
function dearcharts_render_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'id' => '',
        'width' => '35%',
        'height' => '400px',
        'max_width' => '100%'
    ), $atts, 'dearchart');

    $post_id = intval($atts['id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'dearcharts') {
        return '';
    }

    // Only render for published charts. Allow rendering for previews when the current user can edit the post.
    $can_preview = (function_exists('is_preview') && is_preview() && current_user_can('edit_post', $post_id));
    if ($post->post_status !== 'publish' && !$can_preview) {
        return '';
    }

    // Retrieve Data
    $manual_data = get_post_meta($post_id, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post_id, '_dearcharts_csv_url', true);
    $active_source = get_post_meta($post_id, '_dearcharts_active_source', true) ?: ((!empty($csv_url)) ? 'csv' : 'manual');

    // Aesthetic Settings
    $chart_type = get_post_meta($post_id, '_dearcharts_type', true) ?: (get_post_meta($post_id, '_dearcharts_is_donut', true) === '1' ? 'doughnut' : 'pie');
    $legend_pos = get_post_meta($post_id, '_dearcharts_legend_pos', true) ?: 'top';
    $palette_key = get_post_meta($post_id, '_dearcharts_palette', true) ?: 'default';

    // Ensure a stable, unique ID per page render (avoids changing on each reload)
    static $dearcharts_instance_counter = 0;
    $dearcharts_instance_counter++;
    $unique_id = 'dearchart-' . $post_id . '-' . $dearcharts_instance_counter;

    // Enqueue assets only when shortcode is used
    wp_enqueue_script('dearcharts-frontend');

    // Prepare Config for JS
    $config = array(
        'id' => $unique_id,
        'type' => $chart_type,
        'legendPos' => $legend_pos,
        'palette' => $palette_key,
        'source' => $active_source,
        'csvUrl' => $csv_url,
        'manualData' => $manual_data
    );

    // Output Container
    $style = "position: relative; width: " . esc_attr($atts['width']) . "; max-width: " . esc_attr($atts['max_width']) . "; height: " . esc_attr($atts['height']) . "; margin: 0 auto;";
    $output = '<div class="dearchart-container" style="' . $style . '">';
    $output .= '<canvas id="' . esc_attr($unique_id) . '" style="width: 100%; height: 100%;"></canvas>';
    $output .= '</div>';

    // Inline Script to Init
    // Enqueue assets if not already (safeguard for AJAX or weird loading contexts)
    wp_enqueue_script('chartjs');
    $output .= '<script>jQuery(document).ready(function($) { if(typeof dearcharts_init_frontend === "function") { dearcharts_init_frontend(' . wp_json_encode($config) . '); } });</script>';

    return $output;
}
add_shortcode('dearchart', 'dearcharts_render_shortcode');

/**
 * Enqueue Frontend Assets
 */
function dearcharts_frontend_assets()
{
    // Register local Chart.js for on-demand loading
    wp_register_script('chartjs', plugins_url('../assets/js/chartjs/chart.umd.min.js', __FILE__), array(), '4.4.1', true);

    // Register plugin frontend logic, dependent on Chart.js
    wp_register_script('dearcharts-frontend', plugins_url('../assets/js/dearcharts.js', __FILE__), array('chartjs'), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'dearcharts_frontend_assets');

/**
 * Add CSS to hide post-metadata on Frontend Single View
 */
add_action('wp_head', function() {
    if (is_singular('dearcharts')) {
        echo '<style>.entry-meta, .byline, .cat-links, .post-author, .post-date { display: none !important; }</style>';
    }
});
