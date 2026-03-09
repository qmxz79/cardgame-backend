<?php
/**
 * NanningPokerGame - 南宁拖拉机游戏规则（完整版）
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
    private array $trickScores = [0, 0];  // 两队得分
    
    private string $trumpSuit = '';
    private string $trumpRank = '2';
    private int $currentPlayer = 0;
    private int $round = 1;
    private int $landlordSeat = 0;
    
    // 底牌（8 张）
    private array $bottomCards = [];
    
    // 分数牌：5=5 分，10=10 分，K=10 分
    private const SCORE_CARDS = ['5' => 5, '10' => 10, 'K' => 10];
    
    // 南宁规则
    private const RULES = [
        'must_follow_suit' => true,      // 必须跟花色
        'trump_can_change' => true,      // 可以反主
        'bottom_cards_count' => 8,       // 底牌 8 张
        'score_5' => 5,                  // 5 的分值
        'score_10' => 10,                // 10 的分值
        'score_k' => 10,                 // K 的分值
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
        if (count($this->players) !== 4) {
            return false;
        }
        
        // 生成 4 副牌（216 张）
        $this->deck = PokerCard::createDecks(4);
        PokerCard::shuffleCards($this->deck);
        
        // 留 8 张底牌
        $this->bottomCards = array_splice($this->deck, 0, 8);
        
        // 发牌（每人 52 张）
        $this->dealCards();
        
        // 随机确定庄家
        $this->landlordSeat = array_rand($this->players);
        $this->currentPlayer = $this->landlordSeat;
        $this->players[$this->landlordSeat]->isLandlord = true;
        
        $this->trumpRank = '2';
        $this->trumpSuit = '';
        $this->status = 'playing';
        $this->round = 1;
        $this->trickScores = [0, 0];
        
        return true;
    }
    
    /**
     * 发牌（每人 52 张，剩余 8 张为底牌）
     */
    private function dealCards(): void
    {
        $cardsPerPlayer = 52;  // (216-8)/4 = 52
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
        $playedCards = $this->getCardsFromHand($seat, $cardIndices);
        
        if (empty($playedCards)) {
            return ['success' => false, 'message' => 'No cards to play'];
        }
        
        // 验证出牌（南宁规则：必须跟花色）
        $validation = $this->validatePlay($seat, $playedCards);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // 记录出牌
        $this->currentTrick[] = [
            'seat' => $seat,
            'cards' => $playedCards,
            'score' => $this->calculateTrickScore($playedCards)
        ];
        
        // 确定主花色（第一张非王牌）
        if (empty($this->trumpSuit)) {
            foreach ($playedCards as $card) {
                if ($card['suit'] !== 'joker') {
                    $this->trumpSuit = $card['suit'];
                    break;
                }
            }
        }
        
        // 从手牌移除
        $this->removeCardsFromHand($seat, $cardIndices);
        
        // 一轮结束（4 人都出牌）
        if (count($this->currentTrick) >= 4) {
            $this->completeTrick();
        } else {
            $this->currentPlayer = ($seat + 1) % 4;
        }
        
        $this->round++;
        
        return [
            'success' => true,
            'playedCards' => $playedCards,
            'trumpSuit' => $this->trumpSuit,
            'trumpRank' => $this->trumpRank,
            'nextPlayer' => $this->currentPlayer,
            'trickComplete' => count($this->currentTrick) >= 4
        ];
    }
    
    /**
     * 获取手牌中的牌
     */
    private function getCardsFromHand(int $seat, array $indices): array
    {
        $player = $this->players[$seat];
        $hand = $player->getHand();
        $cards = [];
        
        foreach ($indices as $index) {
            if (isset($hand[$index])) {
                $cards[] = $hand[$index];
            }
        }
        
        return $cards;
    }
    
    /**
     * 验证出牌
     */
    private function validatePlay(int $seat, array $cards): array
    {
        if (empty($this->currentTrick)) {
            return ['valid' => true];  // 首家可以任意出
        }
        
        // 必须跟首家的花色（南宁规则）
        $leadSuit = $this->currentTrick[0]['cards'][0]['suit'];
        $player = $this->players[$seat];
        $hand = $player->getHand();
        
        // 检查是否有首攻花色
        $hasLeadSuit = false;
        foreach ($hand as $card) {
            if ($card['suit'] === $leadSuit) {
                $hasLeadSuit = true;
                break;
            }
        }
        
        // 如果有该花色但没跟，违规
        if ($hasLeadSuit && $cards[0]['suit'] !== $leadSuit) {
            return [
                'valid' => false,
                'message' => "必须跟{$leadSuit}花色"
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 计算本轮分数
     */
    private function calculateTrickScore(array $cards): int
    {
        $score = 0;
        foreach ($cards as $card) {
            $rank = $card['rank'];
            if (isset(self::SCORE_CARDS[$rank])) {
                $score += self::SCORE_CARDS[$rank];
            }
        }
        return $score;
    }
    
    /**
     * 完成一轮
     */
    private function completeTrick(): void
    {
        // 确定赢家
        $winnerSeat = $this->determineTrickWinner();
        $winnerTeam = $this->players[$winnerSeat]->team;
        
        // 计算本轮分数
        $trickScore = 0;
        foreach ($this->currentTrick as $play) {
            $trickScore += $play['score'];
        }
        
        // 赢家得分
        $this->trickScores[$winnerTeam] += $trickScore;
        
        // 如果是最后一轮，庄家获得底牌分
        if ($this->isLastRound()) {
            $bottomScore = $this->calculateBottomScore();
            if ($winnerSeat === $this->landlordSeat) {
                // 庄家赢，底牌分归庄家队
                $this->trickScores[$winnerTeam] += $bottomScore;
            } else {
                // 闲家赢，抠底，分数翻倍
                $this->trickScores[$winnerTeam] += $bottomScore * 2;
            }
        }
        
        // 赢家下一轮先出
        $this->currentPlayer = $winnerSeat;
        $this->currentTrick = [];
        
        // 检查是否结束
        $this->checkGameEnd();
    }
    
    /**
     * 确定本轮赢家
     */
    private function determineTrickWinner(): int
    {
        $winner = $this->currentTrick[0]['seat'];
        $highestCard = $this->currentTrick[0]['cards'][0];
        
        foreach ($this->currentTrick as $play) {
            foreach ($play['cards'] as $card) {
                if ($this->isHigherCard($card, $highestCard)) {
                    $winner = $play['seat'];
                    $highestCard = $card;
                }
            }
        }
        
        return $winner;
    }
    
    /**
     * 比较牌大小（考虑主牌）
     */
    private function isHigherCard(array $card1, array $card2): bool
    {
        // 都是主牌
        $isTrump1 = $this->isTrump($card1);
        $isTrump2 = $this->isTrump($card2);
        
        if ($isTrump1 && !$isTrump2) return true;
        if (!$isTrump1 && $isTrump2) return false;
        
        // 同花色或同为主牌，比较牌值
        if ($card1['suit'] === $card2['suit']) {
            return $card1['value'] > $card2['value'];
        }
        
        return false;
    }
    
    /**
     * 是否为主牌
     */
    private function isTrump(array $card): bool
    {
        // 大小王是主
        if ($card['suit'] === 'joker') return true;
        
        // 主花色是主
        if (!empty($this->trumpSuit) && $card['suit'] === $this->trumpSuit) return true;
        
        // 级牌是主
        if ($card['rank'] === $this->trumpRank) return true;
        
        return false;
    }
    
    /**
     * 计算底牌分
     */
    private function calculateBottomScore(): int
    {
        $score = 0;
        foreach ($this->bottomCards as $card) {
            $rank = $card['rank'];
            if (isset(self::SCORE_CARDS[$rank])) {
                $score += self::SCORE_CARDS[$rank];
            }
        }
        return $score;
    }
    
    /**
     * 是否最后一轮
     */
    private function isLastRound(): bool
    {
        foreach ($this->players as $player) {
            if ($player->getHandSize() > 0) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * 检查游戏结束
     */
    private function checkGameEnd(): void
    {
        // 所有玩家手牌为 0 时结束
        $allEmpty = true;
        foreach ($this->players as $player) {
            if ($player->getHandSize() > 0) {
                $allEmpty = false;
                break;
            }
        }
        
        if ($allEmpty) {
            $this->calculateLevelUp();
            $this->status = 'finished';
        }
    }
    
    /**
     * 计算升级（南宁规则）
     */
    private function calculateLevelUp(): void
    {
        // 闲家得分判断升级
        $opponentTeam = ($this->players[$this->landlordSeat]->team + 1) % 2;
        $opponentScore = $this->trickScores[$opponentTeam];
        
        // 南宁规则简化版：
        // 闲家 0-35 分：庄家升 3 级
        // 闲家 40-75 分：庄家升 2 级
        // 闲家 80-115 分：庄家升 1 级
        // 闲家 120-155 分：庄家下庄
        // 闲家 160+ 分：闲家升 1 级并坐庄
        
        $levels = 0;
        $newLandlord = $this->landlordSeat;
        
        if ($opponentScore < 40) {
            $levels = 3;
        } elseif ($opponentScore < 80) {
            $levels = 2;
        } elseif ($opponentScore < 120) {
            $levels = 1;
        } elseif ($opponentScore >= 160) {
            // 闲家上台
            $levels = 1;
            $newLandlord = $this->findOpponentPlayer();
        } else {
            // 闲家 120-155 分，庄家下庄
            $newLandlord = $this->findOpponentPlayer();
        }
        
        // 升级
        for ($i = 0; $i < $levels; $i++) {
            $this->players[$this->landlordSeat]->levelUp();
            $teammate = $this->players[$this->landlordSeat]->getTeammateSeat();
            if (isset($this->players[$teammate])) {
                $this->players[$teammate]->levelUp();
            }
        }
        
        // 换庄
        if ($newLandlord !== $this->landlordSeat) {
            $this->players[$this->landlordSeat]->isLandlord = false;
            $this->landlordSeat = $newLandlord;
            $this->players[$newLandlord]->isLandlord = true;
        }
    }
    
    /**
     * 找到对手玩家
     */
    private function findOpponentPlayer(): int
    {
        $landlordTeam = $this->players[$this->landlordSeat]->team;
        foreach ($this->players as $seat => $player) {
            if ($player->team !== $landlordTeam) {
                return $seat;
            }
        }
        return 0;
    }
    
    /**
     * 从手牌移除
     */
    private function removeCardsFromHand(int $seat, array $indices): void
    {
        // 简化实现：实际需要根据索引移除对应的牌
        $player = $this->players[$seat];
        // 这里需要 Player 类支持按索引移除
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
            'landlordSeat' => $this->landlordSeat,
            'trumpSuit' => $this->trumpSuit,
            'trumpRank' => $this->trumpRank,
            'round' => $this->round,
            'scores' => $this->trickScores,
            'players' => array_map(fn($p) => $p->toArray(), $this->players)
        ];
    }
    
    public function getTrumpInfo(): array
    {
        return [
            'trumpSuit' => $this->trumpSuit,
            'trumpRank' => $this->trumpRank,
            'isNoTrump' => empty($this->trumpSuit)
        ];
    }
    
    public function getScores(): array
    {
        return $this->trickScores;
    }
}
