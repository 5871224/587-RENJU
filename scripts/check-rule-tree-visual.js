/* Prevent regressions to mixed connector colors or blue rule-count badges. */
'use strict';
const fs = require('node:fs');
const assert = require('node:assert/strict');
const read = path => fs.readFileSync(path, 'utf8');
const publicCss = read('rule-tree.css');
const adminCss = read('rank/rule-tree-admin.css');
const shared = read('rule-tree-visual.css');

for (const [path,css] of [['rule-tree.css',publicCss],['rank/rule-tree-admin.css',adminCss]]) {
  assert.doesNotMatch(css, /\.rule-tree-(?:card-count|child-count)\s*\{/,
    path + ' must not redefine the count badge');
  assert.doesNotMatch(css, /\.rule-tree-(?:connectors|admin-connectors)\s+\.rule-tree-(?:branch|stem|twig|trunk|origin|endpoint)\s*\{/,
    path + ' must not override shared SVG colors');
  assert.doesNotMatch(css, /stroke\s*:/, path + ' must not specify separate line colors');
}
assert.match(shared, /--rule-tree-link-color:\s*#[\da-fA-F]{6}/);
assert.match(shared, /--rule-tree-opening-bg:\s*#fcf8f1/i);
for (const selector of ['rule-tree-branch','rule-tree-origin','rule-tree-endpoint','rule-tree-card-count','rule-tree-child-count']) {
  assert.ok(shared.includes(selector), 'missing shared style ' + selector);
}
assert.equal((shared.match(/stroke:\s*var\(--rule-tree-link-color\)/g) || []).length, 2,
  'all SVG path and endpoint strokes must use the same color');
assert.match(read('rule.htm'), /rule-tree-visual\.css\?v=20261024n/);
assert.match(read('rank/index.php'), /rule-tree-visual\.css\?v=20261024n/);
assert.match(read('.github/workflows/deploy.yml'), /'rule-tree-visual\.css'/);
console.log('Rule visual palette: shared connector strokes, warm count badges and deployment paths OK');
