<?php
/**
 * CQC Menu Manager
 * Customize which menus are visible for CQC business only
 */

header('Content-Type: text/html; charset=utf-8');

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$masterDb = 'adf_system';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$masterDb", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle POST request to update menus
    if ($_POST && isset($_POST['submitted'])) {
        $enabledMenus = $_POST['menu_ids'] ?? [];
        
        // Disable all CQC menus first
        $pdo->exec("UPDATE business_menu_config SET is_enabled = 0 WHERE business_id = 7");
        
        // Enable selected menus
        if (!empty($enabledMenus)) {
            $placeholders = implode(',', array_fill(0, count($enabledMenus), '?'));
            $stmt = $pdo->prepare("UPDATE business_menu_config SET is_enabled = 1 WHERE business_id = 7 AND menu_id IN ($placeholders)");
            $stmt->execute($enabledMenus);
        }
        
        $message = "✅ Menu untuk CQC berhasil diubah! Menus yang dipilih: " . count($enabledMenus);
    }
    
    // Get all menus
    $allMenus = $pdo->query("
        SELECT id, menu_code, menu_name, menu_icon, menu_order, is_active
        FROM menu_items
        WHERE is_active = 1
        ORDER BY menu_order
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get currently enabled menus for CQC
    $enabledMenuIds = $pdo->query("
        SELECT menu_id
        FROM business_menu_config
        WHERE business_id = 7 AND is_enabled = 1
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $enabledSet = array_flip($enabledMenuIds);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CQC Menu Manager | ADF System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .content {
            padding: 2rem;
        }
        .message {
            background: #e8f5e9;
            border: 1px solid #4caf50;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: none;
        }
        .message.show {
            display: block;
        }
        .menu-list {
            display: grid;
            gap: 1rem;
        }
        .menu-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        .menu-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .menu-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-right: 1rem;
            accent-color: #667eea;
        }
        .menu-info {
            flex: 1;
        }
        .menu-icon {
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }
        .menu-name {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }
        .menu-code {
            font-size: 0.85rem;
            color: #999;
            margin-top: 0.25rem;
        }
        .button-group {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        button {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .summary {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }
        .summary strong {
            color: #667eea;
            font-size: 1.1rem;
        }
        .note {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ CQC Menu Manager</h1>
            <p>Kustomisasi menu yang ditampilkan untuk bisnis CQC</p>
        </div>
        
        <div class="content">
            <?php if (isset($message)): ?>
            <div class="message show">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <div class="note">
                <strong>📌 Catatan:</strong> CQC adalah bisnis kontraktor/engineering. Pilih hanya menu yang relevan dengan operasional bisnis Anda. Menu yang tidak dipilih akan disembunyikan dari dashboard CQC.
            </div>
            
            <form method="POST">
                <div class="menu-list">
                    <?php foreach ($allMenus as $menu): ?>
                    <label class="menu-item">
                        <input 
                            type="checkbox" 
                            name="menu_ids[]" 
                            value="<?php echo $menu['id']; ?>"
                            <?php echo isset($enabledSet[$menu['id']]) ? 'checked' : ''; ?>
                        >
                        <div class="menu-info">
                            <div>
                                <span class="menu-icon"><?php echo $menu['menu_icon'] ?? '📌'; ?></span>
                                <span class="menu-name"><?php echo htmlspecialchars($menu['menu_name']); ?></span>
                            </div>
                            <div class="menu-code"><code><?php echo $menu['menu_code']; ?></code></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary">
                    <strong id="count">0</strong> dari <strong><?php echo count($allMenus); ?></strong> menu dipilih
                </div>
                
                <input type="hidden" name="submitted" value="1">
                
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="selectAll()">
                        ✓ Pilih Semua
                    </button>
                    <button type="button" class="btn-secondary" onclick="clearAll()">
                        ✕ Hapus Semua
                    </button>
                    <button type="submit" class="btn-primary">
                        💾 Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updateCount() {
            const checked = document.querySelectorAll('input[type="checkbox"]:checked').length;
            document.getElementById('count').textContent = checked;
        }
        
        function selectAll() {
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
            updateCount();
        }
        
        function clearAll() {
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            updateCount();
        }
        
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', updateCount);
        });
        
        updateCount();
    </script>
</body>
</html>
