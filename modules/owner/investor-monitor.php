<?php
/**
 * INVESTOR & PROJECT MONITOR
 * Mobile-optimized project monitoring for owner
 * Clean, Compact, Modern
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

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
$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false);
$basePath = $isProduction ? '' : '/adf_system';

// Initialize variables
$investors = [];
$projects = [];
$totalCapital = 0;
$totalBudget = 0;
$totalExpenses = 0;
$projectExpenses = [];
$selectedProject = null;
$selectedProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

try {
    // Get all investors
    $investors = $db->fetchAll("SELECT * FROM investors ORDER BY total_capital DESC");
    foreach ($investors as $inv) {
        $totalCapital += $inv['total_capital'] ?? 0;
    }
    
    // Get all projects
    $projects = $db->fetchAll("SELECT * FROM projects ORDER BY budget DESC");
    foreach ($projects as $proj) {
        $totalBudget += $proj['budget'] ?? 0;
        $totalExpenses += $proj['total_expenses'] ?? 0;
    }
    
    // If a project is selected, get its details and expenses
    if ($selectedProjectId) {
        $stmt = $db->getConnection()->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$selectedProjectId]);
        $selectedProject = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($selectedProject) {
            // Get project expenses
            try {
                $expStmt = $db->getConnection()->prepare("
                    SELECT pe.*, pec.category_name 
                    FROM project_expenses pe 
                    LEFT JOIN project_expense_categories pec ON pe.expense_category_id = pec.id
                    WHERE pe.project_id = ? 
                    ORDER BY pe.expense_date DESC
                    LIMIT 20
                ");
                $expStmt->execute([$selectedProjectId]);
                $projectExpenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Fallback
                $expStmt = $db->getConnection()->prepare("SELECT * FROM project_expenses WHERE project_id = ? ORDER BY expense_date DESC LIMIT 20");
                $expStmt->execute([$selectedProjectId]);
                $projectExpenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

function rp($num) {
    if ($num >= 1000000000) {
        return 'Rp ' . number_format($num / 1000000000, 1, ',', '.') . 'M';
    } elseif ($num >= 1000000) {
        return 'Rp ' . number_format($num / 1000000, 1, ',', '.') . 'Jt';
    } else {
        return 'Rp ' . number_format($num, 0, ',', '.');
    }
}

function rpFull($num) {
    return 'Rp ' . number_format($num, 0, ',', '.');
}

$usagePercent = $totalBudget > 0 ? round(($totalExpenses / $totalBudget) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Projects & Investors - Owner</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --bg: #0f172a;
            --card: rgba(255,255,255,0.08);
            --card-border: rgba(255,255,255,0.1);
            --text: #ffffff;
            --text-muted: rgba(255,255,255,0.6);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
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
            text-align: center;
            padding: 20px 0;
            margin-bottom: 16px;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .header-subtitle {
            font-size: 11px;
            color: var(--text-muted);
        }
        
        /* Overview Cards */
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .overview-card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
        }
        
        .overview-card.highlight {
            grid-column: span 2;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border: none;
        }
        
        .overview-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 6px;
        }
        
        .overview-card.highlight .overview-label {
            color: rgba(255,255,255,0.8);
        }
        
        .overview-value {
            font-size: 20px;
            font-weight: 800;
        }
        
        .overview-hint {
            font-size: 9px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        /* Progress Bar */
        .progress-container {
            margin-bottom: 16px;
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 14px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .progress-label {
            font-size: 11px;
            font-weight: 600;
        }
        
        .progress-percent {
            font-size: 13px;
            font-weight: 800;
            color: <?= $usagePercent < 70 ? 'var(--success)' : ($usagePercent < 90 ? 'var(--warning)' : 'var(--danger)') ?>;
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: <?= $usagePercent < 70 ? 'var(--success)' : ($usagePercent < 90 ? 'var(--warning)' : 'var(--danger)') ?>;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        /* Section Title */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Investor List */
        .investor-list {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        
        .investor-item {
            padding: 12px 14px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .investor-item:last-child {
            border-bottom: none;
        }
        
        .investor-name {
            font-size: 13px;
            font-weight: 600;
        }
        
        .investor-id {
            font-size: 10px;
            color: var(--text-muted);
        }
        
        .investor-capital {
            font-size: 13px;
            font-weight: 700;
            color: var(--success);
        }
        
        /* Project List */
        .project-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .project-card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 14px;
            text-decoration: none;
            color: var(--text);
            display: block;
            transition: transform 0.2s;
        }
        
        .project-card:active {
            transform: scale(0.98);
        }
        
        .project-card.active {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.15);
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .project-name {
            font-size: 14px;
            font-weight: 700;
        }
        
        .project-status {
            font-size: 8px;
            padding: 3px 8px;
            border-radius: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .project-status.active { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .project-status.planning { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .project-status.completed { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        
        .project-stats {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }
        
        .project-stat-label {
            color: var(--text-muted);
        }
        
        .project-stat-value {
            font-weight: 600;
        }
        
        .project-stat-value.budget { color: var(--info); }
        .project-stat-value.expense { color: var(--danger); }
        
        /* Expense List */
        .expense-list {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        
        .expense-item {
            padding: 12px 14px;
            border-bottom: 1px solid var(--card-border);
            border-left: 3px solid var(--danger);
        }
        
        .expense-item:last-child {
            border-bottom: none;
        }
        
        .expense-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }
        
        .expense-desc {
            font-size: 12px;
            font-weight: 600;
            flex: 1;
            margin-right: 12px;
        }
        
        .expense-amount {
            font-size: 12px;
            font-weight: 700;
            color: var(--danger);
            white-space: nowrap;
        }
        
        .expense-meta {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: var(--text-muted);
        }
        
        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
            padding: 8px 12px;
            background: var(--card);
            border-radius: 8px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-size: 12px;
        }
        
        /* Footer Nav */
        .nav-bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.3);
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: rgba(255,255,255,0.5);
            font-size: 10px;
            font-weight: 600;
            transition: color 0.2s;
        }
        
        .nav-item.active {
            color: #a78bfa;
        }
        
        .nav-icon {
            font-size: 20px;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <?php if ($selectedProject): ?>
        <!-- Project Detail View -->
        <a href="investor-monitor.php" class="back-btn">← Back</a>
        
        <div class="header">
            <div class="header-title">📊 <?= htmlspecialchars($selectedProject['project_name']) ?></div>
            <div class="header-subtitle">Project expense details</div>
        </div>
        
        <!-- Project Stats -->
        <div class="overview-grid">
            <div class="overview-card">
                <div class="overview-label">💰 Budget</div>
                <div class="overview-value" style="color: var(--info);"><?= rp($selectedProject['budget'] ?? 0) ?></div>
            </div>
            <div class="overview-card">
                <div class="overview-label">💸 Spent</div>
                <div class="overview-value" style="color: var(--danger);"><?= rp($selectedProject['total_expenses'] ?? 0) ?></div>
            </div>
            <div class="overview-card highlight">
                <div class="overview-label">🏦 Remaining Budget</div>
                <div class="overview-value"><?= rp(($selectedProject['budget'] ?? 0) - ($selectedProject['total_expenses'] ?? 0)) ?></div>
            </div>
        </div>
        
        <!-- Progress -->
        <?php 
        $projectUsage = ($selectedProject['budget'] ?? 0) > 0 
            ? round((($selectedProject['total_expenses'] ?? 0) / ($selectedProject['budget'] ?? 1)) * 100) 
            : 0;
        ?>
        <div class="progress-container">
            <div class="progress-header">
                <span class="progress-label">Budget Usage</span>
                <span class="progress-percent" style="color: <?= $projectUsage < 70 ? 'var(--success)' : ($projectUsage < 90 ? 'var(--warning)' : 'var(--danger)') ?>"><?= $projectUsage ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= min($projectUsage, 100) ?>%; background: <?= $projectUsage < 70 ? 'var(--success)' : ($projectUsage < 90 ? 'var(--warning)' : 'var(--danger)') ?>"></div>
            </div>
        </div>
        
        <!-- Expenses -->
        <div class="section-title">📋 Expense History (<?= count($projectExpenses) ?>)</div>
        <div class="expense-list">
            <?php if (!empty($projectExpenses)): ?>
                <?php foreach ($projectExpenses as $exp): ?>
                <div class="expense-item">
                    <div class="expense-header">
                        <div class="expense-desc"><?= htmlspecialchars($exp['description'] ?? '-') ?></div>
                        <div class="expense-amount"><?= rpFull($exp['amount_idr'] ?? $exp['amount'] ?? 0) ?></div>
                    </div>
                    <div class="expense-meta">
                        <span><?= $exp['category_name'] ?? 'General' ?></span>
                        <span><?= date('d M Y', strtotime($exp['expense_date'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No expenses yet</div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Overview View -->
        <div class="header">
            <div class="header-title">📊 Projects & Investors</div>
            <div class="header-subtitle">Project financial monitoring</div>
        </div>
        
        <!-- Overview Stats -->
        <div class="overview-grid">
            <div class="overview-card">
                <div class="overview-label">👥 Total Investor Capital</div>
                <div class="overview-value" style="color: var(--success);"><?= rp($totalCapital) ?></div>
                <div class="overview-hint"><?= count($investors) ?> investors</div>
            </div>
            <div class="overview-card">
                <div class="overview-label">📁 Total Project Budget</div>
                <div class="overview-value" style="color: var(--info);"><?= rp($totalBudget) ?></div>
                <div class="overview-hint"><?= count($projects) ?> projects</div>
            </div>
            <div class="overview-card highlight">
                <div class="overview-label">💸 Total Expenses</div>
                <div class="overview-value"><?= rp($totalExpenses) ?></div>
                <div class="overview-hint">All projects</div>
            </div>
        </div>
        
        <!-- Progress -->
        <div class="progress-container">
            <div class="progress-header">
                <span class="progress-label">Penggunaan Budget Total</span>
                <span class="progress-percent"><?= $usagePercent ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= min($usagePercent, 100) ?>%"></div>
            </div>
        </div>
        
        <!-- Investors -->
        <div class="section-title">👤 Daftar Investor (<?= count($investors) ?>)</div>
        <div class="investor-list">
            <?php if (!empty($investors)): ?>
                <?php foreach ($investors as $inv): ?>
                <div class="investor-item">
                    <div>
                        <div class="investor-name"><?= htmlspecialchars($inv['investor_name'] ?? '-') ?></div>
                        <div class="investor-id">ID: <?= $inv['id'] ?></div>
                    </div>
                    <div class="investor-capital"><?= rp($inv['total_capital'] ?? 0) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">Belum ada investor terdaftar</div>
            <?php endif; ?>
        </div>
        
        <!-- Projects -->
        <div class="section-title">📁 Daftar Proyek (<?= count($projects) ?>)</div>
        <div class="project-list">
            <?php if (!empty($projects)): ?>
                <?php foreach ($projects as $proj): ?>
                <a href="?project_id=<?= $proj['id'] ?>" class="project-card">
                    <div class="project-header">
                        <div class="project-name"><?= htmlspecialchars($proj['project_name'] ?? '-') ?></div>
                        <span class="project-status <?= strtolower($proj['status'] ?? 'active') ?>">
                            <?= ucfirst($proj['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    <div class="project-stats">
                        <div>
                            <span class="project-stat-label">Budget: </span>
                            <span class="project-stat-value budget"><?= rp($proj['budget'] ?? 0) ?></span>
                        </div>
                        <div>
                            <span class="project-stat-label">Terpakai: </span>
                            <span class="project-stat-value expense"><?= rp($proj['total_expenses'] ?? 0) ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">Belum ada proyek terdaftar</div>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
        
    </div>
    
    <!-- Footer Nav -->
    <nav class="nav-bottom">
        <a href="<?= $basePath ?>/modules/owner/dashboard-2028.php" class="nav-item">
            <span class="nav-icon">🏠</span>
            <span>Home</span>
        </a>
        <a href="<?= $basePath ?>/modules/owner/frontdesk-mobile.php" class="nav-item">
            <span class="nav-icon">📋</span>
            <span>Frontdesk</span>
        </a>
        <a href="<?= $basePath ?>/modules/owner/investor-monitor.php" class="nav-item active">
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
