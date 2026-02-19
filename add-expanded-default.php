<?php
// Add default expanded class to deposit groups
$file = 'modules/investor/deposits-history.php';
$content = file_get_contents($file);

$oldLine = '<div class="investor-deposit-group">';
$newLine = '<div class="investor-deposit-group expanded">';

$content = str_replace($oldLine, $newLine, $content);

if (file_put_contents($file, $content)) {
    echo "✓ Deposit groups set to expanded by default!\n";
} else {
    echo "✗ Failed to update file\n";
}
?>
