<?php
/**
 * Deck 类 - 卡组管理类
 */

namespace App\Classes;

class Deck
{
    public int $id;
    public int $userId;
    public string $name;
    public ?string $description;
    public string $format;
    public bool $isPublic;
    
    /** @var Card[] */
    private array $cards = [];
    
    /** @var Card[] 手牌 */
    private array $hand = [];
    
    /** @var Card[] 战场 */
    private array $battlefield = [];
    
    /** @var Card[] 墓地 */
    private array $graveyard = [];
    
    /** @var Card[] 除外区 */
    private array $exile = [];
    
    public int $life = 20;
    public int $mana = 0;
    public int $maxMana = 0;

    /**
     * 添加卡牌到牌库
     */
    public function addCard(Card $card): void
    {
        $this->cards[] = $card;
    }
    
    /**
     * 获取牌库数量
     */
    public function getDeckSize(): int
    {
        return count($this->cards);
    }
    
    /**
     * 获取手牌数量
     */
    public function getHandSize(): int
    {
        return count($this->hand);
    }
    
    /**
     * 获取战场数量
     */
    public function getBattlefieldSize(): int
    {
        return count($this->battlefield);
    }

    
    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->userId = $data['user_id'] ?? $data['userId'] ?? 0;
        $this->name = $data['name'] ?? 'Untitled Deck';
        $this->description = $data['description'] ?? null;
        $this->format = $data['format'] ?? 'standard';
        $this->isPublic = (bool)($data['is_public'] ?? $data['isPublic'] ?? false);
    }
    
    /**
     * 从数据库加载卡组
     */
    public static function fromDatabase(int $deckId, \PDO $pdo): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM decks WHERE id = ?");
        $stmt->execute([$deckId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        $deck = new self($data);
        $deck->loadCards($pdo);
        return $deck;
    }
    
    /**
     * 加载卡组中的卡牌
     */
    private function loadCards(\PDO $pdo): void
    {
        $stmt = $pdo->prepare("
            SELECT c.* FROM cards c
            JOIN deck_cards dc ON c.id = dc.card_id
            WHERE dc.deck_id = ?
        ");
        $stmt->execute([$this->id]);
        $cards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($cards as $cardData) {
            $card = new Card($cardData);
            $this->cards[] = $card;
        }
    }
    
    /**
     * 洗牌
     */
    public function shuffle(): void
    {
        shuffle($this->cards);
    }
    
    /**
     * 抽牌
     */
    public function drawCard(int $count = 1): array
    {
        $drawn = [];
        
        for ($i = 0; $i < $count; $i++) {
            if (empty($this->cards)) {
                // 牌库空了，从墓地洗牌
                if (!empty($this->graveyard)) {
                    $this->cards = $this->graveyard;
                    $this->graveyard = [];
                    $this->shuffle();
                } else {
                    break; // 真的没牌了
                }
            }
            
            $card = array_pop($this->cards);
            if ($card) {
                $this->hand[] = $card;
                $drawn[] = $card;
            }
        }
        
        return $drawn;
    }
    
    /**
     * 从手牌打出卡牌
     */
    public function playCard(int $handIndex): ?Card
    {
        if (!isset($this->hand[$handIndex])) {
            return null;
        }
        
        $card = $this->hand[$handIndex];
        
        // 检查法力是否足够
        $manaCost = $this->calculateManaCost($card->manaCost);
        if ($this->mana < $manaCost) {
            return null;
        }
        
        $this->mana -= $manaCost;
        array_splice($this->hand, $handIndex, 1);
        
        if ($card->cardType === 'creature' || $card->cardType === 'artifact') {
            $this->battlefield[] = $card;
        } else if ($card->cardType === 'land') {
            $this->battlefield[] = $card;
            $this->maxMana++;
            $this->mana = $this->maxMana;
        }
        
        return $card;
    }
    
    /**
     * 计算法力消耗
     */
    private function calculateManaCost(?string $manaCost): int
    {
        if (!$manaCost) {
            return 0;
        }
        
        $total = 0;
        preg_match_all('/(\d+)/', $manaCost, $matches);
        
        foreach ($matches[1] as $num) {
            $total += (int)$num;
        }
        
        // 计算彩色法力
        preg_match_all('/([WUBRGC])/', $manaCost, $colorMatches);
        $total += count($colorMatches[1]);
        
        return $total;
    }
    
    /**
     * 开始回合
     */
    public function startTurn(): void
    {
        $this->mana = $this->maxMana;
        
        // 重置所有战场上的卡牌
        foreach ($this->battlefield as $card) {
            $card->untap();
            $card->hasSummoningSickness = false;
        }
        
        // 抽一张牌
        $this->drawCard(1);
    }
    
    /**
     * 卡牌进入墓地
     */
    public function putToGraveyard(Card $card): void
    {
        $this->graveyard[] = $card;
        
        // 从战场移除
        $index = array_search($card, $this->battlefield, true);
        if ($index !== false) {
            array_splice($this->battlefield, $index, 1);
        }
        
        // 从手牌移除
        $index = array_search($card, $this->hand, true);
        if ($index !== false) {
            array_splice($this->hand, $index, 1);
        }
    }
    
    /**
     * 获取卡组统计
     */
    public function getStats(): array
    {
        $stats = [
            'totalCards' => count($this->cards),
            'creatures' => 0,
            'spells' => 0,
            'artifacts' => 0,
            'lands' => 0,
            'averageManaCost' => 0,
            'manaCurve' => []
        ];
        
        $totalMana = 0;
        
        foreach ($this->cards as $card) {
            $stats[$card->cardType . 's']++;
            $manaCost = $this->calculateManaCost($card->manaCost);
            $totalMana += $manaCost;
            
            if (!isset($stats['manaCurve'][$manaCost])) {
                $stats['manaCurve'][$manaCost] = 0;
            }
            $stats['manaCurve'][$manaCost]++;
        }
        
        $stats['averageManaCost'] = $stats['totalCards'] > 0 
            ? round($totalMana / $stats['totalCards'], 2) 
            : 0;
        
        ksort($stats['manaCurve']);
        
        return $stats;
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'format' => $this->format,
            'isPublic' => $this->isPublic,
            'life' => $this->life,
            'mana' => $this->mana,
            'maxMana' => $this->maxMana,
            'deckSize' => count($this->cards),
            'handSize' => count($this->hand),
            'battlefieldSize' => count($this->battlefield),
            'graveyardSize' => count($this->graveyard),
            'stats' => $this->getStats()
        ];
    }
    
    /**
     * 转换为 JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
