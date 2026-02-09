#!/bin/bash

# CSS Modular Extraction Tool
# Helps extract and organize CSS from admin-global.min.css into modules

set -e

CSS_FILE="admin-global.min.css"
MODULES_DIR="modules"

if [ ! -f "$CSS_FILE" ]; then
    echo "Error: $CSS_FILE not found in current directory"
    exit 1
fi

if [ ! -d "$MODULES_DIR" ]; then
    echo "Error: $MODULES_DIR directory not found"
    exit 1
fi

echo "==================================="
echo "CSS Modular Extraction Tool"
echo "==================================="
echo ""

# 1. Check current file statistics
echo "[1] Current File Statistics:"
echo "  Lines: $(wc -l < $CSS_FILE)"
echo "  Size: $(du -h $CSS_FILE | cut -f1)"
echo ""

# 2. Check for duplicate selectors
echo "[2] Checking for Duplicate Selectors:"
grep -o '^\.[a-zA-Z0-9_-]*' "$CSS_FILE" | sort | uniq -d | wc -l > /tmp/dup_count.txt
DUP_COUNT=$(cat /tmp/dup_count.txt)
echo "  Found $DUP_COUNT duplicate selectors"
if [ "$DUP_COUNT" -gt 0 ]; then
    echo "  Run: grep -o '^\.[a-zA-Z0-9_-]*' $CSS_FILE | sort | uniq -d | head -20"
fi
echo ""

# 3. Count lines in each module
echo "[3] Module Sizes:"
for module in $MODULES_DIR/??-*.min.css; do
    if [ -f "$module" ]; then
        lines=$(wc -l < "$module")
        size=$(du -h "$module" | cut -f1)
        echo "  $(basename $module): $lines lines ($size)"
    fi
done
echo ""

# 4. Check for CSS sections
echo "[4] Major CSS Sections (by line):"
grep -n '^/\*.*=.*\*/$' "$CSS_FILE" | head -20
echo ""

# 5. Validate modules exist
echo "[5] Required Modules Status:"
required_modules=(
    "01-variables.min.css"
    "02-base.min.css"
    "03-layout.min.css"
    "04-header.min.css"
    "05-controls.min.css"
    "06-tables.min.css"
    "07-badges.min.css"
    "08-modals.min.css"
    "09-pages.min.css"
    "10-responsive.min.css"
)

missing=0
for module in "${required_modules[@]}"; do
    if [ -f "$MODULES_DIR/$module" ]; then
        echo "  ✓ $module exists"
    else
        echo "  ✗ $module MISSING"
        missing=$((missing + 1))
    fi
done
echo "  Total: $((10 - missing))/10 modules present"
echo ""

# 6. Build output file
echo "[6] Building admin-global.min.css..."
if bash build-css.sh > /tmp/build.log 2>&1; then
    echo "  ✓ Build successful"
    # Show new stats
    echo ""
    echo "[7] New File Statistics:"
    echo "  Lines: $(wc -l < $CSS_FILE)"
    echo "  Size: $(du -h $CSS_FILE | cut -f1)"
else
    echo "  ✗ Build failed. See /tmp/build.log:"
    cat /tmp/build.log
    exit 1
fi

echo ""
echo "==================================="
echo "Extraction Complete!"
echo "==================================="
