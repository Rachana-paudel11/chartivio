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
        #dc-inline-recommend, .dc-delete-col { position:relative; z-index:1; }
        /* Inline recommendation badge (saffron with bulb) */
        #dc-inline-recommend { display:inline-flex; align-items:center; gap:8px; }
        .dc-rec-badge { background: var(--dc-saffron); color: #fff; padding:6px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:10px; font-size:13px; box-shadow:0 1px 0 rgba(0,0,0,0.04); }
        .dc-rec-bulb { width:16px; height:16px; fill:#fff; flex:0 0 16px; }
        .dc-rec-text { color:#fff; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:8px; }
        .dc-rec-prefix { color: rgba(255,255,255,0.95); font-size:12px; font-weight:600; margin-right:6px; }
        .dc-rec-primary { color:#fff; font-weight:700; }
        .dc-rec-reason { color: rgba(255,255,255,0.95); font-size:11px; opacity:0.95; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:inline-block; }
        .dc-rec-apply { margin-left:6px; padding:4px 6px; font-size:12px; background:#fff; color:var(--dc-saffron); border-radius:4px; border:1px solid rgba(0,0,0,0.06); cursor:pointer; }
        .dc-rec-apply:focus { outline: 2px solid rgba(255,176,32,0.2); }
        .dc-rec-strong { color:#fff; font-weight:700; }

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

        table.dc-table th {
            position: relative;
        }

        /* Inline header control container: overlay delete icon to avoid changing column width */
        .dc-col-control {
            display: flex;
            align-items: flex-end;
            position: relative;
            width: 100%;
            height: 100%;
        }
        /* Ensure header input leaves room for the overlay icon without changing column width */
        .dc-col-control input {
            padding-right: 36px; /* space for the delete icon */
            box-sizing: border-box;
            margin-bottom: 0;
        }

        .dc-delete-col {
            position: absolute;
            right: 6px;
            bottom: 6px;
            color: #ef4444;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            padding: 2px 6px;
            border-radius: 3px;
            background: transparent;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .dc-delete-col:focus { outline: 2px solid rgba(239,68,68,0.15); border-radius:3px; }

        /* Align table headers to the bottom to match data rows */
        table.dc-table th { vertical-align: bottom; }
        table.dc-table td { vertical-align: middle; }

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
            <div class="dc-main-type" style="display:flex; align-items:center; gap:8px;">
                <label for="dearcharts_type" style="font-weight:600; color:var(--dc-muted);">Chart Type:</label>
                <select name="dearcharts_type" id="dearcharts_type" onchange="dearcharts_update_live_preview(); dearcharts_local_autosave();">
                    <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie</option>
                    <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut</option>
                    <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar</option>
                    <option value="line" <?php selected($chart_type, 'line'); ?>>Line</option>
                </select>
                <span id="dc-inline-recommend" style="display:none; margin-left:8px;">
                    <span class="dc-rec-badge" role="status" aria-live="polite">
                        <svg class="dc-rec-bulb" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" aria-label="Recommendation">
                        <path d="M9 18h6v1a1 1 0 01-1 1H10a1 1 0 01-1-1v-1z" fill="#fff"/>
                        <path d="M12 2a6 6 0 00-4.5 9.9V14a3 3 0 003 3h3a3 3 0 003-3v-2.1A6 6 0 0012 2zm0 2a4 4 0 012.83 6.83L14.5 11H9.5l-.33-.17A4 4 0 0112 4z" fill="#fff"/>
                        </svg>
                        <span class="dc-rec-text">
                            <span class="dc-rec-prefix">Recommendation:</span>
                            <span id="dc-inline-recommend-text" class="dc-rec-primary"></span>
                            <span id="dc-inline-recommend-reason" class="dc-rec-reason" aria-hidden="true"></span>
                        </span>
                        <button type="button" class="dc-rec-apply" id="dc-inline-recommend-apply" style="display:none;" aria-label="Apply recommendation">Apply</button>
                    </span>
                </span>
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
                                            foreach ($manual_data as $r => $row) {
                                                if ($r === 0 || !is_array($row)) continue;
                                                echo '<tr>';
                                                foreach ($row as $cell) {
                                                    echo '<td><input type="text" name="dearcharts_manual_data[' . esc_attr($r) . '][]" value="' . esc_attr($cell) . '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
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
        jQuery(function(){ dearcharts_add_delete_col_controls();
            // debounce recommendation trigger for table edits
            var _recTimer = null;
            jQuery('#dc-manual-table').on('input', 'input', function(){
                clearTimeout(_recTimer);
                _recTimer = setTimeout(function(){ dearcharts_recommend_inline(); }, 500);
            });
            // watch CSV input field
            jQuery('#dearcharts_csv_url').on('input', function(){
                clearTimeout(_recTimer);
                _recTimer = setTimeout(function(){ dearcharts_recommend_inline(); dearcharts_local_autosave(); }, 600);
            });
            // wire apply button
            jQuery(document).on('click', '#dc-inline-recommend-apply', function(){
                var type = jQuery(this).data('type');
                if (type) {
                    jQuery('#dearcharts_type').val(type).trigger('change');
                    dearcharts_update_live_preview();
                    jQuery('#dc-inline-recommend').hide();
                    jQuery('#dc-status').text('Applied: ' + jQuery('#dearcharts_type option:selected').text());
                    setTimeout(function(){ jQuery('#dc-status').text(''); }, 2000);
                }
            });
        });

        function dearcharts_analyze_table() {
            var $table = jQuery('#dc-manual-table');
            var cols = Math.max(0, $table.find('thead th').length - 1);
            var rows = $table.find('tbody tr').length;
            var numericCells = 0, totalCells = 0, firstColValues = [];
            $table.find('tbody tr').each(function(){
                jQuery(this).find('td').each(function(idx){
                    // skip delete button cell
                    if (idx === cols) return;
                    var val = jQuery(this).find('input').val();
                    if (idx === 0) firstColValues.push(val);
                    if (val !== undefined && val !== '') {
                        totalCells++;
                        if (!isNaN(parseFloat(val)) && isFinite(val)) numericCells++;
                    }
                });
            });
            var numericRatio = totalCells ? (numericCells / totalCells) : 0;
            var timeMatches = 0; firstColValues.forEach(function(v){ if (Date.parse(v)) timeMatches++; });
            var isTimeSeries = (rows > 1 && timeMatches / rows > 0.6);
            return { cols: cols, rows: rows, numericRatio: numericRatio, isTimeSeries: isTimeSeries };
        }

        async function dearcharts_analyze_csv(url) {
            if (!url) return null;
            try {
                // fetch small portion to avoid long downloads
                var res = await fetch(url, { method:'GET', cache:'no-cache' });
                if (!res.ok) return null;
                var text = await res.text();
                // simple CSV parsing: split lines, sample first 50 lines
                var lines = text.split(/\r?\n/).filter(Boolean);
                if (lines.length < 2) return null;
                var header = lines[0].split(',');
                var cols = Math.max(0, header.length - 1);
                var rows = Math.max(0, Math.min(200, lines.length - 1));
                var numericCells = 0, totalCells = 0, firstColValues = [];
                for (var r=1; r<Math.min(lines.length, 101); r++) {
                    var parts = lines[r].split(',');
                    for (var c=0;c<parts.length;c++){
                        var val = parts[c].trim();
                        if (c===0) firstColValues.push(val);
                        if (val !== '') { totalCells++; if (!isNaN(parseFloat(val)) && isFinite(val)) numericCells++; }
                    }
                }
                var numericRatio = totalCells ? (numericCells / totalCells) : 0;
                var timeMatches = 0; firstColValues.forEach(function(v){ if (Date.parse(v)) timeMatches++; });
                var isTimeSeries = (rows > 1 && timeMatches / rows > 0.6);
                return { cols: cols, rows: rows, numericRatio: numericRatio, isTimeSeries: isTimeSeries };
            } catch (e) {
                return null;
            }
        }

        function dearcharts_compute_recommendation(analysis) {
            if (!analysis) return null;
            // simple scoring heuristics
            var rec = null;
            if (analysis.cols <= 1) {
                if (analysis.isTimeSeries) {
                    rec = { type:'line', label:'Line', score:0.95, reason:'Time-series data.' };
                } else if (analysis.rows <= 8) {
                    rec = { type:'pie', label:'Pie', score:0.92, reason:'Single series with few categories.' };
                } else {
                    rec = { type:'bar', label:'Bar', score:0.9, reason:'Single series across categories.' };
                }
            } else {
                if (analysis.isTimeSeries) rec = { type:'line', label:'Line', score:0.95, reason:'Multiple series over time.' };
                else rec = { type:'bar', label:'Bar', score:0.9, reason:'Multiple series across categories.' };
            }
            // nudge score down if numeric ratio low
            if (analysis.numericRatio < 0.5) rec.score -= 0.15;
            rec.score = Math.max(0.3, rec.score);
            return rec;
        }

        async function dearcharts_recommend_inline() {
            // prioritize manual table if there's manual rows, else CSV
            var tableAnalysis = dearcharts_analyze_table();
            var rec = null;
            if (tableAnalysis && tableAnalysis.rows > 0) {
                rec = dearcharts_compute_recommendation(tableAnalysis);
            } else {
                var csvUrl = jQuery('#dearcharts_csv_url').val();
                if (csvUrl) {
                    var csvAnalysis = await dearcharts_analyze_csv(csvUrl);
                    rec = dearcharts_compute_recommendation(csvAnalysis);
                }
            }
            if (rec) {
                jQuery('#dc-inline-recommend-text').html('<span class="dc-rec-strong">' + rec.label + '</span> (' + Math.round(rec.score*100) + '%)');
                if (rec.reason) {
                    jQuery('#dc-inline-recommend-reason').text('— ' + rec.reason).show();
                    jQuery('.dc-rec-badge').attr('title', rec.reason);
                } else {
                    jQuery('#dc-inline-recommend-reason').hide();
                    jQuery('.dc-rec-badge').removeAttr('title');
                }
                jQuery('#dc-inline-recommend-apply').data('type', rec.type).show();
                jQuery('#dc-inline-recommend').show();
            } else {
                jQuery('#dc-inline-recommend').hide();
                jQuery('#dc-inline-recommend-apply').hide();
            }
        }





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
            let palette = dc_palettes[jQuery('#dearcharts_palette').val()] || dc_palettes['default'];
            var canvas = document.getElementById('dc-live-chart');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            
            // Capture current state to handle race conditions
            var currentSource = jQuery('#dearcharts_active_source').val();
            var currentUrl = jQuery('#dearcharts_csv_url').val();

            if (dcLiveChart) {
                dcLiveChart.destroy();
                dcLiveChart = null;
            }

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
                const colorArray = labels.map((_, j) => palette[j % palette.length]);
                const singleColor = palette[i % palette.length];

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
                        legend: { display: legendPos !== 'none', position: legendPos }
                    }
                }
            });
        }
        jQuery(document).ready(function () { dearcharts_update_live_preview();
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
            jQuery('#dc-save-chart').on('click', function(){
                // If no changes compared to saved snapshot, inform the user
                var curr = dearcharts_get_snapshot();
                if (snapshotsEqual(curr, dc_saved_snapshot)) {
                    jQuery('#dc-status').show().text('No changes to save').css({ 'color': '#475569', 'background': '#f8fafc' });
                    setTimeout(function(){ jQuery('#dc-status').text(''); }, 2000);
                    return;
                }
                // disable button while saving
                var $btn = jQuery(this).attr('disabled', true).addClass('disabled');
                jQuery('#dc-status').show().text('Saving...').css({ 'color': '#1e3a8a', 'background': '#eef2ff' });
                // prepare payload
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
                        // update saved snapshot and clear local autosave
                        dc_saved_snapshot = curr;
                        var key = 'dearcharts_autosave_' + dc_post_id; localStorage.removeItem(key);
                        jQuery('#dc-status').show().text(res.data && res.data.no_changes ? 'No changes' : 'Saved').css({ 'color': '#065f46', 'background': '#ecfdf5' });
                        setTimeout(function(){ jQuery('#dc-status').text(''); }, 2000);
                        updateSaveButtonState();
                    } else {
                        var msg = (res && res.data && res.data.message) ? res.data.message : 'Save failed';
                        jQuery('#dc-status').show().text(msg).css({ 'color': '#ef4444', 'background': '#fff1f2' });
                        setTimeout(function(){ jQuery('#dc-status').text(''); }, 3000);
                    }
                }).fail(function(){ jQuery('#dc-status').show().text('Save failed').css({ 'color': '#ef4444', 'background': '#fff1f2' }); }).always(function(){ $btn.removeAttr('disabled').removeClass('disabled'); });
            });
        });

        function dearcharts_get_snapshot() {
            var snapshot = { manual: { headers: [], rows: [] }, csv_url: jQuery('#dearcharts_csv_url').val() || '', active_source: jQuery('#dearcharts_active_source').val() || 'manual', type: jQuery('#dearcharts_type').val() || '', legend_pos: jQuery('#dearcharts_legend_pos').val() || '', palette: jQuery('#dearcharts_palette').val() || '' };
            // headers (skip label column i=0 and add button i=last)
            jQuery('#dc-manual-table thead th').each(function(i){ if (i === 0 || i === jQuery('#dc-manual-table thead th').length - 1) return; var v = jQuery(this).find('input').val() || ''; snapshot.manual.headers.push(v); });
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

        function updateSaveButtonState(){ var curr = dearcharts_get_snapshot(); if (snapshotsEqual(curr, dc_saved_snapshot)) { jQuery('#dc-save-chart').attr('disabled', true).addClass('disabled'); } else { jQuery('#dc-save-chart').removeAttr('disabled').removeClass('disabled'); } }

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
 * AJAX Save Handler for quick saving from the editor without publishing the post.
 */
function dearcharts_ajax_save_chart() {
    // Validate nonce and permissions
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'dearcharts_save_meta')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (! $post_id || ! current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Build current saved normalized snapshot for comparison
    $saved_manual = get_post_meta($post_id, '_dearcharts_manual_data', true);
    $saved_manual_norm = array('headers' => array(), 'rows' => array());
    if (!empty($saved_manual) && is_array($saved_manual)) {
        if (isset($saved_manual[0]) && is_array($saved_manual[0]) && isset($saved_manual[0]['label'])) {
            $saved_manual_norm['headers'] = array('Label', 'Value');
            foreach ($saved_manual as $row) {
                if (!isset($row['label']) || !isset($row['value'])) continue;
                $saved_manual_norm['rows'][] = array($row['label'], $row['value']);
            }
        } else {
            $headers = isset($saved_manual[0]) && is_array($saved_manual[0]) ? array_values($saved_manual[0]) : array();
            $saved_manual_norm['headers'] = $headers;
            foreach ($saved_manual as $k => $row) {
                if ($k === 0 || !is_array($row)) continue;
                $saved_manual_norm['rows'][] = array_values($row);
            }
        }
    }

    $current_snapshot = array(
        'manual' => $saved_manual_norm,
        'csv_url' => get_post_meta($post_id, '_dearcharts_csv_url', true),
        'active_source' => get_post_meta($post_id, '_dearcharts_active_source', true),
        'type' => get_post_meta($post_id, '_dearcharts_type', true),
        'legend_pos' => get_post_meta($post_id, '_dearcharts_legend_pos', true),
        'palette' => get_post_meta($post_id, '_dearcharts_palette', true)
    );

    // Parse incoming snapshot from request
    $incoming_manual = array();
    if (isset($_POST['manual_json']) && $_POST['manual_json']) {
        $incoming_manual = json_decode(wp_unslash($_POST['manual_json']), true);
        if (!is_array($incoming_manual)) $incoming_manual = array('headers'=>array(), 'rows'=>array());
    }
    $incoming_snapshot = array(
        'manual' => is_array($incoming_manual) ? $incoming_manual : array('headers'=>array(), 'rows'=>array()),
        'csv_url' => isset($_POST['dearcharts_csv_url']) ? esc_url_raw($_POST['dearcharts_csv_url']) : '',
        'active_source' => isset($_POST['dearcharts_active_source']) ? sanitize_text_field($_POST['dearcharts_active_source']) : '',
        'type' => isset($_POST['dearcharts_type']) ? sanitize_text_field($_POST['dearcharts_type']) : '',
        'legend_pos' => isset($_POST['dearcharts_legend_pos']) ? sanitize_text_field($_POST['dearcharts_legend_pos']) : '',
        'palette' => isset($_POST['dearcharts_palette']) ? sanitize_text_field($_POST['dearcharts_palette']) : ''
    );

    // If incoming snapshot equals saved snapshot, return fast 'no changes'
    if ($incoming_snapshot == $current_snapshot) {
        wp_send_json_success(array('message' => 'No changes', 'no_changes' => true));
    }

    // Apply updates (only for fields present in the request)
    if (!empty($incoming_manual) && is_array($incoming_manual)) {
        // convert normalized manual to storage format: headers + rows -> columnar array
        // store as first row = headers, subsequent rows indexed numerically
        $store_manual = array();
        $store_manual[0] = $incoming_manual['headers'];
        foreach ($incoming_manual['rows'] as $idx => $r) {
            $store_manual[$idx + 1] = $r;
        }
        update_post_meta($post_id, '_dearcharts_manual_data', dearcharts_sanitize_manual_data($store_manual));
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

    wp_send_json_success(array('message' => 'Saved'));
}
add_action('wp_ajax_dearcharts_save_chart', 'dearcharts_ajax_save_chart');
