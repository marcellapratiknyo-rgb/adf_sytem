<?php
/**
 * CLEANUP: Remove cash_book entries for non-checked-in bookings
 * HANYA booking dengan status checked_in atau checked_out yang boleh ada di cash_book
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die('Please login first');
}

$db = Database::getInstance();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'view';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Cleanup Non-Checked-In</title>';
echo '<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1, h2 { color: #333; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; background: white; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
th { background: #dc3545; color: white; }
.btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; color: white; }
.btn-danger { background: #dc3545; }
.btn-info { background: #17a2b8; }
.btn-success { background: #28a745; }
.box { background: white; padding: 15px; margin: 15px 0; border-radius: 8px; }
.danger { background: #f8d7da; }
.success { background: #d4edda; }
</style></head><body>';

echo '<h1>🧹 Cleanup: Remove Non-Checked-In Entries from Cash Book</h1>';
echo '<p>Date: ' . date('Y-m-d H:i:s') . '</p>';

echo '<div class="box">';
echo '<a class="btn btn-info" href="?action=view">👁️ View Invalid Entries</a> ';
echo '<a class="btn btn-danger" href="?action=delete" onclick="return confirm(\'HAPUS semua entry cash_book yang booking-nya BELUM check-in?\')">🗑️ DELETE Invalid Entries</a> ';
echo '<a class="btn btn-success" href="?action=reset_sync">🔄 Reset Sync Flags</a> ';
echo '</div>';

// =========== VIEW ===========
if ($action === 'view') {
    echo '<h2>❌ Cash Book Entries dari Booking yang BELUM Check-in</h2>';
    echo '<p>Entries ini SEHARUSNYA tidak ada di cash_book karena tamu belum check-in.</p>';
    
    // Find cash_book entries where booking status is NOT checked_in or checked_out
    $invalidEntries = $conn->query("
        SELECT cb.id, cb.transaction_date, cb.amount, cb.payment_method, cb.description,
               b.booking_code, b.status as booking_status, b.booking_source, g.guest_name
        FROM cash_book cb
        JOIN bookings b ON cb.description LIKE CONCAT('%', b.booking_code, '%')
        LEFT JOIN guests g ON b.guest_id = g.id
        WHERE cb.transaction_type = 'income'
        AND cb.description LIKE '%BK-%'
        AND b.status NOT IN ('checked_in', 'checked_out')
        ORDER BY cb.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($invalidEntries) === 0) {
        echo '<div class="box success"><strong>✅ Tidak ada entry invalid!</strong> Semua entry sudah benar.</div>';
    } else {
        $totalAmount = 0;
        echo '<div class="box danger">';
        echo '<p><strong>⚠️ Ditemukan ' . count($invalidEntries) . ' entries yang SALAH</strong></p>';
        echo '<table><tr><th>CB ID</th><th>Date</th><th>Booking</th><th>Status</th><th>Source</th><th>Guest</th><th>Amount</th></tr>';
        foreach ($invalidEntries as $e) {
            $totalAmount += $e['amount'];
            echo '<tr>';
            echo '<td>' . $e['id'] . '</td>';
            echo '<td>' . $e['transaction_date'] . '</td>';
            echo '<td>' . $e['booking_code'] . '</td>';
            echo '<td><strong style="color:red">' . strtoupper($e['booking_status']) . '</strong></td>';
            echo '<td>' . $e['booking_source'] . '</td>';
            echo '<td>' . $e['guest_name'] . '</td>';
            echo '<td>Rp ' . number_format($e['amount'], 0, ',', '.') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<p><strong>Total Amount to Remove: Rp ' . number_format($totalAmount, 0, ',', '.') . '</strong></p>';
        echo '</div>';
        
        echo '<p><a class="btn btn-danger" href="?action=delete" onclick="return confirm(\'HAPUS ' . count($invalidEntries) . ' entries ini?\')">🗑️ DELETE These Entries</a></p>';
    }
    
    // Also show valid entries
    echo '<h2>✅ Valid Entries (dari Checked-In/Checked-Out)</h2>';
    $validEntries = $conn->query("
        SELECT cb.id, cb.transaction_date, cb.amount, cb.description,
               b.booking_code, b.status as booking_status, g.guest_name
        FROM cash_book cb
        JOIN bookings b ON cb.description LIKE CONCAT('%', b.booking_code, '%')
        LEFT JOIN guests g ON b.guest_id = g.id
        WHERE cb.transaction_type = 'income'
        AND cb.description LIKE '%BK-%'
        AND b.status IN ('checked_in', 'checked_out')
        ORDER BY cb.id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($validEntries) > 0) {
        echo '<table><tr><th>CB ID</th><th>Date</th><th>Booking</th><th>Status</th><th>Guest</th><th>Amount</th></tr>';
        foreach ($validEntries as $e) {
            echo '<tr>';
            echo '<td>' . $e['id'] . '</td>';
            echo '<td>' . $e['transaction_date'] . '</td>';
            echo '<td>' . $e['booking_code'] . '</td>';
            echo '<td style="color:green"><strong>' . strtoupper($e['booking_status']) . '</strong></td>';
            echo '<td>' . $e['guest_name'] . '</td>';
            echo '<td>Rp ' . number_format($e['amount'], 0, ',', '.') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>Tidak ada entry valid ditemukan.</p>';
    }
}

// =========== DELETE ===========
if ($action === 'delete') {
    echo '<h2>🗑️ Deleting Invalid Entries...</h2>';
    
    // Get IDs to delete
    $toDelete = $conn->query("
        SELECT cb.id, cb.amount, b.booking_code, b.status
        FROM cash_book cb
        JOIN bookings b ON cb.description LIKE CONCAT('%', b.booking_code, '%')
        WHERE cb.transaction_type = 'income'
        AND cb.description LIKE '%BK-%'
        AND b.status NOT IN ('checked_in', 'checked_out')
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($toDelete) === 0) {
        echo '<div class="box success">✅ Tidak ada entry yang perlu dihapus!</div>';
    } else {
        $deletedCount = 0;
        $deletedAmount = 0;
        
        foreach ($toDelete as $entry) {
            $conn->query("DELETE FROM cash_book WHERE id = " . (int)$entry['id']);
            echo "<p>✅ Deleted ID={$entry['id']} | {$entry['booking_code']} | Status={$entry['status']} | Rp " . number_format($entry['amount'], 0, ',', '.') . "</p>";
            $deletedCount++;
            $deletedAmount += $entry['amount'];
            
            // Also reset booking_payments sync flag
            $conn->query("UPDATE booking_payments SET synced_to_cashbook = 0, cashbook_id = NULL 
                          WHERE cashbook_id = " . (int)$entry['id']);
        }
        
        echo '<div class="box success">';
        echo "<h3>✅ Cleanup Complete!</h3>";
        echo "<p>Deleted: {$deletedCount} entries</p>";
        echo "<p>Amount removed: Rp " . number_format($deletedAmount, 0, ',', '.') . "</p>";
        echo '</div>';
    }
    
    echo '<p><a class="btn btn-info" href="?action=view">← Back to View</a></p>';
}

// =========== RESET SYNC ===========
if ($action === 'reset_sync') {
    echo '<h2>🔄 Reset Sync Flags for Non-Checked-In Bookings</h2>';
    
    // Reset sync flags for bookings that are not checked_in or checked_out
    $result = $conn->exec("
        UPDATE booking_payments bp
        JOIN bookings b ON bp.booking_id = b.id
        SET bp.synced_to_cashbook = 0, bp.cashbook_id = NULL
        WHERE b.status NOT IN ('checked_in', 'checked_out')
    ");
    
    echo "<div class='box success'>✅ Reset sync flags for payments of non-checked-in bookings. Affected rows: {$result}</div>";
    
    echo '<p><a class="btn btn-info" href="?action=view">← Back to View</a></p>';
}

echo '</body></html>';
