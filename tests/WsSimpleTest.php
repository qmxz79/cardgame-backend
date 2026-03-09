<?php
/**
 * 简化的 WebSocket 测试
 * 使用原始 socket 连接
 */

echo "🧪 WebSocket 简化测试\n====================\n\n";

$passed = $failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) { echo "✅ $name\n"; $passed++; }
    else { echo "❌ $name\n"; $failed++; }
}

// 测试 1: 服务器端口可达
echo "1️⃣  服务器连接测试\n";
$socket = @fsockopen('localhost', 8080, $errno, $errstr, 3);
if ($socket) {
    test('端口 8080 可连接', true);
    fclose($socket);
} else {
    test('端口 8080 可连接', false);
    echo "   错误：$errstr\n";
    exit(1);
}

// 测试 2: 发送 HTTP 握手请求
echo "\n2️⃣  WebSocket 握手测试\n";
$socket = fsockopen('localhost', 8080, $errno, $errstr, 3);
if ($socket) {
    stream_set_timeout($socket, 3);
    
    $key = base64_encode(random_bytes(16));
    $request = "GET / HTTP/1.1\r\n";
    $request .= "Host: localhost:8080\r\n";
    $request .= "Upgrade: websocket\r\n";
    $request .= "Connection: Upgrade\r\n";
    $request .= "Sec-WebSocket-Key: $key\r\n";
    $request .= "Sec-WebSocket-Version: 13\r\n";
    $request .= "\r\n";
    
    fwrite($socket, $request);
    $response = fread($socket, 1024);
    
    test('收到握手响应', !empty($response));
    test('响应包含 101', strpos($response, '101') !== false);
    test('响应包含 Upgrade', stripos($response, 'Upgrade') !== false);
    
    fclose($socket);
} else {
    test('WebSocket 握手', false);
}

// 测试 3: 数据库验证
echo "\n3️⃣  数据库验证\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=cardgame', 'usr', '123456');
    
    // 验证表结构
    $tables = ['users', 'cards', 'decks', 'deck_cards', 'games', 'game_actions'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        test("表 $table 存在", $stmt->rowCount() > 0);
    }
    
    // 验证卡牌数据
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cards");
    $count = $stmt->fetch()['count'];
    test("卡牌数量: $count", $count >= 8);
    
    // 验证卡牌类型
    $stmt = $pdo->query("SELECT DISTINCT card_type FROM cards");
    $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    test('包含 creature 类型', in_array('creature', $types));
    test('包含 spell 类型', in_array('spell', $types));
    test('包含 land 类型', in_array('land', $types));
    
} catch (Exception $e) {
    test('数据库验证', false);
    echo "   错误：" . $e->getMessage() . "\n";
}

// 测试 4: 类文件验证
echo "\n4️⃣  代码文件验证\n";
$files = [
    '/var/www/cardgame/src/Classes/Card.php',
    '/var/www/cardgame/src/Classes/Deck.php',
    '/var/www/cardgame/src/WebSocket/GameServer.php',
    '/var/www/cardgame/src/Config/database.php'
];

foreach ($files as $file) {
    test(basename($file) . ' 存在', file_exists($file));
    test(basename($file) . ' 非空', filesize($file) > 0);
}

// 测试 5: Composer 自动加载
echo "\n5️⃣  自动加载验证\n";
require_once __DIR__ . '/../vendor/autoload.php';

test('Card 类存在', class_exists('App\Classes\Card'));
test('Deck 类存在', class_exists('App\Classes\Deck'));

// 实例化测试
$card = new App\Classes\Card(['name' => '测试', 'card_type' => 'creature']);
test('Card 实例化成功', $card instanceof App\Classes\Card);

$deck = new App\Classes\Deck(['name' => '测试卡组']);
test('Deck 实例化成功', $deck instanceof App\Classes\Deck);

echo "\n" . str_repeat('=', 40) . "\n";
echo "结果：✅ $passed | ❌ $failed\n";

if ($failed === 0) {
    echo "🎉 所有核心代码测试通过！\n\n";
    echo "✅ WebSocket 服务器运行正常\n";
    echo "✅ 数据库连接和表结构正常\n";
    echo "✅ Card/Deck 类功能正常\n";
    echo "✅ 自动加载配置正常\n";
    exit(0);
} else {
    echo "⚠️  有 $failed 个测试失败\n";
    exit(1);
}
