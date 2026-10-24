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
// Public and admin rule-tree buttons must share one hover/focus declaration.
const hover = shared.match(/\.rule-tree-layout \.rule-tree-card-button:is\(:hover, :focus-visible\),\s*\.rule-tree-grid \.rule-tree-node-toggle:is\(:hover, :focus-visible\)\s*\{([^}]*)\}/);
assert.ok(hover, 'both views must use the same shared hover style');
assert.match(hover[1], /background:\s*#edf6fc/i);
assert.match(hover[1], /color:\s*var\(--renju-primary-dark,\s*#24577F\)/i);
assert.doesNotMatch(publicCss, /\.rule-tree-card-button:hover\s*\{/,
    'public rule button hover must not override shared style');
assert.doesNotMatch(adminCss, /\.rule-tree-node-toggle:(?:hover|focus-visible)\s*\{/,
    'admin rule button hover must not override shared style');
assert.match(read('rule.htm'), /rule-tree-visual\.css\?v=20261024u/);
assert.match(read('rank/index.php'), /rule-tree-visual\.css\?v=20261024u/);
assert.match(read('.github/workflows/deploy.yml'), /'rule-tree-visual\.css'/);
console.log('Rule visual palette: shared connector strokes, warm count badges and deployment paths OK');
