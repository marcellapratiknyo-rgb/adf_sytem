<?php
/**
 * CQC Pay Termin Invoice
 * Process payment for termin invoice
 */
define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

require_once '../cqc-projects/db-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index-cqc.php');
    exit;
}

try {
    $pdo = getCQCDatabaseConnection();
    $db = Database::getInstance(); // Master DB for cashbook
    
    $invoice_id = (int)$_POST['invoice_id'];
    $payment_method = $_POST['payment_method'] ?? 'transfer';
    $notes = trim($_POST['notes'] ?? '');
    $currentUser = $auth->getCurrentUser();
    
    // Get invoice info
    $stmt = $pdo->prepare("
        SELECT ti.*, p.project_code, p.project_name, p.client_name 
        FROM cqc_termin_invoices ti
        LEFT JOIN cqc_projects p ON ti.project_id = p.id
        WHERE ti.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception("Faktur tidak ditemukan.");
    }
    
    if ($invoice['payment_status'] === 'paid') {
        throw new Exception("Faktur sudah lunas.");
    }
    
    // Update invoice status
    $stmt = $pdo->prepare("
        UPDATE cqc_termin_invoices 
        SET payment_status = 'paid',
            paid_amount = total_amount,
            payment_date = CURDATE(),
            payment_method = :payment_method,
            payment_notes = :notes,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        'payment_method' => $payment_method,
        'notes' => $notes,
        'id' => $invoice_id
    ]);
    
    // Record in cashbook (master database)
    $description = "[CQC_PROJECT:{$invoice['project_id']}] [{$invoice['project_code']}] Pembayaran {$invoice['invoice_number']} - Termin {$invoice['termin_number']} ({$invoice['percentage']}%) - {$invoice['client_name']}";
    
    // Get CQC business ID
    $businessId = 7; // CQC business ID
    
    // Find default cash account
    $cashAccountId = $db->fetchOne("
        SELECT id FROM cash_accounts 
        WHERE business_id = ? AND is_active = 1 
        ORDER BY is_default DESC LIMIT 1
    ", [$businessId]);
    
    if ($cashAccountId) {
        // Insert cashbook entry
        $db->execute("
            INSERT INTO cash_book (
                business_id, cash_account_id, transaction_date, transaction_time,
                transaction_type, amount, description, payment_method, source_type,
                created_by, created_at
            ) VALUES (?, ?, CURDATE(), CURTIME(), 'income', ?, ?, ?, 'cqc_termin', ?, NOW())
        ", [
            $businessId,
            $cashAccountId,
            $invoice['total_amount'],
            $description,
            $payment_method,
            $currentUser['id']
        ]);
        
        // Update cash account balance
        $db->execute("
            UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?
        ", [$invoice['total_amount'], $cashAccountId]);
    }
    
    $_SESSION['success'] = "Pembayaran {$invoice['invoice_number']} berhasil dicatat. Total: Rp " . number_format($invoice['total_amount'], 0, ',', '.');
    header('Location: index-cqc.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: index-cqc.php');
    exit;
}
