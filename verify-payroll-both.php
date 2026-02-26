<?php
echo "=== CHECKING LOCAL DATABASE (adf_system) ===\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=adf_system', 'root', '');
    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM menu_items WHERE menu_code = "payroll"');
    $result = $stmt->fetch();
    echo "Local: Payroll menu exists? " . ($result['cnt'] > 0 ? "✅ YES" : "❌ NO") . "\n";
    
    // Check business assignment
    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM business_menu_config WHERE menu_id = 12');
    $result = $stmt->fetch();
    echo "Local: Payroll assigned to businesses? " . ($result['cnt'] > 0 ? "✅ YES ({$result['cnt']} businesses)" : "❌ NO") . "\n\n";
} catch (Exception $e) {
    echo "Local error: " . $e->getMessage() . "\n\n";
}

echo "=== CHECKING HOSTING DATABASE ===\n";
echo "The live site (adfsystem.online) uses different database credentials.\n";
echo "To update the live site, we need to either:\n";
echo "1. Run rebuild-menus.php on the hosting server\n";
echo "2. Or modify it to connect to hosting database\n";
echo "3. Or create a patch script that works with hosting\n\n";

// Check if there's any reference to hosting credentials
$files_to_check = [
    'config/config.php',
    'config/database.php',
    'modules/owner/config/config.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "Found config: $file\n";
    }
}
?>
