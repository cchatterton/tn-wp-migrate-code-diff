#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="tn-code-release-installer"
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE_DIR="$PROJECT_DIR/release-installer/$PLUGIN_SLUG"
WORK_DIR="$(mktemp -d)"
PACKAGE_DIR="$WORK_DIR/$PLUGIN_SLUG"
OUTPUT_DIR="$PROJECT_DIR/dist"

trap 'rm -rf "$WORK_DIR"' EXIT

mkdir -p "$OUTPUT_DIR" "$PACKAGE_DIR"
rm -f "$OUTPUT_DIR/$PLUGIN_SLUG.zip" "$PROJECT_DIR/$PLUGIN_SLUG.zip"

cp -R "$SOURCE_DIR/functions" "$PACKAGE_DIR/functions"
cp -R "$SOURCE_DIR/styles" "$PACKAGE_DIR/styles"
cp -R "$SOURCE_DIR/templates" "$PACKAGE_DIR/templates"
cp "$SOURCE_DIR/$PLUGIN_SLUG.php" "$PACKAGE_DIR/$PLUGIN_SLUG.php"
cp "$SOURCE_DIR/readme.md" "$PACKAGE_DIR/readme.md"
cp "$SOURCE_DIR/CHANGELOG.md" "$PACKAGE_DIR/CHANGELOG.md"

find "$PACKAGE_DIR" -name '.DS_Store' -delete

(
    cd "$WORK_DIR"
    zip -qr "$OUTPUT_DIR/$PLUGIN_SLUG.zip" "$PLUGIN_SLUG"
)

cp "$OUTPUT_DIR/$PLUGIN_SLUG.zip" "$PROJECT_DIR/$PLUGIN_SLUG.zip"
