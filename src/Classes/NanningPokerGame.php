<?php
/**
 * NanningPokerGame - 南宁拖拉机游戏规则
 * 4 人游戏，2 队对抗，结合南宁本地玩法
 */

namespace App\Classes;

class NanningPokerGame
{
    public string $gameId;
    public string $status = 'waiting';
    
    /** @var Player[] */
    private array $players = [];
    
    /** @var PokerCard[] */
    private array $deck = [];
    
    private array $playedRounds = [];
    private array $currentTrick = [];
    
    private string $trumpSuit = '';
    private string $trumpRank = '2';
    private int $currentPlayer = 0;
    private int $round = 1;
    
    // 南宁规则
    private const RULES = [
        'must_follow_suit' => true,
        'trump_can_change' => true,
    ];
    
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
        PokerCard::shuffleCards($this->deck);
        
        // 发牌
        $this->dealCards();
        
        // 随机确定庄家
        $this->currentPlayer = array_rand($this->players);
        $this->players[$this->currentPlayer]->isLandlord = true;
        
        $this->trumpRank = '2';
        $this->trumpSuit = '';
        $this->status = 'playing';
        $this->round = 1;
        
        return true;
    }
    
    /**
     * 发牌
     */
    private function dealCards(): void
    {
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
        $playedCards = [];
        
        // 出牌
        foreach ($cardIndices as $index) {
            if (isset($player->getHand()[$index])) {
                $hand = $player->getHand();
                $cardData = $hand[$index];
                $playedCards[] = $cardData;
            }
        }
        
        if (empty($playedCards)) {
            return ['success' => false, 'message' => 'No cards to play'];
        }
        
        // 记录
        $this->currentTrick[] = [
            'seat' => $seat,
            'cards' => $playedCards
        ];
        
        // 确定主花色
        if (empty($this->trumpSuit) && $playedCards[0]['suit'] !== 'joker') {
            $this->trumpSuit = $playedCards[0]['suit'];
            echo "🎴 主牌确定：{$this->trumpSuit}\n";
        }
        
        // 检查是否出完
        $this->removeCardsFromHand($seat, count($cardIndices));
        
        if ($this->players[$seat]->isEmptyHand()) {
            $this->handleWin($seat);
        }
        
        // 一轮结束或下一个玩家
        if (count($this->currentTrick) >= 4) {
            $this->currentPlayer = $this->determineTrickWinner();
            $this->currentTrick = [];
        } else {
            $this->currentPlayer = ($seat + 1) % 4;
        }
        
        $this->round++;
        
        return [
            'success' => true,
            'playedCards' => $playedCards,
            'trumpSuit' => $this->trumpSuit,
            'nextPlayer' => $this->currentPlayer
        ];
    }
    
    /**
     * 从手牌移除
     */
    private function removeCardsFromHand(int $seat, int $count): void
    {
        // 简化处理
    }
    
    /**
     * 确定赢家
     */
    private function determineTrickWinner(): int
    {
        return $this->currentTrick[0]['seat']; // 简化
    }
    
    /**
     * 处理胜利
     */
    private function handleWin(int $seat): void
    {
        $player = $this->players[$seat];
        $player->score += 10;
        
        $teammateSeat = $player->getTeammateSeat();
        if (isset($this->players[$teammateSeat]) && $this->players[$teammateSeat]->isEmptyHand()) {
            $this->status = 'finished';
        }
    }
    
    public function getPlayers(): array
    {
        return array_values($this->players);
    }
    
    public function getPlayer(int $seat): ?Player
    {
        return $this->players[$seat] ?? null;
    }
    
    public function getState(): array
    {
        return [
            'gameId' => $this->gameId,
            'status' => $this->status,
            'currentPlayer' => $this->currentPlayer,
            'trumpSuit' => $this->trumpSuit,
            'trumpRank' => $this->trumpRank,
            'players' => array_map(fn($p) => $p->toArray(), $this->players)
        ];
    }
    
    public function getTrumpInfo(): array
    {
        return [
            'trumpSuit' => $this->trumpSuit,
            'trumpRank' => $this->trumpRank
        ];
    }
}
