<?php
$pageTitle = "Dashboard Overview";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();

// 1. Dashboard Metrics
$todayDate = date('Y-m-d');
$todayMonth = date('Y-m');

$stmtSales = $db->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE DATE(sale_date) = ? AND status = 'completed'");
$stmtSales->execute([$todayDate]);
$todaySales = $stmtSales->fetchColumn();

$stmtPurchases = $db->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM purchases WHERE DATE(purchase_date) = ?");
$stmtPurchases->execute([$todayDate]);
$todayPurchases = $stmtPurchases->fetchColumn();

$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE status = 1")->fetchColumn();
$lowStockCount = $db->query("SELECT COUNT(*) FROM products WHERE current_stock <= min_stock AND status = 1")->fetchColumn();
$customerDueTotal = $db->query("SELECT COALESCE(SUM(outstanding_amount), 0) FROM customers WHERE status = 1")->fetchColumn();
$supplierDueTotal = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE status = 1")->fetchColumn();

// 2. Recent Sales
$recentSalesStmt = $db->query("
    SELECT s.*, c.name as customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    ORDER BY s.id DESC LIMIT 5
");
$recentSales = $recentSalesStmt->fetchAll();

// 3. Low Stock List
$lowStockStmt = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.current_stock <= p.min_stock AND p.status = 1 
    ORDER BY p.current_stock ASC LIMIT 5
");
$lowStockProducts = $lowStockStmt->fetchAll();

// 4. Expense summary
$stmtExp = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date LIKE ?");
$stmtExp->execute([$todayMonth . '%']);
$monthExpenses = $stmtExp->fetchColumn();
?>

<div id="content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">

        <!-- Header Title Banner -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Store Dashboard Overview</h4>
                <p class="text-muted small mb-0">Real-time stats, sales summary, and inventory stock monitoring</p>
            </div>
            <div>
                <a href="<?php echo base_url('billing/new_bill.php'); ?>" class="btn btn-primary fw-bold shadow-sm">
                    <i class="fa-solid fa-receipt me-1"></i> CREATE NEW BILL
                </a>
            </div>
        </div>

        <!-- 6 Metric Cards -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-6 g-3 mb-4">
            
            <div class="col">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small text-muted fw-bold">Today's Sales</span>
                        <div class="stat-icon bg-success-soft">
                            <i class="fa-solid fa-indian-rupee-sign"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-0 text-success">₹<?php echo number_format($todaySales, 2); ?></h5>
                    <small class="text-muted" style="font-size:0.75rem;">Offline POS Billing</small>
                </div>
            </div>

            <div class="col">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small text-muted fw-bold">Today's Purchases</span>
                        <div class="stat-icon bg-primary-soft">
                            <i class="fa-solid fa-truck-ramp-box"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-0 text-primary">₹<?php echo number_format($todayPurchases, 2); ?></h5>
                    <small class="text-muted" style="font-size:0.75rem;">Supplier Invoices</small>
                </div>
            </div>

            <div class="col">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small text-muted fw-bold">Total Products</span>
                        <div class="stat-icon bg-info-soft">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark"><?php echo number_format($totalProducts); ?></h5>
                    <small class="text-muted" style="font-size:0.75rem;">Active Inventory SKUs</small>
                </div>
            </div>

            <div class="col">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small text-muted fw-bold">Low Stock Items</span>
                        <div class="stat-icon bg-danger-soft">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-0 text-danger"><?php echo number_format($lowStockCount); ?></h5>
                    <small class="text-muted" style="font-size:0.75rem;">Action Needed</small>
                </div>
            </div>

            <div class="col">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small text-muted fw-bold">Customer Due</span>
                        <div class="stat-icon bg-warning-soft">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-0 text-warning">₹<?php echo number_format($customerDueTotal, 2); ?></h5>
                    <small class="text-muted" style="font-size:0.75rem;">Udhaar Outstanding</small>
                </div>
            </div>

            <div class="col">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small text-muted fw-bold">Supplier Due</span>
                        <div class="stat-icon bg-purple-soft">
                            <i class="fa-solid fa-building"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-0 text-purple" style="color:#9333ea;">₹<?php echo number_format($supplierDueTotal, 2); ?></h5>
                    <small class="text-muted" style="font-size:0.75rem;">Payables Balance</small>
                </div>
            </div>

        </div>

        <!-- Charts & Tables Section -->
        <div class="row g-4 mb-4">
            
            <!-- Left Chart: Weekly Sales vs Expenses -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-chart-area text-primary me-2"></i> Sales & Revenue Overview</h6>
                        <span class="badge bg-light text-muted">This Month</span>
                    </div>
                    <div style="height: 280px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Low Stock Table -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Low Stock Alerts</h6>
                        <a href="<?php echo base_url('admin/inventory.php'); ?>" class="small text-decoration-none fw-bold">View All</a>
                    </div>

                    <?php if (empty($lowStockProducts)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-circle-check fa-2x text-success mb-2"></i>
                            <p class="mb-0 small">All inventory stock levels are healthy!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>Product</th>
                                        <th class="text-center">Min</th>
                                        <th class="text-center">Current</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockProducts as $lp): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark small text-truncate" style="max-width:140px;"><?php echo sanitize($lp['name']); ?></div>
                                                <small class="text-muted" style="font-size:0.7rem;"><?php echo sanitize($lp['category_name']); ?></small>
                                            </td>
                                            <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $lp['min_stock']; ?></span></td>
                                            <td class="text-center"><span class="badge bg-danger text-white fw-bold"><?php echo $lp['current_stock']; ?></span></td>
                                            <td class="text-end">
                                                <a href="<?php echo base_url('admin/purchases.php'); ?>" class="btn btn-sm btn-outline-primary py-0 px-2 style='font-size:0.75rem;'">
                                                    + Restock
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Bottom Table: Recent Transactions -->
        <div class="card border-0 shadow-sm rounded-3 p-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Recent Sales Transactions</h6>
                <a href="<?php echo base_url('admin/sales.php'); ?>" class="btn btn-sm btn-outline-secondary">View Sales History</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 custom-table">
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Customer</th>
                            <th>Date & Time</th>
                            <th>Mode</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSales)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No sales recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentSales as $s): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?php echo sanitize($s['invoice_no']); ?></td>
                                    <td><?php echo sanitize($s['customer_name'] ?? 'Customer'); ?></td>
                                    <td class="small text-muted"><?php echo date('d M Y, h:i A', strtotime($s['sale_date'])); ?></td>
                                    <td><span class="badge bg-light text-dark border text-uppercase"><?php echo sanitize($s['payment_mode']); ?></span></td>
                                    <td class="fw-bold">₹<?php echo number_format($s['grand_total'], 2); ?></td>
                                    <td>
                                        <?php if ($s['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success-soft text-success">PAID</span>
                                        <?php elseif ($s['payment_status'] === 'partial'): ?>
                                            <span class="badge bg-warning-soft text-warning">PARTIAL</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-soft text-danger">CREDIT DUE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo base_url('billing/print_invoice.php?invoice_no='.$s['invoice_no']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-print"></i> Receipt
                                        </a>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [
                    {
                        label: 'Sales Revenue (₹)',
                        data: [12400, 18500, 24000, 19200, 31000, 42850, 38000],
                        backgroundColor: '#2563eb',
                        borderRadius: 6
                    },
                    {
                        label: 'Purchases Cost (₹)',
                        data: [8000, 12000, 5000, 14000, 18500, 9000, 15000],
                        backgroundColor: '#94a3b8',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
