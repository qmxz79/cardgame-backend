<?php
/**
 * WebSocket 功能测试
 * 使用 Ratchet 客户端测试服务器功能
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\Client\Connector;
use React\EventLoop\StreamSelectLoop;
use React\Socket\Connector as SocketConnector;

echo "🧪 WebSocket 功能测试\n====================\n\n";

$loop = new StreamSelectLoop();
$connector = new Connector($loop);

$tests = [];
$passed = 0;
$failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ $name\n";
        $passed++;
        return true;
    } else {
        echo "❌ $name\n";
        $failed++;
        return false;
    }
}

$connector('ws://localhost:8080')->then(function($conn) use ($loop, &$tests) {
    echo "✅ 连接到 WebSocket 服务器\n\n";
    
    // 测试 1: 欢迎消息
    $conn->on('message', function($msg) use ($conn, $loop, &$tests) {
        $data = json_decode($msg, true);
        
        if (!isset($data['type'])) return;
        
        echo "📩 收到：{$data['type']}\n";
        
        switch ($data['type']) {
            case 'welcome':
                test('收到欢迎消息', true);
                test('包含 clientId', isset($data['clientId']));
                
                // 测试 2: 登录
                echo "\n1️⃣  登录测试\n";
                $conn->send(json_encode([
                    'action' => 'login',
                    'username' => 'test_player_' . time()
                ]));
                $tests['login'] = true;
                break;
                
            case 'loginSuccess':
                if (!empty($tests['login'])) {
                    test('登录成功', true);
                    test('返回 userId', isset($data['userId']));
                    test('返回 username', isset($data['username']));
                    
                    // 测试 3: 创建游戏
                    echo "\n2️⃣  创建游戏测试\n";
                    $conn->send(json_encode([
                        'action' => 'createGame'
                    ]));
                    $tests['createGame'] = true;
                }
                break;
                
            case 'gameCreated':
                if (!empty($tests['createGame'])) {
                    test('游戏创建成功', true);
                    test('返回 gameId', isset($data['gameId']));
                    test('状态为 waiting', $data['status'] === 'waiting');
                    
                    // 保存 gameId 供后续测试
                    $tests['gameId'] = $data['gameId'];
                    
                    // 测试 4: Ping
                    echo "\n3️⃣  Ping 测试\n";
                    $conn->send(json_encode(['action' => 'ping']));
                    $tests['ping'] = true;
                }
                break;
                
            case 'pong':
                if (!empty($tests['ping'])) {
                    test('Ping 响应正常', true);
                    test('包含时间戳', isset($data['timestamp']));
                    
                    // 测试 5: 离开游戏
                    echo "\n4️⃣  离开游戏测试\n";
                    global $tests;
                    if (!empty($tests['gameId'])) {
                        $conn->send(json_encode([
                            'action' => 'leaveGame',
                            'gameId' => $tests['gameId']
                        ]));
                        $tests['leaveGame'] = true;
                    }
                }
                break;
                
            case 'gameLeft':
                if (!empty($tests['leaveGame'])) {
                    test('离开游戏成功', true);
                    
                    echo "\n" . str_repeat('=', 40) . "\n";
                    echo "测试完成!\n";
                    
                    $conn->close();
                    global $loop;
                    $loop->stop();
                }
                break;
                
            case 'error':
                test('错误：' . $data['message'], false);
                break;
        }
    });
    
}, function($e) {
    echo "❌ 连接失败：" . $e->getMessage() . "\n";
    exit(1);
});

$loop->run();

echo "\n结果：✅ $passed | ❌ $failed\n";
exit($failed === 0 ? 0 : 1);
