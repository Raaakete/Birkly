#!/bin/bash
# Birkly Development Server
# Starts the PHP built-in server with proper configuration
#
# Quick start (copy/paste into your terminal):
#   chmod +x ./start-server.sh && ./start-server.sh -k
#
# Choose a specific port (optional):
#   ./start-server.sh -k -p 8001

set -e  # Exit on any error

# Configuration
DEFAULT_PORT=8080
ROOT_DIR=$(dirname "$0")
SCRIPT_NAME=$(basename "$0")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[Birkly]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[Birkly]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[Birkly]${NC} $1"
}

print_error() {
    echo -e "${RED}[Birkly]${NC} $1"
}

# Function to show usage
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
    echo "  $SCRIPT_NAME -p 8080        # Start on port 8080"
    echo "  $SCRIPT_NAME -k             # Kill existing server and start new one"
}

# Function to check if port is in use
check_port() {
    local port=$1
    if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1; then
        return 0  # Port is in use
    else
        return 1  # Port is free
    fi
}

# Function to kill process on port
kill_port() {
    local port=$1
    local pid=$(lsof -Pi :$port -sTCP:LISTEN -t 2>/dev/null)
    if [ ! -z "$pid" ]; then
        print_warning "Killing existing process on port $port (PID: $pid)"
        kill $pid
        sleep 1
        # Check if it's still running
        if check_port $port; then
            print_warning "Process still running, force killing..."
            kill -9 $pid 2>/dev/null || true
        fi
    fi
}

# Function to check PHP version
check_php() {
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed!"
        echo ""
        echo "Birkly requires PHP to run. Please install PHP first:"
        echo "  - Mac: Install via Homebrew (brew install php) or download from php.net"
        echo "  - Windows: Download from php.net or use XAMPP/WAMP"
        echo "  - Linux: Install via your package manager (apt install php, yum install php, etc.)"
        echo ""
        echo "After installing PHP, try running this script again."
        exit 1
    fi
    
    local php_version=$(php -r "echo PHP_VERSION;" 2>/dev/null)
    if [ -z "$php_version" ]; then
        print_error "Could not determine PHP version"
        echo "Please make sure PHP is installed correctly."
        exit 1
    fi
    
    print_success "PHP is installed (version: $php_version)"
    
    # Check if version is >= 7.4 (recommended minimum)
    local major=$(echo $php_version | cut -d. -f1)
    local minor=$(echo $php_version | cut -d. -f2)
    if [ "$major" -lt 7 ] || ([ "$major" -eq 7 ] && [ "$minor" -lt 4 ]); then
        print_warning "PHP version $php_version is quite old. Birkly works best with PHP 7.4+"
    fi
}

# Function to check project structure
check_project() {
    cd "$ROOT_DIR"
    
    if [ ! -f "index.php" ]; then
        print_error "index.php not found. Are you in the Birkly root directory?"
        exit 1
    fi
    
    if [ ! -d "admin" ]; then
        print_error "admin directory not found. This doesn't look like a Birkly installation."
        exit 1
    fi
    
    if [ ! -d "api" ]; then
        print_error "api directory not found. This doesn't look like a Birkly installation."
        exit 1
    fi
    
    print_success "Project structure looks good"
}

# Parse command line arguments
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

# Validate port number
if ! [[ "$PORT" =~ ^[0-9]+$ ]] || [ "$PORT" -lt 1024 ] || [ "$PORT" -gt 65535 ]; then
    print_error "Invalid port number: $PORT. Must be between 1024 and 65535"
    exit 1
fi

# Main execution
print_status "Starting Birkly Development Server..."

# Make script executable if it isn't already
if [ ! -x "$0" ]; then
    chmod +x "$0"
fi

# Check prerequisites FIRST - PHP is required before anything else
print_status "Checking prerequisites..."
check_php
check_project

# Handle port conflicts
if check_port $PORT; then
    if [ "$KILL_EXISTING" = true ]; then
        kill_port $PORT
    else
        print_error "Port $PORT is already in use!"
        print_status "Use -k/--kill to kill the existing process, or choose a different port with -p"
        exit 1
    fi
fi

# Change to project directory
cd "$ROOT_DIR"

# Create logs directory if it doesn't exist
mkdir -p logs

# Start the server
print_success "Starting server on http://localhost:$PORT"
print_status "Press Ctrl+C to stop the server"
print_status "Admin panel: http://localhost:$PORT/admin/"
print_status "Logs will be written to logs/ directory"

# Start PHP server with router script and error logging
# The router ensures PHP files execute correctly instead of being served as plain text
php -S localhost:$PORT -t . server-router.php 2>&1 | tee logs/server.log 