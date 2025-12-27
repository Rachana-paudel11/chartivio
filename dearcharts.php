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

    // ENQUEUE Chart.js library (ensures it is loaded)
    wp_enqueue_script('chartjs');

    // PSEUDO CODE:
    // RETRIEVE saved meta data for this chart:
    // - Manual Data (Repeater rows)
    // - CSV URL
    // - Donut Mode (true/false)
    // - Legend Position (top/bottom/left/right)
    // - Color Palette (key like 'ocean', 'sunset')
    $manual_data = get_post_meta($post_id, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post_id, '_dearcharts_csv_url', true);
    $is_donut = get_post_meta($post_id, '_dearcharts_is_donut', true);
    $legend_pos = get_post_meta($post_id, '_dearcharts_legend_pos', true);
    $palette = get_post_meta($post_id, '_dearcharts_palette', true);

    // SET default palette to 'default' if empty
    if (empty($palette))
        $palette = 'default';
    // SET default legend position to 'top' if empty
    if (empty($legend_pos))
        $legend_pos = 'top';

    // DETERMINE active data source:
    // IF CSV URL is not empty THEN use 'csv'
    // ELSE use 'manual'
    $active_source = (!empty($csv_url)) ? 'csv' : 'manual';

    // GENERATE unique ID for the canvas element
    $unique_id = 'dearchart-' . $post_id . '-' . uniqid();

    // PREPARE configuration object for JavaScript
    $config = array(
        'id' => $unique_id,
        'type' => ($is_donut === '1') ? 'doughnut' : 'pie',
        'legendPos' => $legend_pos,
        'palette' => $palette,
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
    // CALL initDearChart(config) when document is ready
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
                // 1. GET base colors from config.palette
                // 2. GENERATE final colors based on value count
                // 3. CREATE new Chart instance with data and options
                var onDataReady = function (labels, values) {
                    var baseColors = palettes[config.palette] || palettes['default'];
                    var finalColors = generateColors(baseColors, values.length);

                    new Chart(ctx, {
                        type: config.type,
                        data: {
                            labels: labels,
                            datasets: [{
                                data: values,
                                backgroundColor: finalColors,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: config.legendPos
                                }
                            }
                        }
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

/**
 * Add Custom Columns to Admin List
 */
function dearcharts_add_id_column($columns)
{
    // PSEUDO CODE:
    // ADD 'ID' and 'Shortcode' columns to post list
    $columns['id'] = 'ID';
    $columns['shortcode'] = 'Shortcode';
    return $columns;
}
add_filter('manage_dearcharts_posts_columns', 'dearcharts_add_id_column');

function dearcharts_custom_column_content($column, $post_id)
{
    // PSEUDO CODE:
    // SWITCH column name
    // CASE 'id': ECHO post ID
    // CASE 'shortcode': ECHO copyable shortcode string
    switch ($column) {
        case 'id':
            echo $post_id;
            break;
        case 'shortcode':
            echo '<code>[dearchart id="' . $post_id . '"]</code>';
            break;
    }
}
add_action('manage_dearcharts_posts_custom_column', 'dearcharts_custom_column_content', 10, 2);

/**
 * Add Meta Box with Tabs
 */
function dearcharts_register_meta_box()
{
    // PSEUDO CODE:
    // ADD meta box for Chart Data (Tabbed Interface)
    add_meta_box(
        'dearcharts_data_meta_box',
        'Chart Data',
        'dearcharts_render_tabbed_meta_box',
        'dearcharts',
        'normal',
        'high'
    );

    // ADD meta box for Shortcode Display (Sidebar)
    add_meta_box(
        'dearcharts_shortcode_meta_box',
        'Chart Shortcode',
        'dearcharts_render_shortcode_meta_box',
        'dearcharts',
        'side',
        'low'
    );
}
add_action('add_meta_boxes', 'dearcharts_register_meta_box');

function dearcharts_render_shortcode_meta_box($post)
{
    // DISPLAY read-only shortcode for user convenience
    echo '<p>Use this shortcode to display the chart:</p>';
    echo '<code>[dearchart id="' . $post->ID . '"]</code>';
}

function dearcharts_render_tabbed_meta_box($post)
{
    // PSEUDO CODE:
    // RETRIEVE saved meta values (Manual Data, CSV URL, Settings)
    $manual_data = get_post_meta($post->ID, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post->ID, '_dearcharts_csv_url', true);
    $is_donut = get_post_meta($post->ID, '_dearcharts_is_donut', true);
    $legend_pos = get_post_meta($post->ID, '_dearcharts_legend_pos', true);
    $palette = get_post_meta($post->ID, '_dearcharts_palette', true);

    if (empty($palette)) {
        $palette = 'default';
    }

    // ADD Nonce field for security
    wp_nonce_field('dearcharts_save_meta_box_data', 'dearcharts_nonce');
    ?>
    <style>
        /* CSS Definitions for Tabbed Layout and Chart Preview */
        .dearcharts-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .dearcharts-preview {
            flex: 1;
            min-width: 300px;
            border: 1px solid #ddd;
            padding: 15px;
            background: #f9f9f9;
            text-align: center;
        }

        .dearcharts-settings {
            flex: 1;
            min-width: 300px;
        }

        .dearcharts-tabs {
            overflow: hidden;
            border-bottom: 1px solid #ccc;
            margin-bottom: 10px;
        }

        .dearcharts-tab-link {
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 10px 16px;
            transition: 0.3s;
            font-size: 14px;
            background-color: #f1f1f1;
            margin-right: 2px;
            border-radius: 4px 4px 0 0;
        }

        .dearcharts-tab-link:hover {
            background-color: #ddd;
        }

        .dearcharts-tab-link.active {
            background-color: #0073aa;
            color: white;
        }

        .dearcharts-tab-content {
            display: none;
            padding: 10px;
            border: 1px solid #ccc;
            border-top: none;
        }

        .dearcharts-tab-content.active {
            display: block;
        }

        .dearcharts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .dearcharts-table th,
        .dearcharts-table td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .dearcharts-table input[type="text"],
        .dearcharts-table input[type="number"] {
            width: 95%;
        }

        .dearcharts-remove-row {
            color: red;
            cursor: pointer;
            font-weight: bold;
        }

        .dearcharts-input-group {
            margin-bottom: 15px;
        }

        .dearcharts-input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        #dearchartsCanvasContainer {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>

    <div class="dearcharts-wrapper">
        <!-- Left Column: Live Preview -->
        <div class="dearcharts-preview">
            <h3>Live Preview</h3>
            <div id="dearchartsCanvasContainer">
                <canvas id="dearchartsCanvas"></canvas>
            </div>
            <div id="dearcharts-no-data" style="display:none; padding-top: 50px; color: #777;">
                Add data to see preview
            </div>
        </div>

        <!-- Right Column: Settings Tabs -->
        <div class="dearcharts-settings">
            <div class="dearcharts-tabs">
                <!-- Tab Links -->
                <span class="dearcharts-tab-link active" onclick="openDearChartsTab(event, 'tab-manual')">Manual Data</span>
                <span class="dearcharts-tab-link" onclick="openDearChartsTab(event, 'tab-csv')">Import CSV</span>
                <span class="dearcharts-tab-link" onclick="openDearChartsTab(event, 'tab-pie')">Pie Settings</span>
            </div>

            <!-- Tab Content: Manual Data -->
            <div id="tab-manual" class="dearcharts-tab-content active">
                <table class="dearcharts-table" id="dearcharts-repeater-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Value</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // LOOP through existing manual data and render rows
                        if (!empty($manual_data) && is_array($manual_data)) {
                            foreach ($manual_data as $row) {
                                ?>
                                <tr>
                                    <td><input type="text" class="dearcharts-live-input" name="dearcharts_manual_label[]"
                                            value="<?php echo esc_attr($row['label']); ?>" placeholder="Label" /></td>
                                    <td><input type="number" class="dearcharts-live-input" name="dearcharts_manual_value[]"
                                            value="<?php echo esc_attr($row['value']); ?>" placeholder="Value" step="any" /></td>
                                    <td><span class="dearcharts-remove-row">X</span></td>
                                </tr>
                                <?php
                            }
                        } else {
                            // RENDER default empty row if no data
                            ?>
                            <tr>
                                <td><input type="text" class="dearcharts-live-input" name="dearcharts_manual_label[]"
                                        placeholder="Label" /></td>
                                <td><input type="number" class="dearcharts-live-input" name="dearcharts_manual_value[]"
                                        placeholder="Value" step="any" /></td>
                                <td><span class="dearcharts-remove-row">X</span></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                <button type="button" class="button" id="dearcharts-add-row" style="margin-top: 10px;">+ Add Row</button>
            </div>

            <!-- Tab Content: Import CSV -->
            <div id="tab-csv" class="dearcharts-tab-content">
                <div class="dearcharts-input-group">
                    <label for="dearcharts_csv_url">CSV File URL:</label>
                    <input type="text" id="dearcharts_csv_url" name="dearcharts_csv_url" class="large-text"
                        value="<?php echo esc_attr($csv_url); ?>" placeholder="https://...">
                    <button type="button" class="button" id="dearcharts_upload_csv_btn">Upload/Select</button>
                    <span class="description" style="display:block; margin-top:5px;">Live preview active (Label,
                        Value)</span>
                </div>
            </div>

            <!-- Tab Content: Pie Settings -->
            <div id="tab-pie" class="dearcharts-tab-content">
                <div class="dearcharts-input-group">
                    <label for="dearcharts_is_donut">
                        <input type="checkbox" class="dearcharts-live-input" id="dearcharts_is_donut"
                            name="dearcharts_is_donut" value="1" <?php checked($is_donut, '1'); ?>>
                        Donut Mode
                    </label>
                </div>
                <div class="dearcharts-input-group">
                    <label for="dearcharts_legend_pos">Legend Position:</label>
                    <select id="dearcharts_legend_pos" class="dearcharts-live-input" name="dearcharts_legend_pos">
                        <option value="bottom" <?php selected($legend_pos, 'bottom'); ?>>Bottom</option>
                        <option value="top" <?php selected($legend_pos, 'top'); ?>>Top</option>
                        <option value="left" <?php selected($legend_pos, 'left'); ?>>Left</option>
                        <option value="right" <?php selected($legend_pos, 'right'); ?>>Right</option>
                    </select>
                </div>
                <div class="dearcharts-input-group">
                    <label for="dearcharts_palette">Color Palette:</label>
                    <select id="dearcharts_palette" class="dearcharts-live-input" name="dearcharts_palette">
                        <option value="default" <?php selected($palette, 'default'); ?>>Default (Multiple)</option>
                        <option value="pastel" <?php selected($palette, 'pastel'); ?>>Pastel</option>
                        <option value="ocean" <?php selected($palette, 'ocean'); ?>>Ocean (Blues)</option>
                        <option value="sunset" <?php selected($palette, 'sunset'); ?>>Sunset (Reds/Oranges)</option>
                        <option value="neon" <?php selected($palette, 'neon'); ?>>Neon (Bright)</option>
                        <option value="forest" <?php selected($palette, 'forest'); ?>>Forest (Greens)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <script>
        // FUNCTION openDearChartsTab(evt, tabName)
        // HIDE all tab content
        // REMOVE active class from all links
        // SHOW target tab ID
        // ADD active class to clicked link
        function openDearChartsTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("dearcharts-tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("dearcharts-tab-link");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");

            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add("active");
            } else {
                // Programmatic activation support
                var links = document.getElementsByClassName("dearcharts-tab-link");
                for (var j = 0; j < links.length; j++) {
                    var onclickVal = links[j].getAttribute('onclick');
                    if (onclickVal && onclickVal.indexOf(tabName) !== -1) {
                        links[j].classList.add("active");
                    }
                }
            }
        }

        jQuery(document).ready(function ($) {
            var myChart = null;
            var csvParsedData = { labels: [], data: [] };

            // Track which data source was last active ('manual' or 'csv')
            var activeDataSource = 'manual';

            if (typeof Chart === 'undefined') {
                console.error("Chart.js not loaded");
                return;
            }
            var ctx = document.getElementById('dearchartsCanvas').getContext('2d');

            // DEFINE palettes (Javascript side)
            var palettes = {
                'default': ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED'],
                'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa', '#f0e68c'],
                'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#e0f2f1', '#004d40'],
                'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50', '#cd5c5c'],
                'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff', '#ff1493'],
                'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F', '#66CDAA']
            };

            // Helper to generate distinct colors if data points > palette length
            function generateColors(basePalette, count) {
                var colors = [].concat(basePalette);
                if (count <= colors.length) {
                    return colors.slice(0, count);
                }

                var needed = count - colors.length;
                for (var i = 0; i < needed; i++) {
                    var hue = (i * 137.5 + 200) % 360;
                    var color = 'hsl(' + hue + ', 65%, 60%)';
                    colors.push(color);
                }
                return colors;
            }

            // FUNCTION getManualData()
            // ITERATE through repeater table rows
            // EXTRACT label and value inputs
            // RETURN objects {labels, data}
            function getManualData() {
                var labels = [];
                var data = [];
                $('#dearcharts-repeater-table tbody tr').each(function () {
                    var label = $(this).find('input[type="text"]').val();
                    var value = $(this).find('input[type="number"]').val();

                    if (value !== "") {
                        labels.push(label || 'Unnamed');
                        data.push(parseFloat(value));
                    }
                });
                return { labels: labels, data: data };
            }

            // FUNCTION getChartSettings(dataCount)
            // READ checkbox for Donut Mode
            // READ legend position dropdown
            // READ color palette dropdown
            // CALCULATE final colors using generateColors
            function getChartSettings(dataCount) {
                var isDonut = $('#dearcharts_is_donut').is(':checked');
                var legendPos = $('#dearcharts_legend_pos').val();
                var paletteKey = $('#dearcharts_palette').val();

                var baseColors = palettes[paletteKey] || palettes['default'];
                var finalColors = generateColors(baseColors, dataCount || 0);

                return {
                    type: isDonut ? 'doughnut' : 'pie',
                    legendPos: legendPos,
                    colors: finalColors
                };
            }

            // FUNCTION loadCSV(url)
            // FETCH content from URL
            // CALL parseCSV on content
            // TRIGGER updateChart
            function loadCSV(url) {
                if (!url) {
                    csvParsedData = { labels: [], data: [] };
                    updateChart();
                    return;
                }

                fetch(url)
                    .then(function (response) {
                        if (!response.ok) throw new Error("Network response was not ok");
                        return response.text();
                    })
                    .then(function (csvText) {
                        parseCSV(csvText);
                        updateChart();
                    })
                    .catch(function (error) {
                        csvParsedData = { labels: [], data: [] };
                        updateChart();
                    });
            }

            // FUNCTION parseCSV(text)
            // SPLIT text by line
            // SPLIT each line by comma
            // STORE in csvParsedData {labels, data}
            function parseCSV(text) {
                var lines = text.split(/\r\n|\n/);
                var labels = [];
                var data = [];

                for (var i = 0; i < lines.length; i++) {
                    var line = lines[i].trim();
                    if (!line) continue;

                    var parts = line.split(',');
                    if (parts.length >= 2) {
                        var lbl = parts[0].trim();
                        var val = parseFloat(parts[1]);
                        if (!isNaN(val)) {
                            labels.push(lbl);
                            data.push(val);
                        }
                    }
                }
                csvParsedData = { labels: labels, data: data };
            }

            // FUNCTION updateChart()
            // DETERMINE active data source (Manual vs CSV)
            // GET settings (colors, type)
            // DESTROY existing chart if configuration changed
            // CREATE or UPDATE Chart.js instance with new data
            function updateChart() {
                var chartData;
                if (activeDataSource === 'csv') {
                    chartData = csvParsedData;
                } else {
                    chartData = getManualData();
                }

                var validData = [];
                var validLabels = [];
                for (var i = 0; i < chartData.data.length; i++) {
                    if (!isNaN(chartData.data[i])) {
                        validData.push(chartData.data[i]);
                        validLabels.push(chartData.labels[i]);
                    }
                }

                var finalChartData = {
                    labels: validLabels,
                    data: validData
                };

                if (finalChartData.data.length === 0) {
                    if (myChart) {
                        myChart.destroy();
                        myChart = null;
                        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                    }
                    $('#dearchartsCanvas').hide();
                    $('#dearcharts-no-data').show();
                    $('#dearcharts-no-data').text("Add data to see preview" + (activeDataSource === 'csv' ? " (CSV)" : ""));
                    return;
                }

                var settings = getChartSettings(finalChartData.data.length);

                $('#dearcharts-no-data').hide();
                $('#dearchartsCanvas').show();

                if (myChart) {
                    if (myChart.config.type !== settings.type) {
                        myChart.destroy();
                        createChart(finalChartData, settings);
                    } else {
                        myChart.data.labels = finalChartData.labels;
                        myChart.data.datasets[0].data = finalChartData.data;
                        myChart.data.datasets[0].backgroundColor = settings.colors;
                        myChart.options.plugins.legend.position = settings.legendPos;
                        myChart.update();
                    }
                } else {
                    createChart(finalChartData, settings);
                }
            }

            function createChart(chartData, settings) {
                if (!ctx) return;

                myChart = new Chart(ctx, {
                    type: settings.type,
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            data: chartData.data,
                            backgroundColor: settings.colors,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: settings.legendPos,
                            }
                        }
                    }
                });
            }

            // INITIAL LOAD:
            // IF CSV URL exists in hidden input THEN
            //    activeDataSource = 'csv'
            //    Load CSV
            // ELSE default to Manual Data and updateChart
            var initialCSV = $('#dearcharts_csv_url').val();
            // If CSV is saved, prioritize it and switch tab
            if (initialCSV && initialCSV.trim() !== '') {
                activeDataSource = 'csv';
                openDearChartsTab(null, 'tab-csv');
                loadCSV(initialCSV);
            } else {
                activeDataSource = 'manual';
                updateChart();
            }

            // LISTENERS:
            // 1. Tab Click: Switch activeDataSource logic and update preview
            $('.dearcharts-tab-link').click(function () {
                var tabId = $(this).attr('onclick').match(/'([^']+)'/)[1];
                if (tabId === 'tab-manual') {
                    activeDataSource = 'manual';
                    updateChart();
                } else if (tabId === 'tab-csv') {
                    activeDataSource = 'csv';
                    updateChart();
                }
            });

            // 2. Input/Change on inputs: Trigger live update
            $(document).on('input change', '.dearcharts-live-input', function () {
                updateChart();
            });

            $('#dearcharts-repeater-table').on('input', 'input', function () {
                if (activeDataSource === 'manual') {
                    updateChart();
                }
            });

            // 3. Debounce for CSV URL input
            var apiTimeout = null;
            $('#dearcharts_csv_url').on('input change', function () {
                var url = $(this).val();
                activeDataSource = 'csv';
                if (apiTimeout) clearTimeout(apiTimeout);
                apiTimeout = setTimeout(function () {
                    loadCSV(url);
                }, 500);
            });

            // 4. Media Uploader Logic
            $('#dearcharts_upload_csv_btn').click(function (e) {
                e.preventDefault();
                var image = wp.media({
                    title: 'Upload CSV',
                    multiple: false
                }).open()
                    .on('select', function (e) {
                        var uploaded_image = image.state().get('selection').first();
                        var image_url = uploaded_image.toJSON().url;
                        $('#dearcharts_csv_url').val(image_url).trigger('change');
                    });
            });

            // 5. Add/Remove Rows
            $('#dearcharts-add-row').on('click', function (e) {
                e.preventDefault();
                var row = '<tr>' +
                    '<td><input type="text" class="dearcharts-live-input" name="dearcharts_manual_label[]" placeholder="Label" /></td>' +
                    '<td><input type="number" class="dearcharts-live-input" name="dearcharts_manual_value[]" placeholder="Value" step="any" /></td>' +
                    '<td><span class="dearcharts-remove-row">X</span></td>' +
                    '</tr>';
                $('#dearcharts-repeater-table tbody').append(row);
            });

            $('#dearcharts-repeater-table').on('click', '.dearcharts-remove-row', function (e) {
                e.preventDefault();
                $(this).closest('tr').remove();
                if (activeDataSource === 'manual') {
                    updateChart();
                }
            });
        });
    </script>
    <?php
}

/**
 * Save Meta Box Data.
 */
function dearcharts_save_meta_box_data($post_id)
{
    // PSEUDO CODE:
    // VERIFY nonce for security
    // CHECK if doing autosave (skip)
    // CHECK user permissions

    // Check nonce
    if (!isset($_POST['dearcharts_nonce']) || !wp_verify_nonce($_POST['dearcharts_nonce'], 'dearcharts_save_meta_box_data')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // SAVE MANUAL DATA
    // ZIP label and value arrays into associative array
    // UPDATE post meta '_dearcharts_manual_data'
    // Save Manual Data (Repeater)
    // Using separated arrays for label and value to avoid serialization issues
    if (isset($_POST['dearcharts_manual_label']) && isset($_POST['dearcharts_manual_value'])) {
        $labels = $_POST['dearcharts_manual_label'];
        $values = $_POST['dearcharts_manual_value'];
        $manual_data = array();

        // Loop through labels and zip with values
        for ($i = 0; $i < count($labels); $i++) {
            $label = sanitize_text_field($labels[$i]);
            $value = isset($values[$i]) ? floatval($values[$i]) : 0;

            // Only add if either label or value is not empty
            if ($label !== '' || (isset($values[$i]) && $values[$i] !== '')) {
                $manual_data[] = array(
                    'label' => $label,
                    'value' => $value
                );
            }
        }
        update_post_meta($post_id, '_dearcharts_manual_data', $manual_data);
    } else {
        delete_post_meta($post_id, '_dearcharts_manual_data');
    }

    // SAVE CSV URL
    if (isset($_POST['dearcharts_csv_url'])) {
        $csv_url = esc_url_raw($_POST['dearcharts_csv_url']);
        update_post_meta($post_id, '_dearcharts_csv_url', $csv_url);
    }

    // SAVE SETTINGS
    // Donut Mode (Checkbox)
    $is_donut = isset($_POST['dearcharts_is_donut']) ? '1' : '';
    update_post_meta($post_id, '_dearcharts_is_donut', $is_donut);

    // Legend Position
    if (isset($_POST['dearcharts_legend_pos'])) {
        $legend_pos = sanitize_text_field($_POST['dearcharts_legend_pos']);
        update_post_meta($post_id, '_dearcharts_legend_pos', $legend_pos);
    }

    // Palette (Dropdown)
    if (isset($_POST['dearcharts_palette'])) {
        $palette = sanitize_text_field($_POST['dearcharts_palette']);
        update_post_meta($post_id, '_dearcharts_palette', $palette);
    }
}
add_action('save_post', 'dearcharts_save_meta_box_data');

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
        if ('dearcharts' === $post->post_type) {
            // Enqueue Chart.js from CDN
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_scripts');
