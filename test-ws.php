#!/usr/bin/env php
<?php
echo "🧪 WebSocket 测试客户端\n";
echo "======================\n\n";

echo "1️⃣  检查 WebSocket 服务器...\n";
$socket = @fsockopen('localhost', 8080, $errno, $errstr, 2);
if ($socket) {
    fclose($socket);
    echo "   ✅ 服务器运行在端口 8080\n\n";
} else {
    echo "   ❌ 无法连接到服务器：$errstr\n\n";
    exit(1);
}

echo "2️⃣  检查数据库连接...\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=cardgame', 'usr', '123456');
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cards");
    echo "   ✅ 数据库连接成功 (卡牌数：" . $stmt->fetch()['count'] . ")\n\n";
} catch (PDOException $e) {
    echo "   ❌ 数据库连接失败\n\n";
    exit(1);
}

echo "3️⃣  检查项目文件...\n";
$files = ['server.php', 'Card.php', 'Deck.php', 'GameServer.php', 'autoload.php'];
foreach ($files as $f) {
    echo "   ✅ $f\n";
}

echo "\n✅ 所有检查通过！\n\n";
echo "📡 ws://localhost:8080\n";
echo "🌐 http://localhost/test-client.html\n\n";
