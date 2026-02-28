<?php
/**
 * Diagnose CQC menu/dashboard issues on hosting
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$isHosting = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false);
if ($isHosting) {
    $dbHost = 'localhost';
    $dbUser = 'adfb2574_adfsystem';
    $dbPass = '@Nnoc2025';
    $systemDb = 'adfb2574_adf';
} else {
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
    $systemDb = 'adf_system';
}

echo "<pre style='font-size:14px; line-height:1.6;'>\n";
echo "=== CQC HOSTING DIAGNOSTIC ===\n";
echo "Environment: " . ($isHosting ? 'HOSTING' : 'LOCAL') . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Check 1: Key file contents (is code deployed?)
echo "=== 1. CODE DEPLOYMENT CHECK ===\n";

$indexFile = __DIR__ . '/index.php';
$indexContent = file_exists($indexFile) ? file_get_contents($indexFile) : '';
$checks = [
    'index.php has $isCQC' => strpos($indexContent, '$isCQC') !== false,
    'index.php has Semua Proyek' => strpos($indexContent, 'Semua Proyek') !== false,
    'index.php has projectPieChart' => strpos($indexContent, 'projectPieChart') !== false,
];

$headerFile = __DIR__ . '/includes/header.php';
$headerContent = file_exists($headerFile) ? file_get_contents($headerFile) : '';
$checks['header uses hasPermission for CQC'] = strpos($headerContent, "hasPermission('cqc-projects')") !== false;
$checks['header uses OLD isModuleEnabled'] = strpos($headerContent, "isModuleEnabled('cqc-projects')") !== false;

$permFile = __DIR__ . '/developer/permissions.php';
$permContent = file_exists($permFile) ? file_get_contents($permFile) : '';
$checks['permissions.php has menu_code fix'] = strpos($permContent, "code_' . \$menu['menu_code']") !== false;

foreach ($checks as $label => $result) {
    echo ($result ? "✅" : "❌") . " $label\n";
}

// Check 2: Config file
echo "\n=== 2. CONFIG FILE ===\n";
$cqcConfigFile = __DIR__ . '/config/businesses/cqc.php';
if (file_exists($cqcConfigFile)) {
    $cqcConfig = require $cqcConfigFile;
    echo "✅ config/businesses/cqc.php exists\n";
    echo "  business_id: " . ($cqcConfig['business_id'] ?? 'N/A') . "\n";
    echo "  enabled_modules: " . implode(', ', $cqcConfig['enabled_modules'] ?? []) . "\n";
} else {
    echo "❌ config/businesses/cqc.php NOT FOUND!\n";
    echo "  Available configs:\n";
    foreach (glob(__DIR__ . '/config/businesses/*.php') as $f) {
        echo "    - " . basename($f) . "\n";
    }
}

// Check 3: Database
echo "\n=== 3. DATABASE ===\n";
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$systemDb", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $biz = $pdo->query("SELECT id, business_code, slug, database_name FROM businesses WHERE business_code = 'CQC'")->fetch(PDO::FETCH_ASSOC);
    if ($biz) {
        echo "✅ CQC business: ID={$biz['id']}, slug=" . ($biz['slug'] ?? 'NULL') . ", db={$biz['database_name']}\n";
    } else {
        echo "❌ CQC business NOT FOUND in DB!\n";
    }
    
    $menu = $pdo->query("SELECT id, menu_code, menu_name FROM menu_items WHERE menu_code = 'cqc-projects'")->fetch(PDO::FETCH_ASSOC);
    echo ($menu ? "✅ cqc-projects menu: ID={$menu['id']}" : "❌ cqc-projects NOT in menu_items!") . "\n";
    
    if ($biz && $menu) {
        $bmc = $pdo->query("SELECT id FROM business_menu_config WHERE business_id = {$biz['id']} AND menu_id = {$menu['id']}")->fetch();
        echo ($bmc ? "✅" : "❌") . " cqc-projects assigned to CQC business\n";
    }
    
    echo "\n=== 4. USER PERMISSIONS (CQC) ===\n";
    $bizId = $biz['id'] ?? 7;
    $users = $pdo->query("
        SELECT u.id, u.username, r.role_code,
               GROUP_CONCAT(ump.menu_code ORDER BY ump.menu_code) as menus
        FROM users u 
        JOIN roles r ON r.id = u.role_id
        JOIN user_business_assignment uba ON uba.user_id = u.id AND uba.business_id = $bizId
        LEFT JOIN user_menu_permissions ump ON ump.user_id = u.id AND ump.business_id = $bizId
        GROUP BY u.id, u.username, r.role_code
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $u) {
        $hasCqcPerm = strpos($u['menus'] ?? '', 'cqc-projects') !== false;
        echo "\n  User: {$u['username']} (ID:{$u['id']}, role:{$u['role_code']})\n";
        echo "  cqc-projects: " . ($hasCqcPerm ? '✅ YES' : '❌ NO') . "\n";
        echo "  Menus: " . ($u['menus'] ?? 'NONE') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ DB Error: " . $e->getMessage() . "\n";
}

// Check 5: Session
echo "\n=== 5. CURRENT SESSION ===\n";
if (session_status() === PHP_SESSION_NONE) session_start();
echo "  active_business_id: " . ($_SESSION['active_business_id'] ?? 'NOT SET') . "\n";
echo "  business_id: " . ($_SESSION['business_id'] ?? 'NOT SET') . "\n";
echo "  username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";
echo "  role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";

// Check 6: File timestamps
echo "\n=== 6. FILE TIMESTAMPS ===\n";
$files = ['index.php', 'includes/header.php', 'developer/permissions.php', 'login.php'];
foreach ($files as $f) {
    $fullPath = __DIR__ . '/' . $f;
    if (file_exists($fullPath)) {
        echo "  $f: " . date('Y-m-d H:i:s', filemtime($fullPath)) . "\n";
    }
}

echo "\n=== DONE ===\n";
echo "</pre>";
