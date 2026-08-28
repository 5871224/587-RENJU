<?php

require_once dirname(__DIR__) . '/db.php';
$MYSQL = connectDatabase('renjuorg_587');

$scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

function normalizeArchiveDateParam(): ?string {
    $requestedDate = trim((string)($_GET['DATE'] ?? ''));
    if ($requestedDate === '') return null;
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $dateValid = $dateObject instanceof DateTimeImmutable
        && ($dateErrors === false || (($dateErrors['warning_count'] ?? 0) === 0 && ($dateErrors['error_count'] ?? 0) === 0))
        && $dateObject->format('Y-m-d') === $requestedDate;
    return $dateValid ? $requestedDate : null;
}

// Legacy tournament pages historically concatenated TOUR into SQL. Normalize the
// shared variable here as a second line of defense in addition to .htaccess.
if ($scriptName === 'tour.php' || $scriptName === 'tourb.php') {
    $safeTour = filter_input(INPUT_GET, 'TOUR', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $TT = ($safeTour === false || $safeTour === null) ? 1 : $safeTour;
}

// rank.php has already read DATE before including this file, so overwrite its
// YY/Y5 variables. record.php reads DATE afterwards, so sanitize $_GET itself.
if ($scriptName === 'rank.php') {
    $safeDate = normalizeArchiveDateParam();
    $YY = $safeDate ?? date('Y-m-d');
    $Y5 = (new DateTimeImmutable($YY))->modify('-5 years')->format('Y-m-d');
    if ($safeDate === null) unset($_GET['DATE']);
    else $_GET['DATE'] = $safeDate;
}

if ($scriptName === 'record.php') {
    $safeDate = normalizeArchiveDateParam();
    if ($safeDate === null) unset($_GET['DATE']);
    else $_GET['DATE'] = $safeDate;

    // 不以同名 TEMPORARY GAME 覆蓋正式資料表。
    // record.php 有查詢會在同一個 SQL 內多次引用 GAME；MySQL 對 temporary table
    // 會觸發 "Can't reopen table"，導致後續統計整頁中斷。
}

?>
