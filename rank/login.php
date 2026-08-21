<?php

declare(strict_types=1);

$secureCookie = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
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

$configFile = dirname(__DIR__) . '/config.local.php';
$config = is_file($configFile) ? require $configFile : [];
if (!is_array($config)) {
    $config = [];
}

$rankAdminUser = trim((string)($config['rank_admin_user'] ?? ''));
$rankAdminPasswordHash = trim((string)($config['rank_admin_password_hash'] ?? ''));
$rankAdminConfigured = ($rankAdminUser !== '' && $rankAdminPasswordHash !== '');
$sessionMaxAge = 8 * 60 * 60;

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: ./');
    exit;
}

$loginError = $rankAdminConfigured ? '' : '管理登入尚未設定。';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['rank_auth_action'] ?? '') === 'login') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($rankAdminConfigured && hash_equals($rankAdminUser, $username) && password_verify($password, $rankAdminPasswordHash)) {
        session_regenerate_id(true);
        $_SESSION['rank_admin_authenticated'] = true;
        $_SESSION['rank_admin_last_activity'] = time();
        $target = (string)($_SERVER['REQUEST_URI'] ?? '/rank/');
        header('Location: ' . $target);
        exit;
    }
    usleep(350000);
    $loginError = $rankAdminConfigured ? '帳號或密碼錯誤。' : '管理登入尚未設定。';
}

$authenticated = ($_SESSION['rank_admin_authenticated'] ?? false) === true;
$lastActivity = (int)($_SESSION['rank_admin_last_activity'] ?? 0);
if ($authenticated && ($lastActivity <= 0 || time() - $lastActivity > $sessionMaxAge)) {
    $_SESSION = [];
    session_regenerate_id(true);
    $authenticated = false;
    $loginError = '登入已逾時，請重新登入。';
}

if (!$authenticated) {
    http_response_code(401);
    $errorHtml = $loginError !== '' ? '<div class="error">' . htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') . '</div>' : '';
    echo '<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>排名管理登入</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef3f8;font-family:Arial,"Microsoft JhengHei",sans-serif;color:#172033}.card{width:min(92vw,390px);background:#fff;border:1px solid #dbe4ee;border-radius:14px;padding:26px;box-shadow:0 12px 35px rgba(15,23,42,.1)}h1{font-size:23px;margin:0 0 6px}.sub{color:#64748b;font-size:14px;margin-bottom:20px}label{display:block;font-weight:700;font-size:13px;margin:12px 0 6px}input{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:16px}button{width:100%;margin-top:18px;padding:10px;border:0;border-radius:8px;background:#1769aa;color:#fff;font-size:16px;font-weight:700;cursor:pointer}.error{margin:0 0 14px;padding:10px 12px;border-radius:8px;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3}</style></head><body><main class="card"><h1>排名管理登入</h1><div class="sub">登入後才能存取排名資料管理與重算工具。</div>' . $errorHtml . '<form method="post" autocomplete="off"><input type="hidden" name="rank_auth_action" value="login"><label for="username">帳號</label><input id="username" name="username" type="text" autocomplete="username" required autofocus><label for="password">密碼</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">登入</button></form></main></body></html>';
    exit;
}

$_SESSION['rank_admin_last_activity'] = time();

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
    if ($sourceHost === '' || !hash_equals($expectedHostOnly, $sourceHost) || ($fetchSite !== '' && $fetchSite !== 'same-origin')) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden';
        exit;
    }
}

require_once dirname(__DIR__) . '/riftw/login.php';
