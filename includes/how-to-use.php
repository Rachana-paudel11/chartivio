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
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=dearcharts')); ?>"
                    class="button button-primary dc-big-btn">Create Your First Chart</a>
            </div>
        </div>
    </div>

    <?php
}
