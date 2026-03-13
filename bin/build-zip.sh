#!/bin/bash
# Script must be run from module root: ./vendor/bin/module-build-zip.sh

# Detect module root (find composer.json)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULE_DIR="$SCRIPT_DIR"

while [[ ! -f "$MODULE_DIR/composer.json" ]] && [[ "$MODULE_DIR" != "/" ]]; do
  MODULE_DIR="$(dirname "$MODULE_DIR")"
done

if [[ ! -f "$MODULE_DIR/composer.json" ]]; then
  echo "❌ Unable to find project root (composer.json not found)" >&2
  exit 1
fi

MODULE_NAME="$(basename "$MODULE_DIR")"
BUILD_ROOT="$HOME/.module_builds"
BUILD_DIR="$BUILD_ROOT/$MODULE_NAME"
OUTPUT_ZIP=~/"${MODULE_NAME}.zip"

# Check bash shell
if [ -z "$BASH_VERSION" ]; then
  echo -e "\033[31m\033[1m\n✖ ERROR: This script must be executed with bash.\033[0m" >&2
  echo -e "\033[31mShell detected: ${SHELL:-unknown}\033[0m" >&2
  echo -e "\033[31mUse: bash vendor/bin/module-build-zip.sh\033[0m" >&2
  exit 1
fi

# --- Console colors ---
if command -v tput >/dev/null 2>&1; then
  RED="$(tput setaf 1)"
  GREEN="$(tput setaf 2)"
  YELLOW="$(tput setaf 3)"
  BOLD="$(tput bold)"
  RESET="$(tput sgr0)"
else
  RED=$'\033[31m'
  GREEN=$'\033[32m'
  YELLOW=$'\033[33m'
  BOLD=$'\033[1m'
  RESET=$'\033[0m'
fi

# Detect buildignore file (must be in module root)
BUILD_IGNORE_FILE="$MODULE_DIR/.buildignore"

if [ ! -f "$BUILD_IGNORE_FILE" ]; then
  echo -e "${RED}${BOLD}\n✖ ERROR: No .buildignore file found.\n${RESET}" >&2
  echo -e "${RED}Create a .buildignore file at the root of your module.${RESET}" >&2
  exit 1
fi

echo -e "${GREEN}📋 Using .buildignore file${RESET}"

# Check composer
if ! command -v composer >/dev/null 2>&1; then
  echo -e "${RED}${BOLD}\n✖ ERROR: Composer is not installed or not found in PATH.\n${RESET}" >&2
  echo -e "${RED}Please install Composer and run this script again.${RESET}" >&2
  exit 1
fi

# Check zip
if ! command -v zip >/dev/null 2>&1; then
  echo -e "${RED}${BOLD}✖ ERROR: The 'zip' command is required.${RESET}" >&2
  echo -e "${RED}Please install the 'zip' package and run this script again.${RESET}" >&2
  exit 1
fi

# Clean before
rm -rf "$BUILD_DIR" "$OUTPUT_ZIP"
mkdir -p "$BUILD_ROOT"

echo "📦 Copying module to $BUILD_DIR..."
rsync -av --quiet \
  --exclude-from="$BUILD_IGNORE_FILE" \
  "$MODULE_DIR/" "$BUILD_DIR/"

cd "$BUILD_DIR" || exit 1

echo "📦 Installing production dependencies..."
composer install --no-dev --quiet

echo "📦 Optimizing autoloader..."
composer dump-autoload --optimize --no-dev --quiet

echo "📦 Creating final zip archive..."
cd "$BUILD_ROOT"
zip -r "$OUTPUT_ZIP" "$(basename "$BUILD_DIR")" > /dev/null

echo "🧹 Cleaning up..."
rm -rf "$BUILD_DIR"
rmdir "$BUILD_ROOT" 2>/dev/null || true

echo -e "${GREEN}✅ Archive created: $OUTPUT_ZIP${RESET}"
