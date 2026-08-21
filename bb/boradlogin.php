<?php

// upload.php stores TITLE and 5.php later renders it in an HTML meta attribute.
// Encode it before storage so new/updated records cannot break out of that
// attribute even though the legacy reader predates output escaping.
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'upload.php' && isset($_POST['TITLE'])) {
    $title = (string)$_POST['TITLE'];
    $title = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $title) ?? '';
    $_POST['TITLE'] = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require_once dirname(__DIR__) . '/db.php';
$MYSQL = connectDatabase('renjuorg_587');

?>
