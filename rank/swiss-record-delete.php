<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
$type = strtoupper(trim((string)($_POST['type'] ?? '')));
$table = $type === 'SUMMARY' ? 'SUMMARY' : ($type === 'DEN' ? 'DEN' : '');
$tour = max(0, (int)($_POST['TOUR'] ?? 0));
$id = max(0, (int)($_POST['id'] ?? 0));
if ($table === '' || $tour <= 0 || $id <= 0) { http_response_code(400); exit('參數錯誤'); }
try {
    $stmt = $MYSQL->prepare("DELETE FROM `$table` WHERE `序號`=? AND `賽號`=? LIMIT 1");
    $stmt->execute([$id, $tour]);
    header('Location: swiss.php?TOUR=' . $tour);
    exit;
} catch (Throwable $e) {
    http_response_code(500); echo '刪除失敗：' . swissH($e->getMessage());
}
