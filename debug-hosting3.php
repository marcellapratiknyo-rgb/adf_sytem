<?php
/**
 * HOSTING DEBUG - Step by step error detection
 * Upload to https://adfsystem.online/debug-hosting3.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Debug Hosting - Step by Step</h2>";
echo "<pre>";

// Step 1: PHP version
echo "✅ PHP Version: " . phpversion() . "\n";
echo "✅ Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n\n";

// Step 2: Check if config exists
echo "=== CONFIG FILES ===\n";
$configFile = __DIR__ . '/config/config.php';
echo "config/config.php exists: " . (file_exists($configFile) ? 'YES' : 'NO') . "\n";

$dbFile = __DIR__ . '/config/database.php';
echo "config/database.php exists: " . (file_exists($dbFile) ? 'YES' : 'NO') . "\n";

$authFile = __DIR__ . '/includes/auth.php';
echo "includes/auth.php exists: " . (file_exists($authFile) ? 'YES' : 'NO') . "\n";

$funcFile = __DIR__ . '/includes/functions.php';
echo "includes/functions.php exists: " . (file_exists($funcFile) ? 'YES' : 'NO') . "\n";

$trialFile = __DIR__ . '/includes/trial_check.php';
echo "includes/trial_check.php exists: " . (file_exists($trialFile) ? 'YES' : 'NO') . "\n";

$headerFile = __DIR__ . '/includes/header.php';
echo "includes/header.php exists: " . (file_exists($headerFile) ? 'YES' : 'NO') . "\n";

$footerFile = __DIR__ . '/includes/footer.php';
echo "includes/footer.php exists: " . (file_exists($footerFile) ? 'YES' : 'NO') . "\n\n";

// Step 3: Try loading config
echo "=== LOADING CONFIG ===\n";
try {
    define('APP_ACCESS', true);
    require_once 'config/config.php';
    echo "✅ config.php loaded\n";
    echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
    echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
    echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
    echo "ACTIVE_BUSINESS_ID: " . (defined('ACTIVE_BUSINESS_ID') ? ACTIVE_BUSINESS_ID : 'NOT DEFINED') . "\n";
    echo "BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'NOT DEFINED') . "\n";
    echo "BUSINESS_NAME: " . (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'NOT DEFINED') . "\n";
    echo "BUSINESS_ICON: " . (defined('BUSINESS_ICON') ? BUSINESS_ICON : 'NOT DEFINED') . "\n";
} catch (Throwable $e) {
    echo "❌ ERROR loading config: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

// Step 4: Try loading database
echo "\n=== LOADING DATABASE ===\n";
try {
    require_once 'config/database.php';
    echo "✅ database.php loaded\n";
    
    $db = Database::getInstance();
    echo "✅ Database instance created\n";
    
    $conn = $db->getConnection();
    echo "✅ Connection established\n";
} catch (Throwable $e) {
    echo "❌ ERROR loading database: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

// Step 5: Try loading auth
echo "\n=== LOADING AUTH ===\n";
try {
    require_once 'includes/auth.php';
    echo "✅ auth.php loaded\n";
} catch (Throwable $e) {
    echo "❌ ERROR loading auth: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

// Step 6: Try loading functions
echo "\n=== LOADING FUNCTIONS ===\n";
try {
    require_once 'includes/functions.php';
    echo "✅ functions.php loaded\n";
} catch (Throwable $e) {
    echo "❌ ERROR loading functions: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

// Step 7: Try loading trial_check
echo "\n=== LOADING TRIAL CHECK ===\n";
try {
    require_once 'includes/trial_check.php';
    echo "✅ trial_check.php loaded\n";
} catch (Throwable $e) {
    echo "❌ ERROR loading trial_check: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

// Step 8: Check business config
echo "\n=== BUSINESS CONFIG ===\n";
try {
    $bizConfigFile = __DIR__ . '/config/businesses/' . ACTIVE_BUSINESS_ID . '.php';
    echo "Business config file: config/businesses/" . ACTIVE_BUSINESS_ID . ".php\n";
    echo "Exists: " . (file_exists($bizConfigFile) ? 'YES' : 'NO') . "\n";
    
    if (file_exists($bizConfigFile)) {
        $businessConfig = require $bizConfigFile;
        echo "✅ Business config loaded\n";
    }
} catch (Throwable $e) {
    echo "❌ ERROR loading business config: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

// Step 9: Check cash_book table structure
echo "\n=== CASH_BOOK TABLE STRUCTURE ===\n";
try {
    $cols = $db->getConnection()->query("SHOW COLUMNS FROM cash_book")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")" . ($col['Null'] === 'YES' ? ' NULL' : ' NOT NULL') . "\n";
    }
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

// Step 10: Check if key tables exist
echo "\n=== TABLE CHECK ===\n";
$tables = ['cash_book', 'divisions', 'categories', 'users', 'settings'];
foreach ($tables as $table) {
    try {
        $result = $db->getConnection()->query("SELECT COUNT(*) as cnt FROM $table")->fetch(PDO::FETCH_ASSOC);
        echo "  $table: " . $result['cnt'] . " rows\n";
    } catch (Throwable $e) {
        echo "  $table: ❌ " . $e->getMessage() . "\n";
    }
}

// Step 11: Try the specific queries from index.php
echo "\n=== TESTING INDEX.PHP QUERIES ===\n";

// Test 1: cash_account_id column check
echo "\n[Test 1] SHOW COLUMNS cash_account_id:\n";
try {
    $colCheck = $db->getConnection()->query("SHOW COLUMNS FROM cash_book LIKE 'cash_account_id'");
    $hasCashAccountIdCol = $colCheck && $colCheck->rowCount() > 0;
    echo "  has cash_account_id: " . ($hasCashAccountIdCol ? 'YES' : 'NO') . "\n";
} catch (Throwable $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

// Test 2: transaction_time column check
echo "\n[Test 2] transaction_time column:\n";
try {
    $db->getConnection()->query("SELECT transaction_time FROM cash_book LIMIT 1");
    echo "  has transaction_time: YES\n";
} catch (Throwable $e) {
    echo "  has transaction_time: NO (" . $e->getMessage() . ")\n";
}

// Test 3: Today income
echo "\n[Test 3] Today income query:\n";
try {
    $today = date('Y-m-d');
    $result = $db->fetchAll(
        "SELECT COALESCE(SUM(amount), 0) as total FROM cash_book WHERE transaction_type = 'income' AND transaction_date = :date",
        ['date' => $today]
    );
    echo "  ✅ Result: " . ($result[0]['total'] ?? 0) . "\n";
} catch (Throwable $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

// Test 4: Master DB connection
echo "\n[Test 4] Master DB connection:\n";
try {
    $masterDb = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $masterDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "  ✅ Master DB connected\n";
    
    // Check cash_accounts table
    $result = $masterDb->query("SELECT COUNT(*) as cnt FROM cash_accounts")->fetch(PDO::FETCH_ASSOC);
    echo "  cash_accounts rows: " . $result['cnt'] . "\n";
} catch (Throwable $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

// Test 5: Divisions LEFT JOIN
echo "\n[Test 5] Division LEFT JOIN query:\n";
try {
    $thisMonth = date('Y-m');
    $result = $db->fetchAll(
        "SELECT d.division_name, d.division_code,
            COALESCE(SUM(CASE WHEN cb.transaction_type = 'income' THEN cb.amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN cb.transaction_type = 'expense' THEN cb.amount ELSE 0 END), 0) as expense
        FROM divisions d
        LEFT JOIN cash_book cb ON d.id = cb.division_id 
            AND DATE_FORMAT(cb.transaction_date, '%Y-%m') = :month
        WHERE d.is_active = 1
        GROUP BY d.id, d.division_name, d.division_code
        ORDER BY income DESC
        LIMIT 5",
        ['month' => $thisMonth]
    );
    echo "  ✅ Got " . count($result) . " divisions\n";
} catch (Throwable $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

// Test 6: Recent transactions (the problematic one)
echo "\n[Test 6] Recent transactions query:\n";
try {
    $result = $db->fetchAll(
        "SELECT cb.*, d.division_name, c.category_name, u.full_name as created_by_name
        FROM cash_book cb
        LEFT JOIN divisions d ON cb.division_id = d.id
        LEFT JOIN categories c ON cb.category_id = c.id
        LEFT JOIN users u ON cb.created_by = u.id
        ORDER BY cb.transaction_date DESC, cb.id DESC
        LIMIT 10"
    );
    echo "  ✅ Got " . count($result) . " transactions\n";
} catch (Throwable $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

// Test 6b: Same query but with transaction_time (what workspace version uses)
echo "\n[Test 6b] Recent transactions with transaction_time ORDER BY:\n";
try {
    $result = $db->fetchAll(
        "SELECT cb.*, d.division_name, c.category_name, u.full_name as created_by_name
        FROM cash_book cb
        LEFT JOIN divisions d ON cb.division_id = d.id
        LEFT JOIN categories c ON cb.category_id = c.id
        LEFT JOIN users u ON cb.created_by = u.id
        ORDER BY cb.transaction_date DESC, cb.transaction_time DESC
        LIMIT 10"
    );
    echo "  ✅ Got " . count($result) . " transactions (transaction_time works)\n";
} catch (Throwable $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

// Test 7: Chart data query
echo "\n[Test 7] Daily chart data:\n";
try {
    $thisMonth = date('Y-m');
    $result = $db->fetchAll(
        "SELECT DATE(transaction_date) as date,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
        FROM cash_book
        WHERE DATE_FORMAT(transaction_date, '%Y-%m') = :month
        GROUP BY DATE(transaction_date)
        ORDER BY date ASC",
        ['month' => $thisMonth]
    );
    echo "  ✅ Got " . count($result) . " data points\n";
} catch (Throwable $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

// Step 12: Check which index.php version is currently uploaded
echo "\n=== INDEX.PHP VERSION CHECK ===\n";
$indexContent = file_get_contents(__DIR__ . '/index.php');
if (strpos($indexContent, 'hasCashAccountIdCol') !== false) {
    echo "  ✅ Contains cash_account_id check (FIXED version)\n";
} else {
    echo "  ❌ MISSING cash_account_id check (OLD version - this is your problem!)\n";
}

if (strpos($indexContent, "SHOW COLUMNS FROM cash_book LIKE 'cash_account_id'") !== false) {
    echo "  ✅ Contains SHOW COLUMNS check\n";
} else {
    echo "  ❌ MISSING SHOW COLUMNS check\n";
}

if (strpos($indexContent, 'cb.transaction_time DESC') !== false) {
    echo "  ⚠️ Contains transaction_time in ORDER BY (may fail if column missing)\n";
} else {
    echo "  ✅ No transaction_time in ORDER BY\n";
}

if (strpos($indexContent, "COALESCE(d.division_name, 'Unknown')") !== false) {
    echo "  ✅ Has COALESCE wrappers for LEFT JOIN\n";
} else {
    echo "  ⚠️ Missing COALESCE wrappers\n";
}

// Check file size and last modified
echo "\n  index.php size: " . filesize(__DIR__ . '/index.php') . " bytes\n";
echo "  index.php modified: " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/index.php')) . "\n";

// Step 13: Check PHP error log
echo "\n=== PHP ERROR LOG ===\n";
$errorLog = ini_get('error_log');
echo "Error log location: " . ($errorLog ?: 'default') . "\n";

if ($errorLog && file_exists($errorLog)) {
    $lines = file($errorLog);
    $lastLines = array_slice($lines, -20);
    echo "Last 20 lines:\n";
    foreach ($lastLines as $line) {
        echo "  " . trim($line) . "\n";
    }
} else {
    echo "Cannot read error log file directly.\n";
    
    // Try common hosting paths
    $possibleLogs = [
        '/home/' . get_current_user() . '/logs/error.log',
        '/home/' . get_current_user() . '/public_html/error_log',
        __DIR__ . '/error_log',
        __DIR__ . '/../error_log',
    ];
    
    foreach ($possibleLogs as $logPath) {
        if (file_exists($logPath)) {
            echo "Found log at: $logPath\n";
            $lines = file($logPath);
            $lastLines = array_slice($lines, -20);
            foreach ($lastLines as $line) {
                echo "  " . trim($line) . "\n";
            }
            break;
        }
    }
}

echo "\n=== DONE ===\n";
echo "</pre>";
?>
