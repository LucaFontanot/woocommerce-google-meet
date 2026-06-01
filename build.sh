#!/bin/bash

PLUGIN_SLUG="woocommerce-meetings"
OUTPUT_DIR="./build"
ZIP_FILE="${OUTPUT_DIR}/${PLUGIN_SLUG}.zip"

mkdir -p "$OUTPUT_DIR"

rm -f "$ZIP_FILE"

echo "Building ${PLUGIN_SLUG} zip bundle..."

zip -r "$ZIP_FILE" . \
    --include \
        "woo-gmeet.php" \
        "uninstall.php" \
        "readme.txt" \
        "assets/*" \
        "includes/*" \
        "templates/*" \
        "vendor/*" \
    --exclude "*.git*"

echo "Done! Bundle created at: ${ZIP_FILE}"

