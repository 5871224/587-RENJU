<?php

declare(strict_types=1);

try {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        throw new RuntimeException('只允許 POST。');
    }

    // Browser requests must come from this site. This is an additional abuse
    // barrier; uploaded content is still validated independently below.
    $expectedHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $expectedHostOnly = strtolower((string)(parse_url('https://' . $expectedHost, PHP_URL_HOST) ?? $expectedHost));
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    $sourceHost = '';
    if ($origin !== '') {
        $sourceHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?? ''));
    } elseif ($referer !== '') {
        $sourceHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?? ''));
    }
    if ($sourceHost !== '' && !hash_equals($expectedHostOnly, $sourceHost)) {
        http_response_code(403);
        throw new RuntimeException('來源不允許。');
    }
    $fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '' && $fetchSite !== 'same-origin') {
        http_response_code(403);
        throw new RuntimeException('來源不允許。');
    }

    $configFile = dirname(__DIR__) . '/config.local.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Missing config.local.php.');
    }
    $config = require $configFile;
    if (!is_array($config)) {
        throw new RuntimeException('Invalid config.local.php.');
    }
    foreach (['imgbb_api_key', 'url_shortener_api_key'] as $key) {
        if (!isset($config[$key]) || $config[$key] === '') {
            throw new RuntimeException("Missing configuration setting: {$key}");
        }
    }

    if (!isset($_FILES['image']) || !is_array($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['image']['error'] ?? 'NO_FILE';
        throw new RuntimeException('圖片上傳失敗，錯誤代碼: ' . $errorCode);
    }

    $tmpFile = (string)($_FILES['image']['tmp_name'] ?? '');
    $fileSize = (int)($_FILES['image']['size'] ?? 0);
    if ($tmpFile === '' || !is_uploaded_file($tmpFile)) {
        throw new RuntimeException('不是有效的 HTTP 上傳檔案。');
    }
    if ($fileSize <= 0 || $fileSize > 8 * 1024 * 1024) {
        throw new RuntimeException('圖片大小必須介於 1 byte 與 8 MB 之間。');
    }

    $imageInfo = @getimagesize($tmpFile);
    $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    $mime = is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';
    if (!in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('只允許 PNG、JPEG、GIF 或 WebP 圖片。');
    }

    // Never move the upload into the web directory. Read the PHP-managed
    // temporary upload directly so an uploaded file can never become executable.
    $rawImage = file_get_contents($tmpFile);
    if ($rawImage === false || $rawImage === '') {
        throw new RuntimeException('無法讀取上傳圖片。');
    }
    $imageData = base64_encode($rawImage);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . rawurlencode((string)$config['imgbb_api_key']));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $imageData]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);

    $PNGURL = '';
    if ($response === false) {
        error_log('ImgBB 請求失敗: ' . curl_error($ch));
    } else {
        $responseData = json_decode((string)$response, true);
        if (is_array($responseData) && isset($responseData['data']['url'])) {
            $PNGURL = (string)$responseData['data']['url'];
        } else {
            error_log('ImgBB 回傳格式錯誤');
        }
    }
    curl_close($ch);

    $sourceUrl = trim((string)($_POST['URL'] ?? ''));
    if ($sourceUrl === '' || strlen($sourceUrl) > 8000 || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('URL 參數格式不正確。');
    }
    $scheme = strtolower((string)(parse_url($sourceUrl, PHP_URL_SCHEME) ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new RuntimeException('URL 只允許 http 或 https。');
    }

    $TINYURL = '';
    $apiUrl = 'https://scct.tw/index.php';
    $payload = json_encode(['url' => $sourceUrl], JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('URL 編碼失敗。');
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-API-KEY: ' . (string)$config['url_shortener_api_key']]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $response !== '' && $httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode((string)$response, true);
        $TINYURL = is_array($decoded) && isset($decoded['short']) ? trim((string)$decoded['short']) : trim((string)$response);
    }

    if ($TINYURL === '' || stripos($TINYURL, 'http') !== 0) {
        $ch = curl_init('https://is.gd/create.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['format' => 'simple', 'url' => $sourceUrl]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response !== false && stripos((string)$response, 'Error') === false && trim((string)$response) !== '') {
            $TINYURL = trim((string)$response);
        }
    }

    if ($TINYURL === '' || stripos($TINYURL, 'http') !== 0) {
        $ch = curl_init('https://tinyurl.com/api-create.php?url=' . rawurlencode($sourceUrl));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        if ($response === false || trim((string)$response) === '') {
            error_log('tinyurl 呼叫失敗: ' . curl_error($ch));
        } else {
            $TINYURL = trim((string)$response);
        }
        curl_close($ch);
    }

    if ($TINYURL === '' || stripos($TINYURL, 'http') !== 0) {
        $TINYURL = $sourceUrl;
    }

    header('Content-Type: text/plain; charset=UTF-8');
    echo $TINYURL;

    require_once 'boradlogin.php';
    $queryPos = strpos($sourceUrl, '?');
    $move = $queryPos === false ? '' : substr($sourceUrl, $queryPos + 1);
    $title = (string)($_POST['TITLE'] ?? '');
    if (strlen($title) > 500) {
        $title = substr($title, 0, 500);
    }

    try {
        $stmt = $MYSQL->prepare('INSERT INTO borad (MOVE,PNG,TITLE,TINYURL) VALUES (:move,:png,:title,:tinyurl) ON DUPLICATE KEY UPDATE `PNG`=VALUES(`PNG`),`TITLE`=VALUES(`TITLE`)');
        $stmt->execute([
            ':move' => $move,
            ':png' => $PNGURL,
            ':title' => $title,
            ':tinyurl' => $TINYURL,
        ]);
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
    }
} catch (Throwable $e) {
    if (http_response_code() < 400) {
        http_response_code(400);
    }
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ERROR: ' . $e->getMessage();
    error_log('upload.php 錯誤: ' . $e->getMessage());
}
