#!/bin/bash

# HubTube Development Server Setup for Ubuntu 22.04
# This script sets up and runs the HubTube development server

set -e

echo "🚀 Starting HubTube Development Server Setup..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if running on Ubuntu
if ! grep -q "Ubuntu" /etc/os-release; then
    print_error "This script is designed for Ubuntu 22.04"
    exit 1
fi

# Get server IP
SERVER_IP=$(hostname -I | awk '{print $1}')
print_status "Server IP: $SERVER_IP"

# Step 1: Check dependencies
print_step "Checking dependencies..."

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed. Please install PHP 8.2+ first."
    exit 1
fi

PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
print_status "PHP version: $PHP_VERSION"

if [[ $(echo "$PHP_VERSION < 8.2" | bc -l) -eq 1 ]]; then
    print_error "PHP 8.2+ is required. Current version: $PHP_VERSION"
    exit 1
fi

# Check Composer
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed"
    exit 1
fi

# Check Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed"
    exit 1
fi

NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
print_status "Node.js version: v$(node -v)"

if [[ $NODE_VERSION -lt 18 ]]; then
    print_error "Node.js 18+ is required"
    exit 1
fi

# Check MariaDB/MySQL
if ! command -v mysql &> /dev/null; then
    print_warning "MySQL/MariaDB is not installed or not in PATH"
fi

# Check Redis
if ! command -v redis-server &> /dev/null; then
    print_warning "Redis is not installed"
fi

# Check FFmpeg
if ! command -v ffmpeg &> /dev/null; then
    print_warning "FFmpeg is not installed (required for video processing)"
fi

# Step 2: Install PHP dependencies
print_step "Installing PHP dependencies..."
if [ ! -d "vendor" ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    print_status "PHP dependencies already installed"
fi

# Step 3: Install Node dependencies
print_step "Installing Node dependencies..."
if [ ! -d "node_modules" ]; then
    npm install
else
    print_status "Node dependencies already installed"
fi

# Step 4: Build frontend assets
print_step "Building frontend assets..."
npm run build

# Step 5: Check environment file
print_step "Checking environment configuration..."
if [ ! -f ".env" ]; then
    print_status "Creating .env file from .env.example"
    cp .env.example .env
    php artisan key:generate
    print_warning "Please edit .env file with your database credentials"
    echo "Press Enter to continue or Ctrl+C to stop..."
    read -r
else
    print_status ".env file exists"
fi

# Step 6: Run migrations
print_step "Running database migrations..."
php artisan migrate --force

# Step 7: Seed database
print_step "Seeding database..."
php artisan db:seed --force

# Step 8: Create storage link
print_step "Creating storage link..."
php artisan storage:link

# Step 9: Clear caches
print_step "Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Step 10: Start services
print_step "Starting development services..."

# Kill any existing processes on ports 8000, 6001
pkill -f "php artisan serve" 2>/dev/null || true
pkill -f "php artisan reverb:start" 2>/dev/null || true
pkill -f "php artisan horizon" 2>/dev/null || true

# Start Laravel development server
print_status "Starting Laravel server on http://$SERVER_IP:8000"
php artisan serve --host=$SERVER_IP --port=8000 &
SERVER_PID=$!

# Start Reverb (WebSocket server)
print_status "Starting WebSocket server on ws://$SERVER_IP:6001"
php artisan reverb:start &
REVERB_PID=$!

# Start Horizon (queue worker)
print_status "Starting queue worker (Horizon)"
php artisan horizon &
HORIZON_PID=$!

# Wait a moment for services to start
sleep 3

# Display access information
echo ""
echo "🎉 HubTube Development Server is running!"
echo ""
echo "📱 Access URLs:"
echo "   Main App:     http://$SERVER_IP:8000"
echo "   Admin Panel:  http://$SERVER_IP:8000/admin"
echo "   WebSocket:    ws://$SERVER_IP:6001"
echo ""
echo "👤 Default Login:"
echo "   Admin:        admin@hubtube.com / password"
echo "   Demo User:    demo@hubtube.com / password"
echo ""
echo "🔧 Services Running:"
echo "   Laravel Server (PID: $SERVER_PID)"
echo "   WebSocket Server (PID: $REVERB_PID)"
echo "   Queue Worker (PID: $HORIZON_PID)"
echo ""
echo "📋 Useful Commands:"
echo "   View logs:    tail -f storage/logs/laravel.log"
echo "   Queue status: php artisan horizon"
echo "   Stop all:     pkill -f 'php artisan'"
echo ""
echo "Press Ctrl+C to stop all services"

# Function to cleanup on exit
cleanup() {
    print_status "Stopping all services..."
    kill $SERVER_PID 2>/dev/null || true
    kill $REVERB_PID 2>/dev/null || true
    kill $HORIZON_PID 2>/dev/null || true
    print_status "All services stopped"
    exit 0
}

# Set trap to cleanup on Ctrl+C
trap cleanup INT

# Wait for user to stop
wait
