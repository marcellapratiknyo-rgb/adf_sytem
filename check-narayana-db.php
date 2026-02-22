<?php
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    echo "=== DATABASES DENGAN 'narayana' ===\n";
    $dbs = $pdo->query("SHOW DATABASES LIKE '%narayana%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach($dbs as $db) {
        echo "- $db\n";
    }
    
    echo "\n=== TABLES DI adf_narayana_hotel ===\n";
    $pdo->exec("USE adf_narayana_hotel");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table) {
        echo "- $table\n";
    }
    
    // Check if adf_web_narayana exists
    echo "\n=== CEK adf_web_narayana ===\n";
    $result = $pdo->query("SHOW DATABASES LIKE 'adf_web_narayana'")->fetchAll();
    if (count($result) > 0) {
        echo "✓ Database adf_web_narayana EXISTS\n";
        $pdo->exec("USE adf_web_narayana");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables: " . count($tables) . "\n";
        foreach($tables as $table) {
            echo "  - $table\n";
        }
    } else {
        echo "✗ Database adf_web_narayana TIDAK ADA\n";
    }
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
