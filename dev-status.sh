#!/bin/bash

# HubTube Development Server Status Script
# This script checks the status of all HubTube development services

echo "📊 HubTube Development Server Status"
echo "=================================="

# Get server IP
SERVER_IP=$(hostname -I | awk '{print $1}')
echo "Server IP: $SERVER_IP"
echo ""

# Check Laravel server
if pgrep -f "php artisan serve" > /dev/null; then
    echo "✅ Laravel Server: RUNNING"
    echo "   URL: http://$SERVER_IP:8000"
else
    echo "❌ Laravel Server: STOPPED"
fi

# Check WebSocket server
if pgrep -f "php artisan reverb:start" > /dev/null; then
    echo "✅ WebSocket Server: RUNNING"
    echo "   URL: ws://$SERVER_IP:6001"
else
    echo "❌ WebSocket Server: STOPPED"
fi

# Check Horizon (queue worker)
if pgrep -f "php artisan horizon" > /dev/null; then
    echo "✅ Queue Worker: RUNNING"
else
    echo "❌ Queue Worker: STOPPED"
fi

echo ""

# Check port usage
echo "🔌 Port Usage:"
lsof -i:8000 2>/dev/null && echo "   Port 8000: IN USE" || echo "   Port 8000: FREE"
lsof -i:6001 2>/dev/null && echo "   Port 6001: IN USE" || echo "   Port 6001: FREE"

echo ""

# Check dependencies
echo "🔧 Dependencies:"
command -v php &> /dev/null && echo "   PHP: ✅ $(php -v | head -n1)" || echo "   PHP: ❌ Not found"
command -v composer &> /dev/null && echo "   Composer: ✅ $(composer --version)" || echo "   Composer: ❌ Not found"
command -v node &> /dev/null && echo "   Node.js: ✅ $(node -v)" || echo "   Node.js: ❌ Not found"
command -v npm &> /dev/null && echo "   NPM: ✅ $(npm --version)" || echo "   NPM: ❌ Not found"
command -v redis-server &> /dev/null && echo "   Redis: ✅ $(redis-server --version | head -n1)" || echo "   Redis: ❌ Not found"
command -v ffmpeg &> /dev/null && echo "   FFmpeg: ✅ $(ffmpeg -version | head -n1)" || echo "   FFmpeg: ❌ Not found"
