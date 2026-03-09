<?php
/**
 * Card 类 - 卡牌基础类
 */

namespace App\Classes;

class Card
{
    public int $id;
    public string $name;
    public ?string $description;
    public string $cardType;
    public ?string $manaCost;
    public ?int $power;
    public ?int $toughness;
    public string $rarity;
    public ?string $setCode;
    public ?string $imageUrl;
    
    // 游戏状态
    public bool $isTapped = false;
    public bool $hasSummoningSickness = true;
    public int $currentPower;
    public int $currentToughness;
    public array $enchantments = [];
    
    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->name = $data['name'] ?? 'Unknown Card';
        $this->description = $data['description'] ?? null;
        $this->cardType = $data['card_type'] ?? $data['cardType'] ?? 'creature';
        $this->manaCost = $data['mana_cost'] ?? $data['manaCost'] ?? null;
        $this->power = $data['power'] ?? null;
        $this->toughness = $data['toughness'] ?? null;
        $this->rarity = $data['rarity'] ?? 'common';
        $this->setCode = $data['set_code'] ?? $data['setCode'] ?? null;
        $this->imageUrl = $data['image_url'] ?? $data['imageUrl'] ?? null;
        
        $this->currentPower = $this->power ?? 0;
        $this->currentToughness = $this->toughness ?? 0;
    }
    
    /**
     * 从数据库加载卡牌
     */
    public static function fromDatabase(int $cardId, \PDO $pdo): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ?");
        $stmt->execute([$cardId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * 攻击
     */
    public function attack(): array
    {
        if ($this->isTapped) {
            return ['success' => false, 'message' => 'Card is already tapped'];
        }
        
        if ($this->hasSummoningSickness) {
            return ['success' => false, 'message' => 'Card has summoning sickness'];
        }
        
        if ($this->cardType !== 'creature') {
            return ['success' => false, 'message' => 'Only creatures can attack'];
        }
        
        $this->isTapped = true;
        return [
            'success' => true,
            'power' => $this->currentPower,
            'card' => $this->toArray()
        ];
    }
    
    /**
     * 横置
     */
    public function tap(): bool
    {
        if ($this->isTapped) {
            return false;
        }
        $this->isTapped = true;
        return true;
    }
    
    /**
     * 重置
     */
    public function untap(): bool
    {
        if (!$this->isTapped) {
            return false;
        }
        $this->isTapped = false;
        return true;
    }
    
    /**
     * 施加伤害
     */
    public function takeDamage(int $damage): int
    {
        $this->currentToughness -= $damage;
        return $this->currentToughness;
    }
    
    /**
     * 治疗
     */
    public function heal(int $amount, int $maxToughness): int
    {
        $this->currentToughness = min($this->currentToughness + $amount, $maxToughness);
        return $this->currentToughness;
    }
    
    /**
     * 检查是否死亡
     */
    public function isDead(): bool
    {
        return $this->currentToughness <= 0;
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
            'cardType' => $this->cardType,
            'manaCost' => $this->manaCost,
            'power' => $this->power,
            'toughness' => $this->toughness,
            'currentPower' => $this->currentPower,
            'currentToughness' => $this->currentToughness,
            'rarity' => $this->rarity,
            'isTapped' => $this->isTapped,
            'hasSummoningSickness' => $this->hasSummoningSickness,
            'imageUrl' => $this->imageUrl
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
