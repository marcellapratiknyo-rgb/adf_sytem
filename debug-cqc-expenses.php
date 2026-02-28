<?php
// Debug CQC Expenses
require_once 'modules/cqc-projects/db-helper.php';
$pdo = getCQCDatabaseConnection();

echo "<h2>CQC Expense Debug</h2>";

// Check expenses table structure
echo "<h3>Table Structure:</h3><pre>";
$cols = $pdo->query('DESCRIBE cqc_project_expenses')->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
echo "</pre>";

// Check expense data
echo "<h3>Expense Data:</h3><pre>";
$expenses = $pdo->query('SELECT * FROM cqc_project_expenses LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
print_r($expenses);
echo "</pre>";

// Check categories
echo "<h3>Categories:</h3><pre>";
try {
    $cats = $pdo->query('SELECT * FROM cqc_expense_categories')->fetchAll(PDO::FETCH_ASSOC);
    print_r($cats);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";

// Test the category query
echo "<h3>Category Query Test:</h3><pre>";
try {
    // Get first project
    $proj = $pdo->query("SELECT id FROM cqc_projects LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($proj) {
        echo "Project ID: " . $proj['id'] . "\n";
        
        // Try simple query first
        $simpleQ = $pdo->prepare("SELECT * FROM cqc_project_expenses WHERE project_id = ?");
        $simpleQ->execute([$proj['id']]);
        $simpleData = $simpleQ->fetchAll(PDO::FETCH_ASSOC);
        echo "Simple expense query result:\n";
        print_r($simpleData);
        
        // Test the actual category query
        $stmtCat = $pdo->prepare("
            SELECT 
                COALESCE(c.category_name, 'Lainnya') as category_name,
                COALESCE(c.category_icon, '📦') as category_icon,
                SUM(e.amount) as total_amount
            FROM cqc_project_expenses e
            LEFT JOIN cqc_expense_categories c ON e.category_id = c.id
            WHERE e.project_id = ?
            GROUP BY COALESCE(c.category_name, 'Lainnya'), COALESCE(c.category_icon, '📦')
            ORDER BY total_amount DESC
        ");
        $stmtCat->execute([$proj['id']]);
        $catData = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
        echo "\nCategory grouped query result:\n";
        print_r($catData);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
