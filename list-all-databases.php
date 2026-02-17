<?php
/**
 * List All Databases Script
 * Check what databases actually exist on hosting
 */

session_start();

// Set session
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'developer';

// Detect environment
$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false);

if ($isProduction) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'adfb2574_adfsystem');
    define('DB_PASS', '@Nnoc2025');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>List All Databases</title>
    <style>
        body {
            font-family: monospace;
            background: #1a1a2e;
            color: #eee;
            padding: 20px;
        }
        .box {
            background: #16213e;
            border: 2px solid #0f3460;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .success { color: #4ade80; }
        .error { color: #ef4444; }
        .info { color: #60a5fa; }
        .warning { color: #fbbf24; }
        h2 { color: #a855f7; margin-top: 0; }
        pre { 
            background: #0f172a; 
            padding: 15px; 
            border-radius: 5px; 
            overflow-x: auto;
            line-height: 1.5;
        }
        .db-item {
            padding: 8px;
            margin: 5px 0;
            background: #0f172a;
            border-left: 4px solid #60a5fa;
            border-radius: 3px;
        }
        .db-item.adf {
            border-left-color: #4ade80;
        }
        .db-item.system {
            border-left-color: #fbbf24;
        }
    </style>
</head>
<body>
    <h1>🔍 List All Databases</h1>
    
    <?php
    echo "<div class='box'>";
    echo "<h2>Database User Info</h2>";
    echo "<pre>";
    echo "User: " . DB_USER . "\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Environment: " . ($isProduction ? 'PRODUCTION (Hosting)' : 'LOCAL') . "\n";
    echo "</pre>";
    echo "</div>";
    
    try {
        // Connect WITHOUT specifying database
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<div class='box'>";
        echo "<h2 class='success'>✅ Connected to MySQL Server</h2>";
        
        // Get list of all databases this user can access
        $dbStmt = $pdo->query("SHOW DATABASES");
        $databases = $dbStmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Total Databases Accessible: " . count($databases) . "</h3>";
        
        // Categorize databases
        $adfDatabases = [];
        $systemDatabases = [];
        $otherDatabases = [];
        
        foreach ($databases as $dbName) {
            if (strpos($dbName, 'adfb2574_') === 0) {
                $adfDatabases[] = $dbName;
            } elseif (in_array($dbName, ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
                $systemDatabases[] = $dbName;
            } else {
                $otherDatabases[] = $dbName;
            }
        }
        
        // Display ADF databases
        if (!empty($adfDatabases)) {
            echo "<h3 class='success'>🎯 ADF System Databases (" . count($adfDatabases) . "):</h3>";
            foreach ($adfDatabases as $db) {
                echo "<div class='db-item adf'>";
                echo "<strong class='success'>✅ " . htmlspecialchars($db) . "</strong>";
                
                // Try to connect and get table count
                try {
                    $testPdo = new PDO(
                        'mysql:host=' . DB_HOST . ';dbname=' . $db . ';charset=utf8mb4',
                        DB_USER,
                        DB_PASS
                    );
                    $tableStmt = $testPdo->query("SHOW TABLES");
                    $tableCount = $tableStmt->rowCount();
                    echo " <span class='info'>(" . $tableCount . " tables)</span>";
                    
                    // Check for specific tables
                    $tables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);
                    if (in_array('businesses', $tables)) {
                        echo " <span class='warning'>[MASTER DB]</span>";
                    }
                    if (in_array('cash_book', $tables)) {
                        echo " <span class='info'>[BUSINESS DB]</span>";
                        
                        // Count transactions
                        $countStmt = $testPdo->query("SELECT COUNT(*) FROM cash_book");
                        $transCount = $countStmt->fetchColumn();
                        echo " <span class='success'>[" . $transCount . " transactions]</span>";
                    }
                } catch (Exception $e) {
                    echo " <span class='error'>[Access Error]</span>";
                }
                
                echo "</div>";
            }
        }
        
        // Display other databases
        if (!empty($otherDatabases)) {
            echo "<h3 class='info'>📦 Other Databases (" . count($otherDatabases) . "):</h3>";
            foreach ($otherDatabases as $db) {
                echo "<div class='db-item'>";
                echo "<span class='info'>" . htmlspecialchars($db) . "</span>";
                echo "</div>";
            }
        }
        
        // Display system databases (collapsed)
        if (!empty($systemDatabases)) {
            echo "<h3 class='warning'>⚙️ System Databases (" . count($systemDatabases) . "):</h3>";
            echo "<details><summary>Click to expand</summary>";
            foreach ($systemDatabases as $db) {
                echo "<div class='db-item system'>";
                echo "<span class='warning'>" . htmlspecialchars($db) . "</span>";
                echo "</div>";
            }
            echo "</details>";
        }
        
        echo "</div>";
        
        // Now check businesses table for comparison
        echo "<div class='box'>";
        echo "<h2>📋 Businesses Table vs Actual Databases</h2>";
        
        try {
            // Find master database
            $masterDb = null;
            foreach ($adfDatabases as $db) {
                if (strpos($db, '_adf') !== false && strpos($db, 'narayana') === false && strpos($db, 'benscafe') === false && strpos($db, 'bens') === false) {
                    $masterDb = $db;
                    break;
                }
            }
            
            if ($masterDb) {
                echo "<p class='success'>Master Database: <strong>" . $masterDb . "</strong></p>";
                
                $masterPdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . $masterDb . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS
                );
                $masterPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $bizStmt = $masterPdo->query("SELECT id, business_code, branch_name, database_name, is_active FROM businesses ORDER BY id");
                $businesses = $bizStmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<table style='width:100%; border-collapse: collapse; margin-top: 15px;'>";
                echo "<tr style='background: #0f3460;'>";
                echo "<th style='padding: 10px; text-align: left;'>ID</th>";
                echo "<th style='padding: 10px; text-align: left;'>Code</th>";
                echo "<th style='padding: 10px; text-align: left;'>Name</th>";
                echo "<th style='padding: 10px; text-align: left;'>Database Name (in table)</th>";
                echo "<th style='padding: 10px; text-align: left;'>Status</th>";
                echo "</tr>";
                
                foreach ($businesses as $biz) {
                    $dbExists = in_array($biz['database_name'], $databases);
                    $status = $dbExists ? "<span class='success'>✅ EXISTS</span>" : "<span class='error'>❌ NOT FOUND</span>";
                    
                    echo "<tr style='border-bottom: 1px solid #0f3460;'>";
                    echo "<td style='padding: 10px;'>" . $biz['id'] . "</td>";
                    echo "<td style='padding: 10px;'>" . htmlspecialchars($biz['business_code']) . "</td>";
                    echo "<td style='padding: 10px;'>" . htmlspecialchars($biz['branch_name']) . "</td>";
                    echo "<td style='padding: 10px;'><code>" . htmlspecialchars($biz['database_name']) . "</code></td>";
                    echo "<td style='padding: 10px;'>" . $status . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                
                // Suggest corrections
                echo "<h3 class='warning'>💡 Suggested Database Names:</h3>";
                echo "<pre>";
                foreach ($businesses as $biz) {
                    $currentDb = $biz['database_name'];
                    $dbExists = in_array($currentDb, $databases);
                    
                    if (!$dbExists) {
                        echo "❌ " . $biz['branch_name'] . ":\n";
                        echo "   Current: " . $currentDb . " (NOT FOUND)\n";
                        
                        // Try to find matching database
                        $possibleMatches = [];
                        foreach ($adfDatabases as $db) {
                            if ($db !== $masterDb) {
                                $similarity = 0;
                                similar_text(strtolower($currentDb), strtolower($db), $similarity);
                                if ($similarity > 50) {
                                    $possibleMatches[] = ['db' => $db, 'similarity' => $similarity];
                                }
                            }
                        }
                        
                        if (!empty($possibleMatches)) {
                            usort($possibleMatches, function($a, $b) {
                                return $b['similarity'] <=> $a['similarity'];
                            });
                            echo "   Suggested: " . $possibleMatches[0]['db'] . " (" . round($possibleMatches[0]['similarity']) . "% match)\n";
                            echo "   SQL: UPDATE businesses SET database_name = '" . $possibleMatches[0]['db'] . "' WHERE id = " . $biz['id'] . ";\n";
                        }
                        echo "\n";
                    } else {
                        echo "✅ " . $biz['branch_name'] . ": " . $currentDb . " (OK)\n";
                    }
                }
                echo "</pre>";
                
            } else {
                echo "<p class='error'>❌ Could not find master database</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error checking businesses table</p>";
            echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        }
        
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<div class='box'>";
        echo "<h2 class='error'>❌ Database Connection Failed</h2>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "</div>";
    }
    ?>
    
    <div class='box'>
        <h2>🔗 Quick Links</h2>
        <p><a href="test-database-simple.php" style="color: #60a5fa;">⚡ Simple Database Test</a></p>
        <p><a href="modules/owner/dashboard-2028.php" style="color: #60a5fa;">📊 Owner Dashboard</a></p>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #16213e; border-radius: 8px;">
        <h3 class="info">📝 Summary</h3>
        <p>This script shows ALL databases you have access to and compares them with the businesses table.</p>
        <p>Use the suggested SQL commands to fix database names in the businesses table.</p>
    </div>
</body>
</html>
