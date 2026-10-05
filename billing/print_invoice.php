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

// Fetch Sale Items
$itemStmt = $db->prepare("
    SELECT si.*, p.unit, p.product_code
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$itemStmt->execute([$sale['id']]);
$items = $itemStmt->fetchAll();

// Store Settings
$shopName = get_setting('shop_name', 'Saraswati Hardware');
$shopAddress = get_setting('shop_address', '123 Main Hardware Market, City');
$shopPhone = get_setting('shop_phone', '+91 98765 43210');
$gstin = get_setting('gstin', '27ABCDE1234F1Z5');
$printerWidth = get_setting('thermal_printer_width', '80mm');
$footerNote = get_setting('footer_note', 'Thank you for shopping with us! Visit again.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo sanitize($sale['invoice_no']); ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier Prime', monospace, sans-serif;
            font-size: 13px;
            color: #000;
            background: #f1f5f9;
            padding: 20px;
        }
        .receipt-card {
            background: #ffffff;
            width: <?php echo ($printerWidth === '58mm') ? '58mm' : '80mm'; ?>;
            margin: 0 auto;
            padding: 12px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
        }
        th, td {
            font-size: 11px;
            padding: 3px 0;
            vertical-align: top;
        }
        .actions {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-card { border: none; box-shadow: none; width: 100%; margin: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>

<div class="actions">
    <button onclick="window.print()" class="btn-print">🖨️ PRINT INVOICE</button>
    <a href="<?php echo base_url('billing/new_bill.php'); ?>" class="btn-print" style="background:#475569;">➕ NEW BILL</a>
</div>

<div class="receipt-card" id="thermal-receipt">
    <!-- Header -->
    <div class="text-center">
        <h3 class="text-bold" style="font-size:15px;"><?php echo sanitize($shopName); ?></h3>
        <p style="font-size:10px;"><?php echo sanitize($shopAddress); ?></p>
        <p style="font-size:10px;">Ph: <?php echo sanitize($shopPhone); ?></p>
        <?php if (!empty($gstin)): ?>
            <p style="font-size:10px;" class="text-bold">GSTIN: <?php echo sanitize($gstin); ?></p>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <!-- Invoice Meta -->
    <div>
        <div><span class="text-bold">Inv No:</span> <?php echo sanitize($sale['invoice_no']); ?></div>
        <div><span class="text-bold">Date:</span> <?php echo date('d/m/Y h:i A', strtotime($sale['sale_date'])); ?></div>
        <div><span class="text-bold">Cashier:</span> <?php echo sanitize($sale['cashier_name'] ?? 'Staff'); ?></div>
        <div><span class="text-bold">Customer:</span> <?php echo sanitize($sale['customer_name'] ?? 'Customer'); ?></div>
        <?php if (!empty($sale['customer_mobile']) && $sale['customer_mobile'] !== '0000000000'): ?>
            <div><span class="text-bold">Mobile:</span> <?php echo sanitize($sale['customer_mobile']); ?></div>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <!-- Itemized Table -->
    <table>
        <thead>
            <tr style="border-bottom: 1px dashed #000;">
                <th style="text-align:left;">Item</th>
                <th style="text-align:center;">Qty</th>
                <th style="text-align:right;">Rate</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo sanitize($item['product_name']); ?></td>
                    <td class="text-center"><?php echo $item['qty']; ?></td>
                    <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                    <td class="text-end"><?php echo number_format($item['total_amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Calculations -->
    <table>
        <tr>
            <td>Subtotal:</td>
            <td class="text-end">₹<?php echo number_format($sale['total_amount'], 2); ?></td>
        </tr>
        <?php if ($sale['discount_amount'] > 0): ?>
            <tr>
                <td>Discount:</td>
                <td class="text-end">- ₹<?php echo number_format($sale['discount_amount'], 2); ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>GST Tax:</td>
            <td class="text-end">₹<?php echo number_format($sale['tax_amount'], 2); ?></td>
        </tr>
        <tr class="text-bold" style="font-size:13px;">
            <td>GRAND TOTAL:</td>
            <td class="text-end">₹<?php echo number_format($sale['grand_total'], 2); ?></td>
        </tr>
        <tr>
            <td>Paid Amount:</td>
            <td class="text-end">₹<?php echo number_format($sale['paid_amount'], 2); ?></td>
        </tr>
        <?php if ($sale['due_amount'] > 0): ?>
            <tr class="text-bold">
                <td>Balance Due:</td>
                <td class="text-end">₹<?php echo number_format($sale['due_amount'], 2); ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>Payment Mode:</td>
            <td class="text-end" style="text-transform:uppercase; font-weight:bold;"><?php echo sanitize($sale['payment_mode']); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="text-center" style="margin-top:8px;">
        <p class="text-bold" style="font-size:10px;"><?php echo sanitize($footerNote); ?></p>
        <p style="font-size:9px; margin-top:4px;">*** Computer Generated Receipt ***</p>
    </div>
</div>

<script>
// Auto print when opened in print preview
window.onload = function() {
    // Uncomment if auto-print desired
    // window.print();
};
</script>

</body>
</html>
