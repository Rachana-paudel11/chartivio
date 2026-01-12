var cvio_palettes = {
    'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
    'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'],
    'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'],
    'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'],
    'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'],
    'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F']
};

/**
 * Robust CSV Parser (Supports quotes and newlines)
 */
function cvio_parse_csv(str) {
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

/**
 * Frontend Initialization for chartivio
 */
function chartivio_init_frontend(config) {
    var canvas = document.getElementById(config.id);
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var palette = cvio_palettes[config.palette] || cvio_palettes['default'];

    var drawChart = (l, ds) => {
        let realType = config.type;
        let indexAxis = 'x';

        if (realType === 'horizontalBar') {
            realType = 'bar';
            indexAxis = 'y';
        }

        // Apply colors and performance optimizations to datasets
        ds.forEach((set, i) => {
            let colors = (ds.length > 1) ? palette[i % palette.length] : l.map((_, j) => palette[j % palette.length]);

            // Optimization for large data
            set.normalized = true;
            set.spanGaps = false;

            if (realType === 'pie' || realType === 'doughnut') {
                set.backgroundColor = colors;
                set.borderColor = '#ffffff';
                set.borderWidth = 2;
            } else if (realType === 'bar') {
                set.backgroundColor = (ds.length > 1) ? palette[i % palette.length] : colors;
                set.borderColor = (ds.length > 1) ? palette[i % palette.length] : colors;
                set.borderWidth = 1;
            } else if (realType === 'line') {
                set.backgroundColor = (ds.length > 1) ? palette[i % palette.length] : palette[0];
                set.borderColor = (ds.length > 1) ? palette[i % palette.length] : palette[0];
                set.borderWidth = 2;
                set.fill = false;
                set.pointBackgroundColor = '#fff';
                // Performance: disable points for large datasets
                if (l.length > 200) {
                    set.pointRadius = 0;
                    set.pointHoverRadius = 0;
                }
            }
        });

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
    };

    if (config.source === 'csv' && config.csvUrl) {
        fetch(config.csvUrl).then(res => res.text()).then(text => {
            const rows = cvio_parse_csv(text.trim());
            if (!rows || rows.length < 2) return;

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
            drawChart(labels, datasets);
        }).catch(err => console.error('chartivio Load Error:', err));
    } else {
        let labels = [], datasets = [];
        let raw = config.manualData;
        if (raw) {
            let rows = Array.isArray(raw) ? raw : Object.keys(raw).sort((a, b) => a - b).map(k => raw[k]);
            if (rows.length > 0) {
                if (rows[0] && rows[0].label !== undefined) {
                    datasets.push({ label: 'Value', data: [] });
                    rows.forEach((row) => {
                        labels.push(row.label || '');
                        datasets[0].data.push(parseFloat(row.value) || 0);
                    });
                } else {
                    const headers = rows[0];
                    for (let i = 1; i < headers.length; i++) datasets.push({ label: headers[i], data: [] });
                    for (let r = 1; r < rows.length; r++) {
                        labels.push(rows[r][0]);
                        for (let c = 0; c < datasets.length; c++) datasets[c].data.push(parseFloat(rows[r][c + 1]) || 0);
                    }
                }
            }
        }
        drawChart(labels, datasets);
    }
}


