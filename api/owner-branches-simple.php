<?php
/**
 * API: Owner Branches - Simple Version
 * Return current business only (no multi-tenant)
 */

// Use same session name as main app
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Simple auth check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['owner', 'admin', 'manager', 'developer'])) {
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
