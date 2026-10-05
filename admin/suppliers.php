<?php
$pageTitle = "Supplier Management";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_supplier') {
        $id = intval($_POST['id'] ?? 0);
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gst_number = trim($_POST['gst_number'] ?? '');

        if (empty($company_name) || empty($mobile)) {
            $msg = 'Company name and Mobile are required!';
            $msgType = 'danger';
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE suppliers SET supplier_name = ?, company_name = ?, mobile = ?, email = ?, address = ?, gst_number = ? WHERE id = ?");
                $stmt->execute([$supplier_name, $company_name, $mobile, $email, $address, $gst_number, $id]);
                $msg = 'Supplier updated successfully!';
                $msgType = 'success';
            } else {
                $stmt = $db->prepare("INSERT INTO suppliers (supplier_name, company_name, mobile, email, address, gst_number, opening_balance, current_balance) VALUES (?, ?, ?, ?, ?, ?, 0.00, 0.00)");
                $stmt->execute([$supplier_name, $company_name, $mobile, $email, $address, $gst_number]);
                $msg = 'Supplier added successfully!';
                $msgType = 'success';
            }
        }
    } elseif ($action === 'record_supplier_payment') {
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_mode = sanitize($_POST['payment_mode'] ?? 'bank_transfer');

        if ($supplier_id <= 0 || $amount <= 0) {
            $msg = 'Select supplier and enter valid payment amount!';
            $msgType = 'danger';
        } else {
            $db->exec("UPDATE suppliers SET current_balance = MAX(0, current_balance - {$amount}) WHERE id = {$supplier_id}");
            $msg = 'Supplier payment recorded successfully!';
            $msgType = 'success';
        }
    }
}

$suppliers = $db->query("SELECT * FROM suppliers WHERE status = 1 ORDER BY id ASC")->fetchAll();
$totalSupplierDue = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE status = 1")->fetchColumn();
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
                <h4 class="fw-bold mb-1">Suppliers & Manufacturers</h4>
                <p class="text-muted small mb-0">Manage hardware vendor details, GSTIN, purchase ledgers, and payables</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-purple text-white fw-bold shadow-sm" style="background:#9333ea;" data-bs-toggle="modal" data-bs-target="#paySupplierModal">
                    <i class="fa-solid fa-money-bill-wave me-1"></i> PAY SUPPLIER
                </button>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="resetSupForm()">
                    <i class="fa-solid fa-building-circle-check me-1"></i> ADD SUPPLIER
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-purple-soft" style="background:#f3e8ff;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="small text-muted fw-bold text-uppercase">Total Supplier Payables Outstanding</span>
                    <h3 class="fw-bold text-purple mb-0" style="color:#9333ea;">₹<?php echo number_format($totalSupplierDue, 2); ?></h3>
                </div>
                <div class="stat-icon bg-purple text-white" style="background:#9333ea;">
                    <i class="fa-solid fa-building"></i>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 custom-table">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Contact Person</th>
                            <th>Mobile</th>
                            <th>Email / Address</th>
                            <th>GSTIN</th>
                            <th>Balance Due</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo sanitize($s['company_name']); ?></td>
                                <td><?php echo sanitize($s['supplier_name']); ?></td>
                                <td><i class="fa-solid fa-phone me-1 text-muted"></i><?php echo sanitize($s['mobile']); ?></td>
                                <td class="small text-muted"><?php echo sanitize($s['email'] ?? '-'); ?><br><?php echo sanitize($s['address'] ?? ''); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo sanitize($s['gst_number'] ?? 'N/A'); ?></span></td>
                                <td class="fw-bold text-purple" style="color:#9333ea;">₹<?php echo number_format($s['current_balance'], 2); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick='editSupplier(<?php echo json_encode($s); ?>)'>
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_supplier">
                <input type="hidden" name="id" id="sup_id" value="0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="supModalTitle"><i class="fa-solid fa-building me-2 text-primary"></i> Add Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Company Name *</label>
                        <input type="text" name="company_name" id="sup_company" class="form-control" required placeholder="e.g. Supreme Plumbing Supplies Pvt Ltd">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Contact Person Name</label>
                            <input type="text" name="supplier_name" id="sup_name" class="form-control" placeholder="Suresh Patel">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Mobile Number *</label>
                            <input type="tel" name="mobile" id="sup_mobile" class="form-control" required placeholder="9820011223">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" name="email" id="sup_email" class="form-control" placeholder="sales@supplier.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">GSTIN Number</label>
                        <input type="text" name="gst_number" id="sup_gst" class="form-control" placeholder="24AAACS1234F1Z1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Office Address</label>
                        <textarea name="address" id="sup_address" class="form-control" rows="2" placeholder="GIDC Industrial estate..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Supplier Pay Modal -->
<div class="modal fade" id="paySupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="record_supplier_payment">
                <div class="modal-header text-white" style="background:#9333ea;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-wave me-2"></i> Pay Supplier Balance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Supplier *</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Choose Supplier...</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo sanitize($s['company_name']); ?> (Due: ₹<?php echo number_format($s['current_balance'], 2); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Amount (₹) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Method *</label>
                            <select name="payment_mode" class="form-select">
                                <option value="bank_transfer">Bank Transfer / NEFT</option>
                                <option value="upi">UPI / QR</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white fw-bold" style="background:#9333ea;">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetSupForm() {
    document.getElementById('sup_id').value = '0';
    document.getElementById('supModalTitle').innerHTML = '<i class="fa-solid fa-building me-2 text-primary"></i> Add Supplier';
    document.getElementById('sup_company').value = '';
    document.getElementById('sup_name').value = '';
    document.getElementById('sup_mobile').value = '';
    document.getElementById('sup_email').value = '';
    document.getElementById('sup_gst').value = '';
    document.getElementById('sup_address').value = '';
}

function editSupplier(s) {
    document.getElementById('sup_id').value = s.id;
    document.getElementById('supModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Edit Supplier';
    document.getElementById('sup_company').value = s.company_name;
    document.getElementById('sup_name').value = s.supplier_name;
    document.getElementById('sup_mobile').value = s.mobile;
    document.getElementById('sup_email').value = s.email || '';
    document.getElementById('sup_gst').value = s.gst_number || '';
    document.getElementById('sup_address').value = s.address || '';
    
    var myModal = new bootstrap.Modal(document.getElementById('supplierModal'));
    myModal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
