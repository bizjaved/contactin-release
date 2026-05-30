#!/usr/bin/env bash

################################################################################
# ContactIn - Distribution Export Script (plugin root)
#
# - Builds CSS assets first via ./build-css.php
# - Creates a clean distribution in /tmp/contactin
# - Produces a versionless ZIP: /tmp/contactin.zip
################################################################################

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR"
DIST_DIR="/tmp/contactin"
ZIP_PATH="/tmp/contactin.zip"

print_step() {
  echo "▶ $1"
}

print_ok() {
  echo "✓ $1"
}

print_err() {
  echo "✗ $1"
}

if [[ ! -f "$PLUGIN_DIR/contactin.php" ]]; then
  print_err "contactin.php not found in plugin root: $PLUGIN_DIR"
  exit 1
fi

if [[ ! -f "$PLUGIN_DIR/build-css.php" ]]; then
  print_err "build-css.php not found in plugin root."
  exit 1
fi

print_step "Building CSS assets..."
php "$PLUGIN_DIR/build-css.php"
print_ok "CSS build complete"

print_step "Preparing distribution directory..."
rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"
print_ok "Distribution directory ready"

print_step "Syncing production files..."
rsync -a --delete \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.vscode/' \
  --exclude='.idea/' \
  --exclude='node_modules/' \
  --exclude='tests/' \
  --exclude='test/' \
  --exclude='docs/' \
  --exclude='examples/' \
  --exclude='.distignore' \
  --exclude='.gitignore' \
  --exclude='.editorconfig' \
  --exclude='.phpcs.xml.dist' \
  --exclude='phpcs.xml' \
  --exclude='phpunit.xml' \
  --exclude='phpunit.xml.dist' \
  --exclude='composer.lock' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='yarn.lock' \
  --exclude='*.md' \
  --exclude='*.map' \
  --exclude='*.log' \
  --exclude='/build-css.php' \
  --exclude='/export-distribution.sh' \
  --exclude='assets/src/' \
  --exclude='assets/src/***' \
  --exclude='assets/website-icons/' \
  --exclude='dist/css/modules/' \
  --exclude='dist/css/vendor/select2.css.map' \
  --include='/vendor/' \
  --include='/vendor/autoload.php' \
  --include='/vendor/composer/***' \
  --include='/vendor/freemius/***' \
  --exclude='/vendor/***' \
  "$PLUGIN_DIR/" "$DIST_DIR/"
print_ok "Files synced"

print_step "Cleaning hidden files..."
find "$DIST_DIR" -maxdepth 1 -type f -name ".*" -delete 2>/dev/null || true
print_ok "Hidden files removed"

print_step "Creating ZIP package..."
rm -f "$ZIP_PATH"
(
  cd /tmp
  zip -r -q "$(basename "$ZIP_PATH")" "$(basename "$DIST_DIR")" \
    -x "*/.git/*" "*/.DS_Store" "*/Thumbs.db"
)
print_ok "ZIP created: $ZIP_PATH"

echo
echo "Distribution ready"
echo "- Folder: $DIST_DIR"
echo "- ZIP:    $ZIP_PATH"
