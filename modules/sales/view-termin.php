<?php
/**
 * CQC Professional Invoice
 * Professional invoice display and print - English version
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
    die("Invoice not found.");
}

// Get CQC business settings from master database
$db = Database::getInstance();
$businessId = 7; // CQC business ID

// Default company info - REQUIRED fields
$companyName = 'CQC Enjiniring';
$companyTagline = 'Solar Panel Installation Contractor';
$companyAddress = 'Address not configured';
$companyCity = '';
$companyPhone = '-';
$companyEmail = '-';
$companyNPWP = '-';
$companyLogo = '';
$bankName = '';
$bankAccount = '';
$bankHolder = '';

// Try to load from business_settings
try {
    $settings = $db->fetchAll("SELECT setting_key, setting_value FROM business_settings WHERE business_id = ?", [$businessId]);
    foreach ($settings as $s) {
        switch ($s['setting_key']) {
            case 'business_name': if ($s['setting_value']) $companyName = $s['setting_value']; break;
            case 'tagline': if ($s['setting_value']) $companyTagline = $s['setting_value']; break;
            case 'address': if ($s['setting_value']) $companyAddress = $s['setting_value']; break;
            case 'city': if ($s['setting_value']) $companyCity = $s['setting_value']; break;
            case 'phone': if ($s['setting_value']) $companyPhone = $s['setting_value']; break;
            case 'email': if ($s['setting_value']) $companyEmail = $s['setting_value']; break;
            case 'npwp': if ($s['setting_value']) $companyNPWP = $s['setting_value']; break;
            case 'logo': if ($s['setting_value']) $companyLogo = $s['setting_value']; break;
            case 'bank_name': if ($s['setting_value']) $bankName = $s['setting_value']; break;
            case 'bank_account': if ($s['setting_value']) $bankAccount = $s['setting_value']; break;
            case 'bank_holder': if ($s['setting_value']) $bankHolder = $s['setting_value']; break;
        }
    }
} catch (Exception $e) {
    // Settings table might not exist
}

// Try load from CQC config file as fallback
$configPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__));
$configFile = $configPath . '/config/businesses/cqc.php';
if (file_exists($configFile)) {
    $cqcConfig = include $configFile;
    if ($companyName === 'CQC Enjiniring' && isset($cqcConfig['name'])) {
        $companyName = $cqcConfig['name'];
    }
    if (empty($companyLogo) && isset($cqcConfig['logo'])) {
        $companyLogo = $cqcConfig['logo'];
    }
    if ($companyAddress === 'Address not configured' && isset($cqcConfig['address'])) {
        $companyAddress = $cqcConfig['address'];
    }
    if (empty($companyCity) && isset($cqcConfig['city'])) {
        $companyCity = $cqcConfig['city'];
    }
    if ($companyPhone === '-' && isset($cqcConfig['phone'])) {
        $companyPhone = $cqcConfig['phone'];
    }
    if ($companyEmail === '-' && isset($cqcConfig['email'])) {
        $companyEmail = $cqcConfig['email'];
    }
    if ($companyNPWP === '-' && isset($cqcConfig['npwp'])) {
        $companyNPWP = $cqcConfig['npwp'];
    }
    if (empty($bankName) && isset($cqcConfig['bank_name'])) {
        $bankName = $cqcConfig['bank_name'];
    }
    if (empty($bankAccount) && isset($cqcConfig['bank_account'])) {
        $bankAccount = $cqcConfig['bank_account'];
    }
    if (empty($bankHolder) && isset($cqcConfig['bank_holder'])) {
        $bankHolder = $cqcConfig['bank_holder'];
    }
}

// Full address
$fullAddress = $companyAddress;
if ($companyCity) {
    $fullAddress .= ', ' . $companyCity;
}

$pageTitle = "Invoice " . $invoice['invoice_number'];

// Number to words in English for Indonesian Rupiah
function numberToWords($number) {
    $number = abs(intval($number));
    $words = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
              'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
    if ($number < 20) {
        return $words[$number];
    }
    if ($number < 100) {
        return $tens[intval($number / 10)] . ($number % 10 ? ' ' . $words[$number % 10] : '');
    }
    if ($number < 1000) {
        return $words[intval($number / 100)] . ' Hundred' . ($number % 100 ? ' ' . numberToWords($number % 100) : '');
    }
    if ($number < 1000000) {
        return numberToWords(intval($number / 1000)) . ' Thousand' . ($number % 1000 ? ' ' . numberToWords($number % 1000) : '');
    }
    if ($number < 1000000000) {
        return numberToWords(intval($number / 1000000)) . ' Million' . ($number % 1000000 ? ' ' . numberToWords($number % 1000000) : '');
    }
    if ($number < 1000000000000) {
        return numberToWords(intval($number / 1000000000)) . ' Billion' . ($number % 1000000000 ? ' ' . numberToWords(intval($number % 1000000000)) : '');
    }
    return numberToWords(intval($number / 1000000000000)) . ' Trillion' . ($number % 1000000000000 ? ' ' . numberToWords(intval($number % 1000000000000)) : '');
}

$totalInWords = numberToWords($invoice['total_amount']) . ' Rupiah';

// Format date for invoice (renamed to avoid conflict with global function)
function formatInvDate($date) {
    return date('F j, Y', strtotime($date));
}

// Logo path check
$logoPath = '';
$logoExists = false;
if ($companyLogo) {
    // Check various possible paths
    // Logo might be stored as "logos/filename.png" or just "filename.png"
    $possiblePaths = [
        $configPath . '/uploads/' . $companyLogo,  // Direct path like "logos/file.png"
        $configPath . '/uploads/logos/' . $companyLogo, // Just filename, look in logos folder
        $configPath . '/assets/images/' . $companyLogo,
    ];
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $logoExists = true;
            // Determine URL based on path
            if (strpos($companyLogo, 'logos/') === 0) {
                // Logo stored as "logos/filename.png"
                $logoPath = BASE_URL . '/uploads/' . $companyLogo;
            } elseif (strpos($path, '/uploads/logos/') !== false) {
                $logoPath = BASE_URL . '/uploads/logos/' . $companyLogo;
            } elseif (strpos($path, '/uploads/') !== false) {
                $logoPath = BASE_URL . '/uploads/' . $companyLogo;
            } else {
                $logoPath = BASE_URL . '/assets/images/' . $companyLogo;
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
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
            --gold-dark: #c49a1a;
            --success: #10b981;
            --danger: #ef4444;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
        }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 11px; 
            line-height: 1.5; 
            color: #333;
            background: <?php echo $print ? '#fff' : '#e5e7eb'; ?>;
        }
        
        .page {
            max-width: 210mm; 
            min-height: 297mm;
            margin: <?php echo $print ? '0' : '20px auto'; ?>; 
            padding: 0;
            background: #fff; 
            <?php if (!$print): ?>
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            <?php endif; ?>
        }
        
        /* Header Section */
        .header {
            display: flex;
            justify-content: space-between;
            padding: 30px 40px 25px;
            border-bottom: 3px solid var(--navy);
        }
        
        .company-block {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        
        .logo-box {
            width: 70px;
            height: 70px;
            background: var(--gray-100);
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .logo-box img {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }
        
        .logo-box .no-logo {
            font-size: 9px;
            color: var(--gray-400);
            text-align: center;
            padding: 5px;
        }
        
        .company-info h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 4px;
            letter-spacing: -0.3px;
        }
        
        .company-info .tagline {
            font-size: 10px;
            color: var(--gold-dark);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .company-contact {
            font-size: 10px;
            color: var(--gray-600);
            line-height: 1.6;
        }
        
        .company-contact .row {
            display: flex;
            gap: 5px;
            margin-bottom: 2px;
        }
        
        .company-contact .icon {
            width: 14px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .invoice-header {
            text-align: right;
        }
        
        .invoice-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: 4px;
            margin-bottom: 8px;
        }
        
        .invoice-number {
            font-size: 14px;
            font-weight: 700;
            color: var(--gray-700);
            margin-bottom: 15px;
        }
        
        .invoice-meta {
            font-size: 10px;
            color: var(--gray-500);
            text-align: right;
        }
        
        .invoice-meta .row {
            margin-bottom: 3px;
        }
        
        .invoice-meta strong {
            color: var(--gray-700);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }
        
        .status-draft { background: var(--gray-200); color: var(--gray-600); }
        .status-sent { background: #dbeafe; color: #1d4ed8; }
        .status-paid { background: #d1fae5; color: #059669; }
        .status-partial { background: #fef3c7; color: #d97706; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        
        /* Content */
        .content {
            padding: 30px 40px;
        }
        
        /* Bill To Section */
        .parties-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .party-box {
            padding: 20px;
            background: var(--gray-50);
            border-radius: 8px;
            border-left: 4px solid var(--gold);
        }
        
        .party-box h4 {
            font-size: 9px;
            font-weight: 700;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
        }
        
        .party-box .name {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 8px;
        }
        
        .party-box .info {
            font-size: 10px;
            color: var(--gray-600);
            line-height: 1.7;
        }
        
        .party-box .info .row {
            display: flex;
            gap: 8px;
            margin-bottom: 3px;
        }
        
        /* Invoice Table */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        
        .invoice-table th {
            background: var(--navy);
            color: #fff;
            padding: 12px 14px;
            text-align: left;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .invoice-table th:first-child { border-radius: 6px 0 0 0; }
        .invoice-table th:last-child { border-radius: 0 6px 0 0; }
        
        .invoice-table td {
            padding: 14px;
            border-bottom: 1px solid var(--gray-200);
            font-size: 11px;
        }
        
        .invoice-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .invoice-table tbody tr:hover {
            background: var(--gray-50);
        }
        
        .term-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--navy);
            font-size: 13px;
            font-weight: 800;
            border-radius: 8px;
        }
        
        .item-title {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 3px;
        }
        
        .item-subtitle {
            font-size: 9px;
            color: var(--gray-400);
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Summary Section */
        .summary-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 25px;
        }
        
        .summary-table {
            width: 350px;
            background: var(--gray-50);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 16px;
            border-bottom: 1px solid var(--gray-200);
            font-size: 11px;
        }
        
        .summary-row:last-of-type {
            border-bottom: none;
        }
        
        .summary-row .label {
            color: var(--gray-500);
        }
        
        .summary-row .value {
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .summary-row .value.add { color: var(--success); }
        .summary-row .value.sub { color: var(--danger); }
        
        .summary-total {
            background: var(--navy);
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .summary-total .label {
            color: rgba(255,255,255,0.7);
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .summary-total .value {
            color: var(--gold);
            font-size: 18px;
            font-weight: 800;
        }
        
        /* Amount in Words */
        .amount-words {
            background: linear-gradient(135deg, rgba(240,180,41,0.06), rgba(240,180,41,0.02));
            border: 1px solid rgba(240,180,41,0.3);
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 25px;
        }
        
        .amount-words .label {
            font-size: 9px;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        
        .amount-words .text {
            font-size: 11px;
            font-weight: 600;
            color: var(--navy);
            font-style: italic;
        }
        
        /* Bank Details */
        .bank-section {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 25px;
        }
        
        .bank-section h5 {
            font-size: 9px;
            font-weight: 700;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
        }
        
        .bank-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .bank-item .label {
            font-size: 9px;
            color: var(--gray-400);
            margin-bottom: 2px;
        }
        
        .bank-item .value {
            font-size: 11px;
            font-weight: 700;
            color: var(--navy);
        }
        
        /* Notes */
        .notes-section {
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
            padding: 14px 18px;
            border-radius: 0 6px 6px 0;
            margin-bottom: 25px;
        }
        
        .notes-section .label {
            font-size: 9px;
            font-weight: 700;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        
        .notes-section .text {
            font-size: 10px;
            color: #78350f;
            line-height: 1.6;
        }
        
        /* Terms */
        .terms-section {
            border-top: 1px solid var(--gray-200);
            padding-top: 20px;
            margin-bottom: 30px;
        }
        
        .terms-section h5 {
            font-size: 9px;
            font-weight: 700;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
        }
        
        .terms-section ul {
            font-size: 9px;
            color: var(--gray-500);
            margin-left: 16px;
            line-height: 1.8;
        }
        
        /* Signatures */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            margin-top: 40px;
        }
        
        .sig-block {
            text-align: center;
        }
        
        .sig-block .role {
            font-size: 10px;
            color: var(--gray-500);
            margin-bottom: 60px;
        }
        
        .sig-block .line {
            border-top: 1px solid var(--navy);
            padding-top: 8px;
        }
        
        .sig-block .name {
            font-size: 11px;
            font-weight: 700;
            color: var(--navy);
        }
        
        /* Footer */
        .footer {
            background: var(--gray-50);
            padding: 15px 40px;
            text-align: center;
            font-size: 9px;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }
        
        /* Print Controls */
        .print-controls { 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            display: flex; 
            gap: 10px; 
            z-index: 100;
        }
        
        .print-controls button, .print-controls a {
            padding: 12px 24px; 
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
            color: var(--gray-600); 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-back:hover { background: var(--gray-50); }

        @media print {
            body { background: #fff; }
            .page { box-shadow: none; margin: 0; }
            .print-controls { display: none !important; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body>
    <?php if (!$print): ?>
    <div class="print-controls">
        <a href="index-cqc.php" class="btn-back">← Back</a>
        <button class="btn-print" onclick="window.print()">Print Invoice</button>
    </div>
    <?php endif; ?>

    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="company-block">
                <div class="logo-box">
                    <?php if ($logoExists): ?>
                        <img src="<?php echo $logoPath; ?>" alt="<?php echo htmlspecialchars($companyName); ?>">
                    <?php else: ?>
                        <div class="no-logo">LOGO</div>
                    <?php endif; ?>
                </div>
                <div class="company-info">
                    <h1><?php echo htmlspecialchars($companyName); ?></h1>
                    <div class="tagline"><?php echo htmlspecialchars($companyTagline); ?></div>
                    <div class="company-contact">
                        <div class="row"><span class="icon">📍</span> <?php echo htmlspecialchars($fullAddress); ?></div>
                        <div class="row"><span class="icon">📞</span> <?php echo htmlspecialchars($companyPhone); ?></div>
                        <div class="row"><span class="icon">✉️</span> <?php echo htmlspecialchars($companyEmail); ?></div>
                        <div class="row"><span class="icon">🏢</span> NPWP: <?php echo htmlspecialchars($companyNPWP); ?></div>
                    </div>
                </div>
            </div>
            <div class="invoice-header">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                <div class="invoice-meta">
                    <div class="row"><strong>Date:</strong> <?php echo formatInvDate($invoice['invoice_date']); ?></div>
                    <?php if ($invoice['due_date']): ?>
                    <div class="row"><strong>Due Date:</strong> <?php echo formatInvDate($invoice['due_date']); ?></div>
                    <?php endif; ?>
                </div>
                <?php
                $statusLabels = [
                    'draft' => 'DRAFT',
                    'sent' => 'SENT',
                    'paid' => 'PAID',
                    'partial' => 'PARTIAL',
                    'overdue' => 'OVERDUE'
                ];
                ?>
                <span class="status-badge status-<?php echo $invoice['payment_status']; ?>">
                    <?php echo $statusLabels[$invoice['payment_status']] ?? strtoupper($invoice['payment_status']); ?>
                </span>
            </div>
        </div>
        
        <div class="content">
            <!-- Bill To / Project Info -->
            <div class="parties-row">
                <div class="party-box">
                    <h4>Bill To</h4>
                    <div class="name"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                    <div class="info">
                        <?php if ($invoice['client_phone']): ?>
                        <div class="row">Phone: <?php echo htmlspecialchars($invoice['client_phone']); ?></div>
                        <?php endif; ?>
                        <?php if ($invoice['client_email']): ?>
                        <div class="row">Email: <?php echo htmlspecialchars($invoice['client_email']); ?></div>
                        <?php endif; ?>
                        <?php if ($invoice['location']): ?>
                        <div class="row">Address: <?php echo htmlspecialchars($invoice['location']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="party-box">
                    <h4>Project Details</h4>
                    <div class="name"><?php echo htmlspecialchars($invoice['project_name']); ?></div>
                    <div class="info">
                        <div class="row">Code: <?php echo htmlspecialchars($invoice['project_code']); ?></div>
                        <?php if ($invoice['solar_capacity_kwp']): ?>
                        <div class="row">Capacity: <?php echo number_format($invoice['solar_capacity_kwp'], 2); ?> kWp</div>
                        <?php endif; ?>
                        <?php if ($invoice['location']): ?>
                        <div class="row">Location: <?php echo htmlspecialchars($invoice['location']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Invoice Items -->
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="width: 70px;" class="text-center">Term</th>
                        <th>Description</th>
                        <th style="width: 70px;" class="text-center">%</th>
                        <th style="width: 130px;" class="text-right">Contract Value</th>
                        <th style="width: 130px;" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">
                            <span class="term-badge"><?php echo $invoice['termin_number']; ?></span>
                        </td>
                        <td>
                            <div class="item-title"><?php echo htmlspecialchars($invoice['description'] ?: 'Progress Payment Term ' . $invoice['termin_number']); ?></div>
                            <div class="item-subtitle">Project: <?php echo htmlspecialchars($invoice['project_name']); ?></div>
                        </td>
                        <td class="text-center" style="font-weight: 700; color: var(--gold-dark);">
                            <?php echo number_format($invoice['percentage'], 1); ?>%
                        </td>
                        <td class="text-right">IDR <?php echo number_format($invoice['contract_value'], 0, ',', '.'); ?></td>
                        <td class="text-right" style="font-weight: 700;">IDR <?php echo number_format($invoice['base_amount'], 0, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Summary -->
            <div class="summary-wrapper">
                <div class="summary-table">
                    <div class="summary-row">
                        <span class="label">Subtotal (DPP)</span>
                        <span class="value">IDR <?php echo number_format($invoice['base_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <?php if ($invoice['ppn_amount'] > 0): ?>
                    <div class="summary-row">
                        <span class="label">(+) VAT <?php echo number_format($invoice['ppn_percentage'], 1); ?>%</span>
                        <span class="value add">+ IDR <?php echo number_format($invoice['ppn_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['pph_amount'] > 0): ?>
                    <div class="summary-row">
                        <span class="label">(-) Income Tax <?php echo number_format($invoice['pph_percentage'], 1); ?>%</span>
                        <span class="value sub">- IDR <?php echo number_format($invoice['pph_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['retention_amount'] > 0): ?>
                    <div class="summary-row">
                        <span class="label">(-) Retention <?php echo number_format($invoice['retention_percentage'], 1); ?>%</span>
                        <span class="value sub">- IDR <?php echo number_format($invoice['retention_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-total">
                        <span class="label">Total Due</span>
                        <span class="value">IDR <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Amount in Words -->
            <div class="amount-words">
                <div class="label">Amount in Words</div>
                <div class="text"># <?php echo $totalInWords; ?> #</div>
            </div>
            
            <!-- Bank Details -->
            <?php if ($bankName || $bankAccount): ?>
            <div class="bank-section">
                <h5>Payment Information</h5>
                <div class="bank-grid">
                    <?php if ($bankName): ?>
                    <div class="bank-item">
                        <div class="label">Bank Name</div>
                        <div class="value"><?php echo htmlspecialchars($bankName); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($bankAccount): ?>
                    <div class="bank-item">
                        <div class="label">Account Number</div>
                        <div class="value"><?php echo htmlspecialchars($bankAccount); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($bankHolder): ?>
                    <div class="bank-item">
                        <div class="label">Account Holder</div>
                        <div class="value"><?php echo htmlspecialchars($bankHolder); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($invoice['notes']): ?>
            <div class="notes-section">
                <div class="label">Notes</div>
                <div class="text"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></div>
            </div>
            <?php endif; ?>
            
            <!-- Terms & Conditions -->
            <div class="terms-section">
                <h5>Terms & Conditions</h5>
                <ul>
                    <li>Payment is due within 14 days from invoice date unless otherwise specified.</li>
                    <li>Please include the invoice number as payment reference.</li>
                    <li>Late payments may be subject to additional charges.</li>
                </ul>
            </div>
            
            <!-- Signatures -->
            <div class="signatures">
                <div class="sig-block">
                    <div class="role">Received By</div>
                    <div class="line">
                        <div class="name"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                    </div>
                </div>
                <div class="sig-block">
                    <div class="role">Authorized Signature</div>
                    <div class="line">
                        <div class="name"><?php echo htmlspecialchars($companyName); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            Thank you for your business. For any inquiries, please contact <?php echo htmlspecialchars($companyEmail); ?>
        </div>
    </div>

    <?php if ($print): ?>
    <script>window.onload = function() { window.print(); }</script>
    <?php endif; ?>
</body>
</html>
