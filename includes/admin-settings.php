<?php
/**
 * Admin Settings - Meta Boxes and Admin Functionality
 * 
 * Handles all admin-side functionality including meta boxes,
 * data persistence, and admin column customization.
 */

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
 * Add Meta Boxes
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
    if ($post->post_status === 'auto-draft') {
        echo '<p>Save the post first to get the shortcode.</p>';
        return;
    }
    echo '<p>Use this shortcode to display the chart:</p>';
    echo '<code>[dearchart id="' . $post->ID . '"]</code>';
}

function dearcharts_render_tabbed_meta_box($post)
{
    $manual_data = get_post_meta($post->ID, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post->ID, '_dearcharts_csv_url', true);
    $active_source = get_post_meta($post->ID, '_dearcharts_active_source', true);
    $chart_type = get_post_meta($post->ID, '_dearcharts_type', true);
    $legend_pos = get_post_meta($post->ID, '_dearcharts_legend_pos', true);
    $palette = get_post_meta($post->ID, '_dearcharts_palette', true);
    $custom_colors = get_post_meta($post->ID, '_dearcharts_colors', true);

    if (empty($active_source))
        $active_source = 'manual';
    if (empty($palette))
        $palette = 'default';
    if (empty($chart_type))
        $chart_type = 'pie';
    if (empty($legend_pos))
        $legend_pos = 'top';

    $chart_title = $post->post_title ? $post->post_title : 'Untitled Chart';
    wp_nonce_field('dearcharts_save_meta_box_data', 'dearcharts_nonce');
    ?>
    <style>
        .dearcharts-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            margin-top: 10px;
        }

        .dearcharts-preview {
            flex: 1;
            min-width: 350px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .dearcharts-settings {
            flex: 1.2;
            min-width: 350px;
        }

        .dearcharts-tabs {
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }

        .dearcharts-tab-link {
            display: inline-block;
            cursor: pointer;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .dearcharts-tab-link:hover {
            color: #2271b1;
        }

        .dearcharts-tab-link.active {
            color: #2271b1;
            border-bottom: 2px solid #2271b1;
        }

        .dearcharts-tab-content {
            display: none;
        }

        .dearcharts-tab-content.active {
            display: block;
        }

        /* Blue Header Card Styling */
        .dearcharts-card {
            border: 1px solid #c2e0ff;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
            margin-bottom: 20px;
        }

        .dearcharts-card-header {
            background: #f0f7ff;
            padding: 12px 20px;
            border-bottom: 1px solid #c2e0ff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }

        .dearcharts-card-header strong {
            color: #1e40af;
            font-size: 14px;
        }

        .dearcharts-card-body {
            padding: 20px;
        }

        .dearcharts-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .dearcharts-table th,
        .dearcharts-table td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .dearcharts-table input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 10px;
        }

        .dearcharts-remove-row {
            color: #ef4444;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }

        /* Toggle */
        .dearcharts-source-toggle {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
            gap: 4px;
            max-width: 320px;
            margin-bottom: 20px;
        }

        .dearcharts-toggle-option {
            flex: 1;
        }

        .dearcharts-toggle-option input {
            display: none;
        }

        .dearcharts-toggle-option label {
            display: block;
            text-align: center;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
        }

        .dearcharts-toggle-option input:checked+label {
            background: #fff;
            color: #2271b1;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .dearcharts-input-group {
            margin-bottom: 20px;
        }

        .dearcharts-input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
        }

        #dearchartsCanvasContainer {
            height: 350px;
            width: 100%;
            position: relative;
        }
    </style>

    <div class="dearcharts-wrapper">
        <div class="dearcharts-preview">
            <h3 style="margin-top: 0; font-size: 16px;">Live Preview</h3>
            <div id="dearchartsCanvasContainer">
                <canvas id="dearchartsCanvas"></canvas>
            </div>
            <div id="dearcharts-no-data" style="display:none; padding-top: 100px; color: #94a3b8;">
                Add data to see preview
            </div>
        </div>

        <div class="dearcharts-settings">
            <div class="dearcharts-tabs">
                <span class="dearcharts-tab-link active" onclick="openDearChartsTab(event, 'tab-create-chart')">Create
                    Chart</span>
                <span class="dearcharts-tab-link" onclick="openDearChartsTab(event, 'tab-settings')">Chart Settings</span>
            </div>

            <div id="tab-create-chart" class="dearcharts-tab-content active">
                <div class="dearcharts-input-group" style="display: flex; align-items: center; gap: 20px;">
                    <label style="margin:0; min-width: 100px;">Chart Type:</label>
                    <select id="dearcharts_type" name="dearcharts_type" class="dearcharts-live-input large-text"
                        style="flex:1;">
                        <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie</option>
                        <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut</option>
                        <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar (Vertical)</option>
                        <option value="horizontalBar" <?php selected($chart_type, 'horizontalBar'); ?>>Bar (Horizontal)
                        </option>
                    </select>
                </div>

                <div class="dearcharts-input-group">
                    <label>Data Source:</label>
                    <div class="dearcharts-source-toggle">
                        <div class="dearcharts-toggle-option">
                            <input type="radio" id="source_csv" name="dearcharts_active_source" value="csv" <?php checked($active_source, 'csv'); ?> class="dearcharts-live-input">
                            <label for="source_csv">CSV File</label>
                        </div>
                        <div class="dearcharts-toggle-option">
                            <input type="radio" id="source_manual" name="dearcharts_active_source" value="manual" <?php checked($active_source, 'manual'); ?> class="dearcharts-live-input">
                            <label for="source_manual">Manual Entry</label>
                        </div>
                    </div>
                </div>

                <div id="panel-csv" class="dearcharts-card"
                    style="display: <?php echo $active_source === 'csv' ? 'block' : 'none'; ?>;">
                    <div class="dearcharts-card-header">
                        <strong>Import from CSV</strong>
                    </div>
                    <div class="dearcharts-card-body">
                        <input type="text" id="dearcharts_csv_url" name="dearcharts_csv_url" class="large-text"
                            value="<?php echo esc_attr($csv_url); ?>" placeholder="Enter CSV URL...">
                        <button type="button" class="button" id="dearcharts_upload_csv_btn"
                            style="margin-top: 10px; width: 100%;">Select from Media Library</button>
                    </div>
                </div>

                <div id="panel-manual" class="dearcharts-card"
                    style="display: <?php echo $active_source === 'manual' ? 'block' : 'none'; ?>;">
                    <div class="dearcharts-card-header">
                        <strong>Manual Data Entry</strong>
                    </div>
                    <div class="dearcharts-card-body">
                        <table class="dearcharts-table" id="dearcharts-repeater-table">
                            <thead>
                                <tr>
                                    <th>Labels</th>
                                    <?php
                                    $headers = ['Label', 'Series 1'];
                                    if (!empty($manual_data) && is_array($manual_data))
                                        $headers = $manual_data[0];
                                    for ($i = 1; $i < count($headers); $i++) {
                                        ?>
                                        <th style="position: relative;">
                                            <input type="text" class="dearcharts-series-label dearcharts-live-input"
                                                name="dearcharts_manual_data[0][]" value="<?php echo esc_attr($headers[$i]); ?>"
                                                style="font-weight: 600; border: none; background: transparent; padding: 0;">
                                            <span class="dearcharts-remove-column"
                                                style="position: absolute; right: 0; top: 50%; transform: translateY(-50%); color: red; cursor: pointer;">×</span>
                                        </th>
                                        <?php
                                    }
                                    ?>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $data_rows = (!empty($manual_data) && count($manual_data) > 1) ? array_slice($manual_data, 1) : [['', '']];
                                foreach ($data_rows as $ri => $row) {
                                    $row_idx = $ri + 1;
                                    ?>
                                    <tr>
                                        <td><input type="text" name="dearcharts_manual_data[<?php echo $row_idx; ?>][]"
                                                value="<?php echo esc_attr($row[0]); ?>" class="dearcharts-live-input"></td>
                                        <?php for ($ci = 1; $ci < count($headers); $ci++) { ?>
                                            <td><input type="number" step="any"
                                                    name="dearcharts_manual_data[<?php echo $row_idx; ?>][]"
                                                    value="<?php echo esc_attr($row[$ci]); ?>" class="dearcharts-live-input"></td>
                                        <?php } ?>
                                        <td style="text-align: center;"><span class="dearcharts-remove-row">×</span></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <div style="display: flex; gap:10px;">
                            <button type="button" class="button" id="dearcharts-add-column">Add Column</button>
                            <button type="button" class="button button-primary" id="dearcharts-add-row" style="flex:1;">Add
                                Row</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-settings" class="dearcharts-tab-content">
                <div class="dearcharts-input-group">
                    <label>Legend Position:</label>
                    <select name="dearcharts_legend_pos" class="dearcharts-live-input">
                        <option value="top" <?php selected($legend_pos, 'top'); ?>>Top</option>
                        <option value="bottom" <?php selected($legend_pos, 'bottom'); ?>>Bottom</option>
                        <option value="left" <?php selected($legend_pos, 'left'); ?>>Left</option>
                        <option value="right" <?php selected($legend_pos, 'right'); ?>>Right</option>
                    </select>
                </div>
                <div class="dearcharts-input-group">
                    <label>Color Palette:</label>
                    <select id="dearcharts_palette" name="dearcharts_palette" class="dearcharts-live-input">
                        <option value="default" <?php selected($palette, 'default'); ?>>Default Modern</option>
                        <option value="pastel" <?php selected($palette, 'pastel'); ?>>Pastel Soft</option>
                        <option value="ocean" <?php selected($palette, 'ocean'); ?>>Ocean Blues</option>
                        <option value="sunset" <?php selected($palette, 'sunset'); ?>>Sunset Warm</option>
                        <option value="neon" <?php selected($palette, 'neon'); ?>>Neon Vibrant</option>
                    </select>
                </div>
                <div class="dearcharts-input-group">
                    <label>Custom Hex Colors (Optional):</label>
                    <textarea name="dearcharts_colors" rows="3"
                        class="dearcharts-live-input large-text"><?php echo esc_attr($custom_colors); ?></textarea>
                    <p class="description">Comma separated hex codes (e.g. #FF0000, #00FF00)</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openDearChartsTab(evt, tabName) {
            jQuery('.dearcharts-tab-content').removeClass('active');
            jQuery('.dearcharts-tab-link').removeClass('active');
            jQuery('#' + tabName).addClass('active');
            jQuery(evt.currentTarget).addClass('active');
        }

        jQuery(document).ready(function ($) {
            var myChart = null;
            var csvParsedData = { labels: [], datasets: [] };
            var ctx = document.getElementById('dearchartsCanvas').getContext('2d');
            var palettes = {
                'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
                'pastel': ['#93c5fd', '#6ee7b7', '#fcd34d', '#fca5a5', '#c4b5fd', '#fbcfe8'],
                'ocean': ['#1e40af', '#1d4ed8', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'],
                'sunset': ['#991b1b', '#b91c1c', '#dc2626', '#ef4444', '#f87171', '#fca5a5'],
                'neon': ['#f0abfc', '#818cf8', '#2dd4bf', '#a3e635', '#fde047', '#fb923c']
            };

            function getActiveSource() { return $('input[name="dearcharts_active_source"]:checked').val(); }

            function getManualData() {
                var labels = [];
                var datasets = [];
                var headerInputs = $('#dearcharts-repeater-table thead .dearcharts-series-label');
                headerInputs.each(function (i) {
                    datasets.push({ label: $(this).val() || 'Series ' + (i + 1), data: [] });
                });

                $('#dearcharts-repeater-table tbody tr').each(function () {
                    var inputs = $(this).find('input');
                    labels.push(inputs.eq(0).val() || 'Unnamed');
                    for (var i = 0; i < datasets.length; i++) {
                        var val = parseFloat(inputs.eq(i + 1).val());
                        datasets[i].data.push(isNaN(val) ? 0 : val);
                    }
                });
                return { labels: labels, datasets: datasets };
            }

            function updateChart() {
                var source = getActiveSource();
                $('#panel-csv').toggle(source === 'csv');
                $('#panel-manual').toggle(source === 'manual');

                var data = (source === 'manual') ? getManualData() : csvParsedData;
                if (!data.labels || data.labels.length === 0) {
                    $('#dearchartsCanvas').hide(); $('#dearcharts-no-data').show();
                    return;
                }
                $('#dearchartsCanvas').show(); $('#dearcharts-no-data').hide();

                var type = $('#dearcharts_type').val();
                var palette = palettes[$('#dearcharts_palette').val()] || palettes.default;

                var datasets = data.datasets.map(function (ds, i) {
                    var dsColor;
                    if (data.datasets.length > 1) {
                        dsColor = palette[i % palette.length];
                    } else {
                        // For single series, generate colors for each point
                        var colors = [];
                        for (var j = 0; j < ds.data.length; j++) colors.push(palette[j % palette.length]);
                        dsColor = colors;
                    }

                    return {
                        label: ds.label,
                        data: ds.data,
                        backgroundColor: dsColor,
                        borderColor: (type === 'bar' || type === 'horizontalBar') ? '#fff' : 'transparent',
                        borderWidth: 2
                    };
                });

                var config = {
                    type: type === 'horizontalBar' ? 'bar' : type,
                    data: { labels: data.labels, datasets: datasets },
                    options: {
                        indexAxis: type === 'horizontalBar' ? 'y' : 'x',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: $('select[name="dearcharts_legend_pos"]').val() } }
                    }
                };

                if (myChart) myChart.destroy();
                myChart = new Chart(ctx, config);
            }

            // Events
            $('.dearcharts-live-input').on('change input', updateChart);
            $('#dearcharts_csv_url').on('input', function () {
                var url = $(this).val();
                if (!url) return;
                fetch(url).then(r => r.text()).then(t => {
                    var lines = t.split('\n');
                    var labels = [], values = [];
                    lines.forEach(l => {
                        var p = l.split(',');
                        if (p.length >= 2) { labels.push(p[0].trim()); values.push(parseFloat(p[1])); }
                    });
                    csvParsedData = { labels: labels, datasets: [{ label: 'CSV Data', data: values }] };
                    updateChart();
                });
            });

            $('#dearcharts-add-row').click(function () {
                var cols = $('#dearcharts-repeater-table thead th').length - 1;
                var ri = $('#dearcharts-repeater-table tbody tr').length + 1;
                var row = '<tr><td><input type="text" name="dearcharts_manual_data[' + ri + '][]" class="dearcharts-live-input"></td>';
                for (var i = 1; i < cols; i++) row += '<td><input type="number" step="any" name="dearcharts_manual_data[' + ri + '][]" class="dearcharts-live-input"></td>';
                row += '<td style="text-align:center;"><span class="dearcharts-remove-row">×</span></td></tr>';
                $('#dearcharts-repeater-table tbody').append(row);
                updateChart();
            });

            $('#dearcharts-add-column').click(function () {
                var ci = $('#dearcharts-repeater-table thead th').length - 1;
                var th = '<th style="position:relative;"><input type="text" class="dearcharts-series-label dearcharts-live-input" name="dearcharts_manual_data[0][]" value="Series ' + ci + '" style="font-weight:600; border:none; background:transparent; padding:0;"><span class="dearcharts-remove-column" style="position:absolute; right:0; top:50%; transform:translateY(-50%); color:red; cursor:pointer;">×</span></th>';
                $(th).insertBefore('#dearcharts-repeater-table thead th:last');
                $('#dearcharts-repeater-table tbody tr').each(function (i) {
                    var ri = i + 1;
                    $('<td><input type="number" step="any" name="dearcharts_manual_data[' + ri + '][]" class="dearcharts-live-input"></td>').insertBefore($(this).find('td:last'));
                });
                updateChart();
            });

            $(document).on('click', '.dearcharts-remove-row', function () { $(this).closest('tr').remove(); updateChart(); });
            $(document).on('click', '.dearcharts-remove-column', function () {
                var idx = $(this).closest('th').index();
                $('#dearcharts-repeater-table thead th').eq(idx).remove();
                $('#dearcharts-repeater-table tbody tr').each(function () { $(this).find('td').eq(idx).remove(); });
                updateChart();
            });

            // Media
            $('#dearcharts_upload_csv_btn').click(function (e) {
                var frame = wp.media({ title: 'Select CSV', multiple: false });
                frame.on('select', function () {
                    var url = frame.state().get('selection').first().toJSON().url;
                    $('#dearcharts_csv_url').val(url).trigger('input');
                }).open();
            });

            updateChart();
        });
    </script>
    <?php
}

function dearcharts_save_meta_box_data($post_id)
{
    if (!isset($_POST['dearcharts_nonce']) || !wp_verify_nonce($_POST['dearcharts_nonce'], 'dearcharts_save_meta_box_data'))
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    if (isset($_POST['dearcharts_manual_data'])) {
        $data = $_POST['dearcharts_manual_data'];
        $sanitized = array();
        foreach ($data as $ri => $row) {
            $srow = array();
            foreach ($row as $ci => $val) {
                $srow[] = ($ri === 0 || $ci === 0) ? sanitize_text_field($val) : floatval($val);
            }
            $sanitized[] = $srow;
        }
        update_post_meta($post_id, '_dearcharts_manual_data', $sanitized);
    }

    $fields = ['_dearcharts_csv_url', '_dearcharts_active_source', '_dearcharts_type', '_dearcharts_legend_pos', '_dearcharts_palette', '_dearcharts_colors'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}
add_action('save_post', 'dearcharts_save_meta_box_data');

function dearcharts_admin_scripts($hook)
{
    global $post;
    if ($hook == 'post-new.php' || $hook == 'post.php') {
        if ($post && 'dearcharts' === $post->post_type) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_scripts');
