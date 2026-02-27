<?php
/**
 * Final Verification & Fix for CQC Menus
 */

header('Content-Type: text/html; charset=utf-8');

$isHosting = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
              strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

$dbHost = 'localhost';
$dbUser = $isHosting ? 'adfb2574_adfsystem' : 'root';
$dbPass = $isHosting ? '@Nnoc2025' : '';
$masterDb = $isHosting ? 'adfb2574_adf' : 'adf_system';

echo "<h2>🔍 Final Verification & Fix</h2>\n";

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$masterDb", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $businessId = 7;
    
    // Step 1: Check current assignments
    echo "<h3>1️⃣ Current menu assignments for CQC (ID 7):</h3>\n";
    $current = $pdo->query("
        SELECT COUNT(*) as total FROM business_menu_config 
        WHERE business_id = $businessId
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "Total assigned: <strong>" . $current['total'] . "</strong>\n\n";
    
    if ($current['total'] == 0) {
        echo "❌ NO MENUS ASSIGNED! Will assign now...\n\n";
        
        // Get all menus
        $menus = $pdo->query("SELECT id, menu_name FROM menu_items WHERE is_active = 1 ORDER BY menu_order")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Found " . count($menus) . " menus:<br>\n";
        echo "<ol>\n";
        foreach ($menus as $m) {
            echo "<li>" . htmlspecialchars($m['menu_name']) . " (ID: " . $m['id'] . ")</li>\n";
        }
        echo "</ol>\n";
        
        // Clear any old records
        $pdo->exec("DELETE FROM business_menu_config WHERE business_id = $businessId");
        
        // Insert all menus
        $stmt = $pdo->prepare("INSERT INTO business_menu_config (business_id, menu_id, is_enabled) VALUES (?, ?, 1)");
        
        foreach ($menus as $menu) {
            $stmt->execute([$businessId, $menu['id']]);
        }
        
        echo "\n✅ Assigned " . count($menus) . " menus\n";
    } else {
        echo "✅ Already assigned menus\n\n";
        
        $assigned = $pdo->query("
            SELECT m.id, m.menu_name FROM menu_items m
            JOIN business_menu_config bmc ON bmc.menu_id = m.id
            WHERE bmc.business_id = $businessId
            ORDER BY m.menu_order
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Assigned menus:<br>\n";
        echo "<ol>\n";
        foreach ($assigned as $m) {
            echo "<li>" . htmlspecialchars($m['menu_name']) . "</li>\n";
        }
        echo "</ol>\n";
    }
    
    // Step 2: Check if there's is_enabled column (might prevent display)
    echo "\n<h3>2️⃣ Checking business_menu_config structure:</h3>\n";
    $cols = $pdo->query("SHOW COLUMNS FROM business_menu_config")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cols as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Step 3: Check if is_enabled is 1 for all assignments
    echo "\n<h3>3️⃣ Checking is_enabled status:</h3>\n";
    $status = $pdo->query("
        SELECT is_enabled, COUNT(*) as count 
        FROM business_menu_config 
        WHERE business_id = $businessId 
        GROUP BY is_enabled
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($status as $s) {
        echo "- is_enabled = " . $s['is_enabled'] . ": " . $s['count'] . " menus\n";
    }
    
    // If any disabled, enable them
    $disabled = $pdo->query("
        SELECT COUNT(*) as count FROM business_menu_config 
        WHERE business_id = $businessId AND is_enabled = 0
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($disabled['count'] > 0) {
        echo "\n⚠️ Found " . $disabled['count'] . " disabled menus. Enabling...\n";
        $pdo->exec("UPDATE business_menu_config SET is_enabled = 1 WHERE business_id = $businessId");
        echo "✅ Enabled\n";
    }
    
    // Step 4: Final verification
    echo "\n<h3>4️⃣ Final verification:</h3>\n";
    $final = $pdo->query("
        SELECT COUNT(*) as total FROM business_menu_config 
        WHERE business_id = $businessId AND is_enabled = 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "Enabled menus for CQC: <strong>" . $final['total'] . "</strong>\n";
    
    echo "\n<div style='background: #e8f5e9; padding: 15px; border-left: 4px solid green; margin-top: 20px;'>\n";
    echo "<strong>✅ Setup Complete!</strong><br>\n";
    echo "CQC now has " . $final['total'] . " menus.<br>\n";
    echo "<strong>Next:</strong> Logout and Login again to CQC, or refresh the page with browser cache clear (Ctrl+Shift+Delete) 🎉\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 15px; border-left: 4px solid red;'>\n";
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "</div>\n";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
h2, h3 { color: #333; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
</style>
