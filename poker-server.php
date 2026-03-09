<?php
/**
 * PokerGameServer - 扑克牌游戏 WebSocket 服务器
 * 4 人升级/拖拉机游戏
 */

require_once __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\WebSocket\PokerGameServer;

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "🃏 Poker Game WebSocket Server\n";
echo "==============================\n\n";

try {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new PokerGameServer()
            )
        ),
        8081  // 使用不同端口
    );
    
    echo "✅ Server starting on port 8081...\n";
    echo "📡 WebSocket: ws://localhost:8081\n\n";
    echo "Press Ctrl+C to stop the server.\n\n";
    
    $server->run();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
