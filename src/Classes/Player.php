<?php
/**
 * Player 类 - 玩家
 * 4 人游戏，2 队（对家为队友）
 */

namespace App\Classes;

class Player
{
    public int $id;
    public string $username;
    public int $seat;           // 座位号 0-3
    public int $team;           // 队伍 0 或 1（0 和 2 为队 0，1 和 3 为队 1）
    
    /** @var PokerCard[] 手牌 */
    private array $hand = [];
    
    public int $level = 2;      // 当前级别（从 2 开始打到 A）
    public int $score = 0;      // 得分
    public bool $isLandlord = false; // 是否庄家
    
    public function __construct(int $playerId, string $username, int $seat)
    {
        $this->id = $playerId;
        $this->username = $username;
        $this->seat = $seat;
        $this->team = $seat % 2; // 0 和 2 为队 0，1 和 3 为队 1
    }
    
    /**
     * 发牌
     */
    public function dealCards(array $cards): void
    {
        $this->hand = $cards;
        $this->sortHand();
    }
    
    /**
     * 整理手牌（按牌值排序）
     */
    public function sortHand(): void
    {
        usort($this->hand, function(PokerCard $a, PokerCard $b) {
            return $b->value - $a->value; // 从大到小
        });
    }
    
    /**
     * 出牌
     */
    public function playCards(array $cardIndices): array
    {
        $playedCards = [];
        
        foreach ($cardIndices as $index) {
            if (isset($this->hand[$index])) {
                $playedCards[] = $this->hand[$index];
                array_splice($this->hand, $index, 1);
            }
        }
        
        return $playedCards;
    }
    
    /**
     * 获取手牌数量
     */
    public function getHandSize(): int
    {
        return count($this->hand);
    }
    
    /**
     * 获取手牌
     */
    public function getHand(): array
    {
        return array_map(fn($card) => $card->toArray(), $this->hand);
    }
    
    /**
     * 检查是否出完牌
     */
    public function isEmptyHand(): bool
    {
        return empty($this->hand);
    }
    
    /**
     * 升级
     */
    public function levelUp(): void
    {
        $levels = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        $currentIndex = array_search($this->getLevelName(), $levels);
        
        if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
            $this->level++;
        }
    }
    
    /**
     * 获取级别名称
     */
    public function getLevelName(): string
    {
        $levels = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        return $levels[min($this->level - 2, count($levels) - 1)];
    }
    
    /**
     * 检查是否获胜（打到 A）
     */
    public function hasWon(): bool
    {
        return $this->getLevelName() === 'A';
    }
    
    /**
     * 获取队友座位号
     */
    public function getTeammateSeat(): int
    {
        return ($this->seat + 2) % 4; // 对家是队友
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'seat' => $this->seat,
            'team' => $this->team,
            'level' => $this->level,
            'levelName' => $this->getLevelName(),
            'score' => $this->score,
            'isLandlord' => $this->isLandlord,
            'handSize' => count($this->hand),
            'teammateSeat' => $this->getTeammateSeat()
        ];
    }
}
