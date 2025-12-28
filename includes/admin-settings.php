<?php
/**
 * Admin Settings - Meta Boxes and Admin Functionality
 * 
 * Handles all admin-side functionality including meta boxes,
 * data persistence, and professional split-screen UI.
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
        'Chart Configuration',
        'dearcharts_render_tabbed_meta_box',
        'dearcharts',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'dearcharts_register_meta_box');

function dearcharts_render_tabbed_meta_box($post)
{
    $manual_data = get_post_meta($post->ID, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post->ID, '_dearcharts_csv_url', true);
    $active_source = get_post_meta($post->ID, '_dearcharts_active_source', true);
    $chart_type = get_post_meta($post->ID, '_dearcharts_type', true);
    $legend_pos = get_post_meta($post->ID, '_dearcharts_legend_pos', true);
    $palette = get_post_meta($post->ID, '_dearcharts_palette', true);
    $custom_colors = get_post_meta($post->ID, '_dearcharts_colors', true);
    $is_donut = get_post_meta($post->ID, '_dearcharts_donut', true);

    if (empty($active_source))
        $active_source = 'manual';
    if (empty($palette))
        $palette = 'default';
    if (empty($chart_type))
        $chart_type = 'pie';
    if (empty($legend_pos))
        $legend_pos = 'top';

    wp_nonce_field('dearcharts_save_meta_box_data', 'dearcharts_nonce');
    ?>
    <style>
        .dearcharts-wrapper {
            display: flex;
            width: 100%;
            gap: 30px;
            margin-top: 10px;
            align-items: flex-start;
        }

        .dearcharts-preview {
            flex: 1;
            position: sticky;
            top: 40px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .dearcharts-settings {
            flex: 1 1 0;
            min-width: 0;
        }

        .dearcharts-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f1f5f9;
        }

        .dearcharts-tab-link {
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .dearcharts-tab-link:hover {
            color: #2271b1;
        }

        .dearcharts-tab-link.active {
            color: #2271b1;
            border-bottom-color: #2271b1;
        }

        .dearcharts-tab-content {
            display: none;
        }

        .dearcharts-tab-content.active {
            display: block;
        }

        .dearcharts-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            margin-bottom: 20px;
        }

        .dearcharts-card-header {
            background: #f8fafc;
            padding: 12px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dearcharts-card-header strong {
            color: #1e293b;
            font-size: 13px;
        }

        .dearcharts-card-body {
            padding: 20px;
        }

        .dc-manual-entry-panel {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }

        .dc-table-responsive-wrapper {
            width: 100%;
            display: block;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 15px;
            position: relative;
        }

        .dc-table-responsive-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .dc-table-responsive-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .dc-table-responsive-wrapper::-webkit-scrollbar-thumb {
            background: #2271b1;
            border-radius: 4px;
        }

        .dc-table-responsive-wrapper::-webkit-scrollbar-thumb:hover {
            background: #135e96;
        }

        .dearcharts-table {
            min-width: 100%;
            table-layout: auto;
            border-collapse: collapse;
        }

        .dearcharts-table th,
        .dearcharts-table td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
            min-width: 100px;
        }

        .dearcharts-table th:first-child,
        .dearcharts-table td:first-child {
            min-width: 150px;
        }

        .dearcharts-table input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 13px;
        }

        .dearcharts-input-group {
            margin-bottom: 20px;
        }

        .dearcharts-input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
        }

        .dearcharts-source-toggle {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
            gap: 4px;
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
            padding: 8px;
            cursor: pointer;
            border-radius: 6px;
            font-size: 13px;
            color: #64748b;
        }

        .dearcharts-toggle-option input:checked+label {
            background: #fff;
            color: #2271b1;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        #dearchartsCanvasContainer {
            height: 400px;
            width: 100%;
            position: relative;
        }
    </style>

    <div class="dearcharts-wrapper">
        <!-- LEFT: PREVIEW -->
        <div class="dearcharts-preview">
            <h3 style="margin: 0 0 20px 0; font-size: 16px; color: #1e293b;">Live Chart Preview</h3>
            <div id="dearchartsCanvasContainer">
                <canvas id="dearchartsCanvas"></canvas>
            </div>
            <div id="dearcharts-no-data" style="display:none; padding-top: 150px; color: #94a3b8;">
                Add data to see preview
            </div>
        </div>

        <!-- RIGHT: SETTINGS -->
        <div class="dearcharts-settings">
            <div class="dearcharts-tabs">
                <span class="dearcharts-tab-link active" data-tab="tab-create">Create Chart</span>
                <span class="dearcharts-tab-link" data-tab="tab-settings">Chart Settings</span>
            </div>

            <!-- TAB: CREATE -->
            <div id="tab-create" class="dearcharts-tab-content active">
                <div class="dearcharts-input-group">
                    <label>Chart Type</label>
                    <select id="dearcharts_type" name="_dearcharts_type" class="dearcharts-live-input large-text">
                        <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie Chart</option>
                        <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut Chart</option>
                        <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar Chart (Vertical)</option>
                        <option value="horizontalBar" <?php selected($chart_type, 'horizontalBar'); ?>>Bar Chart
                            (Horizontal)</option>
                        <option value="line" <?php selected($chart_type, 'line'); ?>>Line Chart</option>
                    </select>
                </div>

                <div class="dearcharts-input-group">
                    <label>Data Source</label>
                    <div class="dearcharts-source-toggle">
                        <div class="dearcharts-toggle-option">
                            <input type="radio" id="src_csv" name="_dearcharts_active_source" value="csv" <?php checked($active_source, 'csv'); ?> class="dearcharts-live-input">
                            <label for="src_csv">CSV File</label>
                        </div>
                        <div class="dearcharts-toggle-option">
                            <input type="radio" id="src_manual" name="_dearcharts_active_source" value="manual" <?php checked($active_source, 'manual'); ?> class="dearcharts-live-input">
                            <label for="src_manual">Manual Entry</label>
                        </div>
                    </div>
                </div>

                <!-- CARDS -->
                <div id="panel-csv" class="dearcharts-card"
                    style="display: <?php echo $active_source === 'csv' ? 'block' : 'none'; ?>;">
                    <div class="dearcharts-card-header"><strong>Import from CSV</strong></div>
                    <div class="dearcharts-card-body">
                        <input type="text" id="dearcharts_csv_url" name="_dearcharts_csv_url" class="large-text"
                            value="<?php echo esc_attr($csv_url); ?>" placeholder="CSV URL...">
                        <button type="button" class="button dearcharts-upload-btn"
                            style="margin-top: 10px; width: 100%;">Select from Media</button>
                    </div>
                </div>

                <div id="panel-manual" class="dearcharts-card dc-manual-entry-panel"
                    style="display: <?php echo $active_source === 'manual' ? 'block' : 'none'; ?>;">
                    <div class="dearcharts-card-header"><strong>Manual Data Entry</strong></div>
                    <div class="dearcharts-card-body">
                        <div class="dc-table-responsive-wrapper">
                            <table class="dearcharts-table" id="dearcharts-repeater-table">
                                <thead>
                                    <tr>
                                        <th>Labels</th>
                                        <?php
                                        $headers = ['Label', 'Series 1'];
                                        if (!empty($manual_data))
                                            $headers = $manual_data[0];
                                        for ($i = 1; $i < count($headers); $i++): ?>
                                            <th>
                                                <div style="display:flex; align-items:center;">
                                                    <input type="text" name="dearcharts_manual_data[0][]"
                                                        value="<?php echo esc_attr($headers[$i]); ?>"
                                                        class="dearcharts-live-input"
                                                        style="border:none; background:transparent; font-weight:600; padding:0;">
                                                    <span class="dearcharts-remove-col"
                                                        style="cursor:pointer; margin-left:5px;">×</span>
                                                </div>
                                            </th>
                                        <?php endfor; ?>
                                        <th style="min-width:30px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rows = (!empty($manual_data) && count($manual_data) > 1) ? array_slice($manual_data, 1) : [['', '']];
                                    foreach ($rows as $ri => $row): ?>
                                        <tr>
                                            <td><input type="text" name="dearcharts_manual_data[<?php echo $ri + 1; ?>][]"
                                                    value="<?php echo esc_attr($row[0]); ?>" class="dearcharts-live-input"></td>
                                            <?php for ($ci = 1; $ci < count($headers); $ci++): ?>
                                                <td><input type="number" step="any"
                                                        name="dearcharts_manual_data[<?php echo $ri + 1; ?>][]"
                                                        value="<?php echo esc_attr($row[$ci]); ?>" class="dearcharts-live-input">
                                                </td>
                                            <?php endfor; ?>
                                            <td><span class="dearcharts-remove-row" style="cursor:pointer;">×</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="button" id="dearcharts-add-col">Add Column</button>
                            <button type="button" class="button button-primary" id="dearcharts-add-row" style="flex:1;">Add
                                Row</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: SETTINGS -->
            <div id="tab-settings" class="dearcharts-tab-content">
                <div class="dearcharts-input-group">
                    <label>Legend Position</label>
                    <select name="_dearcharts_legend_pos" class="dearcharts-live-input">
                        <option value="top" <?php selected($legend_pos, 'top'); ?>>Top</option>
                        <option value="bottom" <?php selected($legend_pos, 'bottom'); ?>>Bottom</option>
                        <option value="left" <?php selected($legend_pos, 'left'); ?>>Left</option>
                        <option value="right" <?php selected($legend_pos, 'right'); ?>>Right</option>
                        <option value="none" <?php selected($legend_pos, 'none'); ?>>Hidden</option>
                    </select>
                </div>

                <div class="dearcharts-input-group">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="checkbox" name="_dearcharts_donut" value="1" <?php checked($is_donut, '1'); ?>
                            class="dearcharts-live-input">
                        Donut Style (For Pie/Doughnut)
                    </label>
                </div>

                <div class="dearcharts-input-group">
                    <label>Color Palette</label>
                    <select id="dearcharts_palette" name="_dearcharts_palette" class="dearcharts-live-input">
                        <option value="default" <?php selected($palette, 'default'); ?>>Modern Blue</option>
                        <option value="pastel" <?php selected($palette, 'pastel'); ?>>Soft Pastel</option>
                        <option value="ocean" <?php selected($palette, 'ocean'); ?>>Deep Ocean</option>
                        <option value="sunset" <?php selected($palette, 'sunset'); ?>>Warm Sunset</option>
                    </select>
                </div>

                <div class="dearcharts-input-group">
                    <label>Custom Hex Colors</label>
                    <textarea name="_dearcharts_colors" rows="3" class="dearcharts-live-input large-text"
                        placeholder="#FF0000, #00FF00..."><?php echo esc_attr($custom_colors); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function ($) {
            var myChart = null;
            var ctx = document.getElementById('dearchartsCanvas').getContext('2d');
            var palettes = {
                'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                'pastel': ['#93c5fd', '#6ee7b7', '#fcd34d', '#fca5a5', '#c4b5fd'],
                'ocean': ['#1e40af', '#1d4ed8', '#2563eb', '#3b82f6', '#60a5fa'],
                'sunset': ['#991b1b', '#b91c1c', '#dc2626', '#ef4444', '#f87171']
            };

            // Tabs
            $('.dearcharts-tab-link').click(function () {
                $('.dearcharts-tab-link, .dearcharts-tab-content').removeClass('active');
                $(this).addClass('active');
                $('#' + $(this).data('tab')).addClass('active');
            });

            function getActiveSource() { return $('input[name="_dearcharts_active_source"]:checked').val(); }

            function getManualData() {
                var labels = [];
                var datasets = [];
                var headers = $('#dearcharts-repeater-table thead input');
                headers.each(function (i) { datasets.push({ label: $(this).val() || 'Series ' + (i + 1), data: [] }); });

                $('#dearcharts-repeater-table tbody tr').each(function () {
                    var inputs = $(this).find('input');
                    labels.push(inputs.eq(0).val() || 'Item');
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

                var data = (source === 'manual') ? getManualData() : { labels: [], datasets: [] };
                // CSV logic would go here if needed for live preview (simplified for manual)

                if (!data.labels.length) { $('#dearchartsCanvas').hide(); $('#dearcharts-no-data').show(); return; }
                $('#dearchartsCanvas').show(); $('#dearcharts-no-data').hide();

                var type = $('#dearcharts_type').val();
                var palette = palettes[$('#dearcharts_palette').val()] || palettes.default;
                var legendPos = $('select[name="_dearcharts_legend_pos"]').val();
                var isDonut = $('input[name="_dearcharts_donut"]').is(':checked');

                var datasets = data.datasets.map(function (ds, i) {
                    var colors = (data.datasets.length > 1) ? palette[i % palette.length] : data.labels.map((_, j) => palette[j % palette.length]);
                    return {
                        label: ds.label,
                        data: ds.data,
                        backgroundColor: colors,
                        borderColor: colors,
                        borderWidth: 1,
                        tension: 0.3,
                        fill: type === 'line' ? false : true
                    };
                });

                var finalType = type === 'horizontalBar' ? 'bar' : (type === 'doughnut' || isDonut ? 'doughnut' : type);

                if (myChart) myChart.destroy();
                myChart = new Chart(ctx, {
                    type: finalType,
                    data: { labels: data.labels, datasets: datasets },
                    options: {
                        indexAxis: type === 'horizontalBar' ? 'y' : 'x',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: legendPos !== 'none', position: legendPos === 'none' ? 'top' : legendPos }
                        },
                        scales: (['bar', 'horizontalBar', 'line'].includes(type)) ? { y: { beginAtZero: true } } : {}
                    }
                });
            }

            // Events
            $(document).on('input change', '.dearcharts-live-input', updateChart);

            $('#dearcharts-add-row').click(function () {
                var cols = $('#dearcharts-repeater-table thead th').length - 1;
                var ri = $('#dearcharts-repeater-table tbody tr').length + 1;
                var html = '<tr><td><input type="text" name="dearcharts_manual_data[' + ri + '][]" class="dearcharts-live-input"></td>';
                for (var i = 1; i < cols; i++) html += '<td><input type="number" step="any" name="dearcharts_manual_data[' + ri + '][]" class="dearcharts-live-input"></td>';
                html += '<td><span class="dearcharts-remove-row" style="cursor:pointer;">×</span></td></tr>';
                $('#dearcharts-repeater-table tbody').append(html);
            });

            $('#dearcharts-add-col').click(function () {
                var ci = $('#dearcharts-repeater-table thead th').length - 1;
                var th = '<th><div style="display:flex; align-items:center;"><input type="text" name="dearcharts_manual_data[0][]" value="Series ' + ci + '" class="dearcharts-live-input" style="border:none; background:transparent; font-weight:600; padding:0;"><span class="dearcharts-remove-col" style="cursor:pointer; margin-left:5px;">×</span></div></th>';
                $(th).insertBefore('#dearcharts-repeater-table thead th:last');
                $('#dearcharts-repeater-table tbody tr').each(function (i) {
                    $('<td><input type="number" step="any" name="dearcharts_manual_data[' + (i + 1) + '][]" class="dearcharts-live-input"></td>').insertBefore($(this).find('td:last'));
                });
                updateChart();
            });

            $(document).on('click', '.dearcharts-remove-row', function () { $(this).closest('tr').remove(); updateChart(); });
            $(document).on('click', '.dearcharts-remove-col', function () {
                var idx = $(this).closest('th').index();
                $('#dearcharts-repeater-table thead th').eq(idx).remove();
                $('#dearcharts-repeater-table tbody tr').each(function () { $(this).find('td').eq(idx).remove(); });
                updateChart();
            });

            $('.dearcharts-upload-btn').click(function () {
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
        $sanitized = array();
        foreach ($_POST['dearcharts_manual_data'] as $ri => $row) {
            $srow = array();
            foreach ($row as $ci => $val) {
                $srow[] = ($ri === 0 || $ci === 0) ? sanitize_text_field($val) : floatval($val);
            }
            $sanitized[] = $srow;
        }
        update_post_meta($post_id, '_dearcharts_manual_data', $sanitized);
    }

    $fields = ['_dearcharts_csv_url', '_dearcharts_active_source', '_dearcharts_type', '_dearcharts_legend_pos', '_dearcharts_palette', '_dearcharts_colors', '_dearcharts_donut'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        } else {
            delete_post_meta($post_id, $field);
        }
    }
}
add_action('save_post', 'dearcharts_save_meta_box_data');

function dearcharts_admin_scripts($hook)
{
    global $post;
    if (($hook == 'post-new.php' || $hook == 'post.php') && $post && 'dearcharts' === $post->post_type) {
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_scripts');
