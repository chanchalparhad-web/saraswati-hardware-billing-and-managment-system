<?php
$pageTitle = "Customer & Credit Udhaar Management";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$msg = '';
$msgType = '';

// Handle Add Customer / Record Payment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_customer') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gst_number = trim($_POST['gst_number'] ?? '');
        $credit_limit = floatval($_POST['credit_limit'] ?? 15000);

        if (empty($name) || empty($mobile)) {
            $msg = 'Name and Mobile number are required!';
            $msgType = 'danger';
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE customers SET name = ?, mobile = ?, email = ?, address = ?, gst_number = ?, credit_limit = ? WHERE id = ?");
                $stmt->execute([$name, $mobile, $email, $address, $gst_number, $credit_limit, $id]);
                $msg = 'Customer updated successfully!';
                $msgType = 'success';
            } else {
                $stmt = $db->prepare("INSERT INTO customers (name, mobile, email, address, gst_number, credit_limit, outstanding_amount) VALUES (?, ?, ?, ?, ?, ?, 0.00)");
                $stmt->execute([$name, $mobile, $email, $address, $gst_number, $credit_limit]);
                $msg = 'Customer added successfully!';
                $msgType = 'success';
            }
        }
    } elseif ($action === 'record_payment') {
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_mode = sanitize($_POST['payment_mode'] ?? 'cash');
        $reference_no = trim($_POST['reference_no'] ?? '');

        if ($customer_id <= 0 || $amount <= 0) {
            $msg = 'Select customer and enter valid payment amount!';
            $msgType = 'danger';
        } else {
            $paymentNo = 'PAY-CUST-' . date('Ym') . '-' . rand(100, 999);
            
            // Insert Payment
            $stmtPay = $db->prepare("
                INSERT INTO payments (payment_no, type, entity_id, amount, payment_mode, reference_no, payment_date, notes)
                VALUES (?, 'customer_payment', ?, ?, ?, ?, CURRENT_DATE, 'Udhaar Collection Payment')
            ");
            $stmtPay->execute([$paymentNo, $customer_id, $amount, $payment_mode, $reference_no]);

            // Deduct Customer Outstanding Amount
            $stmtUpd = $db->prepare("UPDATE customers SET outstanding_amount = MAX(0, outstanding_amount - ?) WHERE id = ?");
            $stmtUpd->execute([$amount, $customer_id]);

            $msg = 'Payment recorded successfully! Customer ledger balance updated.';
            $msgType = 'success';
        }
    }
}

// Fetch Customers
$customers = $db->query("SELECT * FROM customers WHERE status = 1 ORDER BY id ASC")->fetchAll();
$totalOutstanding = $db->query("SELECT COALESCE(SUM(outstanding_amount), 0) FROM customers WHERE status = 1")->fetchColumn();
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
                <h4 class="fw-bold mb-1">Customers & Udhaar Credit Ledger</h4>
                <p class="text-muted small mb-0">Manage customer records, credit limits, and record debt/udhaar collections</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> RECORD PAYMENT COLLECTION
                </button>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetCustForm()">
                    <i class="fa-solid fa-user-plus me-1"></i> ADD CUSTOMER
                </button>
            </div>
        </div>

        <!-- Metric Widget -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-warning-soft text-dark">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="small text-muted fw-bold text-uppercase">Total Customer Udhaar Outstanding</span>
                    <h3 class="fw-bold text-danger mb-0">₹<?php echo number_format($totalOutstanding, 2); ?></h3>
                </div>
                <div class="stat-icon bg-warning text-dark">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>

        <!-- Customer List Table -->
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 custom-table">
                    <thead>
                        <tr>
                            <th># ID</th>
                            <th>Customer Name</th>
                            <th>Mobile</th>
                            <th>Email / Address</th>
                            <th>GSTIN</th>
                            <th>Credit Limit</th>
                            <th>Outstanding Balance</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td class="fw-bold">#<?php echo $c['id']; ?></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($c['name']); ?></td>
                                <td><i class="fa-solid fa-phone me-1 text-muted"></i><?php echo sanitize($c['mobile']); ?></td>
                                <td class="small text-muted">
                                    <?php echo sanitize($c['email'] ?? '-'); ?><br>
                                    <?php echo sanitize($c['address'] ?? ''); ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo sanitize($c['gst_number'] ?? 'N/A'); ?></span></td>
                                <td>₹<?php echo number_format($c['credit_limit'], 2); ?></td>
                                <td>
                                    <?php if ($c['outstanding_amount'] > 0): ?>
                                        <span class="badge bg-danger text-white fs-6 fw-bold">₹<?php echo number_format($c['outstanding_amount'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success-soft text-success">Clear (₹0.00)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick='editCustomer(<?php echo json_encode($c); ?>)'>
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

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_customer">
                <input type="hidden" name="id" id="cust_id" value="0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="custModalTitle"><i class="fa-solid fa-user-plus me-2 text-primary"></i> Add Customer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name *</label>
                        <input type="text" name="name" id="cust_name" class="form-control" required placeholder="e.g. Rajesh Construction Co">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Mobile Number *</label>
                            <input type="tel" name="mobile" id="cust_mobile" class="form-control" required placeholder="9876543210">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Credit Limit (₹)</label>
                            <input type="number" name="credit_limit" id="cust_limit" class="form-control" value="15000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" name="email" id="cust_email" class="form-control" placeholder="customer@gmail.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">GST Number</label>
                        <input type="text" name="gst_number" id="cust_gst" class="form-control" placeholder="27AAECR4433P1ZB">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Address</label>
                        <textarea name="address" id="cust_address" class="form-control" rows="2" placeholder="Full postal address..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="record_payment">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-dollar me-2"></i> Record Customer Udhaar Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Customer *</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">Choose customer...</option>
                            <?php foreach ($customers as $c): ?>
                                <?php if ($c['id'] > 1): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?> (Due: ₹<?php echo number_format($c['outstanding_amount'], 2); ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Amount (₹) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Mode *</label>
                            <select name="payment_mode" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI / QR</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reference / Transaction No</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="UPI Ref / Cheque No">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetCustForm() {
    document.getElementById('cust_id').value = '0';
    document.getElementById('custModalTitle').innerHTML = '<i class="fa-solid fa-user-plus me-2 text-primary"></i> Add Customer';
    document.getElementById('cust_name').value = '';
    document.getElementById('cust_mobile').value = '';
    document.getElementById('cust_limit').value = '15000';
    document.getElementById('cust_email').value = '';
    document.getElementById('cust_gst').value = '';
    document.getElementById('cust_address').value = '';
}

function editCustomer(c) {
    document.getElementById('cust_id').value = c.id;
    document.getElementById('custModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Edit Customer';
    document.getElementById('cust_name').value = c.name;
    document.getElementById('cust_mobile').value = c.mobile;
    document.getElementById('cust_limit').value = c.credit_limit;
    document.getElementById('cust_email').value = c.email || '';
    document.getElementById('cust_gst').value = c.gst_number || '';
    document.getElementById('cust_address').value = c.address || '';
    
    var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
    myModal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
