<?php
/**
 * 牌型识别与比较工具类
 * 支持：单张、对子、拖拉机、三张、推土机、四张、飞机
 */

namespace App\Classes;

class HandType
{
    // 牌型常量
    public const TYPE_SINGLE = 'single';
    public const TYPE_PAIR = 'pair';
    public const TYPE_TRACTOR = 'tractor';
    public const TYPE_TRIPLE = 'triple';
    public const TYPE_TRIPLE_TRACTOR = 'triple_tractor';
    public const TYPE_QUAD = 'quad';
    public const TYPE_PLANE = 'plane';
    public const TYPE_MIXED = 'mixed';
    
    /**
     * 识别牌型
     * @param array $cards 牌数组
     * @return array ['type' => 牌型，'ranks' => 涉及的牌值，'count' => 牌数]
     */
    public static function identify(array $cards): array
    {
        $count = count($cards);
        
        if ($count === 0) {
            return ['type' => null, 'ranks' => [], 'count' => 0];
        }
        
        if ($count === 1) {
            return [
                'type' => self::TYPE_SINGLE,
                'ranks' => [$cards[0]['rank']],
                'count' => 1
            ];
        }
        
        // 统计每个牌值的数量
        $rankGroups = [];
        foreach ($cards as $card) {
            $rank = $card['rank'];
            if (!isset($rankGroups[$rank])) {
                $rankGroups[$rank] = [];
            }
            $rankGroups[$rank][] = $card;
        }
        
        ksort($rankGroups);
        
        $groupCounts = array_map('count', $rankGroups);
        $maxCount = max($groupCounts);
        $groupCount = count($rankGroups);
        
        // 对子检查
        if ($maxCount === 2 && $groupCount === 1) {
            return [
                'type' => self::TYPE_PAIR,
                'ranks' => array_keys($rankGroups),
                'count' => 2
            ];
        }
        
        // 拖拉机检查（2 个+相连对子）
        if ($maxCount === 2 && $groupCount >= 2) {
            if (self::isConsecutiveRanks(array_keys($rankGroups))) {
                return [
                    'type' => self::TYPE_TRACTOR,
                    'ranks' => array_keys($rankGroups),
                    'count' => $count,
                    'length' => $groupCount
                ];
            }
        }
        
        // 三张检查
        if ($maxCount === 3 && $groupCount === 1) {
            return [
                'type' => self::TYPE_TRIPLE,
                'ranks' => array_keys($rankGroups),
                'count' => 3
            ];
        }
        
        // 推土机检查（2 个+相连三张）
        if ($maxCount === 3 && $groupCount >= 2) {
            if (self::isConsecutiveRanks(array_keys($rankGroups))) {
                return [
                    'type' => self::TYPE_TRIPLE_TRACTOR,
                    'ranks' => array_keys($rankGroups),
                    'count' => $count,
                    'length' => $groupCount
                ];
            }
        }
        
        // 四张检查
        if ($maxCount === 4 && $groupCount === 1) {
            return [
                'type' => self::TYPE_QUAD,
                'ranks' => array_keys($rankGroups),
                'count' => 4
            ];
        }
        
        // 飞机检查（2 个+相连四张）
        if ($maxCount === 4 && $groupCount >= 2) {
            if (self::isConsecutiveRanks(array_keys($rankGroups))) {
                return [
                    'type' => self::TYPE_PLANE,
                    'ranks' => array_keys($rankGroups),
                    'count' => $count,
                    'length' => $groupCount
                ];
            }
        }
        
        // 默认：单张组合
        return [
            'type' => self::TYPE_MIXED,
            'ranks' => array_keys($rankGroups),
            'count' => $count
        ];
    }
    
    /**
     * 检查牌值是否连续
     * @param array $ranks 牌值数组
     * @return bool
     */
    private static function isConsecutiveRanks(array $ranks): bool
    {
        if (count($ranks) < 2) {
            return false;
        }
        
        $rankOrder = ['3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A', '2'];
        $indices = [];
        
        foreach ($ranks as $rank) {
            $index = array_search($rank, $rankOrder);
            if ($index === false) {
                return false;
            }
            $indices[] = $index;
        }
        
        sort($indices);
        
        for ($i = 1; $i < count($indices); $i++) {
            if ($indices[$i] - $indices[$i - 1] !== 1) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 比较两个相同牌型的大小
     * @param array $cards1 第一组牌
     * @param array $cards2 第二组牌
     * @param string $trumpSuit 主花色
     * @param string $trumpRank 级牌
     * @return int 1=cards1 大，-1=cards2 大，0=相同
     */
    public static function compare(array $cards1, array $cards2, string $trumpSuit, string $trumpRank): int
    {
        $type1 = self::identify($cards1);
        $type2 = self::identify($cards2);
        
        // 牌型不同，无法比较（首家牌型大）
        if ($type1['type'] !== $type2['type']) {
            return 1;  // 首家大
        }
        
        // 牌数量不同，无法比较
        if ($type1['count'] !== $type2['count']) {
            return 1;
        }
        
        // 获取最大牌值进行比较
        $maxRank1 = self::getMaxRankValue($cards1, $trumpSuit, $trumpRank);
        $maxRank2 = self::getMaxRankValue($cards2, $trumpSuit, $trumpRank);
        
        if ($maxRank1 > $maxRank2) {
            return 1;
        } elseif ($maxRank1 < $maxRank2) {
            return -1;
        }
        
        return 0;
    }
    
    /**
     * 获取一组牌中的最大牌值
     */
    private static function getMaxRankValue(array $cards, string $trumpSuit, string $trumpRank): int
    {
        $maxValue = 0;
        
        foreach ($cards as $card) {
            $value = self::getCardValue($card, $trumpSuit, $trumpRank);
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }
        
        return $maxValue;
    }
    
    /**
     * 获取单张牌值（考虑主牌）
     */
    public static function getCardValue(array $card, string $trumpSuit, string $trumpRank): int
    {
        // 大王
        if ($card['suit'] === 'joker' && $card['rank'] === 'red') {
            return 1000;
        }
        
        // 小王
        if ($card['suit'] === 'joker' && $card['rank'] === 'black') {
            return 900;
        }
        
        // 主级牌
        if ($card['rank'] === $trumpRank && $card['suit'] === $trumpSuit) {
            return 800;
        }
        
        // 副级牌
        if ($card['rank'] === $trumpRank) {
            $suitOrder = ['spade' => 77, 'heart' => 76, 'club' => 75, 'diamond' => 74];
            return 700 + ($suitOrder[$card['suit']] ?? 0);
        }
        
        // 主 2
        if ($card['rank'] === '2' && $card['suit'] === $trumpSuit) {
            return 600;
        }
        
        // 副 2
        if ($card['rank'] === '2') {
            $suitOrder = ['spade' => 57, 'heart' => 56, 'club' => 55, 'diamond' => 54];
            return 500 + ($suitOrder[$card['suit']] ?? 0);
        }
        
        // 主花色牌
        if ($card['suit'] === $trumpSuit) {
            return 400 + self::getBaseRankValue($card['rank']);
        }
        
        // 副牌
        return self::getBaseRankValue($card['rank']);
    }
    
    /**
     * 获取基础牌面值
     */
    private static function getBaseRankValue(string $rank): int
    {
        $values = [
            'A' => 14, 'K' => 13, 'Q' => 12, 'J' => 11,
            '10' => 10, '9' => 9, '8' => 8, '7' => 7,
            '6' => 6, '5' => 5, '4' => 4, '3' => 3
        ];
        return $values[$rank] ?? 0;
    }
    
    /**
     * 检查是否可以跟牌
     * @param array $hand 手牌
     * @param array $leadCards 首家出的牌
     * @return array ['canFollow' => bool, 'message' => string]
     */
    public static function canFollow(array $hand, array $leadCards): array
    {
        if (empty($leadCards)) {
            return ['canFollow' => true, 'message' => ''];
        }
        
        $leadType = self::identify($leadCards);
        $leadSuit = $leadCards[0]['suit'];
        $leadCount = count($leadCards);
        
        // 检查手牌中是否有首攻花色
        $hasLeadSuit = false;
        $sameSuitCards = [];
        foreach ($hand as $card) {
            if ($card['suit'] === $leadSuit) {
                $hasLeadSuit = true;
                $sameSuitCards[] = $card;
            }
        }
        
        if (!$hasLeadSuit) {
            // 没有首攻花色，可以任意出（垫牌或毙牌）
            return ['canFollow' => true, 'message' => ''];
        }
        
        // 有首攻花色，必须跟同花色、同牌型、同数量
        if (count($sameSuitCards) < $leadCount) {
            // 同花色牌不够，可以出其他牌
            return ['canFollow' => true, 'message' => ''];
        }
        
        // 检查是否能组成相同牌型
        // TODO: 复杂的牌型匹配逻辑
        
        return ['canFollow' => true, 'message' => ''];
    }
}
