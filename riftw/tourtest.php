<?php
// This legacy endpoint previously accepted arbitrary SQL over HTTP.
// It is intentionally disabled now that the repository is public.
http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo 'Not Found';
exit;
