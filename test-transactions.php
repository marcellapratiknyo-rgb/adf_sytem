<?php
require 'config/config.php';
require 'config/database.php';

$db = Database::getInstance()->getConnection();

// Check table structure
$result = $db->query('DESCRIBE investor_transactions')->fetchAll(PDO::FETCH_ASSOC);
echo "Table columns:\n";
foreach($result as $r) {
    echo $r['Field'] . ' - ' . $r['Type'] . "\n";
}

// Get sample data
echo "\n\nSample transaction:\n";
$sample = $db->query('SELECT * FROM investor_transactions LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($sample) {
    print_r($sample);
}
?>
