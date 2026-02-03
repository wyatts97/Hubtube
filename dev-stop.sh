#!/bin/bash

# HubTube Development Server Stop Script
# This script stops all HubTube development services

echo "🛑 Stopping HubTube Development Services..."

# Kill all Laravel artisan processes
pkill -f "php artisan serve" 2>/dev/null && echo "✅ Stopped Laravel server" || echo "ℹ️  Laravel server not running"
pkill -f "php artisan reverb:start" 2>/dev/null && echo "✅ Stopped WebSocket server" || echo "ℹ️  WebSocket server not running"
pkill -f "php artisan horizon" 2>/dev/null && echo "✅ Stopped Queue worker" || echo "ℹ️  Queue worker not running"

# Also kill any remaining processes on common ports
lsof -ti:8000 | xargs kill -9 2>/dev/null && echo "✅ Killed processes on port 8000" || true
lsof -ti:6001 | xargs kill -9 2>/dev/null && echo "✅ Killed processes on port 6001" || true

echo "🎉 All HubTube services stopped!"
