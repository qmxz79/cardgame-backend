<?php
/**
 * 南宁拖拉机游戏测试
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Classes\NanningPokerGame;

echo "🃏 南宁拖拉机游戏测试\n========================\n\n";
$passed = $failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) { echo "✅ $name\n"; $passed++; }
    else { echo "❌ $name\n"; $failed++; }
}

// 创建游戏
$game = new NanningPokerGame('nanning_test');
test('游戏创建成功', $game !== null);

// 添加玩家
$game->addPlayer(1, '南宁玩家 1');
$game->addPlayer(2, '南宁玩家 2');
$game->addPlayer(3, '南宁玩家 3');
$game->addPlayer(4, '南宁玩家 4');
test('添加 4 个玩家', count($game->getPlayers()) === 4);

// 开始游戏
$result = $game->startGame();
test('游戏开始成功', $result);
test('游戏状态 playing', $game->status === 'playing');

// 检查发牌
$players = $game->getPlayers();
test('每人 54 张牌', $players[0]->getHandSize() === 54);
test('总牌数 216', array_sum(array_map(fn($p) => $p->getHandSize(), $players)) === 216);

// 检查队伍
test('玩家 0 和 2 同队', $players[0]->team === $players[2]->team);
test('玩家 1 和 3 同队', $players[1]->team === $players[3]->team);

// 检查主牌信息
$trumpInfo = $game->getTrumpInfo();
test('初始主花色为空', empty($trumpInfo['trumpSuit']));
test('初始级牌为 2', $trumpInfo['trumpRank'] === '2');

// 测试出牌 - 获取当前玩家
$state = $game->getState();
$currentPlayer = $state['currentPlayer'];

// 当前玩家出牌
$result = $game->playCards($currentPlayer, [0, 1, 2]);
test('当前玩家出牌成功', $result['success'] ?? false);
test('出了 3 张牌', isset($result['playedCards']) ? count($result['playedCards']) === 3 : false);

// 下一个玩家出牌
$nextPlayer = $result['nextPlayer'] ?? (($currentPlayer + 1) % 4);
$result2 = $game->playCards($nextPlayer, [0]);
test('下一个玩家出牌成功', $result2['success'] ?? false);

// 检查主花色已确定
$trumpInfo2 = $game->getTrumpInfo();
test('主花色已确定', !empty($trumpInfo2['trumpSuit']));

echo "\n" . str_repeat('=', 40) . "\n";
echo "结果：✅ $passed | ❌ $failed\n";

if ($failed === 0) {
    echo "\n🎉 南宁拖拉机规则测试全部通过！\n";
}

exit($failed === 0 ? 0 : 1);
