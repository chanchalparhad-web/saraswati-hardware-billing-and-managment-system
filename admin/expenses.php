<?php
$pageTitle = "Expense Management";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_expense') {
        $category = sanitize($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $expense_date = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
        $payment_method = sanitize($_POST['payment_method'] ?? 'cash');

        if (empty($category) || $amount <= 0) {
            $msg = 'Expense Category and positive amount are required!';
            $msgType = 'danger';
        } else {
            $stmt = $db->prepare("INSERT INTO expenses (category, description, amount, expense_date, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$category, $description, $amount, $expense_date, $payment_method]);
            $msg = 'Expense recorded successfully!';
            $msgType = 'success';
        }
    }
}

$expenses = $db->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC")->fetchAll();
$todayMonth = date('Y-m');
$stmtExpMonth = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date LIKE ?");
$stmtExpMonth->execute([$todayMonth . '%']);
$totalExpenseMonth = $stmtExpMonth->fetchColumn();
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
                <h4 class="fw-bold mb-1">Store Expense Tracking</h4>
                <p class="text-muted small mb-0">Record shop operational expenses such as rent, electricity, salaries, and transport</p>
            </div>
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#expenseModal">
                <i class="fa-solid fa-receipt me-1"></i> RECORD EXPENSE
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-danger-soft">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="small text-muted fw-bold text-uppercase">This Month Total Expenses</span>
                    <h3 class="fw-bold text-danger mb-0">₹<?php echo number_format($totalExpenseMonth, 2); ?></h3>
                </div>
                <div class="stat-icon bg-danger text-white">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 custom-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description / Reason</th>
                            <th>Method</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No expense records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $ex): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo date('d/m/Y', strtotime($ex['expense_date'])); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo sanitize($ex['category']); ?></span></td>
                                    <td><?php echo sanitize($ex['description']); ?></td>
                                    <td><span class="badge bg-secondary text-uppercase" style="font-size:0.7rem;"><?php echo sanitize($ex['payment_method']); ?></span></td>
                                    <td class="fw-bold text-danger">₹<?php echo number_format($ex['amount'], 2); ?></td>
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
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_expense">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-wallet me-2 text-danger"></i> Record Store Expense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Expense Category *</label>
                        <select name="category" class="form-select" required>
                            <option value="Shop Rent">Shop Rent</option>
                            <option value="Electricity">Electricity Bill</option>
                            <option value="Salary">Staff Salary</option>
                            <option value="Transportation">Transportation & Freight</option>
                            <option value="Maintenance">Shop Maintenance</option>
                            <option value="Internet">Internet / Phone</option>
                            <option value="Miscellaneous">Miscellaneous</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Amount (₹) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Expense Date *</label>
                            <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Payment Method *</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI / QR</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description / Notes</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Details of payment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
