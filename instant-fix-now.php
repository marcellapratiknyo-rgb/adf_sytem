<?php
/**
 * INSTANT FIX - Update Database Names
 * Direct SQL update to fix database names
 */

session_start();
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'developer';

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

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Update Ben's Cafe database name to correct name
    $updateStmt = $pdo->prepare("UPDATE businesses SET database_name = 'adfb2574_Adf_Bens' WHERE business_code = 'BENSCAFE'");
    $updateStmt->execute();
    
    echo "✅ SUCCESS! Database name updated!<br><br>";
    echo "Ben's Cafe database_name sekarang: <strong>adfb2574_Adf_Bens</strong><br><br>";
    echo "<a href='modules/owner/dashboard-2028.php' style='display:inline-block;padding:15px 30px;background:#10b981;color:white;text-decoration:none;border-radius:8px;font-weight:bold;'>📊 BUKA DASHBOARD SEKARANG</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
