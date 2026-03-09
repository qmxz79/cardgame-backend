<?php
/**
 * GameServer - WebSocket 游戏服务器核心
 */

namespace App\WebSocket;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;
use App\Classes\Card;
use App\Classes\Deck;

class GameServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage 所有连接 */
    protected \SplObjectStorage $clients;
    
    /** @var array 游戏房间 */
    protected array $games = [];
    
    /** @var array 玩家连接映射 */
    protected array $playerConnections = [];
    
    /** @var \PDO 数据库连接 */
    protected \PDO $db;
    
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->connectDatabase();
        echo "🗄️  Database connected\n";
    }
    
    /**
     * 连接数据库
     */
    private function connectDatabase(): void
    {
        $dsn = "mysql:host=localhost;dbname=cardgame;charset=utf8mb4";
        $this->db = new \PDO($dsn, 'usr', '123456', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
    }
    
    /**
     * 客户端连接时
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->playerConnections[$conn->resourceId] = $conn;
        
        echo "✅ New connection: {$conn->resourceId}\n";
        
        // 发送欢迎消息
        $this->send($conn, 'welcome', [
            'message' => 'Connected to Card Game Server',
            'clientId' => $conn->resourceId,
            'timestamp' => time()
        ]);
    }
    
    /**
     * 收到消息时
     */
    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        $data = json_decode($msg, true);
        
        if (!$data) {
            $this->send($conn, 'error', ['message' => 'Invalid JSON']);
            return;
        }
        
        $action = $data['action'] ?? 'unknown';
        echo "📩 Received from {$conn->resourceId}: $action\n";
        
        switch ($action) {
            case 'ping':
                $this->handlePing($conn);
                break;
                
            case 'login':
                $this->handleLogin($conn, $data);
                break;
                
            case 'createGame':
                $this->handleCreateGame($conn, $data);
                break;
                
            case 'joinGame':
                $this->handleJoinGame($conn, $data);
                break;
                
            case 'startGame':
                $this->handleStartGame($conn, $data);
                break;
                
            case 'drawCard':
                $this->handleDrawCard($conn, $data);
                break;
                
            case 'playCard':
                $this->handlePlayCard($conn, $data);
                break;
                
            case 'attack':
                $this->handleAttack($conn, $data);
                break;
                
            case 'endTurn':
                $this->handleEndTurn($conn, $data);
                break;
                
            case 'leaveGame':
                $this->handleLeaveGame($conn, $data);
                break;
                
            default:
                $this->send($conn, 'error', ['message' => "Unknown action: $action"]);
        }
    }
    
    /**
     * 客户端断开时
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        unset($this->playerConnections[$conn->resourceId]);
        
        echo "❌ Connection closed: {$conn->resourceId}\n";
        
        // 清理玩家所在的游戏
        $this->cleanupPlayerGames($conn->resourceId);
    }
    
    /**
     * 发生错误时
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "❌ Error on {$conn->resourceId}: {$e->getMessage()}\n";
    }
    
    /**
     * 发送消息
     */
    protected function send(ConnectionInterface $conn, string $type, array $data): void
    {
        $data['type'] = $type;
        $data['timestamp'] = time();
        $conn->send(json_encode($data));
    }
    
    /**
     * 广播消息给房间内所有玩家
     */
    protected function broadcastToGame(string $gameId, string $type, array $data): void
    {
        if (!isset($this->games[$gameId])) {
            return;
        }
        
        foreach ($this->games[$gameId]['players'] as $playerId => $player) {
            if (isset($this->playerConnections[$playerId])) {
                $this->send($this->playerConnections[$playerId], $type, $data);
            }
        }
    }
    
    /**
     * 处理 Ping
     */
    protected function handlePing(ConnectionInterface $conn): void
    {
        $this->send($conn, 'pong', ['timestamp' => time()]);
    }
    
    /**
     * 处理登录
     */
    protected function handleLogin(ConnectionInterface $conn, array $data): void
    {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($username)) {
            $this->send($conn, 'error', ['message' => 'Username required']);
            return;
        }
        
        // 简单验证（实际应该用密码哈希）
        $stmt = $this->db->prepare("SELECT id, username FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            $conn->userId = $user['id'];
            $conn->username = $user['username'];
            $this->send($conn, 'loginSuccess', [
                'userId' => $user['id'],
                'username' => $user['username']
            ]);
        } else {
            // 自动创建用户（测试用）
            $stmt = $this->db->prepare("INSERT INTO users (username) VALUES (?)");
            $stmt->execute([$username]);
            $userId = $this->db->lastInsertId();
            
            $conn->userId = $userId;
            $conn->username = $username;
            $this->send($conn, 'loginSuccess', [
                'userId' => $userId,
                'username' => $username,
                'autoCreated' => true
            ]);
        }
    }
    
    /**
     * 处理创建游戏
     */
    protected function handleCreateGame(ConnectionInterface $conn, array $data): void
    {
        if (!isset($conn->userId)) {
            $this->send($conn, 'error', ['message' => 'Please login first']);
            return;
        }
        
        $gameId = uniqid('game_');
        $this->games[$gameId] = [
            'id' => $gameId,
            'status' => 'waiting',
            'players' => [
                $conn->resourceId => [
                    'id' => $conn->userId,
                    'username' => $conn->username,
                    'ready' => true,
                    'deck' => null,
                    'hand' => [],
                    'battlefield' => [],
                    'life' => 20,
                    'mana' => 0,
                    'maxMana' => 0,
                    'isCurrentTurn' => false
                ]
            ],
            'turn' => null,
            'turnNumber' => 0,
            'createdAt' => time()
        ];
        
        $this->send($conn, 'gameCreated', [
            'gameId' => $gameId,
            'status' => 'waiting'
        ]);
        
        echo "🎮 Game created: $gameId by {$conn->username}\n";
    }
    
    /**
     * 处理加入游戏
     */
    protected function handleJoinGame(ConnectionInterface $conn, array $data): void
    {
        if (!isset($conn->userId)) {
            $this->send($conn, 'error', ['message' => 'Please login first']);
            return;
        }
        
        $gameId = $data['gameId'] ?? '';
        
        if (!isset($this->games[$gameId])) {
            $this->send($conn, 'error', ['message' => 'Game not found']);
            return;
        }
        
        if (count($this->games[$gameId]['players']) >= 4) {
            $this->send($conn, 'error', ['message' => 'Game is full (max 4 players)']);
            return;
        }
        
        $this->games[$gameId]['players'][$conn->resourceId] = [
            'id' => $conn->userId,
            'username' => $conn->username,
            'ready' => true,
            'deck' => null,
            'hand' => [],
            'battlefield' => [],
            'life' => 20,
            'mana' => 0,
            'maxMana' => 0,
            'isCurrentTurn' => false
        ];
        
        $this->send($conn, 'gameJoined', ['gameId' => $gameId]);
        
        // 通知所有玩家
        $this->broadcastToGame($gameId, 'playerJoined', [
            'playerId' => $conn->resourceId,
            'username' => $conn->username
        ]);
        
        echo "🎮 Player {$conn->username} joined game: $gameId\n";
    }
    
    /**
     * 处理开始游戏
     */
    protected function handleStartGame(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        
        if (!isset($this->games[$gameId])) {
            $this->send($conn, 'error', ['message' => 'Game not found']);
            return;
        }
        
        if (count($this->games[$gameId]['players']) < 2) {
            $this->send($conn, 'error', ['message' => 'Need at least 2 players']);
            return;
        }
        
        // 初始化游戏
        $playerIds = array_keys($this->games[$gameId]['players']);
        $firstPlayer = $playerIds[array_rand($playerIds)];
        
        foreach ($this->games[$gameId]['players'] as $playerId => &$player) {
            // 加载卡组
            $deck = $this->loadDeck($player['id']);
            $deck->shuffle();
            
            // 抽初始手牌（5 张）
            $initialHand = $deck->drawCard(5);
            
            $player['deck'] = $deck;
            $player['hand'] = array_map(fn($card) => $card->toArray(), $initialHand);
            $player['life'] = 20;
            $player['mana'] = 0;
            $player['maxMana'] = 0;
            $player['battlefield'] = [];
            $player['isCurrentTurn'] = ($playerId === $firstPlayer);
        }
        
        $this->games[$gameId]['status'] = 'playing';
        $this->games[$gameId]['turn'] = $firstPlayer;
        $this->games[$gameId]['turnNumber'] = 1;
        
        // 通知所有玩家游戏开始
        $this->broadcastToGame($gameId, 'gameStarted', [
            'firstTurn' => $firstPlayer,
            'players' => array_map(function($p) {
                return [
                    'username' => $p['username'],
                    'life' => $p['life']
                ];
            }, $this->games[$gameId]['players'])
        ]);
        
        // 发送各自的手牌
        foreach ($this->games[$gameId]['players'] as $playerId => $player) {
            if (isset($this->playerConnections[$playerId])) {
                $this->send($this->playerConnections[$playerId], 'yourHand', [
                    'hand' => $player['hand']
                ]);
            }
        }
        
        echo "🎮 Game started: $gameId\n";
    }
    
    /**
     * 处理抽牌
     */
    protected function handleDrawCard(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        
        if (!$this->isValidTurn($conn->resourceId, $gameId)) {
            $this->send($conn, 'error', ['message' => 'Not your turn']);
            return;
        }
        
        $player = &$this->games[$gameId]['players'][$conn->resourceId];
        $drawnCards = $player['deck']->drawCard(1);
        
        if (!empty($drawnCards)) {
            $player['hand'][] = $drawnCards[0]->toArray();
            $this->send($conn, 'cardDrawn', [
                'card' => $drawnCards[0]->toArray(),
                'handSize' => count($player['hand'])
            ]);
        } else {
            $this->send($conn, 'error', ['message' => 'No cards left in deck']);
        }
    }
    
    /**
     * 处理打出卡牌
     */
    protected function handlePlayCard(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        $cardIndex = $data['cardIndex'] ?? 0;
        
        if (!$this->isValidTurn($conn->resourceId, $gameId)) {
            $this->send($conn, 'error', ['message' => 'Not your turn']);
            return;
        }
        
        $player = &$this->games[$gameId]['players'][$conn->resourceId];
        
        if (!isset($player['hand'][$cardIndex])) {
            $this->send($conn, 'error', ['message' => 'Invalid card index']);
            return;
        }
        
        $card = $player['deck']->playCard($cardIndex);
        
        if ($card) {
            $player['battlefield'][] = $card->toArray();
            
            // 通知所有玩家
            $this->broadcastToGame($gameId, 'cardPlayed', [
                'player' => $player['username'],
                'card' => $card->toArray()
            ]);
        } else {
            $this->send($conn, 'error', ['message' => 'Cannot play this card']);
        }
    }
    
    /**
     * 处理攻击
     */
    protected function handleAttack(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        $cardIndex = $data['cardIndex'] ?? 0;
        $target = $data['target'] ?? 'player'; // 'player' or 'creature'
        $targetIndex = $data['targetIndex'] ?? null;
        
        if (!$this->isValidTurn($conn->resourceId, $gameId)) {
            $this->send($conn, 'error', ['message' => 'Not your turn']);
            return;
        }
        
        $attacker = &$this->games[$gameId]['players'][$conn->resourceId];
        
        if (!isset($attacker['battlefield'][$cardIndex])) {
            $this->send($conn, 'error', ['message' => 'Invalid creature']);
            return;
        }
        
        $creature = $attacker['battlefield'][$cardIndex];
        
        // 简化处理：直接攻击对方玩家
        $playerIds = array_keys($this->games[$gameId]['players']);
        $opponentId = $playerIds[0] === $conn->resourceId ? $playerIds[1] : $playerIds[0];
        $opponent = &$this->games[$gameId]['players'][$opponentId];
        
        $opponent['life'] -= $creature['currentPower'];
        
        $this->broadcastToGame($gameId, 'attack', [
            'attacker' => $attacker['username'],
            'creature' => $creature['name'],
            'damage' => $creature['currentPower'],
            'targetLife' => $opponent['life']
        ]);
        
        // 检查胜利
        if ($opponent['life'] <= 0) {
            $this->handleGameEnd($gameId, $conn->resourceId);
        }
    }
    
    /**
     * 处理结束回合
     */
    protected function handleEndTurn(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        
        if (!$this->isValidTurn($conn->resourceId, $gameId)) {
            $this->send($conn, 'error', ['message' => 'Not your turn']);
            return;
        }
        
        $playerIds = array_keys($this->games[$gameId]['players']);
        $currentTurnIndex = array_search($conn->resourceId, $playerIds);
        $nextTurnIndex = ($currentTurnIndex + 1) % count($playerIds);
        $nextPlayerId = $playerIds[$nextTurnIndex];
        
        // 更新回合
        foreach ($this->games[$gameId]['players'] as $pid => &$player) {
            $player['isCurrentTurn'] = ($pid === $nextPlayerId);
            if ($pid === $nextPlayerId) {
                // 新回合开始
                $player['maxMana'] = min($player['maxMana'] + 1, 10);
                $player['mana'] = $player['maxMana'];
                $drawnCards = $player['deck']->drawCard(1);
                if (!empty($drawnCards)) {
                    $player['hand'][] = $drawnCards[0]->toArray();
                }
            }
        }
        
        $this->games[$gameId]['turn'] = $nextPlayerId;
        if ($nextTurnIndex === 0) {
            $this->games[$gameId]['turnNumber']++;
        }
        
        $this->broadcastToGame($gameId, 'turnChanged', [
            'currentPlayer' => $this->games[$gameId]['players'][$nextPlayerId]['username'],
            'turnNumber' => $this->games[$gameId]['turnNumber']
        ]);
    }
    
    /**
     * 处理离开游戏
     */
    protected function handleLeaveGame(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        
        if (isset($this->games[$gameId])) {
            unset($this->games[$gameId]['players'][$conn->resourceId]);
            
            if (empty($this->games[$gameId]['players'])) {
                unset($this->games[$gameId]);
                echo "🗑️  Game deleted: $gameId\n";
            } else {
                $this->broadcastToGame($gameId, 'playerLeft', [
                    'playerId' => $conn->resourceId
                ]);
            }
        }
        
        $this->send($conn, 'gameLeft', ['gameId' => $gameId]);
    }
    
    /**
     * 处理游戏结束
     */
    protected function handleGameEnd(string $gameId, int $winnerId): void
    {
        $winner = $this->games[$gameId]['players'][$winnerId];
        
        $this->broadcastToGame($gameId, 'gameOver', [
            'winner' => $winner['username'],
            'winnerId' => $winnerId
        ]);
        
        echo "🏆 Game over: {$winner['username']} wins!\n";
        
        // 清理游戏
        unset($this->games[$gameId]);
    }
    
    /**
     * 加载玩家卡组
     */
    protected function loadDeck(int $userId): \App\Classes\Deck
    {
        // 获取或创建默认卡组
        $stmt = $this->db->prepare("SELECT id FROM decks WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $deckData = $stmt->fetch();
        
        if ($deckData) {
            return \App\Classes\Deck::fromDatabase($deckData['id'], $this->db);
        }
        
        // 创建默认卡组
        $stmt = $this->db->prepare("INSERT INTO decks (user_id, name) VALUES (?, 'Default Deck')");
        $stmt->execute([$userId]);
        $deckId = $this->db->lastInsertId();
        
        // 添加一些基础卡牌
        $stmt = $this->db->prepare("SELECT id FROM cards LIMIT 20");
        $stmt->execute();
        $cards = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($cards as $cardId) {
            $stmt = $this->db->prepare("INSERT INTO deck_cards (deck_id, card_id, quantity) VALUES (?, ?, 4)");
            $stmt->execute([$deckId, $cardId]);
        }
        
        return \App\Classes\Deck::fromDatabase($deckId, $this->db);
    }
    
    /**
     * 验证是否当前玩家的回合
     */
    protected function isValidTurn(int $playerId, string $gameId): bool
    {
        if (!isset($this->games[$gameId])) {
            return false;
        }
        
        return $this->games[$gameId]['turn'] === $playerId;
    }
    
    /**
     * 清理玩家的游戏
     */
    protected function cleanupPlayerGames(int $playerId): void
    {
        foreach ($this->games as $gameId => $game) {
            if (isset($game['players'][$playerId])) {
                unset($this->games[$gameId]['players'][$playerId]);
                
                if (empty($this->games[$gameId]['players'])) {
                    unset($this->games[$gameId]);
                }
            }
        }
    }
}
