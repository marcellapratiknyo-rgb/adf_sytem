<?php
/**
 * Check cash_accounts table structure on HOSTING
 * Upload to: public_html/check-cash-accounts-structure.php
 * Access: https://adfsystem.online/check-cash-accounts-structure.php
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Cash Accounts Table Structure - Hosting</h1>";

try {
    $masterDbName = defined('MASTER_DB_NAME') ? MASTER_DB_NAME : DB_NAME;
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $masterDbName, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Master Database: <code>$masterDbName</code></h2>";
    
    // Check if cash_accounts table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'cash_accounts'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p style='color: red;'><strong>ERROR: cash_accounts table NOT FOUND!</strong></p>";
        echo "<h3>Available Tables:</h3>";
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<pre>";
        print_r($allTables);
        echo "</pre>";
    } else {
        echo "<p style='color: green;'><strong>✓ cash_accounts table EXISTS</strong></p>";
        
        // Show table structure
        echo "<h3>Table Structure (DESCRIBE cash_accounts):</h3>";
        $cols = $pdo->query("DESCRIBE cash_accounts")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($cols as $col) {
            echo "<tr>";
            echo "<td><strong>{$col['Field']}</strong></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if required columns exist
        $columnNames = array_column($cols, 'Field');
        echo "<h3>Column Checklist:</h3>";
        $requiredCols = ['id', 'business_id', 'account_name', 'account_type', 'current_balance', 'is_active'];
        echo "<ul>";
        foreach ($requiredCols as $req) {
            $exists = in_array($req, $columnNames);
            $icon = $exists ? '✓' : '✗';
            $color = $exists ? 'green' : 'red';
            echo "<li style='color: $color;'><strong>$icon $req</strong> - " . ($exists ? "EXISTS" : "MISSING") . "</li>";
        }
        echo "</ul>";
        
        // Show sample data
        echo "<h3>Sample Data (first 10 rows):</h3>";
        $sample = $pdo->query("SELECT * FROM cash_accounts LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($sample);
        echo "</pre>";
        
        // Count by business_id if column exists
        if (in_array('business_id', $columnNames)) {
            echo "<h3>Count by business_id:</h3>";
            $counts = $pdo->query("SELECT business_id, COUNT(*) as count FROM cash_accounts GROUP BY business_id")->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($counts);
            echo "</pre>";
        }
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>ERROR:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
