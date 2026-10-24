'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');

const page = fs.readFileSync('index.html', 'utf8');
const endpoint = fs.readFileSync('home-info.php', 'utf8');
const frontend = fs.readFileSync('js/home-info.js', 'utf8');

assert.match(page, /<h2>活動資訊<\/h2>/, '首頁應顯示活動資訊');
assert.doesNotMatch(page, /<h2>比賽資訊<\/h2>/, '舊標題應移除');
assert.match(page, /id="activity-info-body"/);
assert.match(page, /id="activity-info-more" hidden/);
assert.match(page, /id="update-info-more"[^>]*hidden/);
assert.match(page, /js\/home-info\.js\?v=20261024a/);

assert.match(endpoint, /FROM SITE_NOTICE[\s\S]*?type = 'TOURNAMENT'/, '比賽公告必須保留');
assert.match(endpoint, /FROM `renjuorg_Sched`\.`activities`[\s\S]*?WHERE brief_public = 1/, '公開活動必須合併且不得曝光草稿');
assert.match(endpoint, /UNION ALL/, '兩種活動共用一個排序查詢');
assert.match(endpoint, /ORDER BY published_date DESC, is_featured DESC/, '日期優先於置頂');
assert.match(endpoint, /CONCAT\('\/Sched\/activity\.html\?id=', id\)/, '活動應連往活動內容頁');
assert.match(endpoint, /\$limitRaw !== 'all'/, '查看更多必須支援全部筆數');
assert.match(endpoint, /'activities' => \$noticeTotal \+ \$publishedActivitiesTotal/, '總數包含兩個來源');

assert.match(frontend, /fetchInfo\('all'\)/);
assert.match(frontend, /state\.totals\[type\] <= INITIAL_LIMIT/, '超過 5 筆才顯示查看更多');
assert.match(frontend, /renderActivities\(rows\)/);
assert.match(frontend, /document\.getElementById\('activity-info-body'\)/);
assert.match(frontend, /document\.getElementById\('activity-info-more'\)/);
assert.doesNotMatch(frontend, /EXPANDED_LIMIT\s*=\s*200/, '查看更多不可沿用舊版 200 筆上限');
console.log('Homepage activity/notice merge and show-all contract passed.');
