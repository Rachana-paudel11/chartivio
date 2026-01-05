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
    $active_source = get_post_meta($post->ID, '_dearcharts_active_source', true) ?: 'manual';

    // Aesthetic Settings
    $chart_type = get_post_meta($post->ID, '_dearcharts_type', true) ?: (get_post_meta($post->ID, '_dearcharts_is_donut', true) === '1' ? 'doughnut' : 'pie');
    $legend_pos = get_post_meta($post->ID, '_dearcharts_legend_pos', true) ?: 'top';
    $palette_key = get_post_meta($post->ID, '_dearcharts_palette', true) ?: 'default';
    $xaxis_label = get_post_meta($post->ID, '_dearcharts_xaxis_label', true);
    $yaxis_label = get_post_meta($post->ID, '_dearcharts_yaxis_label', true);

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

        .dc-main-header h2 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: var(--dc-text);
        }

        .dc-main-header .dc-main-type {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dc-main-header .dc-main-type label {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--dc-muted);
        }

        .dc-main-header .dc-main-type select,
        .dc-main-header select {
            padding: 4px 26px 4px 8px;
            border-radius: 6px;
            font-size: 13px;
            position: relative;
            z-index: 3;
            box-sizing: border-box;
        }

        /* ensure the inline badge and icons sit behind or beside the select */
        .dc-delete-col {
            position: relative;
            z-index: 1;
        }

        .dc-split-container {
            display: flex;
            height: 650px;
            background: #fff;
            overflow: hidden;
        }

        .dc-preview-panel {
            flex: 0 0 550px;
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
            max-width: 500px;
            height: 500px;
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
            min-height: 0;
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
            min-height: 0;
        }

        /* Ensure manual data entry table wrapper is the only scrollable element */
        #dc-manual-body {
            overflow: visible !important;
        }

        #dc-manual-body .dc-table-wrapper {
            overflow-x: auto !important;
            overflow-y: auto !important;
        }

        .dc-tab-content.active {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .dc-card {
            border: 1px solid var(--dc-border);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            display: flex;
            flex-direction: column;
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
            padding: 15px;
            overflow: visible;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        #dc-manual-body {
            transition: opacity 0.3s ease;
            overflow: visible;
            display: block !important;
            visibility: visible !important;
        }

        #dc-manual-body * {
            visibility: visible !important;
        }

        #dc-manual-body table,
        #dc-manual-body .dc-table-wrapper,
        #dc-manual-body .dc-table-actions {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* Ensure buttons are always visible below the table */
        .dc-table-actions {
            flex-shrink: 0 !important;
            background: #fff !important;
            position: sticky !important;
            bottom: -15px !important;
            /* Offset card padding */
            z-index: 20 !important;
            width: calc(100% + 30px) !important;
            margin: 12px -15px -15px -15px !important;
            padding: 12px 15px !important;
            border-top: 1px solid #e2e8f0 !important;
            display: flex !important;
            gap: 10px !important;
            flex-wrap: wrap !important;
            visibility: visible !important;
            opacity: 1 !important;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.02);
        }

        .dc-table-actions button {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        .dc-table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            width: 100%;
            flex: 1;
            height: auto !important;
            max-height: none !important;
            min-height: 100px !important;
            margin-bottom: 0px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 0;
            display: block !important;
            visibility: visible !important;
            background: #fff;
            box-sizing: border-box;
            position: relative;
            -webkit-overflow-scrolling: touch;
            flex-shrink: 0;
        }

        table.dc-table {
            display: table !important;
            visibility: visible !important;
            width: 100% !important;
        }

        table.dc-table th,
        table.dc-table td {
            display: table-cell !important;
            visibility: visible !important;
        }

        table.dc-table th:last-child {
            display: table-cell !important;
            visibility: visible !important;
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
            table-layout: auto;
            margin: 0;
            border-spacing: 0;
        }

        table.dc-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f8fafc;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        }

        table.dc-table th,
        table.dc-table td {
            padding: 8px;
            box-sizing: border-box;
            min-width: 160px;
            vertical-align: middle;
            white-space: nowrap;
            border: 1px solid #e2e8f0;
        }

        table.dc-table th {
            font-weight: 600;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            background: #f8fafc;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
        }

        #dc-manual-table th:last-child,
        #dc-manual-table td:last-child {
            width: 45px;
            min-width: 45px;
            max-width: 45px;
            text-align: center;
            border-right: none;
            padding: 4px;
        }

        table.dc-table input {
            width: 100% !important;
            display: block !important;
            box-sizing: border-box !important;
            border: 1px solid #cbd5e1 !important;
            padding: 8px 10px !important;
            border-radius: 4px !important;
            font-size: 13px !important;
            height: 36px !important;
            margin: 0 !important;
            transition: border-color 0.2s;
            visibility: visible !important;
            opacity: 1 !important;
        }

        table.dc-table input:focus {
            outline: none;
            border-color: var(--dc-primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .dc-delete-row {
            color: #ef4444 !important;
            cursor: pointer !important;
            padding: 12px !important;

            font-size: 18px !important;
            font-weight: bold !important;
            line-height: 1 !important;
            display: table-cell !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        .dc-delete-row:hover {
            background: #fef2f2;
        }

        /* Column Controls */
        .dc-col-control {
            position: relative !important;
            width: 100% !important;
            display: block !important;
        }

        table.dc-table th .dc-col-control input {
            padding-right: 36px !important;
            /* Space for X */
        }

        table.dc-table th .dc-col-control {
            display: block !important;
            width: 100% !important;
            position: relative !important;
        }

        .dc-delete-col {
            position: absolute !important;
            right: 6px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            color: #ef4444 !important;
            cursor: pointer !important;
            font-size: 16px !important;
            font-weight: bold !important;
            background: rgba(255, 255, 255, 0.9) !important;
            border: none !important;
            border-radius: 4px !important;
            padding: 2px !important;
            width: 20px !important;
            height: 20px !important;
            line-height: 1 !important;
            z-index: 5 !important;
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            align-items: center;
            justify-content: center;
        }

        .dc-delete-col:hover {
            background: #fee2e2 !important;
            color: #dc2626 !important;
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
        #submitdiv {
            visibility: hidden;
            position: absolute;
            width: 0;
            height: 0;
            overflow: hidden;
        }

        /* Responsive Adjustments */
        @media (max-width: 1000px) {
            .dc-split-container {
                flex-direction: column;
                height: auto !important;
            }

            .dc-preview-panel {
                flex: 0 0 auto;
                width: auto;
                border-right: none;
                border-bottom: 1px solid var(--dc-border);
                padding: 20px;
            }

            .dc-chart-container {
                max-width: 100%;
                margin: 0 auto;
                /* Center on mobile */
            }

            .dc-settings-panel {
                flex: 1 0 auto;
                min-height: 500px;
                /* Ensure space for settings */
            }

            .dc-main-header {
                flex-wrap: wrap;
                gap: 10px;
            }

            .dc-type-selector-inline {
                margin-left: 0;
                width: 100%;
                justify-content: space-between;
                padding-top: 10px;
                border-top: 1px solid #f1f5f9;
            }
        }

        /* Hide the default WordPress "Add title" placeholder/label */
        #title-prompt-text {
            display: none !important;
        }
    </style>

    <div class="dc-admin-wrapper">
        <div class="dc-main-header">
            <div style="display:flex; align-items:center; gap:15px;">
                <h2>Chart Type</h2>
                <select name="dearcharts_type" id="dearcharts_type" onchange="dearcharts_update_live_preview()">
                    <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie</option>
                    <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut</option>
                    <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar</option>
                    <option value="line" <?php selected($chart_type, 'line'); ?>>Line</option>
                </select>
            </div>
            <div class="dc-type-selector-inline">
                <button type="button" class="button button-primary" data-pid="<?php echo $post->ID; ?>"
                    onclick="dearcharts_quick_save(this)">Save Chart</button>
                <span id="dc-save-status" style="font-size:13px; font-weight:500;"></span>
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

                    <div class="dc-card" style="margin-bottom: 20px;">
                        <div class="dc-card-header">
                            <span>Select Data Source</span>
                        </div>
                        <div class="dc-card-body">
                            <select id="dc_source_selector_dropdown" onchange="dcSetSource(this.value)"
                                style="width: 100%; padding: 8px 35px 8px 12px; border-radius: 6px; border: 1px solid var(--dc-border); background: #f8fafc url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2364748b%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E') no-repeat right 10px center; background-size: 16px; appearance: none; font-size: 14px; font-weight: 500; cursor: pointer; color: var(--dc-text);">
                                <option value="manual" <?php selected($active_source, 'manual'); ?>>Manual Data Entry
                                </option>
                                <option value="csv" <?php selected($active_source, 'csv'); ?>>Import from CSV</option>
                            </select>
                        </div>
                    </div>

                    <div class="dc-card dc-source-panel" id="dc-csv-panel"
                        style="<?php echo ($active_source === 'csv') ? 'display:flex;' : 'display:none;'; ?>">
                        <div class="dc-card-header">
                            <span>Import from CSV</span>
                        </div>
                        <div class="dc-card-body" id="dc-csv-body">
                            <div style="display:flex; gap:8px; margin-bottom: 12px;">
                                <input type="text" name="dearcharts_csv_url" id="dearcharts_csv_url" class="dc-input-text"
                                    style="flex:1;" value="<?php echo esc_url($csv_url); ?>"
                                    oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();">
                                <button type="button" class="button" onclick="dcBrowseCSV()">Media</button>
                            </div>
                            <!-- CSV Data Preview Table -->
                            <div id="dc-csv-preview-label"
                                style="font-weight: 600; color: #475569; font-size: 12px; text-transform: uppercase; margin-bottom: 8px; display: none;">
                                CSV Data Preview</div>
                            <div id="dc-csv-preview-container" class="dc-table-wrapper" style="display: none;">
                                <table class="dc-table" id="dc-csv-preview-table">
                                    <thead></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="dc-card dc-source-panel" id="dc-manual-panel"
                        style="<?php echo ($active_source === 'manual') ? 'display:flex;' : 'display:none;'; ?>">
                        <div class="dc-card-header">
                            <span>Manual Data Entry</span>
                        </div>
                        <div class="dc-card-body" id="dc-manual-body">
                            <style>
                                /* Ensure manual data specific behaviors */
                                #dc-manual-body {
                                    flex: 1;
                                    display: flex !important;
                                    flex-direction: column !important;
                                }

                                #dc-manual-body .dc-table-wrapper {
                                    flex: 1 !important;
                                }
                            </style>
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
                                            <th style="width:45px !important; min-width:45px !important; max-width:45px !important; cursor:pointer !important; background: #eff6ff !important; text-align: center !important; font-size: 18px !important; font-weight: bold !important; color: var(--dc-primary) !important; display: table-cell !important; visibility: visible !important; opacity: 1 !important;"
                                                onclick="dearcharts_add_column()" title="Add Column">+</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (!empty($manual_data) && is_array($manual_data) && (isset($manual_data[0]['label']) || count($manual_data) > 1)) {
                                            if (isset($manual_data[0]['label'])) {
                                                foreach ($manual_data as $i => $row) {
                                                    if (!isset($row['label']) || !isset($row['value']))
                                                        continue;
                                                    echo '<tr>';
                                                    echo '<td><input type="text" name="dearcharts_manual_data[' . ($i + 1) . '][]" value="' . esc_attr($row['label']) . '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
                                                    echo '<td><input type="text" name="dearcharts_manual_data[' . ($i + 1) . '][]" value="' . esc_attr($row['value']) . '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
                                                    echo '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview(); dearcharts_local_autosave();">×</td></tr>';
                                                }
                                            } else {
                                                foreach ($manual_data as $r => $row_data) {
                                                    if ($r === 0 || !is_array($row_data))
                                                        continue;
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
                                            if ($col_count < 2)
                                                $col_count = 2;

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
                            <div class="dc-table-actions"
                                style="display:flex !important; gap:10px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; visibility: visible !important; opacity: 1 !important;">
                                <button type="button" class="button button-secondary" onclick="dearcharts_add_row()"
                                    style="min-width: 120px; font-weight: 500; display: inline-block !important; visibility: visible !important;">+
                                    Add Row</button>
                                <button type="button" class="button button-secondary" onclick="dearcharts_add_column()"
                                    style="min-width: 120px; font-weight: 500; display: inline-block !important; visibility: visible !important;">+
                                    Add Column</button>
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
                            <div class="dc-setting-row dc-axis-setting">
                                <span class="dc-setting-label">X-Axis Title</span>
                                <input type="text" name="dearcharts_xaxis_label" id="dearcharts_xaxis_label"
                                    value="<?php echo esc_attr($xaxis_label); ?>" style="width: 50% !important;"
                                    oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();">
                            </div>
                            <div class="dc-setting-row dc-axis-setting">
                                <span class="dc-setting-label">Y-Axis Title</span>
                                <input type="text" name="dearcharts_yaxis_label" id="dearcharts_yaxis_label"
                                    value="<?php echo esc_attr($yaxis_label); ?>" style="width: 50% !important;"
                                    oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();">
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
        var dc_is_updating_preview = false; // Flag to prevent concurrent updates
        // Normalized snapshot of saved post meta for client-side comparisons
        var dc_saved_snapshot = <?php
        $saved_manual_norm = array('headers' => array(), 'rows' => array());
        if (!empty($manual_data) && is_array($manual_data)) {
            // Legacy label/value format
            if (isset($manual_data[0]) && is_array($manual_data[0]) && isset($manual_data[0]['label'])) {
                $saved_manual_norm['headers'] = array('Label', 'Value');
                foreach ($manual_data as $i => $row) {
                    if (!isset($row['label']) || !isset($row['value']))
                        continue;
                    $saved_manual_norm['rows'][] = array($row['label'], $row['value']);
                }
            } else {
                // Columnar format: first row is headers
                $headers = isset($manual_data[0]) && is_array($manual_data[0]) ? array_values($manual_data[0]) : array();
                $saved_manual_norm['headers'] = $headers;
                foreach ($manual_data as $k => $row) {
                    if ($k === 0 || !is_array($row))
                        continue;
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
            'palette' => $palette_key,
            'xaxis_label' => $xaxis_label,
            'yaxis_label' => $yaxis_label
        );
        echo wp_json_encode($saved_snapshot);
        ?>;
        var dc_palettes = { 'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'], 'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'], 'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'], 'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'], 'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'], 'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F'] };

        function dc_parse_csv(str) {
            var arr = [];
            var quote = false;
            for (var row = 0, col = 0, c = 0; c < str.length; c++) {
                var cc = str[c], nc = str[c + 1];
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

        function dearcharts_update_csv_preview_table(rows) {
            var $label = jQuery('#dc-csv-preview-label');
            var $container = jQuery('#dc-csv-preview-container');
            var $thead = jQuery('#dc-csv-preview-table thead');
            var $tbody = jQuery('#dc-csv-preview-table tbody');

            $thead.empty();
            $tbody.empty();

            if (!rows || rows.length === 0) {
                $label.hide();
                $container.hide();
                return;
            }

            // Headers
            var headHtml = '<tr>';
            if (rows[0]) {
                rows[0].forEach(function (h) {
                    headHtml += '<th>' + jQuery('<div/>').text(h || '').html() + '</th>';
                });
            }
            headHtml += '</tr>';
            $thead.append(headHtml);

            // Body
            for (var i = 1; i < rows.length; i++) {
                if (!rows[i]) continue;
                var rowHtml = '<tr>';
                rows[i].forEach(function (c) {
                    rowHtml += '<td>' + jQuery('<div/>').text(c || '').html() + '</td>';
                });
                rowHtml += '</tr>';
                $tbody.append(rowHtml);
            }

            $label.show();
            $container.show();
        }

        function dcTab(el, id) { jQuery('.dc-tab').removeClass('active'); jQuery('.dc-tab-content').removeClass('active'); jQuery(el).addClass('active'); jQuery('#' + id).addClass('active'); }
        function dcSetSource(src) {
            jQuery('#dearcharts_active_source').val(src);
            jQuery('.dc-source-panel').hide();
            jQuery('#dc-' + src + '-panel').css('display', 'flex');
            if (src === 'manual') {
                // Re-initialize column delete controls when switching to manual
                setTimeout(function () {
                    dearcharts_add_delete_col_controls();
                }, 100);
            }
            dearcharts_update_live_preview();
            dearcharts_local_autosave();
        }
        function dcBrowseCSV() { var media = wp.media({ title: 'Select CSV', multiple: false }).open().on('select', function () { var url = media.state().get('selection').first().toJSON().url; jQuery('#dearcharts_csv_url').val(url); dearcharts_update_live_preview(); dearcharts_local_autosave(); }); }
        function dearcharts_add_row() {
            var colCount = jQuery('#dc-manual-table thead th').length - 1;
            var rowKey = Date.now();
            var html = '<tr>';
            for (var i = 0; i < colCount; i++) {
                html += '<td><input type="text" name="dearcharts_manual_data[' + rowKey + '][]" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>';
            }
            html += '<td class="dc-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); dearcharts_update_live_preview(); dearcharts_local_autosave();">×</td></tr>';
            var $newRow = jQuery(html);
            jQuery('#dc-manual-table tbody').append($newRow);
            dearcharts_update_live_preview();
            dearcharts_local_autosave();
            var $wrapper = jQuery('.dc-table-wrapper');
            $wrapper.animate({ scrollTop: $wrapper.prop("scrollHeight") }, 500, function () {
                $newRow.find('input:first').focus();
            });
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
            newMatrix[0].forEach(h => jQuery('<th><input type="text" name="dearcharts_manual_data[0][]" value="' + (h || '').replace(/"/g, '&quot;') + '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>').insertBefore(jQuery('#dc-manual-table thead th').last()));

            // Rebuild Rows
            newMatrix.slice(1).forEach((row, idx) => {
                var html = '<tr>';
                row.forEach(cell => html += '<td><input type="text" name="dearcharts_manual_data[' + (Date.now() + idx) + '][]" value="' + (cell || '').replace(/"/g, '&quot;') + '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></td>');
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
            $wrapper.animate({ scrollLeft: $wrapper.prop("scrollWidth") }, 500, function () {
                var newColIdx = jQuery('#dc-manual-table thead th').length - 2;
                jQuery('#dc-manual-table thead th').eq(newColIdx).find('input').focus();
            });
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
            jQuery('#dc-manual-table').off('click', '.dc-delete-col').on('click', '.dc-delete-col', function () {
                var idx = parseInt(jQuery(this).attr('data-col-idx'), 10);
                dearcharts_delete_column(idx);
            });
            jQuery('#dc-manual-table').off('keydown', '.dc-delete-col').on('keydown', '.dc-delete-col', function (e) {
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
        // ensure controls are present on DOM ready and after table is rendered
        jQuery(function () {
            // Initial call
            setTimeout(function () {
                dearcharts_add_delete_col_controls();
            }, 200);

            // Also call when manual source is active
            if (jQuery('#dearcharts_active_source').val() === 'manual') {
                setTimeout(function () {
                    dearcharts_add_delete_col_controls();
                }, 500);
            }
        });





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
            // Prevent concurrent calls
            if (dc_is_updating_preview) {
                return;
            }
            dc_is_updating_preview = true;

            try {
                // Wait for Chart.js to be available
                if (typeof Chart === 'undefined') {
                    // Retry after a short delay if Chart.js is still loading
                    setTimeout(function () {
                        if (typeof Chart !== 'undefined') {
                            dc_is_updating_preview = false;
                            dearcharts_update_live_preview();
                        }
                    }, 100);
                    dc_is_updating_preview = false;
                    return;
                }

                var canvas = document.getElementById('dc-live-chart');
                if (!canvas) {
                    dc_is_updating_preview = false;
                    return;
                }

                // Ensure canvas has proper dimensions
                var container = canvas.parentElement;
                if (container && (canvas.width === 0 || canvas.height === 0)) {
                    var rect = container.getBoundingClientRect();
                    if (rect.width > 0 && rect.height > 0) {
                        canvas.width = rect.width;
                        canvas.height = rect.height;
                    } else {
                        // Fallback dimensions
                        canvas.width = 400;
                        canvas.height = 400;
                    }
                }

                var ctx = canvas.getContext('2d');

                // Ensure any existing chart on this canvas is destroyed (fixes "Canvas is already in use")
                // Destroy in order: global reference first, then Chart.js registry

                // First, destroy the global chart reference if it exists
                if (dcLiveChart) {
                    try {
                        if (typeof dcLiveChart.destroy === 'function') {
                            dcLiveChart.destroy();
                        }
                    } catch (e) {
                        console.warn('Error destroying dcLiveChart:', e);
                    }
                    dcLiveChart = null;
                }

                // Then check Chart.js registry for any chart on this canvas
                try {
                    if (typeof Chart !== 'undefined' && typeof Chart.getChart === 'function') {
                        var existingChart = Chart.getChart(canvas);
                        if (existingChart && existingChart !== dcLiveChart) {
                            try {
                                existingChart.destroy();
                            } catch (e) {
                                console.warn('Error destroying chart from registry:', e);
                            }
                        }
                    }
                } catch (e) {
                    console.warn('Error accessing Chart.js registry:', e);
                }

                // Clear the canvas context to ensure clean state
                try {
                    var cWidth = canvas.width || 400;
                    var cHeight = canvas.height || 400;
                    ctx.clearRect(0, 0, cWidth, cHeight);
                } catch (e) {
                    console.warn('Error clearing canvas:', e);
                }

                // Small delay to ensure Chart.js has fully cleaned up before creating new chart
                // This prevents "Canvas is already in use" errors
                await new Promise(resolve => setTimeout(resolve, 20));

                // Double-check canvas is still available and not in use
                try {
                    var checkChart = Chart.getChart(canvas);
                    if (checkChart) {
                        console.warn('Chart still exists after destruction, forcing cleanup');
                        checkChart.destroy();
                        await new Promise(resolve => setTimeout(resolve, 10));
                    }
                } catch (e) {
                    // Ignore - canvas might be ready
                }

                // Get settings with defaults
                let chartType = jQuery('#dearcharts_type').val() || 'pie';
                let legendPos = jQuery('#dearcharts_legend_pos').val() || 'top';
                let paletteKey = jQuery('#dearcharts_palette').val() || 'default';
                let palette = (typeof dc_palettes !== 'undefined' && dc_palettes[paletteKey]) ? dc_palettes[paletteKey] : ((typeof dc_palettes !== 'undefined') ? dc_palettes['default'] : ['#3b82f6']);
                let xaxisLabel = jQuery('#dearcharts_xaxis_label').val() || '';
                let yaxisLabel = jQuery('#dearcharts_yaxis_label').val() || '';

                // Toggle axis settings visibility based on chart type
                if (chartType === 'bar' || chartType === 'line') {
                    jQuery('.dc-axis-setting').show();
                } else {
                    jQuery('.dc-axis-setting').hide();
                }

                // Capture current state to handle race conditions
                var currentSource = jQuery('input[name="dc_source_selector"]:checked').val() || jQuery('#dearcharts_active_source').val() || 'manual';
                var currentUrl = jQuery('#dearcharts_csv_url').val();

                var labels = [], datasets = [];

                if (currentSource === 'csv') {
                    if (!currentUrl || currentUrl.trim() === '') {
                        jQuery('#dc-status').show().text('No CSV URL provided').css({ 'color': '#f59e0b', 'background': '#fffbeb' });
                        jQuery('#dc-csv-preview-label').hide();
                        jQuery('#dc-csv-preview-container').hide();
                        // Show empty chart state
                        var cWidth = canvas.width || 400;
                        var cHeight = canvas.height || 400;
                        ctx.clearRect(0, 0, cWidth, cHeight);
                        ctx.fillStyle = '#64748b';
                        ctx.font = '14px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText('No CSV URL provided', cWidth / 2, cHeight / 2);
                        return;
                    }
                    try {
                        const response = await fetch(currentUrl);
                        // Race condition check: Ensure source and URL haven't changed during fetch
                        if (jQuery('#dearcharts_active_source').val() !== 'csv' || jQuery('#dearcharts_csv_url').val() !== currentUrl) return;

                        if (!response.ok) {
                            throw new Error('Failed to fetch CSV: ' + response.statusText);
                        }

                        const text = await response.text();
                        if (!text || text.trim() === '') {
                            throw new Error('CSV file is empty');
                        }

                        const rows = dc_parse_csv(text.trim());
                        if (!rows || rows.length < 2) {
                            throw new Error('Invalid CSV format - need at least header and one data row');
                        }

                        // Update CSV Preview Table
                        dearcharts_update_csv_preview_table(rows);

                        const heads = rows[0];
                        if (!heads || heads.length < 2) {
                            throw new Error('CSV must have at least 2 columns (label + data)');
                        }

                        // Create datasets from headers (skip first column which is labels)
                        for (var i = 1; i < heads.length; i++) {
                            datasets.push({ label: (heads[i] || 'Series ' + i).trim(), data: [] });
                        }

                        // Populate data from rows
                        for (var r = 1; r < rows.length; r++) {
                            const row = rows[r];
                            if (!row || row.length < 2) continue;
                            var label = (row[0] || '').trim();
                            if (label === '') continue; // Skip rows with empty labels
                            labels.push(label);
                            for (var c = 0; c < datasets.length; c++) {
                                var val = parseFloat((row[c + 1] || '').replace(/,/g, '').trim());
                                datasets[c].data.push(isNaN(val) ? 0 : val);
                            }
                        }

                        if (labels.length === 0) {
                            throw new Error('No valid data rows found in CSV');
                        }

                        jQuery('#dc-status').show().text('CSV Loaded').css({ 'color': '#10b981', 'background': '#f0fdf4' });
                    } catch (e) {
                        if (jQuery('#dearcharts_active_source').val() !== 'csv') return;
                        console.error('CSV Fetch Error:', e);
                        jQuery('#dc-status').show().text('Error: ' + e.message).css({ 'color': '#ef4444', 'background': '#fef2f2' });
                        jQuery('#dc-csv-preview-label').hide();
                        jQuery('#dc-csv-preview-container').hide();
                        // Clear canvas and show error
                        var cWidth = canvas.width || 400;
                        var cHeight = canvas.height || 400;
                        ctx.clearRect(0, 0, cWidth, cHeight);
                        ctx.fillStyle = '#ef4444';
                        ctx.font = '14px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText('Error: ' + e.message, cWidth / 2, cHeight / 2);
                        return;
                    }
                } else {
                    // Manual data entry
                    jQuery('#dc-status').hide();
                    jQuery('#dc-csv-preview-label').hide();
                    jQuery('#dc-csv-preview-container').hide();
                    var headerCount = 0;
                    jQuery('#dc-manual-table thead th').each(function (i) {
                        // Skip the last column (delete button column)
                        if (i === jQuery('#dc-manual-table thead th').length - 1) return;
                        var val = jQuery(this).find('input').val() || '';
                        if (i === 0) {
                            // First column is labels, don't create dataset for it
                            headerCount++;
                        } else {
                            datasets.push({ label: val || 'Series ' + (datasets.length + 1), data: [] });
                            headerCount++;
                        }
                    });

                    jQuery('#dc-manual-table tbody tr').each(function () {
                        var rowLabel = jQuery(this).find('td:first input').val() || '';
                        if (rowLabel.trim() === '') return; // Skip empty rows
                        labels.push(rowLabel);
                        var colIndex = 0;
                        jQuery(this).find('td').each(function (i) {
                            // Skip the last column (delete button)
                            if (i === jQuery(this).closest('tr').find('td').length - 1) return;
                            if (colIndex > 0 && colIndex <= datasets.length) {
                                var val = parseFloat(jQuery(this).find('input').val()) || 0;
                                datasets[colIndex - 1].data.push(val);
                            }
                            colIndex++;
                        });
                    });

                    // Ensure all datasets have the same number of data points as labels
                    datasets.forEach(function (ds) {
                        while (ds.data.length < labels.length) {
                            ds.data.push(0);
                        }
                    });
                }

                // Final race condition check before drawing
                if (jQuery('#dearcharts_active_source').val() !== currentSource) return;

                // Validate we have data to render
                if (labels.length === 0 || datasets.length === 0) {
                    jQuery('#dc-status').show().text('No data to display').css({ 'color': '#f59e0b', 'background': '#fffbeb' });
                    // Clear canvas and show message
                    var cWidth = canvas.width || 400;
                    var cHeight = canvas.height || 400;
                    ctx.clearRect(0, 0, cWidth, cHeight);
                    ctx.fillStyle = '#64748b';
                    ctx.font = '14px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText('No data to display', cWidth / 2, cHeight / 2);
                    return;
                }

                // Validate datasets have data
                var hasData = false;
                datasets.forEach(function (ds) {
                    if (ds.data && ds.data.length > 0) {
                        var sum = ds.data.reduce(function (a, b) { return a + b; }, 0);
                        if (sum > 0) hasData = true;
                    }
                });

                if (!hasData) {
                    jQuery('#dc-status').show().text('All values are zero').css({ 'color': '#f59e0b', 'background': '#fffbeb' });
                }

                // Apply colors to datasets
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

                // Create the chart
                dcLiveChart = new Chart(ctx, {
                    type: chartType,
                    data: { labels: labels, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: (chartType === 'bar' || chartType === 'line') ? {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: !!yaxisLabel,
                                    text: yaxisLabel
                                }
                            },
                            x: {
                                title: {
                                    display: !!xaxisLabel,
                                    text: xaxisLabel
                                }
                            }
                        } : {},
                        plugins: {
                            legend: {
                                display: legendPos !== 'none' && (datasets.length > 1 || ['pie', 'doughnut'].includes(chartType)),
                                position: legendPos || 'top'
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Chart Render Error:', e);
                jQuery('#dc-status').show().text('Preview Error: ' + e.message).css({ 'color': '#ef4444', 'background': '#fff1f2' });
                // Try to clear canvas on error
                try {
                    var errorCanvas = document.getElementById('dc-live-chart');
                    if (errorCanvas) {
                        var errorCtx = errorCanvas.getContext('2d');
                        var cWidth = errorCanvas.width || 400;
                        var cHeight = errorCanvas.height || 400;
                        errorCtx.clearRect(0, 0, cWidth, cHeight);
                        errorCtx.fillStyle = '#ef4444';
                        errorCtx.font = '14px sans-serif';
                        errorCtx.textAlign = 'center';
                        errorCtx.fillText('Error: ' + e.message, cWidth / 2, cHeight / 2);
                    }
                } catch (clearError) {
                    console.error('Error clearing canvas:', clearError);
                }
            } finally {
                // Always reset the flag
                dc_is_updating_preview = false;
            }
        }
        jQuery(document).ready(function () {
            // Initial render: ensure Chart.js is loaded
            function initPreview() {
                if (typeof Chart === 'undefined') {
                    // Wait for Chart.js to load, check every 100ms for up to 5 seconds
                    var attempts = 0;
                    var checkInterval = setInterval(function () {
                        attempts++;
                        if (typeof Chart !== 'undefined') {
                            clearInterval(checkInterval);
                            dearcharts_update_live_preview();
                        } else if (attempts > 50) {
                            clearInterval(checkInterval);
                            console.error('Chart.js failed to load after 5 seconds');
                            jQuery('#dc-status').show().text('Chart.js library not loaded').css({ 'color': '#ef4444', 'background': '#fef2f2' });
                        }
                    }, 100);
                } else {
                    dearcharts_update_live_preview();
                }
            }

            // Try immediately, then also on window load as fallback
            initPreview();
            jQuery(window).on('load', function () {
                if (typeof Chart !== 'undefined' && !dcLiveChart) {
                    dearcharts_update_live_preview();
                }
            });

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
                        jQuery('#dearcharts_legend_pos').val(local_snapshot.legend_pos);
                        jQuery('#dearcharts_palette').val(local_snapshot.palette);
                        if (local_snapshot.xaxis_label !== undefined) jQuery('#dearcharts_xaxis_label').val(local_snapshot.xaxis_label);
                        if (local_snapshot.yaxis_label !== undefined) jQuery('#dearcharts_yaxis_label').val(local_snapshot.yaxis_label);
                        if (local_snapshot.active_source === 'manual') {
                            // clear table
                            jQuery('#dc-manual-table thead tr').html('<th style="width:40px; cursor:pointer;" onclick="dearcharts_add_column()">+</th>');
                            jQuery('#dc-manual-table tbody').html('');
                            // add headers
                            local_snapshot.manual.headers.forEach(function (h) {
                                jQuery('<th><input type="text" name="dearcharts_manual_data[0][]" value="' + h.replace(/"/g, '"') + '" oninput="dearcharts_update_live_preview(); dearcharts_local_autosave();"></th>').insertBefore(jQuery('#dc-manual-table thead th:last'));
                            });
                            // add rows
                            local_snapshot.manual.rows.forEach(function (row, idx) {
                                var html = '<tr>';
                                row.forEach(function (cell) {
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
                } catch (e) { }
            }
            // Initialize autosave (no restore UI)
            dearcharts_local_autosave();
        });

        function dearcharts_get_snapshot() {
            var snapshot = { manual: { headers: [], rows: [] }, csv_url: jQuery('#dearcharts_csv_url').val() || '', active_source: jQuery('#dearcharts_active_source').val() || 'manual', type: jQuery('#dearcharts_type').val() || '', legend_pos: jQuery('#dearcharts_legend_pos').val() || '', palette: jQuery('#dearcharts_palette').val() || '', xaxis_label: jQuery('#dearcharts_xaxis_label').val() || '', yaxis_label: jQuery('#dearcharts_yaxis_label').val() || '' };
            // headers (skip only add button i=last)
            jQuery('#dc-manual-table thead th').each(function (i) { if (i === jQuery('#dc-manual-table thead th').length - 1) return; var v = jQuery(this).find('input').val() || ''; snapshot.manual.headers.push(v); });
            // rows
            jQuery('#dc-manual-table tbody tr').each(function () { var row = []; jQuery(this).find('td').each(function (i) { if (i === jQuery(this).closest('tr').find('td').length - 1) return; var v = jQuery(this).find('input').val() || ''; row.push(v); }); snapshot.manual.rows.push(row); });
            return snapshot;
        }

        function snapshotsEqual(a, b) { try { return JSON.stringify(a) === JSON.stringify(b); } catch (e) { return false; } }



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
            } catch (e) { }
        }

        function updateSaveButtonState() { /* Button state management removed to allow Publish action at all times */ }

        function dcCopyShortcode() {
            var shortcode = document.getElementById('dc-shortcode').textContent;
            navigator.clipboard.writeText(shortcode).then(function () {
                document.getElementById('dc-copy-status').textContent = 'Copied!';
                setTimeout(function () {
                    document.getElementById('dc-copy-status').textContent = '';
                }, 2000);
            }).catch(function (err) {
                console.error('Failed to copy: ', err);
                document.getElementById('dc-copy-status').textContent = 'Copy failed';
                setTimeout(function () {
                    document.getElementById('dc-copy-status').textContent = '';
                }, 2000);
            });
        }

        function dearcharts_quick_save(btn) {
            // Validation: Ensure Title is present
            // Validation: Ensure Title is present
            var $titleInput = jQuery('input[name="post_title"]'); // Support standard post title input
            if ($titleInput.length === 0) {
                // Fallback if not found by name, try ID
                $titleInput = jQuery('#title');
            }

            var title = $titleInput.val();
            if (!title || title.trim() === '') {
                // Instead of alert, show inline error
                var originalPlaceholder = $titleInput.attr('placeholder');

                // Hide WordPress default prompt label if it exists
                jQuery('#title-prompt-text').addClass('screen-reader-text');

                $titleInput.css('border', '2px solid #ef4444');
                $titleInput.attr('placeholder', 'Please enter a title');
                $titleInput.focus();

                // Revert style on input
                $titleInput.one('input', function () {
                    jQuery(this).css('border', '');
                    // Restore prompt text visibility if empty and needed (standard WP behavior)
                    if (jQuery(this).val().trim() === '') {
                        jQuery('#title-prompt-text').removeClass('screen-reader-text');
                    }
                    if (originalPlaceholder) {
                        jQuery(this).attr('placeholder', originalPlaceholder);
                    } else {
                        jQuery(this).removeAttr('placeholder');
                    }
                });
                return;
            }

            var $btn = jQuery(btn);
            var originalText = $btn.text();
            $btn.text('Saving...').prop('disabled', true);

            var headers = [];
            jQuery('#dc-manual-table thead th input').each(function () { headers.push(jQuery(this).val()); });

            var rows = [];
            jQuery('#dc-manual-table tbody tr').each(function () {
                var row = [];
                jQuery(this).find('td input').each(function () { row.push(jQuery(this).val()); });
                if (row.length > 0) rows.push(row);
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
                dearcharts_palette: jQuery('#dearcharts_palette').val(),
                dearcharts_xaxis_label: jQuery('#dearcharts_xaxis_label').val(),
                dearcharts_yaxis_label: jQuery('#dearcharts_yaxis_label').val()
            };

            jQuery.post(ajaxurl, data, function (res) {
                $btn.text(originalText).prop('disabled', false);
                if (res.success) {
                    jQuery('#dc-save-status').text('Saved!').css('color', '#10b981').show().delay(2000).fadeOut();

                    // Mark post as NOT dirty to prevent browser "Leave site" warnings
                    if (typeof wp !== 'undefined' && wp.autosave && wp.autosave.server) {
                        wp.autosave.server.postChanged = false;
                    }
                    // Reset the "initial" state for the Heartbeat API if needed
                    if (window.onbeforeunload) {
                        // Suppress warning for this navigation if navigating immediately
                        // But usually marking postChanged = false is enough for WP
                    }

                    if (res.data.shortcode_html) {
                        jQuery('#dearcharts_usage_box .inside').html(res.data.shortcode_html);
                    }
                } else {
                    alert('Save Failed');
                }
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
    if (!is_array($data))
        return array();
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

    if (isset($_POST['dearcharts_xaxis_label']))
        update_post_meta($post_id, '_dearcharts_xaxis_label', sanitize_text_field($_POST['dearcharts_xaxis_label']));
    if (isset($_POST['dearcharts_yaxis_label']))
        update_post_meta($post_id, '_dearcharts_yaxis_label', sanitize_text_field($_POST['dearcharts_yaxis_label']));
}
add_action('save_post', 'dearcharts_save_meta_box_data');

/**
 * AJAX Save Handler
 */
function dearcharts_ajax_save_chart()
{
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
    if (isset($_POST['dearcharts_xaxis_label']))
        update_post_meta($post_id, '_dearcharts_xaxis_label', sanitize_text_field($_POST['dearcharts_xaxis_label']));
    if (isset($_POST['dearcharts_yaxis_label']))
        update_post_meta($post_id, '_dearcharts_yaxis_label', sanitize_text_field($_POST['dearcharts_yaxis_label']));

    // Update Title
    $update_args = array('ID' => $post_id, 'post_status' => 'publish');
    if (isset($_POST['post_title'])) {
        $update_args['post_title'] = sanitize_text_field($_POST['post_title']);
    }
    wp_update_post($update_args);

    // Generate Shortcode HTML
    $shortcode_html = '<div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">';
    $shortcode_html .= '<p style="margin-top:0; font-size:13px; color:#64748b;">Copy this shortcode to display the chart:</p>';
    $shortcode_html .= '<code style="display:block; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; font-weight:bold; color:#1e293b;">[dearchart id="' . $post_id . '"]</code>';
    $shortcode_html .= '</div>';

    wp_send_json_success(array('message' => 'Saved', 'shortcode_html' => $shortcode_html));
}
add_action('wp_ajax_dearcharts_save_chart', 'dearcharts_ajax_save_chart');
