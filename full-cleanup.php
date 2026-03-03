<?php
/**
 * FULL CLEANUP: Remove ALL invalid cash_book entries
 * Catches entries with or without BK- prefix
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
echo '<!DOCTYPE html><html><head><title>Full Cash Book Cleanup</title>';
echo '<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1, h2, h3 { color: #333; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; background: white; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
th { background: #dc3545; color: white; }
.valid th { background: #28a745; }
.btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; color: white; }
.btn-danger { background: #dc3545; }
.btn-info { background: #17a2b8; }
.btn-warning { background: #ffc107; color: #333; }
.box { background: white; padding: 15px; margin: 15px 0; border-radius: 8px; }
.danger { background: #f8d7da; }
.success { background: #d4edda; }
.warning { background: #fff3cd; }
</style></head><body>';

echo '<h1>🧹 FULL Cash Book Cleanup</h1>';
echo '<p>Date: ' . date('Y-m-d H:i:s') . '</p>';

echo '<div class="box">';
echo '<a class="btn btn-info" href="?action=view">👁️ View All Entries</a> ';
echo '<a class="btn btn-danger" href="?action=delete" onclick="return confirm(\'HAPUS semua entry INVALID?\')">🗑️ DELETE Invalid</a> ';
echo '<a class="btn btn-warning" href="?action=delete_all_income" onclick="return confirm(\'HAPUS SEMUA income entries? Ini akan reset total!\')">⚠️ DELETE ALL Income</a> ';
echo '</div>';

// Get ALL bookings for reference (for matching)
$allBookings = $conn->query("
    SELECT b.id, b.booking_code, b.status, b.booking_source, b.final_price, b.check_in_date, b.check_out_date,
           g.guest_name
    FROM bookings b
    LEFT JOIN guests g ON b.guest_id = g.id
    ORDER BY b.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Index bookings by code for fast lookup
$bookingsByCode = [];
foreach ($allBookings as $b) {
    $bookingsByCode[$b['booking_code']] = $b;
}

// Also index by partial patterns (guest name, room mentions, etc.)
$bookingPatterns = [];
foreach ($allBookings as $b) {
    // Store patterns for matching
    $bookingPatterns[] = [
        'booking' => $b,
        'code' => $b['booking_code'],
        'guest' => strtolower($b['guest_name'] ?? ''),
    ];
}

// Function to find matching booking
function findMatchingBooking($description, $bookingsByCode, $bookingPatterns) {
    $desc = $description;
    
    // 1. Try exact BK- code match
    if (preg_match('/BK-\d+-\d+/', $desc, $matches)) {
        $code = $matches[0];
        if (isset($bookingsByCode[$code])) {
            return $bookingsByCode[$code];
        }
    }
    
    // 2. Try any BK- pattern
    if (preg_match('/BK-[A-Z0-9-]+/i', $desc, $matches)) {
        $code = $matches[0];
        if (isset($bookingsByCode[$code])) {
            return $bookingsByCode[$code];
        }
        // Try partial match
        foreach ($bookingsByCode as $bcode => $b) {
            if (strpos($bcode, $code) !== false || strpos($code, $bcode) !== false) {
                return $b;
            }
        }
    }
    
    // 3. Try matching by guest name (if unique enough)
    $descLower = strtolower($desc);
    foreach ($bookingPatterns as $p) {
        if (!empty($p['guest']) && strlen($p['guest']) > 3) {
            if (strpos($descLower, $p['guest']) !== false) {
                return $p['booking'];
            }
        }
    }
    
    return null;
}

// Get ALL cash_book income entries
$allEntries = $conn->query("
    SELECT id, transaction_date, amount, payment_method, description, transaction_type, category_id
    FROM cash_book 
    WHERE transaction_type = 'income'
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Analyze each entry
$invalidEntries = [];
$validEntries = [];
$unknownEntries = [];

foreach ($allEntries as $entry) {
    $booking = findMatchingBooking($entry['description'], $bookingsByCode, $bookingPatterns);
    
    if ($booking) {
        $entry['booking_code'] = $booking['booking_code'];
        $entry['booking_status'] = $booking['status'];
        $entry['booking_source'] = $booking['booking_source'];
        $entry['guest_name'] = $booking['guest_name'];
        $entry['final_price'] = $booking['final_price'];
        
        // VALID: Only checked_in or checked_out
        if (in_array($booking['status'], ['checked_in', 'checked_out'])) {
            $validEntries[] = $entry;
        } else {
            // INVALID: pending, confirmed, cancelled, etc.
            $invalidEntries[] = $entry;
        }
    } else {
        // Can't match to any booking - show separately
        $entry['booking_code'] = '-';
        $entry['booking_status'] = '-';
        $entry['booking_source'] = '-';
        $entry['guest_name'] = '-';
        $unknownEntries[] = $entry;
    }
}

// =========== VIEW ===========
if ($action === 'view') {
    // Summary
    echo '<div class="box">';
    echo '<h3>📊 Summary</h3>';
    echo '<p>Total income entries: <strong>' . count($allEntries) . '</strong></p>';
    echo '<p style="color:red">❌ Invalid (belum check-in): <strong>' . count($invalidEntries) . '</strong></p>';
    echo '<p style="color:green">✅ Valid (checked_in/out): <strong>' . count($validEntries) . '</strong></p>';
    echo '<p style="color:orange">⚠️ Unknown (tidak match): <strong>' . count($unknownEntries) . '</strong></p>';
    echo '</div>';
    
    // INVALID entries
    echo '<h2>❌ INVALID - Akan Dihapus (' . count($invalidEntries) . ')</h2>';
    if (count($invalidEntries) === 0) {
        echo '<div class="box success">✅ Tidak ada entry invalid!</div>';
    } else {
        $totalInvalid = 0;
        echo '<div class="box danger">';
        echo '<table><tr><th>ID</th><th>Date</th><th>Booking</th><th>Status</th><th>Source</th><th>Guest</th><th>Amount</th><th>Description</th></tr>';
        foreach ($invalidEntries as $e) {
            $totalInvalid += $e['amount'];
            echo '<tr>';
            echo '<td>' . $e['id'] . '</td>';
            echo '<td>' . $e['transaction_date'] . '</td>';
            echo '<td>' . htmlspecialchars($e['booking_code']) . '</td>';
            echo '<td><strong style="color:red">' . strtoupper($e['booking_status']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($e['booking_source']) . '</td>';
            echo '<td>' . htmlspecialchars($e['guest_name']) . '</td>';
            echo '<td>Rp ' . number_format($e['amount'], 0, ',', '.') . '</td>';
            echo '<td style="font-size:10px;max-width:200px;overflow:hidden">' . htmlspecialchars(substr($e['description'], 0, 50)) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<p><strong>Total: Rp ' . number_format($totalInvalid, 0, ',', '.') . '</strong></p>';
        echo '</div>';
    }
    
    // VALID entries
    echo '<h2>✅ VALID - Akan Dipertahankan (' . count($validEntries) . ')</h2>';
    if (count($validEntries) === 0) {
        echo '<p>Tidak ada entry valid.</p>';
    } else {
        $totalValid = 0;
        echo '<table class="valid"><tr><th>ID</th><th>Date</th><th>Booking</th><th>Status</th><th>Source</th><th>Guest</th><th>Amount</th></tr>';
        foreach (array_slice($validEntries, 0, 20) as $e) {
            $totalValid += $e['amount'];
            echo '<tr>';
            echo '<td>' . $e['id'] . '</td>';
            echo '<td>' . $e['transaction_date'] . '</td>';
            echo '<td>' . htmlspecialchars($e['booking_code']) . '</td>';
            echo '<td><strong style="color:green">' . strtoupper($e['booking_status']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($e['booking_source']) . '</td>';
            echo '<td>' . htmlspecialchars($e['guest_name']) . '</td>';
            echo '<td>Rp ' . number_format($e['amount'], 0, ',', '.') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        if (count($validEntries) > 20) {
            echo '<p>... dan ' . (count($validEntries) - 20) . ' lainnya</p>';
        }
        foreach ($validEntries as $e) $totalValid += 0; // Already added above
        $totalValid = array_sum(array_column($validEntries, 'amount'));
        echo '<p><strong>Total Valid: Rp ' . number_format($totalValid, 0, ',', '.') . '</strong></p>';
    }
    
    // UNKNOWN entries
    if (count($unknownEntries) > 0) {
        echo '<h2>⚠️ UNKNOWN - Tidak Match Booking (' . count($unknownEntries) . ')</h2>';
        echo '<div class="box warning">';
        echo '<table><tr><th>ID</th><th>Date</th><th>Amount</th><th>Description</th></tr>';
        foreach (array_slice($unknownEntries, 0, 20) as $e) {
            echo '<tr>';
            echo '<td>' . $e['id'] . '</td>';
            echo '<td>' . $e['transaction_date'] . '</td>';
            echo '<td>Rp ' . number_format($e['amount'], 0, ',', '.') . '</td>';
            echo '<td style="font-size:10px">' . htmlspecialchars(substr($e['description'], 0, 80)) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
}

// =========== DELETE ===========
if ($action === 'delete') {
    echo '<h2>🗑️ Deleting Invalid Entries...</h2>';
    
    if (count($invalidEntries) === 0) {
        echo '<div class="box success">✅ Tidak ada entry yang perlu dihapus!</div>';
    } else {
        $deletedCount = 0;
        $deletedAmount = 0;
        
        foreach ($invalidEntries as $entry) {
            $id = (int)$entry['id'];
            $result = $conn->exec("DELETE FROM cash_book WHERE id = " . $id);
            
            if ($result !== false) {
                echo "<p>✅ Deleted ID={$id} | {$entry['booking_code']} | {$entry['booking_status']} | Rp " . number_format($entry['amount'], 0, ',', '.') . "</p>";
                $deletedCount++;
                $deletedAmount += $entry['amount'];
            }
        }
        
        // Reset sync flags
        $conn->exec("UPDATE booking_payments SET synced_to_cashbook = 0, cashbook_id = NULL");
        
        echo '<div class="box success">';
        echo "<p><strong>✅ Deleted {$deletedCount} entries</strong></p>";
        echo "<p>Total Removed: Rp " . number_format($deletedAmount, 0, ',', '.') . "</p>";
        echo '</div>';
        
        echo '<p><a class="btn btn-info" href="?action=view">👁️ Verify</a></p>';
    }
}

// =========== DELETE ALL INCOME ===========
if ($action === 'delete_all_income') {
    echo '<h2>⚠️ Deleting ALL Income Entries...</h2>';
    
    $count = $conn->exec("DELETE FROM cash_book WHERE transaction_type = 'income'");
    $conn->exec("UPDATE booking_payments SET synced_to_cashbook = 0, cashbook_id = NULL");
    
    echo '<div class="box warning">';
    echo "<p><strong>⚠️ Deleted {$count} income entries</strong></p>";
    echo "<p>All sync flags have been reset.</p>";
    echo "<p>Income akan tercatat ulang saat:</p>";
    echo "<ul><li>Direct Booking: saat bayar DP</li><li>OTA: saat CHECK-IN</li></ul>";
    echo '</div>';
    
    echo '<p><a class="btn btn-info" href="?action=view">👁️ View</a></p>';
}

echo '</body></html>';
