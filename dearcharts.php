<?php
/**
 * Plugin Name: DearCharts
 * Plugin URI: https://example.com/dearcharts
 * Description: A basic WordPress plugin header for DearCharts.
 * Version: 1.0.0
 * Author: Rachana Paudel
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: dearcharts
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register DearCharts Custom Post Type.
 */
function dearcharts_register_cpt()
{
    $args = array(
        'labels' => array(
            'name' => 'DearCharts',
            'singular_name' => 'DearChart',
        ),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-chart-area',
        'supports' => array('title'),
    );

    if (function_exists('register_post_type')) {
        register_post_type('dearcharts', $args);
    }
}
add_action('init', 'dearcharts_register_cpt');

/**
 * Shortcode to display DearChart title.
 * Usage: [dearchart_title id="123"]
 */
function dearcharts_shortcode_title($atts)
{
    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts, 'dearchart');

    $post_id = intval($atts['id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'dearcharts') {
        return 'Chart title not found';
    }

    $title = get_the_title($post_id);

    return '<h2 class="dearcharts-title">' . esc_html($title) . '</h2>';
}
add_shortcode('dearchart', 'dearcharts_shortcode_title');

/**
 * Add Chart ID and Shortcode columns to the dearcharts post type list.
 */
function dearcharts_add_id_column($columns)
{
    $columns['id'] = 'ID';
    $columns['shortcode'] = 'Shortcode';
    return $columns;
}
add_filter('manage_dearcharts_posts_columns', 'dearcharts_add_id_column');

/**
 * Display the content for custom columns.
 */
function dearcharts_custom_column_content($column, $post_id)
{
    switch ($column) {
        case 'id':
            echo $post_id;
            break;
        case 'shortcode':
            echo '<code>[dearchart id="' . $post_id . '"]</code>';
            break;
    }
}
add_action('manage_dearcharts_posts_custom_column', 'dearcharts_custom_column_content', 10, 2);

/**
 * Register Meta Box for DearCharts.
 */
function dearcharts_register_meta_box()
{
    add_meta_box(
        'dearcharts_data_meta_box',
        'Chart Data',
        'dearcharts_render_tabbed_meta_box',
        'dearcharts',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'dearcharts_register_meta_box');

/**
 * Render the Tabbed Meta Box.
 */
function dearcharts_render_tabbed_meta_box($post)
{
    // Retrieve existing values
    $manual_values = get_post_meta($post->ID, '_dearcharts_manual_values', true);
    $csv_url = get_post_meta($post->ID, '_dearcharts_csv_url', true);

    // Nonce field for security
    wp_nonce_field('dearcharts_save_meta_box_data', 'dearcharts_nonce');
    ?>
    <style>
        .dearcharts-tabs {
            border-bottom: 1px solid #ccc;
            margin-bottom: 15px;
        }

        .dearcharts-tab-link {
            display: inline-block;
            padding: 10px 15px;
            text-decoration: none;
            color: #444;
            border: 1px solid transparent;
            border-bottom: none;
            margin-bottom: -1px;
            cursor: pointer;
            font-weight: 500;
        }

        .dearcharts-tab-link.active {
            background-color: #fff;
            border-color: #ccc;
            border-top: 2px solid #2271b1;
            color: #000;
        }

        .dearcharts-tab-content {
            display: none;
        }

        .dearcharts-tab-content.active {
            display: block;
        }

        .dearcharts-input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .dearcharts-input-group input[type="text"] {
            width: 100%;
            max-width: 400px;
        }
    </style>

    <div class="dearcharts-tabs">
        <span class="dearcharts-tab-link active" onclick="openDearChartsTab(event, 'tab-manual')">Manual Data</span>
        <span class="dearcharts-tab-link" onclick="openDearChartsTab(event, 'tab-csv')">Import CSV</span>
    </div>

    <div id="tab-manual" class="dearcharts-tab-content active">
        <div class="dearcharts-input-group">
            <label for="dearcharts_manual_values">Enter comma-separated numbers:</label>
            <input type="text" id="dearcharts_manual_values" name="dearcharts_manual_values"
                value="<?php echo esc_attr($manual_values); ?>" placeholder="e.g. 10, 20, 30">
        </div>
    </div>

    <div id="tab-csv" class="dearcharts-tab-content">
        <div class="dearcharts-input-group">
            <label for="dearcharts_csv_url">CSV File URL:</label>
            <input type="text" id="dearcharts_csv_url" name="dearcharts_csv_url" value="<?php echo esc_attr($csv_url); ?>"
                placeholder="https://...">
            <button type="button" class="button" id="dearcharts_upload_csv_btn">Upload/Select</button>
        </div>
    </div>

    <script>
        function openDearChartsTab(evt, tabName) {
            // Hide all tab content
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("dearcharts-tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }

            // Remove active class from all tab links
            tablinks = document.getElementsByClassName("dearcharts-tab-link");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }

            // Show the current tab and add active class to the button
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        jQuery(document).ready(function ($) {
            $('#dearcharts_upload_csv_btn').click(function (e) {
                e.preventDefault();
                var image = wp.media({
                    title: 'Upload CSV',
                    // mutiple: true if you want to upload multiple files at once
                    multiple: false
                }).open()
                    .on('select', function (e) {
                        // This will return the selected image from the Media Uploader, the result is an object
                        var uploaded_image = image.state().get('selection').first();
                        // We convert that object to JSON
                        var image_url = uploaded_image.toJSON().url;
                        // Assign the url to the input
                        $('#dearcharts_csv_url').val(image_url);
                    });
            });
        });
    </script>
    <?php
}

/**
 * Save Meta Box Data.
 */
function dearcharts_save_meta_box_data($post_id)
{
    // Check nonce
    if (!isset($_POST['dearcharts_nonce']) || !wp_verify_nonce($_POST['dearcharts_nonce'], 'dearcharts_save_meta_box_data')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save Manual Data
    if (isset($_POST['dearcharts_manual_values'])) {
        $manual_data = sanitize_text_field($_POST['dearcharts_manual_values']);
        update_post_meta($post_id, '_dearcharts_manual_values', $manual_data);
    }

    // Save CSV URL
    if (isset($_POST['dearcharts_csv_url'])) {
        $csv_url = esc_url_raw($_POST['dearcharts_csv_url']);
        update_post_meta($post_id, '_dearcharts_csv_url', $csv_url);
    }
}
add_action('save_post', 'dearcharts_save_meta_box_data');

/**
 * Enqueue Media Scripts for CSV Upload.
 */
function dearcharts_admin_scripts($hook)
{
    global $post;
    if ($hook == 'post-new.php' || $hook == 'post.php') {
        if ('dearcharts' === $post->post_type) {
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'dearcharts_admin_scripts');
