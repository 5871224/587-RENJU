/* Regression checks for mind-map row formatting without a browser or database. */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const window = {};
vm.runInNewContext(fs.readFileSync('rule-tree-content.js','utf8'), {window});
const formatHtml = window.RenjuRuleTreeContent.formatHtml;

const game = formatHtml(
    '●黑子先下、白子後下，輪流下子。\n●雙方認為無法獲勝時，可協議和局。\n※禁手例外。',
    'game'
);
assert.match(game,/rule-text-group--game/);
assert.equal((game.match(/rule-text-bullet/g)||[]).length,3);
assert.match(game,/rule-text-note/);
assert.doesNotMatch(game,/rule-text-trunk|rule-tree-branch|<svg/i);

const opening = formatHtml('（目前採用）\n１第一步。\n２第二步，內容很長時需要正確懸掛縮排。\n　○子選項\n３第三步。','opening');
assert.match(opening,/rule-text-group--opening/);
assert.equal((opening.match(/rule-text-step/g)||[]).length,3);
assert.match(opening,/rule-text-nested/);
assert.match(opening,/<span class="rule-text-marker" aria-hidden="true">２<\/span>/);

const harmless = formatHtml('●<img src=x onerror=alert(1)>\n\n<a href="/danger">連結</a>','game');
assert.doesNotMatch(harmless,/<img|<a href|onerror=alert\(1\)/);
assert.match(harmless,/&lt;img/);
assert.match(harmless,/rule-text-paragraph-gap/);
console.log('Rule mind-map formatting: game markers, numbered steps, safe text OK');
