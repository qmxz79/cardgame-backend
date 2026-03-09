#!/bin/bash
echo "🧪 运行所有测试"
echo "================"
echo ""

cd /var/www/cardgame

# 测试计数器
total_passed=0
total_failed=0

echo "1️⃣  Card 类测试"
echo "----------------"
php tests/CardTest.php
if [ $? -eq 0 ]; then
    ((total_passed+=14))
else
    ((total_failed+=1))
fi
echo ""

echo "2️⃣  Deck 类测试"
echo "----------------"
php tests/DeckTest.php
if [ $? -eq 0 ]; then
    ((total_passed+=18))
else
    ((total_failed+=1))
fi
echo ""

echo "3️⃣  集成测试"
echo "----------------"
php tests/IntegrationTest.php
if [ $? -eq 0 ]; then
    ((total_passed+=16))
else
    ((total_failed+=1))
fi
echo ""

echo "4️⃣  WebSocket 简化测试"
echo "----------------"
php tests/WsSimpleTest.php
if [ $? -eq 0 ]; then
    ((total_passed+=26))
else
    ((total_failed+=1))
fi
echo ""

echo "========================================"
echo "📊 总结果"
echo "========================================"
echo "✅ 通过：$total_passed 项测试"
if [ $total_failed -gt 0 ]; then
    echo "❌ 失败：$total_failed 项测试"
    exit 1
else
    echo "🎉 所有测试通过！"
    exit 0
fi
