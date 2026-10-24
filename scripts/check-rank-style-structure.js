/* Static regression: rank CSS stays in canonical files without obsolete rule editor overrides. */
'use strict';
const fs = require('node:fs');
const assert = require('node:assert/strict');
const read = path => fs.readFileSync(path, 'utf8');

const page = read('rank/index.php');
const admin = read('rank/admin.css');
const rules = read('rank/rule-tree-admin.css');
const tools = read('rank/table-tools.css');

// Form layout must be in the canonical admin stylesheet, not a PHP inline patch.
assert.doesNotMatch(page, /<style\b/i);
assert.match(page, /href="admin\.css\?v=/);
for (const [selector,properties] of [
  ['.edit-field:has(textarea)', ['grid-column:1/-1']],
  ['.edit-field textarea', ['display:block','width:100%','min-height:190px',
       'resize:vertical','padding:11px','border:1px solid #cbd5e1',
       'border-radius:8px','font:14px/1.55 Consolas,monospace']],
  ['.edit-field textarea[name*="content_html"]', ['min-height:420px']],
]) {
    const declaration = admin.split(selector + '{')[1]?.split('}')[0];
    assert.ok(declaration, 'missing original textarea selector ' + selector);
    for (const property of properties)
        assert.ok(declaration.includes(property), selector + ' lost ' + property);
}

// Fixed rule-tree headers retain the same values without a later duplicate patch.
const headers = [...rules.matchAll(/\.rule-tree-column-head\s*\{([^}]*)\}/g)];
assert.equal(headers.length, 1, 'duplicated rule-tree header styles');
for (const property of ['display:flex','align-items:center','flex-wrap:wrap',
        'row-gap:5px','gap:10px','padding-bottom:12px']) {
    assert.ok(headers[0][1].includes(property), 'header layout changed: ' + property);
}
assert.doesNotMatch(rules, /\.rule-tree-section-dialog\s+textarea\s*\{/,
    'a removed note textarea must not retain CSS patches');
assert.doesNotMatch(rules, /\.rule-tree-column--child:before/,
    'CSS-based connectors were replaced with the shared SVG and must stay removed');

// The site-wide button:hover turns buttons dark blue; rule nodes must override
// that through their own canonical hover state, without hiding the text.
assert.match(rules,
    /\.rule-tree-node-toggle:hover,\.rule-tree-node-toggle:focus-visible\s*\{\s*background:#edf6fc;color:var\(--renju-primary-dark,#24577F\)\s*\}/,
    'Rule rows need a light hover/focus background and readable text color');

// Safeguard relevant mobile layout and table-action styles against accidental deletion.
assert.match(rules, /@media\s*\(max-width:820px\)/);
assert.match(rules, /\.rule-tree-node-body\s*\{padding-left:16px\}/);
assert.match(tools, /table\.rank-actions-pinned/);
assert.match(tools, /:where\(a, button\)\.icon-action/);
console.log('Rank style refactor: original editor metrics, mobile layout and pinned actions preserved');
