<?php
/**
 * FIX: Cash Accounts Setup for ALL Businesses
 * 
 * Fixes:
 * 1. Add missing columns to cash_accounts table (is_active, description, is_default_account)
 * 2. Create default cash accounts for ALL businesses that don't have them
 * 
 * Usage: /fix-cash-accounts-all.php (view mode)
 *        /fix-cash-accounts-all.php?run=1 (execute fixes)
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');
$run = isset($_GET['run']);

echo "<h2>🔧 Fix Cash Accounts for ALL Businesses</h2>";
echo "<p>Mode: <strong>" . ($run ? '🟢 EXECUTE' : '🔍 VIEW ONLY') . "</strong></p>";
if (!$run) echo "<p><a href='?run=1' style='color:white;background:#059669;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:bold;'>▶ Klik untuk Run Fix</a></p>";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✅ Connected to master DB: " . DB_NAME . "</p>";

    // ============================================
    // STEP 1: Fix cash_accounts table structure
    // ============================================
    echo "<h3>Step 1: Fix Table Structure</h3>";
    
    // Get current columns
    $cols = $pdo->query("SHOW COLUMNS FROM cash_accounts")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Current columns: <code>" . implode(', ', $cols) . "</code></p>";
    
    $missingCols = [];
    
    if (!in_array('is_active', $cols)) $missingCols['is_active'] = "TINYINT(1) NOT NULL DEFAULT 1";
    if (!in_array('description', $cols)) $missingCols['description'] = "TEXT NULL";
    if (!in_array('is_default_account', $cols)) $missingCols['is_default_account'] = "TINYINT(1) NOT NULL DEFAULT 0";
    if (!in_array('current_balance', $cols)) $missingCols['current_balance'] = "DECIMAL(15,2) NOT NULL DEFAULT 0.00";
    if (!in_array('created_at', $cols)) $missingCols['created_at'] = "TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if (!in_array('updated_at', $cols)) $missingCols['updated_at'] = "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    
    if (empty($missingCols)) {
        echo "<p style='color:green'>✅ All columns present</p>";
    } else {
        echo "<p style='color:orange'>⚠️ Missing columns: " . implode(', ', array_keys($missingCols)) . "</p>";
        if ($run) {
            foreach ($missingCols as $col => $def) {
                try {
                    $pdo->exec("ALTER TABLE cash_accounts ADD COLUMN `$col` $def");
                    echo "<p style='color:green'>  ✅ Added column: $col</p>";
                } catch (Exception $e) {
                    echo "<p style='color:red'>  ❌ Error adding $col: " . $e->getMessage() . "</p>";
                }
            }
        }
    }

    // ============================================
    // STEP 2: Show current data
    // ============================================
    echo "<h3>Step 2: Current Cash Accounts Data</h3>";
    
    // Use safe query (only columns that exist)
    $safeSelect = "id, business_id, account_name, account_type";
    if (in_array('current_balance', $cols) || ($run && isset($missingCols['current_balance']))) $safeSelect .= ", current_balance";
    if (in_array('is_active', $cols) || ($run && isset($missingCols['is_active']))) $safeSelect .= ", is_active";
    
    $rows = $pdo->query("SELECT $safeSelect FROM cash_accounts ORDER BY business_id, id")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        echo "<p style='color:red'>❌ Table is EMPTY - no cash accounts at all!</p>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr style='background:#f3f4f6'><th>ID</th><th>Biz ID</th><th>Name</th><th>Type</th></tr>";
        foreach ($rows as $r) {
            echo "<tr><td>{$r['id']}</td><td>{$r['business_id']}</td><td>{$r['account_name']}</td><td>{$r['account_type']}</td></tr>";
        }
        echo "</table>";
    }

    // ============================================
    // STEP 3: Check and create missing accounts
    // ============================================
    echo "<h3>Step 3: Ensure All Businesses Have Cash Accounts</h3>";
    
    $businesses = $pdo->query("SELECT id, business_name, business_code FROM businesses WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    // Default accounts every business should have
    $defaultAccounts = [
        ['name' => 'Petty Cash', 'type' => 'cash', 'desc' => 'Uang cash dari tamu / operasional', 'default' => 1],
        ['name' => 'Bank', 'type' => 'bank', 'desc' => 'Rekening bank utama bisnis', 'default' => 0],
        ['name' => 'Kas Modal Owner', 'type' => 'owner_capital', 'desc' => 'Modal operasional dari owner', 'default' => 0],
    ];
    
    $totalCreated = 0;
    
    foreach ($businesses as $biz) {
        $bizId = $biz['id'];
        $bizName = $biz['business_name'];
        
        // Check existing accounts for this business
        $stmt = $pdo->prepare("SELECT account_type, account_name FROM cash_accounts WHERE business_id = ?");
        $stmt->execute([$bizId]);
        $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingTypes = array_column($existing, 'account_type');
        
        $missing = [];
        foreach ($defaultAccounts as $acc) {
            if (!in_array($acc['type'], $existingTypes)) {
                $missing[] = $acc;
            }
        }
        
        if (empty($missing)) {
            echo "<p style='color:green'>✅ <strong>$bizName</strong> (ID=$bizId): Has all 3 accounts (" . implode(', ', $existingTypes) . ")</p>";
        } else {
            $missingNames = array_map(function($m) { return $m['name']; }, $missing);
            echo "<p style='color:orange'>⚠️ <strong>$bizName</strong> (ID=$bizId): Missing accounts: " . implode(', ', $missingNames) . "</p>";
            
            if ($run) {
                // Determine which columns exist for INSERT
                $insertCols = "business_id, account_name, account_type";
                $insertPlaceholders = "?, ?, ?";
                $hasBalance = in_array('current_balance', $cols) || isset($missingCols['current_balance']);
                $hasDefault = in_array('is_default_account', $cols) || isset($missingCols['is_default_account']);
                $hasDesc = in_array('description', $cols) || isset($missingCols['description']);
                $hasActive = in_array('is_active', $cols) || isset($missingCols['is_active']);
                
                if ($hasBalance) { $insertCols .= ", current_balance"; $insertPlaceholders .= ", 0"; }
                if ($hasDefault) { $insertCols .= ", is_default_account"; $insertPlaceholders .= ", ?"; }
                if ($hasDesc) { $insertCols .= ", description"; $insertPlaceholders .= ", ?"; }
                if ($hasActive) { $insertCols .= ", is_active"; $insertPlaceholders .= ", 1"; }
                
                foreach ($missing as $acc) {
                    $params = [$bizId, $acc['name'], $acc['type']];
                    if ($hasDefault) $params[] = $acc['default'];
                    if ($hasDesc) $params[] = $acc['desc'];
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO cash_accounts ($insertCols) VALUES ($insertPlaceholders)");
                        $stmt->execute($params);
                        $newId = $pdo->lastInsertId();
                        echo "<p style='color:green'>  ✅ Created: {$acc['name']} ({$acc['type']}) → ID=$newId</p>";
                        $totalCreated++;
                    } catch (Exception $e) {
                        echo "<p style='color:red'>  ❌ Error creating {$acc['name']}: " . $e->getMessage() . "</p>";
                    }
                }
            }
        }
    }
    
    if ($run && $totalCreated > 0) {
        echo "<hr><p style='color:green;font-size:1.2em'>✅ <strong>Done! Created $totalCreated new cash accounts.</strong></p>";
        echo "<p>Sekarang coba tambah transaksi lagi di cashbook.</p>";
    }
    
    // ============================================
    // STEP 4: Verify final state
    // ============================================
    if ($run) {
        echo "<h3>Step 4: Final State</h3>";
        $finalRows = $pdo->query("SELECT id, business_id, account_name, account_type FROM cash_accounts ORDER BY business_id, id")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr style='background:#d1fae5'><th>ID</th><th>Biz ID</th><th>Name</th><th>Type</th></tr>";
        foreach ($finalRows as $r) {
            echo "<tr><td>{$r['id']}</td><td>{$r['business_id']}</td><td>{$r['account_name']}</td><td>{$r['account_type']}</td></tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
