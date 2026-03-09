<?php
/**
 * NanningPokerGame - 南宁拖拉机游戏规则（完整版）
 * 根据官方规则文档实现
 * 
 * 规则要点:
 * - 4 人游戏，2 队对抗（对家为友）
 * - 4 副扑克（216 张），每人 54 张，无底牌
 * - 叫牌定庄，黑桃 2 有叫牌权
 * - 主牌：大王 > 小王 > 主级牌 > 副级牌 > 主 2 > 副 2 > A > K > ... > 3
 * - 牌型：单张、对子、拖拉机、三张、推土机、四张、飞机
 * - 计分：只有闲家赢圈计分，总分 480 分
 * - 扣牌：最后一圈非单张有倍数
 * - 升级：<80 升 3 级，80-159 升 2 级，160-239 升 1 级，≥160 上庄
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
    
    // 计分：只有闲家得分
    private int $opponentScore = 0;
    
    // 主牌信息
    private string $trumpSuit = '';
    private string $trumpRank = '3';  // 从 3 开始打
    private int $currentLevel = 3;
    
    // 游戏流程
    private int $currentPlayer = 0;
    private int $landlordSeat = 0;
    private int $landlordTeam = 0;
    private int $round = 1;
    private int $totalRounds = 54;  // 每人 54 张
    
    // 叫牌相关
    private array $bids = [];
    private bool $biddingComplete = false;
    
    // 分数牌：5=10 分，10=10 分，K=10 分（4 副牌共 480 分）
    private const SCORE_CARDS = ['5' => 10, '10' => 10, 'K' => 10];
    private const TOTAL_SCORE = 480;
    private const SHANGZHUANG_LINE = 160;  // 上庄线
    private const LEVEL_2_LINE = 80;       // 升 2 级线
    private const LEVEL_3_LINE = 0;        // 升 3 级线
    
    // 牌型常量
    private const TYPE_SINGLE = 'single';
    private const TYPE_PAIR = 'pair';
    private const TYPE_TRACTOR = 'tractor';      // 连对
    private const TYPE_TRIPLE = 'triple';        // 三张
    private const TYPE_TRIPLE_TRACTOR = 'triple_tractor';  // 推土机
    private const TYPE_QUAD = 'quad';            // 四张
    private const TYPE_PLANE = 'plane';          // 飞机
    
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
     * 开始游戏（叫牌阶段）
     */
    public function startGame(): bool
    {
        if (count($this->players) !== 4) {
            return false;
        }
        
        // 生成 4 副牌（216 张）
        $this->deck = PokerCard::createDecks(4);
        PokerCard::shuffleCards($this->deck);
        
        // 发牌（每人 54 张，无底牌）
        $this->dealCards();
        
        // 进入叫牌阶段
        $this->status = 'bidding';
        $this->findHeiTao2Player();
        
        return true;
    }
    
    /**
     * 找到黑桃 2 持有者（有叫牌权）
     */
    private function findHeiTao2Player(): int
    {
        foreach ($this->players as $seat => $player) {
            $hand = $player->getHand();
            foreach ($hand as $card) {
                if ($card['suit'] === 'spade' && $card['rank'] === '2') {
                    return $seat;
                }
            }
        }
        // 默认第一个玩家
        return 0;
    }
    
    /**
     * 叫牌
     * @param int $seat 玩家座位
     * @param string $suit 叫的花色
     * @param int $count 叫的张数（1-4）
     */
    public function bid(int $seat, string $suit, int $count = 1): array
    {
        if ($this->status !== 'bidding') {
            return ['success' => false, 'message' => '不在叫牌阶段'];
        }
        
        // 验证玩家是否有叫牌权
        if (!$this->canBid($seat)) {
            return ['success' => false, 'message' => '没有叫牌权'];
        }
        
        // 记录叫牌
        $this->bids[] = [
            'seat' => $seat,
            'suit' => $suit,
            'count' => $count,
            'time' => time()
        ];
        
        // 检查是否可以反叫
        $canOvercall = $this->canOvercall($seat, $count);
        
        return [
            'success' => true,
            'bid' => $this->bids[count($this->bids) - 1],
            'canOvercall' => $canOvercall,
            'nextBidder' => $this->getNextBidder($seat)
        ];
    }
    
    /**
     * 检查是否有叫牌权
     */
    private function canBid(int $seat): bool
    {
        if (empty($this->bids)) {
            // 第一轮：黑桃 2 持有者有叫牌权
            return $seat === $this->findHeiTao2Player();
        }
        
        // 后续：按顺序
        $lastBid = $this->bids[count($this->bids) - 1];
        return $seat === $this->getNextBidder($lastBid['seat']);
    }
    
    /**
     * 检查是否可以反叫
     */
    private function canOvercall(int $seat, int $count): bool
    {
        if (empty($this->bids)) {
            return true;
        }
        
        $lastBid = $this->bids[count($this->bids) - 1];
        
        // 张数必须更多
        if ($count <= $lastBid['count']) {
            return false;
        }
        
        // 检查玩家是否有足够的级牌
        $player = $this->players[$seat];
        $hand = $player->getHand();
        $levelCards = 0;
        foreach ($hand as $card) {
            if ($card['rank'] === $this->trumpRank) {
                $levelCards++;
            }
        }
        
        return $levelCards >= $count;
    }
    
    /**
     * 获取下一个有叫牌权的玩家
     */
    private function getNextBidder(int $currentSeat): int
    {
        return ($currentSeat + 1) % 4;
    }
    
    /**
     * 结束叫牌，确定庄家
     */
    public function finishBidding(): array
    {
        if (empty($this->bids)) {
            return ['success' => false, 'message' => '没有叫牌'];
        }
        
        // 找到最大的叫牌（张数最多）
        $maxBid = $this->bids[0];
        foreach ($this->bids as $bid) {
            if ($bid['count'] > $maxBid['count']) {
                $maxBid = $bid;
            }
        }
        
        // 确定庄家和主花色
        $this->landlordSeat = $maxBid['seat'];
        $this->landlordTeam = $this->players[$maxBid['seat']]->team;
        $this->trumpSuit = $maxBid['suit'];
        $this->players[$maxBid['seat']]->isLandlord = true;
        
        $this->biddingComplete = true;
        $this->status = 'playing';
        $this->currentPlayer = $maxBid['seat'];
        
        return [
            'success' => true,
            'landlord' => $maxBid['seat'],
            'trumpSuit' => $this->trumpSuit,
            'trumpRank' => $this->trumpRank
        ];
    }
    
    /**
     * 发牌（每人 54 张，无底牌）
     */
    private function dealCards(): void
    {
        $cardsPerPlayer = 54;  // 216/4 = 54
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
        if ($this->status !== 'playing') {
            return ['success' => false, 'message' => '游戏未开始'];
        }
        
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
        
        // 验证出牌
        $validation = $this->validatePlay($seat, $playedCards);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // 记录出牌
        $trickScore = $this->calculateTrickScore($playedCards);
        $this->currentTrick[] = [
            'seat' => $seat,
            'cards' => $playedCards,
            'score' => $trickScore,
            'type' => $this->getCardType($playedCards)
        ];
        
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
            'nextPlayer' => $this->currentPlayer,
            'trickComplete' => count($this->currentTrick) >= 4
        ];
    }
    
    /**
     * 验证出牌（跟牌规则）
     */
    private function validatePlay(int $seat, array $cards): array
    {
        if (empty($this->currentTrick)) {
            return ['valid' => true];  // 首家可以任意出
        }
        
        $leadCards = $this->currentTrick[0]['cards'];
        $leadSuit = $leadCards[0]['suit'];
        
        $player = $this->players[$seat];
        $hand = $player->getHand();
        
        // 检查是否有首攻花色
        $hasLeadSuit = false;
        $sameSuitCards = [];
        foreach ($hand as $card) {
            if ($card['suit'] === $leadSuit) {
                $hasLeadSuit = true;
                $sameSuitCards[] = $card;
            }
        }
        
        // 没有首攻花色，可以任意出（垫牌或毙牌）
        if (!$hasLeadSuit) {
            return ['valid' => true];
        }
        
        // 有首攻花色，必须跟该花色
        $playedSuit = $cards[0]['suit'];
        if ($playedSuit !== $leadSuit) {
            // 检查出的牌是否为主牌（毙牌）
            $isTrump = false;
            foreach ($cards as $card) {
                if ($this->isTrumpCard($card)) {
                    $isTrump = true;
                    break;
                }
            }
            
            if (!$isTrump) {
                return [
                    'valid' => false,
                    'message' => "必须跟{$leadSuit}花色"
                ];
            }
            // 毙牌允许
            return ['valid' => true, 'isTrump' => true];
        }
        
        // 跟了首攻花色，检查牌型是否匹配
        $leadType = HandType::identify($leadCards);
        $playedType = HandType::identify($cards);
        
        // 牌型必须相同（单张跟单张，对子跟对子等）
        if ($leadType['type'] !== $playedType['type']) {
            return [
                'valid' => false,
                'message' => "必须跟相同牌型（{$leadType['type']}）"
            ];
        }
        
        // 数量必须相同
        if (count($cards) !== count($leadCards)) {
            return [
                'valid' => false,
                'message' => "必须出相同数量的牌"
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 检查单张牌是否为主牌
     */
    private function isTrumpCard(array $card): bool
    {
        // 大小王
        if ($card['suit'] === 'joker') {
            return true;
        }
        // 级牌
        if ($card['rank'] === $this->trumpRank) {
            return true;
        }
        // 所有 2
        if ($card['rank'] === '2') {
            return true;
        }
        // 主花色
        if ($card['suit'] === $this->trumpSuit) {
            return true;
        }
        return false;
    }
    
    /**
     * 获取牌型
     */
    private function getCardType(array $cards): string
    {
        $result = HandType::identify($cards);
        return $result['type'];
    }
    
    /**
     * 计算本轮分数（只有闲家赢才计分）
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
        
        // 只有闲家赢才计分
        if ($winnerTeam !== $this->landlordTeam) {
            // 如果是最后一圈，检查是否有扣牌
            if ($this->isLastRound()) {
                $trickScore = $this->calculateDeduction($trickScore);
            }
            $this->opponentScore += $trickScore;
        }
        
        // 赢家下一轮先出
        $this->currentPlayer = $winnerSeat;
        $this->currentTrick = [];
        $this->playedRounds[] = $this->currentTrick;
        
        // 检查是否结束
        $this->checkGameEnd();
    }
    
    /**
     * 计算扣牌（最后一圈倍数）
     */
    private function calculateDeduction(int $score): int
    {
        if (empty($this->currentTrick)) {
            return $score;
        }
        
        $firstPlay = $this->currentTrick[0];
        $cardCount = count($firstPlay['cards']);
        
        // 只有非单张才有倍数
        if ($firstPlay['type'] === self::TYPE_SINGLE) {
            return $score;
        }
        
        // 扣牌分 = 得分牌总分 × 出牌数量
        return $score * $cardCount;
    }
    
    /**
     * 确定本轮赢家
     */
    private function determineTrickWinner(): int
    {
        $winner = $this->currentTrick[0]['seat'];
        $highestCards = $this->currentTrick[0]['cards'];
        $highestType = $this->currentTrick[0]['type'];
        $leadSuit = $highestCards[0]['suit'];
        
        foreach ($this->currentTrick as $play) {
            $comparison = $this->comparePlay($play['cards'], $highestCards, $leadSuit);
            if ($comparison > 0) {
                $winner = $play['seat'];
                $highestCards = $play['cards'];
                $highestType = $play['type'];
            }
        }
        
        return $winner;
    }
    
    /**
     * 比较两家的出牌
     * @param array $cards1 当前玩家的牌
     * @param array $cards2 当前最大的牌
     * @param string $leadSuit 首攻花色
     * @return int 1=cards1 大，-1=cards2 大
     */
    private function comparePlay(array $cards1, array $cards2, string $leadSuit): int
    {
        $type1 = HandType::identify($cards1);
        $type2 = HandType::identify($cards2);
        
        // 检查是否为主牌
        $isTrump1 = $this->isTrumpPlay($cards1);
        $isTrump2 = $this->isTrumpPlay($cards2);
        $isLeadSuit1 = $cards1[0]['suit'] === $leadSuit;
        $isLeadSuit2 = $cards2[0]['suit'] === $leadSuit;
        
        // 毙牌情况：主牌 > 副牌
        if ($isTrump1 && !$isTrump2) {
            return 1;  // 毙牌大
        }
        if (!$isTrump1 && $isTrump2) {
            return -1;
        }
        
        // 都是主牌或都是副牌，按牌型比较
        if ($type1['type'] !== $type2['type']) {
            // 牌型不同，首家大
            return -1;
        }
        
        // 牌型相同，比较大小
        return HandType::compare($cards1, $cards2, $this->trumpSuit, $this->trumpRank);
    }
    
    /**
     * 检查是否为毙牌（主牌毙副牌）
     */
    private function isTrumpPlay(array $cards): bool
    {
        foreach ($cards as $card) {
            // 大小王
            if ($card['suit'] === 'joker') {
                return true;
            }
            // 级牌
            if ($card['rank'] === $this->trumpRank) {
                return true;
            }
            // 所有 2
            if ($card['rank'] === '2') {
                return true;
            }
            // 主花色
            if ($card['suit'] === $this->trumpSuit) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 比较牌大小（根据官方规则）
     * 大王 > 小王 > 主级牌 > 副级牌 > 主 2 > 副 2 > A > K > ... > 3
     */
    private function isHigherCard(array $card1, array $card2, string $type1, string $type2): bool
    {
        // 同牌型比较
        if ($type1 !== $type2) {
            // 不同牌型：首家牌型大（除非被毙）
            return false;
        }
        
        $value1 = $this->getCardValue($card1);
        $value2 = $this->getCardValue($card2);
        
        return $value1 > $value2;
    }
    
    /**
     * 获取牌值（考虑主牌）
     */
    private function getCardValue(array $card): int
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
        if ($card['rank'] === $this->trumpRank && $card['suit'] === $this->trumpSuit) {
            return 800;
        }
        
        // 副级牌（其他花色的级牌）
        if ($card['rank'] === $this->trumpRank) {
            // 按花色：黑>红>梅>方
            $suitOrder = ['spade' => 4, 'heart' => 3, 'club' => 2, 'diamond' => 1];
            return 700 + ($suitOrder[$card['suit']] ?? 0);
        }
        
        // 主 2
        if ($card['rank'] === '2' && $card['suit'] === $this->trumpSuit) {
            return 600;
        }
        
        // 副 2
        if ($card['rank'] === '2') {
            $suitOrder = ['spade' => 4, 'heart' => 3, 'club' => 2, 'diamond' => 1];
            return 500 + ($suitOrder[$card['suit']] ?? 0);
        }
        
        // 主花色牌
        if ($card['suit'] === $this->trumpSuit) {
            return 400 + $this->getRankValue($card['rank']);
        }
        
        // 副牌
        return $this->getRankValue($card['rank']);
    }
    
    /**
     * 获取牌面值
     */
    private function getRankValue(string $rank): int
    {
        $values = [
            'A' => 14, 'K' => 13, 'Q' => 12, 'J' => 11,
            '10' => 10, '9' => 9, '8' => 8, '7' => 7,
            '6' => 6, '5' => 5, '4' => 4, '3' => 3
        ];
        return $values[$rank] ?? 0;
    }
    
    /**
     * 是否最后一轮
     */
    private function isLastRound(): bool
    {
        return $this->round >= $this->totalRounds;
    }
    
    /**
     * 检查游戏结束
     */
    private function checkGameEnd(): void
    {
        if ($this->round >= $this->totalRounds) {
            $this->calculateLevelUp();
            $this->status = 'finished';
        }
    }
    
    /**
     * 计算升级（根据官方规则）
     * <80 分：升 3 级
     * 80-159 分：升 2 级
     * 160-239 分：升 1 级
     * ≥160 分：上庄
     */
    private function calculateLevelUp(): void
    {
        $score = $this->opponentScore;
        $levels = 0;
        $shangzhuang = false;
        
        if ($score < self::LEVEL_3_LINE) {
            // 负分，每 -80 分多升 1 级
            $levels = 3 + (int)(abs($score) / 80);
        } elseif ($score < self::LEVEL_2_LINE) {
            $levels = 3;
        } elseif ($score < self::SHANGZHUANG_LINE) {
            $levels = 2;
        } elseif ($score < 240) {
            $levels = 1;
        } else {
            // ≥160 分，上庄
            $shangzhuang = true;
            $levels = 1 + (int)(($score - self::SHANGZHUANG_LINE) / 80);
        }
        
        // 升级
        for ($i = 0; $i < $levels; $i++) {
            $this->players[$this->landlordSeat]->levelUp();
            $teammate = $this->getTeammateSeat($this->landlordSeat);
            if (isset($this->players[$teammate])) {
                $this->players[$teammate]->levelUp();
            }
        }
        
        // 上庄
        if ($shangzhuang) {
            $this->changeLandlord();
        }
        
        // 检查是否获胜（打到 A）
        $this->checkWin();
    }
    
    /**
     * 获取队友座位
     */
    private function getTeammateSeat(int $seat): int
    {
        return ($seat + 2) % 4;  // 对家为友
    }
    
    /**
     * 换庄
     */
    private function changeLandlord(): void
    {
        $this->players[$this->landlordSeat]->isLandlord = false;
        
        // 找到对手方
        $newLandlord = ($this->landlordSeat + 1) % 4;
        $this->landlordSeat = $newLandlord;
        $this->landlordTeam = $this->players[$newLandlord]->team;
        $this->players[$newLandlord]->isLandlord = true;
    }
    
    /**
     * 检查是否获胜（打到 A）
     */
    private function checkWin(): void
    {
        // 检查庄家方是否打到 A
        $landlordPlayer = $this->players[$this->landlordSeat];
        $teammate = $this->players[$this->getTeammateSeat($this->landlordSeat)];
        
        // TODO: 检查是否打到 A（需要 Player 类记录当前级别）
    }
    
    /**
     * 从手牌移除
     */
    private function removeCardsFromHand(int $seat, array $indices): void
    {
        $player = $this->players[$seat];
        // TODO: 实现手牌移除逻辑
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
            'landlordTeam' => $this->landlordTeam,
            'trumpSuit' => $this->trumpSuit,
            'trumpRank' => $this->trumpRank,
            'currentLevel' => $this->currentLevel,
            'round' => $this->round,
            'opponentScore' => $this->opponentScore,
            'bids' => $this->bids,
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
    
    public function getScore(): int
    {
        return $this->opponentScore;
    }
}
