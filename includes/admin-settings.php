<?php
/**
 * Professional Split-Screen Admin UI for DearCharts
 */

if (!defined('ABSPATH')) {
    exit;
}

// Allow CSV uploads
add_filter('upload_mimes', function ($mimes) {
    if (!isset($mimes['csv']))
        $mimes['csv'] = 'text/csv';
    return $mimes;
});

/**
 * Register Meta Boxes
 */
add_action('add_meta_boxes', function () {
    add_meta_box('dearcharts_main_box', 'Chart Configuration', 'dearcharts_render_main_box', 'dearcharts', 'normal', 'high');
    add_meta_box('dearcharts_usage_box', 'Chart Shortcodes', 'dearcharts_render_usage_box', 'dearcharts', 'side', 'low');
});

/**
 * Enqueue Admin Assets
 */
add_action('admin_enqueue_scripts', function ($hook) {
    global $post;
    if (($hook == 'post-new.php' || $hook == 'post.php') && $post && $post->post_type === 'dearcharts') {
        wp_enqueue_media();
        wp_enqueue_script('chartjs', plugins_url('../assets/js/chartjs/chart.umd.min.js', __FILE__), array(), '4.4.1', true);
    }
});

/**
 * Sidebar Meta Box
 */
function dearcharts_render_usage_box($post)
{
    echo '<div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">';
    if (isset($post->post_status) && $post->post_status === 'publish') {
        echo '<p style="margin:0 0 8px 0; font-size:13px; color:#64748b;">Shortcode:</p>';
        echo '<code style="display:block; padding:8px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; font-weight:bold; color:#1e293b;">[dearchart id="' . absint($post->ID) . '"]</code>';
    } else {
        echo '<p style="margin:0; font-size:13px; color:#64748b;">Publish to generate shortcode.</p>';
    }
    echo '</div>';
}

/**
 * Main Meta Box
 */
function dearcharts_render_main_box($post)
{
    $manual_data = get_post_meta($post->ID, '_dearcharts_manual_data', true);
    $csv_url = get_post_meta($post->ID, '_dearcharts_csv_url', true);
    $active_source = get_post_meta($post->ID, '_dearcharts_active_source', true) ?: 'manual';
    $chart_type = get_post_meta($post->ID, '_dearcharts_type', true) ?: 'pie';
    $legend_pos = get_post_meta($post->ID, '_dearcharts_legend_pos', true) ?: 'top';
    $palette_key = get_post_meta($post->ID, '_dearcharts_palette', true) ?: 'default';
    $xaxis_label = get_post_meta($post->ID, '_dearcharts_xaxis_label', true);
    $yaxis_label = get_post_meta($post->ID, '_dearcharts_yaxis_label', true);

    wp_nonce_field('dearcharts_save_meta', 'dearcharts_nonce');
    ?>
    <style>
        :root {
            --dc-pri: #3b82f6;
            --dc-brd: #e2e8f0;
            --dc-bg: #f8fafc;
            --dc-txt: #1e293b;
            --dc-mut: #64748b;
        }

        .dc-wrap {
            margin: -12px;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--dc-brd);
        }

        .dc-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid var(--dc-brd);
            background: #fff;
        }

        .dc-head select {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .dc-main {
            display: flex;
            height: 650px;
            background: #fff;
        }

        .dc-prev {
            flex: 0 0 520px;
            padding: 25px;
            background: var(--dc-bg);
            border-right: 1px solid var(--dc-brd);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .dc-chart-box {
            width: 100%;
            height: 500px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .dc-sets {
            flex: 1;
            padding: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .dc-tabs {
            display: flex;
            background: #fff;
            border-bottom: 1px solid var(--dc-brd);
        }

        .dc-tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 600;
            color: var(--dc-mut);
            border-bottom: 2px solid transparent;
            transition: 0.2s;
        }

        .dc-tab.active {
            color: var(--dc-pri);
            border-bottom-color: var(--dc-pri);
            background: #eff6ff;
        }

        .dc-content {
            display: none;
            padding: 20px;
            flex: 1;
            overflow-y: auto;
        }

        .dc-content.active {
            display: block;
        }

        .dc-card {
            border: 1px solid var(--dc-brd);
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .dc-card-h {
            background: #f1f5f9;
            padding: 10px 15px;
            border-bottom: 1px solid var(--dc-brd);
            font-weight: 600;
            color: #475569;
            font-size: 13px;
        }

        .dc-card-b {
            padding: 15px;
        }

        .dc-table-wrap {
            overflow: auto;
            width: 100%;
            max-height: 400px;
            border: 1px solid var(--dc-brd);
            border-radius: 4px;
        }

        table.dc-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 400px;
        }

        table.dc-table th,
        table.dc-table td {
            border: 1px solid var(--dc-brd);
            padding: 6px;
            text-align: left;
        }

        table.dc-table th {
            background: #f8fafc;
            font-size: 11px;
            text-transform: uppercase;
        }

        table.dc-table input {
            width: 100%;
            border: 1px solid #cbd5e1;
            padding: 6px;
            border-radius: 4px;
            font-size: 13px;
        }

        .dc-del-r {
            color: #ef4444;
            cursor: pointer;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            width: 30px;
        }

        .dc-del-c {
            position: absolute;
            right: 2px;
            top: 50%;
            transform: translateY(-50%);
            color: #ef4444;
            cursor: pointer;
            font-size: 14px;
            background: #fff;
            padding: 0 4px;
        }

        .dc-row-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .dc-setting {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        #dc-status {
            font-size: 11px;
            margin-top: 10px;
            padding: 4px 8px;
            border-radius: 4px;
            display: none;
        }

        #submitdiv {
            display: none;
        }

        .dc-col-wrap {
            position: relative;
            display: block;
            padding-right: 15px;
        }

        @media (max-width: 1100px) {
            .dc-main {
                flex-direction: column;
                height: auto;
            }

            .dc-prev {
                flex: none;
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--dc-brd);
            }
        }
    </style>

    <div class="dc-wrap">
        <div class="dc-head">
            <div style="display:flex; align-items:center; gap:10px;">
                <label style="font-weight:600; color:var(--dc-mut);">Chart Type</label>
                <select name="dearcharts_type" id="dearcharts_type" onchange="dcUpdatePreview()">
                    <option value="pie" <?php selected($chart_type, 'pie'); ?>>Pie Chart</option>
                    <option value="doughnut" <?php selected($chart_type, 'doughnut'); ?>>Doughnut</option>
                    <option value="bar" <?php selected($chart_type, 'bar'); ?>>Bar Chart</option>
                    <option value="line" <?php selected($chart_type, 'line'); ?>>Line Chart</option>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span id="dc-save-status" style="font-size:12px; font-weight:600;"></span>
                <button type="button" class="button button-primary" onclick="dcQuickSave(this)"
                    data-pid="<?php echo $post->ID; ?>">Save Chart</button>
            </div>
        </div>

        <div class="dc-main">
            <div class="dc-prev">
                <h3 style="margin:0 0 15px 0; font-size:16px;">Live Preview</h3>
                <div class="dc-chart-box"><canvas id="dc-canvas"></canvas></div>
                <div id="dc-status"></div>
            </div>

            <div class="dc-sets">
                <div class="dc-tabs">
                    <div class="dc-tab active" onclick="dcTab(this, 'dc-tab-data')">Data</div>
                    <div class="dc-tab" onclick="dcTab(this, 'dc-tab-style')">Appearance</div>
                </div>

                <div id="dc-tab-data" class="dc-content active">
                    <div class="dc-setting" style="margin-bottom:15px; border:none;">
                        <label style="font-weight:600;">Data Source</label>
                        <select id="dc-source" onchange="dcSetSource(this.value)">
                            <option value="manual" <?php selected($active_source, 'manual'); ?>>Manual Entry</option>
                            <option value="csv" <?php selected($active_source, 'csv'); ?>>CSV URL</option>
                        </select>
                    </div>

                    <div id="dc-panel-csv" style="display:<?php echo $active_source == 'csv' ? 'block' : 'none'; ?>">
                        <div class="dc-card">
                            <div class="dc-card-h">CSV Configuration</div>
                            <div class="dc-card-b">
                                <div style="display:flex; gap:8px;">
                                    <input type="text" id="dc-csv-url" value="<?php echo esc_url($csv_url); ?>"
                                        style="flex:1;" oninput="dcUpdatePreview()">
                                    <button type="button" class="button" onclick="dcBrowseCSV()">Media</button>
                                </div>
                                <div id="dc-csv-preview" style="margin-top:15px; display:none;">
                                    <div style="font-size:11px; font-weight:600; color:var(--dc-mut); margin-bottom:5px;">
                                        PREVIEW (10 ROWS)</div>
                                    <div class="dc-table-wrap">
                                        <table class="dc-table" id="dc-table-csv">
                                            <thead></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="dc-panel-manual" style="display:<?php echo $active_source == 'manual' ? 'block' : 'none'; ?>">
                        <div class="dc-card">
                            <div class="dc-card-h">Data Table</div>
                            <div class="dc-card-b">
                                <div class="dc-table-wrap">
                                    <table class="dc-table" id="dc-manual-table">
                                        <thead>
                                            <tr>
                                                <?php
                                                $cols = (!empty($manual_data) && is_array($manual_data[0])) ? $manual_data[0] : ['Label', 'Series 1'];
                                                foreach ($cols as $h)
                                                    echo '<th><span class="dc-col-wrap"><input type="text" value="' . esc_attr($h) . '" oninput="dcUpdatePreview()"></span></th>';
                                                ?>
                                                <th style="width:40px; text-align:center; cursor:pointer; color:var(--dc-pri);"
                                                    onclick="dcAddCol()">+</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (!empty($manual_data) && count($manual_data) > 1) {
                                                for ($r = 1; $r < count($manual_data); $r++) {
                                                    echo '<tr>';
                                                    foreach ($manual_data[$r] as $v)
                                                        echo '<td><input type="text" value="' . esc_attr($v) . '" oninput="dcUpdatePreview()"></td>';
                                                    echo '<td class="dc-del-r" onclick="jQuery(this).closest(\'tr\').remove(); dcUpdatePreview();">×</td></tr>';
                                                }
                                            } else {
                                                echo '<tr><td><input type="text" value="Jan" oninput="dcUpdatePreview()"></td><td><input type="text" value="10" oninput="dcUpdatePreview()"></td><td class="dc-del-r" onclick="jQuery(this).closest(\'tr\').remove(); dcUpdatePreview();">×</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="dc-row-actions">
                                    <button type="button" class="button" onclick="dcAddRow()">+ Add Row</button>
                                    <button type="button" class="button" onclick="dcTranspose()">Transpose</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="dc-tab-style" class="dc-content">
                    <div class="dc-card">
                        <div class="dc-card-h">Style Settings</div>
                        <div class="dc-card-b">
                            <div class="dc-setting"><span>Legend Position</span><select id="dc-legend"
                                    onchange="dcUpdatePreview()">
                                    <option value="top" <?php selected($legend_pos, 'top'); ?>>Top</option>
                                    <option value="bottom" <?php selected($legend_pos, 'bottom'); ?>>Bottom</option>
                                    <option value="left" <?php selected($legend_pos, 'left'); ?>>Left</option>
                                    <option value="right" <?php selected($legend_pos, 'right'); ?>>Right</option>
                                    <option value="none" <?php selected($legend_pos, 'none'); ?>>None</option>
                                </select></div>
                            <div class="dc-setting"><span>Palette</span><select id="dc-palette"
                                    onchange="dcUpdatePreview()">
                                    <option value="default" <?php selected($palette_key, 'default'); ?>>Standard</option>
                                    <option value="pastel" <?php selected($palette_key, 'pastel'); ?>>Pastel</option>
                                    <option value="ocean" <?php selected($palette_key, 'ocean'); ?>>Ocean</option>
                                    <option value="sunset" <?php selected($palette_key, 'sunset'); ?>>Sunset</option>
                                    <option value="neon" <?php selected($palette_key, 'neon'); ?>>Neon</option>
                                    <option value="forest" <?php selected($palette_key, 'forest'); ?>>Forest</option>
                                </select></div>
                            <div class="dc-setting"><span>X-Axis Title</span><input type="text" id="dc-xaxis"
                                    value="<?php echo esc_attr($xaxis_label); ?>" oninput="dcUpdatePreview()"
                                    style="width:180px;"></div>
                            <div class="dc-setting"><span>Y-Axis Title</span><input type="text" id="dc-yaxis"
                                    value="<?php echo esc_attr($yaxis_label); ?>" oninput="dcUpdatePreview()"
                                    style="width:180px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var dcChart = null, dcPalettes = { 'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'], 'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'], 'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'], 'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'], 'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'], 'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F'] };

        function dcTab(el, id) { jQuery('.dc-tab').removeClass('active'); jQuery('.dc-content').removeClass('active'); jQuery(el).addClass('active'); jQuery('#' + id).addClass('active'); }
        function dcSetSource(v) { jQuery('#dc-panel-manual, #dc-panel-csv').hide(); jQuery('#dc-panel-' + v).show(); dcUpdatePreview(); }
        function dcBrowseCSV() { var m = wp.media({ title: 'Select CSV', multiple: false }).open().on('select', function () { jQuery('#dc-csv-url').val(m.state().get('selection').first().toJSON().url); dcUpdatePreview(); }); }

        function dcAddRow() {
            var cols = jQuery('#dc-manual-table thead th').length - 1;
            var html = '<tr>' + '<td><input type="text" oninput="dcUpdatePreview()"></td>'.repeat(cols) + '<td class="dc-del-r" onclick="jQuery(this).closest(\'tr\').remove(); dcUpdatePreview();">×</td></tr>';
            jQuery('#dc-manual-table tbody').append(html); dcUpdatePreview();
        }

        function dcAddCol() {
            jQuery('<th><span class="dc-col-wrap"><input type="text" value="Series" oninput="dcUpdatePreview()"></span></th>').insertBefore('#dc-manual-table thead th:last');
            jQuery('#dc-manual-table tbody tr').each(function () { jQuery('<td><input type="text" oninput="dcUpdatePreview()"></td>').insertBefore(jQuery(this).find('td:last')); });
            dcRefreshColDel(); dcUpdatePreview();
        }

        function dcRefreshColDel() {
            jQuery('.dc-del-c').remove();
            jQuery('#dc-manual-table thead th').each(function (i) {
                if (i > 0 && i < jQuery('#dc-manual-table thead th').length - 1) {
                    jQuery(this).find('.dc-col-wrap').append('<span class="dc-del-c" onclick="dcDelCol(' + i + ')">×</span>');
                }
            });
        }

        function dcDelCol(idx) {
            jQuery('#dc-manual-table thead th').eq(idx).remove();
            jQuery('#dc-manual-table tbody tr').each(function () { jQuery(this).find('td').eq(idx).remove(); });
            dcRefreshColDel(); dcUpdatePreview();
        }

        function dcTranspose() {
            var data = [];
            var headers = [];
            jQuery('#dc-manual-table thead th').each(function (i) { if (i < jQuery('#dc-manual-table thead th').length - 1) headers.push(jQuery(this).find('input').val()); });
            data.push(headers);
            jQuery('#dc-manual-table tbody tr').each(function () {
                var row = [];
                jQuery(this).find('td').each(function (i) { if (i < jQuery(this).closest('tr').find('td').length - 1) row.push(jQuery(this).find('input').val()); });
                data.push(row);
            });
            var trans = data[0].map((_, c) => data.map(r => r[c] || ''));
            jQuery('#dc-manual-table thead tr').html('<th style="width:40px; text-align:center; cursor:pointer; color:var(--dc-pri);" onclick="dcAddCol()">+</th>');
            trans[0].forEach(h => jQuery('<th><span class="dc-col-wrap"><input type="text" value="' + h + '" oninput="dcUpdatePreview()"></span></th>').insertBefore('#dc-manual-table thead th:last'));
            jQuery('#dc-manual-table tbody').html('');
            trans.slice(1).forEach(r => {
                var html = '<tr>' + r.map(v => '<td><input type="text" value="' + v + '" oninput="dcUpdatePreview()"></td>').join('') + '<td class="dc-del-r" onclick="jQuery(this).closest(\'tr\').remove(); dcUpdatePreview();">×</td></tr>';
                jQuery('#dc-manual-table tbody').append(html);
            });
            dcRefreshColDel(); dcUpdatePreview();
        }

        async function dcUpdatePreview() {
            if (typeof Chart === 'undefined') return;
            var canvas = document.getElementById('dc-canvas');
            if (!canvas) return;
            if (dcChart) dcChart.destroy();

            var src = jQuery('#dc-source').val(), type = jQuery('#dc-type').val(), pal = dcPalettes[jQuery('#dc-palette').val()] || dcPalettes.default;
            var labels = [], datasets = [];

            if (src === 'csv') {
                var url = jQuery('#dc-csv-url').val();
                if (!url) return;
                try {
                    const res = await fetch(url);
                    const text = await res.text();
                    const rows = text.trim().split('\n').map(r => r.split(','));
                    const heads = rows[0];
                    for (var i = 1; i < heads.length; i++) datasets.push({ label: heads[i].trim(), data: [] });
                    rows.slice(1).forEach(r => {
                        labels.push(r[0].trim());
                        for (var i = 1; i < r.length; i++) datasets[i - 1].data.push(parseFloat(r[i]) || 0);
                    });
                    jQuery('#dc-csv-preview').show();
                    jQuery('#dc-table-csv thead').html('<tr>' + heads.map(h => '<th>' + h + '</th>').join('') + '</tr>');
                    jQuery('#dc-table-csv tbody').html(rows.slice(1, 11).map(r => '<tr>' + r.map(v => '<td>' + v + '</td>').join('') + '</tr>').join(''));
                } catch (e) { console.error(e); }
            } else {
                jQuery('#dc-manual-table thead th').each(function (i) { if (i > 0 && i < jQuery('#dc-manual-table thead th').length - 1) datasets.push({ label: jQuery(this).find('input').val(), data: [] }); });
                jQuery('#dc-manual-table tbody tr').each(function () {
                    labels.push(jQuery(this).find('td:first input').val());
                    jQuery(this).find('td').each(function (i) { if (i > 0 && i < datasets.length + 1) datasets[i - 1].data.push(parseFloat(jQuery(this).find('input').val()) || 0); });
                });
            }

            datasets.forEach((ds, i) => {
                const colors = labels.map((_, j) => pal[j % pal.length]);
                const single = pal[i % pal.length];
                if (type === 'pie' || type === 'doughnut') { ds.backgroundColor = colors; ds.borderColor = '#fff'; ds.borderWidth = 2; }
                else { ds.backgroundColor = datasets.length > 1 ? single : colors; ds.borderColor = datasets.length > 1 ? single : colors; ds.borderWidth = 1; }
            });

            dcChart = new Chart(canvas, {
                type: type,
                data: { labels: labels, datasets: datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: (type === 'bar' || type === 'line') ? { y: { beginAtZero: true, title: { display: !!jQuery('#dc-yaxis').val(), text: jQuery('#dc-yaxis').val() } }, x: { title: { display: !!jQuery('#dc-xaxis').val(), text: jQuery('#dc-xaxis').val() } } } : {},
                    plugins: { legend: { display: jQuery('#dc-legend').val() !== 'none', position: jQuery('#dc-legend').val() } }
                }
            });
        }

        function dcQuickSave(btn) {
            var $btn = jQuery(btn), post_id = $btn.data('pid'), title = jQuery('#title').val();
            if (!title) { jQuery('#title').css('border', '1px solid red').focus(); return; }
            $btn.text('Saving...').prop('disabled', true);

            var manual = [];
            var h = []; jQuery('#dc-manual-table thead th input').each(function () { h.push(jQuery(this).val()); }); manual.push(h);
            jQuery('#dc-manual-table tbody tr').each(function () { var r = []; jQuery(this).find('td input').each(function () { r.push(jQuery(this).val()); }); if (r.length) manual.push(r); });

            jQuery.post(ajaxurl, {
                action: 'dearcharts_save_chart',
                nonce: jQuery('#dearcharts_nonce').val(),
                post_id: post_id,
                post_title: title,
                manual_json: JSON.stringify({ headers: manual[0], rows: manual.slice(1) }),
                dearcharts_csv_url: jQuery('#dc-csv-url').val(),
                dearcharts_active_source: jQuery('#dc-source').val(),
                dearcharts_type: jQuery('#dearcharts_type').val(),
                dearcharts_legend_pos: jQuery('#dc-legend').val(),
                dearcharts_palette: jQuery('#dc-palette').val(),
                dearcharts_xaxis_label: jQuery('#dc-xaxis').val(),
                dearcharts_yaxis_label: jQuery('#dc-yaxis').val()
            }, function (res) {
                $btn.text('Save Chart').prop('disabled', false);
                if (res.success) { jQuery('#dc-save-status').text('Saved!').css('color', '#10b981').show().delay(2000).fadeOut(); if (res.data.shortcode_html) jQuery('#dearcharts_usage_box .inside').html(res.data.shortcode_html); }
            });
        }

        jQuery(document).ready(function () { setTimeout(dcRefreshColDel, 500); setTimeout(dcUpdatePreview, 1000); });
    </script>
    <?php
}

/**
 * Save Meta Data
 */
add_action('save_post', function ($post_id) {
    if (!isset($_POST['dearcharts_nonce']) || !wp_verify_nonce($_POST['dearcharts_nonce'], 'dearcharts_save_meta'))
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;

    $fields = ['_dearcharts_manual_data', '_dearcharts_csv_url', '_dearcharts_active_source', '_dearcharts_type', '_dearcharts_legend_pos', '_dearcharts_palette', '_dearcharts_xaxis_label', '_dearcharts_yaxis_label'];
    foreach ($fields as $f) {
        $key = str_replace('_dearcharts_', 'dearcharts_', $f);
        if (isset($_POST[$key]))
            update_post_meta($post_id, $f, is_array($_POST[$key]) ? $_POST[$key] : sanitize_text_field($_POST[$key]));
    }
});

/**
 * AJAX Save
 */
add_action('wp_ajax_dearcharts_save_chart', function () {
    check_ajax_referer('dearcharts_save_meta', 'nonce');
    $pid = intval($_POST['post_id']);
    if (!current_user_can('edit_post', $pid))
        wp_send_json_error();

    $json = json_decode(stripslashes($_POST['manual_json']), true);
    $manual = [$json['headers']];
    if (!empty($json['rows']))
        foreach ($json['rows'] as $r)
            $manual[] = $r;
    update_post_meta($pid, '_dearcharts_manual_data', $manual);

    $meta = ['dearcharts_csv_url' => '_dearcharts_csv_url', 'dearcharts_active_source' => '_dearcharts_active_source', 'dearcharts_type' => '_dearcharts_type', 'dearcharts_legend_pos' => '_dearcharts_legend_pos', 'dearcharts_palette' => '_dearcharts_palette', 'dearcharts_xaxis_label' => '_dearcharts_xaxis_label', 'dearcharts_yaxis_label' => '_dearcharts_yaxis_label'];
    foreach ($meta as $post_k => $meta_k)
        if (isset($_POST[$post_k]))
            update_post_meta($pid, $meta_k, sanitize_text_field($_POST[$post_k]));

    wp_update_post(['ID' => $pid, 'post_title' => sanitize_text_field($_POST['post_title']), 'post_status' => 'publish']);

    ob_start();
    dearcharts_render_usage_box(get_post($pid));
    $html = ob_get_clean();
    wp_send_json_success(['shortcode_html' => $html]);
});
