<?php
/**
 * Simple Database Test
 * Check if database has data
 */

session_start();

// Set session for testing
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'developer';
$_SESSION['user_id'] = 1;
$_SESSION['business_id'] = 2;

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
    <title>Simple Database Test</title>
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
        h2 { color: #a855f7; margin-top: 0; }
        pre { 
            background: #0f172a; 
            padding: 15px; 
            border-radius: 5px; 
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 Simple Database Test</h1>
    
    <?php
    echo "<div class='box'>";
    echo "<h2>Database Config</h2>";
    echo "<pre>";
    echo "Host: " . DB_HOST . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n";
    echo "Environment: " . ($isProduction ? 'PRODUCTION (Hosting)' : 'LOCAL') . "\n";
    echo "</pre>";
    echo "</div>";
    
    try {
        // Connect to main database
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<div class='box'>";
        echo "<h2>✅ Database Connected</h2>";
        
        // Check businesses table
        $bizStmt = $pdo->query("SELECT * FROM businesses WHERE status = 'active' ORDER BY id");
        $businesses = $bizStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Businesses Found: " . count($businesses) . "</h3>";
        echo "<pre>";
        print_r($businesses);
        echo "</pre>";
        echo "</div>";
        
        // Check each business database
        foreach ($businesses as $biz) {
            echo "<div class='box'>";
            echo "<h2>Business: " . htmlspecialchars($biz['branch_name']) . "</h2>";
            echo "<p class='info'>Database: " . htmlspecialchars($biz['database_name']) . "</p>";
            
            try {
                // Connect to business database
                $bizPdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . $biz['database_name'] . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS
                );
                $bizPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if cash_book table exists
                $tableCheck = $bizPdo->query("SHOW TABLES LIKE 'cash_book'");
                if ($tableCheck->rowCount() > 0) {
                    // Count transactions
                    $countStmt = $bizPdo->query("SELECT COUNT(*) as total FROM cash_book");
                    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo "<p class='success'>✅ cash_book table exists - Total: " . $count['total'] . " transactions</p>";
                    
                    if ($count['total'] > 0) {
                        // Get today's stats
                        $todayStmt = $bizPdo->query("
                            SELECT 
                                COUNT(*) as count,
                                COALESCE(SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END), 0) as income,
                                COALESCE(SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END), 0) as expense
                            FROM cash_book
                            WHERE DATE(transaction_date) = CURDATE()
                        ");
                        $today = $todayStmt->fetch(PDO::FETCH_ASSOC);
                        
                        echo "<p><strong>Today (" . date('Y-m-d') . "):</strong></p>";
                        echo "<pre>";
                        echo "Transactions: " . $today['count'] . "\n";
                        echo "Income: Rp " . number_format($today['income'], 0, ',', '.') . "\n";
                        echo "Expense: Rp " . number_format($today['expense'], 0, ',', '.') . "\n";
                        echo "</pre>";
                        
                        // Get this month's stats
                        $monthStmt = $bizPdo->query("
                            SELECT 
                                COUNT(*) as count,
                                COALESCE(SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END), 0) as income,
                                COALESCE(SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END), 0) as expense
                            FROM cash_book
                            WHERE YEAR(transaction_date) = YEAR(CURDATE())
                            AND MONTH(transaction_date) = MONTH(CURDATE())
                        ");
                        $month = $monthStmt->fetch(PDO::FETCH_ASSOC);
                        
                        echo "<p><strong>This Month (" . date('F Y') . "):</strong></p>";
                        echo "<pre>";
                        echo "Transactions: " . $month['count'] . "\n";
                        echo "Income: Rp " . number_format($month['income'], 0, ',', '.') . "\n";
                        echo "Expense: Rp " . number_format($month['expense'], 0, ',', '.') . "\n";
                        echo "</pre>";
                        
                        // Show recent 3 transactions
                        $recentStmt = $bizPdo->query("
                            SELECT transaction_date, transaction_type, category, amount, description
                            FROM cash_book
                            ORDER BY transaction_date DESC, id DESC
                            LIMIT 3
                        ");
                        $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo "<p><strong>Recent 3 Transactions:</strong></p>";
                        echo "<pre>";
                        print_r($recent);
                        echo "</pre>";
                    } else {
                        echo "<p class='error'>❌ No transactions in database</p>";
                    }
                } else {
                    echo "<p class='error'>❌ cash_book table NOT FOUND</p>";
                }
                
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Cannot connect to business database</p>";
                echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
            }
            
            echo "</div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='box'>";
        echo "<h2 class='error'>❌ Database Connection Failed</h2>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "</div>";
    }
    ?>
    
    <div class='box'>
        <h2>Quick Links</h2>
        <p><a href="modules/owner/dashboard-2028.php" style="color: #60a5fa;">📊 Open Dashboard</a></p>
        <p><a href="api/owner-branches-simple.php" target="_blank" style="color: #60a5fa;">🏢 Test Branches API</a></p>
        <p><a href="api/owner-stats-simple.php" target="_blank" style="color: #60a5fa;">📈 Test Stats API</a></p>
    </div>
</body>
</html>
