<?php
/**
 * Professional Split-Screen Admin UI for chartivio
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
function chartivio_add_custom_metaboxes()
{
    add_meta_box('chartivio_main_box', 'Chart Configuration', 'chartivio_render_main_box', 'chartivio', 'normal', 'high');
    add_meta_box('chartivio_usage_box', 'Chart Shortcodes', 'chartivio_render_usage_box', 'chartivio', 'side', 'low');
}
add_action('add_meta_boxes', 'chartivio_add_custom_metaboxes');

/**
 * Enqueue Admin Assets
 */
function chartivio_admin_assets($hook)
{
    global $post;
    if ($hook == 'post-new.php' || $hook == 'post.php') {
        if ($post && $post->post_type === 'chartivio') {
            wp_enqueue_media();
            // Enqueue Chart.js v4.5.1 from local files
            wp_enqueue_script('chartjs', CHARTIVIO_URL . 'assets/js/chartjs/chart.umd.min.js', array(), '4.5.1', true);
            wp_enqueue_style('chartivio-admin-style', CHARTIVIO_URL . 'assets/css/admin-style.css', array(), '1.0.1');

            wp_enqueue_script('chartivio-admin-settings', CHARTIVIO_URL . 'assets/js/admin-settings.js', array('jquery', 'chartjs'), '1.0.1', true);

            // Prepare Saved Snapshot Data for Localize Script
            $manual_data = get_post_meta($post->ID, '_chartivio_manual_data', true);
            $csv_url = get_post_meta($post->ID, '_chartivio_csv_url', true);
            $active_source = get_post_meta($post->ID, '_chartivio_active_source', true) ?: 'manual';

            // Aesthetic Settings
            $chart_type = get_post_meta($post->ID, '_chartivio_type', true) ?: (get_post_meta($post->ID, '_chartivio_is_donut', true) === '1' ? 'doughnut' : 'pie');
            $legend_pos = get_post_meta($post->ID, '_chartivio_legend_pos', true) ?: 'top';
            $palette_key = get_post_meta($post->ID, '_chartivio_palette', true) ?: 'default';
            $xaxis_label = get_post_meta($post->ID, '_chartivio_xaxis_label', true);
            $yaxis_label = get_post_meta($post->ID, '_chartivio_yaxis_label', true);

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
                'csv_data' => get_post_meta($post->ID, '_chartivio_csv_data', true) ?: null, // Parsed CSV data for comparison
                'active_source' => $active_source,
                'type' => $chart_type,
                'legend_pos' => $legend_pos,
                'palette' => $palette_key,
                'xaxis_label' => $xaxis_label,
                'yaxis_label' => $yaxis_label
            );

            wp_localize_script('chartivio-admin-settings', 'chartivio_admin_vars', array(
                'post_id' => intval($post->ID),
                'nonce' => wp_create_nonce('chartivio_save_meta'),
                'saved_snapshot' => $saved_snapshot
            ));
        }
    }
}
add_action('admin_enqueue_scripts', 'chartivio_admin_assets');

/**
 * Sidebar Meta Box: Shortcodes
 */
function chartivio_render_usage_box($post)
{
    echo '<div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">';
    if (isset($post->post_status) && $post->post_status === 'publish') {
        echo '<p style="margin-top:0; font-size:13px; color:#64748b;">Copy this shortcode to display the chart:</p>';
        echo '<code style="display:block; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; font-weight:bold; color:#1e293b;">' . esc_html('[chartivio id="' . absint($post->ID) . '"]') . '</code>';
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
function chartivio_render_main_box($post)
{
    // Retrieve Meta Data
    $manual_data = get_post_meta($post->ID, '_chartivio_manual_data', true);
    $csv_url = get_post_meta($post->ID, '_chartivio_csv_url', true);
    $active_source = get_post_meta($post->ID, '_chartivio_active_source', true) ?: 'manual';

    // Aesthetic Settings
    $chart_type = get_post_meta($post->ID, '_chartivio_type', true) ?: (get_post_meta($post->ID, '_chartivio_is_donut', true) === '1' ? 'doughnut' : 'pie');
    $legend_pos = get_post_meta($post->ID, '_chartivio_legend_pos', true) ?: 'top';
    $palette_key = get_post_meta($post->ID, '_chartivio_palette', true) ?: 'default';
    $xaxis_label = get_post_meta($post->ID, '_chartivio_xaxis_label', true);
    $yaxis_label = get_post_meta($post->ID, '_chartivio_yaxis_label', true);

    wp_nonce_field('chartivio_save_meta', 'chartivio_nonce');
    ?>
    <div class="chartivio-admin-wrapper">
        <div class="chartivio-main-header">
            <div class="chartivio-chart-type-container">
                <label for="chartivio_type">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                        <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                    </svg>
                    Chart Type
                </label>
                <select name="chartivio_type" id="chartivio_type"
                    onchange="chartivio_update_live_preview(); chartivio_toggle_axis_fields();">
                    <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie Chart</option>
                    <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut</option>
                    <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar Chart</option>
                    <option value="line" <?php selected($chart_type, 'line'); ?>>Line Chart</option>
                </select>
            </div>
            <div class="chartivio-type-selector-inline">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=chartivio&page=chartivio-how-to-use')); ?>"
                    target="_blank"
                    style="margin-right:15px; text-decoration:none; font-weight:600; font-size:13px; color:#2271b1; display:flex; align-items:center; gap:4px;">
                    <span class="dashicons dashicons-editor-help" style="font-size:18px; width:18px; height:18px;"></span>
                    How to Use
                </a>
                <button type="button" class="button button-primary" data-pid="<?php echo esc_attr($post->ID); ?>"
                    onclick="chartivio_quick_save(this)"><?php echo ($post->post_status === 'auto-draft') ? 'Save Chart' : 'Update Chart'; ?></button>
                <span id="chartivio-save-status" style="font-size:13px; font-weight:500;"></span>
            </div>
        </div>

        <div class="chartivio-split-container">
            <!-- Live Preview -->
            <div class="chartivio-preview-panel">
                <div class="chartivio-preview-header">
                    <h3>Live Preview</h3>
                    <span id="chartivio-status"></span>
                </div>
                <div class="chartivio-chart-container">
                    <canvas id="chartivio-live-chart"></canvas>
                </div>
            </div>

            <!-- Settings Tabs -->
            <div class="chartivio-settings-panel">
                <div class="chartivio-tabs">
                    <div class="chartivio-tab active" onclick="chartivio_Tab(this, 'chartivio-data')">Data Source</div>
                    <div class="chartivio-tab" onclick="chartivio_Tab(this, 'chartivio-style')">Appearance</div>
                </div>

                <div id="chartivio-data" class="chartivio-tab-content active">
                    <div class="chartivio-data-source-selector">
                        <label>Data Source</label>
                        <select name="chartivio_active_source" id="chartivio_active_source"
                            onchange="chartivio_SetSource(this.value)">
                            <option value="manual" <?php selected($active_source, 'manual'); ?>>Manual Data Entry</option>
                            <option value="csv" <?php selected($active_source, 'csv'); ?>>Import from CSV (URL)</option>
                        </select>
                    </div>

                    <!-- CSV Panel -->
                    <div class="chartivio-card chartivio-source-panel" id="chartivio-csv-panel"
                        style="<?php echo ($active_source === 'csv') ? 'display:flex;' : 'display:none;'; ?>">
                        <div class="chartivio-card-header">
                            <span>CSV Configuration</span>
                        </div>
                        <div class="chartivio-card-body"
                            style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
                            <div style="flex-shrink: 0; margin-bottom: 12px;">
                                <div style="display:flex; gap:8px; margin-bottom: 12px;">
                                    <input type="text" name="chartivio_csv_url" id="chartivio_csv_url"
                                        class="chartivio-input-text" style="flex:1;" value="<?php echo esc_url($csv_url); ?>"
                                        oninput="chartivio_update_live_preview(); chartivio_local_autosave();">
                                    <button type="button" class="button" onclick="chartivio_BrowseCSV()">Media</button>
                                </div>
                                <!-- CSV Data Preview Label and Button -->
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div id="chartivio-csv-preview-label" class="chartivio-csv-preview-label"
                                        style="font-weight: 600; color: #475569; font-size: 12px; text-transform: uppercase; display: none;">
                                        CSV Data Preview</div>
                                    <button type="button" id="chartivio-csv-view-all-btn" class="button button-small chartivio-csv-view-all-btn"
                                        onclick="chartivio_ToggleCSVViewAll()"
                                        style="display: none; font-size: 11px; padding: 4px 10px; height: auto;">
                                        View All Rows
                                    </button>
                                </div>
                            </div>
                            <!-- CSV Data Preview Table -->
                            <div id="chartivio-csv-preview-container" class="chartivio-table-wrapper chartivio-csv-preview-container" style="display: none; ">
                                <table class="chartivio-table chartivio-csv-preview-table" id="chartivio-csv-preview-table">
                                    <thead></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Panel -->
                    <div class="chartivio-card chartivio-source-panel" id="chartivio-manual-panel"
                        style="<?php echo ($active_source === 'manual') ? 'display:flex;' : 'display:none;'; ?>">
                        <div class="chartivio-card-header">
                            <span>Manual Data Table</span>
                        </div>
                        <div class="chartivio-card-body" id="chartivio-manual-body">
                            <div class="chartivio-table-wrapper">
                                <table class="chartivio-table chartivio-manual-table" id="chartivio-manual-table">
                                    <thead>
                                        <tr>
                                            <?php
                                            if (!empty($manual_data) && is_array($manual_data)) {
                                                if (isset($manual_data[0]) && is_array($manual_data[0]) && count($manual_data[0]) > 0) {
                                                    foreach ($manual_data[0] as $h) {
                                                        echo '<th><input type="text" name="chartivio_manual_data[0][]" value="' . esc_attr($h) . '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></th>';
                                                    }
                                                } else {
                                                    echo '<th><input type="text" name="chartivio_manual_data[0][]" value="' . esc_attr__('Label', 'chartivio') . '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></th>';
                                                    echo '<th><input type="text" name="chartivio_manual_data[0][]" value="' . esc_attr__('Series 1', 'chartivio') . '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></th>';
                                                }
                                            } else {
                                                echo '<th><input type="text" name="chartivio_manual_data[0][]" value="' . esc_attr__('Label', 'chartivio') . '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></th>';
                                                echo '<th><input type="text" name="chartivio_manual_data[0][]" value="' . esc_attr__('Series 1', 'chartivio') . '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></th>';
                                            }
                                            ?>
                                            <th style="width:45px !important; min-width:45px !important; max-width:45px !important; cursor:pointer !important; background: #eff6ff !important; text-align: center !important; font-size: 18px !important; font-weight: bold !important; color: var(--chartivio-primary) !important;"
                                                onclick="chartivio_add_column()" title="Add Column">+</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (!empty($manual_data) && is_array($manual_data) && count($manual_data) > 1) {
                                            foreach ($manual_data as $r => $row_data) {
                                                if ($r === 0 || !is_array($row_data))
                                                    continue;
                                                echo '<tr>';
                                                foreach ($row_data as $cell) {
                                                    echo '<td><input type="text" name="chartivio_manual_data[' . esc_attr($r) . '][]" value="' . esc_attr($cell) . '" oninput="chartivio_update_live_preview()"></td>';
                                                }
                                                echo '<td class="chartivio-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); chartivio_update_live_preview(); chartivio_local_autosave();">×</td></tr>';
                                            }
                                        } else {
                                            $col_count = (!empty($manual_data) && isset($manual_data[0]) && is_array($manual_data[0])) ? count($manual_data[0]) : 2;
                                            echo '<tr><td><input type="text" name="chartivio_manual_data[1][]" value="' . esc_attr__('Jan', 'chartivio') . '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></td>';
                                            for ($c = 1; $c < $col_count; $c++) {
                                                echo '<td><input type="text" name="chartivio_manual_data[1][]" value="10" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></td>';
                                            }
                                            echo '<td class="chartivio-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); chartivio_update_live_preview(); chartivio_local_autosave();">×</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="chartivio-table-actions">
                                <button type="button" class="button button-secondary" onclick="chartivio_add_row()">+ Add
                                    Row</button>
                                <button type="button" class="button button-secondary" onclick="chartivio_add_column()">+
                                    Add Column</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="chartivio-style" class="chartivio-tab-content">
                    <div class="chartivio-card">
                        <div class="chartivio-card-header"><span>Visual Settings</span></div>
                        <div class="chartivio-card-body">
                            <div class="chartivio-setting-row">
                                <span class="chartivio-setting-label">Legend Position</span>
                                <select name="chartivio_legend_pos" id="chartivio_legend_pos"
                                    onchange="chartivio_update_live_preview(); chartivio_local_autosave();">
                                    <option value="top" <?php selected($legend_pos, 'top'); ?>>Top</option>
                                    <option value="bottom" <?php selected($legend_pos, 'bottom'); ?>>Bottom</option>
                                    <option value="left" <?php selected($legend_pos, 'left'); ?>>Left</option>
                                    <option value="right" <?php selected($legend_pos, 'right'); ?>>Right</option>
                                    <option value="none" <?php selected($legend_pos, 'none'); ?>>None</option>
                                </select>
                            </div>
                            <div class="chartivio-setting-row">
                                <span class="chartivio-setting-label">Color Palette</span>
                                <select name="chartivio_palette" id="chartivio_palette"
                                    onchange="chartivio_update_live_preview(); chartivio_local_autosave();">
                                    <option value="default" <?php selected($palette_key, 'default'); ?>>Standard</option>
                                    <option value="pastel" <?php selected($palette_key, 'pastel'); ?>>Pastel</option>
                                    <option value="ocean" <?php selected($palette_key, 'ocean'); ?>>Ocean</option>
                                    <option value="sunset" <?php selected($palette_key, 'sunset'); ?>>Sunset</option>
                                    <option value="neon" <?php selected($palette_key, 'neon'); ?>>Neon</option>
                                    <option value="forest" <?php selected($palette_key, 'forest'); ?>>Forest</option>
                                </select>
                            </div>
                            <div class="chartivio-setting-row chartivio-xaxis-row" id="chartivio-xaxis-row">
                                <span class="chartivio-setting-label">X-Axis Title</span>
                                <input type="text" name="chartivio_xaxis_label" id="chartivio_xaxis_label"
                                    value="<?php echo esc_attr($xaxis_label); ?>" style="width: 50% !important;"
                                    oninput="chartivio_update_live_preview(); chartivio_local_autosave();">
                            </div>
                            <div class="chartivio-setting-row chartivio-yaxis-row" id="chartivio-yaxis-row">
                                <span class="chartivio-setting-label">Y-Axis Title</span>
                                <input type="text" name="chartivio_yaxis_label" id="chartivio_yaxis_label"
                                    value="<?php echo esc_attr($yaxis_label); ?>" style="width: 50% !important;"
                                    oninput="chartivio_update_live_preview(); chartivio_local_autosave();">
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <?php
}

/**
 * Sanitize manual data (recursive) to ensure stored values are safe.
 */
function chartivio_sanitize_manual_data($data)
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
function chartivio_save_meta_box_data($post_id)
{
    if (!isset($_POST['chartivio_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['chartivio_nonce'])), 'chartivio_save_meta'))
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    if (isset($_POST['chartivio_manual_data'])) {
        $manual = chartivio_sanitize_manual_data(map_deep(wp_unslash($_POST['chartivio_manual_data']), 'sanitize_text_field'));
        update_post_meta($post_id, '_chartivio_manual_data', $manual);
    }
    if (isset($_POST['chartivio_csv_url']))
        update_post_meta($post_id, '_chartivio_csv_url', esc_url_raw(wp_unslash($_POST['chartivio_csv_url'])));
    if (isset($_POST['chartivio_active_source']))
        update_post_meta($post_id, '_chartivio_active_source', sanitize_text_field(wp_unslash($_POST['chartivio_active_source'])));

    if (isset($_POST['chartivio_type']))
        update_post_meta($post_id, '_chartivio_type', sanitize_text_field(wp_unslash($_POST['chartivio_type'])));
    if (isset($_POST['chartivio_legend_pos']))
        update_post_meta($post_id, '_chartivio_legend_pos', sanitize_text_field(wp_unslash($_POST['chartivio_legend_pos'])));
    if (isset($_POST['chartivio_palette']))
        update_post_meta($post_id, '_chartivio_palette', sanitize_text_field(wp_unslash($_POST['chartivio_palette'])));

    if (isset($_POST['chartivio_xaxis_label']))
        update_post_meta($post_id, '_chartivio_xaxis_label', sanitize_text_field(wp_unslash($_POST['chartivio_xaxis_label'])));
    if (isset($_POST['chartivio_yaxis_label']))
        update_post_meta($post_id, '_chartivio_yaxis_label', sanitize_text_field(wp_unslash($_POST['chartivio_yaxis_label'])));
}
add_action('save_post', 'chartivio_save_meta_box_data');

/**
 * AJAX Save Handler
 */
function chartivio_ajax_save_chart()
{
    check_ajax_referer('chartivio_save_meta', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error();
    }

    // Save Manual Data
    $manual_data = array();
    if (isset($_POST['manual_json'])) {
        $json = json_decode(sanitize_text_field(wp_unslash($_POST['manual_json'])), true);
        if (!empty($json['headers'])) {
            $manual_data[] = array_map('sanitize_text_field', $json['headers']);
            foreach ($json['rows'] as $row) {
                $manual_data[] = array_map('sanitize_text_field', $row);
            }
        }
    }
    update_post_meta($post_id, '_chartivio_manual_data', $manual_data);

    // Save Settings
    if (isset($_POST['chartivio_csv_url']))
        update_post_meta($post_id, '_chartivio_csv_url', esc_url_raw(wp_unslash($_POST['chartivio_csv_url'])));
    if (isset($_POST['chartivio_active_source']))
        update_post_meta($post_id, '_chartivio_active_source', sanitize_text_field(wp_unslash($_POST['chartivio_active_source'])));
    if (isset($_POST['chartivio_type']))
        update_post_meta($post_id, '_chartivio_type', sanitize_text_field(wp_unslash($_POST['chartivio_type'])));
    if (isset($_POST['chartivio_legend_pos']))
        update_post_meta($post_id, '_chartivio_legend_pos', sanitize_text_field(wp_unslash($_POST['chartivio_legend_pos'])));
    if (isset($_POST['chartivio_palette']))
        update_post_meta($post_id, '_chartivio_palette', sanitize_text_field(wp_unslash($_POST['chartivio_palette'])));
    if (isset($_POST['chartivio_xaxis_label']))
        update_post_meta($post_id, '_chartivio_xaxis_label', sanitize_text_field(wp_unslash($_POST['chartivio_xaxis_label'])));
    if (isset($_POST['chartivio_yaxis_label']))
        update_post_meta($post_id, '_chartivio_yaxis_label', sanitize_text_field(wp_unslash($_POST['chartivio_yaxis_label'])));

    // Update Title and ensure post is published
    $post = get_post($post_id);
    $update_args = array(
        'ID' => $post_id,
        'post_status' => 'publish'  // Always publish the post when saving via AJAX
    );
    if (isset($_POST['post_title'])) {
        $update_args['post_title'] = sanitize_text_field(wp_unslash($_POST['post_title']));
    }
    wp_update_post($update_args);

    // Generate Shortcode HTML
    $shortcode_html = '<div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">';
    $shortcode_html .= '<p style="margin-top:0; font-size:13px; color:#64748b;">Copy this shortcode to display the chart:</p>';
    $shortcode_html .= '<code style="display:block; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; font-weight:bold; color:#1e293b;">' . esc_html('[chartivio id="' . absint($post_id) . '"]') . '</code>';
    $shortcode_html .= '</div>';

    wp_send_json_success(array('message' => 'Saved', 'shortcode_html' => $shortcode_html));
}
add_action('wp_ajax_chartivio_save_chart', 'chartivio_ajax_save_chart');


