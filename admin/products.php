<?php
$pageTitle = "Product Management";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$user = current_user();
$msg = '';
$msgType = '';

// Handle Add / Edit Product POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_product') {
        $id = intval($_POST['id'] ?? 0);
        $code = trim($_POST['product_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $brand = trim($_POST['brand'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unit = trim($_POST['unit'] ?? 'Piece');
        $purchase_price = floatval($_POST['purchase_price'] ?? 0);
        $selling_price = floatval($_POST['selling_price'] ?? 0);
        $mrp = floatval($_POST['mrp'] ?? 0);
        $gst_rate = floatval($_POST['gst_rate'] ?? 18);
        $current_stock = intval($_POST['current_stock'] ?? 0);
        $min_stock = intval($_POST['min_stock'] ?? 5);
        $location_rack = trim($_POST['location_rack'] ?? '');

        if (empty($code) || empty($name) || $category_id <= 0) {
            $msg = 'Please provide Product Code, Name, and Category!';
            $msgType = 'danger';
        } else {
            if ($id > 0) {
                // Edit product
                $stmt = $db->prepare("
                    UPDATE products SET 
                    product_code = ?, name = ?, category_id = ?, brand = ?, description = ?, unit = ?,
                    purchase_price = ?, selling_price = ?, mrp = ?, gst_rate = ?, current_stock = ?, min_stock = ?, location_rack = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $name, $category_id, $brand, $description, $unit, $purchase_price, $selling_price, $mrp, $gst_rate, $current_stock, $min_stock, $location_rack, $id]);
                $msg = 'Product updated successfully!';
                $msgType = 'success';
            } else {
                // Add product
                $stmt = $db->prepare("
                    INSERT INTO products (product_code, barcode, name, category_id, brand, description, unit, purchase_price, selling_price, mrp, gst_rate, opening_stock, current_stock, min_stock, location_rack, status)
                    VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$code, $name, $category_id, $brand, $description, $unit, $purchase_price, $selling_price, $mrp, $gst_rate, $current_stock, $current_stock, $min_stock, $location_rack]);
                $msg = 'Product added successfully!';
                $msgType = 'success';
            }
        }
    } elseif ($action === 'delete_product' && $user['role'] === 'admin') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE products SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $msg = 'Product deactivated successfully!';
            $msgType = 'warning';
        }
    }
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$catFilter = intval($_GET['category_id'] ?? 0);

$query = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.product_code LIKE ? OR p.brand LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}
if ($catFilter > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $catFilter;
}

$query .= " ORDER BY p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch Categories for modal dropdown
$categories = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY name ASC")->fetchAll();
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

        <!-- Page Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Product Management</h4>
                <p class="text-muted small mb-0">Manage hardware catalog items, prices, and stock parameters</p>
            </div>
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
                <i class="fa-solid fa-plus me-1"></i> ADD NEW PRODUCT
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, SKU code, brand..." value="<?php echo sanitize($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category_id" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $catFilter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">Filter</button>
                    <a href="products.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 custom-table">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Purchase</th>
                            <th>Selling Price</th>
                            <th>GST</th>
                            <th>Stock</th>
                            <th>Rack</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">No products found matching criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-dark"><?php echo sanitize($p['product_code']); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary"><?php echo sanitize($p['name']); ?></div>
                                        <small class="text-muted"><?php echo sanitize($p['unit']); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo sanitize($p['category_name']); ?></span></td>
                                    <td><?php echo sanitize($p['brand'] ?? '-'); ?></td>
                                    <td>₹<?php echo number_format($p['purchase_price'], 2); ?></td>
                                    <td class="fw-bold text-success">₹<?php echo number_format($p['selling_price'], 2); ?></td>
                                    <td><span class="badge bg-info-soft text-info"><?php echo $p['gst_rate']; ?>%</span></td>
                                    <td>
                                        <?php if ($p['current_stock'] <= $p['min_stock']): ?>
                                            <span class="badge bg-danger text-white fw-bold"><?php echo $p['current_stock']; ?> (Low)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-soft text-success fw-bold"><?php echo $p['current_stock']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo sanitize($p['location_rack'] ?? '-'); ?></small></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick='editProduct(<?php echo json_encode($p); ?>)'>
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Deactivate this product?')">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fa-solid fa-trash"></i>
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

<!-- Product Add / Edit Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="id" id="prod_id" value="0">
                
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle"><i class="fa-solid fa-box me-2 text-primary"></i> Add Hardware Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">SKU / Product Code *</label>
                            <input type="text" name="product_code" id="prod_code" class="form-control" required placeholder="PRD-HW-1001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Category *</label>
                            <select name="category_id" id="prod_category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Product Name *</label>
                            <input type="text" name="name" id="prod_name" class="form-control" required placeholder="e.g. PVC Pipe 110mm 3 Meter">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Brand</label>
                            <input type="text" name="brand" id="prod_brand" class="form-control" placeholder="Supreme, Havells, etc.">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Unit *</label>
                            <select name="unit" id="prod_unit" class="form-select">
                                <option value="Piece">Piece</option>
                                <option value="Meter">Meter</option>
                                <option value="Kg">Kg</option>
                                <option value="Bucket">Bucket</option>
                                <option value="Roll">Roll</option>
                                <option value="Packet">Packet</option>
                                <option value="Set">Set</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Purchase Price (₹)</label>
                            <input type="number" step="0.01" name="purchase_price" id="prod_purchase" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Selling Price (₹) *</label>
                            <input type="number" step="0.01" name="selling_price" id="prod_selling" class="form-control" required value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">MRP (₹)</label>
                            <input type="number" step="0.01" name="mrp" id="prod_mrp" class="form-control" value="0.00">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">GST Rate (%)</label>
                            <input type="number" step="0.01" name="gst_rate" id="prod_gst" class="form-control" value="18.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Stock Qty</label>
                            <input type="number" name="current_stock" id="prod_stock" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Min Stock Limit</label>
                            <input type="number" name="min_stock" id="prod_min_stock" class="form-control" value="5">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Rack / Location</label>
                            <input type="text" name="location_rack" id="prod_rack" class="form-control" placeholder="Rack A-1">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Description / Notes</label>
                            <textarea name="description" id="prod_desc" class="form-control" rows="2" placeholder="Product specifications..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('prod_id').value = '0';
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-box me-2 text-primary"></i> Add Hardware Product';
    document.getElementById('prod_code').value = 'PRD-HW-' + Math.floor(1000 + Math.random() * 9000);
    document.getElementById('prod_name').value = '';
    document.getElementById('prod_category').value = '';
    document.getElementById('prod_brand').value = '';
    document.getElementById('prod_unit').value = 'Piece';
    document.getElementById('prod_purchase').value = '0.00';
    document.getElementById('prod_selling').value = '0.00';
    document.getElementById('prod_mrp').value = '0.00';
    document.getElementById('prod_gst').value = '18.00';
    document.getElementById('prod_stock').value = '10';
    document.getElementById('prod_min_stock').value = '5';
    document.getElementById('prod_rack').value = '';
    document.getElementById('prod_desc').value = '';
}

function editProduct(p) {
    document.getElementById('prod_id').value = p.id;
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Edit Product';
    document.getElementById('prod_code').value = p.product_code;
    document.getElementById('prod_name').value = p.name;
    document.getElementById('prod_category').value = p.category_id;
    document.getElementById('prod_brand').value = p.brand || '';
    document.getElementById('prod_unit').value = p.unit || 'Piece';
    document.getElementById('prod_purchase').value = p.purchase_price;
    document.getElementById('prod_selling').value = p.selling_price;
    document.getElementById('prod_mrp').value = p.mrp;
    document.getElementById('prod_gst').value = p.gst_rate;
    document.getElementById('prod_stock').value = p.current_stock;
    document.getElementById('prod_min_stock').value = p.min_stock;
    document.getElementById('prod_rack').value = p.location_rack || '';
    document.getElementById('prod_desc').value = p.description || '';

    var myModal = new bootstrap.Modal(document.getElementById('productModal'));
    myModal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
