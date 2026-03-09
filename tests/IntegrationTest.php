<?php
/**
 * 集成测试 - 测试 WebSocket 服务器和数据库
 */

echo "🧪 集成测试\n==============\n\n";
$passed = $failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) { echo "✅ $name\n"; $passed++; }
    else { echo "❌ $name\n"; $failed++; }
}

// 测试 1: WebSocket 服务器连接
echo "1️⃣  WebSocket 服务器测试\n";
$socket = @fsockopen('localhost', 8080, $errno, $errstr, 2);
if ($socket) {
    fclose($socket);
    test('WebSocket 服务器运行中', true);
} else {
    test('WebSocket 服务器运行中', false);
    echo "   错误：$errstr\n";
}

// 测试 2: 数据库连接
echo "\n2️⃣  数据库连接测试\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=cardgame', 'usr', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    test('数据库连接成功', true);
    
    // 测试表存在
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    test('users 表存在', in_array('users', $tables));
    test('cards 表存在', in_array('cards', $tables));
    test('decks 表存在', in_array('decks', $tables));
    test('games 表存在', in_array('games', $tables));
    
    // 测试卡牌数据
    $count = $pdo->query("SELECT COUNT(*) FROM cards")->fetchColumn();
    test("卡牌数据存在 ($count 张)", $count > 0);
    
} catch (PDOException $e) {
    test('数据库连接成功', false);
    echo "   错误：" . $e->getMessage() . "\n";
}

// 测试 3: 自动加载
echo "\n3️⃣  自动加载测试\n";
require_once __DIR__ . '/../vendor/autoload.php';
test('Composer 自动加载', class_exists('App\Classes\Card'));
test('Card 类可加载', class_exists('App\Classes\Card'));
test('Deck 类可加载', class_exists('App\Classes\Deck'));

// 测试 4: 创建测试用户
echo "\n4️⃣  数据库操作测试\n";
try {
    $testUser = 'test_user_' . time();
    $stmt = $pdo->prepare("INSERT INTO users (username) VALUES (?)");
    $stmt->execute([$testUser]);
    $userId = $pdo->lastInsertId();
    test('创建测试用户', $userId > 0);
    
    // 清理
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    test('清理测试数据', true);
    
} catch (Exception $e) {
    test('数据库操作', false);
    echo "   错误：" . $e->getMessage() . "\n";
}

// 测试 5: 创建测试卡组
echo "\n5️⃣  卡组创建测试\n";
try {
    // 创建测试用户
    $testUser = 'deck_test_' . time();
    $stmt = $pdo->prepare("INSERT INTO users (username) VALUES (?)");
    $stmt->execute([$testUser]);
    $userId = $pdo->lastInsertId();
    
    // 创建卡组
    $stmt = $pdo->prepare("INSERT INTO decks (user_id, name) VALUES (?, '测试卡组')");
    $stmt->execute([$userId]);
    $deckId = $pdo->lastInsertId();
    test('创建测试卡组', $deckId > 0);
    
    // 添加卡牌到卡组
    $stmt = $pdo->query("SELECT id FROM cards LIMIT 5");
    $cards = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($cards as $cardId) {
        $stmt = $pdo->prepare("INSERT INTO deck_cards (deck_id, card_id, quantity) VALUES (?, ?, 4)");
        $stmt->execute([$deckId, $cardId]);
    }
    test('添加卡牌到卡组', true);
    
    // 验证
    $count = $pdo->query("SELECT COUNT(*) FROM deck_cards WHERE deck_id = $deckId")->fetchColumn();
    test('卡组卡牌数量', $count === count($cards));
    
    // 清理
    $pdo->exec("DELETE FROM decks WHERE id = $deckId");
    $pdo->exec("DELETE FROM users WHERE id = $userId");
    test('清理测试数据', true);
    
} catch (Exception $e) {
    test('卡组创建', false);
    echo "   错误：" . $e->getMessage() . "\n";
}

echo "\n" . str_repeat('=', 40) . "\n";
echo "结果：✅ $passed | ❌ $failed\n";

if ($failed === 0) {
    echo "🎉 所有集成测试通过！\n";
    exit(0);
} else {
    echo "⚠️  有 $failed 个测试失败\n";
    exit(1);
}
