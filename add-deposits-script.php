<?php
// Add deposits script include to index.php
$file = 'modules/investor/index.php';
$content = file_get_contents($file);

// Check if script tag already added
if (strpos($content, 'deposits-script.js') !== false) {
    echo "Script already included\n";
    exit;
}

// Replace the chart.js script line with version that also includes deposits-script.js
$oldLine = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>';
$newLines = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>' . "\n" . '<script src="deposits-script.js"></script>';

$content = str_replace($oldLine, $newLines, $content);

if (file_put_contents($file, $content)) {
    echo "✓ Script include added successfully!\n";
} else {
    echo "✗ Failed to update file\n";
}
?>
