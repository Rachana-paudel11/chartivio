<?php
/**
 * Plugin Name: DearCharts
 * Plugin URI: https://example.com/dearcharts
 * Description: A basic WordPress plugin header for DearCharts.
 * Version: 1.0.0
 * Author: Rachana Paudel
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: dearcharts
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register DearCharts Custom Post Type.
 */
function dearcharts_register_cpt()
{
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

    if (function_exists('register_post_type')) {
        register_post_type('dearcharts', $args);
    }
}
add_action('init', 'dearcharts_register_cpt');

/**
 * Shortcode to display DearChart title.
 * Usage: [dearchart_title id="123"]
 */
function dearcharts_shortcode_title($atts)
{
    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts, 'dearchart');

    $post_id = intval($atts['id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'dearcharts') {
        return 'Chart title not found';
    }

    $title = get_the_title($post_id);

    return '<h2 class="dearcharts-title">' . esc_html($title) . '</h2>';
}
add_shortcode('dearchart', 'dearcharts_shortcode_title');

/**
 * Add Chart ID and Shortcode columns to the dearcharts post type list.
 */
function dearcharts_add_id_column($columns)
{
    $columns['id'] = 'ID';
    $columns['shortcode'] = 'Shortcode';
    return $columns;
}
add_filter('manage_dearcharts_posts_columns', 'dearcharts_add_id_column');

/**
 * Display the content for custom columns.
 */
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
 * Register Meta Box for DearCharts.
 */
function dearcharts_register_meta_box()
{
    add_meta_box(
        'dearcharts_data_meta_box',
        'Chart Data',
        'dearcharts_render_tabbed_meta_box',
        'dearcharts',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'dearcharts_register_meta_box');

/**
 * Render the Tabbed Meta Box.
 */
function dearcharts_render_tabbed_meta_box($post)
{
    // Retrieve existing values
    $manual_data = get_post_meta($post->ID, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post->ID, '_dearcharts_csv_url', true);
    $is_donut = get_post_meta($post->ID, '_dearcharts_is_donut', true);
    $legend_pos = get_post_meta($post->ID, '_dearcharts_legend_pos', true);
    $palette = get_post_meta($post->ID, '_dearcharts_palette', true); // New meta key

    // Default to 'default' if empty
    if (empty($palette)) {
        $palette = 'default';
    }

    // Nonce field for security
    wp_nonce_field('dearcharts_save_meta_box_data', 'dearcharts_nonce');
    ?>
    <style>
        .dearcharts-wrapper {
            display: flex;
            gap: 20px;
        }

        .dearcharts-preview {
            width: 50%;
            min-width: 300px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            max-height: 400px;
            position: relative;
        }

        .dearcharts-settings {
            width: 50%;
            flex-grow: 1;
        }

        .dearcharts-tabs {
            border-bottom: 1px solid #ccc;
            margin-bottom: 15px;
        }

        .dearcharts-tab-link {
            display: inline-block;
            padding: 10px 15px;
            text-decoration: none;
            color: #444;
            border: 1px solid transparent;
            border-bottom: none;
            margin-bottom: -1px;
            cursor: pointer;
            font-weight: 500;
        }

        .dearcharts-tab-link.active {
            background-color: #fff;
            border-color: #ccc;
            border-top: 2px solid #2271b1;
            color: #000;
        }

        .dearcharts-tab-content {
            display: none;
        }

        .dearcharts-tab-content.active {
            display: block;
        }

        .dearcharts-input-group {
            margin-bottom: 15px;
        }

        .dearcharts-input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .dearcharts-input-group input[type="text"],
        .dearcharts-input-group select {
            width: 100%;
            max-width: 400px;
        }

        /* Repeater Table Styles */
        .dearcharts-table {
            width: 100%;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #ddd;
        }

        .dearcharts-table th,
        .dearcharts-table td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .dearcharts-table th {
            background-color: #f9f9f9;
            font-weight: 600;
        }

        .dearcharts-remove-row {
            color: #a00;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
        }

        .dearcharts-remove-row:hover {
            color: #d00;
        }

        canvas#dearchartsCanvas {
            max-height: 350px;
            width: 100% !important;
            height: 100% !important;
        }
    </style>

    <div class="dearcharts-wrapper">
        <!-- Left Column: Chart Preview -->
        <div class="dearcharts-preview" style="height: 350px;">
            <canvas id="dearchartsCanvas"></canvas>
            <div id="dearcharts-no-data"
                style="display:none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">Add data to
                see preview</div>
        </div>

        <!-- Right Column: Settings Tabs -->
        <div class="dearcharts-settings">
            <div class="dearcharts-tabs">
                <span class="dearcharts-tab-link active" onclick="openDearChartsTab(event, 'tab-manual')">Manual Data</span>
                <span class="dearcharts-tab-link" onclick="openDearChartsTab(event, 'tab-csv')">Import CSV</span>
                <span class="dearcharts-tab-link" onclick="openDearChartsTab(event, 'tab-pie')">Pie Settings</span>
            </div>

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
                            // Default empty row
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
                <button type="button" class="button" id="dearcharts-add-row">Add Row</button>
            </div>

            <div id="tab-csv" class="dearcharts-tab-content">
                <div class="dearcharts-input-group">
                    <label for="dearcharts_csv_url">CSV File URL:</label>
                    <input type="text" id="dearcharts_csv_url" name="dearcharts_csv_url"
                        value="<?php echo esc_attr($csv_url); ?>" placeholder="https://...">
                    <button type="button" class="button" id="dearcharts_upload_csv_btn">Upload/Select</button>
                    <p class="description">Preview not available for CSV data yet.</p>
                </div>
            </div>

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
            evt.currentTarget.classList.add("active");
        }

        jQuery(document).ready(function ($) {
            var myChart = null;
            // Check if Chart is defined (loaded properly)
            if (typeof Chart === 'undefined') {
                console.error("Chart.js not loaded");
                return;
            }
            var ctx = document.getElementById('dearchartsCanvas').getContext('2d');

            // Color Palettes Definition
            var palettes = {
                'default': ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED'],
                'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa', '#f0e68c'],
                'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#e0f2f1', '#004d40'],
                'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50', '#cd5c5c'],
                'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff', '#ff1493'],
                'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F', '#66CDAA']
            };

            function getManualData() {
                var labels = [];
                var data = [];
                $('#dearcharts-repeater-table tbody tr').each(function () {
                    // Use simpler selectors based on type to avoid attribute quoting issues
                    // Label is the first text input, Value is the first number input in the row
                    var label = $(this).find('input[type="text"]').val();
                    var value = $(this).find('input[type="number"]').val();

                    if (value !== "") {
                        labels.push(label || 'Unnamed');
                        data.push(parseFloat(value));
                    }
                });
                return { labels: labels, data: data };
            }

            function getChartSettings() {
                var isDonut = $('#dearcharts_is_donut').is(':checked');
                var legendPos = $('#dearcharts_legend_pos').val();
                var paletteKey = $('#dearcharts_palette').val();

                var colors = palettes[paletteKey] || palettes['default'];

                return {
                    type: isDonut ? 'doughnut' : 'pie',
                    legendPos: legendPos,
                    colors: colors
                };
            }

            function updateChart() {
                var chartData = getManualData();
                var settings = getChartSettings();

                var hasData = false;
                for (var i = 0; i < chartData.data.length; i++) {
                    if (!isNaN(chartData.data[i])) {
                        hasData = true;
                        break;
                    }
                }

                if (!hasData) {
                    if (myChart) {
                        myChart.destroy();
                        myChart = null;
                        // Clear canvas
                        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                    }
                    $('#dearchartsCanvas').hide();
                    $('#dearcharts-no-data').show();
                    return;
                }

                $('#dearcharts-no-data').hide();
                $('#dearchartsCanvas').show();

                if (myChart) {
                    // Update existing chart
                    // If type changed, we must destroy and recreate
                    if (myChart.config.type !== settings.type) {
                        myChart.destroy();
                        createChart(chartData, settings);
                    } else {
                        myChart.data.labels = chartData.labels;
                        myChart.data.datasets[0].data = chartData.data;
                        myChart.data.datasets[0].backgroundColor = settings.colors;
                        myChart.options.plugins.legend.position = settings.legendPos;
                        myChart.update();
                    }
                } else {
                    createChart(chartData, settings);
                }
            }

            function createChart(chartData, settings) {
                // Ensure context is available
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
                        maintainAspectRatio: false, // Important for layout
                        plugins: {
                            legend: {
                                position: settings.legendPos,
                            }
                        }
                    }
                });
            }

            // Initial Render
            updateChart();

            // Listen for changes
            $(document).on('input change', '.dearcharts-live-input', function () {
                updateChart();
            });
            // Manual data inputs usually don't have unique IDs/classes that are easy to target globally without delegation,
            // but we added 'dearcharts-live-input' class to them in PHP loop.

            // Also need to listen to dynamically added rows inputs
            $('#dearcharts-repeater-table').on('input', 'input', function () {
                updateChart();
            });


            // Media Uploader (Existing)
            $('#dearcharts_upload_csv_btn').click(function (e) {
                e.preventDefault();
                var image = wp.media({
                    title: 'Upload CSV',
                    multiple: false
                }).open()
                    .on('select', function (e) {
                        var uploaded_image = image.state().get('selection').first();
                        var image_url = uploaded_image.toJSON().url;
                        $('#dearcharts_csv_url').val(image_url);
                    });
            });

            // Repeater Table: Add Row
            $('#dearcharts-add-row').on('click', function (e) {
                e.preventDefault();
                var row = '<tr>' +
                    '<td><input type="text" class="dearcharts-live-input" name="dearcharts_manual_label[]" placeholder="Label" /></td>' +
                    '<td><input type="number" class="dearcharts-live-input" name="dearcharts_manual_value[]" placeholder="Value" step="any" /></td>' +
                    '<td><span class="dearcharts-remove-row">X</span></td>' +
                    '</tr>';
                $('#dearcharts-repeater-table tbody').append(row);
                // No need to call updateChart here, waiting for input.
            });

            // Repeater Table: Remove Row
            $('#dearcharts-repeater-table').on('click', '.dearcharts-remove-row', function (e) {
                e.preventDefault();
                $(this).closest('tr').remove();
                updateChart();
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

    // Save CSV URL
    if (isset($_POST['dearcharts_csv_url'])) {
        $csv_url = esc_url_raw($_POST['dearcharts_csv_url']);
        update_post_meta($post_id, '_dearcharts_csv_url', $csv_url);
    }

    // Save Pie Settings
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
    if ($hook == 'post-new.php' || $hook == 'post.php') {
        if ('dearcharts' === $post->post_type) {
            // Enqueue Chart.js from CDN
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_scripts');
