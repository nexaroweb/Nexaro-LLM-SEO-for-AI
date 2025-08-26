#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$ROOT_DIR"
PLUGIN_SLUG="nexaro-llms"
VERSION="1.0.1"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

# PHP lint
find "$PLUGIN_DIR" -type f -name "*.php" -print0 | while IFS= read -r -d '' f; do
	php -l "$f" >/dev/null
	echo "Linted: $f"
done

echo "Attempting WP-CLI make-pot (optional)"
if command -v wp >/dev/null 2>&1; then
	wp i18n make-pot "$PLUGIN_DIR" "$PLUGIN_DIR/languages/nexaro-llms.pot" --domain=nexaro-llms || true
fi

echo "Attempting PHPCS (optional)"
if command -v phpcs >/dev/null 2>&1; then
	phpcs -p --standard=WordPress --ignore=vendor "$PLUGIN_DIR" || true
fi

cd "$ROOT_DIR/.."
rm -f "$ZIP_NAME"
zip -r "$ZIP_NAME" "$PLUGIN_SLUG" -x "*/.git/*" "*/node_modules/*" "*/vendor/*"
echo "Built: $ZIP_NAME"