<?php
/**
 * CQC Projects Dashboard
 * Dashboard untuk solar panel projects dengan grafik progress
 */
define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

if (!isModuleEnabled('cqc-projects')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

require_once 'db-helper.php';

// Get database connection untuk CQC
try {
    $pdo = getCQCDatabaseConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get project statistics
$stats = [];
try {
    // Total projects
    $result = $pdo->query("SELECT COUNT(*) as count FROM cqc_projects");
    $stats['total'] = (int)$result->fetch()['count'];
    
    // By status
    $result = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM cqc_projects 
        GROUP BY status
    ");
    $stats['by_status'] = [];
    while ($row = $result->fetch()) {
        $stats['by_status'][$row['status']] = (int)$row['count'];
    }
    
    // Total budget vs spent
    $result = $pdo->query("
        SELECT 
            SUM(budget_idr) as total_budget,
            SUM(spent_idr) as total_spent
        FROM cqc_projects
    ");
    $budget = $result->fetch();
    $stats['total_budget'] = (float)($budget['total_budget'] ?? 0);
    $stats['total_spent'] = (float)($budget['total_spent'] ?? 0);
    $stats['remaining'] = $stats['total_budget'] - $stats['total_spent'];
    
    // Active projects (ongoing + installation)
    $result = $pdo->query("
        SELECT COUNT(*) as count 
        FROM cqc_projects 
        WHERE status IN ('procurement', 'installation', 'testing')
    ");
    $stats['active'] = (int)$result->fetch()['count'];
    
    // Average progress
    $result = $pdo->query("
        SELECT AVG(progress_percentage) as avg_progress 
        FROM cqc_projects 
        WHERE status != 'planning'
    ");
    $progress = $result->fetch();
    $stats['avg_progress'] = (int)($progress['avg_progress'] ?? 0);
    
} catch (Exception $e) {
    // Table might not exist yet
    $stats = [
        'total' => 0,
        'by_status' => [],
        'total_budget' => 0,
        'total_spent' => 0,
        'remaining' => 0,
        'active' => 0,
        'avg_progress' => 0
    ];
}

// Get running projects (untuk quick view)
$running_projects = [];
try {
    $stmt = $pdo->query("
        SELECT id, project_name, client_name, status, progress_percentage, 
               budget_idr, spent_idr, start_date, estimated_completion
        FROM cqc_projects
        WHERE status IN ('procurement', 'installation', 'testing')
        ORDER BY progress_percentage DESC
        LIMIT 5
    ");
    $running_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table doesn't exist
}

$pageTitle = "CQC Projects Dashboard";
$pageSubtitle = "Solar Panel Installation Project Management";

$additionalCSS = [];
$inlineStyles = '<style>
/* Chart.js */
</style>';

include '../../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<style>
        .cqc-container { max-width: 1400px; }

        /* Header - compact dark gray */
        .cqc-header {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            color: #f7fafc;
            padding: 16px 24px;
            border-radius: 10px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cqc-header h1 { font-size: 17px; margin-bottom: 2px; font-weight: 700; color: #f7fafc; }
        .cqc-header p { opacity: 0.65; font-size: 12px; margin: 0; }
        .cqc-header button {
            background: #e2e8f0; color: #1a202c; border: none;
            padding: 8px 18px; border-radius: 6px; font-weight: 600;
            cursor: pointer; font-size: 12px; transition: all 0.2s;
        }
        .cqc-header button:hover { background: #cbd5e0; }

        /* Stats - compact */
        .cqc-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        .cqc-stat-card {
            background: var(--bg-secondary, white);
            padding: 14px 16px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            border-left: 3px solid #4a5568;
            transition: all 0.2s ease;
        }
        .cqc-stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .cqc-stat-card.yellow { border-left-color: #d69e2e; }
        .cqc-stat-card.green { border-left-color: #38a169; }
        .cqc-stat-card.red { border-left-color: #e53e3e; }

        .cqc-stat-icon { font-size: 20px; margin-bottom: 6px; }
        .cqc-stat-label { font-size: 10px; color: var(--text-muted, #a0aec0); margin-bottom: 4px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .cqc-stat-value { font-size: 22px; font-weight: 800; color: #2d3748; }
        .cqc-stat-card.yellow .cqc-stat-value { color: #b7791f; }
        .cqc-stat-card.green .cqc-stat-value { color: #276749; }
        .cqc-stat-card.red .cqc-stat-value { color: #c53030; }
        .cqc-stat-subtitle { font-size: 10px; color: var(--text-muted, #a0aec0); margin-top: 4px; }

        /* Charts - compact glassmorphism */
        .cqc-charts-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        .cqc-chart-card {
            background: var(--bg-secondary, rgba(255,255,255,0.92));
            backdrop-filter: blur(12px);
            padding: 16px;
            border-radius: 10px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06);
            border: 1px solid var(--bg-tertiary, rgba(0,0,0,0.06));
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .cqc-chart-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, #718096, #4a5568, #a0aec0);
            background-size: 300% 100%;
            animation: cqcShimmer 4s ease infinite;
        }
        @keyframes cqcShimmer {
            0%,100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        .cqc-chart-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .cqc-chart-title {
            font-size: 11px; font-weight: 700; color: #4a5568;
            margin-bottom: 12px; display: flex; align-items: center;
            gap: 6px; letter-spacing: 0.5px; text-transform: uppercase;
        }
        .cqc-chart-canvas { max-height: 200px; }

        /* Section title */
        .cqc-section-title {
            font-size: 14px; font-weight: 700; color: #2d3748;
            margin: 16px 0 12px; padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        /* Table - compact */
        .cqc-projects-table {
            background: var(--bg-secondary, white);
            border-radius: 8px; overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .cqc-projects-table table { width: 100%; border-collapse: collapse; }
        .cqc-projects-table th {
            background: #2d3748; color: #e2e8f0;
            padding: 10px 14px; text-align: left;
            font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px;
        }
        .cqc-projects-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--bg-tertiary, #edf2f7);
            font-size: 13px; color: var(--text-primary, #2d3748);
        }
        .cqc-projects-table tr:hover { background: var(--bg-tertiary, #f7fafc); }

        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-planning { background: #ebf4ff; color: #2b6cb0; }
        .status-procurement { background: #fefcbf; color: #975a16; }
        .status-installation { background: #e6fffa; color: #285e61; }
        .status-testing { background: #f0fff4; color: #276749; }
        .status-completed { background: #c6f6d5; color: #22543d; }
        .status-on_hold { background: #fed7d7; color: #9b2c2c; }

        .cqc-progress-bar { width: 100%; height: 6px; background: var(--bg-tertiary, #edf2f7); border-radius: 3px; overflow: hidden; margin-bottom: 3px; }
        .cqc-progress-fill { height: 100%; background: linear-gradient(90deg, #4a5568, #718096); border-radius: 3px; }
        .cqc-progress-text { font-size: 11px; color: var(--text-muted, #a0aec0); }

        .cqc-action-links { display: flex; gap: 6px; }
        .cqc-action-links a {
            padding: 4px 10px; background: #4a5568; color: #f7fafc;
            border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 500;
        }
        .cqc-action-links a:hover { background: #2d3748; }

        .cqc-empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted, #a0aec0); }
        .cqc-empty-state-icon { font-size: 48px; margin-bottom: 12px; }
        .cqc-empty-state h3 { color: #2d3748; margin-bottom: 6px; font-size: 16px; }

        @media (max-width: 900px) {
            .cqc-stats-grid { grid-template-columns: repeat(2, 1fr); }
            .cqc-charts-section { grid-template-columns: 1fr; }
        }
</style>

    <div class="cqc-container">
        <div class="cqc-header">
            <div>
                <h1>☀️ Dashboard Proyek CQC</h1>
                <p>Solar Panel Installation Project Management</p>
            </div>
            <button onclick="location.href='add.php'">+ Proyek Baru</button>
        </div>

        <div class="cqc-stats-grid">
            <div class="cqc-stat-card">
                <div class="cqc-stat-icon">📋</div>
                <div class="cqc-stat-label">Total Proyek</div>
                <div class="cqc-stat-value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="cqc-stat-card yellow">
                <div class="cqc-stat-icon">⚡</div>
                <div class="cqc-stat-label">Proyek Berjalan</div>
                <div class="cqc-stat-value"><?php echo $stats['active']; ?></div>
                <div class="cqc-stat-subtitle">Procurement, Installation, Testing</div>
            </div>
            <div class="cqc-stat-card green">
                <div class="cqc-stat-icon">✅</div>
                <div class="cqc-stat-label">Rata-rata Progress</div>
                <div class="cqc-stat-value"><?php echo $stats['avg_progress']; ?>%</div>
            </div>
            <div class="cqc-stat-card red">
                <div class="cqc-stat-icon">💰</div>
                <div class="cqc-stat-label">Total Pengeluaran</div>
                <div class="cqc-stat-value">Rp <?php echo number_format($stats['total_spent'], 0); ?></div>
                <div class="cqc-stat-subtitle">dari Rp <?php echo number_format($stats['total_budget'], 0); ?></div>
            </div>
        </div>

        <div class="cqc-charts-section">
            <div class="cqc-chart-card">
                <div class="cqc-chart-title">📈 Distribusi Status</div>
                <canvas id="statusChart" class="cqc-chart-canvas"></canvas>
            </div>
            <div class="cqc-chart-card">
                <div class="cqc-chart-title">💵 Budget vs Pengeluaran</div>
                <canvas id="budgetChart" class="cqc-chart-canvas"></canvas>
            </div>
            <div class="cqc-chart-card">
                <div class="cqc-chart-title">⏳ Progress Rata-rata</div>
                <canvas id="progressChart" class="cqc-chart-canvas"></canvas>
            </div>
        </div>

        <div class="cqc-section-title">⚡ Proyek Sedang Berjalan</div>
        
        <?php if (!empty($running_projects)): ?>
            <div class="cqc-projects-table">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Proyek</th>
                            <th>Klien</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Budget</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($running_projects as $proj): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($proj['project_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($proj['client_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $proj['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $proj['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="cqc-progress-bar">
                                        <div class="cqc-progress-fill" style="width: <?php echo $proj['progress_percentage']; ?>%"></div>
                                    </div>
                                    <div class="cqc-progress-text"><?php echo $proj['progress_percentage']; ?>%</div>
                                </td>
                                <td>
                                    <div style="font-size: 12px; color: var(--text-muted, #666);">
                                        Rp <?php echo number_format($proj['spent_idr'] ?? 0, 0); ?> / 
                                        Rp <?php echo number_format($proj['budget_idr'] ?? 0, 0); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="cqc-action-links">
                                        <a href="detail.php?id=<?php echo $proj['id']; ?>">Lihat</a>
                                        <a href="add.php?id=<?php echo $proj['id']; ?>">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cqc-empty-state">
                <div class="cqc-empty-state-icon">📭</div>
                <h3>Tidak Ada Proyek Sedang Berjalan</h3>
                <p>Mulai dengan membuat proyek baru untuk instalasi panel surya.</p>
                <button style="background: #0066CC; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 20px;" onclick="location.href='add.php'">
                    ➕ Buat Proyek Baru
                </button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // === Center Text Plugin (futuristic doughnut center) ===
        const centerTextPlugin = {
            id: 'centerText',
            afterDraw(chart) {
                if (!chart.config.options.plugins.centerText) return;
                const { text, subtext, color } = chart.config.options.plugins.centerText;
                const { ctx, chartArea: { left, right, top, bottom } } = chart;
                const cx = (left + right) / 2;
                const cy = (top + bottom) / 2;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                if (text) {
                    ctx.font = 'bold 22px -apple-system, BlinkMacSystemFont, sans-serif';
                    ctx.fillStyle = color || '#2d3748';
                    ctx.fillText(text, cx, subtext ? cy - 8 : cy);
                }
                if (subtext) {
                    ctx.font = '600 9px -apple-system, BlinkMacSystemFont, sans-serif';
                    ctx.fillStyle = '#a0aec0';
                    ctx.fillText(subtext, cx, cy + 14);
                }
                ctx.restore();
            }
        };
        Chart.register(centerTextPlugin);

        // === Shared tooltip style ===
        const cqcTooltip = {
            backgroundColor: 'rgba(26, 32, 44, 0.95)',
            titleColor: '#e2e8f0',
            bodyColor: '#f7fafc',
            borderColor: 'rgba(74, 85, 104, 0.4)',
            borderWidth: 1,
            cornerRadius: 8,
            padding: 10,
            titleFont: { size: 11, weight: '600' },
            bodyFont: { size: 12, weight: '700' },
            displayColors: true,
            boxPadding: 4
        };

        // === Gradient helper ===
        function cqcGrad(ctx, c1, c2) {
            const g = ctx.createLinearGradient(0, 0, 0, 250);
            g.addColorStop(0, c1);
            g.addColorStop(1, c2);
            return g;
        }

        // ── Status Chart ──
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            const sData = [
                <?php echo $stats['by_status']['planning'] ?? 0; ?>,
                <?php echo $stats['by_status']['procurement'] ?? 0; ?>,
                <?php echo $stats['by_status']['installation'] ?? 0; ?>,
                <?php echo $stats['by_status']['testing'] ?? 0; ?>,
                <?php echo $stats['by_status']['completed'] ?? 0; ?>,
                <?php echo $stats['by_status']['on_hold'] ?? 0; ?>
            ];
            const sTotal = sData.reduce((a,b) => a+b, 0);

            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Planning', 'Procurement', 'Installation', 'Testing', 'Completed', 'On Hold'],
                    datasets: [{
                        data: sData,
                        backgroundColor: ['#4a5568','#d69e2e','#718096','#38a169','#2d3748','#c53030'],
                        hoverBackgroundColor: ['#718096','#ecc94b','#a0aec0','#48bb78','#4a5568','#fc8181'],
                        borderWidth: 0,
                        spacing: 2,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '70%',
                    plugins: {
                        centerText: { text: sTotal.toString(), subtext: 'PROYEK', color: '#2d3748' },
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, pointStyle: 'circle', padding: 10, font: { size: 10, weight: '600' }, color: '#718096' }
                        },
                        tooltip: cqcTooltip
                    },
                    animation: { animateRotate: true, duration: 800, easing: 'easeOutQuart' }
                }
            });
        }

        // ── Budget Chart ──
        const budgetCtx = document.getElementById('budgetChart');
        if (budgetCtx) {
            const ctx2d = budgetCtx.getContext('2d');
            new Chart(budgetCtx, {
                type: 'bar',
                data: {
                    labels: ['Budget', 'Terpakai', 'Sisa'],
                    datasets: [{
                        label: 'Rp',
                        data: [
                            <?php echo $stats['total_budget']; ?>,
                            <?php echo $stats['total_spent']; ?>,
                            <?php echo $stats['remaining']; ?>
                        ],
                        backgroundColor: [
                            cqcGrad(ctx2d, '#4a5568', '#2d3748'),
                            cqcGrad(ctx2d, '#d69e2e', '#b7791f'),
                            cqcGrad(ctx2d, '#38a169', '#276749')
                        ],
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.55,
                        categoryPercentage: 0.7
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...cqcTooltip,
                            callbacks: { label: function(ctx) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.x); } }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                            ticks: {
                                color: '#a0aec0', font: { size: 10, weight: '500' },
                                callback: function(v) { return v >= 1e9 ? 'Rp '+(v/1e9).toFixed(1)+'B' : 'Rp '+(v/1e6).toFixed(0)+'M'; }
                            }
                        },
                        y: { grid: { display: false }, ticks: { color: '#4a5568', font: { size: 11, weight: '600' } } }
                    },
                    animation: { duration: 800, easing: 'easeOutQuart' }
                }
            });
        }

        // ── Progress Chart ──
        const progressCtx = document.getElementById('progressChart');
        if (progressCtx) {
            const pVal = <?php echo $stats['avg_progress']; ?>;
            new Chart(progressCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Selesai', 'Tersisa'],
                    datasets: [{
                        data: [pVal, 100 - pVal],
                        backgroundColor: [
                            (function(){
                                const g = progressCtx.getContext('2d').createLinearGradient(0,0,250,250);
                                g.addColorStop(0, '#2d3748');
                                g.addColorStop(1, '#4a5568');
                                return g;
                            })(),
                            'rgba(160, 174, 192, 0.15)'
                        ],
                        borderWidth: 0,
                        spacing: 2,
                        borderRadius: 12
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '75%',
                    plugins: {
                        centerText: { text: pVal + '%', subtext: 'PROGRESS', color: '#2d3748' },
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, pointStyle: 'circle', padding: 10, font: { size: 10, weight: '600' }, color: '#718096' }
                        },
                        tooltip: {
                            ...cqcTooltip,
                            callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed + '%'; } }
                        }
                    },
                    animation: { animateRotate: true, duration: 1000, easing: 'easeOutQuart' }
                }
            });
        }
    </script>

<?php include '../../includes/footer.php'; ?>
