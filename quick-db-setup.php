<?php
/**
 * Quick Setup: Run business template on existing empty database
 * Upload this to hosting via cPanel File Manager if Git deploy is stuck
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';

$targetDb = $_GET['db'] ?? 'adfb2574_demo';
$targetDb = preg_replace('/[^a-zA-Z0-9_]/', '', $targetDb);
$run = isset($_GET['run']);
$results = [];

// Check database exists
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $check = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$targetDb}'");
    if ($check->rowCount() === 0) {
        die("Database '{$targetDb}' does not exist!");
    }
    
    // Connect to target database
    $dbPdo = new PDO("mysql:host=" . DB_HOST . ";dbname={$targetDb}", DB_USER, DB_PASS);
    $dbPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check existing tables
    $tables = $dbPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $results[] = "Database '{$targetDb}' has " . count($tables) . " tables.";
    
    if ($run) {
        $templatePath = __DIR__ . '/database/business_template.sql';
        if (!file_exists($templatePath)) {
            die("Template file not found: database/business_template.sql");
        }
        
        $sql = file_get_contents($templatePath);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $executed = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            if (!empty($statement) && strpos($statement, '--') !== 0) {
                try {
                    $dbPdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    $errors[] = $e->getMessage() . " | SQL: " . substr($statement, 0, 80);
                }
            }
        }
        
        $results[] = "Executed {$executed} SQL statements.";
        if (!empty($errors)) {
            $results[] = "Errors (" . count($errors) . "): " . implode(" | ", array_slice($errors, 0, 5));
        }
        
        // Re-check tables
        $tablesAfter = $dbPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $results[] = "Tables now: " . implode(', ', $tablesAfter);
        $results[] = "DONE! Database '{$targetDb}' is ready.";
    } else {
        if (count($tables) > 0) {
            $results[] = "Existing tables: " . implode(', ', $tables);
        }
        $results[] = "<a href='?db={$targetDb}&run=1' style='display:inline-block;background:#16a34a;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold;margin-top:10px;'>▶ RUN TEMPLATE on {$targetDb}</a>";
    }
    
} catch (PDOException $e) {
    $results[] = "ERROR: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html><head><title>Quick DB Setup</title>
<style>body{font-family:sans-serif;padding:2rem;max-width:700px;margin:auto;background:#f1f5f9;}
.r{background:#fff;padding:1rem;margin:0.5rem 0;border-radius:8px;border:1px solid #e2e8f0;}</style>
</head><body>
<h2>Quick Database Setup: <?= htmlspecialchars($targetDb) ?></h2>
<?php foreach($results as $r): ?>
<div class="r"><?= $r ?></div>
<?php endforeach; ?>
<hr style="margin:1rem 0">
<p><small>Other databases: 
<a href="?db=adfb2574_demo">adfb2574_demo</a> | 
<a href="?db=adfb2574_benscafe">adfb2574_benscafe</a> |
<a href="?db=adfb2574_narayana_hotel">adfb2574_narayana_hotel</a>
</small></p>
</body></html>
