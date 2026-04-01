#!/bin/zsh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR"
PARENT_DIR="$(dirname "$PLUGIN_DIR")"
PLUGIN_SLUG="dynamic-alt-tags"
MAIN_PLUGIN_FILE="$PLUGIN_DIR/dynamic-alt-tags.php"

VERSION="$(perl -ne 'print $1 if /Version:\s+([0-9.]+)/' "$MAIN_PLUGIN_FILE")"

if [[ -z "$VERSION" ]]; then
  echo "Unable to determine plugin version from $MAIN_PLUGIN_FILE" >&2
  exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

cd "$PARENT_DIR"
rm -f "$ZIP_NAME"

zip -r "$ZIP_NAME" "$PLUGIN_SLUG" \
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

echo "Built: $PARENT_DIR/$ZIP_NAME"
