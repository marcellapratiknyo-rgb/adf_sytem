<?php
/**
 * CQC Projects Database Helper
 * Auto-creates database AND tables if not exist
 */

function getCQCDatabaseConnection() {
    $isLocalhost = (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false);
    
    $dbHost = 'localhost';
    $dbUser = $isLocalhost ? 'root' : 'adfb2574_adfsystem';
    $dbPass = $isLocalhost ? '' : '@Nnoc2025';
    $dbName = $isLocalhost ? 'adf_cqc' : 'adfb2574_cqc';
    
    try {
        // Create database if not exists
        $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Connect to database
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        // Auto-create tables if not exist
        $check = $pdo->query("SHOW TABLES LIKE 'cqc_projects'");
        if ($check->rowCount() === 0) {
            autoCreateCQCTables($pdo);
        }
        
        return $pdo;
    } catch (Exception $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

function autoCreateCQCTables($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cqc_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_name VARCHAR(200) NOT NULL,
            project_code VARCHAR(50) UNIQUE NOT NULL,
            description LONGTEXT,
            location VARCHAR(300),
            client_name VARCHAR(150),
            client_phone VARCHAR(20),
            client_email VARCHAR(100),
            solar_capacity_kwp DECIMAL(8,2) COMMENT 'Kapasitas dalam KWp',
            panel_count INT,
            panel_type VARCHAR(100),
            inverter_type VARCHAR(100),
            budget_idr DECIMAL(15,2),
            spent_idr DECIMAL(15,2) DEFAULT 0,
            status ENUM('planning','procurement','installation','testing','completed','on_hold') DEFAULT 'planning',
            progress_percentage INT DEFAULT 0,
            start_date DATE,
            end_date DATE,
            estimated_completion DATE,
            actual_completion DATE,
            project_manager_id INT,
            lead_installer_id INT,
            created_by INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_code (project_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cqc_expense_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            category_icon VARCHAR(10) DEFAULT '📦',
            description VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cqc_project_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            category_id INT,
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            expense_date DATE NOT NULL,
            receipt_number VARCHAR(50),
            notes TEXT,
            created_by INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project (project_id),
            FOREIGN KEY (project_id) REFERENCES cqc_projects(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES cqc_expense_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert default categories
    $pdo->exec("
        INSERT IGNORE INTO cqc_expense_categories (id, category_name, category_icon, description) VALUES
        (1, 'Panel Surya', '☀️', 'Pembelian panel surya'),
        (2, 'Inverter', '🔌', 'Pembelian inverter'),
        (3, 'Kabel & Konektor', '🔗', 'Kabel, konektor, MC4'),
        (4, 'Tenaga Kerja', '👷', 'Upah pekerja instalasi'),
        (5, 'Struktur Mounting', '🏗️', 'Rangka dan mounting bracket'),
        (6, 'Perizinan', '📋', 'Biaya izin dan sertifikasi'),
        (7, 'Testing & Commissioning', '🔧', 'Biaya pengujian sistem'),
        (8, 'Logistik & Transportasi', '🚛', 'Ongkos kirim dan transportasi'),
        (9, 'Konsultasi & Desain', '📐', 'Biaya konsultan dan desain'),
        (10, 'Lain-lain', '📦', 'Pengeluaran lainnya')
    ");
}

function isLocalhost() {
    return (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false);
}

function getCQCDatabaseName() {
    return isLocalhost() ? 'adf_cqc' : 'adfb2574_cqc';
}
