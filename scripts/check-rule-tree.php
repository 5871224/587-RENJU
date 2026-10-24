<?php
/** Rule tree projection: all nodes public, one-line notes and stable parent order. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/site-content-lib.php';
$rows = [
    ['id'=>1,'parent_id'=>null,'title'=>'普通規則','note'=>'一般下法','content_text'=>'黑棋先下','is_visible'=>1],
    ['id'=>2,'parent_id'=>null,'title'=>'連珠規則','note'=>'經典禁手','content_text'=>'可PASS','is_visible'=>1],
    ['id'=>3,'parent_id'=>null,'title'=>'舊版隱藏分類','note'=>'改為公開','content_text'=>'','is_visible'=>0],
    ['id'=>4,'parent_id'=>2,'title'=>'塔拉規則','note'=>'國內賽事','content_text'=>"第一手\n第二手",'is_visible'=>1],
    ['id'=>5,'parent_id'=>3,'title'=>'原本隱藏分類的子節點','note'=>'現在顯示','content_text'=>'測試','is_visible'=>1],
    ['id'=>6,'parent_id'=>2,'title'=>'舊版隱藏子節點','note'=>'現在顯示','content_text'=>'測試','is_visible'=>0],
    ['id'=>7,'parent_id'=>777,'title'=>'孤兒規則','note'=>'不顯示','content_text'=>'測試','is_visible'=>1],
    ['id'=>8,'parent_id'=>2,'title'=>'山口規則','note'=>'世界盃','content_text'=>'測試','is_visible'=>1],
];
$tree = siteContentBuildRuleTree($rows);
if (array_column($tree, 'id') !== [1,2,3]) throw new RuntimeException('All first-level rules must be shown.');
if (array_column($tree[1]['children'], 'id') !== [4,6,8]) {
    throw new RuntimeException('All original children must render in stored order regardless of legacy visibility.');
}
if (array_column($tree[2]['children'], 'id') !== [5]) throw new RuntimeException('Previously hidden parent and children must be visible.');
if ($tree[0]['children'] !== []) throw new RuntimeException('Unrelated rules were attached.');
if ($tree[1]['children'][0]['content_text'] !== "第一手\n第二手") throw new RuntimeException('Content line breaks were lost.');
if ($tree[1]['children'][0]['note'] !== '國內賽事') throw new RuntimeException('Rule note was lost.');
foreach ($tree as $root) {
    if ($root['id'] === 7) throw new RuntimeException('Orphaned rules must not be shown.');
}
echo "Two-level rule tree projection: every rule shown, notes and order OK\n";
