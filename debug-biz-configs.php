<?php
header('Content-Type: application/json');
$dir = __DIR__ . '/config/businesses/';
$files = [];
foreach (scandir($dir) as $f) {
    if ($f === '.' || $f === '..') continue;
    $cfg = require $dir . $f;
    $files[] = [
        'file' => $f,
        'name' => $cfg['name'] ?? '?',
        'icon' => $cfg['theme']['icon'] ?? '?',
        'type' => $cfg['business_type'] ?? '?'
    ];
}
echo json_encode(['files' => $files, 'count' => count($files)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
