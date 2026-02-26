<?php
/**
 * Diagnose Database Connection Issue for New Business
 * Check: Database exists, credentials, permissions, config mapping
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h2>🔍 Database Connection Diagnostic</h2>\n";

// Detect environment
$isHosting = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
              strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

echo "<p><strong>Environment:</strong> " . ($isHosting ? "🌐 HOSTING" : "💻 LOCAL") . "</p>\n";
echo "<p><strong>HTTP_HOST:</strong> " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'CLI') . "</p>\n";

// Get credentials based on environment
if ($isHosting) {
    $dbHost = 'localhost';
    $dbUser = 'adfb2574_adfsystem';
    $dbPass = '@Nnoc2025';
} else {
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
}

echo "<p><strong>Using credentials:</strong> User: <code>$dbUser</code></p>\n";
echo "<hr>\n";

try {
    // Test master connection
    echo "<h3>1️⃣ Testing Master Database Connection...</h3>\n";
    $masterPdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
    echo "✅ Connected as: <code>$dbUser</code>\n\n";
    
    // List databases user has access to
    echo "<h3>2️⃣ Databases User Can Access:</h3>\n";
    $databases = $masterPdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<table style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background: #f0f0f0; border: 1px solid #ddd;'><th style='padding: 8px; border: 1px solid #ddd;'>Database Name</th></tr>\n";
    
    $adfDatabases = [];
    foreach ($databases as $db) {
        if (strpos($db, 'adf') !== false || strpos($db, 'cqc') !== false) {
            $adfDatabases[] = $db;
            $highlight = (strpos($db, 'cqc') !== false) ? "style='background: #fef2f2;'" : "";
            echo "<tr $highlight style='border: 1px solid #ddd;'><td style='padding: 8px; border: 1px solid #ddd;'>$db</td></tr>\n";
        }
    }
    echo "</table>\n";
    
    // Check if adf_cqc or adfb2574_adf_cqc exists
    echo "\n<h3>3️⃣ Checking for CQC databases:</h3>\n";
    
    $checkNames = ['adf_cqc', 'adfb2574_adf_cqc', 'adfb2574_CQC'];
    foreach ($checkNames as $name) {
        if (in_array($name, $databases)) {
            echo "✅ Found: <code>$name</code>\n";
            
            // Try to connect
            try {
                $testPdo = new PDO("mysql:host=$dbHost;dbname=$name", $dbUser, $dbPass);
                echo "   ✅ Connection successful\n";
                
                // Check tables
                $tables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                echo "   📊 Tables: " . (count($tables) > 0 ? implode(', ', $tables) : "❌ NO TABLES") . "\n\n";
            } catch (Exception $e) {
                echo "   ❌ Connection failed: " . $e->getMessage() . "\n\n";
            }
        } else {
            echo "❌ NOT found: <code>$name</code>\n";
        }
    }
    
    // Check config
    echo "\n<h3>4️⃣ Checking config/businesses.php:</h3>\n";
    $configFile = __DIR__ . '/../../config/businesses.php';
    if (file_exists($configFile)) {
        echo "✅ Config file exists\n";
        $content = file_get_contents($configFile);
        if (strpos($content, 'cqc') !== false || strpos($content, 'CQC') !== false) {
            echo "✅ CQC business found in config\n";
            // Extract the business entry
            preg_match("/\['id'.*?'name'.*?'database'\s*=>\s*'([^']+)'.*?\],/s", $content, $matches);
            if (!empty($matches)) {
                echo "   Database configured as: <code>" . $matches[1] . "</code>\n";
            }
        } else {
            echo "❌ CQC business NOT found in config\n";
        }
    } else {
        echo "❌ Config file NOT found at: $configFile\n";
    }
    
    echo "\n<hr>\n";
    echo "<h3>✅ Solution:</h3>\n";
    echo "<ol>\n";
    echo "<li>If <code>adf_cqc</code> exists but <code>adfb2574_adf_cqc</code> doesn't: Database wasn't renamed properly on hosting</li>\n";
    echo "<li>If neither exists: Database creation failed</li>\n";
    echo "<li>If database exists but has no tables: Need to sync/setup tables</li>\n";
    echo "</ol>\n";
    
    echo "<p><a href='javascript:history.back()'>← Back</a></p>\n";
    
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "\n";
    echo "<p>Could not connect to database. Check credentials.</p>\n";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
table { margin: 15px 0; }
</style>
