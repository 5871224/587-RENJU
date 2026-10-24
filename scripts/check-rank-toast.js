/* Regression for centered, non-layout-shifting rank action notifications. */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const read = file => fs.readFileSync(file, 'utf8');

const page = read('rank/index.php');
const css = read('rank/admin.css');
const code = read('rank/rank-toast.js');
const workflow = read('.github/workflows/deploy.yml');
assert.doesNotMatch(page, /<div class="(?:success|error)">/, 'Old inline banners must be gone');
assert.match(page, /<\/main>\s*<\?php if \(\$error !== '' \|\| \$message !== ''\)/,
    'Toast must be rendered after main so the table never shifts');
assert.match(page, /data-rank-toast/);
assert.match(page, /role="<\?= \$error !== '' \? 'alert' : 'status' \?>"/);
assert.match(page, /rank-toast\.js\?v=/);
assert.match(css, /\.rank-toast\{[\s\S]*?position:fixed/);
assert.match(css, /transform:translate\(-50%,-50%\)/);
assert.match(workflow, /'rank\/rank-toast\.js'/);

function simulate(data, reduceMotion = false) {
    const scheduled = [];
    const listeners = {};
    const classes = [];
    let removed = false;
    const toast = {
        dataset: data,
        classList: {add: cls => classes.push(cls)},
        remove() {removed = true;},
        querySelector(sel) {
            if (sel !== '[data-rank-toast-close]') throw new Error('Unexpected query');
            return {addEventListener(type, fn) {listeners[type] = fn;}};
        }
    };
    const context = {
        document: {querySelector: sel => sel === '[data-rank-toast]' ? toast : null},
        window: {
            matchMedia: () => ({matches: reduceMotion}),
            setTimeout: (fn, ms) => scheduled.push({fn,ms})
        }
    };
    vm.runInNewContext(code,context);
    return {scheduled,listeners,classes,get removed(){return removed;}};
}
const success = simulate({autoDismiss:'3800'});
assert.equal(success.scheduled.length,1);
assert.equal(success.scheduled[0].ms,3800);
success.scheduled[0].fn();
assert.deepEqual(success.classes,['is-closing']);
assert.equal(success.scheduled[1].ms,200);
success.scheduled[1].fn();
assert.equal(success.removed,true);

const error = simulate({});
assert.equal(error.scheduled.length,0,'Errors must not auto-disappear');
error.listeners.click();
error.scheduled[0].fn();
assert.equal(error.removed,true);
const reduced = simulate({autoDismiss:'3800'},true);
reduced.scheduled[0].fn();
assert.equal(reduced.removed,true,'Reduced-motion notices should close immediately');
console.log('Rank centered notification: non-layout positioning, auto-close, manual errors OK');
