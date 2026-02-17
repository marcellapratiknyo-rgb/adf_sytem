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

// Return single business based on current system
$businessName = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'My Business';
$businessType = 'hotel'; // default

echo json_encode([
    'success' => true,
    'branches' => [
        [
            'id' => 1,
            'branch_name' => $businessName,
            'business_name' => $businessName,
            'business_type' => $businessType,
            'database_name' => DB_NAME
        ]
    ],
    'count' => 1
]);
?>
