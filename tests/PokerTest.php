<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Classes\PokerCard;
use App\Classes\Player;
use App\Classes\PokerGame;

echo "🃏 扑克牌游戏测试\n==================\n\n";
$passed = $failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) { echo "✅ $name\n"; $passed++; }
    else { echo "❌ $name\n"; $failed++; }
}

echo "1️⃣  扑克牌测试\n";
$cards = PokerCard::createDecks(4);
test('4 副牌共 216 张', count($cards) === 216);

$deck1 = PokerCard::createDeck(0);
test('1 副牌 54 张', count($deck1) === 54);

test('包含大小王', count(array_filter($deck1, fn($c) => $c->suit === 'joker')) === 2);

echo "\n2️⃣  玩家测试\n";
$player = new Player(1, 'TestPlayer', 0);
test('玩家座位 0', $player->seat === 0);
test('队伍 0', $player->team === 0);
test('初始级别 2', $player->level === 2);

$player2 = new Player(2, 'Player2', 2);
test('对家座位 2', $player2->seat === 2);
test('同队', $player->team === $player2->team);

$player3 = new Player(3, 'Player3', 1);
test('对手队伍', $player3->team === 1);

echo "\n3️⃣  游戏初始化\n";
$game = new PokerGame('test_game');
test('游戏初始状态 waiting', $game->status === 'waiting');

$game->addPlayer(1, 'Player1');
$game->addPlayer(2, 'Player2');
$game->addPlayer(3, 'Player3');
$game->addPlayer(4, 'Player4');
test('添加 4 个玩家', count($game->getPlayers()) === 4);

echo "\n4️⃣  开始游戏\n";
$result = $game->startGame();
test('游戏开始成功', $result);
test('游戏状态 playing', $game->status === 'playing');

$players = $game->getPlayers();
test('每人 54 张牌', $players[0]->getHandSize() === 54);

echo "\n5️⃣  队伍测试\n";
$p0 = $game->getPlayer(0);
$p2 = $game->getPlayer(2);
test('0 和 2 同队', $p0->team === $p2->team);

$p1 = $game->getPlayer(1);
test('1 和 3 同队', $p1->team === $game->getPlayer(3)->team);
test('0 和 1 不同队', $p0->team !== $p1->team);

echo "\n" . str_repeat('=', 40) . "\n";
echo "结果：✅ $passed | ❌ $failed\n";
exit($failed === 0 ? 0 : 1);
