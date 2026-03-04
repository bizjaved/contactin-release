#!/bin/bash

################################################################################
# Contact Inbox - Free Distribution Export Script
#
# Creates a free-version package from the current codebase in /tmp/contact-inbox
# and produces /tmp/contact-inbox.zip for WordPress.org/free distribution.
#
# Usage: ./export-free-distribution.sh
################################################################################

set -e

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="/tmp/contact-inbox"
ZIP_FILE="/tmp/contact-inbox.zip"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

print_step() {
    echo -e "${YELLOW}▶ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

if [ ! -f "$PLUGIN_DIR/contact-inbox.php" ]; then
    print_error "contact-inbox.php not found in $PLUGIN_DIR"
    exit 1
fi

print_header "Contact Inbox - Free Distribution Export"
echo "Plugin Root: $PLUGIN_DIR"
echo "Distribution Output: $DIST_DIR"
echo

print_step "Preparing destination directory..."
if [ -d "$DIST_DIR" ]; then
    rm -rf "$DIST_DIR"
fi
mkdir -p "$DIST_DIR"
print_success "Destination ready"

print_step "Syncing production files..."
rsync -av --delete \
    --exclude='.git' \
    --exclude='tests' \
    --exclude='examples' \
    --exclude='.circleci' \
    --exclude='.github' \
    --exclude='.vscode' \
    --exclude='*.md' \
    --exclude='WEBSITE_DOCUMENTATION*.html' \
    --exclude='*.log' \
    --exclude='.env.example' \
    --exclude='.phpcs.xml.dist' \
    --exclude='cleanup-pending-logs.php' \
    --exclude='diagnose-attachment-sync.php' \
    --exclude='manual-cleanup.php' \
    --exclude='run-tests.php' \
    --exclude='test-classifier-learning.php' \
    --exclude='test-intent-classifier.php' \
    --exclude='test-learning-direct.php' \
    --exclude='test-phone-sync.sh' \
    --exclude='test-phone-utils.php' \
    --exclude='test-spam-blocked-card.php' \
    --exclude='trigger-learning.php' \
    --exclude='verify-attachment-sync.php' \
    --exclude='verify-pro-version.php' \
    --exclude='vendor/phpcs*' \
    --exclude='vendor/dealerdirect' \
    --exclude='vendor/phpcompatibility' \
    --exclude='vendor/wpcsstandards' \
    --exclude='vendor/squizlabs' \
    --exclude='vendor/wp-coding-standards' \
    --exclude='vendor/composer/installed.json' \
    --exclude='vendor/bin' \
    --exclude='*.map' \
    "$PLUGIN_DIR/" "$DIST_DIR/" > /dev/null 2>&1
print_success "Files synced"

print_step "Removing internal test/dev directories..."
rm -rf "$DIST_DIR/includes/tests" 2>/dev/null || true
rm -f "$DIST_DIR/package-lock.json" 2>/dev/null || true
find "$DIST_DIR" -type f -name "*.map" -delete 2>/dev/null || true
find "$DIST_DIR/vendor" -type f \( -name "*.md" -o -name "README*" -o -name "CHANGELOG*" -o -name "LICENSE*" \) ! -path "*/dist/*" -delete 2>/dev/null || true
print_success "Cleanup complete"

print_step "Applying free-version metadata transforms..."

# Main plugin header branding
sed -i "s/^\( \* Plugin Name:[[:space:]]*\).*/\1Contact Inbox/" "$DIST_DIR/contact-inbox.php"
sed -i "s/^\( \* Text Domain:[[:space:]]*\).*/\1contact-inbox/" "$DIST_DIR/contact-inbox.php"

# Keep URL references coherent for free package
sed -i "s#https://github.com/bizjaved/contact-inbox-pro#https://github.com/bizjaved/contact-inbox#g" "$DIST_DIR/contact-inbox.php"

# readme title for free package
if [ -f "$DIST_DIR/readme.txt" ]; then
    sed -i "1s/^=== .* ===$/=== Contact Inbox ===/" "$DIST_DIR/readme.txt"
    sed -i "s#^Plugin URI: .*#Plugin URI: https://github.com/bizjaved/contact-inbox#" "$DIST_DIR/readme.txt"
    sed -i "s/Contact Inbox Pro turns/Contact Inbox turns/g" "$DIST_DIR/readme.txt"
fi

print_success "Metadata updated for free package"

print_step "Building ZIP archive..."
if [ -f "$ZIP_FILE" ]; then
    rm -f "$ZIP_FILE"
fi
cd /tmp
zip -r -q "$(basename "$ZIP_FILE")" "$(basename "$DIST_DIR")" -x "*/.DS_Store" "*/Thumbs.db"
cd "$PLUGIN_DIR"
print_success "ZIP created: $ZIP_FILE"

DIST_SIZE=$(du -sh "$DIST_DIR" 2>/dev/null | cut -f1)
ZIP_SIZE=$(ls -lh "$ZIP_FILE" | awk '{print $5}')

echo
print_header "Free Export Complete"
echo -e "${GREEN}✓ Directory:${NC} $DIST_DIR"
echo -e "${GREEN}✓ Archive:${NC}   $ZIP_FILE (${ZIP_SIZE})"
echo -e "${GREEN}✓ Size:${NC}      $DIST_SIZE"
echo
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Validate in a clean WordPress install"
echo "  2. Confirm plugin slug path is: wp-content/plugins/contact-inbox/"
echo "  3. Upload free ZIP to your distribution channel"
echo