<?php
/**
 * Quick Hosting Verification
 * Run this at: https://adfsystem.online/verify-hosting.php
 */

require_once 'config/config.php';

header('Content-Type: application/json');

$result = [
    'status' => 'checking',
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => [],
    'config' => [],
    'database' => [],
    'files' => [],
    'modules' => []
];

// Environment
$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
                strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

$result['environment'] = [
    'is_production' => $isProduction,
    'http_host' => $_SERVER['HTTP_HOST'] ?? 'N/A',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'php_version' => PHP_VERSION,
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'
];

// Configuration
$result['config'] = [
    'base_url' => BASE_URL,
    'base_path' => BASE_PATH,
    'db_host' => DB_HOST,
    'db_name' => DB_NAME,
    'db_user' => DB_USER
];

// Database Check
try {
    require_once 'config/database.php';
    $db = Database::getInstance()->getConnection();
    
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $result['database'] = [
        'connected' => true,
        'tables_count' => count($tables),
        'has_investors' => in_array('investors', $tables),
        'has_investor_transactions' => in_array('investor_transactions', $tables),
        'has_projects' => in_array('projects', $tables)
    ];
    
} catch (Exception $e) {
    $result['database'] = [
        'connected' => false,
        'error' => $e->getMessage()
    ];
}

// Critical Files Check
$criticalFiles = [
    'modules/investor/index.php',
    'modules/investor/deposits-history.php',
    'modules/investor/deposits-script.js',
    'modules/investor/deposits-style.css',
    'api/investor-transactions.php',
    'api/investor-get.php',
    'api/investor-deposit.php'
];

$result['files'] = [];
foreach ($criticalFiles as $file) {
    $result['files'][$file] = file_exists($file);
}

// Module URL Test
$result['modules'] = [
    'investor_url' => BASE_URL . '/modules/investor/',
    'api_url' => BASE_URL . '/api/investor-transactions.php'
];

// Final Status
$allFilesExist = !in_array(false, $result['files'], true);
$dbConnected = $result['database']['connected'] ?? false;

if ($allFilesExist && $dbConnected && $isProduction) {
    $result['status'] = 'OK';
    $result['message'] = 'Hosting configuration is correct!';
} else {
    $result['status'] = 'ERROR';
    $result['message'] = 'Some issues detected. Check the details below.';
    
    if (!$allFilesExist) {
        $result['missing_files'] = array_keys(array_filter($result['files'], function($v) { return $v === false; }));
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
