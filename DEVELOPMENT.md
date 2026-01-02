# DearCharts Development Guide

## Working Across Multiple IDEs

This guide helps you manage code changes when working in different IDEs/environments.

## 🚨 Before You Start

### 1. Check Current State
- Read `CHANGELOG.md` to see what was last changed
- Check `CODE_STRUCTURE.md` to understand the codebase
- Review recent git commits (if using version control)

### 2. Document Your Changes
- Update `CHANGELOG.md` immediately after making changes
- Note which IDE you're using
- Include file paths and function names

## 📝 Workflow for Multiple IDEs

### Option A: Using Git (Recommended)

```bash
# Before starting work in any IDE:
git pull origin main          # Get latest changes
git status                    # Check for uncommitted changes

# Make your changes...

# After making changes:
git add .
git commit -m "Description of changes"
git push origin main

# Before switching to another IDE:
git pull origin main          # Always pull before switching
```

### Option B: Manual Sync (No Git)

1. **Before switching IDEs:**
   - Update `CHANGELOG.md` with your changes
   - Copy entire plugin folder to a backup location
   - Note which files you modified

2. **When starting in new IDE:**
   - Read `CHANGELOG.md` to see what changed
   - Compare your backup with current files
   - Manually merge any conflicting changes

3. **After merging:**
   - Test the plugin thoroughly
   - Update `CHANGELOG.md` with merge notes

## 🗂️ File Organization

### Core Files (Modify Carefully)
- `dearcharts.php` - Main plugin file
- `includes/admin-settings.php` - Admin interface (1,400+ lines)
- `includes/shortcodes.php` - Frontend rendering
- `assets/js/dearcharts.js` - Frontend JavaScript

### Key Functions to Know

#### Admin Settings (`includes/admin-settings.php`)
- `dearcharts_update_live_preview()` - Live preview function (line ~787)
- `dearcharts_save_meta_box_data()` - Save handler (line ~1358)
- `dearcharts_ajax_save_chart()` - AJAX save (line ~1398)

#### Shortcodes (`includes/shortcodes.php`)
- `dearcharts_render_shortcode()` - Shortcode handler (line ~18)

#### Frontend JS (`assets/js/dearcharts.js`)
- `dearcharts_init_frontend()` - Chart initialization (line ~19)

## 🔍 Finding What Changed

### Search for Recent Changes
```bash
# If using git:
git log --oneline --since="1 week ago"
git diff HEAD~1

# Search for TODO/FIXME comments:
grep -r "TODO\|FIXME" .
```

### Check Changelog
- Open `CHANGELOG.md`
- Look for entries with your IDE name
- Check dates to see recent changes

## ⚠️ Common Merge Conflicts

### 1. Live Preview Function
- **Location**: `includes/admin-settings.php` ~line 787
- **Why**: Frequently modified, complex function
- **Solution**: Read CHANGELOG, understand the logic, merge carefully

### 2. JavaScript Variables
- **Location**: `includes/admin-settings.php` ~line 583
- **Why**: Global variables can conflict
- **Solution**: Check variable names, don't duplicate

### 3. CSS Styles
- **Location**: `includes/admin-settings.php` ~line 84
- **Why**: Styles often tweaked
- **Solution**: Merge style blocks, test visually

## 🛠️ Best Practices

### 1. One Feature Per Session
- Don't mix multiple features in one session
- Complete one feature, commit, then move to next

### 2. Use Descriptive Commit Messages
```
Good: "Fixed live preview canvas error with concurrent call prevention"
Bad: "Fixed bug"
```

### 3. Test Before Switching IDEs
- Always test your changes
- Note any issues in CHANGELOG

### 4. Keep Backups
- Before major changes, backup the entire plugin folder
- Date your backups: `dearcharts-backup-2024-01-15`

## 📋 Checklist Before Merging

- [ ] Read `CHANGELOG.md` for recent changes
- [ ] Test all functionality (admin, frontend, save)
- [ ] Check for console errors
- [ ] Verify live preview works
- [ ] Test shortcode rendering
- [ ] Update `CHANGELOG.md` with your changes
- [ ] Note which IDE you used
- [ ] Include file paths and function names

## 🆘 If Something Breaks

1. **Don't Panic** - Check `CHANGELOG.md` first
2. **Restore Backup** - Use your most recent backup
3. **Compare Files** - Use diff tool to see what changed
4. **Test Incrementally** - Restore one file at a time
5. **Document the Issue** - Add to CHANGELOG with `[BROKEN]` tag

## 💡 Tips

- **Use feature branches** if using git: `git checkout -b feature-name`
- **Comment your code** - Future you will thank you
- **Keep functions small** - Easier to merge
- **Use consistent formatting** - Reduces merge conflicts
- **Test in staging** before production

## 📞 Quick Reference

| Task | Command/File |
|------|-------------|
| See what changed | `CHANGELOG.md` |
| Understand structure | `CODE_STRUCTURE.md` |
| Find a function | `grep -r "function_name" .` |
| Check git status | `git status` |
| View recent commits | `git log --oneline -10` |

