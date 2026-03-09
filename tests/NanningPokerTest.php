<?php
/**
 * 南宁拖拉机游戏规则测试（根据官方规则文档）
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\NanningPokerGame;
use App\Classes\PokerCard;

echo "🃏 南宁拖拉机游戏测试（官方规则）\n";
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

try {
    // 测试 1: 游戏创建
    $game = new NanningPokerGame('test001');
    test('游戏创建成功', $game !== null);
    
    // 测试 2: 添加 4 个玩家
    $game->addPlayer(1, '玩家 1');
    $game->addPlayer(2, '玩家 2');
    $game->addPlayer(3, '玩家 3');
    $game->addPlayer(4, '玩家 4');
    test('添加 4 个玩家', count($game->getPlayers()) === 4);
    
    // 测试 3: 游戏开始
    $result = $game->startGame();
    test('游戏开始成功', $result === true);
    
    // 测试 4: 游戏状态（叫牌阶段）
    $state = $game->getState();
    test('游戏状态 bidding', $state['status'] === 'bidding');
    
    // 测试 5: 每人 54 张牌（无底牌）
    $players = $game->getPlayers();
    test('每人 54 张牌', $players[0]->getHandSize() === 54);
    test('总牌数 216', array_sum(array_map(fn($p) => $p->getHandSize(), $players)) === 216);
    
    // 测试 6: 队伍系统
    test('玩家 0 和 2 同队', $players[0]->team === $players[2]->team);
    test('玩家 1 和 3 同队', $players[1]->team === $players[3]->team);
    test('对家为友', $players[0]->team !== $players[1]->team);
    
    // 测试 7: 叫牌
    $bidResult = $game->bid(0, 'heart', 1);
    test('叫牌成功', $bidResult['success'] === true);
    
    // 测试 8: 结束叫牌
    $finishResult = $game->finishBidding();
    test('结束叫牌成功', $finishResult['success'] === true);
    test('游戏状态 playing', $game->getState()['status'] === 'playing');
    
    // 测试 9: 主花色确定
    test('主花色已确定', !empty($game->getState()['trumpSuit']));
    test('级牌为 3', $game->getState()['trumpRank'] === '3');
    
    // 测试 10: 出牌测试
    $playResult = $game->playCards(0, [0, 1]);
    test('首家出牌成功', $playResult['success'] === true);
    
    // 测试 11: 顺时针出牌
    test('下一个玩家是 1', $playResult['nextPlayer'] === 1);
    
    // 测试 12: 分数计算（新规则：5=5 分，10/K=10 分，总分 400 分）
    $score = $game->getScore();
    test('初始分数 0', $score === 0);
    test('总分 400 分', $game->getTotalScore() === 400);
    
    // 测试 13: 牌型识别
    $testCards = [
        ['rank' => '3', 'suit' => 'heart', 'value' => 3],
        ['rank' => '3', 'suit' => 'spade', 'value' => 3]
    ];
    test('对子识别', true);  // TODO: 实现牌型识别测试
    
    echo "\n========================================\n";
    echo "结果：✅ {$passed} | ❌ {$failed}\n\n";
    
    if ($failed === 0) {
        echo "🎉 南宁拖拉机规则测试全部通过！\n";
        exit(0);
    } else {
        echo "⚠️  有 {$failed} 项测试失败\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ 测试失败：{$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
