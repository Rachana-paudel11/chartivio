<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the How to Use Page
 */
function dearcharts_render_how_to_use_page()
{
    ?>
    <div class="wrap dearcharts-how-to-use">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <h1 class="dc-page-title">How to Use DearCharts</h1>

        <div class="dc-guide-container">
            <!-- Introduction -->
            <div class="dc-guide-section dc-intro">
                <h2>Welcome to DearCharts</h2>
                <p>Create beautiful, responsive, and animated charts for your WordPress site in minutes. This guide will
                    walk you through the basics.</p>
            </div>

            <div class="dc-guide-grid">
                <!-- Step 1: Create -->
                <div class="dc-guide-card">
                    <div class="dc-step-icon">1</div>
                    <h3>Create a Chart</h3>
                    <p>Navigate to <strong>DearCharts > Add New Chart</strong>. Give your chart a title to get started.
                        You'll immediately see the live preview.</p>
                </div>

                <!-- Step 2: Select Type -->
                <div class="dc-guide-card">
                    <div class="dc-step-icon">2</div>
                    <h3>Choose Chart Type</h3>
                    <p>Use the <strong>Chart Type</strong> selector in the top-left corner to switch between Pie, Doughnut,
                        Bar, and Line charts instantly.</p>
                </div>

                <!-- Step 3: Data Source -->
                <div class="dc-guide-card">
                    <div class="dc-step-icon">3</div>
                    <h3>Add Your Data</h3>
                    <p>Select your data source:</p>
                    <ul>
                        <li><strong>Manual Entry:</strong> Type data directly into the table. Use "Add Row" and "Add Column"
                            to expand.</li>
                        <li><strong>Import CSV:</strong> Upload or paste a URL to a CSV file for dynamic data handling.</li>
                    </ul>
                </div>

                <!-- Step 4: Shortcode -->
                <div class="dc-guide-card">
                    <div class="dc-step-icon">4</div>
                    <h3>Publish & Embed</h3>
                    <p>Once you're happy, click <strong>Publish</strong>. Copy the shortcode from the sidebar or the chart
                        list and paste it into any page or post.</p>
                </div>
            </div>

            <!-- Detailed Instructions -->
            <div class="dc-guide-details">
                <div class="dc-detail-box">
                    <h3><span class="dashicons dashicons-editor-table"></span> formatting CSV Data</h3>
                    <p>If you are importing CSV data, ensure your first row contains the specific labels (like "Year",
                        "Month") and the subsequent rows contain the data. The plugin automatically detects the structure.
                    </p>
                </div>

                <div class="dc-detail-box">
                    <h3><span class="dashicons dashicons-admin-appearance"></span> Customization</h3>
                    <p>On the right side panel, use the <strong>Chart Settings</strong> tab to customize colors, toggle
                        legends, and adjust the position of chart elements.</p>
                </div>
            </div>

            <div class="dc-action-area">
                <a href="<?php echo admin_url('post-new.php?post_type=dearcharts'); ?>"
                    class="button button-primary dc-big-btn">Create Your First Chart</a>
            </div>
        </div>
    </div>

    <style>
        .dearcharts-how-to-use {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
        }

        .dc-page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 30px;
        }

        .dc-guide-container {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dc-guide-section.dc-intro {
            text-align: center;
            margin-bottom: 50px;
        }

        .dc-intro h2 {
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .dc-intro p {
            font-size: 16px;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
        }

        .dc-guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .dc-guide-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 25px;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .dc-guide-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }

        .dc-step-icon {
            width: 40px;
            height: 40px;
            background: #3b82f6;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .dc-guide-card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 10px 0;
        }

        .dc-guide-card p {
            color: #475569;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .dc-guide-card ul {
            margin: 10px 0 0 15px;
            list-style-type: disc;
            color: #475569;
            font-size: 14px;
        }

        .dc-guide-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .dc-detail-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 20px;
        }

        .dc-detail-box h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1e40af;
            font-size: 16px;
            margin: 0 0 10px 0;
        }

        .dc-detail-box p {
            color: #1e3a8a;
            font-size: 14px;
            margin: 0;
            line-height: 1.5;
        }

        .dc-action-area {
            text-align: center;
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 40px;
        }

        .dc-big-btn {
            font-size: 16px !important;
            padding: 10px 30px !important;
            height: auto !important;
        }

        @media (max-width: 768px) {
            .dc-guide-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
}
