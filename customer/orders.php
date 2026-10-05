<?php
$pageTitle = "My Orders History";
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$user = current_user();

// Fetch customer's orders
$stmtC = $db->prepare("SELECT id FROM customers WHERE email = ?");
$stmtC->execute([$user['email']]);
$cust = $stmtC->fetch();
$customerId = $cust ? $cust['id'] : 4;

$orders = $db->query("SELECT * FROM online_orders WHERE customer_id = {$customerId} ORDER BY id DESC")->fetchAll();
?>

<div id="content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">

        <h4 class="fw-bold mb-4"><i class="fa-solid fa-truck text-primary me-2"></i> My Online Orders</h4>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 custom-table">
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Order Date</th>
                            <th>Shipping Address</th>
                            <th>Total Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No online orders placed yet. <a href="products.php">Shop Now</a></td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?php echo sanitize($o['order_no']); ?></td>
                                    <td><?php echo date('d/m/Y h:i A', strtotime($o['order_date'])); ?></td>
                                    <td class="small text-muted"><?php echo sanitize($o['shipping_address']); ?></td>
                                    <td class="fw-bold">₹<?php echo number_format($o['total_amount'], 2); ?></td>
                                    <td><span class="badge bg-light text-dark border text-uppercase"><?php echo sanitize($o['payment_status']); ?></span></td>
                                    <td>
                                        <?php if ($o['order_status'] === 'Pending'): ?>
                                            <span class="badge bg-warning text-dark">PENDING</span>
                                        <?php elseif ($o['order_status'] === 'Completed'): ?>
                                            <span class="badge bg-success-soft text-success">DELIVERED</span>
                                        <?php else: ?>
                                            <span class="badge bg-info-soft text-info"><?php echo strtoupper(sanitize($o['order_status'])); ?></span>
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
