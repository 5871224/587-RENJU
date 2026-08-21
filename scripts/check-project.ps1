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

$disabledEndpoints = @(
    'riftw/tourtest.php',
    'riftw/officialweb-query.php',
    'bb/SQLRANK.php',
    'bb/SQLRANK1.php',
    'bb/PP.php',
    'bb/SQL.php',
    'bb/querysql.php',
    'bb/readsql.php'
)
foreach ($file in $disabledEndpoints) {
    if (-not (Test-Path $file)) {
        throw "Disabled maintenance endpoint stub is missing: $file"
    }
    $source = Get-Content -Raw $file
    if ($source -notmatch 'http_response_code\s*\(\s*404\s*\)') {
        throw "Disabled maintenance endpoint must return 404: $file"
    }
    if ($source -match '(?i)connectDatabase|->query\s*\(|->prepare\s*\(|\$_(?:GET|POST|REQUEST)\s*\[') {
        throw "Disabled maintenance endpoint contains executable database/request logic: $file"
    }
}

$keyinSource = Get-Content -Raw 'bb/keyinsql.php'
if ($keyinSource -notmatch 'Editing is disabled on the public website') {
    throw 'bb/keyinsql.php must keep public write operations disabled until the private admin migration is complete.'
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
    'rank/recalculate.php',
    'rank/recalculate-check.php',
    'rank/recalculate-export.php',
    'rank/recalculated-ranking.php',
    'rank/final-diff.php',
    'rank/elo-audit.php'
)
foreach ($file in $rankProtectedPages) {
    $source = Get-Content -Raw $file
    if ($source -notmatch 'require_once\s+__DIR__\s*\.\s*[''"]/login\.php[''"]') {
        throw "Rank administration page is missing login protection: $file"
    }
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

Write-Host "Checked $($phpFiles.Count) PHP files; public source security controls are present."
