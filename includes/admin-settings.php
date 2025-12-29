<?php
/**
 * Professional Split-Screen Admin UI for DearCharts
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filter to allow CSV uploads in media library
 */
add_filter('upload_mimes', function ($mimes) {
    if (!isset($mimes['csv'])) {
        $mimes['csv'] = 'text/csv';
    }
    return $mimes;
});

/**
 * Register Meta Boxes
 */
function dearcharts_add_custom_metaboxes()
{
    add_meta_box('dearcharts_main_box', 'Chart Configuration', 'dearcharts_render_main_box', 'dearcharts', 'normal', 'high');
    add_meta_box('dearcharts_usage_box', 'Chart Shortcodes', 'dearcharts_render_usage_box', 'dearcharts', 'side', 'low');
}
add_action('add_meta_boxes', 'dearcharts_add_custom_metaboxes');

/**
 * Enqueue Admin Assets
 */
function dearcharts_admin_assets($hook)
{
    global $post;
    if ($hook == 'post-new.php' || $hook == 'post.php') {
        if ($post && $post->post_type === 'dearcharts') {
            wp_enqueue_media();
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
        }
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_assets');

/**
 * Sidebar Meta Box: Shortcodes
 */
function dearcharts_render_usage_box($post)
{
    echo '<div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">';
    if (isset($post->post_status) && $post->post_status === 'publish') {
        echo '<p style="margin-top:0; font-size:13px; color:#64748b;">Copy this shortcode to display the chart:</p>';
        echo '<code style="display:block; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; font-weight:bold; color:#1e293b;">[dearchart id="' . $post->ID . '"]</code>';
    } else {
        echo '<p style="margin-top:0; font-size:13px; color:#64748b;">Shortcode will be available after you publish this chart. You can preview it using the Preview button.</p>';
        // Intentionally do not reveal the shortcode for unpublished charts to prevent confusion.
    }
    echo '</div>';
}

/**
 * Main Meta Box: Split-Screen Professional UI
 * PSEUDOCODE:
 * 1. Fetch current chart data and settings from post meta.
 * 2. Define the 'Live Preview' panel (Left side).
 * 3. Define the 'Settings' panel with Tabs (Right side).
 * 4. Implement a unified 'Chart Editor' header with type selection.
 * 5. Initialize the JavaScript Live Preview engine.
 */
function dearcharts_render_main_box($post)
{
    // Retrieve Meta Data
    $manual_data = get_post_meta($post->ID, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post->ID, '_dearcharts_csv_url', true);
    $active_source = get_post_meta($post->ID, '_dearcharts_active_source', true) ?: ((!empty($csv_url)) ? 'csv' : 'manual');

    // Aesthetic Settings
    $chart_type = get_post_meta($post->ID, '_dearcharts_type', true) ?: (get_post_meta($post->ID, '_dearcharts_is_donut', true) === '1' ? 'doughnut' : 'pie');
    $legend_pos = get_post_meta($post->ID, '_dearcharts_legend_pos', true) ?: 'top';
    $palette_key = get_post_meta($post->ID, '_dearcharts_palette', true) ?: 'default';

    wp_nonce_field('dearcharts_save_meta', 'dearcharts_nonce');
    ?>
    <style>
        :root {
            --dc-primary: #3b82f6;
            --dc-border: #e2e8f0;
            --dc-bg: #f8fafc;
            --dc-text: #1e293b;
        }

        .dc-admin-wrapper {
            margin: -12px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--dc-border);
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        }

        .dc-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #fff;
            border-bottom: 2px solid #f1f5f9;
        }

        .dc-main-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--dc-text);
            letter-spacing: -0.025em;
        }

        .dc-split-container {
            display: flex;
            height: 480px;
            background: #fff;
            overflow: hidden;
        }

        .dc-preview-panel {
            flex: 0 0 450px;
            padding: 20px;
            background: var(--dc-bg);
            border-right: 1px solid var(--dc-border);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }

        .dc-preview-header {
            width: 100%;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dc-preview-header h3 {
            margin: 0;
            font-size: 16px;
            color: var(--dc-text);
        }

        #dc-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            display: none;
        }

        .dc-chart-container {
            width: 100%;
            max-width: 400px;
            height: 400px;
            background: #fff;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .dc-settings-panel {
            flex: 1;
            padding: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .dc-tabs {
            display: flex;
            align-items: center;
            background: #fff;
            border-bottom: 1px solid var(--dc-border);
        }

        .dc-tab {
            padding: 15px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #64748b;
            border-bottom: 2px solid transparent;
            transition: 0.2s;
        }

        .dc-tab.active {
            color: var(--dc-primary);
            border-bottom-color: var(--dc-primary);
            background: #eff6ff;
        }

        .dc-type-selector-inline {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #64748b;
        }

        .dc-type-selector-inline select {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--dc-border);
            background: #f8fafc;
            font-size: 13px;
            font-weight: 500;
            color: var(--dc-text);
            cursor: pointer;
        }

        .dc-tab-content {
            display: none;
            padding: 15px 20px;
            flex: 1;
            overflow-y: auto;
            max-height: 100%;
        }

        .dc-tab-content.active {
            display: block;
        }

        .dc-card {
            border: 1px solid var(--dc-border);
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            background: #fff;
        }

        .dc-card-header {
            background: #f1f5f9;
            padding: 12px 15px;
            border-bottom: 1px solid var(--dc-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dc-card-header span {
            font-weight: 600;
            color: #475569;
            font-size: 13px;
        }

        .dc-card-body {
            padding: 12px;
        }

        .dc-table-wrapper {
            overflow-x: auto;
            overflow-y: scroll;
            width: 100%;
            max-height: 180px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding-bottom: 5px;
            display: block;
        }

        /* Custom Scrollbar "Slider" Styling */
        .dc-table-wrapper::-webkit-scrollbar {
            width: 10px;
            /* Vertical scrollbar width */
            height: 10px;
            /* Horizontal scrollbar height */
        }

        .dc-table-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .dc-table-wrapper::-webkit-scrollbar-thumb {
            background: #94a3b8;
            /* Darker thumb for better visibility */
            border-radius: 10px;
            border: 2px solid #f1f5f9;
        }

        .dc-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--dc-primary);
        }

        table.dc-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            position: relative;
        }

        table.dc-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        table.dc-table th,
        table.dc-table td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
            width: 150px;
            min-width: 150px;
        }

        table.dc-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
        }

        table.dc-table th:last-child,
        table.dc-table td:last-child {
            width: 40px;
            min-width: 40px;
            text-align: center;
        }

        table.dc-table input {
            width: 100%;
            border: 1px solid #cbd5e1;
            padding: 8px;
            border-radius: 4px;
            font-size: 13px;
        }

        .dc-delete-row {
            color: #ef4444;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }

        .dc-setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .dc-setting-row:last-child {
            border-bottom: none;
        }

        .dc-setting-label {
            font-weight: 600;
            color: #475569;
        }
    </style>

    <div class="dc-admin-wrapper">
        <div class="dc-main-header">
            <h2>Chart Editor</h2>
            <div class="dc-type-selector-inline">
                <label for="dearcharts_type">Chart Type:</label>
                <select name="dearcharts_type" id="dearcharts_type" onchange="dearcharts_update_live_preview()">
                    <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie</option>
                    <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut</option>
                    <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar</option>
                    <option value="line" <?php selected($chart_type, 'line'); ?>>Line</option>
                </select>
            </div>
        </div>

        <div class="dc-split-container">
            <!-- Live Preview -->
            <div class="dc-preview-panel">
                <div class="dc-preview-header">
                    <h3>Live Preview</h3>
                    <span id="dc-status"></span>
                </div>
                <div class="dc-chart-container">
                    <canvas id="dc-live-chart"></canvas>
                </div>
            </div>

        <!-- Settings Tabs -->
        <div class="dc-settings-panel">
            <div class="dc-tabs">
                <div class="dc-tab active" onclick="dcTab(this, 'dc-data')">Data Source</div>
                <div class="dc-tab" onclick="dcTab(this, 'dc-style')">Appearance</div>
            </div>

            <div id="dc-data" class="dc-tab-content active">
                <input type="hidden" name="dearcharts_active_source" id="dearcharts_active_source"
                    value="<?php echo esc_attr($active_source); ?>">

                <div class="dc-card">
                    <div class="dc-card-header">
                        <span>Import from CSV</span>
                        <input type="radio" name="dc_source_selector" value="csv" <?php checked($active_source, 'csv'); ?>
                            onclick="dcSetSource('csv')">
                    </div>
                    <div class="dc-card-body" id="dc-csv-body"
                        style="<?php echo ($active_source === 'csv') ? '' : 'opacity:0.5; pointer-events:none;'; ?>">
                        <div style="display:flex; gap:8px;">
                            <input type="text" name="dearcharts_csv_url" id="dearcharts_csv_url" class="dc-input-text"
                                style="flex:1;" value="<?php echo esc_url($csv_url); ?>"
                                oninput="dearcharts_update_live_preview()">
                            <button type="button" class="button" onclick="dcBrowseCSV()">Media</button>
                        </div>
                    </div>
                </div>

                <div class="dc-card">
                    <div class="dc-card-header">
                        <span>Manual Data Entry</span>
                        <input type="radio" name="dc_source_selector" value="manual" <?php checked($active_source, 'manual'); ?> onclick="dcSetSource('manual')">
                    </div>
                    <div class="dc-card-body" id="dc-manual-body"
                        style="<?php echo ($active_source === 'manual') ? '' : 'opacity:0.5; pointer-events:none;'; ?>">
                        <div class="dc-table-wrapper">
                            <table class="dc-table" id="dc-manual-table">
                                <thead>
                                    <tr>
                                        <?php
                                        // Handle backward compatibility for old 2-column data
                                        if (!empty($manual_data) && is_array($manual_data)) {
                                            if (isset($manual_data[0]['label'])) {
                                                // Convert old format to new format
                                                echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="Label" oninput="dearcharts_update_live_preview()"></th>';
                                                echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="Value" oninput="dearcharts_update_live_preview()"></th>';
                                            } else {
                                                foreach ($manual_data[0] as $h) {
                                                    echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="' . esc_attr($h) . '" oninput="dearcharts_update_live_preview()"></th>';
                                                }
                                            }
                                        } else {
                                            echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="Label" oninput="dearcharts_update_live_preview()"></th>';
                                            echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="Series 1" oninput="dearcharts_update_live_preview()"></th>';
                                        }
                                        ?>
                                        <th style="width:40px; cursor:pointer;" onclick="dearcharts_add_column()">+</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($manual_data) && is_array($manual_data)) {
                                        if (isset($manual_data[0]['label'])) {
                                            foreach ($manual_data as $i => $row) {
                                                echo '<tr>';
                                                echo '<td><input type="text" name="dearcharts_manual_data[' . ($i + 1) . '][]" value="' . esc_attr($row['label']) . '" oninput="dearcharts_update_live_preview()"></td>';
                                                echo '<td><input type="text" name="dearcharts_manual_data[' . ($i + 1) . '][]" value="' . esc_attr($row['value']) . '" oninput="dearcharts_update_live_preview()"></td>';
                                                echo '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview();">×</td></tr>';
                                            }
                                        } else {
                                            for ($r = 1; $r < count($manual_data); $r++) {
                                                echo '<tr>';
                                                foreach ($manual_data[$r] as $cell) {
                                                    echo '<td><input type="text" name="dearcharts_manual_data[' . $r . '][]" value="' . esc_attr($cell) . '" oninput="dearcharts_update_live_preview()"></td>';
                                                }
                                                echo '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview();">×</td></tr>';
                                            }
                                        }
                                    } else {
                                        echo '<tr><td><input type="text" name="dearcharts_manual_data[1][]" value="Jan" oninput="dearcharts_update_live_preview()"></td><td><input type="text" name="dearcharts_manual_data[1][]" value="10" oninput="dearcharts_update_live_preview()"></td><td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview();">×</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="button" onclick="dearcharts_add_row()">+ Add Row</button>
                            <button type="button" class="button" onclick="dearcharts_add_column()">+ Add Column</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="dc-style" class="dc-tab-content">
                <div class="dc-card">
                    <div class="dc-card-header"><span>Visual Settings</span></div>
                    <div class="dc-card-body">
                        <div class="dc-setting-row">
                            <span class="dc-setting-label">Legend Position</span>
                            <select name="dearcharts_legend_pos" id="dearcharts_legend_pos"
                                onchange="dearcharts_update_live_preview()">
                                <option value="top" <?php selected($legend_pos, 'top'); ?>>Top</option>
                                <option value="bottom" <?php selected($legend_pos, 'bottom'); ?>>Bottom</option>
                                <option value="left" <?php selected($legend_pos, 'left'); ?>>Left</option>
                                <option value="right" <?php selected($legend_pos, 'right'); ?>>Right</option>
                                <option value="none" <?php selected($legend_pos, 'none'); ?>>None</option>
                            </select>
                        </div>
                        <div class="dc-setting-row">
                            <span class="dc-setting-label">Color Palette</span>
                            <select name="dearcharts_palette" id="dearcharts_palette"
                                onchange="dearcharts_update_live_preview()">
                                <option value="default" <?php selected($palette_key, 'default'); ?>>Standard</option>
                                <option value="pastel" <?php selected($palette_key, 'pastel'); ?>>Pastel</option>
                                <option value="ocean" <?php selected($palette_key, 'ocean'); ?>>Ocean</option>
                                <option value="sunset" <?php selected($palette_key, 'sunset'); ?>>Sunset</option>
                                <option value="neon" <?php selected($palette_key, 'neon'); ?>>Neon</option>
                                <option value="forest" <?php selected($palette_key, 'forest'); ?>>Forest</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <script>     var dcLiveChart = null; var dc_palettes = { 'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'], 'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'], 'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'], 'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'], 'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'], 'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F'] };
        function dcTab(el, id) { jQuery('.dc-tab').removeClass('active'); jQuery('.dc-tab-content').removeClass('active'); jQuery(el).addClass('active'); jQuery('#' + id).addClass('active'); }
        function dcSetSource(src) { jQuery('#dearcharts_active_source').val(src); jQuery('#dc-csv-body, #dc-manual-body').css({ 'opacity': 0.5, 'pointer-events': 'none' }); jQuery('#dc-' + src + '-body').css({ 'opacity': 1, 'pointer-events': 'auto' }); dearcharts_update_live_preview(); }
        function dcBrowseCSV() { var media = wp.media({ title: 'Select CSV', multiple: false }).open().on('select', function () { var url = media.state().get('selection').first().toJSON().url; jQuery('#dearcharts_csv_url').val(url); dearcharts_update_live_preview(); }); }
        function dearcharts_add_row() {
            var colCount = jQuery('#dc-manual-table thead th').length - 1;
            var rowKey = Date.now();
            var html = '<tr>';
            for (var i = 0; i < colCount; i++) {
                html += '<td><input type="text" name="dearcharts_manual_data[' + rowKey + '][]" oninput="dearcharts_update_live_preview()"></td>';
            }
            html += '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview();">×</td></tr>';
            jQuery('#dc-manual-table tbody').append(html);
        }
        /**
         * PSEUDOCODE: Add Column
         * 1. Determine new column index.
         * 2. Insert as a new header input (for the series label).
         * 3. Iterate through all rows and append a new data input cell for that series.
         * 4. Auto-scroll the table to the right to focus on the new column.
         */
        function dearcharts_add_column() {
            var colIdx = jQuery('#dc-manual-table thead th').length - 1;
            var headHtml = '<th><input type="text" name="dearcharts_manual_data[0][]" value="Series ' + colIdx + '" oninput="dearcharts_update_live_preview()"></th>';
            jQuery(headHtml).insertBefore(jQuery('#dc-manual-table thead th').last());
            jQuery('#dc-manual-table tbody tr').each(function () {
                var rowKeyMatch = jQuery(this).find('td:first input').attr('name').match(/\[(.*?)\]/);
                var rowKey = rowKeyMatch ? rowKeyMatch[1] : Date.now();
                var cellHtml = '<td><input type="text" name="dearcharts_manual_data[' + rowKey + '][]" oninput="dearcharts_update_live_preview()"></td>';
                jQuery(cellHtml).insertBefore(jQuery(this).find('td').last());
            });
            dearcharts_update_live_preview();
            var $wrapper = jQuery('.dc-table-wrapper');
            $wrapper.animate({ scrollLeft: $wrapper.prop("scrollWidth") }, 500);
        }

        /**
         * PSEUDOCODE: Update Live Preview
         * 1. Safely clear the previous chart instance.
         * 2. Read the source (CSV vs Manual).
         * 3. If CSV: Fetch URL, parse lines, and map to Chart.js datasets.
         * 4. If Manual: Scrape table TBODY for data and THEAD for labels.
         * 5. Apply selected color palette from the dc_palettes dictionary.
         * 6. Re-draw the Chart using the Chart.js API.
         */
        async function dearcharts_update_live_preview() {
            let chartType = jQuery('#dearcharts_type').val();
            let legendPos = jQuery('#dearcharts_legend_pos').val();
            let palette = dc_palettes[jQuery('#dearcharts_palette').val()] || dc_palettes['default'];
            var canvas = document.getElementById('dc-live-chart');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            var source = jQuery('#dearcharts_active_source').val();

            if (dcLiveChart) dcLiveChart.destroy();

            var labels = [], datasets = [];

            if (source === 'csv') {
                var url = jQuery('#dearcharts_csv_url').val();
                if (!url) return;
                try {
                    const response = await fetch(url);
                    const text = await response.text();
                    const lines = text.trim().split(/\r\n|\n/);
                    const heads = lines[0].split(',');
                    for (var i = 1; i < heads.length; i++) datasets.push({ label: heads[i].trim(), data: [] });
                    for (var r = 1; r < lines.length; r++) {
                        const row = lines[r].split(',');
                        if (row.length < 1) continue;
                        labels.push(row[0].trim());
                        for (var c = 0; c < datasets.length; c++) {
                            var val = parseFloat(row[c + 1]);
                            datasets[c].data.push(isNaN(val) ? 0 : val);
                        }
                    }
                    jQuery('#dc-status').show().text('CSV Loaded').css({ 'color': '#10b981', 'background': '#f0fdf4' });
                } catch (e) {
                    console.error('CSV Fetch Error:', e);
                    jQuery('#dc-status').show().text('CSV Error').css({ 'color': '#ef4444', 'background': '#fef2f2' });
                }
            } else {
                jQuery('#dc-manual-table thead th').each(function (i) {
                    var val = jQuery(this).find('input').val();
                    if (i > 0 && val !== undefined) datasets.push({ label: val, data: [] });
                });
                jQuery('#dc-manual-table tbody tr').each(function () {
                    var rowLabel = jQuery(this).find('td:first input').val();
                    labels.push(rowLabel);
                    jQuery(this).find('td').each(function (i) {
                        if (i > 0 && i < datasets.length + 1) {
                            datasets[i - 1].data.push(parseFloat(jQuery(this).find('input').val()) || 0);
                        }
                    });
                });
            }

            datasets.forEach((ds, i) => {
                let colors = (datasets.length > 1) ? palette[i % palette.length] : labels.map((_, j) => palette[j % palette.length]);
                if (chartType === 'bar' || chartType === 'line') {
                    ds.backgroundColor = (datasets.length > 1) ? palette[i % palette.length] : palette;
                    ds.borderColor = (datasets.length > 1) ? palette[i % palette.length] : palette;
                } else {
                    ds.backgroundColor = colors;
                    ds.borderColor = colors;
                }
                ds.borderWidth = (chartType === 'line') ? 2 : 1;
                ds.fill = (chartType === 'line') ? false : true;
            });

            dcLiveChart = new Chart(ctx, {
                type: chartType,
                data: { labels: labels, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: (chartType === 'bar' || chartType === 'line') ? { y: { beginAtZero: true } } : {},
                    plugins: {
                        legend: { display: legendPos !== 'none', position: legendPos }
                    }
                }
            });
        }
        jQuery(document).ready(function () { dearcharts_update_live_preview(); });
    </script>
    <?php
}

/**
 * Sanitize manual data (recursive) to ensure stored values are safe.
 */
function dearcharts_sanitize_manual_data($data)
{
    if (!is_array($data)) return array();
    $out = array();
    foreach ($data as $k => $row) {
        if (is_array($row)) {
            $out[$k] = array();
            foreach ($row as $v) {
                if (is_array($v)) {
                    $out[$k][] = array_map('sanitize_text_field', $v);
                } else {
                    $out[$k][] = sanitize_text_field($v);
                }
            }
        } else {
            $out[$k] = sanitize_text_field($row);
        }
    }
    return $out;
}

/**
 * Save Meta Box Data
 * PSEUDOCODE:
 * 1. Security check: Verify the nonce.
 * 2. Bypass if autosaving.
 * 3. Sanitize and update manual data array.
 * 4. Sanitize and update CSV URL if applicable.
 * 5. Save the active source state (CSV vs Manual).
 * 6. Save aesthetic settings: Chart Type, Legend Position, Palette.
 */
function dearcharts_save_meta_box_data($post_id)
{
    if (!isset($_POST['dearcharts_nonce']) || !wp_verify_nonce($_POST['dearcharts_nonce'], 'dearcharts_save_meta'))
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    if (isset($_POST['dearcharts_manual_data'])) {
        $manual = dearcharts_sanitize_manual_data($_POST['dearcharts_manual_data']);
        update_post_meta($post_id, '_dearcharts_manual_data', $manual);
    }
    if (isset($_POST['dearcharts_csv_url']))
        update_post_meta($post_id, '_dearcharts_csv_url', esc_url_raw($_POST['dearcharts_csv_url']));
    if (isset($_POST['dearcharts_active_source']))
        update_post_meta($post_id, '_dearcharts_active_source', sanitize_text_field($_POST['dearcharts_active_source']));

    if (isset($_POST['dearcharts_type']))
        update_post_meta($post_id, '_dearcharts_type', sanitize_text_field($_POST['dearcharts_type']));
    if (isset($_POST['dearcharts_legend_pos']))
        update_post_meta($post_id, '_dearcharts_legend_pos', sanitize_text_field($_POST['dearcharts_legend_pos']));
    if (isset($_POST['dearcharts_palette']))
        update_post_meta($post_id, '_dearcharts_palette', sanitize_text_field($_POST['dearcharts_palette']));
}
add_action('save_post', 'dearcharts_save_meta_box_data');
