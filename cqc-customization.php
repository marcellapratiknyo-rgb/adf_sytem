<?php
/**
 * CQC Business Customization Manager
 * Customize colors, theme, dan dashboard khusus untuk CQC
 */

header('Content-Type: text/html; charset=utf-8');

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$masterDb = 'adf_system';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$masterDb", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get CQC config file
    $configFile = __DIR__ . '/config/businesses/cqc.php';
    $config = file_exists($configFile) ? require $configFile : [];
    
    $message = '';
    
    // Handle POST - save changes
    if ($_POST && isset($_POST['save_settings'])) {
        $primaryColor = $_POST['color_primary'] ?? '#059669';
        $secondaryColor = $_POST['color_secondary'] ?? '#065f46';
        $icon = $_POST['icon'] ?? '🏢';
        $businessName = $_POST['business_name'] ?? 'CQC Enjiniring';
        
        // Update CQC config file
        $newConfig = "<?php\nreturn [\n";
        $newConfig .= "    'business_id' => 'cqc',\n";
        $newConfig .= "    'name' => '" . addslashes($businessName) . "',\n";
        $newConfig .= "    'business_type' => 'other',\n";
        $newConfig .= "    'database' => 'adf_cqc',\n";
        $newConfig .= "    'logo' => '',\n";
        $newConfig .= "    'enabled_modules' => [\n";
        $newConfig .= "        'cashbook',\n";
        $newConfig .= "        'auth',\n";
        $newConfig .= "        'settings',\n";
        $newConfig .= "        'reports',\n";
        $newConfig .= "        'divisions',\n";
        $newConfig .= "        'procurement',\n";
        $newConfig .= "        'sales',\n";
        $newConfig .= "        'bills',\n";
        $newConfig .= "        'payroll'\n";
        $newConfig .= "    ],\n";
        $newConfig .= "    'theme' => [\n";
        $newConfig .= "        'color_primary' => '" . $primaryColor . "',\n";
        $newConfig .= "        'color_secondary' => '" . $secondaryColor . "',\n";
        $newConfig .= "        'icon' => '" . $icon . "'\n";
        $newConfig .= "    ],\n";
        $newConfig .= "    'cashbook_columns' => [],\n";
        $newConfig .= "    'dashboard_widgets' => [\n";
        $newConfig .= "        'show_daily_sales' => true,\n";
        $newConfig .= "        'show_orders' => true,\n";
        $newConfig .= "        'show_revenue' => true\n";
        $newConfig .= "    ]\n";
        $newConfig .= "];\n";
        
        if (file_put_contents($configFile, $newConfig)) {
            $message = "✅ Tampilan CQC berhasil diperbarui! Refresh browser untuk melihat perubahan.";
            $config = require $configFile;
        } else {
            $message = "❌ Gagal menyimpan perubahan. Cek permission file.";
        }
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$primaryColor = $config['theme']['color_primary'] ?? '#059669';
$secondaryColor = $config['theme']['color_secondary'] ?? '#065f46';
$icon = $config['theme']['icon'] ?? '🏢';
$businessName = $config['name'] ?? 'CQC Enjiniring';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CQC Customization | ADF System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 2rem;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: #666;
            font-size: 1rem;
        }
        .main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
            background: white;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .section {
            padding: 1.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .section h2 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.75rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
            font-size: 0.95rem;
        }
        input[type="text"],
        input[type="color"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="color"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .color-preview {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .color-box {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .preview-section {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .preview-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .preview-title {
            font-size: 0.9rem;
            color: #999;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
        }
        .business-pill {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 20px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }
        .dashboard-preview {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .dashboard-preview h3 {
            margin-bottom: 0.75rem;
            color: #333;
            font-size: 1.1rem;
        }
        .dashboard-preview p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .emoji-picker {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-top: 0.75rem;
        }
        .emoji-btn {
            padding: 0.75rem;
            border: 2px solid #ddd;
            background: white;
            border-radius: 6px;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .emoji-btn:hover {
            border-color: #667eea;
            background: #f0f4ff;
            transform: scale(1.1);
        }
        .emoji-btn.active {
            border-color: #667eea;
            background: #667eea;
        }
        .button-group {
            grid-column: 1 / -1;
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e0e0e0;
        }
        button {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
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
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        .message.success {
            background: #e8f5e9;
            border: 1px solid #4caf50;
            color: #2e7d32;
        }
        .message.error {
            background: #ffebee;
            border: 1px solid #f44336;
            color: #c62828;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.6;
            color: #1565c0;
        }
        @media (max-width: 768px) {
            .main {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎨 CQC Business Customization</h1>
            <p>Ubah warna, icon, dan tampilan dashboard khusus untuk bisnis CQC</p>
        </div>
        
        <form method="POST" style="display: contents;">
            <div class="main">
                <!-- Settings Section -->
                <div>
                    <?php if ($message): ?>
                    <div class="message <?php echo strpos($message, '✅') ? 'success' : 'error'; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        💡 Perubahan akan langsung muncul di sidebar dan dashboard CQC.
                    </div>
                    
                    <div class="section">
                        <h2>⚙️ Pengaturan Umum</h2>
                        
                        <div class="form-group">
                            <label for="business_name">Nama Bisnis</label>
                            <input 
                                type="text" 
                                id="business_name"
                                name="business_name"
                                value="<?php echo htmlspecialchars($businessName); ?>"
                                placeholder="Contoh: CQC Enjiniring"
                            >
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>🎨 Warna Tema</h2>
                        
                        <div class="form-group">
                            <label for="color_primary">Warna Primer (Utama)</label>
                            <input 
                                type="color" 
                                id="color_primary"
                                name="color_primary"
                                value="<?php echo $primaryColor; ?>"
                            >
                            <small style="color: #666;">Digunakan untuk tombol, header, dan elemen penting</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="color_secondary">Warna Sekunder</label>
                            <input 
                                type="color" 
                                id="color_secondary"
                                name="color_secondary"
                                value="<?php echo $secondaryColor; ?>"
                            >
                            <small style="color: #666;">Digunakan untuk aksen dan background</small>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>😊 Pilih Icon/Emoji</h2>
                        <input 
                            type="text" 
                            id="icon"
                            name="icon"
                            value="<?php echo htmlspecialchars($icon); ?>"
                            placeholder="Paste emoji di sini"
                            style="font-size: 2rem; text-align: center; height: 60px; margin-bottom: 1rem;"
                        >
                        
                        <div class="emoji-picker">
                            <button type="button" class="emoji-btn" data-emoji="🏢" title="Kantor">🏢</button>
                            <button type="button" class="emoji-btn" data-emoji="🏭" title="Pabrik">🏭</button>
                            <button type="button" class="emoji-btn" data-emoji="🏗️" title="Konstruksi">🏗️</button>
                            <button type="button" class="emoji-btn" data-emoji="🔧" title="Tools">🔧</button>
                            <button type="button" class="emoji-btn" data-emoji="⚙️" title="Engineering">⚙️</button>
                            <button type="button" class="emoji-btn" data-emoji="🛠️" title="Maintenance">🛠️</button>
                            <button type="button" class="emoji-btn" data-emoji="💼" title="Business">💼</button>
                            <button type="button" class="emoji-btn" data-emoji="📊" title="Analytics">📊</button>
                        </div>
                    </div>
                </div>
                
                <!-- Preview Section -->
                <div>
                    <div class="section preview-section">
                        <h2>👁️ Preview</h2>
                        
                        <div class="preview-card">
                            <div class="preview-title">Sidebar Business Indicator</div>
                            <div class="business-pill" 
                                 style="background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);">
                                <span id="preview-icon"><?php echo $icon; ?></span>
                                <span id="preview-name" style="margin-left: 0.5rem;"><?php echo htmlspecialchars($businessName); ?></span>
                            </div>
                        </div>
                        
                        <div class="preview-card">
                            <div class="preview-title">Tombol & Header</div>
                            <button type="button" style="background-color: <?php echo $primaryColor; ?>; color: white; width: 100%;">
                                Contoh Tombol
                            </button>
                        </div>
                        
                        <div class="preview-card">
                            <div class="preview-title">Card dengan Border</div>
                            <div class="dashboard-preview" 
                                 style="border-left-color: <?php echo $primaryColor; ?>;">
                                <h3>Dashboard Card</h3>
                                <p>Ini adalah preview card di dashboard</p>
                            </div>
                        </div>
                        
                        <div class="preview-card">
                            <div class="preview-title">Warna Gradient</div>
                            <div style="padding: 2rem; border-radius: 8px; background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); color: white; text-align: center; font-weight: 600;">
                                Gradient Background
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="resetForm()">
                        ↻ Reset
                    </button>
                    <button type="submit" name="save_settings" value="1" class="btn-primary">
                        💾 Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        function updatePreview() {
            const primaryColor = document.getElementById('color_primary').value;
            const secondaryColor = document.getElementById('color_secondary').value;
            const icon = document.getElementById('icon').value;
            const name = document.getElementById('business_name').value;
            
            document.getElementById('preview-icon').textContent = icon;
            document.getElementById('preview-name').textContent = name;
            
            document.querySelectorAll('[id^="preview-"]').forEach(el => {
                const parent = el.closest('.business-pill');
                if (parent) {
                    parent.style.background = `linear-gradient(135deg, ${primaryColor} 0%, ${secondaryColor} 100%)`;
                }
            });
        }
        
        document.getElementById('color_primary').addEventListener('change', updatePreview);
        document.getElementById('color_secondary').addEventListener('change', updatePreview);
        document.getElementById('icon').addEventListener('input', updatePreview);
        document.getElementById('business_name').addEventListener('input', updatePreview);
        
        // Emoji picker
        document.querySelectorAll('.emoji-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const emoji = btn.getAttribute('data-emoji');
                document.getElementById('icon').value = emoji;
                updatePreview();
            });
        });
        
        function resetForm() {
            location.reload();
        }
    </script>
</body>
</html>
