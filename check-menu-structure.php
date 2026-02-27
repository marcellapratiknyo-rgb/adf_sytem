<?php
/**
 * Check Menu Items Table Structure & Fix Column Reference
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h2>🔍 Fixing Menu Items Table Structure</h2>\n";

$isHosting = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
              strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

$dbHost = 'localhost';
$dbUser = $isHosting ? 'adfb2574_adfsystem' : 'root';
$dbPass = $isHosting ? '@Nnoc2025' : '';
$masterDb = $isHosting ? 'adfb2574_adf' : 'adf_system';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$masterDb", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Step 1: Check menu_items columns
    echo "<h3>1️⃣ Checking MENU_ITEMS table structure...</h3>\n";
    
    $columns = $pdo->query("SHOW COLUMNS FROM menu_items")->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Found columns:\n";
    echo "<ul>\n";
    foreach ($columns as $col) {
        echo "<li><code>" . $col['Field'] . "</code> - " . $col['Type'] . "</li>\n";
    }
    echo "</ul>\n";
    
    // Find the order column (could be 'order', 'sort_order', 'menu_order', 'position', etc)
    $orderColumn = null;
    $possibleOrderCols = ['order', 'sort_order', 'menu_order', 'position', 'sequence'];
    foreach ($possibleOrderCols as $col) {
        $result = $pdo->query("SHOW COLUMNS FROM menu_items WHERE Field = '$col'");
        if ($result->rowCount() > 0) {
            $orderColumn = $col;
            break;
        }
    }
    
    if ($orderColumn) {
        echo "\n<p style='background: #e8f5e9; padding: 10px; border-left: 4px solid green;'>\n";
        echo "✅ Found order column: <strong><code>$orderColumn</code></strong>\n";
        echo "</p>\n";
    } else {
        echo "\n<p style='background: #ffebee; padding: 10px; border-left: 4px solid red;'>\n";
        echo "❌ Order column not found! Will need to add it.\n";
        echo "</p>\n";
        
        // Add order column if missing
        echo "\n<h3>Adding ORDER column...</h3>\n";
        try {
            $pdo->exec("ALTER TABLE menu_items ADD COLUMN `order` INT DEFAULT 0");
            $orderColumn = 'order';
            echo "✅ Added `order` column\n";
        } catch (Exception $e) {
            echo "⚠️ Could not add order column: " . $e->getMessage() . "\n";
        }
    }
    
    // Step 2: Get all menus with correct column
    echo "\n<h3>2️⃣ Listing all menus (using column: <code>$orderColumn</code>)...</h3>\n";
    
    $menus = $pdo->query("SELECT id, name, code, `$orderColumn` as sort_order FROM menu_items ORDER BY `$orderColumn` ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
    echo "<tr style='background: #f0f0f0; border: 1px solid #ddd;'><th style='padding: 8px; border: 1px solid #ddd;'>ID</th><th style='padding: 8px; border: 1px solid #ddd;'>Name</th><th style='padding: 8px; border: 1px solid #ddd;'>Code</th></tr>\n";
    foreach ($menus as $m) {
        echo "<tr style='border: 1px solid #ddd;'><td style='padding: 8px; border: 1px solid #ddd;'>" . $m['id'] . "</td><td style='padding: 8px; border: 1px solid #ddd;'>" . $m['name'] . "</td><td style='padding: 8px; border: 1px solid #ddd;'><code>" . $m['code'] . "</code></td></tr>\n";
    }
    echo "</table>\n";
    
    // Step 3: Check CQC business menu assignments
    echo "\n<h3>3️⃣ Checking CQC (ID: 7) menu assignments...</h3>\n";
    
    $cqcMenus = $pdo->query("
        SELECT m.id, m.name, m.code FROM menu_items m
        JOIN business_menu_config bmc ON bmc.menu_item_id = m.id
        WHERE bmc.business_id = 7
        ORDER BY m.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ CQC has " . count($cqcMenus) . " menus assigned:\n";
    if (!empty($cqcMenus)) {
        echo "<ol>\n";
        foreach ($cqcMenus as $m) {
            echo "<li>" . $m['name'] . " (<code>" . $m['code'] . "</code>)</li>\n";
        }
        echo "</ol>\n";
    } else {
        echo "<p style='color: red;'>❌ No menus assigned!</p>\n";
    }
    
    // Step 4: Check permission setup
    echo "\n<h3>4️⃣ Checking CQC user menu permissions...</h3>\n";
    
    try {
        $perms = $pdo->query("SELECT * FROM user_menu_permissions WHERE business_id = 7")->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Permission records for CQC: " . count($perms) . "\n";
        
        if (!empty($perms)) {
            echo "<p>Sample permissions (first 5):</p>\n";
            echo "<ul>\n";
            foreach (array_slice($perms, 0, 5) as $p) {
                echo "<li>User " . $p['user_id'] . " - Menu: " . $p['menu_code'] . " - Can View: " . ($p['can_view'] ? '✓' : '✗') . "</li>\n";
            }
            echo "</ul>\n";
        }
    } catch (Exception $e) {
        echo "⚠️ Permission table query failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n<h3>✅ Diagnostic Complete</h3>\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "\n";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; }
h2, h3 { color: #333; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
table { margin: 10px 0; }
</style>
