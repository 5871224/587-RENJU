<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '../riftw/player.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;
