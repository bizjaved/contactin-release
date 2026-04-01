#!/bin/bash

################################################################################
# ContactIn - Distribution Export Script
#
# Creates a clean production distribution in /tmp/contactin.
# Excludes development files and verifies required runtime assets.
#
# Usage: ./export-distribution.sh [--dist-only|--no-zip]
################################################################################

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="/tmp/contactin"
PLUGIN_NAME="contactin"
CREATE_ZIP=true

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

require_command() {
	if ! command -v "$1" >/dev/null 2>&1; then
		print_error "Required command not found: $1"
		exit 1
	fi
}

for arg in "$@"; do
	case "$arg" in
		--dist-only|--no-zip)
			CREATE_ZIP=false
			;;
		--help|-h)
			echo "Usage: ./export-distribution.sh [--dist-only|--no-zip]"
			echo "  --dist-only, --no-zip   Prepare /tmp/contactin only; skip ZIP creation"
			exit 0
			;;
	esac
done

if [ ! -f "$PLUGIN_DIR/contactin.php" ]; then
	print_error "contactin.php not found in $PLUGIN_DIR"
	echo "Please run this script from the plugin root directory."
	exit 1
fi

require_command rsync
require_command zip
require_command unzip
require_command find
require_command du

print_header "ContactIn - Distribution Export"
echo "Plugin Root: $PLUGIN_DIR"
echo "Distribution Output: $DIST_DIR"
if [ "$CREATE_ZIP" = false ]; then
	echo "ZIP Output: disabled (--dist-only)"
fi
echo

print_step "Preparing destination directory..."
if [ -d "$DIST_DIR" ]; then
	print_step "Backing up existing distribution to ${DIST_DIR}.backup"
	rm -rf "${DIST_DIR}.backup"
	mv "$DIST_DIR" "${DIST_DIR}.backup"
	print_success "Backup created"
fi

print_step "Creating distribution directory..."
mkdir -p "$DIST_DIR"
print_success "Distribution directory created"

print_step "Syncing production files..."
rsync -av --delete \
	--exclude='.git' \
	--exclude='.gitignore' \
	--exclude='.distignore' \
	--exclude='.github' \
	--exclude='.circleci' \
	--exclude='.vscode' \
	--exclude='tests' \
	--exclude='examples' \
	--exclude='scripts/' \
	--exclude='*.md' \
	--exclude='*.log' \
	--exclude='*.bak' \
	--exclude='*.backup' \
	--exclude='*.sh' \
	--exclude='.env.example' \
	--exclude='phpcs.xml.dist' \
	--exclude='.phpcs.xml.dist' \
	--exclude='vendor/bin' \
	--exclude='vendor/phpcs*' \
	--exclude='vendor/dealerdirect' \
	--exclude='vendor/phpcompatibility' \
	--exclude='vendor/wpcsstandards' \
	--exclude='vendor/squizlabs' \
	--exclude='vendor/wp-coding-standards' \
	--exclude='vendor/composer/installed.json' \
	--exclude='*.map' \
	"$PLUGIN_DIR/" "$DIST_DIR/" > /dev/null 2>&1
print_success "Files synced"

print_step "Removing test directories..."
rm -rf "$DIST_DIR/includes/tests" 2>/dev/null || true
print_success "Test directories removed"

print_step "Cleaning development files..."
rm -f "$DIST_DIR/package-lock.json" 2>/dev/null || true
find "$DIST_DIR" -type f \( -name "test-*.php" -o -name "test-*.sh" -o -name "verify-*.php" -o -name "verify-*.sh" -o -name "diagnose-*.php" -o -name "manual-cleanup.php" -o -name "run-tests.php" \) -delete 2>/dev/null || true
find "$DIST_DIR" -type f -name "*.map" -delete 2>/dev/null || true
find "$DIST_DIR/vendor" -type f \( -name "*.md" -o -name "README*" -o -name "CHANGELOG*" \) ! -path "*/dist/*" -delete 2>/dev/null || true
print_success "Development files cleaned"

print_step "Verifying required plugin runtime files..."
REQUIRED_FILES=(
	"contactin.php"
	"readme.txt"
	"LICENSE"
	"includes/freemius-bootstrap.php"
	"includes/Integration/FreemiusIntegration.php"
	"vendor/autoload.php"
	"vendor/freemius/wordpress-sdk/start.php"
)

for required_file in "${REQUIRED_FILES[@]}"; do
	if [ ! -f "$DIST_DIR/$required_file" ]; then
		print_error "Missing required file in distribution: $required_file"
		exit 1
	fi
done

REQUIRED_DIRS=(
	"includes"
	"templates"
	"assets"
	"languages"
	"vendor"
)

for required_dir in "${REQUIRED_DIRS[@]}"; do
	if [ ! -d "$DIST_DIR/$required_dir" ]; then
		print_error "Missing required directory in distribution: $required_dir"
		exit 1
	fi
done

print_success "Required runtime files verified"

print_step "Calculating distribution size..."
DIST_SIZE=$(du -sh "$DIST_DIR" 2>/dev/null | cut -f1)
print_success "Distribution size: $DIST_SIZE"

echo
print_header "Distribution Export Complete"
echo -e "${GREEN}✓ Production-ready distribution created in:${NC}"
echo "  📁 $DIST_DIR"
echo

FILE_COUNT=$(find "$DIST_DIR" -type f | wc -l)
DIR_COUNT=$(find "$DIST_DIR" -type d | wc -l)
echo -e "${BLUE}Statistics:${NC}"
echo "  Files: $FILE_COUNT"
echo "  Directories: $DIR_COUNT"
echo

if [ "$CREATE_ZIP" = false ]; then
	print_header "Export Complete"
	echo -e "${GREEN}✓ Distribution prepared (ZIP skipped).${NC}"
	echo "  📁 $DIST_DIR"
	print_header "Status: Distribution Ready ✓"
	exit 0
fi

print_step "Creating ZIP archive..."
cd /tmp
if [ -f "${PLUGIN_NAME}.zip" ]; then
	rm -f "${PLUGIN_NAME}.zip"
fi
zip -r -q "${PLUGIN_NAME}.zip" "${PLUGIN_NAME}/" \
	-x "${PLUGIN_NAME}/.git/*" "*/.DS_Store" "*/Thumbs.db"
ZIP_SIZE=$(ls -lh "${PLUGIN_NAME}.zip" | awk '{print $5}')
print_success "ZIP created: /tmp/${PLUGIN_NAME}.zip (${ZIP_SIZE})"

print_step "Verifying ZIP integrity..."
unzip -t "/tmp/${PLUGIN_NAME}.zip" >/dev/null

zip_entries="$(unzip -Z1 "/tmp/${PLUGIN_NAME}.zip")"
if ! grep -Fxq "${PLUGIN_NAME}/contactin.php" <<< "$zip_entries"; then
	print_error "ZIP structure is invalid. Expected ${PLUGIN_NAME}/contactin.php at root."
	exit 1
fi
print_success "ZIP integrity and structure verified"

cd "$PLUGIN_DIR"

print_header "Export Complete"
echo -e "${GREEN}✓ Production-ready ZIP package created!${NC}"
echo "  📦 /tmp/${PLUGIN_NAME}.zip (${ZIP_SIZE})"
print_header "Status: Ready for Installation ✓"
