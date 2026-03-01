<?php
/**
 * CQC View/Print Termin Invoice
 * Display and print termin invoice
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
    die("Faktur tidak ditemukan.");
}

// Get company info from CQC config
$companyName = 'CQC Enjiniring';
$companyAddress = 'Jl. Solar Energy No. 123';
$companyPhone = '-';
$companyEmail = '-';

// Try to load from config
$configFile = ROOT_PATH . '/config/businesses/cqc.php';
if (file_exists($configFile)) {
    $cqcConfig = include $configFile;
    $companyName = $cqcConfig['name'] ?? $companyName;
}

$pageTitle = "Faktur " . $invoice['invoice_number'];

// Terbilang function
function terbilang($angka) {
    $angka = abs($angka);
    $huruf = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
    $temp = "";
    if ($angka < 12) {
        $temp = " " . $huruf[$angka];
    } else if ($angka < 20) {
        $temp = terbilang($angka - 10) . " belas";
    } else if ($angka < 100) {
        $temp = terbilang($angka / 10) . " puluh" . terbilang($angka % 10);
    } else if ($angka < 200) {
        $temp = " seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $temp = terbilang($angka / 100) . " ratus" . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $temp = " seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $temp = terbilang($angka / 1000) . " ribu" . terbilang($angka % 1000);
    } else if ($angka < 1000000000) {
        $temp = terbilang($angka / 1000000) . " juta" . terbilang($angka % 1000000);
    } else if ($angka < 1000000000000) {
        $temp = terbilang($angka / 1000000000) . " milyar" . terbilang(fmod($angka, 1000000000));
    } else if ($angka < 1000000000000000) {
        $temp = terbilang($angka / 1000000000000) . " triliun" . terbilang(fmod($angka, 1000000000000));
    }
    return $temp;
}
$totalTerbilang = ucwords(trim(terbilang($invoice['total_amount']))) . " Rupiah";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px; line-height: 1.5; color: #333;
            background: <?php echo $print ? '#fff' : '#f5f5f5'; ?>;
        }
        
        .container {
            max-width: 210mm; margin: 0 auto; padding: 20px;
            <?php if (!$print): ?>
            background: #fff; box-shadow: 0 0 20px rgba(0,0,0,0.1);
            <?php endif; ?>
        }
        
        /* Header */
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 3px solid #0d1f3c; }
        .company-info h1 { font-size: 20px; color: #0d1f3c; margin-bottom: 4px; }
        .company-info p { color: #666; font-size: 10px; }
        .invoice-title { text-align: right; }
        .invoice-title h2 { font-size: 24px; color: #0d1f3c; letter-spacing: 2px; }
        .invoice-title .badge { 
            display: inline-block; padding: 4px 12px; border-radius: 4px; 
            font-size: 10px; font-weight: 700; margin-top: 5px;
        }
        .badge-draft { background: #f1f5f9; color: #475569; }
        .badge-sent { background: #dbeafe; color: #1d4ed8; }
        .badge-paid { background: #dcfce7; color: #15803d; }
        .badge-partial { background: #fef3c7; color: #b45309; }
        .badge-overdue { background: #fee2e2; color: #dc2626; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .info-box { background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 3px solid #f0b429; }
        .info-box h4 { font-size: 10px; color: #64748b; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
        .info-box p { margin-bottom: 3px; font-size: 11px; }
        .info-box strong { color: #0d1f3c; }

        /* Invoice Details */
        .invoice-details { margin-bottom: 25px; }
        .invoice-details table { width: 100%; border-collapse: collapse; }
        .invoice-details th, .invoice-details td { padding: 12px 15px; text-align: left; border: 1px solid #e2e8f0; }
        .invoice-details th { background: #0d1f3c; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        .invoice-details tbody tr:nth-child(even) { background: #f8fafc; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }

        /* Calculation Table */
        .calc-table { width: 350px; margin-left: auto; margin-bottom: 25px; }
        .calc-table table { width: 100%; border-collapse: collapse; }
        .calc-table td { padding: 8px 12px; font-size: 11px; }
        .calc-table .label { color: #64748b; }
        .calc-table .value { text-align: right; font-weight: 600; }
        .calc-table .total-row { background: linear-gradient(135deg, #0d1f3c, #1a3a5c); }
        .calc-table .total-row td { color: #fff; font-size: 14px; font-weight: 700; padding: 12px; }
        .calc-table .minus { color: #ef4444; }
        .calc-table .plus { color: #10b981; }

        /* Terbilang */
        .terbilang { 
            background: linear-gradient(135deg, rgba(240,180,41,0.1), rgba(240,180,41,0.05));
            border: 1px dashed #f0b429; padding: 12px 15px; border-radius: 6px;
            margin-bottom: 25px; font-style: italic;
        }
        .terbilang strong { color: #0d1f3c; }

        /* Notes */
        .notes { background: #f8fafc; padding: 12px 15px; border-radius: 6px; margin-bottom: 25px; font-size: 10px; color: #64748b; }

        /* Signatures */
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px; }
        .signature-box { text-align: center; }
        .signature-box p { font-size: 10px; color: #64748b; margin-bottom: 60px; }
        .signature-line { border-top: 1px solid #333; padding-top: 5px; font-weight: 600; }

        /* Print Controls (hide when printing) */
        .print-controls { 
            position: fixed; top: 20px; right: 20px; 
            display: flex; gap: 10px; z-index: 100;
        }
        .print-controls button, .print-controls a {
            padding: 10px 20px; border: none; border-radius: 6px;
            font-weight: 600; font-size: 12px; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-print { background: #0d1f3c; color: #fff; }
        .btn-back { background: #f1f5f9; color: #475569; }

        @media print {
            body { background: #fff; }
            .container { box-shadow: none; padding: 0; }
            .print-controls { display: none !important; }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body>
    <?php if (!$print): ?>
    <div class="print-controls">
        <a href="index-cqc.php" class="btn-back">← Kembali</a>
        <button class="btn-print" onclick="window.print()">🖨️ Cetak</button>
    </div>
    <?php endif; ?>

    <div class="container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-info">
                <h1>☀️ <?php echo htmlspecialchars($companyName); ?></h1>
                <p>Solar Panel Installation Contractor</p>
            </div>
            <div class="invoice-title">
                <h2>INVOICE</h2>
                <div><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></div>
                <?php
                $badgeClass = 'badge-' . $invoice['payment_status'];
                $statusLabel = [
                    'draft' => 'DRAFT',
                    'sent' => 'TERKIRIM',
                    'paid' => 'LUNAS',
                    'partial' => 'SEBAGIAN',
                    'overdue' => 'JATUH TEMPO'
                ];
                ?>
                <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusLabel[$invoice['payment_status']] ?? strtoupper($invoice['payment_status']); ?></span>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-box">
                <h4>Kepada</h4>
                <p><strong><?php echo htmlspecialchars($invoice['client_name']); ?></strong></p>
                <?php if ($invoice['client_phone']): ?>
                <p>📞 <?php echo htmlspecialchars($invoice['client_phone']); ?></p>
                <?php endif; ?>
                <?php if ($invoice['client_email']): ?>
                <p>✉️ <?php echo htmlspecialchars($invoice['client_email']); ?></p>
                <?php endif; ?>
                <?php if ($invoice['location']): ?>
                <p>📍 <?php echo htmlspecialchars($invoice['location']); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-box">
                <h4>Detail Faktur</h4>
                <p><strong>Tanggal:</strong> <?php echo date('d F Y', strtotime($invoice['invoice_date'])); ?></p>
                <?php if ($invoice['due_date']): ?>
                <p><strong>Jatuh Tempo:</strong> <?php echo date('d F Y', strtotime($invoice['due_date'])); ?></p>
                <?php endif; ?>
                <p><strong>Proyek:</strong> [<?php echo htmlspecialchars($invoice['project_code']); ?>] <?php echo htmlspecialchars($invoice['project_name']); ?></p>
                <?php if ($invoice['solar_capacity_kwp']): ?>
                <p><strong>Kapasitas:</strong> <?php echo number_format($invoice['solar_capacity_kwp'], 1); ?> kWp</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-details">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Keterangan</th>
                        <th class="text-center" style="width: 100px;">Termin</th>
                        <th class="text-center" style="width: 80px;">%</th>
                        <th class="text-right" style="width: 150px;">Nilai Kontrak</th>
                        <th class="text-right" style="width: 150px;">DPP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">1</td>
                        <td>
                            <strong><?php echo htmlspecialchars($invoice['description'] ?: 'Pembayaran Termin ' . $invoice['termin_number']); ?></strong>
                            <br><small style="color: #64748b;">Proyek: <?php echo htmlspecialchars($invoice['project_name']); ?></small>
                        </td>
                        <td class="text-center">
                            <span style="background: #f0b429; color: #0d1f3c; padding: 4px 12px; border-radius: 12px; font-weight: 700;">
                                Termin <?php echo $invoice['termin_number']; ?>
                            </span>
                        </td>
                        <td class="text-center"><strong><?php echo number_format($invoice['percentage'], 1); ?>%</strong></td>
                        <td class="text-right">Rp <?php echo number_format($invoice['contract_value'], 0, ',', '.'); ?></td>
                        <td class="text-right"><strong>Rp <?php echo number_format($invoice['base_amount'], 0, ',', '.'); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Calculation -->
        <div class="calc-table">
            <table>
                <tr>
                    <td class="label">DPP (Dasar Pengenaan Pajak)</td>
                    <td class="value">Rp <?php echo number_format($invoice['base_amount'], 0, ',', '.'); ?></td>
                </tr>
                <?php if ($invoice['ppn_amount'] > 0): ?>
                <tr>
                    <td class="label">(+) PPN <?php echo number_format($invoice['ppn_percentage'], 1); ?>%</td>
                    <td class="value plus">+ Rp <?php echo number_format($invoice['ppn_amount'], 0, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($invoice['pph_amount'] > 0): ?>
                <tr>
                    <td class="label">(-) PPh <?php echo number_format($invoice['pph_percentage'], 1); ?>%</td>
                    <td class="value minus">- Rp <?php echo number_format($invoice['pph_amount'], 0, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($invoice['retention_amount'] > 0): ?>
                <tr>
                    <td class="label">(-) Retensi <?php echo number_format($invoice['retention_percentage'], 1); ?>%</td>
                    <td class="value minus">- Rp <?php echo number_format($invoice['retention_amount'], 0, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td>TOTAL TAGIHAN</td>
                    <td class="value">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></td>
                </tr>
            </table>
        </div>

        <!-- Terbilang -->
        <div class="terbilang">
            <strong>Terbilang:</strong> <?php echo $totalTerbilang; ?>
        </div>

        <?php if ($invoice['notes']): ?>
        <div class="notes">
            <strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
        </div>
        <?php endif; ?>

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-box">
                <p>Diterima oleh,</p>
                <div class="signature-line"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
            </div>
            <div class="signature-box">
                <p>Hormat kami,</p>
                <div class="signature-line"><?php echo $companyName; ?></div>
            </div>
        </div>
    </div>

    <?php if ($print): ?>
    <script>window.onload = function() { window.print(); }</script>
    <?php endif; ?>
</body>
</html>
