var chartivio_LiveChart = null;
var chartivio_post_id = chartivio_admin_vars.post_id;
var chartivio_admin_nonce = chartivio_admin_vars.nonce;
var chartivio_current_csv_data = null; // Store parsed CSV data for snapshot comparison
var chartivio_is_updating_preview = false; // Flag to prevent concurrent updates

/**
 * Toggle X-Axis and Y-Axis title fields visibility based on chart type
 * Pie and Doughnut charts don't have axes, so hide the fields for those types
 */
function chartivio_toggle_axis_fields() {
    var chartType = jQuery('#chartivio_type').val() || 'pie';
    var showAxisFields = (chartType === 'bar' || chartType === 'line');

    if (showAxisFields) {
        jQuery('.chartivio-xaxis-row').show();
        jQuery('.chartivio-yaxis-row').show();
    } else {
        jQuery('.chartivio-xaxis-row').hide();
        jQuery('.chartivio-yaxis-row').hide();
    }
}

// Initialize axis field visibility on page load
jQuery(document).ready(function () {
    chartivio_toggle_axis_fields();
});

// Normalized snapshot of saved post meta for client-side comparisons
var chartivio_saved_snapshot = chartivio_admin_vars.saved_snapshot;
var chartivio_palettes = { 'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'], 'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'], 'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'], 'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'], 'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'], 'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F'] };

function chartivio_parse_csv(str) {
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

function chartivio_Tab(el, id) { jQuery('.chartivio-tab').removeClass('active'); jQuery('.chartivio-tab-content').removeClass('active'); jQuery(el).addClass('active'); jQuery('#' + id).addClass('active'); }
function chartivio_SetSource(src) {
    jQuery('#chartivio_active_source').val(src);
    jQuery('.chartivio-source-panel').hide();
    jQuery('.chartivio-' + src + '-panel').show();
    if (src === 'manual') {
        setTimeout(function () {
            chartivio_add_delete_col_controls();
        }, 100);
    }
    chartivio_update_live_preview();
    chartivio_local_autosave();
}
function chartivio_BrowseCSV() { var media = wp.media({ title: 'Select CSV', multiple: false }).open().on('select', function () { var url = media.state().get('selection').first().toJSON().url; jQuery('#chartivio_csv_url').val(url); chartivio_update_live_preview(); chartivio_local_autosave(); }); }
function chartivio_ToggleCSVViewAll() {
    var $btn = jQuery('.chartivio-csv-view-all-btn');
    var $table = jQuery('.chartivio-csv-preview-table');
    var $container = jQuery('.chartivio-csv-preview-container');
    var $label = jQuery('.chartivio-csv-preview-label');
    var isExpanded = $btn.data('expanded');
    var fullRows = $table.data('fullCSVRows');

    if (!fullRows || fullRows.length < 2) return;

    var totalDataRows = fullRows.length - 1;
    var heads = fullRows[0];

    if (isExpanded) {
        // Collapse: Show only first 10 rows
        var bodyHtml = '';
        fullRows.slice(1, 11).forEach(row => {
            bodyHtml += '<tr>';
            row.forEach(cell => bodyHtml += '<td>' + (cell || '').trim() + '</td>');
            bodyHtml += '</tr>';
        });
        $table.find('tbody').html(bodyHtml);
        $container.css('max-height', '250px');
        $btn.text('View All Rows (' + totalDataRows + ')').data('expanded', false);
        $label.text('CSV Data Preview (Showing 10 of ' + totalDataRows + ' rows)');
    } else {
        // Expand: Show all rows
        var bodyHtml = '';
        fullRows.slice(1).forEach(row => {
            bodyHtml += '<tr>';
            row.forEach(cell => bodyHtml += '<td>' + (cell || '').trim() + '</td>');
            bodyHtml += '</tr>';
        });
        $table.find('tbody').html(bodyHtml);
        $container.css('max-height', '400px');
        $btn.text('Show Less').data('expanded', true);
        $label.text('CSV Data Preview (Showing all ' + totalDataRows + ' rows)');
    }
}
function chartivio_add_row() {
    var colCount = jQuery('.chartivio-manual-table thead th').length - 1;
    var rowKey = Date.now();
    var html = '<tr>';
    for (var i = 0; i < colCount; i++) {
        html += '<td><input type="text" name="chartivio_manual_data[' + rowKey + '][]" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></td>';
    }
    html += '<td class="chartivio-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); chartivio_update_live_preview(); chartivio_local_autosave();">×</td></tr>';

    var $newRow = jQuery(html);
    jQuery('.chartivio-manual-table tbody').append($newRow);

    chartivio_update_live_preview();
    chartivio_local_autosave();

    var $wrapper = jQuery('.chartivio-table-wrapper');
    $wrapper.animate({ scrollTop: $wrapper.prop("scrollHeight") }, 500);

    // Smart Focus: Auto-focus the first field of the new row after scroll
    setTimeout(function () {
        $newRow.find('input:first').focus();
    }, 50);
}
/**
 * Transpose Table Data (Swap Rows and Columns)
 */
function chartivio_transpose_table() {
    if (!confirm('Transpose data (swap rows and columns)? This will overwrite the current table arrangement.')) return;
    var snap = chartivio_get_snapshot();
    var oldH = snap.manual.headers;
    var oldR = snap.manual.rows;

    // Build matrix: [Headers, Row1, Row2...]
    var matrix = [oldH].concat(oldR);

    // Transpose matrix
    var newMatrix = matrix[0].map((_, colIndex) => matrix.map(row => row[colIndex] || ''));

    // Clear table
    jQuery('.chartivio-manual-table thead tr').html('<th style="width:40px; cursor:pointer;" onclick="chartivio_add_column()">+</th>');
    jQuery('.chartivio-manual-table tbody').html('');

    // Rebuild Headers
    newMatrix[0].forEach(h => jQuery('<th><input type="text" name="chartivio_manual_data[0][]" value="' + (h || '').replace(/"/g, '&quot;') + '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></th>').insertBefore(jQuery('.chartivio-manual-table thead th').last()));

    // Rebuild Rows
    newMatrix.slice(1).forEach((row, idx) => {
        var html = '<tr>';
        row.forEach(cell => html += '<td><input type="text" name="chartivio_manual_data[' + (Date.now() + idx) + '][]" value="' + (cell || '').replace(/"/g, '&quot;') + '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></td>');
        html += '<td class="chartivio-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); chartivio_update_live_preview(); chartivio_local_autosave();">×</td></tr>';
        jQuery('.chartivio-manual-table tbody').append(html);
    });

    chartivio_add_delete_col_controls();
    chartivio_update_live_preview();
    chartivio_local_autosave();
}

/**
 * PSEUDOCODE: Add Column
 * 1. Determine new column index.
 * 2. Insert as a new header input (for the series label).
 * 3. Iterate through all rows and append a new data input cell for that series.
 * 4. Auto-scroll the table to the right to focus on the new column.
 */
function chartivio_add_column() {
    var colIdx = jQuery('.chartivio-manual-table thead th').length - 1;
    var headHtml = '<th><input type="text" name="chartivio_manual_data[0][]" value="Series ' + colIdx + '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></th>';
    var $newTh = jQuery(headHtml);
    $newTh.insertBefore(jQuery('.chartivio-manual-table thead th').last());

    jQuery('.chartivio-manual-table tbody tr').each(function () {
        var rowKeyMatch = jQuery(this).find('td:first input').attr('name').match(/\[(.*?)\]/);
        var rowKey = rowKeyMatch ? rowKeyMatch[1] : Date.now();
        var cellHtml = '<td><input type="text" name="chartivio_manual_data[' + rowKey + '][]" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></td>';
        jQuery(cellHtml).insertBefore(jQuery(this).find('td').last());
    });

    // add delete controls for columns and update preview
    chartivio_add_delete_col_controls();
    chartivio_update_live_preview();

    var $wrapper = jQuery('.chartivio-table-wrapper');
    $wrapper.animate({ scrollLeft: $wrapper.prop("scrollWidth") }, 500);

    // Smart Focus: Auto-focus and select text in the new column header for easy renaming
    setTimeout(function () {
        $newTh.find('input').focus().select();
    }, 50);
}

function chartivio_add_delete_col_controls() {
    var $ths = jQuery('.chartivio-manual-table thead th');
    var lastIdx = $ths.length - 1;
    $ths.each(function (i) {
        if (i > 0 && i < lastIdx) {
            var $th = jQuery(this);
            var $input = $th.find('input');
            // wrap input in .chartivio-col-control if not already
            if ($input.length) {
                if (!$input.parent().hasClass('chartivio-col-control')) {
                    $input.wrap('<span class="chartivio-col-control"></span>');
                }
                var $control = $input.parent();
                // ensure delete icon exists inside the control, immediately after input
                if ($control.find('.chartivio-delete-col').length === 0) {
                    $control.append('<button type="button" class="chartivio-delete-col" data-col-idx="' + i + '" aria-label="Delete column" title="Delete column">×</button>');
                } else {
                    $control.find('.chartivio-delete-col').attr('data-col-idx', i);
                }
            } else {
                // fallback: append to th
                if ($th.find('.chartivio-delete-col').length === 0) {
                    $th.append('<button type="button" class="chartivio-delete-col" data-col-idx="' + i + '" aria-label="Delete column" title="Delete column">×</button>');
                } else {
                    $th.find('.chartivio-delete-col').attr('data-col-idx', i);
                }
            }
        } else {
            // remove delete controls from non-deletable headers
            jQuery(this).find('.chartivio-delete-col').remove();
            // unwrap.chartivio-col-control if it exists and has only the input
            var $wrap = jQuery(this).find('.chartivio-col-control');
            if ($wrap.length && $wrap.find('input').length && $wrap.find('.chartivio-delete-col').length === 0) {
                $wrap.replaceWith($wrap.find('input'));
            }
        }
    });
    // delegated handlers for click and keyboard
    jQuery('.chartivio-manual-table').off('click', '.chartivio-delete-col').on('click', '.chartivio-delete-col', function () {
        var idx = parseInt(jQuery(this).attr('data-col-idx'), 10);
        chartivio_delete_column(idx);
    });
    jQuery('.chartivio-manual-table').off('keydown', '.chartivio-delete-col').on('keydown', '.chartivio-delete-col', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); var idx = parseInt(jQuery(this).attr('data-col-idx'), 10); chartivio_delete_column(idx); }
    });
}
function chartivio_delete_column(idx) {
    var $ths = jQuery('.chartivio-manual-table thead th');
    var lastIdx = $ths.length - 1;
    if (idx <= 0 || idx >= lastIdx) return; // don't delete label column or the add button
    jQuery('.chartivio-manual-table thead th').eq(idx).remove();
    jQuery('.chartivio-manual-table tbody tr').each(function () {
        jQuery(this).find('td').eq(idx).remove();
    });
    // refresh controls and preview
    chartivio_add_delete_col_controls();
    chartivio_update_live_preview();
}
// ensure controls are present on DOM ready and after table is rendered
jQuery(function () {
    // Initial call
    setTimeout(function () {
        chartivio_add_delete_col_controls();
    }, 200);

    // Also call when manual source is active
    if (jQuery('#chartivio_active_source').val() === 'manual') {
        setTimeout(function () {
            chartivio_add_delete_col_controls();
        }, 500);
    }
});

/**
 * PSEUDOCODE: Update Live Preview
 * 1. Safely clear the previous chart instance.
 * 2. Read the source (CSV vs Manual).
 * 3. If CSV: Fetch URL, parse lines, and map to Chart.js datasets.
 * 4. If Manual: Scrape table TBODY for data and THEAD for labels.
 * 5. Apply selected color palette from the chartivio_palettes dictionary.
 * 6. Re-draw the Chart using the Chart.js API.
 */
async function chartivio_update_live_preview() {
    // Prevent concurrent calls
    if (chartivio_is_updating_preview) {
        return;
    }
    chartivio_is_updating_preview = true;

    try {
        // Wait for Chart.js to be available
        if (typeof Chart === 'undefined') {
            // Retry after a short delay if Chart.js is still loading
            setTimeout(function () {
                if (typeof Chart !== 'undefined') {
                    chartivio_is_updating_preview = false;
                    chartivio_update_live_preview();
                }
            }, 100);
            chartivio_is_updating_preview = false;
            return;
        }

        var canvas = document.getElementById('chartivio-live-chart');
        if (!canvas) {
            chartivio_is_updating_preview = false;
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
        if (chartivio_LiveChart) {
            try {
                if (typeof chartivio_LiveChart.destroy === 'function') {
                    chartivio_LiveChart.destroy();
                }
            } catch (e) {
                console.warn('Error destroying chartivio_LiveChart:', e);
            }
            chartivio_LiveChart = null;
        }

        // Then check Chart.js registry for any chart on this canvas
        try {
            if (typeof Chart !== 'undefined' && typeof Chart.getChart === 'function') {
                var existingChart = Chart.getChart(canvas);
                if (existingChart && existingChart !== chartivio_LiveChart) {
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
        let chartType = jQuery('#chartivio_type').val() || 'pie';
        let legendPos = jQuery('#chartivio_legend_pos').val() || 'top';
        let paletteKey = jQuery('#chartivio_palette').val() || 'default';
        let palette = (typeof chartivio_palettes !== 'undefined' && chartivio_palettes[paletteKey]) ? chartivio_palettes[paletteKey] : ((typeof chartivio_palettes !== 'undefined') ? chartivio_palettes['default'] : ['#3b82f6']);
        let xaxisLabel = jQuery('#chartivio_xaxis_label').val() || '';
        let yaxisLabel = jQuery('#chartivio_yaxis_label').val() || '';

        // Capture current state to handle race conditions
        var currentSource = jQuery('input[name="chartivio_source_selector"]:checked').val() || jQuery('#chartivio_active_source').val() || 'manual';
        var currentUrl = jQuery('#chartivio_csv_url').val();

        var labels = [], datasets = [];

        if (currentSource === 'csv') {
            if (!currentUrl || currentUrl.trim() === '') {
                jQuery('.chartivio-status').show().text('No CSV URL provided').css({ 'color': '#f59e0b', 'background': '#fffbeb' });
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
                jQuery('.chartivio-status').show().text('Loading CSV...').css({ 'color': '#3b82f6', 'background': '#eff6ff' });
                const response = await fetch(currentUrl);
                // Race condition check: Ensure source and URL haven't changed during fetch
                if (jQuery('#chartivio_active_source').val() !== 'csv' || jQuery('#chartivio_csv_url').val() !== currentUrl) return;

                if (!response.ok) {
                    throw new Error('Failed to fetch CSV: ' + response.statusText);
                }

                const text = await response.text();
                if (!text || text.trim() === '') {
                    throw new Error('CSV file is empty');
                }

                const rows = chartivio_parse_csv(text.trim());
                if (!rows || rows.length < 2) {
                    throw new Error('Invalid CSV format - need at least header and one data row');
                }

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

                jQuery('.chartivio-status').show().text('CSV Loaded (' + (rows.length - 1) + ' rows)').css({ 'color': '#10b981', 'background': '#f0fdf4' });

                // Populate CSV Preview Table
                var $previewTable = jQuery('.chartivio-csv-preview-table');
                var $previewContainer = jQuery('.chartivio-csv-preview-container');
                var $previewLabel = jQuery('.chartivio-csv-preview-label');

                if ($previewTable.length) {
                    // Store full CSV data for "View All" functionality
                    $previewTable.data('fullCSVRows', rows);

                    var totalDataRows = rows.length - 1;
                    var showingRows = Math.min(10, totalDataRows);

                    $previewLabel.text('CSV Data Preview (Showing ' + showingRows + ' of ' + totalDataRows + ' rows)');
                    var headHtml = '<tr>';
                    heads.forEach(h => headHtml += '<th>' + (h || '').trim() + '</th>');
                    headHtml += '</tr>';
                    $previewTable.find('thead').html(headHtml);

                    var bodyHtml = '';
                    rows.slice(1, 11).forEach(row => { // Show first 10 rows initially
                        bodyHtml += '<tr>';
                        row.forEach(cell => bodyHtml += '<td>' + (cell || '').trim() + '</td>');
                        bodyHtml += '</tr>';
                    });
                    $previewTable.find('tbody').html(bodyHtml);
                    $previewContainer.show();
                    $previewLabel.show();

                    // Show "View All" button only if there are more than 10 rows
                    var $viewAllBtn = jQuery('.chartivio-csv-view-all-btn');
                    if (totalDataRows > 10) {
                        $viewAllBtn.show().text('View All Rows (' + totalDataRows + ')');
                        $viewAllBtn.data('expanded', false);
                    } else {
                        $viewAllBtn.hide();
                    }
                }
            } catch (e) {
                if (jQuery('#chartivio_active_source').val() !== 'csv') return;
                console.error('CSV Fetch Error:', e);
                jQuery('.chartivio-status').show().text('Error: ' + e.message).css({ 'color': '#ef4444', 'background': '#fef2f2' });
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
            jQuery('.chartivio-status').hide();
            var headerCount = 0;
            jQuery('.chartivio-manual-table thead th').each(function (i) {
                // Skip the last column (delete button column)
                if (i === jQuery('.chartivio-manual-table thead th').length - 1) return;
                var val = jQuery(this).find('input').val() || '';
                if (i === 0) {
                    // First column is labels, don't create dataset for it
                    headerCount++;
                } else {
                    datasets.push({ label: val || 'Series ' + (datasets.length + 1), data: [] });
                    headerCount++;
                }
            });

            jQuery('.chartivio-manual-table tbody tr').each(function () {
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
        if (jQuery('#chartivio_active_source').val() !== currentSource) return;

        // Validate we have data to render
        if (labels.length === 0 || datasets.length === 0) {
            jQuery('.chartivio-status').show().text('No data to display').css({ 'color': '#f59e0b', 'background': '#fffbeb' });
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
            jQuery('.chartivio-status').show().text('All values are zero').css({ 'color': '#f59e0b', 'background': '#fffbeb' });
        }

        // Apply colors and performance optimizations to datasets
        datasets.forEach((ds, i) => {
            const colorArray = labels.map((_, j) => palette[j % palette.length] || '#ccc');
            const singleColor = palette[i % palette.length] || '#ccc';

            // Optimization for large data
            ds.normalized = true;
            ds.spanGaps = false;

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
                // Performance: disable points for large datasets
                if (labels.length > 200) {
                    ds.pointRadius = 0;
                    ds.pointHoverRadius = 0;
                }
            }
        });

        // Create the chart
        chartivio_LiveChart = new Chart(ctx, {
            type: chartType,
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: labels.length > 500 ? false : { duration: 800 }, // Disable animation for very large data
                scales: (chartType === 'bar' || chartType === 'line') ? {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: !!yaxisLabel,
                            text: yaxisLabel
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxRotation: 0,
                            minRotation: 0
                        },
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
                    },
                    tooltip: {
                        enabled: true,
                        intersect: false,
                        mode: 'index'
                    }
                }
            }
        });
    } catch (e) {
        console.error('Chart Render Error:', e);
        jQuery('.chartivio-status').show().text('Preview Error: ' + e.message).css({ 'color': '#ef4444', 'background': '#fff1f2' });
        // Try to clear canvas on error
        try {
            var errorCanvas = document.getElementById('chartivio-live-chart');
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
        chartivio_is_updating_preview = false;
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
                    chartivio_update_live_preview();
                } else if (attempts > 50) {
                    clearInterval(checkInterval);
                    console.error('Chart.js failed to load after 5 seconds');
                    jQuery('.chartivio-status').show().text('Chart.js library not loaded').css({ 'color': '#ef4444', 'background': '#fef2f2' });
                }
            }, 100);
        } else {
            chartivio_update_live_preview();
        }
    }

    // Try immediately, then also on window load as fallback
    initPreview();
    jQuery(window).on('load', function () {
        if (typeof Chart !== 'undefined' && !chartivio_LiveChart) {
            chartivio_update_live_preview();
        }
    });

    // Restore from local storage if different from saved
    var key = 'chartivio_autosave_' + chartivio_post_id;
    var raw = localStorage.getItem(key);
    if (raw) {
        try {
            var local_snapshot = JSON.parse(raw);
            if (!snapshotsEqual(local_snapshot, chartivio_saved_snapshot)) {
                // restore from local
                jQuery('#chartivio_active_source').val(local_snapshot.active_source);
                chartivio_SetSource(local_snapshot.active_source);
                jQuery('#chartivio_csv_url').val(local_snapshot.csv_url);
                jQuery('#chartivio_type').val(local_snapshot.type);
                jQuery('#chartivio_legend_pos').val(local_snapshot.legend_pos);
                jQuery('#chartivio_legend_pos').val(local_snapshot.legend_pos);
                jQuery('#chartivio_palette').val(local_snapshot.palette);
                if (local_snapshot.xaxis_label !== undefined) jQuery('#chartivio_xaxis_label').val(local_snapshot.xaxis_label);
                if (local_snapshot.yaxis_label !== undefined) jQuery('#chartivio_yaxis_label').val(local_snapshot.yaxis_label);
                if (local_snapshot.active_source === 'manual') {
                    // clear table
                    jQuery('.chartivio-manual-table thead tr').html('<th style="width:40px; cursor:pointer;" onclick="chartivio_add_column()">+</th>');
                    jQuery('.chartivio-manual-table tbody').html('');
                    // add headers
                    local_snapshot.manual.headers.forEach(function (h) {
                        jQuery('<th><input type="text" name="chartivio_manual_data[0][]" value="' + h.replace(/"/g, '"') + '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></th>').insertBefore(jQuery('.chartivio-manual-table thead th:last'));
                    });
                    // add rows
                    local_snapshot.manual.rows.forEach(function (row, idx) {
                        var html = '<tr>';
                        row.forEach(function (cell) {
                            html += '<td><input type="text" name="chartivio_manual_data[' + (idx + 1) + '][]" value="' + cell.replace(/"/g, '"') + '" oninput="chartivio_update_live_preview(); chartivio_local_autosave();"></td>';
                        });
                        html += '<td class="chartivio-delete-row" onclick="jQuery(this).closest(\'tr\').remove(); chartivio_update_live_preview(); chartivio_local_autosave();">×</td></tr>';
                        jQuery('.chartivio-manual-table tbody').append(html);
                    });
                    chartivio_add_delete_col_controls();
                }
                chartivio_update_live_preview();
                updateSaveButtonState();
            }
        } catch (e) { }
    }
    // Initialize autosave (no restore UI)
    chartivio_local_autosave();
});

function chartivio_get_snapshot() {
    var snapshot = { manual: { headers: [], rows: [] }, csv_url: jQuery('#chartivio_csv_url').val() || '', active_source: jQuery('#chartivio_active_source').val() || 'manual', type: jQuery('#chartivio_type').val() || '', legend_pos: jQuery('#chartivio_legend_pos').val() || '', palette: jQuery('#chartivio_palette').val() || '', xaxis_label: jQuery('#chartivio_xaxis_label').val() || '', yaxis_label: jQuery('#chartivio_yaxis_label').val() || '' };
    // headers (skip only add button i=last)
    jQuery('.chartivio-manual-table thead th').each(function (i) { if (i === jQuery('.chartivio-manual-table thead th').length - 1) return; var v = jQuery(this).find('input').val() || ''; snapshot.manual.headers.push(v); });
    // rows
    jQuery('.chartivio-manual-table tbody tr').each(function () { var row = []; jQuery(this).find('td').each(function (i) { if (i === jQuery(this).closest('tr').find('td').length - 1) return; var v = jQuery(this).find('input').val() || ''; row.push(v); }); snapshot.manual.rows.push(row); });
    return snapshot;
}

function snapshotsEqual(a, b) { try { return JSON.stringify(a) === JSON.stringify(b); } catch (e) { return false; } }



function chartivio_local_autosave(init) {
    var key = 'chartivio_autosave_' + chartivio_post_id;
    var curr = chartivio_get_snapshot();
    var raw = JSON.stringify(curr);
    localStorage.setItem(key, raw);
    // show restore button only when there's a local snapshot different from saved
    try {
        var savedRaw = JSON && chartivio_saved_snapshot ? JSON.stringify(chartivio_saved_snapshot) : '';
        var hasDiff = savedRaw !== raw;
        /* don't show a restore button; we keep a local snapshot for safety but do not expose restore UI */
        // no restore UI; just update button disability state below
        updateSaveButtonState();
    } catch (e) { }
}

function updateSaveButtonState() { /* Button state management removed to allow Publish action at all times */ }

function dcCopyShortcode() {
    var shortcode = document.getElementById('chartivio-shortcode').textContent;
    navigator.clipboard.writeText(shortcode).then(function () {
        document.getElementById('chartivio-copy-status').textContent = 'Copied!';
        setTimeout(function () {
            document.getElementById('chartivio-copy-status').textContent = '';
        }, 500);
    }).catch(function (err) {
        console.error('Failed to copy: ', err);
        document.getElementById('chartivio-copy-status').textContent = 'Copy failed';
        setTimeout(function () {
            document.getElementById('chartivio-copy-status').textContent = '';
        }, 1000);
    });
}

function cvCopyList(el, text) {
    navigator.clipboard.writeText(text).then(() => {
        const $el = jQuery(el);
        const originalHtml = $el.html();
        $el.html('<span class="dashicons dashicons-yes" style="color:#10b981"></span> <small style="color:#10b981; font-weight:bold;">Copied!</small>');
        setTimeout(() => {
            $el.html(originalHtml);
        }, 500);
    });
}

function chartivio_quick_save(btn) {
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
    jQuery('.chartivio-manual-table thead th input').each(function () { headers.push(jQuery(this).val()); });

    var rows = [];
    jQuery('.chartivio-manual-table tbody tr').each(function () {
        var row = [];
        jQuery(this).find('td input').each(function () { row.push(jQuery(this).val()); });
        if (row.length > 0) rows.push(row);
    });

    var data = {
        action: 'chartivio_save_chart',
        nonce: jQuery('#chartivio_nonce').val(),
        post_id: $btn.data('pid'),
        manual_json: JSON.stringify({ headers: headers, rows: rows }),
        post_title: title,
        chartivio_csv_url: jQuery('#chartivio_csv_url').val(),
        chartivio_active_source: jQuery('#chartivio_active_source').val(),
        chartivio_type: jQuery('#chartivio_type').val(),
        chartivio_legend_pos: jQuery('#chartivio_legend_pos').val(),
        chartivio_palette: jQuery('#chartivio_palette').val(),
        chartivio_xaxis_label: jQuery('#chartivio_xaxis_label').val(),
        chartivio_yaxis_label: jQuery('#chartivio_yaxis_label').val()
    };

    jQuery.post(ajaxurl, data, function (res) {
        $btn.text(originalText).prop('disabled', false);
        if (res.success) {
            jQuery('.chartivio-save-status').text('Saved!').css('color', '#10b981').show().delay(2000).fadeOut();

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
                jQuery('#chartivio_usage_box .inside').html(res.data.shortcode_html);
            }
        } else {
            alert('Save Failed');
        }
    });
}
