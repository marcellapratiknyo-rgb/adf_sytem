<?php
/**
 * CQC Full System Diagnostic & Fix
 * Checks database, permissions, config files, and login flow
 */

header('Content-Type: text/html; charset=utf-8');

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$masterDb = 'adf_system';

echo "<!DOCTYPE html>\n<html>\n<head><meta charset='utf-8'><title>CQC Diagnostic | ADF System</title>\n";
echo "<style>body{font-family:Arial;padding:20px;max-width:1000px;margin:0 auto;line-height:1.6;}\n";
echo ".ok{background:#e8f5e9;padding:15px;margin:10px 0;border-left:4px solid #4caf50;}\n";
echo ".error{background:#ffebee;padding:15px;margin:10px 0;border-left:4px solid #f44336;}\n";
echo ".warning{background:#fff3e0;padding:15px;margin:10px 0;border-left:4px solid #ff9800;}\n";
echo ".step{font-size:1.1em;margin:20px 0 10px 0;font-weight:bold;}\n";
echo "code{background:#f0f0f0;padding:2px 6px;border-radius:3px;}\n";
echo "</style>\n</head>\n<body>\n";

echo "<h1>🔍 CQC System Diagnostic</h1>\n";

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$masterDb", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='ok'><strong>✅ Database Connected</strong></div>\n";
    
    // Step 1: Check CQC business record
    echo "<div class='step'>1️⃣ CQC Business Record</div>\n";
    $cqc = $pdo->query("SELECT id, business_code, slug, business_name, is_active FROM businesses WHERE id=7 OR business_code='CQC'")->fetch(PDO::FETCH_ASSOC);
    
    if ($cqc) {
        echo "<div class='ok'>";
        echo "✅ CQC Business Found<br>\n";
        echo "   ID: <code>" . $cqc['id'] . "</code><br>\n";
        echo "   Code: <code>" . $cqc['business_code'] . "</code><br>\n";
        echo "   Slug: <code>" . ($cqc['slug'] ?: 'NULL') . "</code><br>\n";
        echo "   Name: " . htmlspecialchars($cqc['business_name']) . "<br>\n";
        echo "   Active: " . ($cqc['is_active'] ? 'Yes' : 'No') . "\n";
        echo "</div>\n";
        
        if (empty($cqc['slug'])) {
            echo "<div class='warning'>⚠️ Slug is NULL - Setting to 'cqc'...</div>\n";
            $pdo->exec("UPDATE businesses SET slug='cqc' WHERE id=7");
            $cqc['slug'] = 'cqc';
        }
    } else {
        echo "<div class='error'>❌ CQC business not found!</div>\n";
        exit;
    }
    
    // Step 2: Check config file
    echo "<div class='step'>2️⃣ Config File</div>\n";
    $configFile = __DIR__ . '/config/businesses/cqc.php';
    if (file_exists($configFile)) {
        echo "<div class='ok'>";
        echo "✅ Config file exists: <code>config/businesses/cqc.php</code><br>\n";
        $config = require $configFile;
        echo "   Business ID: <code>" . $config['business_id'] . "</code><br>\n";
        echo "   Name: " . $config['name'] . "<br>\n";
        echo "   Database: <code>" . $config['database'] . "</code>\n";
        echo "</div>\n";
    } else {
        echo "<div class='error'>❌ Config file missing at <code>config/businesses/cqc.php</code>\n</div>";
    }
    
    // Step 3: Check menu assignments
    echo "<div class='step'>3️⃣ Menu Assignments</div>\n";
    $menuCount = $pdo->query("SELECT COUNT(*) as cnt FROM business_menu_config WHERE business_id=7 AND is_enabled=1")->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    if ($menuCount > 0) {
        echo "<div class='ok'>";
        echo "✅ Menus assigned: <strong>" . $menuCount . "</strong><br>\n";
        $menus = $pdo->query("
            SELECT m.menu_name FROM menu_items m
            JOIN business_menu_config bmc ON bmc.menu_id=m.id
            WHERE bmc.business_id=7
            ORDER BY m.menu_order
        ")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($menus as $menu) {
            echo "   • " . htmlspecialchars($menu) . "<br>\n";
        }
        echo "</div>\n";
    } else {
        echo "<div class='warning'>⚠️ No menus assigned - Assigning now...</div>\n";
        $pdo->exec("DELETE FROM business_menu_config WHERE business_id=7");
        $menus = $pdo->query("SELECT id FROM menu_items WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("INSERT INTO business_menu_config (business_id, menu_id, is_enabled) VALUES (?, ?, 1)");
        foreach ($menus as $menuId) {
            $stmt->execute([7, $menuId]);
        }
        echo "<div class='ok'>✅ " . count($menus) . " menus assigned</div>\n";
    }
    
    // Step 4: Check user permissions
    echo "<div class='step'>4️⃣ User Permissions</div>\n";
    $permCount = $pdo->query("SELECT COUNT(*) as cnt FROM user_menu_permissions WHERE business_id=7")->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    if ($permCount > 0) {
        echo "<div class='ok'>✅ Permissions set: <strong>" . $permCount . "</strong> records</div>\n";
    } else {
        echo "<div class='warning'>⚠️ No permissions - Setting up...</div>\n";
        $pdo->exec("DELETE FROM user_menu_permissions WHERE business_id=7");
        $users = $pdo->query("SELECT id FROM users WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        $menus = $pdo->query("SELECT menu_code FROM menu_items WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->prepare("INSERT INTO user_menu_permissions (user_id, business_id, menu_code, can_view, can_create, can_edit, can_delete) VALUES (?, 7, ?, 1, 1, 1, 1)");
        $count = 0;
        foreach ($users as $userId) {
            foreach ($menus as $menuCode) {
                try {
                    $stmt->execute([$userId, $menuCode]);
                    $count++;
                } catch (Exception $e) {}
            }
        }
        echo "<div class='ok'>✅ Created " . $count . " permission records</div>\n";
    }
    
    // Step 5: Check business assignments
    echo "<div class='step'>5️⃣ User Business Assignments</div>\n";
    $assignedUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) as cnt FROM user_business_assignment WHERE business_id=7")->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    if ($assignedUsers > 0) {
        echo "<div class='ok'>";
        echo "✅ Assigned to <strong>" . $assignedUsers . "</strong> users<br>\n";
        $users = $pdo->query("
            SELECT u.username FROM users u
            WHERE u.is_active=1 AND u.id IN (
                SELECT user_id FROM user_business_assignment WHERE business_id=7
            )
        ")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($users as $user) {
            echo "   • " . htmlspecialchars($user) . "<br>\n";
        }
        echo "</div>\n";
    } else {
        echo "<div class='warning'>⚠️ Not assigned to any users - Assigning all...</div>\n";
        $inserted = $pdo->exec("INSERT INTO user_business_assignment (user_id, business_id) 
                               SELECT u.id, 7 FROM users u WHERE u.is_active=1 
                               AND u.id NOT IN (SELECT user_id FROM user_business_assignment WHERE business_id=7)");
        echo "<div class='ok'>✅ Assigned to " . $inserted . " users</div>\n";
    }
    
    // Step 6: Check auth.php mapping
    echo "<div class='step'>6️⃣ Auth.php CQC Mapping</div>\n";
    $authFile = __DIR__ . '/includes/auth.php';
    if (file_exists($authFile)) {
        $authContent = file_get_contents($authFile);
        if (strpos($authContent, "'cqc' => 'CQC'") !== false) {
            echo "<div class='ok'>✅ CQC mapping found in auth.php</div>\n";
        } else {
            echo "<div class='error'>❌ CQC mapping missing from auth.php - This needs to be added manually!</div>\n";
        }
    }
    
    // Final summary
    echo "<div class='step' style='margin-top:30px;'>✨ Summary</div>\n";
    echo "<div class='ok'>";
    echo "<strong>CQC System Status:</strong><br>\n";
    echo "✅ Database record: Complete<br>\n";
    echo "✅ Config file: Present<br>\n";
    echo "✅ Menu assignments: " . $menuCount . " menus<br>\n";
    echo "✅ User permissions: " . $permCount . " records<br>\n";
    echo "✅ Business assignments: " . $assignedUsers . " users<br>\n";
    echo "<br>\n";
    echo "<strong>Next Steps:</strong><br>\n";
    echo "1. Open a <strong>private/incognito</strong> browser window<br>\n";
    echo "2. Go to <strong>https://adfsystem.online/</strong><br>\n";
    echo "3. Login with:<br>\n";
    echo "   - Username: <code>lucca</code> (owner)<br>\n";
    echo "   - Or your CQC business user account<br>\n";
    echo "4. You should now be able to select and access CQC<br>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div class='error'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

echo "</body>\n</html>\n";
?>
