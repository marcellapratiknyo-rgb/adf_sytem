<?php
/**
 * Quick script to add database menu locally
 */
$pdo = new PDO('mysql:host=localhost;dbname=adf_system', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Add database menu
$check = $pdo->query("SELECT id FROM menu_items WHERE menu_code = 'database'")->fetch();
if (!$check) {
    $pdo->exec("INSERT INTO menu_items (menu_code, menu_name, menu_url, menu_order, is_active) VALUES ('database', 'Database Master', 'modules/database/', 11, 1)");
    echo "Added database menu to menu_items\n";
} else {
    echo "Database menu already exists (id: {$check['id']})\n";
}

// Add finance menu if not exists
$check2 = $pdo->query("SELECT id FROM menu_items WHERE menu_code = 'finance'")->fetch();
if (!$check2) {
    $pdo->exec("INSERT INTO menu_items (menu_code, menu_name, menu_url, menu_order, is_active) VALUES ('finance', 'Manajemen Keuangan', 'modules/finance/', 12, 1)");
    echo "Added finance menu to menu_items\n";
} else {
    echo "Finance menu already exists\n";
}

// Show all menus
$menus = $pdo->query('SELECT id, menu_code, menu_name FROM menu_items ORDER BY menu_order')->fetchAll(PDO::FETCH_ASSOC);
echo "\nAll menus:\n";
foreach ($menus as $m) {
    echo "  - {$m['id']}: {$m['menu_code']} - {$m['menu_name']}\n";
}

echo "\nDone!\n";
