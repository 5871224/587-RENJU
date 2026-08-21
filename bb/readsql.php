<?php
// Legacy database endpoint intentionally disabled on the public website.
http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo 'Not Found';
exit;
