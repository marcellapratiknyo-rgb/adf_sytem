<?php
/**
 * API: Owner Statistics - Simple Version
 * Direct query to current database (no multi-tenant)
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Simple auth check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['owner', 'admin', 'manager', 'developer'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Session: ' . json_encode($_SESSION)]);
    exit;
}

try {
    $db = Database::getInstance();
    
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('-1 month'));
    
    // TODAY's transactions
    $todayIncomeResult = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total FROM cash_book 
         WHERE transaction_type = 'income' AND transaction_date = ?",
        [$today]
    );
    $todayIncome = $todayIncomeResult['total'] ?? 0;
    
    $todayExpenseResult = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total FROM cash_book 
         WHERE transaction_type = 'expense' AND transaction_date = ?",
        [$today]
    );
    $todayExpense = $todayExpenseResult['total'] ?? 0;
    
    // THIS MONTH's transactions
    $monthIncomeResult = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total FROM cash_book 
         WHERE transaction_type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?",
        [$thisMonth]
    );
    $monthIncome = $monthIncomeResult['total'] ?? 0;
    
    $monthExpenseResult = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total FROM cash_book 
         WHERE transaction_type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?",
        [$thisMonth]
    );
    $monthExpense = $monthExpenseResult['total'] ?? 0;
    
    // LAST MONTH's transactions (for comparison)
    $lastMonthIncomeResult = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total FROM cash_book 
         WHERE transaction_type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?",
        [$lastMonth]
    );
    $lastMonthIncome = $lastMonthIncomeResult['total'] ?? 0;
    
    $lastMonthExpenseResult = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total FROM cash_book 
         WHERE transaction_type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?",
        [$lastMonth]
    );
    $lastMonthExpense = $lastMonthExpenseResult['total'] ?? 0;
    
    // CASH ACCOUNTS BALANCES
    $cashAccounts = $db->fetchAll(
        "SELECT id, account_name, account_type, current_balance 
         FROM cash_accounts WHERE is_active = 1 ORDER BY account_type, account_name"
    );
    
    $pettyCash = 0;
    $bankBalance = 0;
    $ownerCapital = 0;
    
    foreach ($cashAccounts as $acc) {
        if ($acc['account_type'] === 'cash') {
            $pettyCash += (float)$acc['current_balance'];
        } elseif ($acc['account_type'] === 'bank') {
            $bankBalance += (float)$acc['current_balance'];
        } elseif ($acc['account_type'] === 'owner_capital') {
            $ownerCapital += (float)$acc['current_balance'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'todayIncome' => (float)$todayIncome,
        'todayExpense' => (float)$todayExpense,
        'monthIncome' => (float)$monthIncome,
        'monthExpense' => (float)$monthExpense,
        'pettyCash' => $pettyCash,
        'bankBalance' => $bankBalance,
        'ownerCapital' => $ownerCapital,
        'cashAccounts' => $cashAccounts,
        'lastMonth' => [
            'income' => (float)$lastMonthIncome,
            'expense' => (float)$lastMonthExpense
        ],
        'debug' => [
            'today' => $today,
            'thisMonth' => $thisMonth,
            'lastMonth' => $lastMonth,
            'database' => DB_NAME
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
