#!/bin/zsh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR"
PARENT_DIR="$(dirname "$PLUGIN_DIR")"
OUTPUT_DIR="/Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/plugin-updates"
INFO_JSON_SOURCE="/Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/dynamic-alt-tags-config/info.json"
INFO_JSON_DEST="${OUTPUT_DIR}/info.json"
PLUGIN_SLUG="dynamic-alt-tags"
MAIN_PLUGIN_FILE="$PLUGIN_DIR/dynamic-alt-tags.php"

VERSION="$(perl -ne 'print $1 if /Version:\s+([0-9.]+)/' "$MAIN_PLUGIN_FILE")"

if [[ -z "$VERSION" ]]; then
  echo "Unable to determine plugin version from $MAIN_PLUGIN_FILE" >&2
  exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${OUTPUT_DIR}/${ZIP_NAME}"

mkdir -p "$OUTPUT_DIR"
cd "$PARENT_DIR"
rm -f "$ZIP_PATH"

zip -r "$ZIP_PATH" "$PLUGIN_SLUG" \
  -x "$PLUGIN_SLUG/.git/*" \
  -x "$PLUGIN_SLUG/.github/*" \
  -x "$PLUGIN_SLUG/vendor/*" \
  -x "$PLUGIN_SLUG/docs/*" \
  -x "$PLUGIN_SLUG/CODE_OF_CONDUCT.md" \
  -x "$PLUGIN_SLUG/CONTRIBUTING.md" \
  -x "$PLUGIN_SLUG/SECURITY.md" \
  -x "$PLUGIN_SLUG/README.md" \
  -x "$PLUGIN_SLUG/composer.json" \
  -x "$PLUGIN_SLUG/composer.lock" \
  -x "$PLUGIN_SLUG/phpcs.xml.dist" \
  -x "$PLUGIN_SLUG/.gitignore" \
  -x "$PLUGIN_SLUG/.DS_Store" \
  -x "$PLUGIN_SLUG/._*" \
  -x "$PLUGIN_SLUG/__MACOSX/*" \
  -x "$PLUGIN_SLUG/build-release.sh" \
  -x "$PLUGIN_SLUG/**/.DS_Store" \
  -x "$PLUGIN_SLUG/**/._*" \
  -x "$PLUGIN_SLUG/**/__MACOSX/*"

cp "$INFO_JSON_SOURCE" "$INFO_JSON_DEST"

echo "Built: $ZIP_PATH"
echo "Copied: $INFO_JSON_DEST"
