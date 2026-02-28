<?php
/**
 * Cleanup duplicate business config files
 * Run once on hosting to remove auto-generated duplicates
 */
define('APP_ACCESS', true);
require_once 'config/config.php';

$dir = __DIR__ . '/config/businesses/';
$configs = [];
$removed = [];

// Load all config files
foreach (scandir($dir) as $f) {
    if ($f === '.' || $f === '..' || pathinfo($f, PATHINFO_EXTENSION) !== 'php') continue;
    $cfg = require $dir . $f;
    $db = $cfg['database'] ?? '';
    $configs[] = ['file' => $f, 'name' => $cfg['name'] ?? '?', 'db' => $db, 'icon' => $cfg['theme']['icon'] ?? '?'];
}

// Find duplicates by database
$byDb = [];
foreach ($configs as $c) {
    $byDb[$c['db']][] = $c;
}

echo "<h2>Business Config Files</h2><pre>";
foreach ($configs as $c) {
    echo "{$c['file']} => {$c['icon']} {$c['name']} (db: {$c['db']})\n";
}

echo "\n--- Duplicates ---\n";
foreach ($byDb as $db => $items) {
    if (count($items) > 1) {
        echo "Database '{$db}' has " . count($items) . " configs:\n";
        // Keep the one in git (known slugs: cqc.php, bens-cafe.php, etc.)
        $knownFiles = ['cqc.php', 'bens-cafe.php', 'narayana-hotel.php', 'demo.php'];
        foreach ($items as $item) {
            $isKnown = in_array($item['file'], $knownFiles);
            if (!$isKnown) {
                $fullPath = $dir . $item['file'];
                if (unlink($fullPath)) {
                    echo "  REMOVED: {$item['file']} ({$item['icon']} {$item['name']})\n";
                    $removed[] = $item['file'];
                } else {
                    echo "  FAILED TO REMOVE: {$item['file']}\n";
                }
            } else {
                echo "  KEPT: {$item['file']} ({$item['icon']} {$item['name']})\n";
            }
        }
    }
}

if (empty($removed)) {
    echo "No duplicates found locally. If you see duplicates on hosting, run this script there.\n";
}
echo "</pre>";
