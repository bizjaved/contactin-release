# CSS Module Naming Convention

## Why `.min.css` without minification?

All CSS module files use the `.min.css` extension even though the content is **NOT minified** (human-readable).

### Reasoning:
- **Consistent naming** throughout development
- **No rename needed** when we actually minify for production release
- **Future-proof** - files already named for production
- **Clear purpose** - signals these files will be minified eventually

### Current State:
- Files named: `*.min.css` ✓
- Content: **NOT minified** (readable, formatted, commented)
- Purpose: Development and maintenance

### Future Release:
- Files named: `*.min.css` (no change needed)
- Content: **WILL BE minified** (compressed, whitespace removed)
- Purpose: Production deployment

### Workflow Remains the Same:
1. Edit module file (e.g., `05-controls.min.css`)
2. Run `./build-css.sh`
3. Test changes
4. Commit both module + generated file

**When releasing**: Run actual CSS minification on all `*.min.css` files, keeping the same filenames.

---
**Date**: January 30, 2026
**Note**: This naming convention was established during the CSS refactoring project.
