<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Classes\Card;

echo "🧪 Card 类测试\n==============\n\n";
$passed = $failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) { echo "✅ $name\n"; $passed++; }
    else { echo "❌ $name\n"; $failed++; }
}

echo "1️⃣  基础属性测试\n";
$card = new Card(['id'=>1,'name'=>'火焰精灵','card_type'=>'creature','mana_cost'=>'2R','power'=>3,'toughness'=>2]);
test('名称正确', $card->name === '火焰精灵');
test('类型正确', $card->cardType === 'creature');
test('法力费用正确', $card->manaCost === '2R');
test('初始未横置', !$card->isTapped);

echo "\n2️⃣  攻击测试\n";
$result = $card->attack();
test('召唤失调不能攻击', !$result['success']);
$card->hasSummoningSickness = false;
$result = $card->attack();
test('可以攻击', $result['success']);
test('攻击后横置', $card->isTapped);

echo "\n3️⃣  横置/重置测试\n";
$card2 = new Card(['name'=>'测试卡']);
$card2->tap(); test('横置成功', $card2->isTapped);
$card2->untap(); test('重置成功', !$card2->isTapped);

echo "\n4️⃣  伤害治疗测试\n";
$creature = new Card(['name'=>'测试','card_type'=>'creature','toughness'=>5]);
test('受到 2 点伤害', $creature->takeDamage(2) === 3);
$creature->heal(1, 5); test('治疗 1 点', $creature->currentToughness === 4);
$creature->takeDamage(10); test('致命伤害', $creature->isDead());

echo "\n5️⃣  toArray 测试\n";
$card3 = new Card(['id'=>5,'name'=>'冰霜巨人','mana_cost'=>'4UU','power'=>6,'toughness'=>7]);
$array = $card3->toArray();
test('数组 ID 正确', $array['id'] === 5);
test('数组名称正确', $array['name'] === '冰霜巨人');

echo "\n" . str_repeat('=', 40) . "\n";
echo "结果：✅ $passed | ❌ $failed\n";
exit($failed === 0 ? 0 : 1);
