<?php
/**
 * 牌型识别测试
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\HandType;

echo "🎴 牌型识别测试\n";
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

// 测试单张
$single = [['rank' => 'A', 'suit' => 'heart', 'value' => 14]];
$result = HandType::identify($single);
test('单张识别', $result['type'] === HandType::TYPE_SINGLE);
test('单张数量', $result['count'] === 1);

// 测试对子
$pair = [
    ['rank' => 'K', 'suit' => 'heart', 'value' => 13],
    ['rank' => 'K', 'suit' => 'spade', 'value' => 13]
];
$result = HandType::identify($pair);
test('对子识别', $result['type'] === HandType::TYPE_PAIR);
test('对子数量', $result['count'] === 2);

// 测试拖拉机（连对）
$tractor = [
    ['rank' => '3', 'suit' => 'heart', 'value' => 3],
    ['rank' => '3', 'suit' => 'spade', 'value' => 3],
    ['rank' => '4', 'suit' => 'heart', 'value' => 4],
    ['rank' => '4', 'suit' => 'spade', 'value' => 4]
];
$result = HandType::identify($tractor);
test('拖拉机识别', $result['type'] === HandType::TYPE_TRACTOR);
test('拖拉机数量', $result['count'] === 4);
test('拖拉机长度', $result['length'] === 2);

// 测试三张
$triple = [
    ['rank' => '5', 'suit' => 'heart', 'value' => 5],
    ['rank' => '5', 'suit' => 'spade', 'value' => 5],
    ['rank' => '5', 'suit' => 'club', 'value' => 5]
];
$result = HandType::identify($triple);
test('三张识别', $result['type'] === HandType::TYPE_TRIPLE);
test('三张数量', $result['count'] === 3);

// 测试推土机（连三张）
$tripleTractor = [
    ['rank' => '6', 'suit' => 'heart', 'value' => 6],
    ['rank' => '6', 'suit' => 'spade', 'value' => 6],
    ['rank' => '6', 'suit' => 'club', 'value' => 6],
    ['rank' => '7', 'suit' => 'heart', 'value' => 7],
    ['rank' => '7', 'suit' => 'spade', 'value' => 7],
    ['rank' => '7', 'suit' => 'club', 'value' => 7]
];
$result = HandType::identify($tripleTractor);
test('推土机识别', $result['type'] === HandType::TYPE_TRIPLE_TRACTOR);
test('推土机数量', $result['count'] === 6);
test('推土机长度', $result['length'] === 2);

// 测试四张
$quad = [
    ['rank' => '8', 'suit' => 'heart', 'value' => 8],
    ['rank' => '8', 'suit' => 'spade', 'value' => 8],
    ['rank' => '8', 'suit' => 'club', 'value' => 8],
    ['rank' => '8', 'suit' => 'diamond', 'value' => 8]
];
$result = HandType::identify($quad);
test('四张识别', $result['type'] === HandType::TYPE_QUAD);
test('四张数量', $result['count'] === 4);

// 测试飞机（连四张）
$plane = [
    ['rank' => '9', 'suit' => 'heart', 'value' => 9],
    ['rank' => '9', 'suit' => 'spade', 'value' => 9],
    ['rank' => '9', 'suit' => 'club', 'value' => 9],
    ['rank' => '9', 'suit' => 'diamond', 'value' => 9],
    ['rank' => '10', 'suit' => 'heart', 'value' => 10],
    ['rank' => '10', 'suit' => 'spade', 'value' => 10],
    ['rank' => '10', 'suit' => 'club', 'value' => 10],
    ['rank' => '10', 'suit' => 'diamond', 'value' => 10]
];
$result = HandType::identify($plane);
test('飞机识别', $result['type'] === HandType::TYPE_PLANE);
test('飞机数量', $result['count'] === 8);
test('飞机长度', $result['length'] === 2);

// 测试牌值比较
$card1 = ['rank' => 'A', 'suit' => 'heart', 'value' => 14];
$card2 = ['rank' => 'K', 'suit' => 'heart', 'value' => 13];
$value1 = HandType::getCardValue($card1, 'heart', '3');
$value2 = HandType::getCardValue($card2, 'heart', '3');
test('A 大于 K', $value1 > $value2);

// 测试主牌值
$joker = ['rank' => 'red', 'suit' => 'joker', 'value' => 0];
$kingJokerValue = HandType::getCardValue($joker, 'heart', '3');
test('大王牌值最大', $kingJokerValue === 1000);

// 测试级牌值
$trumpLevel = ['rank' => '3', 'suit' => 'heart', 'value' => 3];
$trumpValue = HandType::getCardValue($trumpLevel, 'heart', '3');
test('主级牌值大于副级牌', $trumpValue > 700);

// 测试 2 的牌值
$two = ['rank' => '2', 'suit' => 'spade', 'value' => 2];
$twoValue = HandType::getCardValue($two, 'heart', '3');
test('2 的牌值大于 A', $twoValue > 500);

echo "\n========================================\n";
echo "结果：✅ {$passed} | ❌ {$failed}\n\n";

if ($failed === 0) {
    echo "🎉 所有牌型识别测试通过！\n";
    exit(0);
} else {
    echo "⚠️  有 {$failed} 项测试失败\n";
    exit(1);
}
