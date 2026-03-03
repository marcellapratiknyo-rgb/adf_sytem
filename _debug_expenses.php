<?php
define('ACTIVE_BUSINESS_ID', 'cqc');
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/Database.php';

$db = new Database();

// Check petty cash account ID
$masterDb = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$stmtPettyId = $masterDb->prepare('SELECT id FROM cash_accounts WHERE business_id = 7 AND account_type = "cash" LIMIT 1');
$stmtPettyId->execute();
$pettyCashAccountId = (int)$stmtPettyId->fetchColumn();
echo "Petty Cash Account ID: $pettyCashAccountId\n";

$stmtBankId = $masterDb->prepare('SELECT id FROM cash_accounts WHERE business_id = 7 AND account_type = "bank" LIMIT 1');
$stmtBankId->execute();
$bankAccountId = (int)$stmtBankId->fetchColumn();
echo "Bank Account ID: $bankAccountId\n";

// Query expenses from petty cash
$expenses = $db->fetchAll(
    'SELECT cb.description, cb.amount, cb.transaction_date, cb.cash_account_id 
     FROM cash_book cb 
     WHERE cb.transaction_type = "expense" AND cb.cash_account_id = ? 
     ORDER BY cb.transaction_date DESC, cb.id DESC 
     LIMIT 5', 
    [$pettyCashAccountId]
);
echo "\nPetty Cash Expenses (" . count($expenses) . "):\n";
print_r($expenses);

// Query expenses from bank
$bankExpenses = $db->fetchAll(
    'SELECT cb.description, cb.amount, cb.transaction_date, cb.cash_account_id 
     FROM cash_book cb 
     WHERE cb.transaction_type = "expense" AND cb.cash_account_id = ? 
     ORDER BY cb.transaction_date DESC, cb.id DESC 
     LIMIT 5', 
    [$bankAccountId]
);
echo "\nBank Expenses (" . count($bankExpenses) . "):\n";
print_r($bankExpenses);
