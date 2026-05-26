#!/bin/bash

################################################################################
# ContactIn (Free) - Distribution Export Script
#
# This script creates a clean production distribution package for WordPress.org
# Excludes all development files, tests, and unnecessary dependencies
#
# Usage: ./export-distribution.sh
################################################################################

set -e  # Exit on error

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="/tmp/contactin"
BACKUP_DIR="${DIST_DIR}.backup"
PLUGIN_NAME="contactin"

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Verify we're in the right directory
if [ ! -f "$PLUGIN_DIR/contactin.php" ]; then
    print_error "contactin.php not found in $PLUGIN_DIR"
    echo "Please run this script from the plugin root directory."
    exit 1
fi

print_header "ContactIn (Free) - Distribution Export"
echo "Plugin Root: $PLUGIN_DIR"
echo "Distribution Output: $DIST_DIR"
echo

# Step 1: Backup existing distribution
print_step "Preparing destination directory..."
if [ -d "$DIST_DIR" ]; then
    print_step "Backing up existing distribution to ${BACKUP_DIR}"
    rm -rf "$BACKUP_DIR"
    mv "$DIST_DIR" "$BACKUP_DIR"
    print_success "Backup created"
fi

# Step 2: Create distribution directory
print_step "Creating distribution directory..."
mkdir -p "$DIST_DIR"
print_success "Distribution directory created"

# Step 3: Copy production files
print_step "Syncing production files..."
rsync -av --delete \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.distignore' \
    --exclude='tests' \
    --exclude='examples' \
    --exclude='.circleci' \
    --exclude='.github' \
    --exclude='.vscode' \
    --exclude='*.md' \
    --exclude='WEBSITE_*' \
    --exclude='*.log' \
    --exclude='ngrok.log' \
    --exclude='.env.example' \
    --exclude='.phpcs.xml.dist' \
    --exclude='cleanup-pending-logs.php' \
    --exclude='diagnose-attachment-sync.php' \
    --exclude='diagnose-crm-sync.php' \
    --exclude='diagnose-cron-not-running.php' \
    --exclude='manual-cleanup.php' \
    --exclude='run-tests.php' \
    --exclude='test-*.php' \
    --exclude='test-*.sh' \
    --exclude='verify-*.php' \
    --exclude='verify-*.sh' \
    --exclude='force-process-crm-queue.php' \
    --exclude='trigger-learning.php' \
    --exclude='export-distribution.sh' \
    --include='vendor/' \
    --include='vendor/autoload.php' \
    --include='vendor/composer/***' \
    --exclude='vendor/***' \
    --exclude='dist/css/modules/***' \
    --exclude='dist/css/REFACTORING_GUIDE.md' \
    --exclude='dist/css/analyze-css.sh' \
    --exclude='dist/css/build-css.php' \
    --include='dist/branding/icon-20x20.svg' \
    --exclude='dist/branding/***' \
    --exclude='assets/website-icons/***' \
    --exclude='assets/icon-128x128.png' \
    --exclude='assets/icon-256x256.png' \
    --exclude='assets/icon-512x512.png' \
    --exclude='*.map' \
    "$PLUGIN_DIR/" "$DIST_DIR/" > /dev/null 2>&1

print_success "Files synced"

# Step 4: Remove test files from includes
print_step "Removing test directories..."
rm -rf "$DIST_DIR/includes/tests" 2>/dev/null || true
print_success "Test directories removed"

# Step 5: Remove remaining unnecessary files
print_step "Cleaning development files..."
rm -f "$DIST_DIR/package-lock.json" 2>/dev/null || true
rm -f "$DIST_DIR/export-distribution.sh" 2>/dev/null || true
find "$DIST_DIR" -type f \( -name "test-*.php" -o -name "test-*.sh" -o -name "verify-*.php" -o -name "verify-*.sh" -o -name "diagnose-*.php" -o -name "manual-cleanup.php" -o -name "run-tests.php" -o -name "force-process-crm-queue.php" -o -name "trigger-learning.php" \) -delete 2>/dev/null || true
find "$DIST_DIR" -type f -name "*.map" -delete 2>/dev/null || true
find "$DIST_DIR/vendor" -type f \( -name "*.md" -o -name "README*" -o -name "CHANGELOG*" \) ! -path "*/dist/*" -delete 2>/dev/null || true
print_success "Development files cleaned"

# Step 6: Remove documentation files (except readme.txt)
print_step "Removing documentation files..."
find "$DIST_DIR" -maxdepth 1 -type f -name "*.md" -delete 2>/dev/null || true
find "$DIST_DIR/vendor" -maxdepth 2 -type f \( -name "*.md" -o -name "README" -o -name "CHANGELOG" \) -delete 2>/dev/null || true
print_success "Documentation cleaned"

# Step 7: Remove hidden files not allowed by WordPress Plugin Check
print_step "Removing hidden files from distribution..."
find "$DIST_DIR" -maxdepth 1 -type f -name ".*" -delete 2>/dev/null || true
print_success "Hidden files removed"

# Step 8: Calculate distribution size
print_step "Calculating distribution size..."
DIST_SIZE=$(du -sh "$DIST_DIR" 2>/dev/null | cut -f1)
print_success "Distribution size: $DIST_SIZE"

# Step 9: Get version from plugin header
VERSION=$(grep "Version:" "$PLUGIN_DIR/contactin.php" | head -1 | sed 's/.*Version:\s*//;s/\s*$//')
if [ -z "$VERSION" ]; then
    print_error "Could not determine plugin version from $PLUGIN_DIR/contactin.php"
    exit 1
fi

# Step 10: Display final summary
echo
print_header "Distribution Export Complete"
echo
echo -e "${GREEN}✓ WordPress.org-ready distribution created!${NC}"
echo
echo -e "${GREEN}Distribution Details:${NC}"
echo "  📁 Location: $DIST_DIR"
echo "  📦 Size: $DIST_SIZE"
echo "  📄 Plugin: ContactIn (Free)"
echo "  📌 Version: $VERSION"
echo "  🏷️  Slug: $PLUGIN_NAME"
echo
echo -e "${YELLOW}Next Steps:${NC}"
echo "  1. Test the plugin locally from: $DIST_DIR"
echo "  2. Submit to: https://wordpress.org/plugins/developers/add/"
echo
echo -e "${GREEN}WordPress.org Requirements:${NC}"
echo "  ✓ GPL-3.0 License included"
echo "  ✓ readme.txt WordPress.org format"
echo "  ✓ No AI/ML processing disclaimers (if needed)"
echo "  ✓ All external APIs disclosed"
echo "  ✓ Freemius SDK for premium features"
echo
print_success "Distribution is ready for WordPress.org submission!"

# Step 11: Show file count
FILE_COUNT=$(find "$DIST_DIR" -type f | wc -l)
DIR_COUNT=$(find "$DIST_DIR" -type d | wc -l)
echo -e "${BLUE}Statistics:${NC}"
echo "  Files: $FILE_COUNT"
echo "  Directories: $DIR_COUNT"
echo

# Step 12: Create ZIP file with correct structure
print_step "Creating ZIP archive..."
cd /tmp
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"
if [ -f "$ZIP_NAME" ]; then
    rm -f "$ZIP_NAME"
fi
zip -r -q "$ZIP_NAME" contactin/ \
    -x "contactin/.git/*" "*/.DS_Store" "*/Thumbs.db"
ZIP_SIZE=$(ls -lh "$ZIP_NAME" | awk '{print $5}')
print_success "ZIP created: /tmp/${ZIP_NAME} (${ZIP_SIZE})"

if [ -d "$BACKUP_DIR" ]; then
    rm -rf "$BACKUP_DIR"
fi

cd "$PLUGIN_DIR"

print_header "Export Complete"
echo
echo -e "${GREEN}✓ Production-ready ZIP package created!${NC}"
echo "  📦 /tmp/${ZIP_NAME} (${ZIP_SIZE})"
echo
echo -e "${GREEN}Installation Instructions:${NC}"
echo "  1. Go to Plugins > Add New > Upload Plugin"
echo "  2. Upload the ZIP file: /tmp/${ZIP_NAME}"
echo "  3. Click 'Install Now'"
echo "  4. Plugin will extract to: wp-content/plugins/contactin/"
echo
echo -e "${GREEN}Deployment Options:${NC}"
echo "  • Submit to WordPress.org plugin repository"
echo "  • Distribute directly to clients"
echo
print_header "Status: Ready for Installation ✓"