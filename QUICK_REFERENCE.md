# Quick Reference Card

## 🚦 Before Making Changes

1. ✅ Read `CHANGELOG.md` - See what changed recently
2. ✅ Read `CODE_STRUCTURE.md` - Understand the code
3. ✅ Backup your files - Copy entire plugin folder

## 📝 Making Changes

1. Make your changes
2. Test thoroughly
3. **IMMEDIATELY** update `CHANGELOG.md` with:
   - What you changed
   - Which file(s)
   - Which IDE you used
   - Date

## 🔄 Switching IDEs

1. ✅ Update `CHANGELOG.md` with your changes
2. ✅ Backup/copy plugin folder
3. ✅ In new IDE: Read `CHANGELOG.md` first
4. ✅ Compare files if needed
5. ✅ Test after merging

## 🆘 Something Broke?

1. Check `CHANGELOG.md` for recent changes
2. Restore from backup
3. Compare files side-by-side
4. Test incrementally

## 📍 Key Files

| File | Purpose | Lines to Watch |
|------|---------|----------------|
| `admin-settings.php` | Admin UI | ~787 (live preview) |
| `shortcodes.php` | Frontend | ~18 (shortcode) |
| `dearcharts.js` | Frontend JS | ~19 (init) |

## 🔍 Quick Commands

```bash
# See what changed (if using git)
git log --oneline -10
git diff

# Search for function
grep -r "function_name" .

# Find all changes in CHANGELOG
grep -i "your_ide_name" CHANGELOG.md
```

## ⚠️ High-Risk Functions

- `dearcharts_update_live_preview()` - Line ~787
- `dearcharts_save_meta_box_data()` - Line ~1358
- `dearcharts_ajax_save_chart()` - Line ~1398

**Always check CHANGELOG before modifying these!**

