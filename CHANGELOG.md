# DearCharts Plugin - Changelog

This file tracks all changes made to the plugin. Update this file whenever you make changes in any IDE.

## How to Use This Changelog

1. **Before making changes**: Check this file to see what was last changed
2. **After making changes**: Add your changes here with date and description
3. **Before merging**: Review all entries to understand what changed

---

## [Unreleased] - Current Development

### Fixed - 2024
- **Live Preview Canvas Error**: Fixed "Canvas is already in use" error
  - Added concurrent call prevention flag (`dc_is_updating_preview`)
  - Improved chart destruction logic with multiple cleanup methods
  - Added cleanup delays to ensure Chart.js fully releases canvas
  - Double-check canvas availability before creating new charts
  - File: `includes/admin-settings.php` (lines ~819-850)

- **Live Preview Improvements**: Enhanced live preview functionality
  - Added Chart.js loading detection with retry mechanism
  - Improved empty data validation
  - Better error handling and user feedback
  - Fixed canvas dimension initialization
  - Improved manual data parsing with proper column indexing
  - File: `includes/admin-settings.php` (function: `dearcharts_update_live_preview`)

---

## Template for New Entries

Copy this template when adding new changes:

```markdown
### [Type] - YYYY-MM-DD
- **Feature/Fix Name**: Brief description
  - Detailed change 1
  - Detailed change 2
  - Files modified: `path/to/file.php` (lines or function names)
  - IDE/Environment: [Which IDE you used]
```

### Types:
- `Added`: New features
- `Changed`: Changes to existing functionality
- `Fixed`: Bug fixes
- `Removed`: Removed features
- `Security`: Security updates

---

## Notes

- Always include the file path and approximate line numbers or function names
- Note which IDE/environment you used (VS Code, PhpStorm, etc.)
- If you're unsure about a change, mark it with `[UNVERIFIED]`

