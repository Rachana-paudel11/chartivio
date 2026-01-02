# DearCharts Plugin - Code Structure

This document explains the structure of the DearCharts WordPress plugin to help you navigate and modify the code safely.

## 📁 Directory Structure

```
dearcharts/
├── dearcharts.php              # Main plugin file
├── includes/
│   ├── admin-settings.php      # Admin interface (1,400+ lines)
│   └── shortcodes.php          # Frontend shortcode handler
├── assets/
│   └── js/
│       └── dearcharts.js       # Frontend JavaScript
├── CHANGELOG.md                # Track all changes
├── DEVELOPMENT.md              # Development workflow guide
└── CODE_STRUCTURE.md            # This file
```

## 🔑 Key Files Explained

### 1. `dearcharts.php` (Main Plugin File)
**Purpose**: Plugin initialization and custom post type registration

**Key Functions**:
- `dearcharts_register_cpt()` - Registers 'dearcharts' custom post type
- Includes admin-settings.php and shortcodes.php

**When to Modify**:
- Adding new plugin constants
- Changing post type settings
- Adding new includes

**Lines to Know**:
- Line 14: Plugin path constant
- Line 20-48: Custom post type registration
- Line 56-59: File includes

---

### 2. `includes/admin-settings.php` (Admin Interface)
**Purpose**: Complete admin UI for creating/editing charts

**Structure**:
1. **Hooks & Filters** (Lines 13-43)
   - CSV upload filter
   - Meta box registration
   - Admin asset enqueuing

2. **Meta Box Rendering** (Lines 48-1107)
   - `dearcharts_render_usage_box()` - Shortcode display
   - `dearcharts_render_main_box()` - Main chart editor UI

3. **JavaScript Functions** (Lines 582-1107)
   - Live preview engine
   - Data management
   - Save functionality

4. **PHP Save Handlers** (Lines 1110-1427)
   - `dearcharts_sanitize_manual_data()` - Data sanitization
   - `dearcharts_save_meta_box_data()` - Save on post save
   - `dearcharts_ajax_save_chart()` - AJAX save handler

**Critical Functions**:

#### `dearcharts_update_live_preview()` (Line ~787)
- **Purpose**: Updates the live chart preview
- **Complexity**: High - handles CSV, manual data, Chart.js rendering
- **Key Variables**:
  - `dcLiveChart` - Global chart instance
  - `dc_is_updating_preview` - Prevents concurrent calls
- **Modify Carefully**: This is frequently changed, check CHANGELOG first

#### `dearcharts_save_meta_box_data()` (Line ~1358)
- **Purpose**: Saves chart data when post is saved
- **Security**: Nonce verification, capability checks
- **Data Saved**:
  - `_dearcharts_manual_data` - Table data
  - `_dearcharts_csv_url` - CSV file URL
  - `_dearcharts_active_source` - 'csv' or 'manual'
  - `_dearcharts_type` - Chart type
  - `_dearcharts_legend_pos` - Legend position
  - `_dearcharts_palette` - Color palette

**JavaScript Global Variables** (Line 583):
```javascript
var dcLiveChart = null;                    // Chart instance
var dc_post_id = <?php echo $post->ID; ?>; // Current post ID
var dc_admin_nonce = '...';                // Security nonce
var dc_is_updating_preview = false;        // Concurrency flag
var dc_palettes = {...};                   // Color palettes
```

**CSS Styles** (Lines 84-406):
- Custom CSS for split-screen admin UI
- Chart container styling
- Table editor styling

---

### 3. `includes/shortcodes.php` (Frontend Rendering)
**Purpose**: Handles `[dearchart id="X"]` shortcode

**Key Functions**:

#### `dearcharts_render_shortcode()` (Line 18)
- **Parameters**: `id`, `width`, `height`, `max_width`
- **Returns**: HTML with canvas element and initialization script
- **Process**:
  1. Validates post ID
  2. Retrieves chart data from post meta
  3. Enqueues frontend assets
  4. Outputs canvas with unique ID
  5. Calls `dearcharts_init_frontend()` via inline script

#### `dearcharts_frontend_assets()` (Line 85)
- Enqueues Chart.js library
- Enqueues frontend JavaScript

**When to Modify**:
- Changing shortcode attributes
- Modifying frontend output
- Adding new frontend features

---

### 4. `assets/js/dearcharts.js` (Frontend JavaScript)
**Purpose**: Initializes charts on the frontend

**Key Function**:

#### `dearcharts_init_frontend(config)` (Line 19)
- **Parameters**: Config object with chart settings
- **Process**:
  1. Gets canvas element
  2. Parses data (CSV or manual)
  3. Applies color palette
  4. Creates Chart.js instance

**Data Formats Supported**:
- Legacy: `{label: "X", value: 10}`
- Columnar: `[["Label", "Series1"], ["Jan", 10]]`

**When to Modify**:
- Adding new chart types
- Changing color logic
- Modifying data parsing

---

## 🔄 Data Flow

### Admin → Database
```
User Input → JavaScript (admin-settings.php)
         → AJAX/Form Submit
         → PHP Save Handler
         → WordPress Post Meta
```

### Database → Frontend
```
Post Meta → Shortcode Handler
         → Config Object
         → Frontend JS
         → Chart.js
         → Canvas Rendering
```

## 📊 Post Meta Keys

All chart data is stored as post meta:

| Meta Key | Type | Description |
|----------|------|-------------|
| `_dearcharts_manual_data` | Array | Table data (headers + rows) |
| `_dearcharts_csv_url` | String | URL to CSV file |
| `_dearcharts_active_source` | String | 'csv' or 'manual' |
| `_dearcharts_type` | String | 'pie', 'doughnut', 'bar', 'line' |
| `_dearcharts_legend_pos` | String | 'top', 'bottom', 'left', 'right', 'none' |
| `_dearcharts_palette` | String | Palette key ('default', 'pastel', etc.) |

## 🎨 Color Palettes

Defined in two places:
1. **Admin**: `includes/admin-settings.php` line ~619 (JavaScript)
2. **Frontend**: `assets/js/dearcharts.js` line 1 (JavaScript)

**Available Palettes**:
- `default` - Standard colors
- `pastel` - Soft pastels
- `ocean` - Blue/green tones
- `sunset` - Orange/red tones
- `neon` - Bright neon colors
- `forest` - Green tones

## ⚠️ Common Modification Points

### High-Risk Areas (Modify Carefully)
1. **Live Preview Function** (`dearcharts_update_live_preview`)
   - Complex async logic
   - Chart.js integration
   - Data parsing

2. **Save Handlers** (`dearcharts_save_meta_box_data`, `dearcharts_ajax_save_chart`)
   - Security critical
   - Data validation
   - Database operations

3. **Data Format** (Manual data structure)
   - Backward compatibility needed
   - Multiple format support

### Safe to Modify
1. **CSS Styles** (Lines 84-406 in admin-settings.php)
2. **Color Palettes** (Add new palettes)
3. **Shortcode Attributes** (Add new parameters)
4. **UI Text/Labels** (Change wording)

## 🔍 Finding Code

### Search Tips
```bash
# Find a function
grep -r "function function_name" .

# Find where a variable is used
grep -r "variable_name" .

# Find all JavaScript functions
grep -r "function " assets/js/

# Find all PHP functions
grep -r "^function " includes/
```

### Key Line Numbers
- Admin UI Start: `includes/admin-settings.php:70`
- JavaScript Start: `includes/admin-settings.php:582`
- Live Preview: `includes/admin-settings.php:787`
- Save Handler: `includes/admin-settings.php:1358`
- Shortcode: `includes/shortcodes.php:18`
- Frontend JS: `assets/js/dearcharts.js:19`

## 📝 Code Style Notes

- **PHP**: WordPress coding standards
- **JavaScript**: jQuery-based, ES5 compatible
- **CSS**: Inline in PHP file (admin), no separate CSS file
- **Comments**: Extensive pseudocode comments in PHP

## 🚀 Adding New Features

1. **Read this file** to understand structure
2. **Check CHANGELOG.md** for recent changes
3. **Identify which file** to modify
4. **Test thoroughly** before committing
5. **Update CHANGELOG.md** with your changes

---

**Last Updated**: Check CHANGELOG.md for latest modifications

