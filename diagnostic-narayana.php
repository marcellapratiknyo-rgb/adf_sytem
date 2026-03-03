<?php
/**
 * DIAGNOSTIC TOOL - Narayana Hotel Cash Book
 * Akses: https://adfsystem.online/diagnostic-narayana.php
 * Copy SEMUA output dan kirim ke developer
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "============================================\n";
echo "DIAGNOSTIC TOOL - " . date('Y-m-d H:i:s') . "\n";
echo "============================================\n\n";

// 1. Database info
echo "=== 1. DATABASE CONFIG ===\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'N/A') . "\n";
echo "MASTER_DB_NAME: " . (defined('MASTER_DB_NAME') ? MASTER_DB_NAME : 'N/A') . "\n";
echo "PHP Version: " . phpversion() . "\n\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get current database
    $currentDb = $conn->query("SELECT DATABASE() as db")->fetch(PDO::FETCH_ASSOC);
    echo "Current Database: " . $currentDb['db'] . "\n\n";

    // 2. Tables list
    echo "=== 2. TABLES IN DATABASE ===\n";
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $count = $conn->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        echo "  {$t}: {$count} rows\n";
    }
    echo "\n";

    // 3. Cash book entries - ALL of them
    echo "=== 3. ALL CASH_BOOK ENTRIES (income) ===\n";
    $entries = $conn->query("
        SELECT cb.id, cb.transaction_date, cb.transaction_type, cb.amount, 
               cb.payment_method, cb.description, cb.created_at,
               cb.division_id, cb.category_id
        FROM cash_book cb 
        WHERE cb.transaction_type = 'income'
        ORDER BY cb.id DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $totalToday = 0;
    $today = date('Y-m-d');
    foreach ($entries as $e) {
        $marker = ($e['transaction_date'] === $today) ? ' *** TODAY ***' : '';
        if ($e['transaction_date'] === $today) $totalToday += $e['amount'];
        echo "  ID={$e['id']} | Date={$e['transaction_date']} | Amt=" . number_format($e['amount'],0) . 
             " | Method={$e['payment_method']} | Div={$e['division_id']} | Cat={$e['category_id']}" .
             " | Desc=" . substr($e['description'] ?? '', 0, 80) . $marker . "\n";
    }
    echo "\n  >>> TOTAL TODAY INCOME: " . number_format($totalToday, 0) . "\n\n";

    // 4. Cash book entries TODAY specifically
    echo "=== 4. CASH_BOOK ENTRIES TODAY ({$today}) ===\n";
    $todayEntries = $conn->query("
        SELECT id, transaction_date, transaction_type, amount, payment_method, description, created_at
        FROM cash_book 
        WHERE transaction_date = '{$today}'
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($todayEntries) === 0) {
        echo "  (KOSONG - tidak ada entry hari ini)\n";
    }
    foreach ($todayEntries as $e) {
        echo "  ID={$e['id']} | Type={$e['transaction_type']} | Amt=" . number_format($e['amount'],0) . 
             " | Method={$e['payment_method']} | Created={$e['created_at']}\n";
        echo "    Desc: " . ($e['description'] ?? 'NULL') . "\n";
    }
    echo "\n";

    // 5. Revenue query - EXACT same query as dashboard
    echo "=== 5. REVENUE QUERY (same as dashboard) ===\n";
    $revResult = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM cash_book
        WHERE transaction_type = 'income'
        AND transaction_date = '{$today}'
        AND (description LIKE '%Reservasi%' OR description LIKE '%Reservation%' OR description LIKE '%BK-%')
    ")->fetch(PDO::FETCH_ASSOC);
    echo "  Revenue Today (dashboard query): " . number_format($revResult['total'], 0) . "\n\n";

    // 6. ALL income today (without filter)
    echo "=== 6. ALL INCOME TODAY (no filter) ===\n";
    $allIncome = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM cash_book
        WHERE transaction_type = 'income'
        AND transaction_date = '{$today}'
    ")->fetch(PDO::FETCH_ASSOC);
    echo "  All Income Today: " . number_format($allIncome['total'], 0) . "\n\n";

    // 7. Bookings
    echo "=== 7. BOOKINGS (recent) ===\n";
    $bookings = $conn->query("
        SELECT b.id, b.booking_code, b.booking_source, b.status, b.final_price, b.paid_amount,
               b.check_in_date, b.check_out_date, g.guest_name, r.room_number
        FROM bookings b
        LEFT JOIN guests g ON b.guest_id = g.id
        LEFT JOIN rooms r ON b.room_id = r.id
        ORDER BY b.id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bookings as $b) {
        echo "  ID={$b['id']} | Code={$b['booking_code']} | Source={$b['booking_source']} | Status={$b['status']}" .
             " | Price=" . number_format($b['final_price'],0) . " | Paid=" . number_format($b['paid_amount'],0) .
             " | Guest={$b['guest_name']} | Room={$b['room_number']}" .
             " | In={$b['check_in_date']} | Out={$b['check_out_date']}\n";
    }
    echo "\n";

    // 8. Booking payments
    echo "=== 8. BOOKING_PAYMENTS (recent) ===\n";
    // Check if synced_to_cashbook column exists
    $hasSyncCol = false;
    try {
        $syncColChk = $conn->query("SHOW COLUMNS FROM booking_payments LIKE 'synced_to_cashbook'");
        $hasSyncCol = $syncColChk && $syncColChk->rowCount() > 0;
    } catch (\Throwable $e) {}
    
    echo "  Has synced_to_cashbook column: " . ($hasSyncCol ? 'YES' : 'NO') . "\n";
    
    $syncSelect = $hasSyncCol ? ", bp.synced_to_cashbook, bp.cashbook_id" : "";
    $payments = $conn->query("
        SELECT bp.id, bp.booking_id, bp.amount, bp.payment_method, bp.payment_date {$syncSelect},
               b.booking_code, b.booking_source
        FROM booking_payments bp
        JOIN bookings b ON bp.booking_id = b.id
        ORDER BY bp.id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payments as $p) {
        $syncInfo = $hasSyncCol ? " | Synced={$p['synced_to_cashbook']} | CbID={$p['cashbook_id']}" : "";
        echo "  PayID={$p['id']} | BookingID={$p['booking_id']} | Code={$p['booking_code']} | Source={$p['booking_source']}" .
             " | Amt=" . number_format($p['amount'],0) . " | Method={$p['payment_method']}" .
             " | Date={$p['payment_date']}{$syncInfo}\n";
    }
    echo "\n";

    // 9. Duplicate analysis
    echo "=== 9. DUPLICATE ANALYSIS ===\n";
    $dups = $conn->query("
        SELECT 
            SUBSTRING_INDEX(SUBSTRING_INDEX(description, 'BK-', -1), ' ', 1) as booking_code,
            COUNT(*) as cnt,
            GROUP_CONCAT(id ORDER BY id) as ids,
            GROUP_CONCAT(amount ORDER BY id) as amounts,
            GROUP_CONCAT(transaction_date ORDER BY id) as dates
        FROM cash_book
        WHERE description LIKE '%BK-%'
        AND transaction_type = 'income'
        GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(description, 'BK-', -1), ' ', 1)
        HAVING cnt > 1
        ORDER BY cnt DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($dups) === 0) {
        echo "  No duplicates found (good!)\n";
    } else {
        echo "  FOUND " . count($dups) . " booking codes with duplicates:\n";
        foreach ($dups as $d) {
            echo "  BK-{$d['booking_code']}: {$d['cnt']}x | IDs=[{$d['ids']}] | Amounts=[{$d['amounts']}] | Dates=[{$d['dates']}]\n";
        }
    }
    echo "\n";

    // 10. Divisions
    echo "=== 10. DIVISIONS ===\n";
    $divs = $conn->query("SELECT * FROM divisions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($divs as $d) {
        echo "  ID={$d['id']} | Name={$d['division_name']}\n";
    }
    echo "\n";

    // 11. Categories
    echo "=== 11. CATEGORIES ===\n";
    $cats = $conn->query("SELECT * FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cats as $c) {
        echo "  ID={$c['id']} | Name={$c['category_name']} | Type={$c['category_type']}\n";
    }
    echo "\n";

    // 12. Cash book columns
    echo "=== 12. CASH_BOOK TABLE STRUCTURE ===\n";
    $cols = $conn->query("SHOW COLUMNS FROM cash_book")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  {$c['Field']} | {$c['Type']} | Null={$c['Null']} | Default={$c['Default']}\n";
    }
    echo "\n";

    // 13. Settings (OTA fees)
    echo "=== 13. OTA FEE SETTINGS ===\n";
    try {
        // Try master DB
        $masterDbName = defined('MASTER_DB_NAME') ? MASTER_DB_NAME : 'adf_system';
        $masterDb = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . $masterDbName . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $settings = $masterDb->query("SELECT * FROM settings WHERE setting_key LIKE '%ota%' ORDER BY setting_key")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($settings as $s) {
            echo "  {$s['setting_key']} = {$s['setting_value']}\n";
        }
        
        // 14. Cash accounts
        echo "\n=== 14. CASH ACCOUNTS (master) ===\n";
        $accounts = $masterDb->query("SELECT * FROM cash_accounts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($accounts as $a) {
            echo "  ID={$a['id']} | Name={$a['account_name']} | Type={$a['account_type']} | Balance=" . number_format($a['current_balance'],0) . " | Active={$a['is_active']} | BizID={$a['business_id']}\n";
        }
    } catch (\Throwable $e) {
        echo "  Master DB error: " . $e->getMessage() . "\n";
        
        // Try settings in current DB
        try {
            $settings = $conn->query("SELECT * FROM settings WHERE setting_key LIKE '%ota%' ORDER BY setting_key")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($settings as $s) {
                echo "  (local) {$s['setting_key']} = {$s['setting_value']}\n";
            }
        } catch (\Throwable $e2) {
            echo "  Local settings error: " . $e2->getMessage() . "\n";
        }
    }
    echo "\n";

    // 15. Git version check
    echo "=== 15. FILE VERSION CHECK ===\n";
    $dashboardFile = __DIR__ . '/modules/frontdesk/dashboard.php';
    if (file_exists($dashboardFile)) {
        $content = file_get_contents($dashboardFile);
        
        // Check if auto-sync still exists
        if (strpos($content, 'AUTO-SYNC BOOKING PAYMENTS TO CASHBOOK') !== false) {
            echo "  ❌ dashboard.php: OLD VERSION - still has AUTO-SYNC code\n";
        } elseif (strpos($content, 'AUTO-CLEANUP DUPLICATE CASH_BOOK ENTRIES') !== false) {
            echo "  ✅ dashboard.php: NEW VERSION - has cleanup only (no auto-sync)\n";
        } else {
            echo "  ⚠️ dashboard.php: UNKNOWN VERSION\n";
        }
        
        // Check for specific markers
        if (strpos($content, 'CRITICAL FIX') !== false || strpos($content, 'Dashboard only cleans up duplicates') !== false) {
            echo "  ✅ dashboard.php: Latest fix applied\n";
        } else {
            echo "  ❌ dashboard.php: Latest fix NOT applied\n";
        }
        
        echo "  File size: " . filesize($dashboardFile) . " bytes\n";
        echo "  Last modified: " . date('Y-m-d H:i:s', filemtime($dashboardFile)) . "\n";
    }
    
    $helperFile = __DIR__ . '/includes/CashbookHelper.php';
    if (file_exists($helperFile)) {
        $helperContent = file_get_contents($helperFile);
        if (strpos($helperContent, 'DEDUP CHECK') !== false) {
            echo "  ✅ CashbookHelper.php: Has DEDUP check\n";
        } else {
            echo "  ❌ CashbookHelper.php: NO dedup check\n";
        }
        echo "  File size: " . filesize($helperFile) . " bytes\n";
        echo "  Last modified: " . date('Y-m-d H:i:s', filemtime($helperFile)) . "\n";
    }
    echo "\n";

    // 16. Last git commit info
    echo "=== 16. GIT INFO ===\n";
    $gitLog = shell_exec('cd ' . escapeshellarg(__DIR__) . ' && git log --oneline -5 2>&1');
    echo $gitLog ?? "  (git not available)\n";
    echo "\n";

    echo "============================================\n";
    echo "END OF DIAGNOSTIC\n";
    echo "============================================\n";

} catch (\Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
