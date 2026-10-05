<?php
$pageTitle = "Sales History & Transactions";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$user = current_user();
$msg = '';
$msgType = '';

// Handle Cancel Sale
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel_sale') {
        $sale_id = intval($_POST['sale_id'] ?? 0);
        
        if ($sale_id > 0) {
            try {
                $db->beginTransaction();

                // Get Sale
                $stmtS = $db->prepare("SELECT * FROM sales WHERE id = ?");
                $stmtS->execute([$sale_id]);
                $sale = $stmtS->fetch();

                if ($sale && $sale['status'] === 'completed') {
                    // Update Sale status
                    $stmtUpd = $db->prepare("UPDATE sales SET status = 'cancelled' WHERE id = ?");
                    $stmtUpd->execute([$sale_id]);

                    // Fetch Sale Items & Restore Stock
                    $items = $db->query("SELECT * FROM sale_items WHERE sale_id = {$sale_id}")->fetchAll();
                    foreach ($items as $item) {
                        $db->exec("UPDATE products SET current_stock = current_stock + {$item['qty']} WHERE id = {$item['product_id']}");
                        
                        // Audit
                        $stmtAudit = $db->prepare("
                            INSERT INTO inventory_transactions (product_id, transaction_type, reference_id, qty_change, stock_after, notes)
                            VALUES (?, 'sale_return', ?, ?, (SELECT current_stock FROM products WHERE id = ?), 'Sale Cancelled - Stock Restored')
                        ");
                        $stmtAudit->execute([$item['product_id'], $sale['invoice_no'], $item['qty'], $item['product_id']]);
                    }

                    // Restore Customer Dues if applicable
                    if ($sale['due_amount'] > 0 && $sale['customer_id'] > 1) {
                        $db->exec("UPDATE customers SET outstanding_amount = Math.max(0, outstanding_amount - {$sale['due_amount']}) WHERE id = {$sale['customer_id']}");
                    }

                    $db->commit();
                    $msg = 'Sale Cancelled successfully and stock restored!';
                    $msgType = 'warning';
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $msg = 'Error cancelling sale: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}

// Search & Date Filter
$search = trim($_GET['search'] ?? '');
$modeFilter = trim($_GET['payment_mode'] ?? '');

$query = "SELECT s.*, c.name as customer_name, u.name as cashier_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id LEFT JOIN users u ON s.user_id = u.id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.invoice_no LIKE ? OR c.name LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term];
}
if (!empty($modeFilter)) {
    $query .= " AND s.payment_mode = ?";
    $params[] = $modeFilter;
}

$query .= " ORDER BY s.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();
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
                <h4 class="fw-bold mb-1">Sales Transactions & History</h4>
                <p class="text-muted small mb-0">View completed invoices, customer billing records, payment modes, and process cancellations</p>
            </div>
            <a href="<?php echo base_url('billing/new_bill.php'); ?>" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> NEW BILL
            </a>
        </div>

        <!-- Filter Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <form method="GET" action="" class="row g-2">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search by Invoice No or Customer Name..." value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="payment_mode" class="form-select">
                        <option value="">All Payment Modes</option>
                        <option value="cash" <?php echo $modeFilter == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="upi" <?php echo $modeFilter == 'upi' ? 'selected' : ''; ?>>UPI</option>
                        <option value="card" <?php echo $modeFilter == 'card' ? 'selected' : ''; ?>>Card</option>
                        <option value="credit" <?php echo $modeFilter == 'credit' ? 'selected' : ''; ?>>Credit (Udhaar)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">Filter</button>
                    <a href="sales.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Sales Table -->
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 custom-table">
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Customer</th>
                            <th>Date & Time</th>
                            <th>Mode</th>
                            <th>Subtotal</th>
                            <th>Discount</th>
                            <th>GST Tax</th>
                            <th>Grand Total</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr><td colspan="10" class="text-center py-5 text-muted">No sales transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sales as $s): ?>
                                <tr class="<?php echo $s['status'] === 'cancelled' ? 'table-secondary text-muted' : ''; ?>">
                                    <td class="fw-bold text-primary"><?php echo sanitize($s['invoice_no']); ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo sanitize($s['customer_name'] ?? 'Customer'); ?></div>
                                        <small class="text-muted">By: <?php echo sanitize($s['cashier_name'] ?? 'Staff'); ?></small>
                                    </td>
                                    <td class="small text-muted"><?php echo date('d/m/Y h:i A', strtotime($s['sale_date'])); ?></td>
                                    <td><span class="badge bg-light text-dark border text-uppercase"><?php echo sanitize($s['payment_mode']); ?></span></td>
                                    <td>₹<?php echo number_format($s['total_amount'], 2); ?></td>
                                    <td class="text-danger">-₹<?php echo number_format($s['discount_amount'], 2); ?></td>
                                    <td>₹<?php echo number_format($s['tax_amount'], 2); ?></td>
                                    <td class="fw-bold text-dark fs-6">₹<?php echo number_format($s['grand_total'], 2); ?></td>
                                    <td>
                                        <?php if ($s['status'] === 'cancelled'): ?>
                                            <span class="badge bg-secondary">CANCELLED</span>
                                        <?php elseif ($s['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success-soft text-success">PAID</span>
                                        <?php elseif ($s['payment_status'] === 'partial'): ?>
                                            <span class="badge bg-warning-soft text-warning">PARTIAL</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-soft text-danger">CREDIT DUE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo base_url('billing/print_invoice.php?invoice_no='.$s['invoice_no']); ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="Print Receipt">
                                            <i class="fa-solid fa-print"></i> Receipt
                                        </a>
                                        <?php if ($s['status'] === 'completed' && $user['role'] === 'admin'): ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Cancel this invoice? Stock will be restored.')">
                                                <input type="hidden" name="action" value="cancel_sale">
                                                <input type="hidden" name="sale_id" value="<?php echo $s['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Sale">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
