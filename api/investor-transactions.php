<?php
/**
 * API: Get Investor Transactions
 */

defined('APP_ACCESS') or define('APP_ACCESS', true);

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $investor_id = intval($_GET['investor_id'] ?? 0);
    
    if ($investor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid investor ID', 'transactions' => []]);
        exit;
    }
    
    // Fetch investor transactions
    $stmt = $db->prepare("
        SELECT 
            id,
            investor_id,
            amount,
            type,
            transaction_type,
            description,
            created_at,
            updated_at
        FROM investor_transactions
        WHERE investor_id = :investor_id
        ORDER BY created_at DESC
        LIMIT 100
    ");
    
    $stmt->execute([':investor_id' => $investor_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'transactions' => []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'transactions' => []
    ]);
}
?>
