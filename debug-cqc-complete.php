<?php
/**
 * COMPLETE DEBUG: CQC Menu Display Issue
 * Check every step of the authentication & permission chain
 */

header('Content-Type: text/html; charset=utf-8');

$isHosting = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
              strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

$dbHost = 'localhost';
$dbUser = $isHosting ? 'adfb2574_adfsystem' : 'root';
$dbPass = $isHosting ? '@Nnoc2025' : '';
$masterDb = $isHosting ? 'adfb2574_adf' : 'adf_system';

echo "<h2>🔍 Complete Debug: CQC Menu Issue</h2>\n";

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$masterDb", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // === STEP 1: Check businesses table ===
    echo "<h3>1️⃣ Checking BUSINESSES table:</h3>\n";
    $biz = $pdo->query("SELECT * FROM businesses WHERE name LIKE '%CQC%' OR id = 7")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($biz)) {
        echo "<table style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f0f0f0;'><th style='padding: 8px; border: 1px solid #ddd;'>ID</th><th style='padding: 8px; border: 1px solid #ddd;'>Name</th><th style='padding: 8px; border: 1px solid #ddd;'>Code</th><th style='padding: 8px; border: 1px solid #ddd;'>Columns</th></tr>\n";
        foreach ($biz as $b) {
            echo "<tr style='border: 1px solid #ddd;'><td style='padding: 8px; border: 1px solid #ddd;'>" . $b['id'] . "</td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($b['name']) . "</td><td style='padding: 8px; border: 1px solid #ddd;'>" . (isset($b['business_code']) ? htmlspecialchars($b['business_code']) : 'N/A') . "</td><td style='padding: 8px; border: 1px solid #ddd;'><code style='font-size: 11px;'>" . implode(', ', array_keys($b)) . "</code></td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "❌ No CQC business found!\n";
    }
    
    // === STEP 2: Check business_menu_config ===
    echo "\n<h3>2️⃣ Checking BUSINESS_MENU_CONFIG assignments for CQC:</h3>\n";
    $bmc = $pdo->query("
        SELECT COUNT(*) as total FROM business_menu_config 
        WHERE business_id = 7
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "Total assignments: <strong>" . $bmc['total'] . "</strong>\n\n";
    
    if ($bmc['total'] > 0) {
        echo "Assigned menus:\n<ul>\n";
        $menus = $pdo->query("
            SELECT m.id, m.menu_name, m.menu_code, bmc.is_enabled
            FROM menu_items m
            JOIN business_menu_config bmc ON bmc.menu_id = m.id
            WHERE bmc.business_id = 7
            ORDER BY m.menu_order
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($menus as $m) {
            $enabled = $m['is_enabled'] ? '✓' : '✗';
            echo "<li>" . htmlspecialchars($m['menu_name']) . " (Code: <code>" . $m['menu_code'] . "</code>) - Enabled: " . $enabled . "</li>\n";
        }
        echo "</ul>\n";
    }
    
    // === STEP 3: Check user_menu_permissions ===
    echo "\n<h3>3️⃣ Checking USER_MENU_PERMISSIONS for CQC business:</h3>\n";
    $perms = $pdo->query("
        SELECT COUNT(*) as total FROM user_menu_permissions 
        WHERE business_id = 7 AND can_view = 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "Total permissions (can_view=1): <strong>" . $perms['total'] . "</strong>\n\n";
    
    // Show per user
    $userPerms = $pdo->query("
        SELECT u.id, u.username, COUNT(*) as perm_count
        FROM users u
        LEFT JOIN user_menu_permissions ump ON u.id = ump.user_id AND ump.business_id = 7 AND ump.can_view = 1
        WHERE u.is_active = 1
        GROUP BY u.id, u.username
        ORDER BY u.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Permissions per user:\n<ul>\n";
    foreach ($userPerms as $up) {
        echo "<li>" . htmlspecialchars($up['username']) . " (ID: " . $up['id'] . "): " . ($up['perm_count'] ?? 0) . " permissions</li>\n";
    }
    echo "</ul>\n";
    
    // === STEP 4: Check businesses table structure ===
    echo "\n<h3>4️⃣ Checking BUSINESSES table structure:</h3>\n";
    $cols = $pdo->query("SHOW COLUMNS FROM businesses")->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>\n";
    foreach ($cols as $col) {
        echo "<li><code>" . $col['Field'] . "</code> - " . $col['Type'] . "</li>\n";
    }
    echo "</ul>\n";
    
    // === STEP 5: Check auth.php mapping ===
    echo "\n<h3>5️⃣ Checking AUTH mapping logic:</h3>\n";
    echo "<p>When auth.php checks permissions, it needs to:</p>\n";
    echo "<ol>\n";
    echo "<li>Get <code>active_business_id</code> from session (this is set during login)</li>\n";
    echo "<li>Map it to <code>business_code</code> (using hardcoded idToCodeMap)</li>\n";
    echo "<li>Query <strong>businesses</strong> table to find matching <code>business_id</code></li>\n";
    echo "<li>Check <strong>user_menu_permissions</strong> with found business_id</li>\n";
    echo "</ol>\n";
    
    echo "<p><strong>Problem:</strong> If active_business_id in session for CQC doesn't match any business_code in the mapping, permissions won't be found!</p>\n";
    
    // === STEP 6: Find what business_code CQC should have ===
    echo "\n<h3>6️⃣ What business_code should CQC use?</h3>\n";
    
    $cqcBiz = $pdo->query("SELECT * FROM businesses WHERE name = 'CQC' OR id = 7")->fetch(PDO::FETCH_ASSOC);
    
    if ($cqcBiz) {
        $suggestedCode = isset($cqcBiz['business_code']) ? $cqcBiz['business_code'] : strtoupper($cqcBiz['name']);
        echo "<p>CQC business_code in database: <strong>" . (isset($cqcBiz['business_code']) ? htmlspecialchars($cqcBiz['business_code']) : 'NOT SET') . "</strong></p>\n";
        echo "<p>Suggested business_code: <strong>" . htmlspecialchars($suggestedCode) . "</strong></p>\n";
        echo "<p>Business ID: <strong>" . $cqcBiz['id'] . "</strong></p>\n";
    }
    
    // === STEP 7: Check what active_business_id is set to when user logs in ===
    echo "\n<h3>7️⃣ Checking login flow:</h3>\n";
    $loginFile = $isHosting ? '/home/adfb2574/public_html/includes/auth.php' : __DIR__ . '/includes/auth.php';
    
    if (file_exists($loginFile)) {
        $content = file_get_contents($loginFile);
        
        if (strpos($content, 'active_business_id') !== false) {
            echo "✅ auth.php uses active_business_id\n";
            
            // Look for where it's set
            if (preg_match("/\\['active_business_id'\\]\\s*=\\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
                echo "Found assignment in auth.php: <strong>" . htmlspecialchars($m[1]) . "</strong>\n";
            }
        }
    }
    
    echo "\n<div style='background: #fff3e0; padding: 15px; border-left: 4px solid orange; margin-top: 20px;'>\n";
    echo "<strong>⚠️ Likely Issue:</strong> The <code>active_business_id</code> session value for CQC doesn't match what's expected in the auth mapping. <br>\n";
    echo "We need to check what session value is being set when user logs in to CQC.\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "<p>" . $e->getFile() . ":" . $e->getLine() . "</p>\n";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; line-height: 1.6; }
h2, h3 { color: #333; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
table { margin: 10px 0; }
</style>
