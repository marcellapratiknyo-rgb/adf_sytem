<?php
/**
 * ADD PAYROLL MENU TO HOSTING DATABASE
 * Run this on the hosting server: https://adfsystem.online/add-payroll-to-hosting.php
 */

header('Content-Type: text/html; charset=utf-8');

// Try to get database credentials from config
$dbConfig = null;
$configPaths = [
    dirname(__FILE__) . '/config/database.php',
    dirname(__FILE__) . '/config/config.php'
];

foreach ($configPaths as $path) {
    if (file_exists($path)) {
        // Read config to extract database credentials
        $content = file_get_contents($path);
        if (strpos($content, 'DB_HOST') !== false || strpos($content, 'localhost') !== false) {
            $dbConfig = $path;
            break;
        }
    }
}

// Fallback: try to detect from existing database connection
try {
    // Use PDO to connect to current database
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Try to find the correct database name
    $databases = $pdo->query("SHOW DATABASES LIKE '%adf%'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($databases)) {
        // Fallback to adf_system (for local testing)
        $databases = ['adf_system'];
    }
    
    foreach ($databases as $dbname) {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=$dbname", 'root', '');
            
            echo "<h2>Connecting to: $dbname</h2>\n";
            
            // Check if payroll menu already exists
            $check = $pdo->query("SELECT COUNT(*) FROM menu_items WHERE menu_code = 'payroll'")->fetchColumn();
            
            if ($check > 0) {
                echo "✅ Payroll menu already exists in $dbname\n";
                continue;
            }
            
            echo "❌ Payroll menu not found. Adding...\n";
            
            // Get max order
            $maxOrder = $pdo->query("SELECT IFNULL(MAX(menu_order), 0) + 1 FROM menu_items")->fetchColumn();
            $menuId = $pdo->query("SELECT IFNULL(MAX(id), 0) + 1 FROM menu_items")->fetchColumn();
            
            // Insert Payroll menu
            $stmt = $pdo->prepare("
                INSERT INTO menu_items (id, menu_code, menu_name, menu_url, menu_icon, menu_order, is_active)
                VALUES (?, 'payroll', 'Payroll', 'modules/payroll/', 'briefcase', ?, 1)
            ");
            $stmt->execute([$menuId, $maxOrder]);
            
            echo "✅ Inserted Payroll menu (ID: $menuId, Order: $maxOrder)\n";
            
            // Get all businesses and assign menu to them
            $businesses = $pdo->query("SELECT id FROM businesses WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($businesses)) {
                $stmt = $pdo->prepare("INSERT INTO business_menu_config (business_id, menu_id, is_enabled) VALUES (?, ?, 1)");
                
                foreach ($businesses as $bizId) {
                    $stmt->execute([$bizId, $menuId]);
                }
                
                echo "✅ Assigned Payroll menu to " . count($businesses) . " business(es)\n";
            }
            
            echo "<hr>\n";
            
        } catch (Exception $e) {
            echo "⚠️ Error with $dbname: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Connection Error: " . $e->getMessage() . "\n";
    echo "<p>Make sure you're running this on the hosting server or with correct database credentials.</p>";
}

echo "<hr>\n";
echo "<p><strong>✅ Done!</strong> Refresh your Menu Configuration page.</p>\n";
echo "<p><a href='developer/menus.php'>← Go to Menu Configuration</a></p>\n";
?>
