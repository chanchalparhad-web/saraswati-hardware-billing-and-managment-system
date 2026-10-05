<?php
$pageTitle = "Reports & Profit Analysis";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();

// 1. Financial Profit & Loss Totals
$totalSalesRevenue = $db->query("SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE status = 'completed'")->fetchColumn();
$totalPurchasesCost = $db->query("SELECT COALESCE(SUM(grand_total), 0) FROM purchases WHERE status = 1")->fetchColumn();
$totalExpensesCost = $db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();
$estimatedNetProfit = $totalSalesRevenue - ($totalPurchasesCost + $totalExpensesCost);

// 2. Category Sales Summary
$catSales = $db->query("
    SELECT c.name as category_name, COUNT(si.id) as total_sold_qty, SUM(si.total_amount) as category_revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.status = 'completed'
    GROUP BY c.id
    ORDER BY category_revenue DESC
")->fetchAll();

// 3. Top Selling Products
$topProducts = $db->query("
    SELECT p.name, p.product_code, SUM(si.qty) as total_qty, SUM(si.total_amount) as total_revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.status = 'completed'
    GROUP BY p.id
    ORDER BY total_qty DESC LIMIT 5
")->fetchAll();
?>

<div id="content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Business Analytics & Profit Reports</h4>
                <p class="text-muted small mb-0">Financial summary, category-wise revenue, top-selling items, and profit calculation</p>
            </div>
            <button class="btn btn-outline-dark fw-bold no-print" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> PRINT REPORT
            </button>
        </div>

        <!-- 4 Financial Summary Cards -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
            <div class="col">
                <div class="stat-card p-3">
                    <span class="small text-muted fw-bold">Total Sales Income</span>
                    <h3 class="fw-bold text-success mb-0">₹<?php echo number_format($totalSalesRevenue, 2); ?></h3>
                    <small class="text-muted">Gross Store Revenue</small>
                </div>
            </div>
            <div class="col">
                <div class="stat-card p-3">
                    <span class="small text-muted fw-bold">Total Stock Purchases</span>
                    <h3 class="fw-bold text-primary mb-0">₹<?php echo number_format($totalPurchasesCost, 2); ?></h3>
                    <small class="text-muted">Inventory Sourcing Cost</small>
                </div>
            </div>
            <div class="col">
                <div class="stat-card p-3">
                    <span class="small text-muted fw-bold">Total Expenses</span>
                    <h3 class="fw-bold text-danger mb-0">₹<?php echo number_format($totalExpensesCost, 2); ?></h3>
                    <small class="text-muted">Store Operational Overhead</small>
                </div>
            </div>
            <div class="col">
                <div class="stat-card p-3">
                    <span class="small text-muted fw-bold">Net Profit / Margin</span>
                    <h3 class="fw-bold <?php echo $estimatedNetProfit >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                        ₹<?php echo number_format($estimatedNetProfit, 2); ?>
                    </h3>
                    <small class="text-muted">Income - (Purchases + Expenses)</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Top Selling Products Table -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-trophy text-warning me-2"></i> Top Selling Hardware Products</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Units Sold</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $tp): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo sanitize($tp['name']); ?></div>
                                            <small class="text-muted"><?php echo sanitize($tp['product_code']); ?></small>
                                        </td>
                                        <td class="text-center"><span class="badge bg-primary-soft text-primary fw-bold"><?php echo $tp['total_qty']; ?></span></td>
                                        <td class="text-end fw-bold text-success">₹<?php echo number_format($tp['total_revenue'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Category Revenue Breakdown -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-chart-pie text-info me-2"></i> Category Revenue Breakdown</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-center">Items Sold</th>
                                    <th class="text-end">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($catSales as $cs): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?php echo sanitize($cs['category_name']); ?></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $cs['total_sold_qty']; ?></span></td>
                                        <td class="text-end fw-bold text-dark">₹<?php echo number_format($cs['category_revenue'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
