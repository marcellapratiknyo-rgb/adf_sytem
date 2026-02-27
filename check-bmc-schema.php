<?php
/**
 * Check business_menu_config Table Structure
 * Shows exact column names to use
 */

header('Content-Type: text/html; charset=utf-8');

$isHosting = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
              strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

$dbHost = 'localhost';
$dbUser = $isHosting ? 'adfb2574_adfsystem' : 'root';
$dbPass = $isHosting ? '@Nnoc2025' : '';
$masterDb = $isHosting ? 'adfb2574_adf' : 'adf_system';

echo "<h2>🔍 Database Schema Check</h2>\n";

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$masterDb", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check business_menu_config columns
    echo "<h3>1️⃣ business_menu_config table columns:</h3>\n";
    $cols = $pdo->query("SHOW COLUMNS FROM business_menu_config")->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>\n";
    foreach ($cols as $col) {
        echo "<li><code>" . $col['Field'] . "</code> - " . $col['Type'] . "</li>\n";
    }
    echo "</ul>\n";
    
    // Show sample data
    echo "<h3>2️⃣ Sample data from business_menu_config:</h3>\n";
    $data = $pdo->query("SELECT * FROM business_menu_config LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($data)) {
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;'>\n";
        print_r($data);
        echo "</pre>\n";
    } else {
        echo "<p>❌ No data in business_menu_config</p>\n";
    }
    
    // Check menu_items columns
    echo "<h3>3️⃣ menu_items table columns:</h3>\n";
    $cols = $pdo->query("SHOW COLUMNS FROM menu_items")->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>\n";
    foreach ($cols as $col) {
        echo "<li><code>" . $col['Field'] . "</code> - " . $col['Type'] . "</li>\n";
    }
    echo "</ul>\n";
    
    // Show menu items sample
    echo "<h3>4️⃣ Sample data from menu_items:</h3>\n";
    $data = $pdo->query("SELECT * FROM menu_items LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($data)) {
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;'>\n";
        print_r($data);
        echo "</pre>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "\n";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
h2, h3 { color: #333; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
</style>
