=== Chartivio ===
Contributors: rachanapaudel26
Tags: chart, charts, data visualization, pie chart, bar chart
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Chartivio is a simple wordpress charts builder designed for speed, minimalism, and professional data visualization.

== Description ==

Transform your data into a visual experience. **Chartivio** is a powerful yet user-friendly **wordpress chart builder** that allows you to create and manage professional, **minimalst charts** using a familiar custom post type interface. 

Most **data visualization** tools are either too complex or too heavy; Chartivio bridges that gap by offering a **lightweight charts** solution that prioritizes performance and aesthetics. Whether you are building a business dashboard or a blog infographic, Chartivio provides the **best charts** with zero coding required. 

== Installation ==

1. Upload the `chartivio` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the 'Chartivio' menu in your sidebar to start creating charts.

== Frequently Asked Questions ==

= How do I display a chart on my site? =
Simply create your chart, copy the unique shortcode, and paste it into any Post or Page.

= Can I import data from Excel? =
Yes! Use our **csv to chart** feature by saving your file as a .csv and uploading the URL.

= Are the charts mobile-friendly? =
Absolutely. Every chart created is a **responsive charts** solution.

== Third-Party Libraries ==

This plugin includes the following third-party library:

* Chart.js v4.5.1 - Licensed under MIT License
* Source: https://www.chartjs.org/
* Bundled locally in: assets/js/chartjs/chart.umd.min.js
* Used for rendering interactive charts

== Changelog ==

= 1.0.4 =
* Fixed: "Publishing failed. The response is not a valid JSON response" error when saving charts
* Improved: AJAX save handler stability against output from other plugins

= 1.0.3 =
* Fixed: CSV import related issue

= 1.0.2 =
* Updated: Chart.js upgraded to v4.5.1 (latest stable release)
* Fixed: WordPress.org compliance - replaced inline scripts with wp_add_inline_script()
* Fixed: Prefix consistency - all variables now use 'chartivio_' prefix
* Enhanced: Axis title fields now hide for pie/doughnut charts (UI improvement)
* Enhanced: Manual table - sticky action column and improved focus behavior
* Improved: Chart initialization with auto-init fallback for better compatibility

= 1.0.1 =
* Security: Fixed XSS vulnerability in shortcode callback (added proper JSON escaping)
* Updated: Chart.js upgraded from v4.4.1 to v4.4.7 (latest stable)
* Fixed: Charts now properly render on frontend after creating new posts
* Fixed: Post status automatically set to 'publish' when saving via AJAX
* Enhanced: Canvas dimension handling for better Chart.js initialization
* Enhanced: Data format detection and parsing robustness
* Improved: Comprehensive console logging for debugging
* Added: CDN delivery for Chart.js with security attributes

= 1.0.0 =
* Initial release.
* Added support for Pie, Doughnut, Bar, and Line charts.
* Implemented professional split-screen Admin UI with Live Preview.
* Added support for Manual Data Entry and CSV Import.
* Integrated curated color palettes and aesthetic settings.

== Upgrade Notice ==

