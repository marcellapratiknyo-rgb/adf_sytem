<?php
$_SERVER['HTTP_HOST'] = 'localhost';
define('APP_ACCESS', true);
require_once 'config/config.php';
require_once 'config/database.php';

// Force CQC business context
$_SESSION['business_db'] = 'adf_cqc';
$_SESSION['user_id'] = 1;

echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . PHP_EOL;
echo "Session business_db: " . ($_SESSION['business_db'] ?? 'NOT SET') . PHP_EOL;

$bizId = getMasterBusinessId();
echo "getMasterBusinessId: " . $bizId . PHP_EOL;

try {
    $masterDb = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $masterDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmtBiz = $masterDb->prepare("SELECT business_type FROM businesses WHERE id = ?");
    $stmtBiz->execute([$bizId]);
    $bizRow = $stmtBiz->fetch(PDO::FETCH_ASSOC);
    echo "Business type: " . ($bizRow['business_type'] ?? 'NULL') . PHP_EOL;
    $isCQC = ($bizRow && $bizRow['business_type'] === 'contractor');
    echo "Is CQC: " . ($isCQC ? 'YES' : 'NO') . PHP_EOL;
    
    if ($isCQC) {
        // Get Petty Cash balance
        $stmtPetty = $masterDb->prepare("SELECT COALESCE(current_balance, 0) as balance FROM cash_accounts WHERE business_id = ? AND account_type = 'cash' LIMIT 1");
        $stmtPetty->execute([$bizId]);
        $pettyCashAccount = $stmtPetty->fetch(PDO::FETCH_ASSOC);
        echo "Petty Cash balance: " . ($pettyCashAccount['balance'] ?? 'NULL') . PHP_EOL;
        
        // Get transfers
        $db = Database::getInstance();
        $thisMonth = date('Y-m');
        $pettyCashMonth = $db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) as total 
             FROM cash_book 
             WHERE transaction_type = 'income' 
             AND source_type = 'owner_fund'
             AND DATE_FORMAT(transaction_date, '%Y-%m') = ?",
            [$thisMonth]
        );
        echo "Petty Cash transfers this month: " . ($pettyCashMonth['total'] ?? 'NULL') . PHP_EOL;
    }
    
    // Show cash_accounts
    echo "\n=== Cash Accounts ===\n";
    $stmt = $masterDb->prepare("SELECT id, account_name, account_type, current_balance FROM cash_accounts WHERE business_id = ?");
    $stmt->execute([$bizId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Name: {$row['account_name']}, Type: {$row['account_type']}, Balance: {$row['current_balance']}\n";
    }
    
    // Show recent cash_book
    echo "\n=== Recent Cash Book ===\n";
    $all = $db->fetchAll("SELECT id, transaction_date, transaction_type, amount, payment_method, cash_account_id, source_type, description FROM cash_book ORDER BY id DESC LIMIT 10");
    foreach ($all as $row) {
        echo "ID:{$row['id']} | {$row['transaction_date']} | {$row['transaction_type']} | {$row['amount']} | method:{$row['payment_method']} | account:{$row['cash_account_id']} | source:{$row['source_type']} | {$row['description']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
