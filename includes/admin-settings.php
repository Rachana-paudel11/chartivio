<?php
/**
 * Admin Settings - Meta Boxes and Admin Functionality
 * 
 * Handles all admin-side functionality including meta boxes,
 * data persistence, and admin column customization.
 */

// PSEUDO CODE:
// IF ABSPATH is not defined THEN
//     EXIT script to prevent direct access
// END IF
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Custom Columns to Admin List
 */
function dearcharts_add_id_column($columns)
{
    $columns['id'] = 'ID';
    $columns['shortcode'] = 'Shortcode';
    return $columns;
}
add_filter('manage_dearcharts_posts_columns', 'dearcharts_add_id_column');

function dearcharts_custom_column_content($column, $post_id)
{
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
    // Check if post is auto-draft (not saved yet)
    if ($post->post_status === 'auto-draft') {
        echo '<p>Save the post first to get the shortcode.</p>';
        return;
    }

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
    $chart_type = get_post_meta($post->ID, '_dearcharts_type', true);
    $is_donut = get_post_meta($post->ID, '_dearcharts_is_donut', true);
    $legend_pos = get_post_meta($post->ID, '_dearcharts_legend_pos', true);
    $palette = get_post_meta($post->ID, '_dearcharts_palette', true);
    $custom_colors = get_post_meta($post->ID, '_dearcharts_colors', true);

    if (empty($palette)) {
        $palette = 'default';
    }
    if (empty($chart_type)) {
        $chart_type = 'pie';
    }

    // GET post title for chart label
    $chart_title = $post->post_title ? $post->post_title : 'Untitled Chart';

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
                <span class="dearcharts-tab-link" onclick="openDearChartsTab(event, 'tab-settings')">Chart Settings</span>
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

            <!-- Tab Content: Chart Settings -->
            <div id="tab-settings" class="dearcharts-tab-content">
                <div class="dearcharts-input-group">
                    <label for="dearcharts_type">Chart Type:</label>
                    <select id="dearcharts_type" class="dearcharts-live-input" name="dearcharts_type">
                        <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie</option>
                        <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut</option>
                        <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar (Vertical)</option>
                        <option value="horizontalBar" <?php selected($chart_type, 'horizontalBar'); ?>>Bar (Horizontal)
                        </option>
                    </select>
                </div>
                <div class="dearcharts-input-group" id="donut-mode-group" style="display:none;">
                    <label for="dearcharts_is_donut">
                        <input type="checkbox" class="dearcharts-live-input" id="dearcharts_is_donut"
                            name="dearcharts_is_donut" value="1" <?php checked($is_donut, '1'); ?>>
                        Donut Mode (Legacy - use Chart Type instead)
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
                <div class="dearcharts-input-group">
                    <label for="dearcharts_colors">Custom Colors:</label>
                    <textarea id="dearcharts_colors" class="dearcharts-live-input large-text" name="dearcharts_colors"
                        rows="3" placeholder="#FF6384, #36A2EB, #FFCE56"><?php echo esc_attr($custom_colors); ?></textarea>
                    <span class="description" style="display:block; margin-top:5px;">Enter comma-separated hex codes
                        (overrides palette)</span>
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

            // Chart title from post
            var chartTitle = <?php echo json_encode($chart_title); ?>;

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
            // READ chart type dropdown
            // READ legend position dropdown
            // READ custom colors or color palette
            // CALCULATE final colors using custom or generateColors
            function getChartSettings(dataCount) {
                var chartType = $('#dearcharts_type').val() || 'pie';
                var legendPos = $('#dearcharts_legend_pos').val();
                var customColorsText = $('#dearcharts_colors').val().trim();
                var paletteKey = $('#dearcharts_palette').val();

                var finalColors;
                if (customColorsText) {
                    // Parse custom colors
                    finalColors = customColorsText.split(',').map(function (color) {
                        return color.trim();
                    });
                    // Repeat colors if not enough
                    while (finalColors.length < dataCount) {
                        finalColors = finalColors.concat(finalColors);
                    }
                    finalColors = finalColors.slice(0, dataCount);
                } else {
                    // Use palette
                    var baseColors = palettes[paletteKey] || palettes['default'];
                    finalColors = generateColors(baseColors, dataCount || 0);
                }

                return {
                    type: chartType,
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

                var chartOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: settings.legendPos,
                        }
                    }
                };

                // Add scales for bar charts
                if (settings.type === 'bar' || settings.type === 'horizontalBar') {
                    chartOptions.scales = {
                        y: {
                            beginAtZero: true
                        }
                    };

                    // For horizontal bar, use indexAxis
                    if (settings.type === 'horizontalBar') {
                        chartOptions.indexAxis = 'y';
                        // Change type to 'bar' for Chart.js
                        settings.type = 'bar';
                    }
                }

                myChart = new Chart(ctx, {
                    type: settings.type,
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: chartTitle,
                            data: chartData.data,
                            backgroundColor: settings.colors,
                            hoverOffset: 4
                        }]
                    },
                    options: chartOptions
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
 * Validate DearChart before publishing
 * Prevent publishing if no chart data exists
 */
function dearcharts_validate_before_publish($post_id, $post, $update)
{
    // Only validate for dearcharts post type
    if ($post->post_type !== 'dearcharts') {
        return;
    }

    // Only validate when trying to publish
    if ($post->post_status !== 'publish') {
        return;
    }

    // Get chart data
    $manual_data = get_post_meta($post_id, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post_id, '_dearcharts_csv_url', true);

    // Check if there's any data
    $has_manual_data = !empty($manual_data) && is_array($manual_data) && count($manual_data) > 0;
    $has_csv_data = !empty($csv_url) && trim($csv_url) !== '';

    // If no data exists, prevent publishing
    if (!$has_manual_data && !$has_csv_data) {
        // Remove publish status and set to draft
        remove_action('save_post', 'dearcharts_validate_before_publish', 10);
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'draft'
        ));
        add_action('save_post', 'dearcharts_validate_before_publish', 10, 3);

        // Set admin notice
        set_transient('dearcharts_publish_error_' . $post_id, 'Please add chart data (Manual Data or CSV) before publishing.', 45);
    }
}
add_action('save_post', 'dearcharts_validate_before_publish', 10, 3);

/**
 * Display admin notice for validation errors
 */
function dearcharts_display_validation_notice()
{
    global $post;

    if (!$post || $post->post_type !== 'dearcharts') {
        return;
    }

    $error = get_transient('dearcharts_publish_error_' . $post->ID);

    if ($error) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html($error) . '</strong></p></div>';
        delete_transient('dearcharts_publish_error_' . $post->ID);
    }
}
add_action('admin_notices', 'dearcharts_display_validation_notice');

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
    // Chart Type (Dropdown)
    if (isset($_POST['dearcharts_type'])) {
        $chart_type = sanitize_text_field($_POST['dearcharts_type']);
        update_post_meta($post_id, '_dearcharts_type', $chart_type);
    }

    // Donut Mode (Checkbox) - Legacy
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

    // Custom Colors (Textarea)
    if (isset($_POST['dearcharts_colors'])) {
        $custom_colors = sanitize_text_field($_POST['dearcharts_colors']);
        update_post_meta($post_id, '_dearcharts_colors', $custom_colors);
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
