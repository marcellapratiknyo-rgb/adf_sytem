<?php
/**
 * Create Business Database - Setup Tool
 * Step 1: User creates DB manually in cPanel
 * Step 2: This tool runs the business template SQL
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';

$results = [];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Auto-detect cPanel username from DB_USER
$defaultCpanelUser = '';
if (defined('DB_USER')) {
    $parts = explode('_', DB_USER);
    if (count($parts) >= 2) {
        $defaultCpanelUser = $parts[0];
    }
}

// Get existing databases
$existingDbs = [];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME LIKE '{$defaultCpanelUser}%' ORDER BY SCHEMA_NAME");
    $existingDbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $results[] = ['type' => 'error', 'msg' => 'Cannot list databases: ' . $e->getMessage()];
}

// Get pending businesses (registered but DB doesn't exist)
$pendingBusinesses = [];
$allBusinesses = [];
try {
    if (isset($pdo)) {
        $bizStmt = $pdo->query("SELECT id, business_name, database_name, business_code FROM businesses ORDER BY id");
        while ($biz = $bizStmt->fetch(PDO::FETCH_ASSOC)) {
            $biz['db_exists'] = in_array($biz['database_name'], $existingDbs);
            $allBusinesses[] = $biz;
            if (!$biz['db_exists']) {
                $pendingBusinesses[] = $biz;
            }
        }
    }
} catch (Exception $e) {}

// ACTION: Run template on existing database
if ($action === 'run_template') {
    $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', trim($_POST['db_name'] ?? ''));
    
    if (empty($dbName)) {
        $results[] = ['type' => 'error', 'msg' => 'Database name is required'];
    } elseif (!in_array($dbName, $existingDbs)) {
        $results[] = ['type' => 'error', 'msg' => "Database '{$dbName}' does not exist yet. Create it first in cPanel (see Step 1 below)."];
    } else {
        $templatePath = __DIR__ . '/database/business_template.sql';
        if (!file_exists($templatePath)) {
            $results[] = ['type' => 'error', 'msg' => 'Template file not found: database/business_template.sql'];
        } else {
            try {
                $newPdo = new PDO("mysql:host=" . DB_HOST . ";dbname={$dbName}", DB_USER, DB_PASS);
                $newPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if tables already exist
                $tables = $newPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (count($tables) > 0) {
                    $results[] = ['type' => 'warning', 'msg' => "Database '{$dbName}' already has " . count($tables) . " tables: " . implode(', ', $tables) . ". Skipping template to avoid duplicates."];
                } else {
                    $sql = file_get_contents($templatePath);
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    $executed = 0;
                    foreach ($statements as $statement) {
                        if (!empty($statement) && strpos($statement, '--') !== 0) {
                            $newPdo->exec($statement);
                            $executed++;
                        }
                    }
                    $results[] = ['type' => 'success', 'msg' => "Business template executed successfully! {$executed} statements in '{$dbName}'. Database is ready to use."];
                }
            } catch (PDOException $e) {
                $results[] = ['type' => 'error', 'msg' => "Failed to run template: " . $e->getMessage() . 
                    ". Make sure user '" . DB_USER . "' has ALL PRIVILEGES on '{$dbName}' (see Step 1c)."];
            }
        }
    }
}

// ACTION: Check if DB exists now (AJAX-like check)
if ($action === 'check') {
    $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', trim($_GET['db'] ?? ''));
    header('Content-Type: application/json');
    echo json_encode(['exists' => in_array($dbName, $existingDbs)]);
    exit;
}

$cpanelUrl = 'https://guangmao.iixcp.rumahweb.net:2083';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Business Database</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; padding: 1.5rem; color: #1e293b; }
        .container { max-width: 750px; margin: 0 auto; }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; }
        .card { background: #fff; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h2 { font-size: 1.1rem; margin-bottom: 0.8rem; color: #334155; }
        .step { display: flex; gap: 0.8rem; margin-bottom: 1rem; }
        .step-num { background: #3b82f6; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; margin-top: 2px; }
        .step-content { flex: 1; }
        .step-content p { font-size: 0.9rem; line-height: 1.5; }
        .step-content code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; color: #dc2626; font-weight: 600; }
        a.btn { display: inline-block; background: #3b82f6; color: #fff; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        a.btn:hover { background: #2563eb; }
        a.btn-cpanel { background: #f97316; }
        a.btn-cpanel:hover { background: #ea580c; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.3rem; }
        select, input[type=text] { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; margin-bottom: 0.8rem; }
        select:focus, input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        button { background: #16a34a; color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 8px; font-size: 0.9rem; cursor: pointer; font-weight: 600; }
        button:hover { background: #15803d; }
        .result { padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; font-size: 0.85rem; }
        .result.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .result.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .result.warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .pending { background: #fef3c7; padding: 0.6rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; font-size: 0.85rem; border: 1px solid #fde68a; }
        .ready { background: #dcfce7; padding: 0.6rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; font-size: 0.85rem; border: 1px solid #bbf7d0; }
        .db-list { font-size: 0.8rem; color: #64748b; }
        .db-list span { display: inline-block; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; margin: 2px; }
        .divider { border-top: 1px solid #e2e8f0; margin: 1rem 0; }
        .info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; font-size: 0.85rem; color: #1e40af; }
    </style>
</head>
<body>
<div class="container">
    <h1>🗄️ Setup Business Database</h1>
    
    <?php foreach ($results as $r): ?>
        <div class="result <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
    <?php endforeach; ?>
    
    <?php if (!empty($pendingBusinesses)): ?>
    <div class="card">
        <h2>⚠️ Businesses Missing Database</h2>
        <?php foreach ($pendingBusinesses as $pb): ?>
            <div class="pending">
                <strong><?= htmlspecialchars($pb['business_name']) ?></strong> — 
                needs DB: <code><?= htmlspecialchars($pb['database_name']) ?></code>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- STEP 1: Manual cPanel creation -->
    <div class="card">
        <h2>Step 1: Create Database in cPanel</h2>
        <div class="info-box">
            On shared hosting (Rumahweb), databases can only be created through cPanel's MySQL interface. Follow these steps:
        </div>
        
        <div class="step">
            <div class="step-num">a</div>
            <div class="step-content">
                <p>Open cPanel MySQL Databases:</p>
                <p style="margin-top:0.5rem"><a href="<?= $cpanelUrl ?>/" target="_blank" class="btn btn-cpanel">Open cPanel ↗</a></p>
                <p style="margin-top:0.3rem; font-size:0.8rem; color:#94a3b8;">Then go to: Databases → MySQL Databases</p>
            </div>
        </div>
        
        <div class="step">
            <div class="step-num">b</div>
            <div class="step-content">
                <p>Under <strong>"Create New Database"</strong>, enter the database name:</p>
                <?php foreach ($pendingBusinesses as $pb): ?>
                    <p style="margin:0.3rem 0;"><code><?= htmlspecialchars($pb['database_name']) ?></code> — for <?= htmlspecialchars($pb['business_name']) ?></p>
                <?php endforeach; ?>
                <p style="font-size:0.8rem; color:#94a3b8; margin-top:0.3rem;">
                    Note: cPanel will show a prefix box. Just type what comes after "<?= $defaultCpanelUser ?>_" — for example, if DB name is <code><?= $defaultCpanelUser ?>_demo</code>, just type <strong>demo</strong>
                </p>
            </div>
        </div>
        
        <div class="step">
            <div class="step-num">c</div>
            <div class="step-content">
                <p>Under <strong>"Add User to Database"</strong>, select:</p>
                <p>User: <code><?= DB_USER ?></code> → Database: <code>(the new one)</code></p>
                <p>Then check <strong>"ALL PRIVILEGES"</strong> and click Make Changes.</p>
            </div>
        </div>
    </div>
    
    <!-- STEP 2: Run template -->
    <div class="card">
        <h2>Step 2: Initialize Database (Run Template)</h2>
        <p style="font-size:0.85rem; color:#64748b; margin-bottom:1rem;">After creating the database in cPanel, select it below to populate it with business tables.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="run_template">
            
            <label>Select Database</label>
            <select name="db_name" required>
                <option value="">-- Select database --</option>
                <?php foreach ($pendingBusinesses as $pb): ?>
                    <option value="<?= htmlspecialchars($pb['database_name']) ?>" 
                        <?= in_array($pb['database_name'], $existingDbs) ? '' : 'style="color:#dc2626"' ?>>
                        <?= htmlspecialchars($pb['database_name']) ?> (<?= htmlspecialchars($pb['business_name']) ?>) 
                        <?= in_array($pb['database_name'], $existingDbs) ? '✓ exists' : '✗ not created yet' ?>
                    </option>
                <?php endforeach; ?>
                <optgroup label="All Existing Databases">
                    <?php foreach ($existingDbs as $db): ?>
                        <option value="<?= htmlspecialchars($db) ?>"><?= htmlspecialchars($db) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
            
            <button type="submit">▶ Run Business Template</button>
        </form>
    </div>
    
    <!-- Status -->
    <div class="card">
        <h2>All Businesses</h2>
        <?php foreach ($allBusinesses as $biz): ?>
            <div class="<?= $biz['db_exists'] ? 'ready' : 'pending' ?>">
                <?= $biz['db_exists'] ? '✅' : '❌' ?>
                <strong><?= htmlspecialchars($biz['business_name']) ?></strong> — 
                <code><?= htmlspecialchars($biz['database_name']) ?></code>
                <?= $biz['db_exists'] ? '(Ready)' : '(Needs setup)' ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="card">
        <h2>Existing Databases (<?= count($existingDbs) ?>)</h2>
        <div class="db-list">
            <?php foreach ($existingDbs as $db): ?>
                <span><?= htmlspecialchars($db) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
