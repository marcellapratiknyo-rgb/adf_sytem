<?php
/**
 * BILLS MODULE - Auto Migration
 * Automatically creates bill_templates and bill_records tables if they don't exist.
 * Include this file in every bills module page AFTER Database::getInstance().
 */
if (!defined('APP_ACCESS')) exit;

function billsAutoMigrate($db) {
    static $done = false;
    if ($done) return;
    $done = true;
    
    try {
        $pdo = $db->getConnection();
        
        // Check if bill_templates exists
        $check = $pdo->query("SHOW TABLES LIKE 'bill_templates'");
        if ($check->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS bill_templates (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    bill_name VARCHAR(150) NOT NULL,
                    bill_category ENUM('electricity','tax','wifi','vehicle','po','receivable','other') NOT NULL DEFAULT 'other',
                    vendor_name VARCHAR(150) DEFAULT NULL,
                    vendor_contact VARCHAR(100) DEFAULT NULL,
                    account_number VARCHAR(100) DEFAULT NULL,
                    default_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                    is_fixed_amount TINYINT(1) NOT NULL DEFAULT 0,
                    recurrence ENUM('monthly','quarterly','yearly','one-time') NOT NULL DEFAULT 'monthly',
                    due_day INT(2) NOT NULL DEFAULT 1,
                    reminder_days INT(3) NOT NULL DEFAULT 3,
                    division_id INT(11) DEFAULT NULL,
                    category_id INT(11) DEFAULT NULL,
                    payment_method ENUM('cash','transfer','qr','debit','other') DEFAULT 'transfer',
                    notes TEXT DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_by INT(11) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_category (bill_category),
                    INDEX idx_active (is_active),
                    INDEX idx_due_day (due_day)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Check if bill_records exists
        $check2 = $pdo->query("SHOW TABLES LIKE 'bill_records'");
        if ($check2->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS bill_records (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    template_id INT(11) NOT NULL,
                    bill_period VARCHAR(7) NOT NULL,
                    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                    due_date DATE NOT NULL,
                    status ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
                    paid_date DATE DEFAULT NULL,
                    paid_amount DECIMAL(15,2) DEFAULT NULL,
                    payment_method ENUM('cash','transfer','qr','debit','other') DEFAULT NULL,
                    cashbook_id INT(11) DEFAULT NULL,
                    proof_file VARCHAR(255) DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    paid_by INT(11) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_template (template_id),
                    INDEX idx_period (bill_period),
                    INDEX idx_status (status),
                    INDEX idx_due_date (due_date),
                    INDEX idx_cashbook (cashbook_id),
                    UNIQUE KEY unique_bill_period (template_id, bill_period),
                    FOREIGN KEY (template_id) REFERENCES bill_templates(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    } catch (Exception $e) {
        error_log("Bills auto-migrate error: " . $e->getMessage());
    }
}

// Run migration
billsAutoMigrate($db);
