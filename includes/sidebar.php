<?php
require_once __DIR__ . '/../config/database.php';
$user = current_user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentPage = basename($currentPath ?: $_SERVER['PHP_SELF']);
if (strpos($currentPage, '.php') === false && !empty($currentPage)) {
    $currentPage .= '.php';
}

// Fetch low stock count for badge
$db = get_db();
$lowStockCount = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM products WHERE current_stock <= min_stock AND status = 1");
    $lowStockCount = $stmt->fetchColumn();
} catch (Exception $e) {
    $lowStockCount = 0;
}
?>
<!-- Sidebar Navigation -->
<nav id="sidebar" class="no-print">
    <div class="sidebar-header d-flex align-items-center justify-content-between">
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="text-white text-decoration-none d-flex align-items-center">
            <i class="fa-solid fa-screwdriver-wrench me-2 text-primary fs-4"></i>
            <div>
                <h6 class="fw-bold mb-0 text-white">SARASWATI HARDWARE</h6>
                <small class="text-white-50" style="font-size: 0.7rem;">STORE MANAGEMENT</small>
            </div>
        </a>
    </div>

    <ul class="list-unstyled components">
        <?php if ($user && ($user['role'] === 'admin' || $user['role'] === 'staff')): ?>
            <li class="px-3 pb-2 pt-1">
                <a href="<?php echo base_url('billing/new_bill.php'); ?>" class="btn btn-primary text-white w-100 justify-content-center py-2 shadow-sm rounded-3">
                    <i class="fa-solid fa-receipt me-2"></i> NEW BILL (POS)
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="<?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/products.php'); ?>" class="<?php echo $currentPage == 'products.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-boxes-stacked"></i> Products
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/categories.php'); ?>" class="<?php echo $currentPage == 'categories.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-layer-group"></i> Categories
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/inventory.php'); ?>" class="<?php echo $currentPage == 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-warehouse"></i> Inventory Stock
                    <?php if ($lowStockCount > 0): ?>
                        <span class="badge bg-danger badge-stock rounded-pill"><?php echo $lowStockCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/sales.php'); ?>" class="<?php echo $currentPage == 'sales.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cart-shopping"></i> Sales History
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/purchases.php'); ?>" class="<?php echo $currentPage == 'purchases.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-truck-ramp-box"></i> Purchases
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/customers.php'); ?>" class="<?php echo $currentPage == 'customers.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> Customers & Credit
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/suppliers.php'); ?>" class="<?php echo $currentPage == 'suppliers.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-building"></i> Suppliers
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('admin/expenses.php'); ?>" class="<?php echo $currentPage == 'expenses.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-wallet"></i> Expenses
                </a>
            </li>

            <?php if ($user['role'] === 'admin'): ?>
                <li>
                    <a href="<?php echo base_url('admin/reports.php'); ?>" class="<?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chart-line"></i> Reports & Profit
                    </a>
                </li>
                <li>
                    <a href="<?php echo base_url('admin/settings.php'); ?>" class="<?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-sliders"></i> Shop Settings
                    </a>
                </li>
            <?php endif; ?>

        <?php elseif ($user && $user['role'] === 'customer'): ?>
            <li>
                <a href="<?php echo base_url('customer/products.php'); ?>" class="<?php echo $currentPage == 'products.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-store"></i> Shop Products
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('customer/cart.php'); ?>" class="<?php echo $currentPage == 'cart.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-basket-shopping"></i> My Cart
                </a>
            </li>
            <li>
                <a href="<?php echo base_url('customer/orders.php'); ?>" class="<?php echo $currentPage == 'orders.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-truck"></i> My Orders
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="p-3 border-top border-secondary mt-auto text-center" style="font-size: 0.8rem; color: #64748b;">
        Hardware Store Billing v1.0<br>
        <span class="text-white-50">Offline & XAMPP Ready</span>
    </div>
</nav>
