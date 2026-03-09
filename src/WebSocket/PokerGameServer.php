<?php
/**
 * PokerGameServer - 扑克牌游戏 WebSocket 服务器
 */

namespace App\WebSocket;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use App\Classes\PokerGame;
use App\Classes\PokerCard;

class PokerGameServer implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    protected array $games = [];
    protected array $playerConnections = [];
    protected \PDO $db;
    
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->connectDatabase();
        echo "🗄️  Database connected\n";
    }
    
    private function connectDatabase(): void
    {
        $this->db = new \PDO('mysql:host=localhost;dbname=cardgame', 'usr', '123456', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
    }
    
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->playerConnections[$conn->resourceId] = $conn;
        echo "✅ New connection: {$conn->resourceId}\n";
        
        $this->send($conn, 'welcome', [
            'message' => 'Connected to Poker Game Server',
            'clientId' => $conn->resourceId,
            'gameType' => '4-player poker',
            'timestamp' => time()
        ]);
    }
    
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
            case 'playCards':
                $this->handlePlayCards($conn, $data);
                break;
            case 'getGameState':
                $this->handleGetGameState($conn, $data);
                break;
            default:
                $this->send($conn, 'error', ['message' => "Unknown action: $action"]);
        }
    }
    
    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        unset($this->playerConnections[$conn->resourceId]);
        echo "❌ Connection closed: {$conn->resourceId}\n";
        $this->cleanupPlayerGames($conn->resourceId);
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "❌ Error on {$conn->resourceId}: {$e->getMessage()}\n";
    }
    
    protected function send(ConnectionInterface $conn, string $type, array $data): void
    {
        $data['type'] = $type;
        $data['timestamp'] = time();
        $conn->send(json_encode($data));
    }
    
    protected function broadcastToGame(string $gameId, string $type, array $data): void
    {
        if (!isset($this->games[$gameId])) return;
        
        foreach ($this->games[$gameId]['players'] as $playerId => $player) {
            if (isset($this->playerConnections[$playerId])) {
                $this->send($this->playerConnections[$playerId], $type, $data);
            }
        }
    }
    
    protected function handlePing(ConnectionInterface $conn): void
    {
        $this->send($conn, 'pong', ['timestamp' => time()]);
    }
    
    protected function handleLogin(ConnectionInterface $conn, array $data): void
    {
        $username = $data['username'] ?? '';
        if (empty($username)) {
            $this->send($conn, 'error', ['message' => 'Username required']);
            return;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $stmt = $this->db->prepare("INSERT INTO users (username) VALUES (?)");
                $stmt->execute([$username]);
                $userId = $this->db->lastInsertId();
                $user = ['id' => $userId, 'username' => $username];
            }
            
            $conn->userId = $user['id'];
            $conn->username = $username;
            $this->send($conn, 'loginSuccess', [
                'userId' => $user['id'],
                'username' => $username
            ]);
        } catch (\Exception $e) {
            $this->send($conn, 'error', ['message' => 'Login failed: ' . $e->getMessage()]);
        }
    }
    
    protected function handleCreateGame(ConnectionInterface $conn, array $data): void
    {
        if (!isset($conn->userId)) {
            $this->send($conn, 'error', ['message' => 'Please login first']);
            return;
        }
        
        $gameId = 'poker_' . uniqid();
        $this->games[$gameId] = [
            'id' => $gameId,
            'game' => new PokerGame($gameId),
            'status' => 'waiting',
            'players' => [],
            'createdAt' => time()
        ];
        
        // 房主自动加入
        $this->games[$gameId]['game']->addPlayer($conn->userId, $conn->username);
        $this->games[$gameId]['players'][$conn->resourceId] = [
            'id' => $conn->userId,
            'username' => $conn->username,
            'seat' => 0
        ];
        
        $this->send($conn, 'gameCreated', [
            'gameId' => $gameId,
            'status' => 'waiting',
            'playerCount' => 1,
            'maxPlayers' => 4
        ]);
        
        echo "🎮 Poker game created: $gameId\n";
    }
    
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
        
        $game = $this->games[$gameId]['game'];
        $seat = count($this->games[$gameId]['players']);
        $game->addPlayer($conn->userId, $conn->username);
        
        $this->games[$gameId]['players'][$conn->resourceId] = [
            'id' => $conn->userId,
            'username' => $conn->username,
            'seat' => $seat
        ];
        
        $this->send($conn, 'gameJoined', [
            'gameId' => $gameId,
            'seat' => $seat,
            'playerCount' => count($this->games[$gameId]['players'])
        ]);
        
        $this->broadcastToGame($gameId, 'playerJoined', [
            'username' => $conn->username,
            'seat' => $seat,
            'playerCount' => count($this->games[$gameId]['players'])
        ]);
        
        echo "🎮 Player {$conn->username} joined game: $gameId (seat $seat)\n";
    }
    
    protected function handleStartGame(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        if (!isset($this->games[$gameId])) {
            $this->send($conn, 'error', ['message' => 'Game not found']);
            return;
        }
        
        $game = $this->games[$gameId]['game'];
        if (count($game->getPlayers()) < 2) {
            $this->send($conn, 'error', ['message' => 'Need at least 2 players']);
            return;
        }
        
        $game->startGame();
        $this->games[$gameId]['status'] = 'playing';
        
        // 发送游戏开始通知
        $this->broadcastToGame($gameId, 'gameStarted', [
            'playerCount' => count($game->getPlayers()),
            'currentPlayer' => $game->getState()['currentPlayer']
        ]);
        
        // 发送每个玩家的手牌
        foreach ($game->getPlayers() as $player) {
            $seat = $player->seat;
            foreach ($this->games[$gameId]['players'] as $connId => $p) {
                if ($p['seat'] === $seat && isset($this->playerConnections[$connId])) {
                    $this->send($this->playerConnections[$connId], 'yourHand', [
                        'seat' => $seat,
                        'hand' => $player->getHand(),
                        'handSize' => $player->getHandSize()
                    ]);
                }
            }
        }
        
        echo "🎮 Poker game started: $gameId\n";
    }
    
    protected function handlePlayCards(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        $cardIndices = $data['cardIndices'] ?? [];
        
        if (!isset($this->games[$gameId])) {
            $this->send($conn, 'error', ['message' => 'Game not found']);
            return;
        }
        
        $game = $this->games[$gameId]['game'];
        $playerSeat = null;
        
        foreach ($this->games[$gameId]['players'] as $p) {
            if ($p['id'] === $conn->userId) {
                $playerSeat = $p['seat'];
                break;
            }
        }
        
        if ($playerSeat === null) {
            $this->send($conn, 'error', ['message' => 'Player not found in game']);
            return;
        }
        
        $result = $game->playCards($playerSeat, $cardIndices);
        
        if ($result['success']) {
            $this->broadcastToGame($gameId, 'cardsPlayed', [
                'seat' => $playerSeat,
                'cards' => $result['playedCards'],
                'nextPlayer' => $result['nextPlayer']
            ]);
            
            if ($game->isFinished()) {
                $this->handleGameEnd($gameId);
            }
        } else {
            $this->send($conn, 'error', $result);
        }
    }
    
    protected function handleGetGameState(ConnectionInterface $conn, array $data): void
    {
        $gameId = $data['gameId'] ?? '';
        if (!isset($this->games[$gameId])) {
            $this->send($conn, 'error', ['message' => 'Game not found']);
            return;
        }
        
        $game = $this->games[$gameId]['game'];
        $this->send($conn, 'gameState', $game->getState());
    }
    
    protected function handleGameEnd(string $gameId): void
    {
        $game = $this->games[$gameId]['game'];
        $winningTeam = $game->getWinningTeam();
        
        $this->broadcastToGame($gameId, 'gameOver', [
            'winningTeam' => $winningTeam,
            'message' => "Team $winningTeam wins!"
        ]);
        
        echo "🏆 Game over: Team $winningTeam wins!\n";
        unset($this->games[$gameId]);
    }
    
    protected function cleanupPlayerGames(int $playerId): void
    {
        foreach ($this->games as $gameId => $gameData) {
            if (isset($gameData['players'][$playerId])) {
                unset($this->games[$gameId]['players'][$playerId]);
                if (empty($this->games[$gameId]['players'])) {
                    unset($this->games[$gameId]);
                }
            }
        }
    }
}
