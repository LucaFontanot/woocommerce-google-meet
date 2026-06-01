#!/bin/bash
set -e

PLUGIN_SLUG="woocommerce-meetings"
BUILD_DIR="build/$PLUGIN_SLUG"

# Pulizia build precedente
echo "Pulizia cartella build..."
rm -rf build
mkdir -p "$BUILD_DIR"

# Copia i file necessari
cp woo-gmeet.php "$BUILD_DIR"/
cp -r includes "$BUILD_DIR"/
cp -r assets "$BUILD_DIR"/
cp -r vendor "$BUILD_DIR"/

# Copia file di licenza e readme se esistono
[ -f LICENSE ] && cp LICENSE "$BUILD_DIR"/
[ -f README.md ] && cp README.md "$BUILD_DIR"/

# Crea lo zip
cd build
zip -r "${PLUGIN_SLUG}.zip" "$PLUGIN_SLUG"
cd ..

echo "Build completata. Trovi lo zip in build/${PLUGIN_SLUG}.zip"

