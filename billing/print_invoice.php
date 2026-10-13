<?php
require_once __DIR__ . '/../config/database.php';
require_auth();

$invoiceNo = sanitize($_GET['invoice_no'] ?? '');

if (empty($invoiceNo)) {
    die('Invoice number not provided.');
}

$db = get_db();

// Fetch Sale Header
$stmt = $db->prepare("
    SELECT s.*, c.name as customer_name, c.mobile as customer_mobile, c.address as customer_address, c.gst_number as customer_gst, u.name as cashier_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.invoice_no = ?
");
$stmt->execute([$invoiceNo]);
$sale = $stmt->fetch();

if (!$sale) {
    die('Invoice not found.');
}

// Fetch Sale Items with product details
$itemStmt = $db->prepare("
    SELECT si.*, COALESCE(p.unit, 'Pcs') as unit, COALESCE(p.product_code, 'PRD-HW') as product_code, p.brand
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$itemStmt->execute([$sale['id']]);
$items = $itemStmt->fetchAll();

// Store Settings
$shopName = get_setting('shop_name', 'SARASWATI HARDWARE');
$shopAddress = get_setting('shop_address', '123 Main Hardware Market, Station Road, City');
$shopPhone = get_setting('shop_phone', '+91 98765 43210');
$shopEmail = get_setting('shop_email', 'contact@saraswatihardware.com');
$gstin = get_setting('gstin', '27ABCDE1234F1Z5');
$stateCode = substr($gstin, 0, 2) ?: '27';
$footerNote = get_setting('footer_note', 'Thank you for your business! Visit again.');

// Determine Bill Type (GST or Non-GST)
$rawType = $_GET['type'] ?? ($sale['bill_type'] ?? 'gst');
$billType = in_array(strtolower($rawType), ['non_gst', 'nongst', 'estimate', 'cash_memo']) ? 'non_gst' : 'gst';
$isNonGst = ($billType === 'non_gst');
$invoiceBadgeText = $isNonGst ? 'ESTIMATE / CASH MEMO' : 'TAX INVOICE';
$pageDocTitle = ($isNonGst ? 'Cash Memo / Estimate - ' : 'Tax Invoice - ') . sanitize($sale['invoice_no']);

// Number to Words function for Indian Rupee format
if (!function_exists('convertNumberToIndianWords')) {
    function convertNumberToIndianWords($number) {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_length = strlen($no);
        $i = 0;
        $str = array();
        $words = array(
            0 => '', 1 => 'One', 2 => 'Two',
            3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
            7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
            13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
            19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
            40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
            70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
        );
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
        while ($i < $digits_length) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? '' : null;
                $hundred = ($counter == 1 && !empty($str[0])) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred
                    : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else $str[] = null;
        }
        $Rupees = implode('', array_reverse($str));
        $paise = ($decimal > 0) ? " and " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
        return 'Rupees ' . ($Rupees ? trim($Rupees) : 'Zero') . $paise . ' Only';
    }
}

$amountInWords = convertNumberToIndianWords($sale['grand_total']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageDocTitle; ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- html2pdf for Direct Client-side PDF Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary-dark: #0f172a;
            --border-color: #334155;
            --border-light: #cbd5e1;
            --table-head-bg: #f1f5f9;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 13px;
            color: #0f172a;
            background: #e2e8f0;
            padding: 20px 10px;
            line-height: 1.4;
        }

        /* Top Action Bar */
        .actions-bar {
            max-width: 850px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .actions-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary { background: #2563eb; color: #ffffff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #475569; color: #ffffff; }
        .btn-secondary:hover { background: #334155; }
        .btn-outline { background: #ffffff; color: #0f172a; border: 1px solid #cbd5e1; }
        .btn-outline:hover { background: #f8fafc; }

        /* A4 Professional Invoice Sheet */
        .invoice-sheet {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm 15mm 20mm 15mm;
            border: 1px solid #cbd5e1;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
        }

        /* Invoice Header */
        .inv-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .inv-brand h1 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .inv-brand .tagline {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .inv-brand .contact-info {
            font-size: 11px;
            color: #334155;
            line-height: 1.5;
        }

        .inv-badge-box {
            text-align: right;
        }

        .tax-invoice-badge {
            background: #0f172a;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 1.5px;
            padding: 5px 14px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .gstin-pill {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            display: inline-block;
            margin-bottom: 4px;
        }

        /* 2-Column Meta Details Box */
        .inv-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
        }

        .meta-col {
            padding: 10px 14px;
        }

        .meta-col:first-child {
            border-right: 1px solid var(--border-color);
        }

        .meta-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .meta-row {
            display: flex;
            margin-bottom: 4px;
            font-size: 12px;
        }

        .meta-label {
            width: 120px;
            color: #64748b;
            font-weight: 500;
            flex-shrink: 0;
        }

        .meta-val {
            font-weight: 600;
            color: #0f172a;
        }

        /* Items Table */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border-color);
            margin-bottom: 14px;
        }

        .inv-table th {
            background: var(--table-head-bg);
            border: 1px solid var(--border-color);
            padding: 8px 6px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: left;
            color: #0f172a;
        }

        .inv-table td {
            border: 1px solid var(--border-color);
            padding: 7px 6px;
            font-size: 11.5px;
            vertical-align: middle;
        }

        .inv-table th.num, .inv-table td.num { text-align: right; }
        .inv-table th.center, .inv-table td.center { text-align: center; }

        .item-name {
            font-weight: 700;
            color: #0f172a;
        }

        .item-sku {
            font-size: 10px;
            color: #64748b;
        }

        /* Summary & Totals Grid */
        .inv-summary-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 15px;
            margin-bottom: 16px;
        }

        .amount-words-card {
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 4px;
            font-size: 11.5px;
        }

        .amount-words-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
        }

        .amount-words-val {
            font-weight: 700;
            color: #0f172a;
            font-style: italic;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border-color);
        }

        .totals-table td {
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #cbd5e1;
        }

        .totals-table td:last-child {
            text-align: right;
            font-weight: 700;
        }

        .grand-total-row {
            background: #0f172a !important;
            color: #ffffff !important;
        }

        .grand-total-row td {
            font-size: 15px !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            border: 1px solid #0f172a !important;
            padding: 8px 10px !important;
        }

        /* Terms & Signatures */
        .inv-footer-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 15px;
            border-top: 1px solid #cbd5e1;
            padding-top: 14px;
            margin-top: 10px;
        }

        .terms-box h6 {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 6px;
            color: #334155;
        }

        .terms-list {
            padding-left: 15px;
            font-size: 10px;
            color: #475569;
            line-height: 1.5;
        }

        .signature-box {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
        }

        .signature-for {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 45px;
            color: #0f172a;
        }

        .signature-line {
            border-top: 1px dashed #334155;
            width: 180px;
            margin-bottom: 4px;
        }

        .signature-label {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .footer-bottom-note {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #64748b;
        }

        /* Thermal Mini-View Override (when switched) */
        body.thermal-mode {
            background: #f1f5f9;
            padding: 10px;
        }

        body.thermal-mode .invoice-sheet {
            width: 80mm;
            min-height: auto;
            padding: 10px;
            font-family: 'Courier Prime', monospace, sans-serif;
            font-size: 11px;
        }

        body.thermal-mode .inv-header {
            flex-direction: column;
            text-align: center;
            border-bottom: 1px dashed #000;
        }

        body.thermal-mode .inv-badge-box {
            text-align: center;
            margin-top: 8px;
        }

        body.thermal-mode .inv-meta-grid,
        body.thermal-mode .inv-summary-grid,
        body.thermal-mode .inv-footer-grid {
            grid-template-columns: 1fr;
        }

        body.thermal-mode .meta-col:first-child {
            border-right: none;
            border-bottom: 1px dashed #000;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }

            .actions-bar {
                display: none !important;
            }

            .invoice-sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 5mm !important;
                width: 100% !important;
                min-height: auto !important;
            }

            @page {
                size: A4;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

<!-- Top Navigation & Action Controls -->
<div class="actions-bar">
    <div class="actions-group">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa-solid fa-print"></i> PRINT INVOICE (A4)
        </button>
        <button onclick="downloadInvoicePDF()" class="btn btn-primary" style="background:#0284c7;">
            <i class="fa-solid fa-file-arrow-down"></i> DOWNLOAD PDF
        </button>
        <button onclick="toggleThermalMode()" id="btn-thermal" class="btn btn-outline">
            <i class="fa-solid fa-receipt"></i> TOGGLE THERMAL SLIP
        </button>
    </div>
    <div class="actions-group">
        <a href="?invoice_no=<?php echo urlencode($invoiceNo); ?>&type=gst" class="btn <?php echo !$isNonGst ? 'btn-primary' : 'btn-outline'; ?>" style="<?php echo !$isNonGst ? 'background:#0f172a;' : ''; ?>" title="View as GST Tax Invoice">
            <i class="fa-solid fa-file-invoice"></i> GST Bill
        </a>
        <a href="?invoice_no=<?php echo urlencode($invoiceNo); ?>&type=non_gst" class="btn <?php echo $isNonGst ? 'btn-primary' : 'btn-outline'; ?>" style="<?php echo $isNonGst ? 'background:#0f172a;' : ''; ?>" title="View as Non-GST Estimate / Cash Memo">
            <i class="fa-solid fa-file-lines"></i> Non-GST Bill
        </a>
        <a href="<?php echo base_url('billing/new_bill.php'); ?>" class="btn btn-secondary">
            <i class="fa-solid fa-plus"></i> NEW BILL (POS)
        </a>
        <a href="<?php echo base_url('admin/index.php'); ?>" class="btn btn-outline">
            <i class="fa-solid fa-gauge"></i> DASHBOARD
        </a>
    </div>
</div>

<!-- Professional Hardware Tax Invoice / Cash Memo Sheet -->
<div class="invoice-sheet" id="invoiceSheet">

    <!-- Store Header -->
    <div class="inv-header">
        <div class="inv-brand">
            <h1><?php echo sanitize($shopName); ?></h1>
            <div class="tagline">Wholesale & Retail: Plumbing, Electrical, Paints, Hardware & Tools</div>
            <div class="contact-info">
                <div><i class="fa-solid fa-location-dot me-1"></i> <?php echo sanitize($shopAddress); ?></div>
                <div><i class="fa-solid fa-phone me-1"></i> Ph: <?php echo sanitize($shopPhone); ?> | <i class="fa-solid fa-envelope me-1"></i> <?php echo sanitize($shopEmail); ?></div>
            </div>
        </div>
        <div class="inv-badge-box">
            <div class="tax-invoice-badge" style="<?php echo $isNonGst ? 'background:#334155;' : ''; ?>">
                <?php echo $invoiceBadgeText; ?>
            </div>
            <?php if (!$isNonGst): ?>
                <div>
                    <span class="gstin-pill">GSTIN: <?php echo sanitize($gstin); ?></span>
                </div>
                <div style="font-size: 11px; font-weight: 600; color: #475569;">
                    State: Maharashtra (Code: <?php echo sanitize($stateCode); ?>)
                </div>
            <?php else: ?>
                <div>
                    <span class="gstin-pill" style="background:#f8fafc; color:#64748b; border:1px dashed #cbd5e1;">RETAIL CASH MEMO</span>
                </div>
                <div style="font-size: 11px; font-weight: 600; color: #475569;">
                    Type: Non-GST Retail Supply
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Billed To & Invoice Meta Information -->
    <div class="inv-meta-grid">
        <!-- Customer Details -->
        <div class="meta-col">
            <div class="meta-title"><i class="fa-solid fa-user me-1"></i> Billed To (Buyer Details)</div>
            <div class="meta-row">
                <span class="meta-label">Customer Name:</span>
                <span class="meta-val"><?php echo sanitize($sale['customer_name'] ?? 'Walk-in Customer'); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Mobile Number:</span>
                <span class="meta-val"><?php echo (!empty($sale['customer_mobile']) && $sale['customer_mobile'] !== '0000000000') ? sanitize($sale['customer_mobile']) : 'N/A'; ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Delivery Site:</span>
                <span class="meta-val"><?php echo sanitize($sale['customer_address'] ?? 'Counter Delivery / Local'); ?></span>
            </div>
            <?php if (!$isNonGst && !empty($sale['customer_gst'])): ?>
                <div class="meta-row">
                    <span class="meta-label">Buyer GSTIN:</span>
                    <span class="meta-val"><?php echo sanitize($sale['customer_gst']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Invoice Details -->
        <div class="meta-col">
            <div class="meta-title"><i class="fa-solid fa-file-invoice me-1"></i> Bill & Dispatch Details</div>
            <div class="meta-row">
                <span class="meta-label"><?php echo $isNonGst ? 'Estimate / Memo No:' : 'Invoice Number:'; ?></span>
                <span class="meta-val" style="font-family:'JetBrains Mono',monospace; font-size:13px;"><?php echo sanitize($sale['invoice_no']); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Date & Time:</span>
                <span class="meta-val"><?php echo date('d-M-Y, h:i A', strtotime($sale['sale_date'])); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Payment Mode:</span>
                <span class="meta-val" style="text-transform:uppercase;"><?php echo sanitize($sale['payment_mode']); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Payment Status:</span>
                <span class="meta-val" style="text-transform:uppercase; color: <?php echo ($sale['payment_status'] === 'paid') ? '#16a34a' : '#dc2626'; ?>;">
                    ● <?php echo sanitize($sale['payment_status']); ?>
                </span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Billed By:</span>
                <span class="meta-val"><?php echo sanitize($sale['cashier_name'] ?? 'Staff Counter'); ?></span>
            </div>
        </div>
    </div>

    <!-- Itemized Hardware Grid -->
    <?php if (!$isNonGst): ?>
        <!-- Full GST Table (10 Columns) -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th class="center" style="width: 32px;">#</th>
                    <th>Description of Hardware Goods</th>
                    <th class="center" style="width: 65px;">HSN</th>
                    <th class="center" style="width: 50px;">Qty</th>
                    <th class="center" style="width: 55px;">Unit</th>
                    <th class="num" style="width: 80px;">Rate (₹)</th>
                    <th class="num" style="width: 85px;">Taxable (₹)</th>
                    <th class="center" style="width: 65px;">GST %</th>
                    <th class="num" style="width: 75px;">GST (₹)</th>
                    <th class="num" style="width: 90px;">Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sr = 1;
                $totalTaxable = 0;
                $totalCgst = 0;
                $totalSgst = 0;

                foreach ($items as $item): 
                    $lineTotal = floatval($item['total_amount']);
                    $gstRate = floatval($item['tax_percent']);
                    $lineTaxable = $gstRate > 0 ? ($lineTotal / (1 + ($gstRate / 100))) : $lineTotal;
                    $lineTax = $lineTotal - $lineTaxable;
                    $totalTaxable += $lineTaxable;
                    $halfTax = $lineTax / 2;
                    $totalCgst += $halfTax;
                    $totalSgst += $halfTax;
                ?>
                    <tr>
                        <td class="center"><?php echo $sr++; ?></td>
                        <td>
                            <div class="item-name"><?php echo sanitize($item['product_name']); ?></div>
                            <div class="item-sku">Item Code: <?php echo sanitize($item['product_code']); ?><?php echo !empty($item['brand']) ? ' | Brand: ' . sanitize($item['brand']) : ''; ?></div>
                        </td>
                        <td class="center" style="font-family:'JetBrains Mono',monospace; font-size:11px;">8481</td>
                        <td class="center fw-bold"><?php echo $item['qty']; ?></td>
                        <td class="center"><?php echo sanitize($item['unit'] ?? 'Pcs'); ?></td>
                        <td class="num"><?php echo number_format($item['rate'], 2); ?></td>
                        <td class="num"><?php echo number_format($lineTaxable, 2); ?></td>
                        <td class="center"><?php echo $item['tax_percent']; ?>%</td>
                        <td class="num"><?php echo number_format($lineTax, 2); ?></td>
                        <td class="num" style="font-weight:700;"><?php echo number_format($lineTotal, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <!-- Simplified Non-GST Table (6 Columns) -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th class="center" style="width: 38px;">#</th>
                    <th>Description of Hardware Goods</th>
                    <th class="center" style="width: 70px;">Qty</th>
                    <th class="center" style="width: 70px;">Unit</th>
                    <th class="num" style="width: 110px;">Rate (₹)</th>
                    <th class="num" style="width: 120px;">Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sr = 1;
                $grossItemsTotal = 0;
                foreach ($items as $item): 
                    $lineTotal = floatval($item['total_amount']);
                    $grossItemsTotal += $lineTotal;
                ?>
                    <tr>
                        <td class="center"><?php echo $sr++; ?></td>
                        <td>
                            <div class="item-name"><?php echo sanitize($item['product_name']); ?></div>
                            <div class="item-sku">Item Code: <?php echo sanitize($item['product_code']); ?><?php echo !empty($item['brand']) ? ' | Brand: ' . sanitize($item['brand']) : ''; ?></div>
                        </td>
                        <td class="center fw-bold"><?php echo $item['qty']; ?></td>
                        <td class="center"><?php echo sanitize($item['unit'] ?? 'Pcs'); ?></td>
                        <td class="num"><?php echo number_format($item['rate'], 2); ?></td>
                        <td class="num" style="font-weight:700;"><?php echo number_format($lineTotal, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Calculations & Summary Grid -->
    <div class="inv-summary-grid">
        <div>
            <!-- Amount in Words -->
            <div class="amount-words-card mb-2">
                <div class="amount-words-label"><i class="fa-solid fa-coins me-1"></i> Amount Chargeable (in words):</div>
                <div class="amount-words-val"><?php echo sanitize($amountInWords); ?></div>
            </div>

            <?php if (!$isNonGst): ?>
                <!-- GST Tax Split Summary -->
                <div class="p-2 border rounded" style="background:#fafafa; font-size:11px;">
                    <div class="d-flex justify-content-between mb-1">
                        <span>CGST Total:</span>
                        <span class="fw-bold">₹<?php echo number_format($totalCgst, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>SGST Total:</span>
                        <span class="fw-bold">₹<?php echo number_format($totalSgst, 2); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-2 border rounded text-muted" style="background:#f8fafc; font-size:11px;">
                    <i class="fa-solid fa-circle-check text-success me-1"></i> Non-GST Retail Supply / Cash Memo. No tax charged.
                </div>
            <?php endif; ?>
        </div>

        <div>
            <table class="totals-table">
                <?php if (!$isNonGst): ?>
                    <tr>
                        <td>Taxable Amount:</td>
                        <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                    </tr>
                    <?php if ($sale['discount_amount'] > 0): ?>
                        <tr>
                            <td style="color: #dc2626;">Discount:</td>
                            <td style="color: #dc2626;">- ₹<?php echo number_format($sale['discount_amount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Total Tax (GST):</td>
                        <td>₹<?php echo number_format($sale['tax_amount'], 2); ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td>Items Subtotal:</td>
                        <td>₹<?php echo number_format($sale['grand_total'] + $sale['discount_amount'], 2); ?></td>
                    </tr>
                    <?php if ($sale['discount_amount'] > 0): ?>
                        <tr>
                            <td style="color: #dc2626;">Discount:</td>
                            <td style="color: #dc2626;">- ₹<?php echo number_format($sale['discount_amount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>

                <tr class="grand-total-row">
                    <td>GRAND TOTAL:</td>
                    <td>₹<?php echo number_format($sale['grand_total'], 2); ?></td>
                </tr>
                <tr>
                    <td>Paid Amount:</td>
                    <td>₹<?php echo number_format($sale['paid_amount'], 2); ?></td>
                </tr>
                <?php if ($sale['due_amount'] > 0): ?>
                    <tr style="background:#fef2f2; font-weight:bold; color:#dc2626;">
                        <td>Balance Due (Udhaar):</td>
                        <td>₹<?php echo number_format($sale['due_amount'], 2); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Terms and Signatures -->
    <div class="inv-footer-grid">
        <div class="terms-box">
            <h6><i class="fa-solid fa-shield-halved me-1"></i> Terms & Conditions:</h6>
            <ol class="terms-list">
                <li>Goods once sold will not be returned or exchanged without this original bill.</li>
                <li>No exchange or return on cut PVC pipes, wire rolls, mixed paints, or customized items.</li>
                <li>Warranty is directly supported by the manufacturing company as per brand guidelines.</li>
                <li>All disputes are subject to local city jurisdiction only. E. & O.E.</li>
            </ol>
        </div>

        <div class="signature-box">
            <div class="signature-for">For <?php echo sanitize($shopName); ?></div>
            <div class="signature-line"></div>
            <div class="signature-label">Authorized Signatory / Seal</div>
        </div>
    </div>

    <div class="footer-bottom-note">
        <p><?php echo sanitize($footerNote); ?> | This is a Computer Generated Hardware <?php echo $isNonGst ? 'Estimate / Cash Memo' : 'Tax Invoice'; ?>.</p>
    </div>

</div>

<script>
function toggleThermalMode() {
    document.body.classList.toggle('thermal-mode');
    const btn = document.getElementById('btn-thermal');
    if (document.body.classList.contains('thermal-mode')) {
        btn.innerHTML = '<i class="fa-solid fa-file-invoice"></i> SWITCH TO A4 SHEET';
    } else {
        btn.innerHTML = '<i class="fa-solid fa-receipt"></i> TOGGLE THERMAL SLIP';
    }
}

function downloadInvoicePDF() {
    const element = document.getElementById('invoiceSheet');
    const invoiceNumber = '<?php echo sanitize($sale["invoice_no"]); ?>';
    const isNonGst = <?php echo $isNonGst ? 'true' : 'false'; ?>;
    const prefix = isNonGst ? 'Estimate_' : 'Invoice_';
    const opt = {
        margin:       [4, 4, 4, 4],
        filename:     `${prefix}${invoiceNumber}.pdf`,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    
    return html2pdf().set(opt).from(element).save();
}

// Auto-trigger print and/or download if requested in URL
window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const shouldDownload = urlParams.get('download') === '1';
    const shouldPrint = urlParams.get('print') === '1';

    if (shouldDownload) {
        setTimeout(function() {
            downloadInvoicePDF();
        }, 500);
    }

    if (shouldPrint) {
        setTimeout(function() {
            window.print();
        }, 1000);
    }
});
</script>

</body>
</html>
