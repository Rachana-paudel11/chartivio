<?php
/**
 * Shortcodes - Frontend Chart Rendering
 * 
 * Handles all frontend shortcode functionality and chart rendering logic.
 */

// PSEUDO CODE:
// IF ABSPATH is not defined THEN
//     EXIT script to prevent direct access
// END IF
if (!defined('ABSPATH')) {
    exit;
}

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

    // ENQUEUE Chart.js library (ensures it is loaded)
    wp_enqueue_script('chartjs');

    // PSEUDO CODE:
    // RETRIEVE saved meta data for this chart:
    // - Manual Data (Repeater rows)
    // - CSV URL
    // - Chart Type (pie/doughnut/bar/horizontalBar)
    // - Legend Position (top/bottom/left/right)
    // - Color Palette (key like 'ocean', 'sunset')
    // - Custom Colors (comma-separated hex codes)
    $manual_data = get_post_meta($post_id, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post_id, '_dearcharts_csv_url', true);
    $chart_type = get_post_meta($post_id, '_dearcharts_type', true);
    $legend_pos = get_post_meta($post_id, '_dearcharts_legend_pos', true);
    $palette = get_post_meta($post_id, '_dearcharts_palette', true);
    $custom_colors = get_post_meta($post_id, '_dearcharts_colors', true);

    // SET defaults
    if (empty($palette))
        $palette = 'default';
    if (empty($legend_pos))
        $legend_pos = 'top';
    if (empty($chart_type))
        $chart_type = 'pie';

    // DETERMINE active data source:
    // IF CSV URL is not empty THEN use 'csv'
    // ELSE use 'manual'
    $active_source = (!empty($csv_url)) ? 'csv' : 'manual';

    // GENERATE unique ID for the canvas element
    $unique_id = 'dearchart-' . $post_id . '-' . uniqid();

    // PREPARE configuration object for JavaScript
    $config = array(
        'id' => $unique_id,
        'type' => $chart_type,
        'legendPos' => $legend_pos,
        'palette' => $palette,
        'customColors' => $custom_colors,
        'chartTitle' => ($post->post_title ? $post->post_title : 'Dataset'),
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

    // ADD inline JavaScript to initialize this specific chart
    $output .= '<script>';
    $output .= 'jQuery(document).ready(function($) {';
    $output .= '  if(typeof initDearChart === "function") {';
    $output .= '    initDearChart(' . json_encode($config) . ');';
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
        (function ($) {
            // PSEUDO CODE:
            // DEFINE color palettes (default, pastel, ocean, etc.)
            var palettes = {
                'default': ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED'],
                'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa', '#f0e68c'],
                'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#e0f2f1', '#004d40'],
                'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50', '#cd5c5c'],
                'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff', '#ff1493'],
                'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F', '#66CDAA']
            };

            // FUNCTION generateColors(basePalette, count)
            // IF count <= basePalette length THEN return basePalette slice
            // ELSE generate extra colors using HSL rotation
            function generateColors(basePalette, count) {
                var colors = [].concat(basePalette);
                if (count <= colors.length) return colors.slice(0, count);

                var needed = count - colors.length;
                for (var i = 0; i < needed; i++) {
                    var hue = (i * 137.5 + 200) % 360;
                    var color = 'hsl(' + hue + ', 65%, 60%)';
                    colors.push(color);
                }
                return colors;
            }

            // FUNCTION initDearChart(config) exposed globally
            window.initDearChart = function (config) {
                var ctx = document.getElementById(config.id);
                if (!ctx) return;

                // CALLBACK onDataReady(labels, values)
                // 1. GET base colors from config.customColors or palette
                // 2. GENERATE final colors based on value count
                // 3. CREATE new Chart instance with data and options
                var onDataReady = function (labels, values) {
                    var finalColors;

                    // Check if custom colors are provided
                    if (config.customColors && config.customColors.trim()) {
                        // Parse custom colors
                        finalColors = config.customColors.split(',').map(function (color) {
                            return color.trim();
                        });
                        // Repeat colors if not enough
                        while (finalColors.length < values.length) {
                            finalColors = finalColors.concat(finalColors);
                        }
                        finalColors = finalColors.slice(0, values.length);
                    } else {
                        // Use palette
                        var baseColors = palettes[config.palette] || palettes['default'];
                        finalColors = generateColors(baseColors, values.length);
                    }

                    var chartOptions = {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: config.legendPos
                            }
                        }
                    };

                    // Add scales for bar charts
                    var chartType = config.type;
                    if (chartType === 'bar' || chartType === 'horizontalBar') {
                        chartOptions.scales = {
                            y: {
                                beginAtZero: true
                            }
                        };

                        // For horizontal bar, use indexAxis
                        if (chartType === 'horizontalBar') {
                            chartOptions.indexAxis = 'y';
                            chartType = 'bar'; // Chart.js uses 'bar' with indexAxis
                        }
                    }

                    new Chart(ctx, {
                        type: chartType,
                        data: {
                            labels: labels,
                            datasets: [{
                                label: config.chartTitle,
                                data: values,
                                backgroundColor: finalColors,
                                hoverOffset: 4
                            }]
                        },
                        options: chartOptions
                    });
                };

                // IF source is 'csv' AND csvUrl is present THEN
                //     FETCH CSV text
                //     PARSE CSV (split by line, then comma)
                //     CALL onDataReady
                // ELSE IF manualData exists THEN
                //     PARSE manualData array
                //     CALL onDataReady
                if (config.source === 'csv' && config.csvUrl) {
                    fetch(config.csvUrl)
                        .then(res => res.text())
                        .then(text => {
                            var lines = text.split(/\r\n|\n/);
                            var labels = [], data = [];
                            lines.forEach(line => {
                                var parts = line.split(',');
                                if (parts.length >= 2) {
                                    var val = parseFloat(parts[1]);
                                    if (!isNaN(val)) {
                                        labels.push(parts[0].trim());
                                        data.push(val);
                                    }
                                }
                            });
                            onDataReady(labels, data);
                        })
                        .catch(err => console.error("DearCharts CSV Error", err));
                } else if (config.manualData && config.manualData.length > 0) {
                    var labels = [], data = [];
                    config.manualData.forEach(row => {
                        var val = parseFloat(row.value);
                        if (!isNaN(val)) {
                            labels.push(row.label);
                            data.push(val);
                        }
                    });
                    onDataReady(labels, data);
                }
            };
        })(jQuery);
    </script>
    <?php
}
// HOOK to 'wp_footer'
add_action('wp_footer', 'dearcharts_footer_js');
