<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';

// This endpoint can only enqueue the fixed, existing RenjuNet sync workflow.
$back = (string)($_POST['return_to'] ?? '') === 'standalone'
    ? 'renjunet-elo.php'
    : './?view=rating-tools&tool=renjunet';

function rnSyncRedirect(string $target, string $kind, string $message): void
{
    $_SESSION['rn_sync_flash'] = ['kind' => $kind, 'message' => $message];
    header('Location: ' . $target, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

$expected = (string)($_SESSION['rn_sync_csrf'] ?? '');
$provided = (string)($_POST['rn_sync_csrf'] ?? '');
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
    rnSyncRedirect($back, 'error', '表單驗證已失效，請重新整理頁面後再試。');
}

$token = trim((string)($config['rank_github_actions_token'] ?? ''));
if ($token === '') $token = trim((string)(getenv('RANK_GITHUB_ACTIONS_TOKEN') ?: ''));
if ($token === '') {
    rnSyncRedirect($back, 'error', '尚未在伺服器設定 GitHub Actions 存取權杖，無法從網站直接啟動。可先到 GitHub Actions 手動執行。');
}

$lastRequestAt = (int)($_SESSION['rn_sync_last_dispatch'] ?? 0);
if ($lastRequestAt > 0 && time() - $lastRequestAt < 120) {
    rnSyncRedirect($back, 'error', '剛才已送出同步要求，請先到 GitHub Actions 查看執行進度，避免重複啟動。');
}

$url = 'https://api.github.com/repos/5871224/587-RENJU/actions/workflows/renjunet-sync.yml/dispatches';
$headers = [
    'Accept: application/vnd.github+json',
    'Authorization: Bearer ' . $token,
    'X-GitHub-Api-Version: 2022-11-28',
    'User-Agent: RenjuTaiwan-Rank-Sync',
    'Content-Type: application/json',
];
$body = json_encode(['ref' => 'main'], JSON_THROW_ON_ERROR);
$status = 0;

try {
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('無法建立 GitHub API 連線。');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $result = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $failed = $result === false;
        curl_close($curl);
        if ($failed) throw new RuntimeException('GitHub API 暫時無法連線，請稍後再試。');
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 25,
                'follow_location' => 0,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) throw new RuntimeException('GitHub API 暫時無法連線，請稍後再試。');
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $match)) $status = (int)$match[1];
        }
    }

    if ($status !== 204) {
        if ($status === 401 || $status === 403) {
            throw new RuntimeException('GitHub 存取權杖無效或缺少 Actions 寫入權限（HTTP ' . $status . '）。');
        }
        if ($status === 404) {
            throw new RuntimeException('找不到同步工作流程，或 GitHub 存取權杖沒有存取此儲存庫的權限（HTTP 404）。');
        }
        throw new RuntimeException('GitHub 未接受更新要求（HTTP ' . $status . '）。');
    }

    $_SESSION['rn_sync_last_dispatch'] = time();
    rnSyncRedirect(
        $back,
        'success',
        '已送出「更新 RenjuNet」要求。GitHub Actions 將在背景下載並驗證 RIF；按鈕送出不代表同步已完成，也不會自動重新計算歷史 Elo。'
    );
} catch (Throwable $error) {
    rnSyncRedirect($back, 'error', $error->getMessage());
}
