var dc_palettes = {
    'default': ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
    'pastel': ['#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff', '#e6e6fa'],
    'ocean': ['#0077be', '#009688', '#4db6ac', '#80cbc4', '#b2dfdb', '#004d40'],
    'sunset': ['#ff4500', '#ff8c00', '#ffa500', '#ffd700', '#ff6347', '#ff7f50'],
    'neon': ['#ff00ff', '#00ffff', '#00ff00', '#ffff00', '#ff0000', '#7b00ff'],
    'forest': ['#228B22', '#32CD32', '#90EE90', '#006400', '#556B2F', '#8FBC8F']
};

/**
 * PSEUDOCODE: dearcharts_init_frontend
 * 1. Get the 2D drawing context of the target canvas.
 * 2. Select the color palette based on user configuration.
 * 3. Define a helper function 'drawChart' to abstract Chart.js instantiation.
 * 4. If source is CSV: Fetch data via AJAX, parse CSV rows, and map columns to Chart.js datasets.
 * 5. If source is Manual: Parse stored nested array/object and map to Chart.js datasets.
 * 6. Finalize: Call Chart.js constructor with mapped labels and datasets.
 */
function dearcharts_init_frontend(config) {
    var canvas = document.getElementById(config.id);
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var palette = dc_palettes[config.palette] || dc_palettes['default'];

    var drawChart = (l, ds) => {
        let realType = config.type;
        let indexAxis = 'x';

        if (realType === 'horizontalBar') {
            realType = 'bar';
            indexAxis = 'y';
        }

        // PSEUDOCODE: Assign colors from palette to each dataset or each data point.
        ds.forEach((set, i) => {
            let colors = (ds.length > 1) ? palette[i % palette.length] : l.map((_, j) => palette[j % palette.length]);
            if (realType === 'bar' || realType === 'line') {
                set.backgroundColor = (ds.length > 1) ? palette[i % palette.length] : palette;
                set.borderColor = (ds.length > 1) ? palette[i % palette.length] : palette;
            } else {
                set.backgroundColor = colors;
                set.borderColor = colors;
            }
            set.borderWidth = (realType === 'line') ? 2 : 1;
            set.fill = (realType === 'line') ? false : true;
        });

        new Chart(ctx, {
            type: realType,
            data: { labels: l, datasets: ds },
            options: {
                indexAxis: indexAxis,
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                scales: (realType === 'bar' || realType === 'line') ? { 
                    y: { 
                        beginAtZero: true,
                        title: { display: !!config.yaxisLabel, text: config.yaxisLabel }
                    },
                    x: {
                        title: { display: !!config.xaxisLabel, text: config.xaxisLabel }
                    }
                } : {},
                plugins: { legend: { display: config.legendPos !== 'none' && (ds.length > 1 || ['pie', 'doughnut'].includes(realType)), position: config.legendPos } }
            }
        });
    };

    if (config.source === 'csv' && config.csvUrl) {
        // PSEUDOCODE: Fetch raw CSV text from the stored URL.
        fetch(config.csvUrl).then(res => res.text()).then(text => {
            const lines = text.trim().split(/\r\n|\n/);
            let labels = [], datasets = [];
            const headParts = lines[0].split(',');
            // PSEUDOCODE: Identify multiple datasets (columns) based on the first row header.
            for (let i = 1; i < headParts.length; i++) datasets.push({ label: headParts[i].trim(), data: [] });
            // PSEUDOCODE: Map subsequent rows to labels (Col 1) and data points (Col 2+).
            for (let r = 1; r < lines.length; r++) {
                const rowParts = lines[r].split(',');
                labels.push(rowParts[0].trim());
                for (let c = 0; c < datasets.length; c++) datasets[c].data.push(parseFloat(rowParts[c + 1]) || 0);
            }
            drawChart(labels, datasets);
        });
    } else {
        let labels = [], datasets = [];
        let raw = config.manualData;
        if (raw) {
            // PSEUDOCODE: Convert storage format (array or object) into a sequential list of rows.
            let rows = Array.isArray(raw) ? raw : Object.keys(raw).sort((a, b) => a - b).map(k => raw[k]);

            if (rows.length > 0) {
                // PSEUDOCODE: Check if data is in 'Legacy' (label/value) or 'Multi-Series' (columnar) format.
                if (rows[0] && rows[0].label !== undefined) {
                    // Legacy Format Handling
                    datasets.push({ label: 'Value', data: [] });
                    rows.forEach((row) => {
                        labels.push(row.label || '');
                        datasets[0].data.push(parseFloat(row.value) || 0);
                    });
                } else {
                    // Multi-Series Columnar Format Handling
                    const headers = rows[0];
                    // Extract series names from the header row.
                    for (let i = 1; i < headers.length; i++) datasets.push({ label: headers[i], data: [] });
                    // Extract labels and values from subsequent rows.
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