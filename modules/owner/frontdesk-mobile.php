<?php
/**
 * FRONTDESK MOBILE DASHBOARD
 * Mobile-optimized view for owner monitoring
 * Clean, Compact, Modern
 */

session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
if (!in_array($currentUser['role'], ['owner', 'admin', 'developer'])) {
    header('Location: dashboard-2028.php');
    exit;
}

$db = Database::getInstance();
$basePath = rtrim(BASE_URL, '/');

// Get today's date
$today = date('Y-m-d');
$thisMonth = date('Y-m');

// Initialize default values
$stats = [
    'checkins' => 0,
    'checkouts' => 0,
    'available' => 0,
    'occupied' => 0,
    'total_rooms' => 0,
    'occupancy' => 0,
    'today_revenue' => 0,
    'month_revenue' => 0
];
$inHouseGuests = [];
$todayArrivals = [];
$todayDepartures = [];
$roomStatusMap = [];
$error = null;

// Check if frontdesk tables exist
$hasFrontdeskTables = false;
try {
    $db->getConnection()->query("SELECT 1 FROM rooms LIMIT 1");
    $hasFrontdeskTables = true;
} catch (Exception $e) {
    $hasFrontdeskTables = false;
}

if ($hasFrontdeskTables) {
try {
    // Today's check-ins
    $checkinsResult = $db->fetchOne("
        SELECT COUNT(*) as count FROM bookings 
        WHERE DATE(check_in_date) = ? 
        AND status IN ('confirmed', 'checked_in')
    ", [$today]);
    $stats['checkins'] = $checkinsResult['count'] ?? 0;

    // Today's check-outs
    $checkoutsResult = $db->fetchOne("
        SELECT COUNT(*) as count FROM bookings 
        WHERE DATE(check_out_date) = ? 
        AND status = 'checked_in'
    ", [$today]);
    $stats['checkouts'] = $checkoutsResult['count'] ?? 0;

    // Available rooms
    $availResult = $db->fetchOne("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'");
    $stats['available'] = $availResult['count'] ?? 0;

    // Occupied rooms
    $occupiedResult = $db->fetchOne("SELECT COUNT(*) as count FROM rooms WHERE status = 'occupied'");
    $stats['occupied'] = $occupiedResult['count'] ?? 0;

    // Total rooms
    $totalResult = $db->fetchOne("SELECT COUNT(*) as count FROM rooms");
    $stats['total_rooms'] = $totalResult['count'] ?? 0;

    // Occupancy rate
    $stats['occupancy'] = $stats['total_rooms'] > 0 ? round(($stats['occupied'] / $stats['total_rooms']) * 100) : 0;

    // Today's revenue
    $revenueResult = $db->fetchOne("
        SELECT COALESCE(SUM(bp.amount), 0) as total
        FROM booking_payments bp
        WHERE DATE(bp.payment_date) = ?
    ", [$today]);
    $stats['today_revenue'] = $revenueResult['total'] ?? 0;

    // Monthly revenue
    $monthResult = $db->fetchOne("
        SELECT COALESCE(SUM(bp.amount), 0) as total
        FROM booking_payments bp
        WHERE DATE_FORMAT(bp.payment_date, '%Y-%m') = ?
    ", [$thisMonth]);
    $stats['month_revenue'] = $monthResult['total'] ?? 0;

    // In-house guests list
    $inHouseGuests = $db->fetchAll("
        SELECT b.id, b.guest_name, b.guest_phone, b.check_in_date, b.check_out_date, 
               r.room_number, r.room_type, b.total_amount
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE b.status = 'checked_in'
        ORDER BY b.check_out_date ASC
        LIMIT 10
    ");

    // Today's arrivals
    $todayArrivals = $db->fetchAll("
        SELECT b.id, b.guest_name, b.check_in_date, b.check_out_date, 
               r.room_number, r.room_type, b.status
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE DATE(b.check_in_date) = ? AND b.status IN ('confirmed', 'checked_in')
        ORDER BY b.check_in_date ASC
        LIMIT 5
    ", [$today]);

    // Today's departures
    $todayDepartures = $db->fetchAll("
        SELECT b.id, b.guest_name, b.check_out_date, 
               r.room_number, r.room_type, b.status
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE DATE(b.check_out_date) = ? AND b.status = 'checked_in'
        ORDER BY b.check_out_date ASC
        LIMIT 5
    ", [$today]);

    // Room status breakdown
    $roomStatus = $db->fetchAll("
        SELECT status, COUNT(*) as count FROM rooms GROUP BY status
    ");
    $roomStatusMap = [];
    foreach ($roomStatus as $rs) {
        $roomStatusMap[$rs['status']] = $rs['count'];
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
} // end if hasFrontdeskTables

function rp($num) {
    return 'Rp ' . number_format($num, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Frontdesk Monitor - Owner</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-bottom: 70px;
        }
        
        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 16px;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 20px 16px;
            border-radius: 16px;
            margin-bottom: 16px;
            text-align: center;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .header-subtitle {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .header-date {
            font-size: 11px;
            opacity: 0.8;
            margin-top: 8px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .stat-card {
            background: var(--card);
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent-color);
        }
        
        .stat-card.checkin { --accent-color: var(--success); }
        .stat-card.checkout { --accent-color: var(--warning); }
        .stat-card.available { --accent-color: var(--info); }
        .stat-card.occupied { --accent-color: var(--danger); }
        
        .stat-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 6px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
        }
        
        .stat-hint {
            font-size: 9px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        /* Occupancy Card */
        .occupancy-card {
            background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%);
            color: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .occupancy-label {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.9;
        }
        
        .occupancy-value {
            font-size: 32px;
            font-weight: 800;
        }
        
        .occupancy-detail {
            font-size: 10px;
            opacity: 0.8;
            margin-top: 4px;
        }
        
        .occupancy-bar {
            width: 100px;
            height: 8px;
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .occupancy-fill {
            height: 100%;
            background: white;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        /* Revenue Card */
        .revenue-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .revenue-card {
            background: var(--card);
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .revenue-label {
            font-size: 10px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 6px;
        }
        
        .revenue-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--success);
        }
        
        /* Section Title */
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Guest List */
        .guest-list {
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 16px;
        }
        
        .guest-item {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .guest-item:last-child {
            border-bottom: none;
        }
        
        .guest-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
        }
        
        .guest-room {
            font-size: 11px;
            color: var(--text-muted);
        }
        
        .guest-date {
            font-size: 10px;
            color: var(--text-muted);
            text-align: right;
        }
        
        .guest-status {
            font-size: 9px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .guest-status.in { background: #dcfce7; color: #16a34a; }
        .guest-status.out { background: #fef3c7; color: #d97706; }
        .guest-status.confirmed { background: #dbeafe; color: #2563eb; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 24px;
            color: var(--text-muted);
            font-size: 12px;
        }
        
        /* Room Status Grid */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .room-status-card {
            background: var(--card);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        
        .room-status-icon {
            font-size: 18px;
            margin-bottom: 4px;
        }
        
        .room-status-count {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }
        
        .room-status-label {
            font-size: 8px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 600;
        }
        
        /* Footer Nav */
        .nav-bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            border-top: 1px solid var(--border);
            box-shadow: 0 -4px 12px rgba(0,0,0,0.05);
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 10px;
            font-weight: 600;
            transition: color 0.2s;
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        .nav-icon {
            font-size: 20px;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">📋 Frontdesk Monitor</div>
            <div class="header-subtitle">Real-time hotel status</div>
            <div class="header-date"><?= date('l, d F Y') ?></div>
        </div>
        
        <?php if (!$hasFrontdeskTables): ?>
        <!-- No Frontdesk Tables -->
        <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px; padding: 24px; margin-bottom: 16px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 12px;">🏨</div>
            <div style="font-size: 16px; font-weight: 700; color: #92400e; margin-bottom: 8px;">Modul Frontdesk Tidak Tersedia</div>
            <div style="font-size: 12px; color: #a16207;">Database frontdesk (rooms, bookings) belum dikonfigurasi untuk bisnis ini.</div>
        </div>
        <?php elseif ($error): ?>
        <!-- Error -->
        <div style="background: #fee2e2; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
            <div style="font-size: 12px; color: #dc2626; font-weight: 600;">Error: <?= htmlspecialchars($error) ?></div>
        </div>
        <?php else: ?>
        
        <!-- Occupancy -->
        <div class="occupancy-card">
            <div>
                <div class="occupancy-label">Occupancy Rate</div>
                <div class="occupancy-value"><?= $stats['occupancy'] ?>%</div>
                <div class="occupancy-detail"><?= $stats['occupied'] ?> dari <?= $stats['total_rooms'] ?> kamar terisi</div>
            </div>
            <div>
                <div class="occupancy-bar">
                    <div class="occupancy-fill" style="width: <?= $stats['occupancy'] ?>%"></div>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card checkin">
                <div class="stat-label">📥 Check-In Hari Ini</div>
                <div class="stat-value"><?= $stats['checkins'] ?></div>
                <div class="stat-hint">Tamu akan datang</div>
            </div>
            <div class="stat-card checkout">
                <div class="stat-label">📤 Check-Out Hari Ini</div>
                <div class="stat-value"><?= $stats['checkouts'] ?></div>
                <div class="stat-hint">Tamu akan pergi</div>
            </div>
            <div class="stat-card available">
                <div class="stat-label">🛏️ Kamar Tersedia</div>
                <div class="stat-value"><?= $stats['available'] ?></div>
                <div class="stat-hint">Siap dijual</div>
            </div>
            <div class="stat-card occupied">
                <div class="stat-label">🔒 Kamar Terisi</div>
                <div class="stat-value"><?= $stats['occupied'] ?></div>
                <div class="stat-hint">Tamu menginap</div>
            </div>
        </div>
        
        <!-- Revenue -->
        <div class="revenue-grid">
            <div class="revenue-card">
                <div class="revenue-label">💰 Pendapatan Hari Ini</div>
                <div class="revenue-value"><?= rp($stats['today_revenue']) ?></div>
            </div>
            <div class="revenue-card">
                <div class="revenue-label">📊 Pendapatan Bulan Ini</div>
                <div class="revenue-value"><?= rp($stats['month_revenue']) ?></div>
            </div>
        </div>
        
        <!-- Room Status -->
        <div class="section-title">🚪 Status Kamar</div>
        <div class="room-grid">
            <div class="room-status-card">
                <div class="room-status-icon">✅</div>
                <div class="room-status-count"><?= $roomStatusMap['available'] ?? 0 ?></div>
                <div class="room-status-label">Ready</div>
            </div>
            <div class="room-status-card">
                <div class="room-status-icon">🛏️</div>
                <div class="room-status-count"><?= $roomStatusMap['occupied'] ?? 0 ?></div>
                <div class="room-status-label">Occupied</div>
            </div>
            <div class="room-status-card">
                <div class="room-status-icon">🧹</div>
                <div class="room-status-count"><?= $roomStatusMap['cleaning'] ?? 0 ?></div>
                <div class="room-status-label">Cleaning</div>
            </div>
            <div class="room-status-card">
                <div class="room-status-icon">🔧</div>
                <div class="room-status-count"><?= $roomStatusMap['maintenance'] ?? 0 ?></div>
                <div class="room-status-label">Maintain</div>
            </div>
        </div>
        
        <!-- In-House Guests -->
        <div class="section-title">👤 Tamu In-House (<?= count($inHouseGuests) ?>)</div>
        <div class="guest-list">
            <?php if (!empty($inHouseGuests)): ?>
                <?php foreach ($inHouseGuests as $guest): ?>
                <div class="guest-item">
                    <div>
                        <div class="guest-name"><?= htmlspecialchars($guest['guest_name']) ?></div>
                        <div class="guest-room">Room <?= $guest['room_number'] ?> • <?= $guest['room_type'] ?? 'Standard' ?></div>
                    </div>
                    <div>
                        <div class="guest-date">C/O: <?= date('d M', strtotime($guest['check_out_date'])) ?></div>
                        <span class="guest-status in">In-House</span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">Tidak ada tamu in-house</div>
            <?php endif; ?>
        </div>
        
        <!-- Today's Arrivals -->
        <?php if (!empty($todayArrivals)): ?>
        <div class="section-title">📥 Kedatangan Hari Ini (<?= count($todayArrivals) ?>)</div>
        <div class="guest-list">
            <?php foreach ($todayArrivals as $arrival): ?>
            <div class="guest-item">
                <div>
                    <div class="guest-name"><?= htmlspecialchars($arrival['guest_name']) ?></div>
                    <div class="guest-room">Room <?= $arrival['room_number'] ?? '-' ?></div>
                </div>
                <div>
                    <span class="guest-status <?= $arrival['status'] === 'checked_in' ? 'in' : 'confirmed' ?>">
                        <?= $arrival['status'] === 'checked_in' ? 'Checked In' : 'Confirmed' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Today's Departures -->
        <?php if (!empty($todayDepartures)): ?>
        <div class="section-title">📤 Keberangkatan Hari Ini (<?= count($todayDepartures) ?>)</div>
        <div class="guest-list">
            <?php foreach ($todayDepartures as $departure): ?>
            <div class="guest-item">
                <div>
                    <div class="guest-name"><?= htmlspecialchars($departure['guest_name']) ?></div>
                    <div class="guest-room">Room <?= $departure['room_number'] ?? '-' ?></div>
                </div>
                <div>
                    <span class="guest-status out">Check Out</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php endif; // end else (no error, has tables) ?>
        
    </div>
    
    <!-- Footer Nav -->
    <nav class="nav-bottom">
        <a href="<?= $basePath ?>/modules/owner/dashboard-2028.php" class="nav-item">
            <span class="nav-icon">🏠</span>
            <span>Home</span>
        </a>
        <a href="<?= $basePath ?>/modules/owner/frontdesk-mobile.php" class="nav-item active">
            <span class="nav-icon">📋</span>
            <span>Frontdesk</span>
        </a>
        <a href="<?= $basePath ?>/modules/owner/investor-monitor.php" class="nav-item">
            <span class="nav-icon">📈</span>
            <span>Proyek</span>
        </a>
        <a href="<?= $basePath ?>/logout.php" class="nav-item">
            <span class="nav-icon">🚪</span>
            <span>Logout</span>
        </a>
    </nav>
</body>
</html>
