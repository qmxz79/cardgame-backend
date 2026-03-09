<?php
/**
 * PokerCard 类 - 扑克牌
 * 支持 4 副扑克（含大小王），共 216 张
 */

namespace App\Classes;

class PokerCard
{
    public int $id;
    public string $suit;        // 花色：♠ ♥ ♦ ♣ joker
    public string $rank;        // 点数：2,3,4,5,6,7,8,9,10,J,Q,K,A,小王，大王
    public int $value;          // 牌值（用于比较大小）
    public int $deckIndex;      // 第几副牌 (0-3)
    
    // 牌型值（用于比较）
    private const CARD_VALUES = [
        '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10,
        'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14,
        '小王' => 15, '大王' => 16
    ];
    
    // 花色顺序
    private const SUIT_ORDER = ['♣', '♦', '♥', '♠', 'joker'];
    
    public function __construct(string $suit, string $rank, int $deckIndex = 0)
    {
        static $idCounter = 0;
        $this->id = ++$idCounter;
        $this->suit = $suit;
        $this->rank = $rank;
        $this->deckIndex = $deckIndex;
        $this->value = self::CARD_VALUES[$rank] ?? 0;
        
        // 大王 > 小王
        if ($rank === '大王') {
            $this->value = 100;
        } elseif ($rank === '小王') {
            $this->value = 99;
        }
    }
    
    /**
     * 生成一副完整的扑克牌（54 张）
     */
    public static function createDeck(int $deckIndex = 0): array
    {
        $cards = [];
        $suits = ['♠', '♥', '♦', '♣'];
        $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        
        // 数字牌
        foreach ($suits as $suit) {
            foreach ($ranks as $rank) {
                $cards[] = new self($suit, $rank, $deckIndex);
            }
        }
        
        // 大小王
        $cards[] = new self('joker', '小王', $deckIndex);
        $cards[] = new self('joker', '大王', $deckIndex);
        
        return $cards;
    }
    
    /**
     * 生成 4 副完整的扑克牌（216 张）
     */
    public static function createDecks(int $count = 4): array
    {
        $allCards = [];
        for ($i = 0; $i < $count; $i++) {
            $allCards = array_merge($allCards, self::createDeck($i));
        }
        return $allCards;
    }
    
    /**
     * 洗牌
     */
    public static function shuffleCards(array &$cards): void
    {
        shuffle($cards);
    }
    
    /**
     * 获取牌的面值显示
     */
    public function getDisplayName(): string
    {
        if ($this->suit === 'joker') {
            return $this->rank;
        }
        return "{$this->suit}{$this->rank}";
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'suit' => $this->suit,
            'rank' => $this->rank,
            'value' => $this->value,
            'deckIndex' => $this->deckIndex,
            'displayName' => $this->getDisplayName()
        ];
    }
    
    /**
     * 比较两张牌的大小
     */
    public function compareTo(self $other): int
    {
        if ($this->value > $other->value) return 1;
        if ($this->value < $other->value) return -1;
        return 0;
    }
}
