<?php
/**
 * DASHBOARD NARAYANA HOTEL - Dengan Pie Chart
 * Data langsung dari database, tampilan modern dengan pie chart
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../../config/config.php';

$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false);
$basePath = $isProduction ? '' : '/adf_system';

// PAKAI CREDENTIALS DARI CONFIG.PHP!
$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = $isProduction ? 'adfb2574_narayana_hotel' : 'adf_narayana_hotel';

// Get stats langsung dari database
$stats = [
    'today_income' => 0,
    'today_expense' => 0,
    'month_income' => 0,
    'month_expense' => 0,
    'total_transactions' => 0
];
$transactions = [];
$error = null;

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    
    // Today Income
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM cash_book WHERE DATE(transaction_date) = ? AND transaction_type = 'income'");
    $stmt->execute([$today]);
    $stats['today_income'] = (float)$stmt->fetchColumn();
    
    // Today Expense
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM cash_book WHERE DATE(transaction_date) = ? AND transaction_type = 'expense'");
    $stmt->execute([$today]);
    $stats['today_expense'] = (float)$stmt->fetchColumn();
    
    // Month Income
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM cash_book WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ? AND transaction_type = 'income'");
    $stmt->execute([$thisMonth]);
    $stats['month_income'] = (float)$stmt->fetchColumn();
    
    // Month Expense
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM cash_book WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ? AND transaction_type = 'expense'");
    $stmt->execute([$thisMonth]);
    $stats['month_expense'] = (float)$stmt->fetchColumn();
    
    // Total transactions
    $stmt = $pdo->query("SELECT COUNT(*) FROM cash_book");
    $stats['total_transactions'] = (int)$stmt->fetchColumn();
    
    // Recent transactions
    $stmt = $pdo->query("SELECT id, transaction_date, description, transaction_type, amount FROM cash_book ORDER BY transaction_date DESC, id DESC LIMIT 10");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Format rupiah
function rp($num) {
    return 'Rp ' . number_format($num, 0, ',', '.');
}

$netProfit = $stats['month_income'] - $stats['month_expense'];
$netToday = $stats['today_income'] - $stats['today_expense'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Narayana Hotel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --text-primary: #334155;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --accent: #6366f1;
            --success: #10b981;
            --danger: #f43f5e;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 80px;
        }
        
        .container {
            max-width: 100%;
            padding: 16px;
        }
        
        /* Hero Section with Pie Chart */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .hero-subtitle {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 16px;
        }
        
        .hero-date {
            font-size: 11px;
            opacity: 0.7;
        }
        
        /* Pie Chart Container */
        .chart-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }
        
        .pie-wrapper {
            position: relative;
            width: 160px;
            height: 160px;
        }
        
        #pieChart {
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }
        
        .pie-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            width: 80px;
            height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .pie-center-label {
            font-size: 9px;
            opacity: 0.8;
            text-transform: uppercase;
        }
        
        .pie-center-value {
            font-size: 14px;
            font-weight: 700;
        }
        
        .legend {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .legend-dot.income { background: #34d399; }
        .legend-dot.expense { background: #fb7185; }
        
        .legend-text {
            display: flex;
            flex-direction: column;
        }
        
        .legend-label {
            font-size: 10px;
            opacity: 0.7;
            text-transform: uppercase;
        }
        
        .legend-value {
            font-size: 14px;
            font-weight: 600;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        
        .stat-value {
            font-size: 18px;
            font-weight: 700;
        }
        
        .stat-value.income { color: var(--success); }
        .stat-value.expense { color: var(--danger); }
        
        /* Summary Card */
        .summary-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .summary-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-row.total {
            font-weight: 700;
            font-size: 15px;
            padding-top: 16px;
            margin-top: 8px;
            border-top: 2px solid var(--border);
            border-bottom: none;
        }
        
        /* Transactions */
        .tx-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .tx-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .tx-list {
            list-style: none;
        }
        
        .tx-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .tx-item:last-child {
            border-bottom: none;
        }
        
        .tx-desc {
            font-size: 13px;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        
        .tx-date {
            font-size: 11px;
            color: var(--text-muted);
        }
        
        .tx-amount {
            font-size: 13px;
            font-weight: 600;
        }
        
        .tx-amount.income { color: var(--success); }
        .tx-amount.expense { color: var(--danger); }
        
        /* AI Health Card */
        .ai-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .ai-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .ai-badge {
            background: #f59e0b;
            color: white;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .ai-title {
            font-size: 14px;
            font-weight: 600;
            color: #92400e;
        }
        
        .ai-content {
            font-size: 13px;
            color: #78350f;
            line-height: 1.6;
        }
        
        /* DB Info */
        .db-info {
            background: #f0fdf4;
            color: #166534;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 11px;
            margin-bottom: 16px;
        }
        
        /* Error */
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 16px;
            border-radius: 12px;
            font-size: 13px;
        }
        
        /* Bottom Nav */
        .nav-bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            border-top: 1px solid var(--border);
            box-shadow: 0 -4px 12px rgba(0,0,0,0.05);
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            font-size: 11px;
            color: var(--text-muted);
            transition: color 0.2s;
        }
        
        .nav-item.active {
            color: var(--accent);
        }
        
        .nav-icon {
            font-size: 22px;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="error">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php else: ?>
        
        <div class="db-info">
            ✅ Connected: <?= $dbName ?> | <?= $stats['total_transactions'] ?> transaksi
        </div>
        
        <!-- Hero with Pie Chart -->
        <div class="hero">
            <div class="hero-content">
                <div class="hero-title">🏨 Narayana Hotel</div>
                <div class="hero-subtitle">Owner Dashboard</div>
                <div class="hero-date"><?= date('l, d F Y') ?></div>
                
                <div class="chart-container">
                    <div class="pie-wrapper">
                        <canvas id="pieChart" width="160" height="160"></canvas>
                        <div class="pie-center">
                            <div class="pie-center-label">Net Bulan</div>
                            <div class="pie-center-value"><?= $netProfit >= 0 ? '+' : '' ?><?= number_format($netProfit/1000000, 1) ?>M</div>
                        </div>
                    </div>
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-dot income"></div>
                            <div class="legend-text">
                                <div class="legend-label">Income</div>
                                <div class="legend-value"><?= rp($stats['month_income']) ?></div>
                            </div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot expense"></div>
                            <div class="legend-text">
                                <div class="legend-label">Expense</div>
                                <div class="legend-value"><?= rp($stats['month_expense']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📈 Income Hari Ini</div>
                <div class="stat-value income"><?= rp($stats['today_income']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📉 Expense Hari Ini</div>
                <div class="stat-value expense"><?= rp($stats['today_expense']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📈 Income Bulan Ini</div>
                <div class="stat-value income"><?= rp($stats['month_income']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📉 Expense Bulan Ini</div>
                <div class="stat-value expense"><?= rp($stats['month_expense']) ?></div>
            </div>
        </div>
        
        <!-- AI Health -->
        <div class="ai-card">
            <div class="ai-header">
                <span class="ai-badge">✨ AI</span>
                <span class="ai-title">Business Health Analysis</span>
            </div>
            <div class="ai-content">
                <?php
                $ratio = $stats['month_income'] > 0 ? ($stats['month_expense'] / $stats['month_income']) * 100 : 0;
                if ($ratio < 50) {
                    echo "🟢 <strong>Excellent!</strong> Expense ratio " . number_format($ratio, 1) . "% dari income. Keuangan sangat sehat.";
                } elseif ($ratio < 70) {
                    echo "🟡 <strong>Good.</strong> Expense ratio " . number_format($ratio, 1) . "% dari income. Pertahankan efisiensi.";
                } elseif ($ratio < 90) {
                    echo "🟠 <strong>Warning.</strong> Expense ratio " . number_format($ratio, 1) . "% dari income. Perlu optimasi pengeluaran.";
                } else {
                    echo "🔴 <strong>Critical!</strong> Expense ratio " . number_format($ratio, 1) . "% dari income. Segera evaluasi pengeluaran.";
                }
                ?>
            </div>
        </div>
        
        <!-- Summary -->
        <div class="summary-card">
            <div class="summary-title">📊 Ringkasan Bulan Ini</div>
            <div class="summary-row">
                <span>Total Pemasukan</span>
                <span style="color:var(--success)"><?= rp($stats['month_income']) ?></span>
            </div>
            <div class="summary-row">
                <span>Total Pengeluaran</span>
                <span style="color:var(--danger)"><?= rp($stats['month_expense']) ?></span>
            </div>
            <div class="summary-row total">
                <span>Net Profit</span>
                <span style="color:<?= $netProfit >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= $netProfit >= 0 ? '+' : '' ?><?= rp($netProfit) ?>
                </span>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="tx-card">
            <div class="tx-title">🕐 Transaksi Terakhir</div>
            <?php if (empty($transactions)): ?>
                <p style="color:var(--text-muted);font-size:13px">Belum ada transaksi</p>
            <?php else: ?>
                <ul class="tx-list">
                    <?php foreach ($transactions as $tx): ?>
                    <li class="tx-item">
                        <div>
                            <div class="tx-desc"><?= htmlspecialchars($tx['description']) ?></div>
                            <div class="tx-date"><?= date('d/m/Y H:i', strtotime($tx['transaction_date'])) ?></div>
                        </div>
                        <div class="tx-amount <?= $tx['transaction_type'] ?>">
                            <?= $tx['transaction_type'] == 'income' ? '+' : '-' ?><?= rp($tx['amount']) ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
    </div>
    
    <!-- Bottom Navigation -->
    <nav class="nav-bottom">
        <a href="dashboard-narayana.php" class="nav-item active">
            <span class="nav-icon">📊</span>
            Dashboard
        </a>
        <a href="../frontdesk/dashboard.php" class="nav-item">
            <span class="nav-icon">🛎️</span>
            Frontdesk
        </a>
        <a href="<?= $basePath ?>/modules/investor/dashboard.php" class="nav-item">
            <span class="nav-icon">💰</span>
            Investor
        </a>
        <a href="<?= $basePath ?>/modules/project/dashboard.php" class="nav-item">
            <span class="nav-icon">📋</span>
            Projek
        </a>
    </nav>
    
    <!-- Pie Chart Script -->
    <script>
        const income = <?= $stats['month_income'] ?>;
        const expense = <?= $stats['month_expense'] ?>;
        const total = income + expense;
        
        const canvas = document.getElementById('pieChart');
        const ctx = canvas.getContext('2d');
        const centerX = 80;
        const centerY = 80;
        const radius = 70;
        
        // Draw pie chart
        function drawPie() {
            if (total === 0) {
                // Empty state
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                ctx.fillStyle = 'rgba(255,255,255,0.2)';
                ctx.fill();
                return;
            }
            
            const incomeAngle = (income / total) * 2 * Math.PI;
            const expenseAngle = (expense / total) * 2 * Math.PI;
            
            // Income slice (green)
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, -Math.PI/2, -Math.PI/2 + incomeAngle);
            ctx.closePath();
            ctx.fillStyle = '#34d399';
            ctx.fill();
            
            // Expense slice (pink)
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, -Math.PI/2 + incomeAngle, -Math.PI/2 + incomeAngle + expenseAngle);
            ctx.closePath();
            ctx.fillStyle = '#fb7185';
            ctx.fill();
            
            // Center circle (donut hole)
            ctx.beginPath();
            ctx.arc(centerX, centerY, 40, 0, 2 * Math.PI);
            ctx.fillStyle = 'rgba(102, 126, 234, 0.3)';
            ctx.fill();
        }
        
        drawPie();
    </script>
</body>
</html>
