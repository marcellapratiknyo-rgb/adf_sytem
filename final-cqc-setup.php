<?php
/**
 * Final CQC Database Setup
 * Run this on hosting to create adfb2574_cqc database
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h2>🎯 Final CQC Database Setup</h2>\n";

// Detect environment
$isHosting = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
              strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

echo "<p><strong>Environment:</strong> " . ($isHosting ? "🌐 HOSTING" : "💻 LOCAL") . "</p>\n";
echo "<p><strong>Host:</strong> " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'CLI') . "</p>\n";
echo "<hr>\n";

$dbHost = 'localhost';
$dbUser = $isHosting ? 'adfb2574_adfsystem' : 'root';
$dbPass = $isHosting ? '@Nnoc2025' : '';
$dbName = $isHosting ? 'adfb2574_cqc' : 'adf_cqc';

try {
    // Step 1: Create database
    echo "<h3>1️⃣ Connecting as user: <code>$dbUser</code></h3>\n";
    $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected\n\n";
    
    // Create database
    echo "<h3>2️⃣ Creating database: <code>$dbName</code></h3>\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database ready\n\n";
    
    // Step 2: Create essential tables
    echo "<h3>3️⃣ Creating essential tables...</h3>\n";
    
    $bizPdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $bizPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Users table
    $bizPdo->exec("
    CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `business_id` int(11) DEFAULT NULL,
        `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'user',
        `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: users table\n";
    
    // Settings table
    $bizPdo->exec("
    CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `key` varchar(255) NOT NULL,
        `value` longtext,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `key_unique` (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: settings table\n";
    
    // Transactions table
    $bizPdo->exec("
    CREATE TABLE IF NOT EXISTS `transactions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `type` varchar(50) NOT NULL,
        `amount` decimal(15,2) NOT NULL,
        `description` text,
        `date` date NOT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: transactions table\n";
    
    // Cash accounts table
    $bizPdo->exec("
    CREATE TABLE IF NOT EXISTS `cash_accounts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `account_name` varchar(255) NOT NULL,
        `account_type` varchar(100),
        `opening_balance` decimal(15,2) DEFAULT 0,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: cash_accounts table\n";
    
    echo "\n<h3>✅ Setup Complete!</h3>\n";
    echo "<p>Database <strong><code>$dbName</code></strong> is now ready to use.</p>\n";
    echo "<p style='background: #e8f5e9; padding: 15px; border-left: 4px solid green; margin-top: 20px;'>\n";
    echo "<strong>🎉 What's been done:</strong><br>\n";
    echo "1. ✅ CQC business added to config/businesses.php<br>\n";
    echo "2. ✅ Database name mapping setup in config/database.php<br>\n";
    echo "3. ✅ API fixed to properly store base database names<br>\n";
    echo "4. ✅ Essential schema created in $dbName<br>\n";
    echo "</p>\n";
    
    echo "<p style='background: #fff3e0; padding: 15px; border-left: 4px solid orange; margin-top: 20px;'>\n";
    echo "<strong>⚠️ Next Steps:</strong><br>\n";
    echo "1. Try creating/accessing the CQC business from the dashboard<br>\n";
    echo "2. If it works, the database connection error should be resolved!<br>\n";
    echo "3. If you see other table-not-found errors, we can add more tables as needed<br>\n";
    echo "</p>\n";
    
    echo "\n<p><a href='javascript:history.back()' style='color: #1976d2;'>← Back</a></p>\n";
    
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "\n";
    echo "<p>Check:<br>\n";
    echo "1. User: <code>$dbUser</code><br>\n";
    echo "2. Host: <code>$dbHost</code><br>\n";
    echo "3. Database: <code>$dbName</code>\n";
    echo "</p>\n";
    echo "<p><a href='javascript:history.back()' style='color: #d32f2f;'>← Back</a></p>\n";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; max-width: 800px; margin: 0 auto; }
h2, h3 { color: #333; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
a { text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
