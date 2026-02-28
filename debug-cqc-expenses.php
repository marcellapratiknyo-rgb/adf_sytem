<?php
// Debug CQC Expenses
require_once 'modules/cqc-projects/db-helper.php';
$pdo = getCQCDatabaseConnection();

echo "<h2>CQC Expense Debug</h2>";

// Check all projects
echo "<h3>All Projects:</h3><pre>";
$projects = $pdo->query('SELECT id, project_name FROM cqc_projects')->fetchAll(PDO::FETCH_ASSOC);
print_r($projects);
echo "</pre>";

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

// Test the category query FOR EACH PROJECT
echo "<h3>Category Query Test (ALL PROJECTS):</h3>";

foreach ($projects as $proj) {
    echo "<h4>Project ID: " . $proj['id'] . " - " . htmlspecialchars($proj['project_name']) . "</h4><pre>";
    
    // First check if there are any expenses for this project
    $countStmt = $pdo->prepare("SELECT COUNT(*), SUM(amount) FROM cqc_project_expenses WHERE project_id = ?");
    $countStmt->execute([$proj['id']]);
    $countData = $countStmt->fetch(PDO::FETCH_NUM);
    echo "Total expenses: " . $countData[0] . ", Sum: " . $countData[1] . "\n";
    
    if ($countData[0] > 0) {
        // Test the actual category query
        try {
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
            echo "Category grouped query result:\n";
            print_r($catData);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "No expenses for this project\n";
    }
    echo "</pre>";
}
