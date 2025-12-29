<?php
/**
 * Shortcodes - Frontend Chart Rendering
 * 
 * Handles all frontend shortcode functionality and chart rendering logic.
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

    if (!$post || $post->post_type !== 'dearcharts') {
        return '';
    }

    wp_enqueue_script('chartjs');

    $manual_data = get_post_meta($post_id, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post_id, '_dearcharts_csv_url', true);
    $active_source = get_post_meta($post_id, '_dearcharts_active_source', true);
    $chart_type = get_post_meta($post_id, '_dearcharts_type', true);
    $legend_pos = get_post_meta($post_id, '_dearcharts_legend_pos', true);
    $palette = get_post_meta($post_id, '_dearcharts_palette', true);
    $custom_colors = get_post_meta($post_id, '_dearcharts_colors', true);

    if (empty($active_source))
        $active_source = 'manual';
    if (empty($palette))
        $palette = 'default';
    if (empty($legend_pos))
        $legend_pos = 'top';
    if (empty($chart_type))
        $chart_type = 'pie';

    $unique_id = 'dearchart-' . $post_id . '-' . uniqid();

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

    $output = '<div class="dearchart-container" style="position: relative; width: 100%; max-width: 800px; height: 450px; margin: 0 auto;">';
    $output .= '<canvas id="' . esc_attr($unique_id) . '"></canvas>';
    $output .= '</div>';

    $output .= '<script>';
    $output .= 'jQuery(document).ready(function($) {';
    $output .= '  if(typeof initDearChart === "function") {';
    $output .= '    initDearChart(' . json_encode($config) . ');';
    $output .= '  }';
    $output .= '});';
    $output .= '</script>';

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
        (function ($) {
            var palettes = {
                'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
                'pastel': ['#93c5fd', '#6ee7b7', '#fcd34d', '#fca5a5', '#c4b5fd', '#fbcfe8'],
                'ocean': ['#1e40af', '#1d4ed8', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'],
                'sunset': ['#991b1b', '#b91c1c', '#dc2626', '#ef4444', '#f87171', '#fca5a5'],
                'neon': ['#f0abfc', '#818cf8', '#2dd4bf', '#a3e635', '#fde047', '#fb923c']
            };

            window.initDearChart = function (config) {
                var ctx = document.getElementById(config.id);
                if (!ctx) return;

                var onDataReady = function (chartData) {
                    if (!chartData.labels || chartData.labels.length === 0) return;

                    var palette = palettes[config.palette] || palettes['default'];

                    var datasets = chartData.datasets.map(function (ds, i) {
                        var dsColor;
                        var filler = false;

                        if (chartData.datasets.length > 1 || ['line', 'radar'].includes(config.type)) {
                            dsColor = palette[i % palette.length];
                            filler = config.type === 'radar' ? true : false;
                        } else {
                            var colors = [];
                            for (var j = 0; j < ds.data.length; j++) colors.push(palette[j % palette.length]);
                            dsColor = colors;
                        }

                        return {
                            label: ds.label,
                            data: ds.data,
                            backgroundColor: dsColor,
                            borderColor: dsColor,
                            borderWidth: (config.type === 'line' || config.type === 'radar') ? 3 : 1,
                            hoverOffset: 4,
                            fill: filler,
                            tension: 0.3,
                            pointRadius: (config.type === 'line' || config.type === 'radar') ? 4 : 0
                        };
                    });

                    var chartConfig = {
                        type: config.type === 'horizontalBar' ? 'bar' : config.type,
                        data: {
                            labels: chartData.labels,
                            datasets: datasets
                        },
                        options: {
                            indexAxis: config.type === 'horizontalBar' ? 'y' : 'x',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: config.legendPos !== 'none',
                                    position: config.legendPos === 'none' ? 'top' : config.legendPos
                                }
                            },
                            scales: (['bar', 'horizontalBar', 'line'].includes(config.type)) ? {
                                y: { beginAtZero: true }
                            } : {}
                        }
                    };

                    new Chart(ctx, chartConfig);
                };

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
                            onDataReady({
                                labels: labels,
                                datasets: [{ label: config.chartTitle, data: data }]
                            });
                        });
                } else if (config.manualData && config.manualData.length > 0) {
                    var labels = [];
                    var datasets = [];
                    var headers = config.manualData[0];

                    for (var i = 1; i < headers.length; i++) {
                        datasets.push({ label: headers[i] || 'Series ' + i, data: [] });
                    }

                    for (var r = 1; r < config.manualData.length; r++) {
                        var row = config.manualData[r];
                        labels.push(row[0] || 'Unnamed');
                        for (var c = 0; c < datasets.length; c++) {
                            var val = parseFloat(row[c + 1]);
                            datasets[c].data.push(isNaN(val) ? 0 : val);
                        }
                    }

                    onDataReady({ labels: labels, datasets: datasets });
                }
            };
        })(jQuery);
    </script>
    <?php
}
add_action('wp_footer', 'dearcharts_footer_js');
