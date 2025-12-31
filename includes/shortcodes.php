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

    // Server-side CSV Parsing
    if ($active_source === 'csv' && !empty($csv_url)) {
        $response = wp_remote_get($csv_url);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $csv_body = wp_remote_retrieve_body($response);
            
            // Remove BOM if present to prevent header issues
            $csv_body = preg_replace('/^\xEF\xBB\xBF/', '', $csv_body);

            // Parse CSV robustly using a temporary stream
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $csv_body);
            rewind($stream);
            
            $parsed_rows = array();
            while (($row = fgetcsv($stream)) !== false) {
                // Filter out completely empty rows
                if (array_filter($row)) {
                    $parsed_rows[] = $row;
                }
            }
            fclose($stream);

            if (!empty($parsed_rows)) {
                $manual_data = $parsed_rows;
                $active_source = 'manual'; // Override source so JS uses the pre-parsed data
            }
        }
    }

    // Aesthetic Settings
    $chart_type = get_post_meta($post_id, '_dearcharts_type', true) ?: (get_post_meta($post_id, '_dearcharts_is_donut', true) === '1' ? 'doughnut' : 'pie');
    $legend_pos = get_post_meta($post_id, '_dearcharts_legend_pos', true) ?: 'top';
    $palette_key = get_post_meta($post_id, '_dearcharts_palette', true) ?: 'default';

    // Ensure a stable, unique ID per page render (avoids changing on each reload)
    static $dearcharts_instance_counter = 0;
    $dearcharts_instance_counter++;
    $unique_id = 'dearchart-' . $post_id . '-' . $dearcharts_instance_counter;

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
    $output .= '<script>document.addEventListener("DOMContentLoaded", function() { if(typeof dearcharts_init_frontend === "function") { dearcharts_init_frontend(' . wp_json_encode($config) . '); } });</script>';

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

        function dc_parse_csv(str) {
            var arr = [];
            var quote = false;
            for (var row = 0, col = 0, c = 0; c < str.length; c++) {
                var cc = str[c], nc = str[c+1];
                arr[row] = arr[row] || [];
                arr[row][col] = arr[row][col] || '';
                if (cc == '"' && quote && nc == '"') { arr[row][col] += cc; ++c; continue; }
                if (cc == '"') { quote = !quote; continue; }
                if (cc == ',' && !quote) { ++col; continue; }
                if (cc == '\r' && nc == '\n' && !quote) { ++row; col = 0; ++c; continue; }
                if (cc == '\n' && !quote) { ++row; col = 0; continue; }
                if (cc == '\r' && !quote) { ++row; col = 0; continue; }
                arr[row][col] += cc;
            }
            return arr;
        }

        /**
         * PSEUDOCODE: dearcharts_init_frontend
         * 1. Get the 2D drawing context of the target canvas.
         * 2. Select the color palette based on user configuration.
         * 3. Define a helper function 'drawChart' to abstract Chart.js instantiation.
         * 4. If source is CSV: Fetch data via AJAX, parse CSV rows, and map columns to Chart.js datasets.
         * 5. If source is Manual: Parse stored nested array/object and map to Chart.js datasets.
         * 6. Finalize: Call Chart.js constructor with mapped labels and datasets.
         */
        function dearcharts_init_frontend(config) {
            var canvas = document.getElementById(config.id);
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            var palette = dc_palettes[config.palette] || dc_palettes['default'];

            var drawChart = (l, ds) => {
                let realType = config.type;
                let indexAxis = 'x';

                if (realType === 'horizontalBar') {
                    realType = 'bar';
                    indexAxis = 'y';
                }

                // PSEUDOCODE: Assign colors from palette to each dataset or each data point.
                ds.forEach((set, i) => {
                    const colorArray = l.map((_, j) => palette[j % palette.length]);
                    const singleColor = palette[i % palette.length];

                    if (realType === 'pie' || realType === 'doughnut') {
                        set.backgroundColor = colorArray;
                        set.borderColor = '#ffffff';
                        set.borderWidth = 2;
                    } else if (realType === 'bar') {
                        if (ds.length > 1) {
                            set.backgroundColor = singleColor;
                            set.borderColor = singleColor;
                        } else {
                            set.backgroundColor = colorArray;
                            set.borderColor = colorArray;
                        }
                        set.borderWidth = 1;
                    } else if (realType === 'line') {
                        set.backgroundColor = singleColor;
                        set.borderColor = singleColor;
                        set.borderWidth = 2;
                        set.fill = false;
                        set.pointBackgroundColor = '#fff';
                        set.pointBorderColor = singleColor;
                    }
                });

                new Chart(ctx, {
                    type: realType,
                    data: { labels: l, datasets: ds },
                    options: {
                        indexAxis: indexAxis,
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: (realType === 'bar' || realType === 'line') ? { y: { beginAtZero: true } } : {},
                        plugins: { legend: { display: config.legendPos !== 'none', position: config.legendPos } }
                    }
                });
            };

            if (config.source === 'csv' && config.csvUrl) {
                // PSEUDOCODE: Fetch raw CSV text from the stored URL.
                fetch(config.csvUrl).then(res => res.text()).then(text => {
                    const rows = dc_parse_csv(text.trim());
                    let labels = [], datasets = [];
                    if (rows.length < 2) return;
                    const headParts = rows[0];
                    // PSEUDOCODE: Identify multiple datasets (columns) based on the first row header.
                    for (let i = 1; i < headParts.length; i++) datasets.push({ label: headParts[i].trim(), data: [] });
                    // PSEUDOCODE: Map subsequent rows to labels (Col 1) and data points (Col 2+).
                    for (let r = 1; r < rows.length; r++) {
                        const rowParts = rows[r];
                        if (rowParts.length < 2) continue;
                        labels.push(rowParts[0].trim());
                        for (let c = 0; c < datasets.length; c++) {
                            let val = parseFloat((rowParts[c + 1] || '').replace(/,/g, ''));
                            datasets[c].data.push(isNaN(val) ? 0 : val);
                        }
                    }
                    drawChart(labels, datasets);
                });
            } else {
                let labels = [], datasets = [];
                let raw = config.manualData;
                if (raw) {
                    // PSEUDOCODE: Convert storage format (array or object) into a sequential list of rows.
                    let rows = Array.isArray(raw) ? raw : Object.keys(raw).sort((a, b) => a - b).map(k => raw[k]);

                    if (rows.length > 0) {
                        // PSEUDOCODE: Check if data is in 'Legacy' (label/value) or 'Multi-Series' (columnar) format.
                        if (rows[0] && rows[0].label !== undefined) {
                            // Legacy Format Handling
                            datasets.push({ label: 'Value', data: [] });
                            rows.forEach((row) => {
                                labels.push(row.label || '');
                                let val = parseFloat(String(row.value || '').replace(/,/g, ''));
                                datasets[0].data.push(isNaN(val) ? 0 : val);
                            });
                        } else {
                            // Multi-Series Columnar Format Handling
                            const headers = rows[0];
                            // Extract series names from the header row.
                            for (let i = 1; i < headers.length; i++) datasets.push({ label: headers[i], data: [] });
                            // Extract labels and values from subsequent rows.
                            for (let r = 1; r < rows.length; r++) {
                                labels.push(rows[r][0]);
                                for (let c = 0; c < datasets.length; c++) {
                                    let val = parseFloat(String(rows[r][c + 1] || '').replace(/,/g, ''));
                                    datasets[c].data.push(isNaN(val) ? 0 : val);
                                }
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
