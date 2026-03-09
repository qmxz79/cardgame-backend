<?php
/**
 * AI 玩家类
 * 支持三个难度级别：容易、中等、困难
 */

namespace App\Classes;

class AIPlayer extends Player
{
    public const DIFFICULTY_EASY = 'easy';
    public const DIFFICULTY_MEDIUM = 'medium';
    public const DIFFICULTY_HARD = 'hard';
    
    private string $difficulty;
    private array $playedCards = [];  // 已出的牌（用于困难模式记牌）
    private array $trickHistory = [];  // 每圈出牌历史
    
    public function __construct(int $seat, string $difficulty = self::DIFFICULTY_MEDIUM)
    {
        parent::__construct(0, "AI_玩家_{$seat}", $seat);
        $this->difficulty = $difficulty;
        $this->isAI = true;
    }
    
    public function getDifficulty(): string
    {
        return $this->difficulty;
    }
    
    /**
     * AI 出牌决策
     */
    public function decidePlay(array $gameState, array $validCards): array
    {
        // 记录已出的牌
        $this->updatePlayedCards($gameState);
        
        switch ($this->difficulty) {
            case self::DIFFICULTY_EASY:
                return $this->easyPlay($validCards);
            
            case self::DIFFICULTY_MEDIUM:
                return $this->mediumPlay($gameState, $validCards);
            
            case self::DIFFICULTY_HARD:
                return $this->hardPlay($gameState, $validCards);
            
            default:
                return $this->mediumPlay($gameState, $validCards);
        }
    }
    
    /**
     * 容易模式：随机出牌
     */
    private function easyPlay(array $validCards): array
    {
        // 随机选择一组合法的牌
        $randomIndex = array_rand($validCards);
        return $validCards[$randomIndex];
    }
    
    /**
     * 中等模式：基础策略
     * - 首家出牌：出小牌
     * - 跟牌：尽量跟牌，能赢则赢
     * - 有分牌时谨慎出牌
     */
    private function mediumPlay(array $gameState, array $validCards): array
    {
        $isFirstPlayer = empty($gameState['currentTrick'] ?? []);
        $trumpSuit = $gameState['trumpSuit'] ?? '';
        $trumpRank = $gameState['trumpRank'] ?? '';
        
        // 首家出牌
        if ($isFirstPlayer) {
            return $this->mediumLeadPlay($validCards, $trumpSuit, $trumpRank);
        }
        
        // 跟牌
        return $this->mediumFollowPlay($gameState, $validCards, $trumpSuit, $trumpRank);
    }
    
    /**
     * 中等模式 - 首家出牌
     */
    private function mediumLeadPlay(array $validCards, string $trumpSuit, string $trumpRank): array
    {
        // 优先出非分牌的小牌
        $safeCards = [];
        $scoreCards = [];
        
        foreach ($validCards as $cards) {
            if ($this->hasScoreCards($cards)) {
                $scoreCards[] = $cards;
            } else {
                $safeCards[] = $cards;
            }
        }
        
        // 优先出安全牌（不含分）
        if (!empty($safeCards)) {
            // 出最小的安全牌
            return $this->getSmallestCards($safeCards, $trumpSuit, $trumpRank);
        }
        
        // 没有安全牌，出最小的分牌
        if (!empty($scoreCards)) {
            return $this->getSmallestCards($scoreCards, $trumpSuit, $trumpRank);
        }
        
        return $validCards[array_rand($validCards)];
    }
    
    /**
     * 中等模式 - 跟牌
     */
    private function mediumFollowPlay(array $gameState, array $validCards, string $trumpSuit, string $trumpRank): array
    {
        $currentTrick = $gameState['currentTrick'] ?? [];
        $leadCards = $currentTrick[0]['cards'] ?? [];
        $myTeam = $gameState['teams'][$this->seat] ?? 0;
        
        // 检查是否能赢
        $winningCards = [];
        $losingCards = [];
        
        foreach ($validCards as $cards) {
            if ($this->canWinTrick($cards, $currentTrick, $trumpSuit, $trumpRank)) {
                $winningCards[] = $cards;
            } else {
                $losingCards[] = $cards;
            }
        }
        
        // 如果队友目前最大，尽量输（保存实力）
        $teammateSeat = ($this->seat + 2) % 4;
        $teammateIsLeading = false;
        foreach ($currentTrick as $play) {
            if ($play['seat'] === $teammateSeat) {
                $teammateIsLeading = true;
                break;
            }
        }
        
        if ($teammateIsLeading && !empty($losingCards)) {
            // 队友领先，出最小的牌
            return $this->getSmallestCards($losingCards, $trumpSuit, $trumpRank);
        }
        
        // 能赢则赢
        if (!empty($winningCards)) {
            return $this->getSmallestCards($winningCards, $trumpSuit, $trumpRank);
        }
        
        // 不能赢，出最小的牌
        if (!empty($losingCards)) {
            return $this->getSmallestCards($losingCards, $trumpSuit, $trumpRank);
        }
        
        return $validCards[array_rand($validCards)];
    }
    
    /**
     * 困难模式：高级策略
     * - 记牌：记录已出的牌
     * - 算分：计算剩余分牌
     * - 团队配合：与队友配合
     */
    private function hardPlay(array $gameState, array $validCards): array
    {
        $isFirstPlayer = empty($gameState['currentTrick'] ?? []);
        $trumpSuit = $gameState['trumpSuit'] ?? '';
        $trumpRank = $gameState['trumpRank'] ?? '';
        $round = $gameState['round'] ?? 1;
        $opponentScore = $gameState['opponentScore'] ?? 0;
        
        // 首家出牌
        if ($isFirstPlayer) {
            return $this->hardLeadPlay($validCards, $trumpSuit, $trumpRank, $round, $opponentScore);
        }
        
        // 跟牌
        return $this->hardFollowPlay($gameState, $validCards, $trumpSuit, $trumpRank);
    }
    
    /**
     * 困难模式 - 首家出牌
     */
    private function hardLeadPlay(array $validCards, string $trumpSuit, string $trumpRank, int $round, int $opponentScore): array
    {
        // 早期：出安全牌
        // 中期：根据分数情况
        // 后期：冲刺或保守
        
        $earlyGame = $round <= 18;  // 前 1/3
        $lateGame = $round >= 45;   // 后 1/3
        
        $safeCards = [];
        $scoreCards = [];
        
        foreach ($validCards as $cards) {
            if ($this->hasScoreCards($cards)) {
                $scoreCards[] = $cards;
            } else {
                $safeCards[] = $cards;
            }
        }
        
        if ($earlyGame) {
            // 早期：出小牌，保留实力
            if (!empty($safeCards)) {
                return $this->getSmallestCards($safeCards, $trumpSuit, $trumpRank);
            }
        }
        
        if ($lateGame) {
            // 后期：根据分数决定
            if ($opponentScore >= 120) {
                // 对手分数高，需要保守
                if (!empty($safeCards)) {
                    return $this->getSmallestCards($safeCards, $trumpSuit, $trumpRank);
                }
            } else {
                // 对手分数低，可以主动出分牌
                if (!empty($scoreCards)) {
                    // 出大的分牌，争取赢圈
                    return $this->getLargestCards($scoreCards, $trumpSuit, $trumpRank);
                }
            }
        }
        
        // 默认：出安全牌
        if (!empty($safeCards)) {
            return $this->getSmallestCards($safeCards, $trumpSuit, $trumpRank);
        }
        
        return $validCards[array_rand($validCards)];
    }
    
    /**
     * 困难模式 - 跟牌
     */
    private function hardFollowPlay(array $gameState, array $validCards, string $trumpSuit, string $trumpRank): array
    {
        $currentTrick = $gameState['currentTrick'] ?? [];
        $leadCards = $currentTrick[0]['cards'] ?? [];
        
        // 计算是否能赢
        $winningCards = [];
        $losingCards = [];
        
        foreach ($validCards as $cards) {
            if ($this->canWinTrick($cards, $currentTrick, $trumpSuit, $trumpRank)) {
                $winningCards[] = $cards;
            } else {
                $losingCards[] = $cards;
            }
        }
        
        // 检查队友是否领先
        $teammateSeat = ($this->seat + 2) % 4;
        $teammateIsLeading = false;
        $currentLeader = 0;
        
        // 简化：假设当前最大牌的玩家是领先的
        if (!empty($currentTrick)) {
            $currentLeader = $currentTrick[0]['seat'];
            $teammateIsLeading = ($currentLeader === $teammateSeat);
        }
        
        // 检查圈中是否有分牌
        $trickScore = $this->calculateTrickScore($currentTrick);
        
        if ($teammateIsLeading) {
            // 队友领先：尽量输（保存实力），除非圈中有大量分
            if ($trickScore >= 20 && !empty($winningCards)) {
                // 圈中有大量分，尽量赢
                return $this->getSmallestCards($winningCards, $trumpSuit, $trumpRank);
            }
            if (!empty($losingCards)) {
                return $this->getSmallestCards($losingCards, $trumpSuit, $trumpRank);
            }
        } else {
            // 对手领先
            if ($trickScore >= 20) {
                // 圈中有大量分，尽量赢
                if (!empty($winningCards)) {
                    return $this->getSmallestCards($winningCards, $trumpSuit, $trumpRank);
                }
            }
            // 分少或没分，保存实力
            if (!empty($losingCards)) {
                return $this->getSmallestCards($losingCards, $trumpSuit, $trumpRank);
            }
        }
        
        if (!empty($winningCards)) {
            return $this->getSmallestCards($winningCards, $trumpSuit, $trumpRank);
        }
        
        return $validCards[array_rand($validCards)];
    }
    
    /**
     * 检查牌组是否包含分牌
     */
    private function hasScoreCards(array $cards): bool
    {
        foreach ($cards as $card) {
            if (in_array($card['rank'], ['5', '10', 'K'])) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 获取最小的牌组
     */
    private function getSmallestCards(array $cardGroups, string $trumpSuit, string $trumpRank): array
    {
        $minValue = PHP_INT_MAX;
        $minCards = $cardGroups[0];
        
        foreach ($cardGroups as $cards) {
            $value = $this->getCardsValue($cards, $trumpSuit, $trumpRank, false);
            if ($value < $minValue) {
                $minValue = $value;
                $minCards = $cards;
            }
        }
        
        return $minCards;
    }
    
    /**
     * 获取最大的牌组
     */
    private function getLargestCards(array $cardGroups, string $trumpSuit, string $trumpRank): array
    {
        $maxValue = 0;
        $maxCards = $cardGroups[0];
        
        foreach ($cardGroups as $cards) {
            $value = $this->getCardsValue($cards, $trumpSuit, $trumpRank, false);
            if ($value > $maxValue) {
                $maxValue = $value;
                $maxCards = $cards;
            }
        }
        
        return $maxCards;
    }
    
    /**
     * 计算牌组总价值
     */
    private function getCardsValue(array $cards, string $trumpSuit, string $trumpRank, bool $useGameValue = true): int
    {
        $total = 0;
        foreach ($cards as $card) {
            $total += HandType::getCardValue($card, $trumpSuit, $trumpRank);
        }
        return $total;
    }
    
    /**
     * 检查是否能赢这一圈
     */
    private function canWinTrick(array $myCards, array $currentTrick, string $trumpSuit, string $trumpRank): bool
    {
        if (empty($currentTrick)) {
            return true;
        }
        
        // 简化比较：检查是否有更大的牌
        $leadCards = $currentTrick[0]['cards'];
        $comparison = HandType::compare($myCards, $leadCards, $trumpSuit, $trumpRank);
        
        return $comparison > 0;
    }
    
    /**
     * 计算一圈中的分数
     */
    private function calculateTrickScore(array $currentTrick): int
    {
        $score = 0;
        foreach ($currentTrick as $play) {
            foreach ($play['cards'] as $card) {
                if ($card['rank'] === '5') {
                    $score += 5;
                } elseif ($card['rank'] === '10' || $card['rank'] === 'K') {
                    $score += 10;
                }
            }
        }
        return $score;
    }
    
    /**
     * 更新已出的牌记录
     */
    private function updatePlayedCards(array $gameState): void
    {
        $currentTrick = $gameState['currentTrick'] ?? [];
        
        foreach ($currentTrick as $play) {
            foreach ($play['cards'] as $card) {
                $key = $card['suit'] . '_' . $card['rank'];
                if (!isset($this->playedCards[$key])) {
                    $this->playedCards[$key] = 0;
                }
                $this->playedCards[$key]++;
            }
        }
    }
    
    /**
     * 获取剩余牌的数量（困难模式用）
     */
    public function getRemainingCount(string $rank, string $suit = ''): int
    {
        // 4 副牌，每张牌最多 4 张
        $totalCount = 4;
        $playedCount = 0;
        
        foreach ($this->playedCards as $key => $count) {
            if ($suit) {
                if ($key === $suit . '_' . $rank) {
                    $playedCount += $count;
                }
            } else {
                if (strpos($key, '_' . $rank) !== false) {
                    $playedCount += $count;
                }
            }
        }
        
        return $totalCount - $playedCount;
    }
}
