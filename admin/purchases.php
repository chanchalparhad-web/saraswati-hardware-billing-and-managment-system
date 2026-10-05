<?php
$pageTitle = "Purchase Management";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$msg = '';
$msgType = '';

// Handle Purchase Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_purchase') {
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $purchase_date = sanitize($_POST['purchase_date'] ?? date('Y-m-d'));
        $invoice_no = trim($_POST['invoice_no'] ?? '');
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        $qty = intval($_POST['qty'] ?? 0);
        $purchase_rate = floatval($_POST['purchase_rate'] ?? 0);

        if ($supplier_id <= 0 || $product_id <= 0 || $qty <= 0 || $purchase_rate <= 0) {
            $msg = 'Please fill in Supplier, Product, Qty, and Purchase Rate!';
            $msgType = 'danger';
        } else {
            try {
                $db->beginTransaction();

                // Calculate Totals
                $taxPercent = 18.00;
                $lineSubtotal = $qty * $purchase_rate;
                $taxAmount = ($lineSubtotal * $taxPercent) / 100;
                $grandTotal = $lineSubtotal + $taxAmount;
                $dueAmount = max(0, $grandTotal - $paid_amount);
                $paymentStatus = ($dueAmount <= 0) ? 'paid' : (($paid_amount > 0) ? 'partial' : 'due');

                // Generate Purchase No
                $purchaseNo = 'PUR-' . date('Ym') . '-' . sprintf("%04d", rand(100, 9999));

                // Insert Purchase Header
                $stmtPur = $db->prepare("
                    INSERT INTO purchases (purchase_no, supplier_id, purchase_date, invoice_no, total_amount, tax_amount, discount_amount, grand_total, paid_amount, due_amount, payment_status, status)
                    VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?, 1)
                ");
                $stmtPur->execute([$purchaseNo, $supplier_id, $purchase_date, $invoice_no, $lineSubtotal, $taxAmount, $grandTotal, $paid_amount, $dueAmount, $paymentStatus]);
                $purchaseId = $db->lastInsertId();

                // Insert Purchase Item
                $stmtItem = $db->prepare("
                    INSERT INTO purchase_items (purchase_id, product_id, qty, purchase_rate, tax_percent, tax_amount, total_amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtItem->execute([$purchaseId, $product_id, $qty, $purchase_rate, $taxPercent, $taxAmount, $grandTotal]);

                // Increase Inventory Stock
                $stmtStock = $db->prepare("UPDATE products SET current_stock = current_stock + ?, purchase_price = ? WHERE id = ?");
                $stmtStock->execute([$qty, $purchase_rate, $product_id]);

                // Record Inventory Transaction
                $stmtAudit = $db->prepare("
                    INSERT INTO inventory_transactions (product_id, transaction_type, reference_id, qty_change, stock_after, notes)
                    VALUES (?, 'purchase', ?, ?, (SELECT current_stock FROM products WHERE id = ?), 'Supplier Purchase Restock')
                ");
                $stmtAudit->execute([$product_id, $purchaseNo, $qty, $product_id]);

                // Update Supplier Balance if Due
                if ($dueAmount > 0) {
                    $stmtSup = $db->prepare("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?");
                    $stmtSup->execute([$dueAmount, $supplier_id]);
                }

                $db->commit();
                $msg = 'Purchase Invoice recorded successfully! Stock increased.';
                $msgType = 'success';
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $msg = 'Error saving purchase: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}

// Fetch Purchases List
$purchases = $db->query("
    SELECT p.*, s.company_name, s.supplier_name 
    FROM purchases p 
    JOIN suppliers s ON p.supplier_id = s.id 
    ORDER BY p.id DESC
")->fetchAll();

$suppliers = $db->query("SELECT * FROM suppliers WHERE status = 1 ORDER BY company_name ASC")->fetchAll();
$products = $db->query("SELECT * FROM products WHERE status = 1 ORDER BY name ASC")->fetchAll();
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

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Purchase Orders & Invoices</h4>
                <p class="text-muted small mb-0">Record product stock purchases from suppliers and auto-update inventory</p>
            </div>
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#purchaseModal">
                <i class="fa-solid fa-cart-flatbed me-1"></i> RECORD NEW PURCHASE
            </button>
        </div>

        <!-- Purchase Orders Table -->
        <div class="card border-0 shadow-sm rounded-3 p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle custom-table mb-0">
                    <thead>
                        <tr>
                            <th>Purchase No</th>
                            <th>Supplier Company</th>
                            <th>Purchase Date</th>
                            <th>Supplier Invoice No</th>
                            <th>Total Amount</th>
                            <th>Paid Amount</th>
                            <th>Balance Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchases)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No purchase records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($purchases as $pur): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?php echo sanitize($pur['purchase_no']); ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo sanitize($pur['company_name']); ?></div>
                                        <small class="text-muted"><?php echo sanitize($pur['supplier_name']); ?></small>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($pur['purchase_date'])); ?></td>
                                    <td><?php echo sanitize($pur['invoice_no'] ?? '-'); ?></td>
                                    <td class="fw-bold">₹<?php echo number_format($pur['grand_total'], 2); ?></td>
                                    <td class="text-success fw-bold">₹<?php echo number_format($pur['paid_amount'], 2); ?></td>
                                    <td class="text-danger fw-bold">₹<?php echo number_format($pur['due_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($pur['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success-soft text-success">PAID</span>
                                        <?php elseif ($pur['payment_status'] === 'partial'): ?>
                                            <span class="badge bg-warning-soft text-warning">PARTIAL</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-soft text-danger">DUE</span>
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

<!-- Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_purchase">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-cart-flatbed me-2 text-primary"></i> Record Supplier Purchase</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Select Supplier *</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Choose Supplier...</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo sanitize($s['company_name']); ?> (<?php echo sanitize($s['supplier_name']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Purchase Date *</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Supplier Invoice Ref #</label>
                            <input type="text" name="invoice_no" class="form-control" placeholder="INV-9988">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Product Item Restocked *</label>
                            <select name="product_id" class="form-select" required>
                                <option value="">Select product...</option>
                                <?php foreach ($products as $pr): ?>
                                    <option value="<?php echo $pr['id']; ?>"><?php echo sanitize($pr['name']); ?> (Code: <?php echo $pr['product_code']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Quantity Received *</label>
                            <input type="number" name="qty" class="form-control" min="1" value="10" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Purchase Rate / Unit (₹) *</label>
                            <input type="number" step="0.01" name="purchase_rate" class="form-control" placeholder="180.00" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Amount Paid Now (₹)</label>
                            <input type="number" step="0.01" name="paid_amount" class="form-control" placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save & Restock Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
