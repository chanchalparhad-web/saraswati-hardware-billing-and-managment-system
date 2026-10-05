<?php
$pageTitle = "Shop Settings & Thermal Printer Config";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'shop_name' => trim($_POST['shop_name'] ?? ''),
        'shop_address' => trim($_POST['shop_address'] ?? ''),
        'shop_phone' => trim($_POST['shop_phone'] ?? ''),
        'shop_email' => trim($_POST['shop_email'] ?? ''),
        'gstin' => trim($_POST['gstin'] ?? ''),
        'currency' => trim($_POST['currency'] ?? '₹'),
        'invoice_prefix' => trim($_POST['invoice_prefix'] ?? 'INV-'),
        'thermal_printer_width' => trim($_POST['thermal_printer_width'] ?? '80mm'),
        'footer_note' => trim($_POST['footer_note'] ?? '')
    ];

    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    
    // Fallback query for SQLite or MySQL
    $stmtSqlite = $db->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($settings as $key => $val) {
        try {
            $stmtSqlite->execute([$key, $val]);
        } catch (Exception $e) {
            $stmt->execute([$key, $val, $val]);
        }
    }

    $msg = 'Shop settings updated successfully!';
    $msgType = 'success';
}

$shopName = get_setting('shop_name', 'Saraswati Hardware');
$shopAddress = get_setting('shop_address', '123 Main Hardware Market, Station Road, City');
$shopPhone = get_setting('shop_phone', '+91 98765 43210');
$shopEmail = get_setting('shop_email', 'contact@abchardware.com');
$gstin = get_setting('gstin', '27ABCDE1234F1Z5');
$currency = get_setting('currency', '₹');
$invoicePrefix = get_setting('invoice_prefix', 'INV-');
$thermalPrinterWidth = get_setting('thermal_printer_width', '80mm');
$footerNote = get_setting('footer_note', 'Thank you for shopping with us! Visit again.');
?>

<div id="content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show fw-bold" role="alert">
                <?php echo sanitize($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Shop & POS Invoice Settings</h4>
                <p class="text-muted small mb-0">Configure store branding, tax GSTIN, receipt thermal printer paper size, and receipt notes</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-0 shadow-sm rounded-3 p-4">
                    <form method="POST" action="">
                        <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-store me-2"></i> Store Profile & Branding</h6>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Shop / Business Name *</label>
                            <input type="text" name="shop_name" class="form-control" value="<?php echo sanitize($shopName); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Full Address *</label>
                            <textarea name="shop_address" class="form-control" rows="2" required><?php echo sanitize($shopAddress); ?></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Contact Phone Number *</label>
                                <input type="text" name="shop_phone" class="form-control" value="<?php echo sanitize($shopPhone); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Store Email</label>
                                <input type="email" name="shop_email" class="form-control" value="<?php echo sanitize($shopEmail); ?>">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">GSTIN Tax Registration Number</label>
                                <input type="text" name="gstin" class="form-control" value="<?php echo sanitize($gstin); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Currency Symbol</label>
                                <input type="text" name="currency" class="form-control" value="<?php echo sanitize($currency); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Invoice Prefix</label>
                                <input type="text" name="invoice_prefix" class="form-control" value="<?php echo sanitize($invoicePrefix); ?>">
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-print me-2"></i> Thermal POS Receipt Printer Settings</h6>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Thermal Printer Paper Width *</label>
                                <select name="thermal_printer_width" class="form-select">
                                    <option value="80mm" <?php echo $thermalPrinterWidth == '80mm' ? 'selected' : ''; ?>>80mm Standard Thermal Roll</option>
                                    <option value="58mm" <?php echo $thermalPrinterWidth == '58mm' ? 'selected' : ''; ?>>58mm Mini Receipt Roll</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Receipt Footer Thank You Message</label>
                                <input type="text" name="footer_note" class="form-control" value="<?php echo sanitize($footerNote); ?>">
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-3 shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-2"></i> SAVE ALL SETTINGS
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
