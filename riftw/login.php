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

    // record.php 的各項紀錄只統計實際對局，不把輪空算進 GAME。
    // 不再建立同名 TEMPORARY GAME，因為部分統計會在同一 SQL 內多次引用 GAME，
    // MySQL 會因此觸發 "Can't reopen table" 並中斷後續整頁統計。
    // 改由本頁的資料庫代理在每個查詢中，把 GAME 改寫成排除輪空的衍生表。
    $recordPdo = $MYSQL;
    $MYSQL = new class($recordPdo) {
        private $pdo;

        public function __construct($pdo) {
            $this->pdo = $pdo;
        }

        private function filterGameTable($sql) {
            if (!is_string($sql) || strpos($sql, '`GAME`') === false) return $sql;

            $filteredGame = "(SELECT * FROM `renjuorg_587`.`GAME` WHERE COALESCE(`備註`, '') <> '輪空')";

            // 先處理有明確別名的 FROM/JOIN `GAME` `G1`，避免衍生表出現雙重別名。
            $sql = preg_replace(
                '/\\b(FROM|JOIN)\\s+`GAME`\\s+(`[^`]+`)/i',
                '$1 ' . $filteredGame . ' $2',
                $sql
            );

            // 再處理沒有別名的 GAME；MySQL 衍生表必須有別名，因此補上 `GAME`。
            $sql = preg_replace(
                '/\\b(FROM|JOIN)\\s+`GAME`/i',
                '$1 ' . $filteredGame . ' `GAME`',
                $sql
            );

            return $sql;
        }

        public function query($sql, ...$args) {
            return $this->pdo->query($this->filterGameTable($sql), ...$args);
        }

        public function prepare($sql, $options = []) {
            return $this->pdo->prepare($this->filterGameTable($sql), $options);
        }

        public function exec($sql) {
            return $this->pdo->exec($this->filterGameTable($sql));
        }

        public function __call($name, $arguments) {
            return $this->pdo->{$name}(...$arguments);
        }
    };
}

?>
