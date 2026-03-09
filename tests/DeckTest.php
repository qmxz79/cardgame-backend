<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Classes\Card;
use App\Classes\Deck;

echo "🧪 Deck 类测试\n==============\n\n";
$passed = $failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) { echo "✅ $name\n"; $passed++; }
    else { echo "❌ $name\n"; $failed++; }
}

echo "1️⃣  基础属性测试\n";
$deck = new Deck(['id'=>1,'name'=>'测试卡组','user_id'=>1]);
test('名称正确', $deck->name === '测试卡组');
test('初始生命 20', $deck->life === 20);
test('初始法力 0', $deck->mana === 0);

echo "\n2️⃣  添加卡牌测试\n";
for ($i = 0; $i < 10; $i++) {
    $deck->addCard(new Card(['name'=>"卡$i"]));
}
test('卡牌数量正确', $deck->getDeckSize() === 10);

echo "\n3️⃣  洗牌测试\n";
$deck->shuffle();
test('洗牌后数量不变', $deck->getDeckSize() === 10);

echo "\n4️⃣  抽牌测试\n";
$drawn = $deck->drawCard(3);
test('抽 3 张牌', count($drawn) === 3);
test('手牌数量正确', $deck->getHandSize() === 3);
test('牌库剩余正确', $deck->getDeckSize() === 7);

echo "\n5️⃣  法力计算测试\n";
$testDeck = new Deck();
$method = new ReflectionMethod($testDeck, 'calculateManaCost');
$method->setAccessible(true);

test('空费用为 0', $method->invoke($testDeck, null) === 0);
test('数字费用正确', $method->invoke($testDeck, '3') === 3);
test('混合费用正确', $method->invoke($testDeck, '2R') === 3);
test('多色费用正确', $method->invoke($testDeck, '1WU') === 3);

echo "\n6️⃣  回合开始测试\n";
$gameDeck = new Deck();
$gameDeck->maxMana = 3;
$gameDeck->mana = 0;
for ($i = 0; $i < 5; $i++) {
    $gameDeck->addCard(new Card(['name'=>"卡$i"]));
}
$gameDeck->startTurn();
test('法力重置', $gameDeck->mana === 3);
test('手牌增加', $gameDeck->getHandSize() === 1);

echo "\n7️⃣  统计测试\n";
$statsDeck = new Deck();
$statsDeck->addCard(new Card(['name'=>'生物 1','card_type'=>'creature','mana_cost'=>'1']));
$statsDeck->addCard(new Card(['name'=>'生物 2','card_type'=>'creature','mana_cost'=>'2']));
$statsDeck->addCard(new Card(['name'=>'法术','card_type'=>'spell','mana_cost'=>'1']));
$statsDeck->addCard(new Card(['name'=>'地','card_type'=>'land','mana_cost'=>null]));

$stats = $statsDeck->getStats();
test('总卡牌数正确', $stats['totalCards'] === 4);
test('生物数正确', $stats['creatures'] === 2);
test('法术数正确', $stats['spells'] === 1);
test('地数正确', $stats['lands'] === 1);

echo "\n" . str_repeat('=', 40) . "\n";
echo "结果：✅ $passed | ❌ $failed\n";
exit($failed === 0 ? 0 : 1);
