<?php
/**
 * Check Businesses Table
 */
require_once 'config/config.php';

echo "<h1>Check Businesses Table</h1>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;margin:10px 0;} th,td{border:1px solid #ddd;padding:8px;} th{background:#333;color:white;}</style>";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=adf_system;charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'businesses'")->fetchAll();
    
    if (count($tables) == 0) {
        echo "<h2 style='color:red;'>❌ Table 'businesses' NOT FOUND!</h2>";
        echo "<p>This is the problem! API needs 'businesses' table but it doesn't exist.</p>";
        echo "<p><strong>Solution:</strong> You need to import the multi-business schema or change API to use current database directly.</p>";
    } else {
        echo "<h2 style='color:green;'>✅ Table 'businesses' EXISTS</h2>";
        
        // Get data
        $stmt = $pdo->query("SELECT * FROM businesses ORDER BY id");
        $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($businesses) == 0) {
            echo "<h3 style='color:orange;'>⚠️ Table is EMPTY!</h3>";
            echo "<p>No businesses configured. API cannot work without business records.</p>";
            echo "<hr>";
            echo "<h3>Quick Fix: Insert Current System as Business</h3>";
            echo "<p>Run this SQL to add current system as a business:</p>";
            echo "<pre>INSERT INTO businesses (business_name, database_name, business_type, is_active) 
VALUES ('My Business', 'adf_system', 'hotel', 1);</pre>";
        } else {
            echo "<h3>✅ Found " . count($businesses) . " business(es):</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Business Name</th><th>Database Name</th><th>Business Type</th><th>Active</th></tr>";
            foreach ($businesses as $biz) {
                echo "<tr>";
                echo "<td>{$biz['id']}</td>";
                echo "<td>{$biz['business_name']}</td>";
                echo "<td>{$biz['database_name']}</td>";
                echo "<td>" . ($biz['business_type'] ?? '-') . "</td>";
                echo "<td>" . ($biz['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check if databases exist
            echo "<hr><h3>Database Verification:</h3>";
            foreach ($businesses as $biz) {
                $dbName = $biz['database_name'];
                try {
                    $testPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=$dbName;charset=utf8mb4", DB_USER, DB_PASS);
                    echo "<p style='color:green;'>✅ Database <strong>$dbName</strong> exists and accessible</p>";
                    
                    // Check cash_book table
                    $checkTable = $testPdo->query("SHOW TABLES LIKE 'cash_book'")->fetchAll();
                    if (count($checkTable) > 0) {
                        $count = $testPdo->query("SELECT COUNT(*) as total FROM cash_book")->fetch(PDO::FETCH_ASSOC);
                        echo "<p>&nbsp;&nbsp;&nbsp;→ cash_book: {$count['total']} transactions</p>";
                    } else {
                        echo "<p style='color:orange;'>&nbsp;&nbsp;&nbsp;⚠️ cash_book table not found</p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color:red;'>❌ Database <strong>$dbName</strong> NOT accessible: " . $e->getMessage() . "</p>";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "<h2 style='color:red;'>ERROR: " . $e->getMessage() . "</h2>";
}

echo "<hr>";
echo "<p><a href='modules/owner/dashboard-dev.php'>Back to Dashboard</a></p>";
?>
