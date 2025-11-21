<?php
// Point to the SAO.png file within the Assests directory
$iconPath = __DIR__ . '/Assests/SAO.png';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

if (file_exists($iconPath)) {
    readfile($iconPath);
    exit;
} else {
    http_response_code(404);
    echo 'Favicon not found.';
    exit;
}
