<?php
/**
 * Admin Settings - Professional Split-Screen UI
 * 
 * Handles Admin UI for DearCharts plugin with Live Preview and Settings.
 */

if (!defined('ABSPATH'))
    exit;

/**
 * 1. Admin List Columns
 */
function dearcharts_add_admin_columns($columns)
{
    $columns['chart_id'] = 'ID';
    $columns['shortcode'] = 'Shortcode';
    return $columns;
}
add_filter('manage_dearcharts_posts_columns', 'dearcharts_add_admin_columns');

function dearcharts_render_admin_columns($column, $post_id)
{
    if ($column === 'chart_id') {
        echo $post_id;
    } elseif ($column === 'shortcode') {
        echo '<code>[dearchart id="' . $post_id . '"]</code>';
    }
}
add_action('manage_dearcharts_posts_custom_column', 'dearcharts_render_admin_columns', 10, 2);

/**
 * 2. Register Meta Boxes
 * Mission: Restore dearcharts_add_custom_metaboxes and sidebar box.
 */
function dearcharts_add_custom_metaboxes()
{
    add_meta_box('dearcharts_main_box', 'Chart Configuration', 'dearcharts_render_main_box', 'dearcharts', 'normal', 'high');
    add_meta_box('dearcharts_usage_box', 'Chart Shortcodes', 'dearcharts_render_usage_box', 'dearcharts', 'side', 'low');
}
add_action('add_meta_boxes', 'dearcharts_add_custom_metaboxes');

/**
 * 2. Sidebar Shortcode Box
 */
function dearcharts_render_usage_box($post)
{
    echo '<p>Use these shortcodes to display your chart:</p>';
    echo '<strong>Title:</strong><br><code>[dearchart_title id="' . $post->ID . '"]</code><br><br>';
    echo '<strong>Chart:</strong><br><code>[dearchart id="' . $post->ID . '"]</code>';
}

/**
 * 3. Main Configuration UI
 */
function dearcharts_render_main_box($post)
{
    $type = get_post_meta($post->ID, '_dearcharts_type', true) ?: 'pie';
    $csv_url = get_post_meta($post->ID, '_dearcharts_csv_url', true);
    $active_source = get_post_meta($post->ID, '_dearcharts_active_source', true) ?: 'manual';
    $manual_data = get_post_meta($post->ID, '_dearcharts_manual_data', true);

    if (!is_array($manual_data) || empty($manual_data)) {
        $manual_data = [['Labels', 'Series 1'], ['', '']];
    }

    wp_nonce_field('dearcharts_save_action', 'dearcharts_nonce');
    wp_enqueue_media();
    ?>
    <style>
        .dc-admin-wrapper {
            display: flex;
            gap: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            margin-top: 10px;
        }

        .dc-preview-panel {
            flex: 1;
            border: 1px solid #ccd0d4;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            min-width: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 40px;
        }

        .dc-settings-panel {
            flex: 1;
            min-width: 0;
        }

        .dc-tab-nav {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .dc-tab-btn {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
        }

        .dc-tab-btn.active {
            color: #2271b1;
            border-bottom-color: #2271b1;
        }

        .dc-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 20px;
            overflow: hidden;
            background: #fff;
        }

        .dc-card-header {
            background: #f8fafc;
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            color: #1e293b;
        }

        .dc-card-body {
            padding: 20px;
        }

        .dc-table-wrapper {
            overflow-x: auto;
            width: 100%;
            margin-bottom: 15px;
        }

        table.dc-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 400px;
        }

        table.dc-table th,
        table.dc-table td {
            padding: 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        table.dc-table input {
            width: 100%;
            border: 1px solid #cbd5e1;
            padding: 6px;
            border-radius: 4px;
            font-size: 13px;
        }

        .dc-delete-row {
            color: #ef4444;
            cursor: pointer;
            font-size: 18px;
            text-align: center;
        }

        .dc-source-toggle {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
            margin-bottom: 15px;
            gap: 4px;
        }

        .dc-toggle-opt {
            flex: 1;
            text-align: center;
            padding: 8px;
            cursor: pointer;
            border-radius: 6px;
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            transition: 0.2s;
        }

        .dc-toggle-opt.active {
            background: #fff;
            color: #2271b1;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        #dc-status {
            margin-top: 15px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            text-align: center;
            display: none;
        }
    </style>

    <div class="dc-admin-wrapper">
        <!-- LEFT: LIVE PREVIEW -->
        <div class="dc-preview-panel">
            <h3 style="margin-top:0; font-size: 16px;">Live Chart Preview</h3>
            <div style="height: 350px; position: relative;">
                <canvas id="dc-live-chart"></canvas>
            </div>
            <div id="dc-status"></div>
        </div>

        <!-- RIGHT: SETTINGS -->
        <div class="dc-settings-panel">
            <div class="dc-tab-nav">
                <div class="dc-tab-btn active" onclick="dearcharts_switch_tab(event, 'dc-tab-create')">Create Chart</div>
                <div class="dc-tab-btn" onclick="dearcharts_switch_tab(event, 'dc-tab-settings')">Chart Settings</div>
            </div>

            <div id="dc-tab-create" class="dc-tab-content">
                <label><strong>Chart Type:</strong></label><br>
                <select name="dearcharts_type" id="dearcharts_type" style="width:100%; margin: 10px 0 20px;"
                    onchange="dearcharts_update_live_preview()">
                    <option value="pie" <?php selected($type, 'pie'); ?>>Pie Chart</option>
                    <option value="doughnut" <?php selected($type, 'doughnut'); ?>>Doughnut Chart</option>
                    <option value="bar" <?php selected($type, 'bar'); ?>>Bar Chart (Vertical)</option>
                    <option value="horizontalBar" <?php selected($type, 'horizontalBar'); ?>>Bar Chart (Horizontal)</option>
                    <option value="line" <?php selected($type, 'line'); ?>>Line Chart</option>
                </select>

                <div class="dc-source-toggle">
                    <input type="hidden" name="dearcharts_active_source" id="dearcharts_active_source"
                        value="<?php echo esc_attr($active_source); ?>">
                    <div class="dc-toggle-opt <?php echo $active_source === 'manual' ? 'active' : ''; ?>"
                        onclick="dearcharts_set_source('manual')">Manual Entry</div>
                    <div class="dc-toggle-opt <?php echo $active_source === 'csv' ? 'active' : ''; ?>"
                        onclick="dearcharts_set_source('csv')">CSV File</div>
                </div>

                <!-- CSV CARD -->
                <div id="dc-panel-csv" class="dc-card"
                    style="display: <?php echo $active_source === 'csv' ? 'block' : 'none'; ?>;">
                    <div class="dc-card-header">Import from CSV</div>
                    <div class="dc-card-body">
                        <input type="text" id="dc_csv_url" name="dearcharts_csv_url"
                            value="<?php echo esc_attr($csv_url); ?>" style="width:70%;" placeholder="https://...">
                        <button type="button" class="button" id="dc_upload_csv_btn">Select File</button>
                    </div>
                </div>

                <!-- MANUAL CARD -->
                <div id="dc-panel-manual" class="dc-card"
                    style="display: <?php echo $active_source === 'manual' ? 'block' : 'none'; ?>;">
                    <div class="dc-card-header">Manual Data Entry</div>
                    <div class="dc-card-body">
                        <div class="dc-table-wrapper">
                            <table id="dc-manual-table" class="dc-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($manual_data[0] as $header): ?>
                                            <th><input type="text" name="dearcharts_manual_data[0][]"
                                                    value="<?php echo esc_attr($header); ?>"
                                                    oninput="dearcharts_update_live_preview()"></th>
                                        <?php endforeach; ?>
                                        <th style="width:30px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 1; $i < count($manual_data); $i++): ?>
                                        <tr>
                                            <?php foreach ($manual_data[$i] as $cell): ?>
                                                <td><input type="text" name="dearcharts_manual_data[<?php echo $i; ?>][]"
                                                        value="<?php echo esc_attr($cell); ?>"
                                                        oninput="dearcharts_update_live_preview()"></td>
                                            <?php endforeach; ?>
                                            <td class="dc-delete-row"
                                                onclick="jQuery(this).closest('tr').remove(); dearcharts_update_live_preview();">
                                                ×</td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="button" onclick="dearcharts_add_row()">+ Add Row</button>
                        <button type="button" class="button" onclick="dearcharts_add_column()">+ Add Column</button>
                    </div>
                </div>
            </div>

            <div id="dc-tab-settings" class="dc-tab-content" style="display:none;">
                <p style="color: #64748b; padding: 20px; background: #f8fafc; border-radius: 8px;">Advanced styling options
                    (colors, legend position, etc.) will be available soon.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var dearchartsLiveChart = null;

        function dearcharts_switch_tab(evt, tabId) {
            jQuery('.dc-tab-content').hide();
            jQuery('.dc-tab-btn').removeClass('active');
            jQuery('#' + tabId).show();
            jQuery(evt.currentTarget).addClass('active');
        }

        function dearcharts_set_source(source) {
            jQuery('#dearcharts_active_source').val(source);
            jQuery('.dc-toggle-opt').removeClass('active');
            if (source === 'manual') jQuery('.dc-toggle-opt').first().addClass('active');
            else jQuery('.dc-toggle-opt').last().addClass('active');

            jQuery('#dc-panel-manual').toggle(source === 'manual');
            jQuery('#dc-panel-csv').toggle(source === 'csv');
            dearcharts_update_live_preview();
        }

        function dearcharts_add_row() {
            var colCount = jQuery('#dc-manual-table thead th').length - 1;
            var rowIdx = Date.now();
            var html = '<tr>';
            for (var i = 0; i < colCount; i++) {
                html += '<td><input type="text" name="dearcharts_manual_data[' + rowIdx + '][]" oninput="dearcharts_update_live_preview()"></td>';
            }
            html += '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview();">×</td></tr>';
            jQuery('#dc-manual-table tbody').append(html);
        }

        function dearcharts_add_column() {
            var colIdx = jQuery('#dc-manual-table thead th').length - 1;
            jQuery('<th><input type="text" name="dearcharts_manual_data[0][]" value="Series ' + colIdx + '" oninput="dearcharts_update_live_preview()"></th>').insertBefore('#dc-manual-table thead th:last');
            jQuery('#dc-manual-table tbody tr').each(function () {
                var rowIdx = Date.now() + Math.random();
                jQuery('<td><input type="text" name="dearcharts_manual_data[' + rowIdx + '][]" oninput="dearcharts_update_live_preview()"></td>').insertBefore(jQuery(this).find('td:last'));
            });
            dearcharts_update_live_preview();
        }

        /**
         * Mission Fix: Capture chartType first and handle CSV with try...catch.
         */
        async function dearcharts_update_live_preview() {
            let chartType = jQuery('#dearcharts_type').val();
            var ctx = document.getElementById('dc-live-chart').getContext('2d');
            var source = jQuery('#dearcharts_active_source').val();
            var labels = [], datasets = [];

            // UI status reset
            jQuery('#dc-status').hide().text('').css({ 'background': 'none', 'color': 'inherit' });

            if (source === 'manual') {
                var headers = jQuery('#dc-manual-table thead input');
                for (var i = 1; i < headers.length; i++) {
                    datasets.push({ label: headers.eq(i).val() || 'Series ' + i, data: [] });
                }

                jQuery('#dc-manual-table tbody tr').each(function () {
                    var inputs = jQuery(this).find('input');
                    labels.push(inputs.eq(0).val() || '');
                    for (var i = 0; i < datasets.length; i++) {
                        datasets[i].data.push(parseFloat(inputs.eq(i + 1).val()) || 0);
                    }
                });
                dearcharts_render_chart_js(labels, datasets, chartType);
            } else {
                var url = jQuery('#dc_csv_url').val();
                if (!url) {
                    jQuery('#dc-status').show().text('Please enter or select a CSV URL').css('color', '#64748b');
                    return;
                }

                try {
                    const response = await fetch(url);
                    if (!response.ok) throw new Error('HTTP Error ' + response.status);
                    const text = await response.text();
                    const lines = text.trim().split(/\r\n|\n/);
                    if (lines.length < 1) throw new Error('CSV is empty');

                    const headParts = lines[0].split(',');
                    for (var i = 1; i < headParts.length; i++) {
                        datasets.push({ label: headParts[i].trim() || 'Series ' + i, data: [] });
                    }

                    for (var r = 1; r < lines.length; r++) {
                        const rowParts = lines[r].split(',');
                        if (rowParts.length < 1) continue;
                        labels.push(rowParts[0].trim() || 'Row ' + r);
                        for (var c = 0; c < datasets.length; c++) {
                            datasets[c].data.push(parseFloat(rowParts[c + 1]) || 0);
                        }
                    }
                    dearcharts_render_chart_js(labels, datasets, chartType);
                    jQuery('#dc-status').show().text('CSV Data Loaded').css({ 'color': '#10b981', 'background': '#f0fdf4' });
                } catch (e) {
                    console.error('CSV Fetch Error:', e);
                    jQuery('#dc-status').show().text('Please check CSV URL or format').css({ 'color': '#ef4444', 'background': '#fef2f2' });
                }
            }
        }

        function dearcharts_render_chart_js(l, d, type) {
            var ctx = document.getElementById('dc-live-chart').getContext('2d');
            if (dearchartsLiveChart) dearchartsLiveChart.destroy();

            const palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

            d.forEach((set, i) => {
                let colors = (d.length > 1) ? palette[i % palette.length] : l.map((_, j) => palette[j % palette.length]);
                set.backgroundColor = colors;
                set.borderColor = colors;
                set.borderWidth = 1;
                set.tension = 0.3;
            });

            dearchartsLiveChart = new Chart(ctx, {
                type: type === 'horizontalBar' ? 'bar' : type,
                data: { labels: l, datasets: d },
                options: {
                    indexAxis: type === 'horizontalBar' ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'top' } },
                    scales: (['bar', 'horizontalBar', 'line'].includes(type)) ? { y: { beginAtZero: true } } : {}
                }
            });
        }

        jQuery(document).ready(function ($) {
            dearcharts_update_live_preview();

            // Re-render on any input change
            $(document).on('input change', '#dearcharts_type, #dc_csv_url', dearcharts_update_live_preview);

            $('#dc_upload_csv_btn').click(function () {
                var uploader = wp.media({ title: 'Select CSV', multiple: false }).on('select', function () {
                    var file = uploader.state().get('selection').first().toJSON();
                    $('#dc_csv_url').val(file.url).trigger('input');
                }).open();
            });
        });
    </script>
    <?php
}

/**
 * 4. Save Logic
 * Mission: Ensure dearcharts_prefix and secure saving with nonce.
 */
function dearcharts_save_meta_box_data($post_id)
{
    if (!isset($_POST['dearcharts_nonce']) || !wp_verify_nonce($_POST['dearcharts_nonce'], 'dearcharts_save_action')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    if (isset($_POST['dearcharts_type'])) {
        update_post_meta($post_id, '_dearcharts_type', sanitize_text_field($_POST['dearcharts_type']));
    }
    if (isset($_POST['dearcharts_active_source'])) {
        update_post_meta($post_id, '_dearcharts_active_source', sanitize_text_field($_POST['dearcharts_active_source']));
    }
    if (isset($_POST['dearcharts_csv_url'])) {
        update_post_meta($post_id, '_dearcharts_csv_url', esc_url_raw($_POST['dearcharts_csv_url']));
    }
    if (isset($_POST['dearcharts_manual_data'])) {
        update_post_meta($post_id, '_dearcharts_manual_data', $_POST['dearcharts_manual_data']);
    }
}
add_action('save_post', 'dearcharts_save_meta_box_data');