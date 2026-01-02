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
    // Remove default Publish box since we have a custom Save button
    remove_meta_box('submitdiv', 'dearcharts', 'side');
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
            wp_enqueue_script('chartjs', plugins_url('../assets/js/chartjs/chart.umd.min.js', __FILE__), array(), '4.4.1', true);
        }
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_assets');

/**
 * Sidebar Meta Box: Shortcodes
 */
function dearcharts_render_usage_box($post)
{
    echo '<div id="dearcharts-usage-content" style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">';
    if (isset($post->post_status) && $post->post_status === 'publish') {
        echo '<p style="margin-top:0; font-size:13px; color:#64748b;">Copy this shortcode to display the chart:</p>';
        echo '<div style="display:flex; align-items:center; gap:8px;">';
        echo '<code id="dc-shortcode" style="flex:1; display:block; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; font-weight:bold; color:#1e293b;">[dearchart id="' . $post->ID . '"]</code>';
        echo '<button type="button" class="button" onclick="dcCopyShortcode()">Copy</button>';
        echo '</div>';
        echo '<span id="dc-copy-status" style="font-size:12px; color:#065f46;"></span>';
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
            --dc-muted: #64748b;
            --dc-saffron: #ffb020;
        }

        .dc-admin-wrapper {
            margin: -12px;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--dc-border);
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        }

        .dc-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: #fff;
            border-bottom: 2px solid #f1f5f9;
        }

        .dc-main-header .dc-main-type { display:flex; align-items:center; gap:8px; }
        .dc-main-header .dc-main-type label { margin:0; font-size:13px; font-weight:600; color:var(--dc-muted); }
        .dc-main-header .dc-main-type select, .dc-main-header select { padding:4px 26px 4px 8px; border-radius:6px; font-size:13px; position:relative; z-index:3; box-sizing:border-box; }
        /* ensure the inline badge and icons sit behind or beside the select */
        .dc-delete-col { position:relative; z-index:1; }

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
            overflow-y: auto;
            width: 100%;
            height: 240px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding-right: 40px; /* Prevent row delete buttons from being cut off */
            display: block;
            background: #fff;
            box-sizing: border-box;
        }

        /* Custom Scrollbar "Slider" Styling */
        .dc-table-wrapper::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .dc-table-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .dc-table-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .dc-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--dc-primary);
        }

        table.dc-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* Force equal widths or respect set widths */
        }

        table.dc-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f8fafc;
            box-shadow: 0 1px 0 #e2e8f0;
        }

        table.dc-table th,
        table.dc-table td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            box-sizing: border-box;
            min-width: 120px;
            vertical-align: middle;
        }

        table.dc-table th {
            font-weight: 600;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
        }

        table.dc-table th:last-child,
        table.dc-table td:last-child {
            width: 50px;
            min-width: 50px;
            text-align: center;
            border-right: none;
            padding: 0;
        }

        table.dc-table input {
            width: 100%;
            display: block;
            box-sizing: border-box;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 13px;
            height: 34px;
            margin: 0;
        }

        .dc-delete-row {
            color: #ef4444;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            line-height: 1;
        }
        
        .dc-delete-row:hover {
            background: #fef2f2;
        }

        /* Column Controls */
        .dc-col-control {
            position: relative;
            width: 100%;
            display: block;
        }
        
        .dc-col-control input {
            padding-right: 28px; /* Space for X */
        }

        .dc-delete-col {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            color: #ef4444;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            background: none;
            border: none;
            padding: 4px;
            line-height: 1;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dc-delete-col:hover {
            background: #fee2e2;
            border-radius: 4px;
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

        /* Hide default WordPress Publish box */
        #submitdiv { visibility: hidden; position: absolute; width: 0; height: 0; overflow: hidden; }
    </style>

    <div class="dc-admin-wrapper">
        <div class="dc-main-header">
            <div style="display:flex; align-items:center; gap:15px;">
                <h2>Chart Editor</h2>
                <button type="button" class="button button-primary" data-pid="<?php echo $post->ID; ?>" onclick="dearcharts_quick_save(this)">Save Chart</button>
                <span id="dc-save-status" style="font-size:13px; font-weight:500;"></span>
            </div>
            <div class="dc-type-selector-inline">
                <label for="dearcharts_type">Chart Type:</label>
                <select name="dearcharts_type" id="dearcharts_type" onchange="dearcharts_update_live_preview()">
                    <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie</option>
                    <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut</option>
                    <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar</option>
                    <option value="line" <?php selected($chart_type, 'line'); ?>>Line</option>
                </select>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <button type="button" class="button button-primary" id="dc-save-chart">Save Chart</button>
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
                                oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();">
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
                                        if (!empty($manual_data) && is_array($manual_data) && (isset($manual_data[0]['label']) || (isset($manual_data[0]) && is_array($manual_data[0]) && count($manual_data[0]) > 1))) {
                                            if (isset($manual_data[0]['label'])) {
                                                // Convert old format to new format
                                                echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="Label" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>';
                                                echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="Value" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>';
                                            } else {
                                                foreach ($manual_data[0] as $h) {
                                                    echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="' . esc_attr($h) . '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>';
                                                }
                                            }
                                        } else {
                                            echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="Label" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>';
                                            echo '<th><input type="text" name="dearcharts_manual_data[0][]" value="Series 1" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>';
                                        }
                                        ?>
                                        <th style="width:40px; cursor:pointer;" onclick="dearcharts_add_column()">+</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($manual_data) && is_array($manual_data) && (isset($manual_data[0]['label']) || count($manual_data) > 1)) {
                                        if (isset($manual_data[0]['label'])) {
                                            foreach ($manual_data as $i => $row) {
                                                if (!isset($row['label']) || !isset($row['value'])) continue;
                                                echo '<tr>';
                                                echo '<td><input type="text" name="dearcharts_manual_data[' . ($i + 1) . '][]" value="' . esc_attr($row['label']) . '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
                                                echo '<td><input type="text" name="dearcharts_manual_data[' . ($i + 1) . '][]" value="' . esc_attr($row['value']) . '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
                                                echo '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview(); dearcharts_local_autosave();">×</td></tr>';
                                            }
                                        } else {
                                            foreach ($manual_data as $r => $row_data) {
                                                if ($r === 0 || !is_array($row_data)) continue;
                                                echo '<tr>';
                                                foreach ($row_data as $cell) {
                                                    echo '<td><input type="text" name="dearcharts_manual_data[' . $r . '][]" value="' . esc_attr($cell) . '" oninput="dearcharts_update_live_preview()"></td>';
                                                }
                                                echo '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview(); dearcharts_local_autosave();">×</td></tr>';
                                            }
                                        }
                                    } else {
                                        // Default row logic: match header count if possible
                                        $col_count = 2;
                                        if (!empty($manual_data) && isset($manual_data[0]) && is_array($manual_data[0])) {
                                            $col_count = count($manual_data[0]);
                                        }
                                        if ($col_count < 2) $col_count = 2;
                                        
                                        echo '<tr><td><input type="text" name="dearcharts_manual_data[1][]" value="Jan" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
                                        for ($c = 1; $c < $col_count; $c++) {
                                            echo '<td><input type="text" name="dearcharts_manual_data[1][]" value="10" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
                                        }
                                        echo '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview(); dearcharts_local_autosave();">×</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="button" onclick="dearcharts_add_row()">+ Add Row</button>
                            <button type="button" class="button" onclick="dearcharts_add_column()">+ Add Column</button>
                            <button type="button" class="button" onclick="dearcharts_transpose_table()" title="Swap Rows and Columns">Transpose</button>
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
                                onchange="dearcharts_update_live_preview(); dearcharts_local_autosave();">
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
                                onchange="dearcharts_update_live_preview(); dearcharts_local_autosave();">
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

    <script>
        var dcLiveChart = null;
        var dc_post_id = <?php echo intval($post->ID); ?>;
        var dc_admin_nonce = '<?php echo wp_create_nonce('dearcharts_save_meta'); ?>';
        var dc_current_csv_data = null; // Store parsed CSV data for snapshot comparison
        // Normalized snapshot of saved post meta for client-side comparisons
        var dc_saved_snapshot = <?php
            $saved_manual_norm = array('headers' => array(), 'rows' => array());
            if (!empty($manual_data) && is_array($manual_data)) {
                // Legacy label/value format
                if (isset($manual_data[0]) && is_array($manual_data[0]) && isset($manual_data[0]['label'])) {
                    $saved_manual_norm['headers'] = array('Label', 'Value');
                    foreach ($manual_data as $i => $row) {
                        if (!isset($row['label']) || !isset($row['value'])) continue;
                        $saved_manual_norm['rows'][] = array($row['label'], $row['value']);
                    }
                } else {
                    // Columnar format: first row is headers
                    $headers = isset($manual_data[0]) && is_array($manual_data[0]) ? array_values($manual_data[0]) : array();
                    $saved_manual_norm['headers'] = $headers;
                    foreach ($manual_data as $k => $row) {
                        if ($k === 0 || !is_array($row)) continue;
                        $saved_manual_norm['rows'][] = array_values($row);
                    }
                }
            }
            $saved_snapshot = array(
                'manual' => $saved_manual_norm,
                'csv_url' => $csv_url,
                'csv_data' => get_post_meta($post->ID, '_dearcharts_csv_data', true) ?: null, // Parsed CSV data for comparison
                'active_source' => $active_source,
                'type' => $chart_type,
                'legend_pos' => $legend_pos,
                'palette' => $palette_key
            );
            echo wp_json_encode($saved_snapshot);
        ?>;
        var dc_palettes = { 'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'], 'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'], 'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'], 'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'], 'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'], 'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F'] };

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

        function dcTab(el, id) { jQuery('.dc-tab').removeClass('active'); jQuery('.dc-tab-content').removeClass('active'); jQuery(el).addClass('active'); jQuery('#' + id).addClass('active'); }
        function dcSetSource(src) { jQuery('#dearcharts_active_source').val(src); jQuery('#dc-csv-body, #dc-manual-body').css({ 'opacity': 0.5, 'pointer-events': 'none' }); jQuery('#dc-' + src + '-body').css({ 'opacity': 1, 'pointer-events': 'auto' }); dearcharts_update_live_preview(); dearcharts_local_autosave(); }
        function dcBrowseCSV() { var media = wp.media({ title: 'Select CSV', multiple: false }).open().on('select', function () { var url = media.state().get('selection').first().toJSON().url; jQuery('#dearcharts_csv_url').val(url); dearcharts_update_live_preview(); dearcharts_local_autosave(); }); }
        function dearcharts_add_row() {
            var colCount = jQuery('#dc-manual-table thead th').length - 1;
            var rowKey = Date.now();
            var html = '<tr>';
            for (var i = 0; i < colCount; i++) {
                html += '<td><input type="text" name="dearcharts_manual_data[' + rowKey + '][]" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
            }
            html += '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview(); dearcharts_local_autosave();">×</td></tr>';
            jQuery('#dc-manual-table tbody').append(html);
        }
        /**
         * Transpose Table Data (Swap Rows and Columns)
         */
        function dearcharts_transpose_table() {
            if (!confirm('Transpose data (swap rows and columns)? This will overwrite the current table arrangement.')) return;
            var snap = dearcharts_get_snapshot();
            var oldH = snap.manual.headers;
            var oldR = snap.manual.rows;
            
            // Build matrix: [Headers, Row1, Row2...]
            var matrix = [oldH].concat(oldR);
            
            // Transpose matrix
            var newMatrix = matrix[0].map((_, colIndex) => matrix.map(row => row[colIndex] || ''));
            
            // Clear table
            jQuery('#dc-manual-table thead tr').html('<th style="width:40px; cursor:pointer;" onclick="dearcharts_add_column()">+</th>');
            jQuery('#dc-manual-table tbody').html('');
            
            // Rebuild Headers
            newMatrix[0].forEach(h => jQuery('<th><input type="text" name="dearcharts_manual_data[0][]" value="'+(h||'').replace(/"/g, '&quot;')+'" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>').insertBefore(jQuery('#dc-manual-table thead th').last()));
            
            // Rebuild Rows
            newMatrix.slice(1).forEach((row, idx) => {
                var html = '<tr>';
                row.forEach(cell => html += '<td><input type="text" name="dearcharts_manual_data['+(Date.now()+idx)+'][]" value="'+(cell||'').replace(/"/g, '&quot;')+'" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>');
                html += '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview(); dearcharts_local_autosave();">×</td></tr>';
                jQuery('#dc-manual-table tbody').append(html);
            });
            
            dearcharts_add_delete_col_controls();
            dearcharts_update_live_preview();
            dearcharts_local_autosave();
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
            var headHtml = '<th><input type="text" name="dearcharts_manual_data[0][]" value="Series ' + colIdx + '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>';
            jQuery(headHtml).insertBefore(jQuery('#dc-manual-table thead th').last());
            jQuery('#dc-manual-table tbody tr').each(function () {
                var rowKeyMatch = jQuery(this).find('td:first input').attr('name').match(/\[(.*?)\]/);
                var rowKey = rowKeyMatch ? rowKeyMatch[1] : Date.now();
                var cellHtml = '<td><input type="text" name="dearcharts_manual_data[' + rowKey + '][]" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
                jQuery(cellHtml).insertBefore(jQuery(this).find('td').last());
            });
            // add delete controls for columns and update preview
            dearcharts_add_delete_col_controls();
            dearcharts_update_live_preview();
            var $wrapper = jQuery('.dc-table-wrapper');
            $wrapper.animate({ scrollLeft: $wrapper.prop("scrollWidth") }, 500);
        }

        function dearcharts_add_delete_col_controls() {
            var $ths = jQuery('#dc-manual-table thead th');
            var lastIdx = $ths.length - 1;
            $ths.each(function (i) {
                if (i > 0 && i < lastIdx) {
                    var $th = jQuery(this);
                    var $input = $th.find('input');
                    // wrap input in .dc-col-control if not already
                    if ($input.length) {
                        if (!$input.parent().hasClass('dc-col-control')) {
                            $input.wrap('<span class="dc-col-control"></span>');
                        }
                        var $control = $input.parent();
                        // ensure delete icon exists inside the control, immediately after input
                        if ($control.find('.dc-delete-col').length === 0) {
                            $control.append('<button type="button" class="dc-delete-col" data-col-idx="' + i + '" aria-label="Delete column" title="Delete column">×</button>');
                        } else {
                            $control.find('.dc-delete-col').attr('data-col-idx', i);
                        }
                    } else {
                        // fallback: append to th
                        if ($th.find('.dc-delete-col').length === 0) {
                            $th.append('<button type="button" class="dc-delete-col" data-col-idx="' + i + '" aria-label="Delete column" title="Delete column">×</button>');
                        } else {
                            $th.find('.dc-delete-col').attr('data-col-idx', i);
                        }
                    }
                } else {
                    // remove delete controls from non-deletable headers
                    jQuery(this).find('.dc-delete-col').remove();
                    // unwrap dc-col-control if it exists and has only the input
                    var $wrap = jQuery(this).find('.dc-col-control');
                    if ($wrap.length && $wrap.find('input').length && $wrap.find('.dc-delete-col').length === 0) {
                        $wrap.replaceWith($wrap.find('input'));
                    }
                }
            });
            // delegated handlers for click and keyboard
            jQuery('#dc-manual-table').off('click', '.dc-delete-col').on('click', '.dc-delete-col', function(){
                var idx = parseInt(jQuery(this).attr('data-col-idx'), 10);
                dearcharts_delete_column(idx);
            });
            jQuery('#dc-manual-table').off('keydown', '.dc-delete-col').on('keydown', '.dc-delete-col', function(e){
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); var idx = parseInt(jQuery(this).attr('data-col-idx'), 10); dearcharts_delete_column(idx); }
            });
        }
        function dearcharts_delete_column(idx) {
            var $ths = jQuery('#dc-manual-table thead th');
            var lastIdx = $ths.length - 1;
            if (idx <= 0 || idx >= lastIdx) return; // don't delete label column or the add button
            jQuery('#dc-manual-table thead th').eq(idx).remove();
            jQuery('#dc-manual-table tbody tr').each(function () {
                jQuery(this).find('td').eq(idx).remove();
            });
            // refresh controls and preview
            dearcharts_add_delete_col_controls();
            dearcharts_update_live_preview();
        }
        // ensure controls are present on DOM ready
        jQuery(function(){ dearcharts_add_delete_col_controls(); });





        /**

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
            let paletteKey = jQuery('#dearcharts_palette').val();
            let palette = (typeof dc_palettes !== 'undefined' && dc_palettes[paletteKey]) ? dc_palettes[paletteKey] : ((typeof dc_palettes !== 'undefined') ? dc_palettes['default'] : ['#3b82f6']);
            
            if (typeof Chart === 'undefined') {
                return; // Chart.js not loaded yet
            }

            var canvas = document.getElementById('dc-live-chart');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            
            // Ensure any existing chart on this canvas is destroyed (fixes "Canvas is already in use")
            var existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }
            dcLiveChart = null;
            
            // Capture current state to handle race conditions
            var currentSource = jQuery('#dearcharts_active_source').val();
            var currentUrl = jQuery('#dearcharts_csv_url').val();

            var labels = [], datasets = [];

            if (currentSource === 'csv') {
                if (!currentUrl) {
                    jQuery('#dc-status').hide();
                    return;
                }
                try {
                    const response = await fetch(currentUrl);
                    // Race condition check: Ensure source and URL haven't changed during fetch
                    if (jQuery('#dearcharts_active_source').val() !== 'csv' || jQuery('#dearcharts_csv_url').val() !== currentUrl) return;

                    const text = await response.text();
                    const rows = dc_parse_csv(text.trim());
                    if (rows.length < 2) throw new Error("Invalid CSV");
                    const heads = rows[0];
                    for (var i = 1; i < heads.length; i++) datasets.push({ label: heads[i].trim(), data: [] });
                    for (var r = 1; r < rows.length; r++) {
                        const row = rows[r];
                        if (row.length < 2) continue;
                        labels.push(row[0].trim());
                        for (var c = 0; c < datasets.length; c++) {
                            var val = parseFloat((row[c + 1] || '').replace(/,/g, ''));
                            datasets[c].data.push(isNaN(val) ? 0 : val);
                        }
                    }
                    jQuery('#dc-status').show().text('CSV Loaded').css({ 'color': '#10b981', 'background': '#f0fdf4' });
                } catch (e) {
                    if (jQuery('#dearcharts_active_source').val() !== 'csv') return;
                    console.error('CSV Fetch Error:', e);
                    jQuery('#dc-status').show().text('Error: ' + e.message).css({ 'color': '#ef4444', 'background': '#fef2f2' });
                    return;
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

            // Final race condition check before drawing
            if (jQuery('#dearcharts_active_source').val() !== currentSource) return;

            datasets.forEach((ds, i) => {
                const colorArray = labels.map((_, j) => palette[j % palette.length] || '#ccc');
                const singleColor = palette[i % palette.length] || '#ccc';

                if (chartType === 'pie' || chartType === 'doughnut') {
                    ds.backgroundColor = colorArray;
                    ds.borderColor = '#ffffff';
                    ds.borderWidth = 2;
                } else if (chartType === 'bar') {
                    if (datasets.length > 1) {
                        ds.backgroundColor = singleColor;
                        ds.borderColor = singleColor;
                    } else {
                        ds.backgroundColor = colorArray;
                        ds.borderColor = colorArray;
                    }
                    ds.borderWidth = 1;
                } else if (chartType === 'line') {
                    ds.backgroundColor = singleColor;
                    ds.borderColor = singleColor;
                    ds.borderWidth = 2;
                    ds.fill = false;
                    ds.pointBackgroundColor = '#fff';
                    ds.pointBorderColor = singleColor;
                }
            });

            dcLiveChart = new Chart(ctx, {
                type: chartType,
                data: { labels: labels, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: (chartType === 'bar' || chartType === 'line') ? { y: { beginAtZero: true } } : {},
                    plugins: {
                        legend: { display: legendPos !== 'none' && (datasets.length > 1 || ['pie', 'doughnut'].includes(chartType)), position: legendPos }
                    }
                });
            } catch (e) {
                console.error('Chart Render Error:', e);
                jQuery('#dc-status').show().text('Preview Error: ' + e.message).css({ 'color': '#ef4444', 'background': '#fff1f2' });
            }
        }
        jQuery(document).ready(function () { 
            // Initial render: ensure Chart.js is loaded
            if (typeof Chart === 'undefined') {
                jQuery(window).on('load', function() { dearcharts_update_live_preview(); });
            } else {
                dearcharts_update_live_preview();
            }

            // Restore from local storage if different from saved
            var key = 'dearcharts_autosave_' + dc_post_id;
            var raw = localStorage.getItem(key);
            if (raw) {
                try {
                    var local_snapshot = JSON.parse(raw);
                    if (!snapshotsEqual(local_snapshot, dc_saved_snapshot)) {
                        // restore from local
                        jQuery('#dearcharts_active_source').val(local_snapshot.active_source);
                        dcSetSource(local_snapshot.active_source);
                        jQuery('#dearcharts_csv_url').val(local_snapshot.csv_url);
                        jQuery('#dearcharts_type').val(local_snapshot.type);
                        jQuery('#dearcharts_legend_pos').val(local_snapshot.legend_pos);
                        jQuery('#dearcharts_palette').val(local_snapshot.palette);
                        if (local_snapshot.active_source === 'manual') {
                            // clear table
                            jQuery('#dc-manual-table thead tr').html('<th style="width:40px; cursor:pointer;" onclick="dearcharts_add_column()">+</th>');
                            jQuery('#dc-manual-table tbody').html('');
                            // add headers
                            local_snapshot.manual.headers.forEach(function(h) {
                                jQuery('<th><input type="text" name="dearcharts_manual_data[0][]" value="' + h.replace(/"/g, '"') + '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>').insertBefore(jQuery('#dc-manual-table thead th:last'));
                            });
                            // add rows
                            local_snapshot.manual.rows.forEach(function(row, idx) {
                                var html = '<tr>';
                                row.forEach(function(cell) {
                                    html += '<td><input type="text" name="dearcharts_manual_data[' + (idx + 1) + '][]" value="' + cell.replace(/"/g, '"') + '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
                                });
                                html += '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview(); dearcharts_local_autosave();">×</td></tr>';
                                jQuery('#dc-manual-table tbody').append(html);
                            });
                            dearcharts_add_delete_col_controls();
                        }
                        dearcharts_update_live_preview();
                        updateSaveButtonState();
                    }
                } catch(e) {}
            }
            // Initialize autosave (no restore UI)
            dearcharts_local_autosave();
            
            jQuery('#dc-save-chart').on('click', function(e){
                e.preventDefault();
                var $btn = jQuery(this);
                $btn.text('Saving...').attr('disabled', 'disabled').addClass('disabled');
                
                // CRITICAL FIX: Sync input values to DOM attributes so Gutenberg captures them correctly
                jQuery('#dc-manual-table input').each(function(){
                    jQuery(this).attr('value', jQuery(this).val());
                });
                jQuery('#dearcharts_csv_url').attr('value', jQuery('#dearcharts_csv_url').val());

                // Check for Gutenberg (Block Editor)
                if (document.body.classList.contains('block-editor-page')) {
                    // Use AJAX for Gutenberg to ensure legacy meta box data is saved
                    var curr = dearcharts_get_snapshot();
                    var payload = {
                        action: 'dearcharts_save_chart',
                        nonce: dc_admin_nonce,
                        post_id: dc_post_id,
                        manual_json: JSON.stringify(curr.manual),
                        dearcharts_csv_url: curr.csv_url,
                        dearcharts_active_source: curr.active_source,
                        dearcharts_type: curr.type,
                        dearcharts_legend_pos: curr.legend_pos,
                        dearcharts_palette: curr.palette
                    };

                    jQuery.post(ajaxurl, payload, function(res){
                        if (res && res.success) {
                            dc_saved_snapshot = curr;
                            var key = 'dearcharts_autosave_' + dc_post_id; localStorage.removeItem(key);
                            $btn.text('Save Chart').removeAttr('disabled').removeClass('disabled');
                            jQuery('#dc-status').show().text('Saved').css({ 'color': '#065f46', 'background': '#ecfdf5' });
                            setTimeout(function(){ jQuery('#dc-status').text(''); }, 2000);
                            // Trigger silent save of post content/title
                            if (window.wp && window.wp.data) { wp.data.dispatch('core/editor').savePost(); }
                        } else {
                            var msg = (res && res.data && res.data.message) ? res.data.message : 'Save failed';
                            $btn.text('Save Chart').removeAttr('disabled').removeClass('disabled');
                            jQuery('#dc-status').show().text(msg).css({ 'color': '#ef4444', 'background': '#fff1f2' });
                        }
                    }).fail(function(){
                        $btn.text('Save Chart').removeAttr('disabled').removeClass('disabled');
                        jQuery('#dc-status').show().text('Server Error').css({ 'color': '#ef4444', 'background': '#fff1f2' });
                    });
                } else {
                    // Classic Editor
                    if (jQuery('#publish').length) {
                        jQuery('#publish').trigger('click');
                    } else {
                        jQuery('form#post').submit();
                    }
                }
            });
        });

        function dearcharts_get_snapshot() {
            var snapshot = { manual: { headers: [], rows: [] }, csv_url: jQuery('#dearcharts_csv_url').val() || '', active_source: jQuery('#dearcharts_active_source').val() || 'manual', type: jQuery('#dearcharts_type').val() || '', legend_pos: jQuery('#dearcharts_legend_pos').val() || '', palette: jQuery('#dearcharts_palette').val() || '' };
            // headers (skip only add button i=last)
            jQuery('#dc-manual-table thead th').each(function(i){ if (i === jQuery('#dc-manual-table thead th').length - 1) return; var v = jQuery(this).find('input').val() || ''; snapshot.manual.headers.push(v); });
            // rows
            jQuery('#dc-manual-table tbody tr').each(function(){ var row = []; jQuery(this).find('td').each(function(i){ if (i === jQuery(this).closest('tr').find('td').length - 1) return; var v = jQuery(this).find('input').val() || ''; row.push(v); }); snapshot.manual.rows.push(row); });
            return snapshot;
        }

        function snapshotsEqual(a,b){ try { return JSON.stringify(a) === JSON.stringify(b); } catch(e){ return false; } }

        

        function dearcharts_local_autosave(init) {
            var key = 'dearcharts_autosave_' + dc_post_id;
            var curr = dearcharts_get_snapshot();
            var raw = JSON.stringify(curr);
            localStorage.setItem(key, raw);
            // show restore button only when there's a local snapshot different from saved
            try {
                var savedRaw = JSON && dc_saved_snapshot ? JSON.stringify(dc_saved_snapshot) : '';
                var hasDiff = savedRaw !== raw;
                /* don't show a restore button; we keep a local snapshot for safety but do not expose restore UI */
            // no restore UI; just update button disability state below
                updateSaveButtonState();
            } catch(e){ }
        }

        function updateSaveButtonState(){ /* Button state management removed to allow Publish action at all times */ }

        function dcCopyShortcode() {
            var shortcode = document.getElementById('dc-shortcode').textContent;
            navigator.clipboard.writeText(shortcode).then(function() {
                document.getElementById('dc-copy-status').textContent = 'Copied!';
                setTimeout(function() {
                    document.getElementById('dc-copy-status').textContent = '';
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                document.getElementById('dc-copy-status').textContent = 'Copy failed';
                setTimeout(function() {
                    document.getElementById('dc-copy-status').textContent = '';
                }, 2000);
            });
        }

        function dearcharts_quick_save(btn) {
            var $btn = jQuery(btn);
            var originalText = $btn.text();
            $btn.text('Saving...').prop('disabled', true);
            
            var headers = [];
            jQuery('#dc-manual-table thead th input').each(function() { headers.push(jQuery(this).val()); });
            
            var rows = [];
            jQuery('#dc-manual-table tbody tr').each(function() {
                var row = [];
                jQuery(this).find('td input').each(function() { row.push(jQuery(this).val()); });
                if(row.length > 0) rows.push(row);
            });
            
            var data = {
                action: 'dearcharts_save_chart',
                nonce: jQuery('#dearcharts_nonce').val(),
                post_id: $btn.data('pid'),
                manual_json: JSON.stringify({ headers: headers, rows: rows }),
                dearcharts_csv_url: jQuery('#dearcharts_csv_url').val(),
                dearcharts_active_source: jQuery('#dearcharts_active_source').val(),
                dearcharts_type: jQuery('#dearcharts_type').val(),
                dearcharts_legend_pos: jQuery('#dearcharts_legend_pos').val(),
                dearcharts_palette: jQuery('#dearcharts_palette').val()
            };
            
            jQuery.post(ajaxurl, data, function(res) {
                $btn.text(originalText).prop('disabled', false);
                if(res.success) {
                    jQuery('#dc-save-status').text('Saved!').css('color', '#10b981').show().delay(2000).fadeOut();
                } else {
                    alert('Save Failed');
                }
            });
        }

        function dearcharts_quick_save(btn) {
            var $btn = jQuery(btn);
            var originalText = $btn.text();
            $btn.text('Saving...').prop('disabled', true);
            
            var headers = [];
            jQuery('#dc-manual-table thead th input').each(function() { headers.push(jQuery(this).val()); });
            
            var rows = [];
            jQuery('#dc-manual-table tbody tr').each(function() {
                var row = [];
                jQuery(this).find('td input').each(function() { row.push(jQuery(this).val()); });
                if(row.length > 0) rows.push(row);
            });
            
            var data = {
                action: 'dearcharts_save_chart',
                nonce: jQuery('#dearcharts_nonce').val(),
                post_id: $btn.data('pid'),
                manual_json: JSON.stringify({ headers: headers, rows: rows }),
                dearcharts_csv_url: jQuery('#dearcharts_csv_url').val(),
                dearcharts_active_source: jQuery('#dearcharts_active_source').val(),
                dearcharts_type: jQuery('#dearcharts_type').val(),
                dearcharts_legend_pos: jQuery('#dearcharts_legend_pos').val(),
                dearcharts_palette: jQuery('#dearcharts_palette').val()
            };
            
            jQuery.post(ajaxurl, data, function(res) {
                $btn.text(originalText).prop('disabled', false);
                if(res.success) {
                    jQuery('#dc-save-status').text('Saved!').css('color', '#10b981').show().delay(2000).fadeOut();
                } else {
                    alert('Save Failed');
                }
            });
        }

        function dearcharts_quick_save(btn) {
            // Validation: Ensure Title is present
            var title = jQuery('#title').val();
            if (!title || title.trim() === '') {
                alert('Please enter a title in the main WordPress title box before saving.');
                jQuery('#title').focus();
                return;
            }

            var $btn = jQuery(btn);
            var originalText = $btn.text();
            $btn.text('Saving...').prop('disabled', true);
            
            var headers = [];
            jQuery('#dc-manual-table thead th input').each(function() { headers.push(jQuery(this).val()); });
            
            var rows = [];
            jQuery('#dc-manual-table tbody tr').each(function() {
                var row = [];
                jQuery(this).find('td input').each(function() { row.push(jQuery(this).val()); });
                if(row.length > 0) rows.push(row);
            });
            
            var data = {
                action: 'dearcharts_save_chart',
                nonce: jQuery('#dearcharts_nonce').val(),
                post_id: $btn.data('pid'),
                manual_json: JSON.stringify({ headers: headers, rows: rows }),
                post_title: title,
                dearcharts_csv_url: jQuery('#dearcharts_csv_url').val(),
                dearcharts_active_source: jQuery('#dearcharts_active_source').val(),
                dearcharts_type: jQuery('#dearcharts_type').val(),
                dearcharts_legend_pos: jQuery('#dearcharts_legend_pos').val(),
                dearcharts_palette: jQuery('#dearcharts_palette').val()
            };
            
            jQuery.post(ajaxurl, data, function(res) {
                $btn.text(originalText).prop('disabled', false);
                if(res.success) {
                    jQuery('#dc-save-status').text('Saved!').css('color', '#10b981').show().delay(2000).fadeOut();
                    
                    // Show shortcode if returned
                    if(res.data && res.data.shortcode) {
                        // Update the Usage Meta Box
                        var $usageBox = jQuery('#dearcharts-usage-content');
                        if($usageBox.length) {
                             var html = '<p style="margin-top:0; font-size:13px; color:#64748b;">Copy this shortcode to display the chart:</p>';
                             html += '<code style="display:block; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; font-weight:bold; color:#1e293b;">' + res.data.shortcode + '</code>';
                             $usageBox.html(html);
                        }
                    }
                } else {
                    alert('Save Failed');
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
        ksort($manual); // Ensure headers (index 0) are first
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

/**
 * AJAX Save Handler
 */
function dearcharts_ajax_save_chart() {
    check_ajax_referer('dearcharts_save_meta', 'nonce');
    
    $post_id = intval($_POST['post_id']);
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error();
    }

    // Save Manual Data
    $json = json_decode(stripslashes($_POST['manual_json']), true);
    $manual_data = array();
    if (!empty($json['headers'])) {
        $manual_data[] = array_map('sanitize_text_field', $json['headers']);
        foreach ($json['rows'] as $row) {
            $manual_data[] = array_map('sanitize_text_field', $row);
        }
    }
    update_post_meta($post_id, '_dearcharts_manual_data', $manual_data);

    // Save Settings
    if (isset($_POST['dearcharts_csv_url'])) update_post_meta($post_id, '_dearcharts_csv_url', esc_url_raw($_POST['dearcharts_csv_url']));
    if (isset($_POST['dearcharts_active_source'])) update_post_meta($post_id, '_dearcharts_active_source', sanitize_text_field($_POST['dearcharts_active_source']));
    if (isset($_POST['dearcharts_type'])) update_post_meta($post_id, '_dearcharts_type', sanitize_text_field($_POST['dearcharts_type']));
    if (isset($_POST['dearcharts_legend_pos'])) update_post_meta($post_id, '_dearcharts_legend_pos', sanitize_text_field($_POST['dearcharts_legend_pos']));
    if (isset($_POST['dearcharts_palette'])) update_post_meta($post_id, '_dearcharts_palette', sanitize_text_field($_POST['dearcharts_palette']));

    // Update Title
    // Update Title & Publish
    $update_args = array(
        'ID' => $post_id,
        'post_status' => 'publish'
    );
    if (isset($_POST['post_title'])) {
        $update_args['post_title'] = sanitize_text_field($_POST['post_title']);
    }
    wp_update_post($update_args);

    $shortcode = '[dearchart id="' . $post_id . '"]';
    wp_send_json_success(array('message' => 'Saved', 'shortcode' => $shortcode));
}
add_action('wp_ajax_dearcharts_save_chart', 'dearcharts_ajax_save_chart');
