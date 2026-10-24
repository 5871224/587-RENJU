/* Validate the rounded single-trunk geometry without needing a browser. */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const scope = {window:{},document:{}};
vm.runInNewContext(fs.readFileSync('rule-tree-connectors.js','utf8'),scope);
const geometry = scope.window.RenjuRuleTreeConnectors.geometry;

const checks = [
  {name:'many children', fromY:100,ys:[150,230,390],expected:1},
  {name:'one downward child',fromY:100,ys:[170],expected:1},
  {name:'one upward child',fromY:200,ys:[90],expected:1},
  {name:'children above and below',fromY:200,ys:[70,320],expected:2},
  {name:'same level child',fromY:100,ys:[100],expected:0},
  {name:'very near child',fromY:100,ys:[99,101],expected:2},
];
for (const check of checks) {
  const points = check.ys.map(y=>({x:300,y}));
  const paths = geometry(100,check.fromY,points,200);
  assert.equal(paths.outer.length,check.expected,check.name+' curved arm count');
  assert.equal(paths.outer.length+paths.inner.length,points.length,check.name+' total paths');
  assert.ok(paths.stem.startsWith('M 100 '+check.fromY+' H '));
  for (const path of paths.outer) {
    assert.ok((path.match(/ Q /g)||[]).length===2,check.name+' must curve both turns');
    assert.ok(path.endsWith(' H 300'),check.name+' should end at a child');
    assert.ok(!/ V \d+ V /.test(path),check.name+' must not extend the stem beyond a child');
  }
  for (const path of paths.inner) assert.ok(path.endsWith(' H 300'));
}
console.log('Rule connector tests: rounded elbows, exact endpoints, single/paired branches OK');
