<?php
$pageTitle = "Hardware Store - Online Product Catalog";
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();

// Search & Filter
$search = trim($_GET['search'] ?? '');
$catFilter = intval($_GET['category_id'] ?? 0);

$query = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.brand LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term];
}
if ($catFilter > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $catFilter;
}

$query .= " ORDER BY p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY name ASC")->fetchAll();
?>

<div id="content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">

        <!-- Hero Banner -->
        <div class="hero-banner shadow-sm d-flex align-items-center justify-content-between">
            <div>
                <h3 class="fw-bold mb-1"><i class="fa-solid fa-store me-2"></i> Hardware Online Store</h3>
                <p class="mb-0 text-white-50">Browse plumbing, electrical, tools, paints, and order online for store pick up or delivery</p>
            </div>
            <a href="<?php echo base_url('customer/cart.php'); ?>" class="btn btn-warning fw-bold px-4 py-2 rounded-pill shadow-sm">
                <i class="fa-solid fa-basket-shopping me-2"></i> View Cart & Checkout
            </a>
        </div>

        <!-- Filter Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control form-control-lg" placeholder="Search tools, pipes, paint, electricals..." value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="category_id" class="form-select form-select-lg">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $catFilter == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Search</button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
            <?php foreach ($products as $p): ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm rounded-3 p-3 product-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark border"><?php echo sanitize($p['brand'] ?? 'Genuine'); ?></span>
                            <?php if ($p['current_stock'] > 0): ?>
                                <span class="badge bg-success-soft text-success"><i class="fa-solid fa-check me-1"></i> In Stock</span>
                            <?php else: ?>
                                <span class="badge bg-danger-soft text-danger">Out of Stock</span>
                            <?php endif; ?>
                        </div>

                        <h6 class="fw-bold text-dark mb-1" style="min-height: 40px;"><?php echo sanitize($p['name']); ?></h6>
                        <small class="text-muted mb-2 d-block"><?php echo sanitize($p['category_name']); ?> | Unit: <?php echo sanitize($p['unit']); ?></small>

                        <div class="d-flex align-items-baseline gap-2 mb-3">
                            <h4 class="fw-bold text-primary mb-0">₹<?php echo number_format($p['selling_price'], 2); ?></h4>
                            <?php if ($p['mrp'] > $p['selling_price']): ?>
                                <small class="text-muted text-decoration-line-through">₹<?php echo number_format($p['mrp'], 2); ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="mt-auto">
                            <?php if ($p['current_stock'] > 0): ?>
                                <button class="btn btn-primary w-100 fw-bold rounded-3" onclick='addOnlineCart(<?php echo json_encode($p); ?>)'>
                                    <i class="fa-solid fa-cart-plus me-1"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100 fw-bold rounded-3" disabled>Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script>
function addOnlineCart(p) {
    let cart = JSON.parse(localStorage.getItem('customer_cart') || '[]');
    let index = cart.findIndex(item => item.id === p.id);
    if (index > -1) {
        cart[index].qty += 1;
    } else {
        cart.push({ id: p.id, name: p.name, price: p.selling_price, gst: p.gst_rate, qty: 1 });
    }
    localStorage.setItem('customer_cart', JSON.stringify(cart));
    alert('"' + p.name + '" added to cart!');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
