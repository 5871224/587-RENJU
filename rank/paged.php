<?php
ob_start();
require __DIR__ . '/index.php';
$html = ob_get_clean();
if (!is_string($html)) {
    $html = '';
}
$html = str_replace(
    '</head>',
    '<link rel="stylesheet" href="pagination.css?v=20260821b">' . "\n</head>",
    $html
);
$html = str_replace(
    '</body>',
    '<script src="pagination.js?v=20260821b"></script>' . "\n</body>",
    $html
);
echo $html;
