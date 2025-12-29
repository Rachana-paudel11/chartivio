<?php
/**
 * Shortcode Rendering & Frontend Assets
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render DearChart Shortcode
 */
function dearcharts_render_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts, 'dearchart');

    $post_id = intval($atts['id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'dearcharts')
        return '';

    // Retrieve Data
    $manual_data = get_post_meta($post_id, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post_id, '_dearcharts_csv_url', true);
    $active_source = get_post_meta($post_id, '_dearcharts_active_source', true) ?: ((!empty($csv_url)) ? 'csv' : 'manual');

    // Aesthetic Settings
    $chart_type = get_post_meta($post_id, '_dearcharts_type', true) ?: (get_post_meta($post_id, '_dearcharts_is_donut', true) === '1' ? 'doughnut' : 'pie');
    $legend_pos = get_post_meta($post_id, '_dearcharts_legend_pos', true) ?: 'top';
    $palette_key = get_post_meta($post_id, '_dearcharts_palette', true) ?: 'default';

    $unique_id = 'dearchart-' . $post_id . '-' . uniqid();

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
    $output = '<div class="dearchart-container" style="position: relative; width: 100%; max-width: 600px; height: 400px; margin: 0 auto;">';
    $output .= '<canvas id="' . esc_attr($unique_id) . '"></canvas>';
    $output .= '</div>';

    // Inline Script to Init
    $output .= '<script>jQuery(document).ready(function($) { if(typeof dearcharts_init_frontend === "function") { dearcharts_init_frontend(' . json_encode($config) . '); } });</script>';

    return $output;
}
add_shortcode('dearchart', 'dearcharts_render_shortcode');

/**
 * Enqueue Frontend Assets
 */
function dearcharts_frontend_assets()
{
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'dearcharts_frontend_assets');

/**
 * Frontend Shared JS Logic
 */
function dearcharts_footer_js()
{
    ?>
    <script>
        var dc_palettes = {
            'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
            'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'],
            'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'],
            'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'],
            'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'],
            'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F']
        };

        function dearcharts_init_frontend(config) {
            var canvas = document.getElementById(config.id);
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            var palette = dc_palettes[config.palette] || dc_palettes['default'];

            var drawChart = (l, ds) => {
                ds.forEach((set, i) => {
                    let colors = (ds.length > 1) ? palette[i % palette.length] : l.map((_, j) => palette[j % palette.length]);
                    if (config.type === 'bar' || config.type === 'line') {
                        set.backgroundColor = (ds.length > 1) ? palette[i % palette.length] : palette;
                        set.borderColor = (ds.length > 1) ? palette[i % palette.length] : palette;
                    } else {
                        set.backgroundColor = colors;
                        set.borderColor = colors;
                    }
                    set.borderWidth = (config.type === 'line') ? 2 : 1;
                    set.fill = (config.type === 'line') ? false : true;
                });

                new Chart(ctx, {
                    type: config.type,
                    data: { labels: l, datasets: ds },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: (config.type === 'bar' || config.type === 'line') ? { y: { beginAtZero: true } } : {},
                        plugins: { legend: { display: config.legendPos !== 'none', position: config.legendPos } }
                    }
                });
            };

            if (config.source === 'csv' && config.csvUrl) {
                fetch(config.csvUrl).then(res => res.text()).then(text => {
                    const lines = text.trim().split(/\r\n|\n/);
                    let labels = [], datasets = [];
                    const headParts = lines[0].split(',');
                    for (let i = 1; i < headParts.length; i++) datasets.push({ label: headParts[i].trim(), data: [] });
                    for (let r = 1; r < lines.length; r++) {
                        const rowParts = lines[r].split(',');
                        labels.push(rowParts[0].trim());
                        for (let c = 0; c < datasets.length; c++) datasets[c].data.push(parseFloat(rowParts[c + 1]) || 0);
                    }
                    drawChart(labels, datasets);
                });
            } else {
                let labels = [], datasets = [];
                let raw = config.manualData;
                if (raw) {
                    // Convert object to sorted array if necessary
                    let rows = Array.isArray(raw) ? raw : Object.keys(raw).sort((a, b) => a - b).map(k => raw[k]);

                    if (rows.length > 0) {
                        // Backward compatibility check
                        if (rows[1] && rows[1].label !== undefined) {
                            datasets.push({ label: 'Data', data: [] });
                            rows.forEach((row, idx) => {
                                if (idx > 0) { // Skip header row if it exists in data-only format
                                    labels.push(row.label);
                                    datasets[0].data.push(parseFloat(row.value) || 0);
                                }
                            });
                        } else {
                            const headers = rows[0];
                            for (let i = 1; i < headers.length; i++) datasets.push({ label: headers[i], data: [] });
                            for (let r = 1; r < rows.length; r++) {
                                labels.push(rows[r][0]);
                                for (let c = 0; c < datasets.length; c++) datasets[c].data.push(parseFloat(rows[r][c + 1]) || 0);
                            }
                        }
                    }
                }
                drawChart(labels, datasets);
            }
        }
    </script>
    <?php
}
add_action('wp_footer', 'dearcharts_footer_js');
