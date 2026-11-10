<?php
/**
 * 排名後台使用 /Sched/ 的 users / auth_tokens 作為唯一身份及權限來源。
 * 只有 Sched users.permission = 'admin' 可進入 /rank/。
 * PHP session 僅保存後台 CSRF / 頁面狀態，不再另設帳號密碼。
 */
declare(strict_types=1);

$rankTimeZone = new DateTimeZone('Asia/Taipei');
$rankNow = new DateTimeImmutable('now', $rankTimeZone);
$rankToday = $rankNow->format('Y-m-d');
$rankTomorrow = $rankNow->modify('tomorrow')->setTime(0, 0, 0);
$sessionLifetime = max(60, $rankTomorrow->getTimestamp() - $rankNow->getTimestamp());
$secureCookie = ((!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '90000');
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/rank/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$schedAuthDir = dirname(__DIR__) . '/Sched/api';
require_once $schedAuthDir . '/AuthTokenService.php';
$schedAuthConfig = require $schedAuthDir . '/config.php';
try {
    $db = $schedAuthConfig['db'];
    $schedDsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['dbname'], $db['charset']);
    $schedAuthPdo = new PDO($schedDsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $schedAuthUser = authCurrentUser($schedAuthPdo, $schedAuthConfig);
} catch (Throwable $e) {
    error_log('rank shared auth error: ' . $e->getMessage());
    http_response_code(503);
    exit('登入系統暫時無法使用，請稍後再試。');
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    authLogoutCurrentToken($schedAuthPdo);
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: /Sched/');
    exit;
}

// 未登入時停留在 /rank/；由瑞士制產出的 JS 直接掛載同一個 EmailAuthDialog。
// 不複製登入表單，也不導頁到 /Sched/index.html。
if (!$schedAuthUser) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(401);
        exit('請先登入。');
    }

    $authAssetsDir = dirname(__DIR__) . '/Sched/assets';
    $authJs = $authAssetsDir . '/rank-auth.js';
    $authCss = $authAssetsDir . '/rank-auth.css';
    $authJsVersion = is_file($authJs) ? (string)filemtime($authJs) : '0';
    $authCssVersion = is_file($authCss) ? (string)filemtime($authCss) : '0';
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>排名管理登入</title>'
        . '<script src="https://accounts.google.com/gsi/client" async defer></script>'
        . '<script src="/Sched/assets/open-external-browser.js" defer></script>'
        . '<link rel="stylesheet" href="/Sched/assets/rank-auth.css?v=' . $authCssVersion . '">'
        . '<script type="module" src="/Sched/assets/rank-auth.js?v=' . $authJsVersion . '"></script>'
        . '</head><body style="margin:0">'
        . '<div id="rank-auth-app"><main style="min-height:100vh;display:grid;place-items:center;font-family:Arial,sans-serif">載入登入視窗…</main></div>'
        . '<noscript>請開啟 JavaScript，才能登入排名管理後台。</noscript>'
        . '</body></html>';
    exit;
}

if (($schedAuthUser['permission'] ?? '') !== 'admin') {
    http_response_code(403);
    echo '<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>排名管理權限</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef3f8;font-family:Arial,"Microsoft JhengHei",sans-serif;color:#172033}.card{width:min(92vw,420px);background:#fff;border:1px solid #dbe4ee;border-radius:14px;padding:26px;box-shadow:0 12px 35px rgba(15,23,42,.1)}h1{font-size:23px;margin:0 0 12px}.sub{color:#64748b;font-size:15px;line-height:1.7;margin-bottom:22px}.btn{display:block;text-decoration:none;text-align:center;padding:11px;border-radius:9px;background:#1769aa;color:#fff;font-weight:700}</style></head><body><main class="card"><h1>沒有管理員權限</h1><div class="sub">目前登入的賽程帳號沒有管理員權限，請聯絡賽程管理員設定。</div><a class="btn" href="/Sched/">返回賽程</a></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    $expectedHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $sourceHost = '';
    if ($origin !== '') {
        $sourceHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?? ''));
    } elseif ($referer !== '') {
        $sourceHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?? ''));
    }
    $expectedHostOnly = strtolower((string)(parse_url('https://' . $expectedHost, PHP_URL_HOST) ?? $expectedHost));
    $fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($sourceHost === '' || !hash_equals($expectedHostOnly, $sourceHost)
        || ($fetchSite !== '' && $fetchSite !== 'same-origin')) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Forbidden');
    }
}

// 保留排名資料庫連線與原本所有後台操作。
require_once dirname(__DIR__) . '/riftw/login.php';
