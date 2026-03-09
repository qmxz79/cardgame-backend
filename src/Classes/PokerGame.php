<?php
/**
 * PokerGame 类 - 扑克游戏核心逻辑
 * 4 人游戏，2 队对抗
 */

namespace App\Classes;

class PokerGame
{
    public string $gameId;
    public string $status = 'waiting'; // waiting, playing, finished
    
    /** @var Player[] */
    private array $players = [];
    
    /** @var PokerCard[] 牌堆 */
    private array $deck = [];
    
    /** @var PokerCard[] 已出的牌 */
    private array $playedCards = [];
    
    private int $currentPlayer = 0;  // 当前出牌玩家座位号
    private int $lastWinner = 0;     // 上一轮赢的玩家
    private array $lastPlay = [];    // 上一轮出的牌
    private int $round = 1;          // 第几轮
    
    public function __construct(string $gameId)
    {
        $this->gameId = $gameId;
    }
    
    /**
     * 添加玩家
     */
    public function addPlayer(int $playerId, string $username): bool
    {
        if (count($this->players) >= 4) {
            return false;
        }
        
        $seat = count($this->players);
        $this->players[$seat] = new Player($playerId, $username, $seat);
        return true;
    }
    
    /**
     * 开始游戏
     */
    public function startGame(): bool
    {
        if (count($this->players) < 2) {
            return false;
        }
        
        // 生成 4 副牌
        $this->deck = PokerCard::createDecks(4);
        
        // 洗牌
        PokerCard::shuffleCards($this->deck);
        
        // 发牌（每人 54 张）
        $this->dealCards();
        
        // 确定庄家（随机）
        $this->currentPlayer = array_rand($this->players);
        $this->players[$this->currentPlayer]->isLandlord = true;
        
        $this->status = 'playing';
        $this->round = 1;
        
        return true;
    }
    
    /**
     * 发牌
     */
    private function dealCards(): void
    {
        $playerCount = count($this->players);
        $cardsPerPlayer = 54;
        
        foreach ($this->players as $seat => $player) {
            $playerCards = [];
            for ($i = 0; $i < $cardsPerPlayer; $i++) {
                if (!empty($this->deck)) {
                    $playerCards[] = array_pop($this->deck);
                }
            }
            $player->dealCards($playerCards);
        }
    }
    
    /**
     * 出牌
     */
    public function playCards(int $seat, array $cardIndices): array
    {
        if (!isset($this->players[$seat])) {
            return ['success' => false, 'message' => 'Invalid seat'];
        }
        
        if ($seat !== $this->currentPlayer) {
            return ['success' => false, 'message' => 'Not your turn'];
        }
        
        $player = $this->players[$seat];
        $playedCards = $player->playCards($cardIndices);
        
        if (empty($playedCards)) {
            return ['success' => false, 'message' => 'No cards played'];
        }
        
        $this->playedCards[] = [
            'seat' => $seat,
            'cards' => $playedCards,
            'round' => $this->round
        ];
        
        $this->lastPlay = [
            'seat' => $seat,
            'cards' => $playedCards
        ];
        
        // 检查是否出完牌
        if ($player->isEmptyHand()) {
            $this->handleWin($seat);
        }
        
        // 顺时针下一个玩家
        $this->currentPlayer = ($seat + 1) % 4;
        $this->round++;
        
        return [
            'success' => true,
            'playedCards' => array_map(fn($c) => $c->toArray(), $playedCards),
            'nextPlayer' => $this->currentPlayer
        ];
    }
    
    /**
     * 处理胜利
     */
    private function handleWin(int $winnerSeat): void
    {
        $winner = $this->players[$winnerSeat];
        $winnerTeam = $winner->team;
        
        // 检查队伍是否获胜
        $teammateSeat = $winner->getTeammateSeat();
        if (isset($this->players[$teammateSeat]) && $this->players[$teammateSeat]->isEmptyHand()) {
            // 队伍获胜
            $this->status = 'finished';
        }
        
        // 升级逻辑
        $winner->levelUp();
        if (isset($this->players[$teammateSeat])) {
            $this->players[$teammateSeat]->levelUp();
        }
    }
    
    /**
     * 获取玩家信息
     */
    public function getPlayer(int $seat): ?Player
    {
        return $this->players[$seat] ?? null;
    }
    
    /**
     * 获取所有玩家
     */
    public function getPlayers(): array
    {
        return array_values($this->players);
    }
    
    /**
     * 获取游戏状态
     */
    public function getState(): array
    {
        return [
            'gameId' => $this->gameId,
            'status' => $this->status,
            'currentPlayer' => $this->currentPlayer,
            'round' => $this->round,
            'players' => array_map(fn($p) => $p->toArray(), $this->players),
            'lastPlay' => $this->lastPlay
        ];
    }
    
    /**
     * 检查游戏是否结束
     */
    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }
    
    /**
     * 获取获胜队伍
     */
    public function getWinningTeam(): ?int
    {
        foreach ($this->players as $player) {
            if ($player->hasWon()) {
                return $player->team;
            }
        }
        return null;
    }
}
