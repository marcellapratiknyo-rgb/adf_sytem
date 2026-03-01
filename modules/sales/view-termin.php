<?php
/**
 * CQC View/Print Invoice
 * Elegant invoice display and print
 */
define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

require_once '../cqc-projects/db-helper.php';

try {
    $pdo = getCQCDatabaseConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$print = isset($_GET['print']) && $_GET['print'] == '1';

if (!$id) {
    header('Location: index-cqc.php');
    exit;
}

// Get invoice
$stmt = $pdo->prepare("
    SELECT ti.*, p.project_code, p.project_name, p.client_name, p.client_phone, 
           p.client_email, p.location, p.solar_capacity_kwp
    FROM cqc_termin_invoices ti
    LEFT JOIN cqc_projects p ON ti.project_id = p.id
    WHERE ti.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice tidak ditemukan.");
}

// Get CQC business settings from master database
$db = Database::getInstance();
$businessId = 7; // CQC business ID

// Default company info
$companyName = 'CQC Enjiniring';
$companyTagline = 'Solar Panel Installation Contractor';
$companyAddress = '';
$companyCity = '';
$companyPhone = '';
$companyEmail = '';
$companyNPWP = '';
$companyLogo = '';
$bankName = '';
$bankAccount = '';
$bankHolder = '';

// Try to load from business_settings
try {
    $settings = $db->fetchAll("SELECT setting_key, setting_value FROM business_settings WHERE business_id = ?", [$businessId]);
    foreach ($settings as $s) {
        switch ($s['setting_key']) {
            case 'business_name': $companyName = $s['setting_value']; break;
            case 'tagline': $companyTagline = $s['setting_value']; break;
            case 'address': $companyAddress = $s['setting_value']; break;
            case 'city': $companyCity = $s['setting_value']; break;
            case 'phone': $companyPhone = $s['setting_value']; break;
            case 'email': $companyEmail = $s['setting_value']; break;
            case 'npwp': $companyNPWP = $s['setting_value']; break;
            case 'logo': $companyLogo = $s['setting_value']; break;
            case 'bank_name': $bankName = $s['setting_value']; break;
            case 'bank_account': $bankAccount = $s['setting_value']; break;
            case 'bank_holder': $bankHolder = $s['setting_value']; break;
        }
    }
} catch (Exception $e) {
    // Settings table might not exist, use defaults
}

// Try load from CQC config file
$configPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__));
$configFile = $configPath . '/config/businesses/cqc.php';
if (file_exists($configFile)) {
    $cqcConfig = include $configFile;
    if (empty($companyName) || $companyName === 'CQC Enjiniring') {
        $companyName = $cqcConfig['name'] ?? $companyName;
    }
}

$pageTitle = "Invoice " . $invoice['invoice_number'];

// Terbilang function - fixed for PHP 8 (use intval to avoid float-to-int warnings)
function terbilang($angka) {
    $angka = abs(intval($angka));
    $huruf = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
    $temp = "";
    if ($angka < 12) {
        $temp = " " . $huruf[$angka];
    } else if ($angka < 20) {
        $temp = terbilang($angka - 10) . " belas";
    } else if ($angka < 100) {
        $temp = terbilang(intval($angka / 10)) . " puluh" . terbilang($angka % 10);
    } else if ($angka < 200) {
        $temp = " seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $temp = terbilang(intval($angka / 100)) . " ratus" . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $temp = " seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $temp = terbilang(intval($angka / 1000)) . " ribu" . terbilang($angka % 1000);
    } else if ($angka < 1000000000) {
        $temp = terbilang(intval($angka / 1000000)) . " juta" . terbilang($angka % 1000000);
    } else if ($angka < 1000000000000) {
        $temp = terbilang(intval($angka / 1000000000)) . " milyar" . terbilang(intval($angka % 1000000000));
    } else if ($angka < 1000000000000000) {
        $temp = terbilang(intval($angka / 1000000000000)) . " triliun" . terbilang(intval($angka % 1000000000000));
    }
    return $temp;
}
$totalTerbilang = ucwords(trim(terbilang($invoice['total_amount']))) . " Rupiah";

// Format date in Indonesian
function formatDateID($date) {
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $d = date('j', strtotime($date));
    $m = date('n', strtotime($date));
    $y = date('Y', strtotime($date));
    return $d . ' ' . $months[$m] . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --navy: #0d1f3c;
            --navy-light: #1a3a5c;
            --gold: #f0b429;
            --gold-dark: #d4960d;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 12px; 
            line-height: 1.6; 
            color: #333;
            background: <?php echo $print ? '#fff' : '#f0f2f5'; ?>;
        }
        
        .page {
            max-width: 210mm; 
            margin: <?php echo $print ? '0' : '30px auto'; ?>; 
            padding: 0;
            background: #fff; 
            <?php if (!$print): ?>
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            border-radius: 8px;
            overflow: hidden;
            <?php endif; ?>
        }
        
        /* Navy Header Band */
        .header-band {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: #fff;
            padding: 25px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .company-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .company-logo {
            width: 60px;
            height: 60px;
            background: var(--gold);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .company-logo img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        
        .company-details h1 {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 2px;
            letter-spacing: -0.3px;
        }
        
        .company-details p {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
            font-weight: 500;
        }
        
        .invoice-badge {
            text-align: right;
        }
        
        .invoice-badge h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--gold);
            letter-spacing: 3px;
            margin-bottom: 4px;
        }
        
        .invoice-badge .inv-number {
            font-size: 13px;
            color: rgba(255,255,255,0.9);
            font-weight: 600;
        }
        
        .status-pill {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }
        
        .status-draft { background: rgba(255,255,255,0.2); color: #fff; }
        .status-sent { background: #3b82f6; color: #fff; }
        .status-paid { background: var(--success); color: #fff; }
        .status-partial { background: #f59e0b; color: #fff; }
        .status-overdue { background: var(--danger); color: #fff; }
        
        /* Content Area */
        .content {
            padding: 30px 35px;
        }
        
        /* Info Cards */
        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 18px 20px;
            border-left: 4px solid var(--gold);
        }
        
        .info-card h4 {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        
        .info-card .name {
            font-size: 16px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 8px;
        }
        
        .info-card .detail {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .info-card .detail span {
            font-size: 13px;
        }
        
        /* Invoice Table */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        
        .inv-table th {
            background: var(--navy);
            color: #fff;
            padding: 14px 16px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .inv-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        .inv-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .inv-table tbody tr:hover {
            background: #fafbfc;
        }
        
        .termin-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
            border-radius: 10px;
        }
        
        .item-desc {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 4px;
        }
        
        .item-sub {
            font-size: 10px;
            color: #94a3b8;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Summary */
        .summary-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 25px;
        }
        
        .summary-box {
            width: 380px;
            background: #f8fafc;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 18px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-row .label {
            color: #64748b;
            font-size: 12px;
        }
        
        .summary-row .value {
            font-weight: 600;
            color: var(--navy);
        }
        
        .summary-row .value.plus { color: var(--success); }
        .summary-row .value.minus { color: var(--danger); }
        
        .summary-total {
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            padding: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .summary-total .label {
            color: rgba(255,255,255,0.8);
            font-size: 12px;
            font-weight: 600;
        }
        
        .summary-total .value {
            color: var(--gold);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        /* Terbilang */
        .terbilang-box {
            background: linear-gradient(135deg, rgba(240,180,41,0.08), rgba(240,180,41,0.02));
            border: 1px dashed var(--gold);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 25px;
        }
        
        .terbilang-box .label {
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .terbilang-box .text {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
            font-style: italic;
        }
        
        /* Bank Info */
        .bank-info {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 25px;
        }
        
        .bank-info h5 {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .bank-info .bank-detail {
            display: flex;
            gap: 30px;
        }
        
        .bank-info .bank-item {
            font-size: 12px;
        }
        
        .bank-info .bank-item span {
            color: #64748b;
        }
        
        .bank-info .bank-item strong {
            color: var(--navy);
        }
        
        /* Signatures */
        .signatures-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-top: 50px;
            padding-top: 20px;
        }
        
        .sig-box {
            text-align: center;
        }
        
        .sig-box .label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 70px;
        }
        
        .sig-box .line {
            border-top: 1px solid var(--navy);
            padding-top: 8px;
            font-weight: 700;
            color: var(--navy);
            font-size: 12px;
        }
        
        /* Notes */
        .notes-box {
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 25px;
        }
        
        .notes-box .label {
            font-size: 10px;
            font-weight: 700;
            color: #92400e;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .notes-box .text {
            font-size: 11px;
            color: #78350f;
        }
        
        /* Print Controls */
        .print-controls { 
            position: fixed; 
            top: 25px; 
            right: 25px; 
            display: flex; 
            gap: 10px; 
            z-index: 100;
        }
        
        .print-controls button, .print-controls a {
            padding: 12px 22px; 
            border: none; 
            border-radius: 8px;
            font-weight: 700; 
            font-size: 13px; 
            cursor: pointer;
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-print { 
            background: var(--navy); 
            color: #fff; 
            box-shadow: 0 4px 15px rgba(13,31,60,0.3);
        }
        .btn-print:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(13,31,60,0.4); }
        
        .btn-back { 
            background: #fff; 
            color: #64748b; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-back:hover { background: #f8fafc; }

        @media print {
            body { background: #fff; }
            .page { box-shadow: none; margin: 0; border-radius: 0; }
            .print-controls { display: none !important; }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>
    <?php if (!$print): ?>
    <div class="print-controls">
        <a href="index-cqc.php" class="btn-back">← Kembali</a>
        <button class="btn-print" onclick="window.print()">🖨️ Cetak Invoice</button>
    </div>
    <?php endif; ?>

    <div class="page">
        <!-- Navy Header Band -->
        <div class="header-band">
            <div class="company-section">
                <div class="company-logo">
                    <?php if ($companyLogo && file_exists($configPath . '/uploads/' . $companyLogo)): ?>
                        <img src="<?php echo BASE_URL; ?>/uploads/<?php echo $companyLogo; ?>" alt="Logo">
                    <?php else: ?>
                        ☀️
                    <?php endif; ?>
                </div>
                <div class="company-details">
                    <h1><?php echo htmlspecialchars($companyName); ?></h1>
                    <p><?php echo htmlspecialchars($companyTagline); ?></p>
                </div>
            </div>
            <div class="invoice-badge">
                <h2>INVOICE</h2>
                <div class="inv-number"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                <?php
                $statusLabel = [
                    'draft' => 'Draft',
                    'sent' => 'Terkirim',
                    'paid' => '✓ Lunas',
                    'partial' => 'Sebagian',
                    'overdue' => '! Jatuh Tempo'
                ];
                ?>
                <span class="status-pill status-<?php echo $invoice['payment_status']; ?>">
                    <?php echo $statusLabel[$invoice['payment_status']] ?? strtoupper($invoice['payment_status']); ?>
                </span>
            </div>
        </div>
        
        <div class="content">
            <!-- Info Row -->
            <div class="info-row">
                <div class="info-card">
                    <h4>Kepada</h4>
                    <div class="name"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                    <?php if ($invoice['client_phone']): ?>
                    <div class="detail"><span>📞</span> <?php echo htmlspecialchars($invoice['client_phone']); ?></div>
                    <?php endif; ?>
                    <?php if ($invoice['client_email']): ?>
                    <div class="detail"><span>✉️</span> <?php echo htmlspecialchars($invoice['client_email']); ?></div>
                    <?php endif; ?>
                    <?php if ($invoice['location']): ?>
                    <div class="detail"><span>📍</span> <?php echo htmlspecialchars($invoice['location']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="info-card">
                    <h4>Detail Invoice</h4>
                    <div class="detail"><strong>Tanggal:</strong> <?php echo formatDateID($invoice['invoice_date']); ?></div>
                    <?php if ($invoice['due_date']): ?>
                    <div class="detail"><strong>Jatuh Tempo:</strong> <?php echo formatDateID($invoice['due_date']); ?></div>
                    <?php endif; ?>
                    <div class="detail"><strong>Proyek:</strong> [<?php echo htmlspecialchars($invoice['project_code']); ?>]</div>
                    <div class="detail"><?php echo htmlspecialchars($invoice['project_name']); ?></div>
                    <?php if ($invoice['solar_capacity_kwp']): ?>
                    <div class="detail"><strong>Kapasitas:</strong> <?php echo number_format($invoice['solar_capacity_kwp'], 1); ?> kWp</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Invoice Table -->
            <table class="inv-table">
                <thead>
                    <tr>
                        <th style="width: 80px;" class="text-center">Termin</th>
                        <th>Keterangan</th>
                        <th style="width: 80px;" class="text-center">%</th>
                        <th style="width: 140px;" class="text-right">Nilai Kontrak</th>
                        <th style="width: 140px;" class="text-right">DPP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">
                            <span class="termin-badge"><?php echo $invoice['termin_number']; ?></span>
                        </td>
                        <td>
                            <div class="item-desc"><?php echo htmlspecialchars($invoice['description'] ?: 'Pembayaran Termin ' . $invoice['termin_number']); ?></div>
                            <div class="item-sub">Proyek: <?php echo htmlspecialchars($invoice['project_name']); ?></div>
                        </td>
                        <td class="text-center" style="font-weight: 700; color: var(--gold-dark);">
                            <?php echo number_format($invoice['percentage'], 1); ?>%
                        </td>
                        <td class="text-right">Rp <?php echo number_format($invoice['contract_value'], 0, ',', '.'); ?></td>
                        <td class="text-right" style="font-weight: 700;">Rp <?php echo number_format($invoice['base_amount'], 0, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Summary -->
            <div class="summary-section">
                <div class="summary-box">
                    <div class="summary-row">
                        <span class="label">DPP (Dasar Pengenaan Pajak)</span>
                        <span class="value">Rp <?php echo number_format($invoice['base_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <?php if ($invoice['ppn_amount'] > 0): ?>
                    <div class="summary-row">
                        <span class="label">(+) PPN <?php echo number_format($invoice['ppn_percentage'], 1); ?>%</span>
                        <span class="value plus">+ Rp <?php echo number_format($invoice['ppn_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['pph_amount'] > 0): ?>
                    <div class="summary-row">
                        <span class="label">(-) PPh <?php echo number_format($invoice['pph_percentage'], 1); ?>%</span>
                        <span class="value minus">- Rp <?php echo number_format($invoice['pph_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['retention_amount'] > 0): ?>
                    <div class="summary-row">
                        <span class="label">(-) Retensi <?php echo number_format($invoice['retention_percentage'], 1); ?>%</span>
                        <span class="value minus">- Rp <?php echo number_format($invoice['retention_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-total">
                        <span class="label">TOTAL TAGIHAN</span>
                        <span class="value">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Terbilang -->
            <div class="terbilang-box">
                <div class="label">Terbilang</div>
                <div class="text"># <?php echo $totalTerbilang; ?> #</div>
            </div>
            
            <!-- Bank Info -->
            <?php if ($bankName || $bankAccount): ?>
            <div class="bank-info">
                <h5>Informasi Pembayaran</h5>
                <div class="bank-detail">
                    <?php if ($bankName): ?>
                    <div class="bank-item"><span>Bank:</span> <strong><?php echo htmlspecialchars($bankName); ?></strong></div>
                    <?php endif; ?>
                    <?php if ($bankAccount): ?>
                    <div class="bank-item"><span>No. Rekening:</span> <strong><?php echo htmlspecialchars($bankAccount); ?></strong></div>
                    <?php endif; ?>
                    <?php if ($bankHolder): ?>
                    <div class="bank-item"><span>Atas Nama:</span> <strong><?php echo htmlspecialchars($bankHolder); ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($invoice['notes']): ?>
            <div class="notes-box">
                <div class="label">Catatan</div>
                <div class="text"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></div>
            </div>
            <?php endif; ?>
            
            <!-- Signatures -->
            <div class="signatures-row">
                <div class="sig-box">
                    <div class="label">Diterima oleh,</div>
                    <div class="line"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                </div>
                <div class="sig-box">
                    <div class="label">Hormat kami,</div>
                    <div class="line"><?php echo htmlspecialchars($companyName); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($print): ?>
    <script>window.onload = function() { window.print(); }</script>
    <?php endif; ?>
</body>
</html>
