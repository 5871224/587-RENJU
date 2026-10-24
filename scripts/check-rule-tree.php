<?php
/** No database required: verify the public hierarchy and hidden-parent rules. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/site-content-lib.php';
$rows = [
    ['id'=>1,'parent_id'=>null,'title'=>'普通規則','content_text'=>'黑棋先下','is_visible'=>1],
    ['id'=>2,'parent_id'=>null,'title'=>'連珠規則','content_text'=>'可PASS','is_visible'=>1],
    ['id'=>3,'parent_id'=>null,'title'=>'隱藏分類','content_text'=>'','is_visible'=>0],
    ['id'=>4,'parent_id'=>2,'title'=>'塔拉規則','content_text'=>"第一手\n第二手",'is_visible'=>1],
    ['id'=>5,'parent_id'=>3,'title'=>'不可洩漏的子節點','content_text'=>'測試','is_visible'=>1],
    ['id'=>6,'parent_id'=>2,'title'=>'隱藏的子節點','content_text'=>'測試','is_visible'=>0],
    ['id'=>7,'parent_id'=>777,'title'=>'孤兒規則','content_text'=>'測試','is_visible'=>1],
    ['id'=>8,'parent_id'=>2,'title'=>'山口規則','content_text'=>'測試','is_visible'=>1],
];
$tree = siteContentBuildRuleTree($rows);
if (count($tree) !== 2) throw new RuntimeException('Only public first-level rules should render.');
if (array_column($tree, 'id') !== [1,2]) throw new RuntimeException('Parent order changed.');
if (array_column($tree[1]['children'], 'id') !== [4,8]) {
    throw new RuntimeException('Only visible children of visible parents should appear in stored order.');
}
if ($tree[0]['children'] !== []) throw new RuntimeException('Unrelated rules were incorrectly attached.');
if ($tree[1]['children'][0]['content_text'] !== "第一手\n第二手") {
    throw new RuntimeException('Plain text line breaks should survive projection.');
}
echo "Two-level rule tree projection: hierarchy, order and visibility OK\n";
