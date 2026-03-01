<?php
require_once __DIR__ . '/modules/cqc-projects/db-helper.php';
$pdo = getCQCDatabaseConnection();

echo "<h2>CQC Database Debug</h2>";

// Check tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<h3>Tables:</h3><pre>" . print_r($tables, true) . "</pre>";

// Check cash_book
if (in_array('cash_book', $tables)) {
    echo "<h3>Cash Book Data:</h3>";
    $rows = $pdo->query("SELECT * FROM cash_book")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Count: " . count($rows) . "</p>";
    echo "<pre>" . print_r($rows, true) . "</pre>";
    
    // Check divisions
    echo "<h3>Divisions:</h3>";
    $divs = $pdo->query("SELECT * FROM divisions")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($divs, true) . "</pre>";
    
    // Check categories
    echo "<h3>Categories:</h3>";
    $cats = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($cats, true) . "</pre>";
} else {
    echo "<p style='color:red'>cash_book table not found!</p>";
}
