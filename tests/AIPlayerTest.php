<?php
/**
 * AI 玩家功能测试
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\AIPlayer;
use App\Classes\NanningPokerGame;

echo "🤖 AI 玩家功能测试\n";
echo "========================================\n\n";

$passed = 0;
$failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ {$name}\n";
        $passed++;
    } else {
        echo "❌ {$name}\n";
        $failed++;
    }
}

// 测试 1: AI 玩家创建
echo "📌 测试 1: AI 玩家创建\n";
$aiEasy = new AIPlayer(0, AIPlayer::DIFFICULTY_EASY);
$aiMedium = new AIPlayer(1, AIPlayer::DIFFICULTY_MEDIUM);
$aiHard = new AIPlayer(2, AIPlayer::DIFFICULTY_HARD);

test('容易难度 AI 创建', $aiEasy->getDifficulty() === AIPlayer::DIFFICULTY_EASY);
test('中等难度 AI 创建', $aiMedium->getDifficulty() === AIPlayer::DIFFICULTY_MEDIUM);
test('困难难度 AI 创建', $aiHard->getDifficulty() === AIPlayer::DIFFICULTY_HARD);
test('AI 玩家标识', $aiEasy->isAI === true);

// 测试 2: 游戏添加 AI 玩家
echo "\n📌 测试 2: 游戏添加 AI 玩家\n";
$game = new NanningPokerGame('test_ai_game');

// 添加 2 个真人 + 2 个 AI
$game->addPlayer(1, '玩家 1');
$game->addPlayer(2, '玩家 2');
$game->addAIPlayer('easy');
$game->addAIPlayer('hard');

test('添加 4 个玩家成功', count($game->getState()['players']) === 4);

$state = $game->getState();
$aiCount = 0;
foreach ($state['players'] as $player) {
    if ($player['isAI']) {
        $aiCount++;
    }
}
test('AI 玩家数量正确', $aiCount === 2);

// 测试 3: AI 出牌决策
echo "\n📌 测试 3: AI 出牌决策\n";

// 准备手牌
$testCards = [
    ['rank' => '3', 'suit' => 'heart', 'value' => 3],
    ['rank' => '4', 'suit' => 'heart', 'value' => 4],
    ['rank' => '5', 'suit' => 'spade', 'value' => 5],  // 分牌
    ['rank' => '10', 'suit' => 'club', 'value' => 10], // 分牌
    ['rank' => 'K', 'suit' => 'diamond', 'value' => 13], // 分牌
    ['rank' => '2', 'suit' => 'heart', 'value' => 2],
    ['rank' => 'A', 'suit' => 'heart', 'value' => 14],
];

$game->startGame();
$game->bid(2, 'heart');  // AI 叫牌
$game->finishBidding();

// 获取 AI 玩家
$aiPlayers = $game->getAIPlayers();
test('获取 AI 玩家列表', count($aiPlayers) === 2);

// 测试 4: 难度差异
echo "\n📌 测试 4: 难度差异\n";
$easyAI = new AIPlayer(10, AIPlayer::DIFFICULTY_EASY);
$hardAI = new AIPlayer(11, AIPlayer::DIFFICULTY_HARD);

// 模拟游戏状态
$gameState = [
    'currentTrick' => [],
    'trumpSuit' => 'heart',
    'trumpRank' => '3',
    'round' => 1,
    'opponentScore' => 0,
    'teams' => [0, 1, 0, 1]
];

// 合法出牌选项
$validCards = [
    [['rank' => '3', 'suit' => 'heart', 'value' => 3]],
    [['rank' => '4', 'suit' => 'heart', 'value' => 4]],
    [['rank' => '5', 'suit' => 'spade', 'value' => 5]],
];

$easyDecision = $easyAI->decidePlay($gameState, $validCards);
$hardDecision = $hardAI->decidePlay($gameState, $validCards);

test('容易 AI 能出牌', !empty($easyDecision));
test('困难 AI 能出牌', !empty($hardDecision));

// 测试 5: 跟牌逻辑
echo "\n📌 测试 5: 跟牌逻辑\n";
$gameState2 = [
    'currentTrick' => [
        [
            'seat' => 0,
            'cards' => [['rank' => '3', 'suit' => 'heart', 'value' => 3]],
            'type' => 'single'
        ]
    ],
    'trumpSuit' => 'heart',
    'trumpRank' => '3',
    'round' => 5,
    'opponentScore' => 20,
    'teams' => [0, 1, 0, 1]
];

$validCards2 = [
    [['rank' => '4', 'suit' => 'heart', 'value' => 4]],
    [['rank' => 'K', 'suit' => 'heart', 'value' => 13]],
];

$mediumAI = new AIPlayer(12, AIPlayer::DIFFICULTY_MEDIUM);
$followDecision = $mediumAI->decidePlay($gameState2, $validCards2);

test('中等 AI 能跟牌', !empty($followDecision));

// 测试 6: 记牌功能（困难模式）
echo "\n📌 测试 6: 困难 AI 记牌功能\n";
$hardAI2 = new AIPlayer(13, AIPlayer::DIFFICULTY_HARD);

// 模拟已出的牌
$gameState3 = [
    'currentTrick' => [
        [
            'seat' => 0,
            'cards' => [
                ['rank' => '5', 'suit' => 'heart', 'value' => 5],
                ['rank' => '5', 'suit' => 'spade', 'value' => 5]
            ],
            'type' => 'pair'
        ]
    ],
    'trumpSuit' => 'heart',
    'trumpRank' => '3',
    'round' => 10,
    'opponentScore' => 10,
    'teams' => [0, 1, 0, 1]
];

$hardAI2->decidePlay($gameState3, [[['rank' => '3', 'suit' => 'diamond', 'value' => 3]]]);

// 检查记牌
$remaining5 = $hardAI2->getRemainingCount('5');
test('困难 AI 能记牌', $remaining5 < 4);  // 已出 2 张 5

echo "\n========================================\n";
echo "结果：✅ {$passed} | ❌ {$failed}\n\n";

if ($failed === 0) {
    echo "🎉 所有 AI 玩家测试通过！\n";
    exit(0);
} else {
    echo "⚠️  有 {$failed} 项测试失败\n";
    exit(1);
}
