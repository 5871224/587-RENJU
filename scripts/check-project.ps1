$ErrorActionPreference = 'Stop'

if (git ls-files -- config.local.php) {
    throw 'config.local.php must never be committed.'
}

$phpFiles = @(git ls-files '*.php')
foreach ($file in $phpFiles) {
    php -l $file
    if ($LASTEXITCODE -ne 0) {
        throw "PHP syntax check failed: $file"
    }
}

php scripts/check-tournament-metadata.php
if ($LASTEXITCODE -ne 0) {
    throw 'Tournament metadata parser regression checks failed.'
}

php scripts/check-renjunet-game-import.php
if ($LASTEXITCODE -ne 0) {
    throw 'RenjuNet game import regression checks failed.'
}

php scripts/check-rank-game-rebuild.php
if ($LASTEXITCODE -ne 0) {
    throw 'RANK and GAME starting scores regression checks failed.'
}

php scripts/check-record-queries.php
if ($LASTEXITCODE -ne 0) {
    throw 'Historical record statistics regression checks failed.'
}

php scripts/check-swiss-render.php
if ($LASTEXITCODE -ne 0) {
    throw 'Swiss render regression checks failed.'
}

if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    throw 'Node.js is required for Swiss JavaScript syntax validation.'
}
foreach ($file in @('rank/swiss-ui.js', 'rank/swiss-admin-ui.js', 'rank/sched-import.js')) {
    node --check $file
    if ($LASTEXITCODE -ne 0) {
        throw "JavaScript syntax check failed: $file"
    }
}

$credentialFiles = @(
    'riftw/login.php',
    'CH/testlogin.php',
    'CH/testranklogin.php',
    'bb/boradlogin.php',
    'bb/testlogin.php',
    'bb/ranklogin.php'
)
foreach ($file in $credentialFiles) {
    $source = Get-Content -Raw $file
    if ($source -match '(?s)new\s+PDO\s*\([^;]+,[\s\r\n]*[''"][^''"]+[''"][\s\r\n]*,[\s\r\n]*[''"][^''"]+[''"]') {
        throw "Possible hard-coded database credentials: $file"
    }
}

foreach ($file in $phpFiles) {
    $source = Get-Content -Raw $file
    if ($source -match '(?i)(?:key=|apiKey\s*=\s*[''"])[a-f0-9]{24,}') {
        throw "Possible hard-coded API key: $file"
    }
    if ($source -match '\$_(?:GET|POST|REQUEST)\s*\[\s*[''"]sql[''"]\s*\]') {
        throw "Arbitrary SQL request parameter is not allowed: $file"
    }
}

$removedEndpoints = @(
    'riftw/tourtest.php',
    'riftw/officialweb-query.php',
    'bb/SQLRANK.php',
    'bb/SQLRANK1.php',
    'bb/PP.php',
    'bb/SQL.php',
    'bb/querysql.php',
    'bb/readsql.php',
    'rank/rating-elo-audit.php',
    'rank/renjunet-sync.php',
    'rank/renjunet-elo-compare.php'
)
foreach ($file in $removedEndpoints) {
    if (Test-Path $file) {
        throw "Removed maintenance endpoint must not exist in public repository: $file"
    }
}

$rankIndexSource = Get-Content -Raw 'rank/index.php'
if ($rankIndexSource -match 'elo-audit|rating-elo-audit.php|舊世界 Elo 盤點') {
    throw 'Removed world Elo audit must not appear in rating tool navigation.'
}

$renjunetEloPage = Get-Content -Raw 'rank/renjunet-elo.php'
if ($renjunetEloPage -match 'renjunet-elo-compare\.php|舊保存分數比對') {
    throw 'The retired saved Elo comparison page must not be linked.'
}

$keyinSource = Get-Content -Raw 'bb/keyinsql.php'
foreach ($required in @("require_once __DIR__ . '/keyin-auth.php'", "connectDatabase('renjuorg_TEST'", "`$action === 'update'", "`$action === 'insert'", '->prepare(')) {
    if ($keyinSource -notmatch [regex]::Escape($required)) {
        throw "bb/keyinsql.php is missing authenticated prepared-write control: $required"
    }
}
if ($keyinSource -notmatch "\['VC4',\s*'X33',\s*'X43',\s*'X44',\s*'1M43'\]") {
    throw 'bb/keyinsql.php must keep its fixed puzzle table whitelist.'
}

$rankLogin = Get-Content -Raw 'rank/login.php'
foreach ($required in @('password_verify', 'session_regenerate_id', "'samesite' => 'Strict'", 'rank_admin_authenticated')) {
    if ($rankLogin -notmatch [regex]::Escape($required)) {
        throw "rank/login.php is missing required authentication control: $required"
    }
}
if ($rankLogin -match '\$2[aby]\$\d{2}\$[A-Za-z0-9./]{20,}') {
    throw 'rank/login.php must not contain a hard-coded password hash.'
}
foreach ($required in @('$rankAdminConfigured', "rank_admin_user'] ?? ''", "rank_admin_password_hash'] ?? ''")) {
    if ($rankLogin -notmatch [regex]::Escape($required)) {
        throw "rank/login.php is missing fail-closed configuration control: $required"
    }
}

$rankProtectedPages = @(
    'rank/index.php',
    'rank/sched-import.php',
    'rank/renjunet-game-import.php',
    'rank/renjunet-elo.php',
    'rank/rating-review.php',
    'rank/rating-latest.php',
    'rank/recalculate-export.php',
    'rank/swiss.php',
    'rank/swiss-history-add.php',
    'rank/swiss-den-add.php',
    'rank/swiss-record-delete.php'
)
foreach ($file in $rankProtectedPages) {
    $source = Get-Content -Raw $file
    if ($source -notmatch 'require_once\s+__DIR__\s*\.\s*[''"]/login\.php[''"]') {
        throw "Rank administration page is missing login protection: $file"
    }
}

$rnPage = Get-Content -Raw 'rank/renjunet-elo.php'
if ($rnPage -notmatch 'https://github.com/5871224/587-RENJU/actions/workflows/renjunet-sync.yml') {
    throw 'RenjuNet manual update must link to the existing GitHub Actions workflow.'
}
if ($rnPage -match 'rank_github_actions_token|rn_sync_csrf|renjunet-sync.php') {
    throw 'RenjuNet manual update must not require a website GitHub API token.'
}

$ratingSource = Get-Content -Raw 'rank/lib/rating.php'
foreach ($required in @('GAME_REBUILD_BACKUP', 'RANK_REBUILD_BACKUP', 'GAME_REBUILD_SCORES', 'rrGameStartRatings', 'RENAME TABLE')) {
    if ($ratingSource -notmatch [regex]::Escape($required)) {
        throw "RANK/GAME synchronized rebuild is missing: $required"
    }
}
$riftwPlayer = Get-Content -Raw 'riftw/player.php'
if ($riftwPlayer -match 'WHEN 備註 IS NOT NULL THEN 備註' -or
    $riftwPlayer -match 'WHERE 備註 IS NULL AND P[12]=') {
    throw 'Player history must recognize empty GAME notes as ordinary games.'
}
foreach ($required in @(
    "CASE WHEN TRIM(COALESCE(備註,''))<>'' THEN 備註",
    "CASE WHEN TRIM(COALESCE(G.備註,''))<>'' THEN NULL",
    "WHERE TRIM(COALESCE(備註,''))='' AND P1=",
    "WHERE TRIM(COALESCE(備註,''))='' AND P2="
)) {
    if (-not $riftwPlayer.Contains($required)) {
        throw "Player history empty-note regression: $required"
    }
}
$deployWorkflow = Get-Content -Raw '.github/workflows/deploy.yml'
if ($deployWorkflow -notmatch "'riftw/player.php'") {
    throw 'Production deployment must always include riftw/player.php.'
}

$playerHistoryCss = Get-Content -Raw 'riftw/player.css'
foreach ($required in @(
    '@media (max-width: 768px)',
    'table.rank.player-summary-table',
    'tr.player-summary-columns',
    'tr.player-summary-item',
    'content: attr(data-label)'
)) {
    if (-not $playerHistoryCss.Contains($required)) {
        throw "Mobile player summary CSS is missing: $required"
    }
}
foreach ($required in @(
    'player.css?v=1',
    'player-summary-table',
    'player-summary-item',
    "data-label='歷程'",
    "data-label='頭銜'"
)) {
    if (-not $riftwPlayer.Contains($required)) {
        throw "Player summary responsive markup is missing: $required"
    }
}
if ($deployWorkflow -notmatch "'riftw/player.css'") {
    throw 'Production deployment must always include the player mobile stylesheet.'
}

$riftwLogin = Get-Content -Raw 'riftw/login.php'
foreach ($required in @('normalizeArchiveDateParam', 'FILTER_VALIDATE_INT', "`$scriptName === 'record.php'", "unset(`$_GET['DATE'])")) {
    if ($riftwLogin -notmatch [regex]::Escape($required)) {
        throw "riftw/login.php is missing legacy request normalization: $required"
    }
}

$rootHtaccess = Get-Content -Raw '.htaccess'
foreach ($required in @('DATE=[0-9]{4}-[0-9]{2}-[0-9]{2}', 'TOUR=[1-9][0-9]*', '[R=400,L]')) {
    if ($rootHtaccess -notmatch [regex]::Escape($required)) {
        throw "Root .htaccess is missing malformed-query rejection: $required"
    }
}

$chLogin = Get-Content -Raw 'CH/testlogin.php'
foreach ($table in @('VC4','X33','X43','X44','1M43')) {
    if ($chLogin -notmatch [regex]::Escape("'$table'")) {
        throw "CH puzzle whitelist is missing table: $table"
    }
}
if ($chLogin -notmatch 'in_array\([^;]+allowedPuzzleTables[^;]+true\)') {
    throw 'CH/testlogin.php must strictly validate the puzzle table whitelist.'
}

$uploadSource = Get-Content -Raw 'bb/upload.php'
if ($uploadSource -match 'move_uploaded_file\s*\(') {
    throw 'bb/upload.php must never move user uploads into the public web directory.'
}
foreach ($required in @('is_uploaded_file', 'getimagesize', "'image/png'", "'image/jpeg'", "'image/webp'")) {
    if ($uploadSource -notmatch [regex]::Escape($required)) {
        throw "bb/upload.php is missing upload validation control: $required"
    }
}

$boardPage = Get-Content -Raw '5.php'
foreach ($required in @('htmlspecialchars((string)$title', 'htmlspecialchars((string)$image', 'ENT_QUOTES | ENT_SUBSTITUTE')) {
    if ($boardPage -notmatch [regex]::Escape($required)) {
        throw "5.php is missing shared-board metadata output escaping: $required"
    }
}

$boardLogin = Get-Content -Raw 'bb/boradlogin.php'
foreach ($required in @('SCRIPT_NAME', 'htmlspecialchars', 'ENT_QUOTES', "`$_POST['TITLE']")) {
    if ($boardLogin -notmatch [regex]::Escape($required)) {
        throw "bb/boradlogin.php is missing shared-board metadata sanitization: $required"
    }
}

Write-Host "Checked $($phpFiles.Count) PHP files; Swiss regression and public source security controls are present."
