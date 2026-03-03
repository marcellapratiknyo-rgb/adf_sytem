<?php
/**
 * CASH BOOK DIAGNOSTIC & CLEANUP TOOL
 * Untuk cek dan bersihkan duplikat entries di cash_book
 * 
 * Akses via browser: https://yourdomain.com/fix-cashbook-duplicates.php
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die('Please login first');
}

$db = Database::getInstance();
$today = date('Y-m-d');

$action = $_GET['action'] ?? 'view';

echo '<!DOCTYPE html><html><head>';
echo '<title>Cash Book Diagnostic</title>';
echo '<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1, h2 { color: #333; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #4a90d9; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
.highlight { background: #fff3cd !important; }
.danger { background: #f8d7da !important; }
.success { background: #d4edda !important; }
.btn { padding: 10px 20px; margin: 5px; cursor: pointer; border: none; border-radius: 4px; }
.btn-danger { background: #dc3545; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-success { background: #28a745; color: white; }
.summary { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
a { color: #4a90d9; text-decoration: none; }
a:hover { text-decoration: underline; }
</style></head><body>';

echo '<h1>🔍 Cash Book Diagnostic Tool</h1>';
echo '<p>Date: ' . date('Y-m-d H:i:s') . '</p>';

// Navigation
echo '<div style="margin: 20px 0;">';
echo '<a class="btn btn-info" href="?action=view">View Entries</a> ';
echo '<a class="btn btn-info" href="?action=duplicates">Find Duplicates</a> ';
echo '<a class="btn btn-danger" href="?action=cleanup" onclick="return confirm(\'Are you sure you want to delete duplicates?\')">Delete Duplicates</a> ';
echo '</div>';

// === VIEW TODAY'S ENTRIES ===
if ($action === 'view') {
    echo '<h2>📋 Cash Book Entries - Today (' . $today . ')</h2>';
    
    $entries = $db->fetchAll("
        SELECT cb.id, cb.transaction_date, cb.description, cb.amount, cb.payment_method,
               cb.transaction_type, cb.created_at, d.division_name
        FROM cash_book cb
        LEFT JOIN divisions d ON cb.division_id = d.id
        WHERE cb.transaction_date = ?
        ORDER BY cb.id DESC
    ", [$today]);
    
    $totalIncome = 0;
    $totalExpense = 0;
    
    echo '<table><tr><th>ID</th><th>Date</th><th>Division</th><th>Type</th><th>Method</th><th>Amount</th><th>Description</th><th>Created</th></tr>';
    foreach ($entries as $e) {
        if ($e['transaction_type'] === 'income') $totalIncome += $e['amount'];
        else $totalExpense += $e['amount'];
        
        $class = $e['transaction_type'] === 'income' ? 'success' : '';
        echo "<tr class='{$class}'>";
        echo "<td>{$e['id']}</td>";
        echo "<td>{$e['transaction_date']}</td>";
        echo "<td>{$e['division_name']}</td>";
        echo "<td>{$e['transaction_type']}</td>";
        echo "<td>{$e['payment_method']}</td>";
        echo "<td style='text-align:right;'>Rp " . number_format($e['amount'], 0, ',', '.') . "</td>";
        echo "<td>" . htmlspecialchars(substr($e['description'] ?? '', 0, 60)) . "...</td>";
        echo "<td>{$e['created_at']}</td>";
        echo "</tr>";
    }
    echo '</table>';
    
    echo '<div class="summary">';
    echo '<h3>Summary Today</h3>';
    echo '<p><strong>Total Income:</strong> Rp ' . number_format($totalIncome, 0, ',', '.') . '</p>';
    echo '<p><strong>Total Expense:</strong> Rp ' . number_format($totalExpense, 0, ',', '.') . '</p>';
    echo '<p><strong>Net:</strong> Rp ' . number_format($totalIncome - $totalExpense, 0, ',', '.') . '</p>';
    echo '</div>';
    
    // Also show this month
    echo '<h2>📋 This Month Income (Reservasi only)</h2>';
    $monthStart = date('Y-m-01');
    $monthEntries = $db->fetchAll("
        SELECT cb.id, cb.transaction_date, cb.description, cb.amount, cb.payment_method, cb.created_at
        FROM cash_book cb
        WHERE cb.transaction_type = 'income'
        AND cb.transaction_date >= ?
        AND (cb.description LIKE '%Reservasi%' OR cb.description LIKE '%BK-%')
        ORDER BY cb.id DESC
    ", [$monthStart]);
    
    $monthTotal = 0;
    echo '<table><tr><th>ID</th><th>Date</th><th>Method</th><th>Amount</th><th>Description</th><th>Created</th></tr>';
    foreach ($monthEntries as $e) {
        $monthTotal += $e['amount'];
        $class = $e['transaction_date'] === $today ? 'highlight' : '';
        echo "<tr class='{$class}'>";
        echo "<td>{$e['id']}</td>";
        echo "<td>{$e['transaction_date']}</td>";
        echo "<td>{$e['payment_method']}</td>";
        echo "<td style='text-align:right;'>Rp " . number_format($e['amount'], 0, ',', '.') . "</td>";
        echo "<td>" . htmlspecialchars(substr($e['description'] ?? '', 0, 80)) . "</td>";
        echo "<td>{$e['created_at']}</td>";
        echo "</tr>";
    }
    echo '</table>';
    echo '<p><strong>Total This Month:</strong> Rp ' . number_format($monthTotal, 0, ',', '.') . '</p>';
}

// === FIND DUPLICATES ===
if ($action === 'duplicates') {
    echo '<h2>🔎 Finding Duplicates (Same Booking Code)</h2>';
    
    // Find entries with same booking code (BK-XXXXXXXX) that appear multiple times on same day
    $duplicates = $db->fetchAll("
        SELECT 
            SUBSTRING_INDEX(SUBSTRING_INDEX(description, 'BK-', -1), ' ', 1) as booking_code,
            transaction_date,
            COUNT(*) as cnt,
            GROUP_CONCAT(id ORDER BY id) as ids,
            SUM(amount) as total_amount
        FROM cash_book
        WHERE description LIKE '%BK-%'
        AND transaction_type = 'income'
        GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(description, 'BK-', -1), ' ', 1), transaction_date
        HAVING cnt > 1
        ORDER BY transaction_date DESC, cnt DESC
    ");
    
    if (count($duplicates) === 0) {
        echo '<p style="color: green; font-weight: bold;">✅ No duplicates found!</p>';
    } else {
        $totalDuplicateAmount = 0;
        echo '<p style="color: red; font-weight: bold;">⚠️ Found ' . count($duplicates) . ' groups with duplicates</p>';
        
        echo '<table><tr><th>Booking Code</th><th>Date</th><th>Count</th><th>IDs</th><th>Total Amount</th></tr>';
        foreach ($duplicates as $d) {
            $ids = explode(',', $d['ids']);
            // First ID is original, rest are duplicates
            $duplicateIds = array_slice($ids, 1);
            if (count($duplicateIds) > 0) {
                // Calculate amount of duplicates
                $dupAmount = $db->fetchOne("SELECT SUM(amount) as total FROM cash_book WHERE id IN (" . implode(',', $duplicateIds) . ")");
                $totalDuplicateAmount += $dupAmount['total'] ?? 0;
            }
            
            echo '<tr class="danger">';
            echo "<td>BK-{$d['booking_code']}</td>";
            echo "<td>{$d['transaction_date']}</td>";
            echo "<td>{$d['cnt']}</td>";
            echo "<td>{$d['ids']}</td>";
            echo "<td style='text-align:right;'>Rp " . number_format($d['total_amount'], 0, ',', '.') . "</td>";
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<div class="summary">';
        echo '<p><strong>Estimated duplicate amount to remove:</strong> Rp ' . number_format($totalDuplicateAmount, 0, ',', '.') . '</p>';
        echo '<p><a class="btn btn-danger" href="?action=cleanup" onclick="return confirm(\'Delete all duplicate entries? This will keep only the first entry for each booking code per day.\')">🗑️ Delete Duplicates</a></p>';
        echo '</div>';
    }
}

// === CLEANUP DUPLICATES ===
if ($action === 'cleanup') {
    echo '<h2>🗑️ Cleaning Up Duplicates</h2>';
    
    $duplicates = $db->fetchAll("
        SELECT 
            SUBSTRING_INDEX(SUBSTRING_INDEX(description, 'BK-', -1), ' ', 1) as booking_code,
            transaction_date,
            GROUP_CONCAT(id ORDER BY id) as ids
        FROM cash_book
        WHERE description LIKE '%BK-%'
        AND transaction_type = 'income'
        GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(description, 'BK-', -1), ' ', 1), transaction_date
        HAVING COUNT(*) > 1
    ");
    
    $deletedCount = 0;
    $deletedAmount = 0;
    
    foreach ($duplicates as $d) {
        $ids = explode(',', $d['ids']);
        // Keep first ID, delete the rest
        $keepId = array_shift($ids);
        $deleteIds = $ids;
        
        if (count($deleteIds) > 0) {
            // Get amount being deleted
            $delAmount = $db->fetchOne("SELECT SUM(amount) as total FROM cash_book WHERE id IN (" . implode(',', $deleteIds) . ")");
            $deletedAmount += $delAmount['total'] ?? 0;
            
            // Delete duplicates
            $db->query("DELETE FROM cash_book WHERE id IN (" . implode(',', $deleteIds) . ")");
            $deletedCount += count($deleteIds);
            
            echo "<p>✅ BK-{$d['booking_code']} ({$d['transaction_date']}): Kept ID {$keepId}, deleted IDs: " . implode(', ', $deleteIds) . "</p>";
        }
    }
    
    if ($deletedCount === 0) {
        echo '<p style="color: green;">✅ No duplicates to delete!</p>';
    } else {
        echo '<div class="summary success">';
        echo '<h3>Cleanup Complete!</h3>';
        echo '<p><strong>Deleted entries:</strong> ' . $deletedCount . '</p>';
        echo '<p><strong>Amount removed:</strong> Rp ' . number_format($deletedAmount, 0, ',', '.') . '</p>';
        echo '</div>';
    }
    
    echo '<p><a class="btn btn-info" href="?action=view">← Back to View</a></p>';
}

echo '</body></html>';
