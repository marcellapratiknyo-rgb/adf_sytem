<?php
/**
 * API: Owner Branches - Simple Version
 * Return current business only (no multi-tenant)
 */

// Use same session name as main app
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Auth check - try session role first, fallback to logged_in user
$role = $_SESSION['role'] ?? null;
if (!$role && isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    try {
        $authDb = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
        $authDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $roleStmt = $authDb->prepare("SELECT r.role_code FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $roleStmt->execute([$_SESSION['user_id'] ?? 0]);
        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
        if ($roleRow) {
            $role = $roleRow['role_code'];
            $_SESSION['role'] = $role;
        }
    } catch (Exception $e) {}
}

if (!$role || !in_array($role, ['owner', 'admin', 'manager', 'developer'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Query all businesses from master database
try {
    $masterDbName = defined('MASTER_DB_NAME') ? MASTER_DB_NAME : DB_NAME;
    $masterPdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $masterDbName, DB_USER, DB_PASS);
    $masterPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $bizStmt = $masterPdo->query("SELECT id, business_code, business_name, business_type, database_name FROM businesses WHERE is_active = 1 ORDER BY business_name");
    $businesses = $bizStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $branches = [];
    foreach ($businesses as $biz) {
        $branches[] = [
            'id' => (int)$biz['id'],
            'business_code' => $biz['business_code'],
            'branch_name' => $biz['business_name'],
            'business_name' => $biz['business_name'],
            'business_type' => $biz['business_type'],
            'database_name' => $biz['database_name']
        ];
    }
    
    // Fallback if no businesses in table
    if (empty($branches)) {
        $businessName = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'My Business';
        $branches[] = [
            'id' => 1,
            'business_code' => 'default',
            'branch_name' => $businessName,
            'business_name' => $businessName,
            'business_type' => 'hotel',
            'database_name' => DB_NAME
        ];
    }
    
    echo json_encode([
        'success' => true,
        'branches' => $branches,
        'count' => count($branches)
    ]);
} catch (Exception $e) {
    // Fallback to single business
    $businessName = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'My Business';
    echo json_encode([
        'success' => true,
        'branches' => [
            [
                'id' => 1,
                'business_code' => 'default',
                'branch_name' => $businessName,
                'business_name' => $businessName,
                'business_type' => 'hotel',
                'database_name' => DB_NAME
            ]
        ],
        'count' => 1,
        'error' => $e->getMessage()
    ]);
}
?>
