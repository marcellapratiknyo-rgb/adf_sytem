<?php
// Script to update investor module with new deposit history component
$filePath = 'modules/investor/index.php';
$originalFile = file_get_contents($filePath);

// Check if file exists
if (!$originalFile) {
    die("File not found: $filePath");
}

// Pattern to find the old table section - starting from table class and ending at closing div
$pattern = '/(<\?php else: \?>)\s*<table class="history-table">[\s\S]*?<\/table>\s*(<\?php endif; \?>)/';
$replacement = '<?php else: ?><?php include "modules/investor/deposits-history.php"; ?><?php endif; ?>';

$newFile = preg_replace($pattern, $replacement, $originalFile);

if ($newFile === $originalFile) {
    die("Pattern not found or no replacement made");
}

// Write back to file
file_put_contents($filePath, $newFile);
echo "✓ File updated successfully!";
?>
