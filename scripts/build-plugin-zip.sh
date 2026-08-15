#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="tn-wp-migrate-code-diff"
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
WORK_DIR="$(mktemp -d)"
PACKAGE_DIR="$WORK_DIR/$PLUGIN_SLUG"
OUTPUT_DIR="$PROJECT_DIR/dist"

trap 'rm -rf "$WORK_DIR"' EXIT

mkdir -p "$OUTPUT_DIR" "$PACKAGE_DIR"
rm -f "$OUTPUT_DIR/$PLUGIN_SLUG.zip"

cp -R "$PROJECT_DIR/functions" "$PACKAGE_DIR/functions"
cp -R "$PROJECT_DIR/scripts" "$PACKAGE_DIR/scripts"
cp -R "$PROJECT_DIR/styles" "$PACKAGE_DIR/styles"
cp -R "$PROJECT_DIR/templates" "$PACKAGE_DIR/templates"
cp "$PROJECT_DIR/$PLUGIN_SLUG.php" "$PACKAGE_DIR/$PLUGIN_SLUG.php"
cp "$PROJECT_DIR/readme.md" "$PACKAGE_DIR/readme.md"
cp "$PROJECT_DIR/CHANGELOG.md" "$PACKAGE_DIR/CHANGELOG.md"

find "$PACKAGE_DIR" -name '.DS_Store' -delete
rm -f "$PACKAGE_DIR/scripts/build-plugin-zip.sh"

(
    cd "$WORK_DIR"
    zip -qr "$OUTPUT_DIR/$PLUGIN_SLUG.zip" "$PLUGIN_SLUG"
)

cp "$OUTPUT_DIR/$PLUGIN_SLUG.zip" "$PROJECT_DIR/$PLUGIN_SLUG.zip"
