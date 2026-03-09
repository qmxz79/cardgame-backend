#!/bin/bash
# WebSocket 服务器启动脚本

cd /var/www/cardgame

echo "🚀 Starting Card Game WebSocket Server..."
echo "=========================================="
echo ""

# 检查 PHP 版本
php -v | head -1

# 检查端口是否被占用
if netstat -tuln 2>/dev/null | grep -q ":8080 "; then
    echo "⚠️  Port 8080 is already in use!"
    echo "   Trying to stop existing server..."
    pkill -f "php.*server.php" 2>/dev/null
    sleep 2
fi

echo ""
echo "✅ Starting server..."
echo ""

# 启动服务器
php server.php
