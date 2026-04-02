/**
 * Chart Initialization Function for Shortcode Instances
 * Called via wp_add_inline_script with dynamic config and canvas ID
 * This ensures proper WordPress enqueue queue management while handling dynamic data
 */
function chartivio_init_chart(config, canvasId) {
    console.log('chartivio_init_chart called with config:', config);

    var init = function () {
        var canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas element not found:', canvasId);
            return;
        }
        console.log('Canvas found, initializing...');

        // ── Inline-wrapper guard ──────────────────────────────────────────────
        // WordPress can inject inline HTML (e.g. <strong>) around shortcodes when
        // the editor accidentally formats the shortcode text as bold/italic.
        // Because <canvas> (inline) sits inside <div> (block) our HTML is valid,
        // but the browser re-parses  <strong><div>...</div></strong>  as invalid and
        // moves the inline element so it ends up wrapping the <canvas> directly.
        // We detect and fix that here — before Chart.js reads any dimensions.
        (function unwrapInlineParents(el) {
            var INLINE_TAGS = ['STRONG', 'EM', 'B', 'I', 'U', 'S', 'SPAN', 'A'];

            // Locate the real .chartivio-container ancestor (with closest() fallback)
            var chartContainer = null;
            if (typeof el.closest === 'function') {
                chartContainer = el.closest('.chartivio-container');
            } else {
                var p = el.parentElement;
                while (p) {
                    if (p.className && p.className.indexOf('chartivio-container') !== -1) {
                        chartContainer = p; break;
                    }
                    p = p.parentElement;
                }
            }

            if (!chartContainer) { return; } // safety: nothing to do

            // Walk up from the canvas; if we hit a known inline tag before we
            // reach .chartivio-container, move the canvas out of it.
            var parent = el.parentElement;
            while (parent && parent !== chartContainer) {
                if (INLINE_TAGS.indexOf(parent.tagName) !== -1) {
                    var grandParent = parent.parentElement;
                    // Move canvas to be a direct child of its grandparent
                    grandParent.insertBefore(el, parent);
                    // Remove the now-empty inline wrapper
                    if (parent.innerHTML.trim() === '') {
                        grandParent.removeChild(parent);
                    }
                    console.log('chartivio: unwrapped stray <' + parent.tagName + '> from canvas');
                    // Reset for next iteration (el.parentElement may have changed)
                    parent = el.parentElement;
                } else {
                    break;
                }
            }
        })(canvas);
        // ── End inline-wrapper guard ──────────────────────────────────────────

        // Read dimensions from the real .chartivio-container (not canvas.parentElement,
        // which could be a stray element before the guard ran).
        var container = (typeof canvas.closest === 'function')
            ? canvas.closest('.chartivio-container')
            : canvas.parentElement;

        if (container) {
            var rect = container.getBoundingClientRect();
            canvas.width  = rect.width  || 800;
            canvas.height = rect.height || 400;
            console.log('Canvas dimensions set:', canvas.width, 'x', canvas.height);
        } else {
            canvas.width  = 800;
            canvas.height = 400;
            console.log('No container found, using fallback dimensions');
        }


        config.id = canvasId;
        console.log('Chart config:', config);

        if (typeof chartivio_init_frontend === 'function') {
            console.log('Calling chartivio_init_frontend with config', config);
            chartivio_init_frontend(config);
        } else {
            console.error('chartivio_init_frontend is not defined yet, retrying in 200ms');
            setTimeout(function () {
                if (typeof chartivio_init_frontend === 'function') {
                    console.log('Retrying chartivio_init_frontend');
                    chartivio_init_frontend(config);
                } else {
                    console.error('chartivio_init_frontend still not available');
                }
            }, 200);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 50);
    }
}

var chartivio_palettes = {
    'default': ['#2271b1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
    'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'],
    'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'],
    'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'],
    'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'],
    'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F']
};

/**
 * Robust CSV Parser (Supports quotes and newlines)
 */
function chartivio_frontend_parse_csv(str) {
    if (!str) return [];
    // Remove UTF-8 BOM if present
    if (str.charCodeAt(0) === 0xFEFF) {
        str = str.substring(1);
    }
    
    // Auto-detect delimiter: comma, semicolon, or tab?
    var firstLine = str.split(/\r\n|\n|\r/)[0];
    if (!firstLine) return [];

    var delimiter = ',';
    var commas = (firstLine.match(/,/g) || []).length;
    var semis = (firstLine.match(/;/g) || []).length;
    var tabs = (firstLine.match(/\t/g) || []).length;
    
    if (semis > commas && semis > tabs) delimiter = ';';
    else if (tabs > commas && tabs > semis) delimiter = '\t';

    var arr = [];
    var quote = false;
    var row = 0, col = 0;
    arr[row] = [];
    arr[row][col] = '';

    for (var c = 0; c < str.length; c++) {
        var cc = str[c], nc = str[c + 1];
        
        if (cc == '"') {
            if (quote && nc == '"') { // Escaped quote
                arr[row][col] += '"';
                c++;
            } else {
                quote = !quote;
            }
            continue;
        }
        
        if (!quote) {
            if (cc == delimiter) {
                col++;
                arr[row][col] = '';
                continue;
            }
            if (cc == '\r' && nc == '\n') {
                row++;
                col = 0;
                arr[row] = [''];
                c++;
                continue;
            }
            if (cc == '\n' || cc == '\r') {
                row++;
                col = 0;
                arr[row] = [''];
                continue;
            }
        }
        arr[row][col] += cc;
    }
    // Filter out empty rows
    return arr.filter(function(r) { return r.some(function(cell) { return cell.trim() !== ''; }); });
}


/**
 * Frontend Initialization for chartivio
 */
function chartivio_init_frontend(config) {
    console.log('chartivio_init_frontend called with config:', config);

    // Ensure Chart.js is available before initializing
    if (typeof Chart === 'undefined') {
        console.error('Chart.js library not loaded yet. Retrying in 100ms...');
        setTimeout(function () {
            chartivio_init_frontend(config);
        }, 100);
        return;
    }

    var canvas = document.getElementById(config.id);
    if (!canvas) {
        console.error('Canvas element not found with ID:', config.id);
        return;
    }

    console.log('Canvas element found:', canvas);

    // Ensure canvas has proper dimensions (Canvas rendering requires actual width/height attributes)
    if (!canvas.width || canvas.width === 0) {
        var container = canvas.parentElement;
        if (container) {
            var rect = container.getBoundingClientRect();
            console.log('Container dimensions:', rect.width, 'x', rect.height);
            canvas.width = rect.width || 800;
            canvas.height = rect.height || 400;
        } else {
            // Fallback
            canvas.width = 800;
            canvas.height = 400;
        }
    }

    console.log('Canvas final dimensions:', canvas.width, 'x', canvas.height);

    var ctx = canvas.getContext('2d');
    if (!ctx) {
        console.error('Failed to get 2D context from canvas');
        return;
    }

    var palette = chartivio_palettes[config.palette] || chartivio_palettes['default'];

    var drawChart = (l, ds) => {
        console.log('Draw Chart called with labels:', l, 'and datasets:', ds);
        let realType = config.type;
        let indexAxis = 'x';

        if (realType === 'horizontalBar') {
            realType = 'bar';
            indexAxis = 'y';
        }

        // Apply colors and performance optimizations to datasets
        ds.forEach((set, i) => {
            const colorArray = l.map((_, j) => palette[j % palette.length] || '#ccc');
            const singleColor = palette[i % palette.length] || '#ccc';

            // Optimization for large data
            set.normalized = true;
            set.spanGaps = false;

            if (realType === 'pie' || realType === 'doughnut') {
                set.backgroundColor = colorArray;
                set.borderColor = '#ffffff';
                set.borderWidth = 2;
            } else if (realType === 'bar') {
                if (ds.length > 1) {
                    set.backgroundColor = singleColor;
                    set.borderColor = singleColor;
                } else {
                    set.backgroundColor = colorArray;
                    set.borderColor = colorArray;
                }
                set.borderWidth = 1;
            } else if (realType === 'line') {
                set.backgroundColor = singleColor;
                set.borderColor = singleColor;
                set.borderWidth = 2;
                set.fill = false;
                set.pointBackgroundColor = '#fff';
                set.pointBorderColor = singleColor;
                // Performance: disable points for large datasets
                if (l.length > 200) {
                    set.pointRadius = 0;
                    set.pointHoverRadius = 0;
                }
            }
        });

        try {
            new Chart(ctx, {
                type: realType,
                data: { labels: l, datasets: ds },
                options: {
                    indexAxis: indexAxis,
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: l.length > 500 ? false : { duration: 800 },
                    scales: (realType === 'bar' || realType === 'line') ? {
                        y: {
                            beginAtZero: true,
                            title: { display: !!config.yaxisLabel, text: config.yaxisLabel }
                        },
                        x: {
                            ticks: {
                                autoSkip: true,
                                maxRotation: 0,
                                minRotation: 0
                            },
                            title: { display: !!config.xaxisLabel, text: config.xaxisLabel }
                        }
                    } : {},
                    plugins: {
                        legend: {
                            display: config.legendPos !== 'none' && (ds.length > 1 || ['pie', 'doughnut'].includes(realType)),
                            position: config.legendPos
                        },
                        tooltip: {
                            enabled: true,
                            intersect: false,
                            mode: 'index'
                        }
                    }
                }
            });
            console.log('Chart created successfully');
        } catch (e) {
            console.error('Error creating Chart.js instance:', e);
        }
    };

    if (config.source === 'csv' && config.csvUrl) {
        console.log('Loading CSV from:', config.csvUrl);
        
        // Use AJAX Proxy to bypass CORS issues
        var ajaxUrl = (typeof chartivio_ajax !== 'undefined' && chartivio_ajax.ajaxurl) ? chartivio_ajax.ajaxurl : '';
        var fetchUrl = config.csvUrl;
        
        if (ajaxUrl) {
            fetchUrl = ajaxUrl + '?action=chartivio_fetch_csv&url=' + encodeURIComponent(config.csvUrl);
        }

        fetch(fetchUrl).then(res => {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status + ' - ' + (res.statusText || 'Fetch Failed'));
            }
            return res.text();
        }).then(text => {
            if (!text || text.trim() === '') {
                throw new Error('CSV is empty');
            }
            const rows = chartivio_frontend_parse_csv(text.trim());
            if (!rows || rows.length < 2) {
                console.warn('CSV data is empty or invalid (headers only?)');
                return;
            }

            let labels = [], datasets = [];
            const heads = rows[0];

            for (let i = 1; i < heads.length; i++) {
                datasets.push({ label: (heads[i] || 'Series ' + i).trim(), data: [] });
            }

            for (let r = 1; r < rows.length; r++) {
                const row = rows[r];
                if (!row || row.length < 2) continue;
                labels.push((row[0] || '').trim());
                for (let c = 0; c < datasets.length; c++) {
                    datasets[c].data.push(parseFloat((row[c + 1] || '0').replace(/,/g, '')) || 0);
                }
            }

            if (labels.length > 0 && datasets.length > 0) {
                drawChart(labels, datasets);
            } else {
                console.warn('CSV data has no valid numerical content rows');
            }
        }).catch(err => {
            console.error('chartivio frontend CSV Load Error:', err);
            // Draw error on canvas if possible
            const errorOverlay = document.getElementById(canvasId + '-error'); if (errorOverlay) { errorOverlay.style.display = 'flex'; errorOverlay.querySelector('.error-message').textContent = 'Error: ' + err.message; } if (ctx) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#ef4444';
                ctx.font = '14px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('CSV Error: ' + err.message, canvas.width / 2, canvas.height / 2);
            }
        });
    } else {
        console.log('Using manual data:', config.manualData);
        let labels = [], datasets = [];
        let raw = config.manualData;

        if (raw && (Array.isArray(raw) && raw.length > 0 || typeof raw === 'object' && Object.keys(raw).length > 0)) {
            let rows = Array.isArray(raw) ? raw : Object.keys(raw).sort((a, b) => {
                // Sort numerically if keys are numeric
                return (isNaN(a) ? 0 : parseInt(a)) - (isNaN(b) ? 0 : parseInt(b));
            }).map(k => raw[k]);

            console.log('Processed rows:', rows);

            if (rows.length > 0) {
                // Check if first row is header row or data row
                let firstRow = rows[0];

                if (firstRow && Array.isArray(firstRow) && firstRow.length > 0) {
                    // Columnar format (first row is headers)
                    const headers = firstRow;
                    console.log('Headers:', headers);

                    for (let i = 1; i < headers.length; i++) {
                        const headerLabel = headers[i] || 'Series ' + i;
                        datasets.push({ label: headerLabel.toString(), data: [] });
                    }

                    for (let r = 1; r < rows.length; r++) {
                        const row = rows[r];
                        if (!Array.isArray(row) || row.length === 0) continue;

                        labels.push((row[0] || '').toString().trim());

                        for (let c = 1; c < row.length; c++) {
                            const value = parseFloat(row[c]) || 0;
                            if (datasets[c - 1]) {
                                datasets[c - 1].data.push(value);
                            }
                        }
                    }
                } else if (firstRow && typeof firstRow === 'object' && 'label' in firstRow) {
                    // Legacy label/value format
                    console.log('Using legacy label/value format');
                    datasets.push({ label: 'Value', data: [] });
                    rows.forEach((row) => {
                        if (row.label !== undefined) {
                            labels.push(row.label.toString());
                            datasets[0].data.push(parseFloat(row.value) || 0);
                        }
                    });
                } else {
                    console.warn('Unknown data format');
                }
            } else {
                console.warn('Manual data array is empty');
            }
        } else {
            console.warn('No manual data provided or data is empty');
        }

        console.log('Final data - labels:', labels, 'datasets:', datasets);

        if (labels.length > 0 && datasets.length > 0 && datasets[0].data.length > 0) {
            drawChart(labels, datasets);
        } else {
            console.warn('No valid chart data available - labels:', labels.length, 'datasets:', datasets.length, 'first_dataset_data:', datasets.length > 0 ? datasets[0].data.length : 0);
        }
    }
}



// Auto-init fallback: initialize any canvas with data-config if inline init missing
(function () {
    var boot = function () {
        var nodes = document.querySelectorAll('canvas[data-config]');
        nodes.forEach(function (node) {
            if (node.dataset.chartivioInitialized) return;
            var raw = node.getAttribute('data-config');
            if (!raw) return;
            try {
                var cfg = JSON.parse(raw);
                var id = node.id;
                if (!id && cfg.id) { node.id = cfg.id; id = cfg.id; }
                if (!id) return;
                node.dataset.chartivioInitialized = '1';
                chartivio_init_chart(cfg, id);
            } catch (e) {
                console.error('chartivio auto-init parse error', e);
            }
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

