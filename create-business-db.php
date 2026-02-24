<?php
/**
 * Create Business Database via cPanel API
 * For shared hosting where CREATE DATABASE SQL is denied
 * Access: https://adfsystem.online/create-business-db.php
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';

// Only allow on production or with dev token
$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
                 strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

$results = [];
$dbName = trim($_POST['db_name'] ?? $_GET['db'] ?? '');
$cpanelUser = trim($_POST['cpanel_user'] ?? '');
$cpanelPass = trim($_POST['cpanel_pass'] ?? '');
$action = $_POST['action'] ?? '';

// Auto-detect cPanel username from DB_USER
$defaultCpanelUser = '';
if (defined('DB_USER')) {
    $parts = explode('_', DB_USER);
    if (count($parts) >= 2) {
        $defaultCpanelUser = $parts[0];
    }
}

// Get existing databases for reference
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
try {
    if (isset($pdo)) {
        $bizStmt = $pdo->query("SELECT id, business_name, database_name, business_code FROM businesses WHERE is_active = 1 ORDER BY id");
        while ($biz = $bizStmt->fetch(PDO::FETCH_ASSOC)) {
            $exists = in_array($biz['database_name'], $existingDbs);
            if (!$exists) {
                $pendingBusinesses[] = $biz;
            }
        }
    }
} catch (Exception $e) {}

if ($action === 'create' && !empty($dbName)) {
    
    // Sanitize DB name
    $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
    
    // Check if already exists
    if (in_array($dbName, $existingDbs)) {
        $results[] = ['type' => 'success', 'msg' => "Database '{$dbName}' already exists!"];
    } else {
        $created = false;
        
        // ============================================================
        // STRATEGY 1: UAPI shell binary (best for shared hosting - NO auth needed)
        // PHP runs as the cPanel user, so uapi works without credentials
        // ============================================================
        $uapiBin = '/usr/local/cpanel/bin/uapi';
        if (!$created && @is_executable($uapiBin)) {
            $cmd = escapeshellcmd($uapiBin) . ' --output=json Mysql create_database name=' . escapeshellarg($dbName) . ' 2>&1';
            $output = @shell_exec($cmd);
            $json = @json_decode($output, true);
            
            if ($json && isset($json['result']['status'])) {
                if ($json['result']['status'] == 1) {
                    $results[] = ['type' => 'success', 'msg' => "Database '{$dbName}' created via UAPI shell!"];
                    $created = true;
                } else {
                    $errMsg = $json['result']['errors'][0] ?? 'Unknown error';
                    if (stripos($errMsg, 'already exists') !== false) {
                        $results[] = ['type' => 'success', 'msg' => "Database '{$dbName}' already exists."];
                        $created = true;
                    } else {
                        $results[] = ['type' => 'warning', 'msg' => "UAPI shell: {$errMsg}"];
                    }
                }
            } else {
                $results[] = ['type' => 'warning', 'msg' => "UAPI shell no JSON response. Output: " . substr($output ?? '(empty)', 0, 300)];
            }
        } elseif (!$created) {
            $results[] = ['type' => 'warning', 'msg' => "UAPI binary not found or not executable at {$uapiBin}"];
        }
        
        // ============================================================
        // STRATEGY 2: cpapi2 shell binary (older cPanel)
        // ============================================================
        $cpapi2Bin = '/usr/local/cpanel/bin/cpapi2';
        if (!$created && @is_executable($cpapi2Bin)) {
            $cmd2 = escapeshellcmd($cpapi2Bin) . ' --output=json MysqlFE createdb db=' . escapeshellarg($dbName) . ' 2>&1';
            $output2 = @shell_exec($cmd2);
            $json2 = @json_decode($output2, true);
            
            if ($json2 && isset($json2['cpanelresult']['data'][0]['result']) && $json2['cpanelresult']['data'][0]['result'] == 1) {
                $results[] = ['type' => 'success', 'msg' => "Database '{$dbName}' created via cpapi2 shell!"];
                $created = true;
            } else {
                $results[] = ['type' => 'warning', 'msg' => "cpapi2 shell: " . substr($output2 ?? '(empty)', 0, 300)];
            }
        }
        
        // ============================================================
        // STRATEGY 3: cPanel UAPI via curl (needs cPanel credentials)
        // Try the actual cPanel hostname, not localhost
        // ============================================================
        if (!$created && !empty($cpanelUser) && !empty($cpanelPass)) {
            // Try multiple hostnames
            $cpanelHosts = [
                'guangmao.iixcp.rumahweb.net:2083',
                'localhost:2083',
                '127.0.0.1:2083',
            ];
            
            foreach ($cpanelHosts as $hostPort) {
                if ($created) break;
                
                $apiUrl = "https://{$hostPort}/execute/Mysql/create_database";
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $apiUrl,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query(['name' => $dbName]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                    CURLOPT_USERPWD => "{$cpanelUser}:{$cpanelPass}",
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $json = json_decode($response, true);
                    if (isset($json['status']) && $json['status'] == 1) {
                        $results[] = ['type' => 'success', 'msg' => "Database '{$dbName}' created via cPanel curl ({$hostPort})!"];
                        $created = true;
                    } elseif (isset($json['errors'])) {
                        $errMsg = is_array($json['errors']) ? implode(', ', $json['errors']) : $json['errors'];
                        if (stripos($errMsg, 'already exists') !== false) {
                            $results[] = ['type' => 'success', 'msg' => "Database '{$dbName}' already exists."];
                            $created = true;
                        } else {
                            $results[] = ['type' => 'warning', 'msg' => "cPanel curl ({$hostPort}): {$errMsg}"];
                        }
                    }
                } else {
                    $results[] = ['type' => 'warning', 'msg' => "cPanel curl ({$hostPort}): HTTP {$httpCode}. {$curlError}"];
                }
            }
        }
        
        // ============================================================
        // STRATEGY 4: Direct SQL (works on VPS/dedicated servers)
        // ============================================================
        if (!$created) {
            try {
                $rootPdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $results[] = ['type' => 'success', 'msg' => "Database '{$dbName}' created via direct SQL!"];
                $created = true;
            } catch (PDOException $e) {
                $results[] = ['type' => 'warning', 'msg' => "Direct SQL: " . $e->getMessage()];
            }
        }
        
        if (!$created) {
            $results[] = ['type' => 'error', 'msg' => "❌ All strategies failed. Please create '{$dbName}' manually in cPanel → MySQL Databases."];
        }
        
        // ============================================================
        // GRANT PRIVILEGES (if DB was created)
        // ============================================================
        if ($created) {
            // Try UAPI shell first
            if (@is_executable($uapiBin)) {
                $grantCmd = escapeshellcmd($uapiBin) . ' --output=json Mysql set_privileges_on_database' .
                    ' user=' . escapeshellarg(DB_USER) .
                    ' database=' . escapeshellarg($dbName) .
                    ' privileges=' . escapeshellarg('ALL PRIVILEGES') . ' 2>&1';
                $grantOut = @shell_exec($grantCmd);
                $grantJson = @json_decode($grantOut, true);
                if ($grantJson && isset($grantJson['result']['status']) && $grantJson['result']['status'] == 1) {
                    $results[] = ['type' => 'success', 'msg' => "Privileges granted to '" . DB_USER . "' via UAPI shell"];
                } else {
                    $results[] = ['type' => 'warning', 'msg' => "UAPI grant: " . substr($grantOut ?? '', 0, 200)];
                }
            }
            // Also try curl if credentials provided
            elseif (!empty($cpanelUser) && !empty($cpanelPass)) {
                $grantUrl = "https://guangmao.iixcp.rumahweb.net:2083/execute/Mysql/set_privileges_on_database";
                $ch3 = curl_init();
                curl_setopt_array($ch3, [
                    CURLOPT_URL => $grantUrl,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'user' => DB_USER,
                        'database' => $dbName,
                        'privileges' => 'ALL PRIVILEGES'
                    ]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                    CURLOPT_USERPWD => "{$cpanelUser}:{$cpanelPass}",
                    CURLOPT_TIMEOUT => 15,
                ]);
                $grantResponse = curl_exec($ch3);
                curl_close($ch3);
                $results[] = ['type' => 'info', 'msg' => "Grant via curl attempted"];
            }
            
            // ============================================================
            // RUN BUSINESS TEMPLATE SQL
            // ============================================================
            $templatePath = __DIR__ . '/database/business_template.sql';
            if (file_exists($templatePath)) {
                // Wait a moment for privileges to propagate
                usleep(500000);
                try {
                    $newPdo = new PDO("mysql:host=" . DB_HOST . ";dbname={$dbName}", DB_USER, DB_PASS);
                    $newPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $sql = file_get_contents($templatePath);
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    $executed = 0;
                    foreach ($statements as $statement) {
                        if (!empty($statement) && strpos($statement, '--') !== 0) {
                            $newPdo->exec($statement);
                            $executed++;
                        }
                    }
                    $results[] = ['type' => 'success', 'msg' => "✅ Business template executed: {$executed} statements in '{$dbName}'"];
                } catch (PDOException $e) {
                    $results[] = ['type' => 'error', 'msg' => "Template execution failed (need privileges?): " . $e->getMessage()];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Business Database</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; padding: 2rem; color: #1e293b; }
        .container { max-width: 700px; margin: 0 auto; }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; }
        .card { background: #fff; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h2 { font-size: 1.1rem; margin-bottom: 1rem; color: #334155; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.3rem; }
        input[type=text], input[type=password] { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; margin-bottom: 0.8rem; }
        input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        button { background: #3b82f6; color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 8px; font-size: 0.9rem; cursor: pointer; font-weight: 600; }
        button:hover { background: #2563eb; }
        .result { padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; font-size: 0.85rem; }
        .result.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .result.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .result.warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .db-list { font-size: 0.8rem; color: #64748b; }
        .db-list span { display: inline-block; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; margin: 2px; }
        .pending { background: #fef3c7; padding: 0.6rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; font-size: 0.85rem; border: 1px solid #fde68a; }
        .pending a { color: #d97706; font-weight: 600; cursor: pointer; }
        .hint { font-size: 0.78rem; color: #94a3b8; margin-bottom: 0.8rem; }
    </style>
</head>
<body>
<div class="container">
    <h1>🗄️ Create Business Database</h1>
    
    <?php foreach ($results as $r): ?>
        <div class="result <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
    <?php endforeach; ?>
    
    <?php if (!empty($pendingBusinesses)): ?>
    <div class="card">
        <h2>⚠️ Businesses Missing Database</h2>
        <?php foreach ($pendingBusinesses as $pb): ?>
            <div class="pending">
                <strong><?= htmlspecialchars($pb['business_name']) ?></strong> — 
                DB: <code><?= htmlspecialchars($pb['database_name']) ?></code>
                <a onclick="document.getElementById('db_name').value='<?= htmlspecialchars($pb['database_name']) ?>'">[Select]</a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>Create New Database</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <label>Database Name</label>
            <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($dbName) ?>" placeholder="e.g. <?= $defaultCpanelUser ?>_newbusiness" required>
            <div class="hint">Must start with "<?= $defaultCpanelUser ?>_" on shared hosting</div>
            
            <label>cPanel Username</label>
            <input type="text" name="cpanel_user" value="<?= htmlspecialchars($cpanelUser ?: $defaultCpanelUser) ?>" required>
            
            <label>cPanel Password</label>
            <input type="password" name="cpanel_pass" value="" placeholder="Only needed if UAPI shell fails (optional)">
            <div class="hint">Usually not needed — UAPI shell works without password on shared hosting.</div>
            
            <button type="submit">Create Database + Run Template</button>
        </form>
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
