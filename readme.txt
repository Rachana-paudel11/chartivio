=== Chartivio ===
Contributors: rachanapaudel26
Tags: chart, charts, data visualization, pie chart, bar chart
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Professional, interactive data visualization for WordPress. Create stunning charts with a live-preview editor, CSV support, and manual data entry.

== Description ==

Chartivio is a powerful yet simple WordPress plugin that allows you to create and manage professional charts using a custom post type interface. Featuring a modern, split-screen admin UI, you can see your changes in real-time as you configure your data and styling.

= Key Features =
* **Live Preview Engine**: See your chart update instantly as you change data or settings.
* **Dual Data Sources**: Import data directly via CSV URL or enter it manually in a professional spreadsheet-like table.
* **Multiple Chart Types**: Supports Pie, Doughnut, Bar, and Line charts out of the box.
* **Responsive Design**: Charts are automatically responsive and look great on all devices.
* **Aesthetic Customization**: Choose from curated color palettes (Pastel, Ocean, Sunset, Neon, Forest) and customize axes and legends.
* **Shortcode Integration**: Easily embed charts anywhere using the generated [chartivio id="XX"] shortcode.

== Installation ==

1. Upload the `chartivio` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the 'Chartivio' menu in your dashboard to start creating charts.
4. Add a new chart, configure your data, and copy the provided shortcode into any page or post.

== Frequently Asked Questions ==

= How do I display a chart on my site? =
After publishing a chart, a shortcode like `[chartivio id="123"]` will be provided in the 'Chart Shortcodes' sidebar. Copy and paste this shortcode into any page, post, or widget area.

= Can I import data from Excel? =
Yes! Save your Excel sheet as a CSV file, upload it to your Media Library, and paste the URL into the 'Import from CSV' field in the plugin editor.

= Are the charts mobile-friendly? =
Absolutely. The charts use Chart.js which is responsive by default, and the plugin ensures they fit perfectly within their containers.

== Screenshots ==

1. The split-screen editor showing the Live Preview and Settings panel.
2. Manual Data Entry table with multi-series support.
3. Appearance settings with color palette selection and preview.

== Third-Party Libraries ==

This plugin includes the following third-party library:

* Chart.js v4.5.1 - Licensed under MIT License
* Source: https://www.chartjs.org/
* Bundled locally in: assets/js/chartjs/chart.umd.min.js
* Used for rendering interactive charts

== Changelog ==

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

