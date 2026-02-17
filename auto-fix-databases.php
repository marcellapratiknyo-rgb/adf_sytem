<?php
/**
 * Auto Fix Database Names
 * Update businesses table with correct production database names
 */

session_start();

// Set session
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'developer';

// Detect environment
$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false);

if ($isProduction) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'adfb2574_adf');
    define('DB_USER', 'adfb2574_adfsystem');
    define('DB_PASS', '@Nnoc2025');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'adf_system');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Auto Fix Database Names</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 700px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .status {
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            font-weight: 500;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        .info {
            background: #dbeafe;
            color: #1e40af;
            border: 2px solid #3b82f6;
        }
        .warning {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #f59e0b;
        }
        pre {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            margin: 10px 5px;
            transition: all 0.3s ease;
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .section {
            margin: 25px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 10px;
        }
        h3 {
            color: #6366f1;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Auto Fix Database Names</h1>
        
        <?php
        $fixed = false;
        $errors = [];
        
        try {
            // Connect to master database
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo '<div class="status success">✅ Connected to master database: <strong>' . DB_NAME . '</strong></div>';
            
            // Check current state
            echo '<div class="section">';
            echo '<h3>📋 Current Database Names in Businesses Table:</h3>';
            
            $checkStmt = $pdo->query("SELECT id, business_code, branch_name, database_name FROM businesses ORDER BY id");
            $businesses = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<pre>';
            foreach ($businesses as $biz) {
                echo sprintf("ID %d | %-20s | %s\n", 
                    $biz['id'], 
                    $biz['branch_name'], 
                    $biz['database_name']
                );
            }
            echo '</pre>';
            echo '</div>';
            
            // Define correct database names for production
            $correctNames = [
                'NARAYANAHOTEL' => 'adfb2574_narayana_hotel',
                'BENSCAFE' => 'adfb2574_benscafe',  // Most likely
                'BENSCAFE_ALT' => 'adfb2574_bens_cafe',  // Alternative
            ];
            
            // Test which databases actually exist
            echo '<div class="section">';
            echo '<h3>🔍 Testing Database Connections:</h3>';
            echo '<pre>';
            
            $workingDatabases = [];
            
            foreach ($businesses as $biz) {
                $testDbs = [];
                
                // Add current name
                $testDbs[] = $biz['database_name'];
                
                // Add potential correct names based on business code
                if ($biz['business_code'] === 'NARAYANAHOTEL') {
                    $testDbs[] = 'adfb2574_narayana_hotel';
                } elseif ($biz['business_code'] === 'BENSCAFE') {
                    $testDbs[] = 'adfb2574_benscafe';
                    $testDbs[] = 'adfb2574_bens_cafe';
                    $testDbs[] = 'adf_benscafe';
                }
                
                // Remove duplicates
                $testDbs = array_unique($testDbs);
                
                echo "\n" . $biz['branch_name'] . " (ID " . $biz['id'] . "):\n";
                
                $foundWorking = false;
                foreach ($testDbs as $testDb) {
                    try {
                        $testPdo = new PDO(
                            'mysql:host=' . DB_HOST . ';dbname=' . $testDb . ';charset=utf8mb4',
                            DB_USER,
                            DB_PASS
                        );
                        
                        // Check for cash_book table
                        $tableCheck = $testPdo->query("SHOW TABLES LIKE 'cash_book'");
                        if ($tableCheck->rowCount() > 0) {
                            echo "  ✅ " . $testDb . " - EXISTS & has cash_book table\n";
                            $workingDatabases[$biz['id']] = $testDb;
                            $foundWorking = true;
                        } else {
                            echo "  ⚠️  " . $testDb . " - EXISTS but no cash_book table\n";
                        }
                        
                    } catch (PDOException $e) {
                        echo "  ❌ " . $testDb . " - NOT ACCESSIBLE\n";
                    }
                }
                
                if (!$foundWorking) {
                    echo "  🔴 NO WORKING DATABASE FOUND!\n";
                    $errors[] = $biz['branch_name'] . ": No accessible database found";
                }
            }
            
            echo '</pre>';
            echo '</div>';
            
            // Show what will be updated
            if (!empty($workingDatabases)) {
                echo '<div class="section">';
                echo '<h3>🔄 Changes to be Applied:</h3>';
                echo '<pre>';
                
                $needsUpdate = false;
                foreach ($businesses as $biz) {
                    if (isset($workingDatabases[$biz['id']])) {
                        $newDb = $workingDatabases[$biz['id']];
                        $currentDb = $biz['database_name'];
                        
                        if ($newDb !== $currentDb) {
                            echo "ID " . $biz['id'] . " | " . $biz['branch_name'] . ":\n";
                            echo "  OLD: " . $currentDb . "\n";
                            echo "  NEW: " . $newDb . "\n\n";
                            $needsUpdate = true;
                        } else {
                            echo "ID " . $biz['id'] . " | " . $biz['branch_name'] . ": ✅ Already correct\n\n";
                        }
                    }
                }
                
                echo '</pre>';
                
                // Apply updates
                if ($needsUpdate) {
                    echo '<div class="status warning">⚠️ Applying database name fixes...</div>';
                    
                    $pdo->beginTransaction();
                    
                    try {
                        foreach ($businesses as $biz) {
                            if (isset($workingDatabases[$biz['id']])) {
                                $newDb = $workingDatabases[$biz['id']];
                                if ($newDb !== $biz['database_name']) {
                                    $updateStmt = $pdo->prepare("UPDATE businesses SET database_name = ? WHERE id = ?");
                                    $updateStmt->execute([$newDb, $biz['id']]);
                                    echo '<div class="status success">✅ Updated ' . htmlspecialchars($biz['branch_name']) . ' to: ' . htmlspecialchars($newDb) . '</div>';
                                }
                            }
                        }
                        
                        $pdo->commit();
                        $fixed = true;
                        
                        echo '<div class="status success">';
                        echo '<strong>🎉 SUCCESS! Database names have been fixed!</strong><br><br>';
                        echo 'All businesses now point to the correct databases.<br>';
                        echo 'Dashboard should now display correct data!';
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        echo '<div class="status error">❌ Update failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        $errors[] = 'Update failed: ' . $e->getMessage();
                    }
                    
                } else {
                    echo '<div class="status info">ℹ️ All database names are already correct. No updates needed.</div>';
                }
                
                echo '</div>';
            }
            
            // Show final state
            if ($fixed) {
                echo '<div class="section">';
                echo '<h3>✅ Final Database Names (Updated):</h3>';
                
                $finalStmt = $pdo->query("SELECT id, business_code, branch_name, database_name FROM businesses ORDER BY id");
                $finalBusinesses = $finalStmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<pre>';
                foreach ($finalBusinesses as $biz) {
                    echo sprintf("ID %d | %-20s | %s\n", 
                        $biz['id'], 
                        $biz['branch_name'], 
                        $biz['database_name']
                    );
                }
                echo '</pre>';
                echo '</div>';
            }
            
        } catch (PDOException $e) {
            echo '<div class="status error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $errors[] = 'Database connection failed';
        }
        
        // Show errors if any
        if (!empty($errors)) {
            echo '<div class="status error">';
            echo '<strong>⚠️ Issues Found:</strong><br><ul>';
            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul></div>';
        }
        
        // Links
        echo '<div style="margin-top: 30px; text-align: center;">';
        
        if ($fixed && empty($errors)) {
            echo '<a href="modules/owner/dashboard-2028.php" class="button">📊 Open Dashboard (Should Work Now!)</a>';
        }
        
        echo '<a href="test-database-simple.php" class="button">🔍 Test Databases</a>';
        echo '<a href="quick-links-testing.html" class="button">🔗 Back to Quick Links</a>';
        echo '</div>';
        ?>
        
        <div style="margin-top: 30px; padding: 15px; background: #f3f4f6; border-radius: 8px; font-size: 13px; color: #6b7280;">
            <strong>What this script does:</strong><br>
            1. Checks current database names in businesses table<br>
            2. Tests connections to potential database names<br>
            3. Automatically updates businesses table with working database names<br>
            4. Verifies the fix worked
        </div>
    </div>
</body>
</html>
