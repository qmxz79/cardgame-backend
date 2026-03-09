<?php
/**
 * WebSocket 游戏服务器
 * 使用 Ratchet 库实现
 */

require_once __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\WebSocket\GameServer;

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "🎮 Card Game WebSocket Server\n";
echo "============================\n\n";

try {
    // 创建服务器
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new GameServer()
            )
        ),
        8080
    );
    
    echo "✅ Server starting on port 8080...\n";
    echo "📡 WebSocket: ws://localhost:8080\n\n";
    echo "Press Ctrl+C to stop the server.\n\n";
    
    $server->run();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
