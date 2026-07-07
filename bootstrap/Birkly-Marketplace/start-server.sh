#!/bin/bash
# Birkly Marketplace Development Server
# Starts the PHP built-in server with proper configuration
#
# Quick start:
#   chmod +x ./start-server.sh && ./start-server.sh -k
#
# Choose a specific port (optional):
#   ./start-server.sh -k -p 8091

set -e

DEFAULT_PORT=8090
ROOT_DIR=$(dirname "$0")
SCRIPT_NAME=$(basename "$0")

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${BLUE}[Marketplace]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[Marketplace]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[Marketplace]${NC} $1"
}

print_error() {
    echo -e "${RED}[Marketplace]${NC} $1"
}

show_usage() {
    echo "Usage: $SCRIPT_NAME [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -p, --port PORT    Port to run server on (default: $DEFAULT_PORT)"
    echo "  -h, --help         Show this help message"
    echo "  -k, --kill         Kill any existing server on the port"
    echo ""
    echo "Examples:"
    echo "  $SCRIPT_NAME                # Start on port $DEFAULT_PORT"
    echo "  $SCRIPT_NAME -p 8090        # Start on port 8090"
    echo "  $SCRIPT_NAME -k             # Kill existing server and start new one"
}

check_port() {
    local port=$1
    if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

kill_port() {
    local port=$1
    local pid=$(lsof -Pi :$port -sTCP:LISTEN -t 2>/dev/null)
    if [ ! -z "$pid" ]; then
        print_warning "Killing existing process on port $port (PID: $pid)"
        kill $pid
        sleep 1
        if check_port $port; then
            print_warning "Process still running, force killing..."
            kill -9 $pid 2>/dev/null || true
        fi
    fi
}

check_php() {
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed!"
        echo ""
        echo "Birkly Marketplace requires PHP 8.1+."
        exit 1
    fi

    local php_version=$(php -r "echo PHP_VERSION;" 2>/dev/null)
    if [ -z "$php_version" ]; then
        print_error "Could not determine PHP version"
        exit 1
    fi

    print_success "PHP is installed (version: $php_version)"

    local major=$(echo $php_version | cut -d. -f1)
    local minor=$(echo $php_version | cut -d. -f2)
    if [ "$major" -lt 8 ] || ([ "$major" -eq 8 ] && [ "$minor" -lt 1 ]); then
        print_error "PHP 8.1+ is required (found $php_version)"
        exit 1
    fi
}

check_project() {
    cd "$ROOT_DIR"

    if [ ! -f "index.php" ]; then
        print_error "index.php not found. Are you in the Marketplace root directory?"
        exit 1
    fi

    if [ ! -d "api" ]; then
        print_error "api directory not found."
        exit 1
    fi

    if [ ! -f "server-router.php" ]; then
        print_error "server-router.php not found."
        exit 1
    fi

    print_success "Project structure looks good"
}

PORT=$DEFAULT_PORT
KILL_EXISTING=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -p|--port)
            PORT="$2"
            shift 2
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        -k|--kill)
            KILL_EXISTING=true
            shift
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

if ! [[ "$PORT" =~ ^[0-9]+$ ]] || [ "$PORT" -lt 1024 ] || [ "$PORT" -gt 65535 ]; then
    print_error "Invalid port number: $PORT. Must be between 1024 and 65535"
    exit 1
fi

print_status "Starting Birkly Marketplace Development Server..."

if [ ! -x "$0" ]; then
    chmod +x "$0"
fi

print_status "Checking prerequisites..."
check_php
check_project

if check_port $PORT; then
    if [ "$KILL_EXISTING" = true ]; then
        kill_port $PORT
    else
        print_error "Port $PORT is already in use!"
        print_status "Use -k/--kill to kill the existing process, or choose a different port with -p"
        exit 1
    fi
fi

cd "$ROOT_DIR"
mkdir -p logs data cache temp artifacts

print_success "Starting server on http://localhost:$PORT"
print_status "Press Ctrl+C to stop the server"
print_status "Health: http://localhost:$PORT/api/v1/health"
print_status "Catalog stub: http://localhost:$PORT/api/v1/catalog"
print_status "Logs will be written to logs/ directory"

php -S localhost:$PORT -t . server-router.php 2>&1 | tee logs/server.log
