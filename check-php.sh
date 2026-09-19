#!/bin/bash
# Birkly — verify PHP CLI is installed before starting the built-in server.

set -e
ROOT_DIR=$(cd "$(dirname "$0")" && pwd)
cd "$ROOT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

echo ""
echo -e "${GREEN}[Birkly]${NC} Checking PHP for local development..."
echo ""

if ! command -v php >/dev/null 2>&1; then
    echo -e "${RED}[Birkly]${NC} PHP is not installed or not on your PATH."
    echo ""
    echo "Birkly needs PHP 8.0+ before you can start the server."
    echo "  Download: https://www.php.net/downloads.php"
    echo "  Windows:  https://windows.php.net/download/"
    echo ""
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)
if [ -z "$PHP_VERSION" ]; then
    echo -e "${RED}[Birkly]${NC} Could not read PHP version."
    exit 1
fi

echo -e "${GREEN}[Birkly]${NC} Found PHP $PHP_VERSION"

php -r "if (version_compare(PHP_VERSION, '8.0.0', '<')) { fwrite(STDERR, \"[Birkly] PHP 8.0+ is required.\n\"); exit(1); }"

for ext in json mbstring curl openssl; do
    if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null; then
        echo -e "${RED}[Birkly]${NC} Missing PHP extension: $ext"
        exit 1
    fi
done

if [ ! -f "index.php" ]; then
    echo -e "${RED}[Birkly]${NC} index.php not found. Run this from your Birkly folder."
    exit 1
fi

echo -e "${GREEN}[Birkly]${NC} PHP is ready. Next: ./start-server.sh (keep that window open)."
echo ""
exit 0
