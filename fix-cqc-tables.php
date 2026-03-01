<?php
// Quick fix to add users table to CQC database
$isLocalhost = true;
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$cqcDbName = 'adf_cqc';

$pdo = new PDO("mysql:host={$dbHost};dbname={$cqcDbName};charset=utf8mb4", $dbUser, $dbPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if users table exists
$hasUsers = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
if (!$hasUsers) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            role VARCHAR(20) DEFAULT 'staff',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("INSERT INTO users (id, username, full_name, role) VALUES (1, 'admin', 'Administrator', 'admin')");
    echo "Created users table with admin user\n";
} else {
    echo "Users table already exists\n";
}

// Verify cash_book data
echo "\nCash book data:\n";
$rows = $pdo->query("SELECT * FROM cash_book")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

// Check all tables
echo "\nAll tables in adf_cqc:\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
