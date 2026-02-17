<?php
/**
 * ONE-CLICK FIX - Update database_name di tabel businesses
 * UPLOAD FILE INI KE HOSTING, LALU AKSES LEWAT BROWSER
 */

// Config
$host = 'localhost';
$user = 'adfb2574_admin';
$pass = 'Warnet_jaya1';
$masterDb = 'adfb2574_adf';

echo "<h1>🔧 FIX DATABASE NAME</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$masterDb", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>BEFORE:</h2>";
    $stmt = $pdo->query("SELECT id, business_name, database_name FROM businesses");
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Database</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['id']}</td><td>{$row['business_name']}</td><td>{$row['database_name']}</td></tr>";
    }
    echo "</table>";
    
    // UPDATE Ben's Cafe
    $update1 = $pdo->prepare("UPDATE businesses SET database_name = ? WHERE business_name LIKE '%Ben%Cafe%' OR business_code LIKE '%ben%'");
    $update1->execute(['adfb2574_Adf_Bens']);
    $affected1 = $update1->rowCount();
    
    // UPDATE Narayana Hotel  
    $update2 = $pdo->prepare("UPDATE businesses SET database_name = ? WHERE business_name LIKE '%Narayana%' OR business_code LIKE '%narayana%'");
    $update2->execute(['adfb2574_narayana_hotel']);
    $affected2 = $update2->rowCount();
    
    echo "<h2 style='color:green'>✅ UPDATED!</h2>";
    echo "<p>Ben's Cafe: $affected1 rows updated</p>";
    echo "<p>Narayana Hotel: $affected2 rows updated</p>";
    
    echo "<h2>AFTER:</h2>";
    $stmt2 = $pdo->query("SELECT id, business_name, database_name FROM businesses");
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Database</th></tr>";
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $color = (strpos($row['database_name'], 'adfb2574_') === 0) ? 'green' : 'red';
        echo "<tr><td>{$row['id']}</td><td>{$row['business_name']}</td><td style='color:$color'>{$row['database_name']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h2 style='color:green'>🎉 SELESAI! Dashboard sekarang akan berfungsi!</h2>";
    echo "<p><a href='/modules/owner/dashboard-2028.php'>→ Buka Dashboard</a></p>";
    
    // Hapus file ini setelah selesai (keamanan)
    echo "<p style='color:red'><strong>⚠️ PENTING:</strong> Hapus file FIX-NOW.php dari hosting setelah selesai!</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ ERROR:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
