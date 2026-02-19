<?php
// Update deposits grouping logic in index.php
$file = 'modules/investor/index.php';
$content = file_get_contents($file);

$oldCode = <<<'EOL'
// Get recent deposits
try {
    $recentDeposits = $db->query("
        SELECT it.*, 
               COALESCE(i.name, i.investor_name) as investor_name
        FROM investor_transactions it
        JOIN investors i ON it.investor_id = i.id
        WHERE it.type = 'capital' OR it.transaction_type = 'capital'
        ORDER BY it.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentDeposits = [];
}
EOL;

$newCode = <<<'EOL'
// Get recent deposits grouped by investor
try {
    $recentDeposits = $db->query("
        SELECT it.*, 
               COALESCE(i.name, i.investor_name) as investor_name,
               COALESCE(i.contact, i.contact_phone) as investor_contact,
               i.id as investor_id
        FROM investor_transactions it
        JOIN investors i ON it.investor_id = i.id
        WHERE it.type = 'capital' OR it.transaction_type = 'capital'
        ORDER BY i.id, it.created_at DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by investor
    $depositsByInvestor = [];
    foreach ($recentDeposits as $deposit) {
        $investorId = $deposit['investor_id'];
        if (!isset($depositsByInvestor[$investorId])) {
            $depositsByInvestor[$investorId] = [
                'name' => $deposit['investor_name'],
                'contact' => $deposit['investor_contact'],
                'deposits' => []
            ];
        }
        $depositsByInvestor[$investorId]['deposits'][] = $deposit;
    }
} catch (Exception $e) {
    $recentDeposits = [];
    $depositsByInvestor = [];
}
EOL;

if (strpos($content, $oldCode) !== false) {
    $content = str_replace($oldCode, $newCode, $content);
    if (file_put_contents($file, $content)) {
        echo "✓ Deposits grouping logic updated!\n";
    } else {
        echo "✗ Failed to update file\n";
    }
} else {
    echo "⚠ Old code pattern not found\n";
}
?>
