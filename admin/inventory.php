<?php
$pageTitle = "Inventory Management & Stock Control";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$msg = '';
$msgType = '';

// Handle Stock Manual Adjustment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'adjust_stock') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $adjust_type = sanitize($_POST['adjust_type'] ?? 'add'); // 'add' or 'reduce'
        $qty = intval($_POST['qty'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Manual Stock Adjustment');

        if ($product_id <= 0 || $qty <= 0) {
            $msg = 'Please select a valid product and positive stock quantity!';
            $msgType = 'danger';
        } else {
            $change = ($adjust_type === 'add') ? $qty : -$qty;
            
            // Get current stock
            $stmtCur = $db->prepare("SELECT current_stock, name FROM products WHERE id = ?");
            $stmtCur->execute([$product_id]);
            $prod = $stmtCur->fetch();

            if ($prod) {
                $newStock = max(0, $prod['current_stock'] + $change);
                
                // Update Product
                $stmtUpd = $db->prepare("UPDATE products SET current_stock = ? WHERE id = ?");
                $stmtUpd->execute([$newStock, $product_id]);

                // Insert Transaction Log
                $stmtAudit = $db->prepare("
                    INSERT INTO inventory_transactions (product_id, transaction_type, reference_id, qty_change, stock_after, notes)
                    VALUES (?, 'adjustment', 'ADJ-MANUAL', ?, ?, ?)
                ");
                $stmtAudit->execute([$product_id, $change, $newStock, $notes]);

                $msg = "Stock for '{$prod['name']}' updated successfully. New Stock: {$newStock}";
                $msgType = 'success';
            }
        }
    }
}

// Fetch Inventory Stats
$totalValuation = $db->query("SELECT COALESCE(SUM(current_stock * purchase_price), 0) FROM products WHERE status = 1")->fetchColumn();
$totalItemsCount = $db->query("SELECT COALESCE(SUM(current_stock), 0) FROM products WHERE status = 1")->fetchColumn();
$lowStockCount = $db->query("SELECT COUNT(*) FROM products WHERE current_stock <= min_stock AND status = 1")->fetchColumn();
$outOfStockCount = $db->query("SELECT COUNT(*) FROM products WHERE current_stock = 0 AND status = 1")->fetchColumn();

// Fetch Inventory List
$inventoryList = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 1 
    ORDER BY p.current_stock ASC
")->fetchAll();

// Fetch Audit History
$history = $db->query("
    SELECT it.*, p.name as product_name, p.product_code 
    FROM inventory_transactions it 
    JOIN products p ON it.product_id = p.id 
    ORDER BY it.id DESC LIMIT 15
")->fetchAll();
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
                <h4 class="fw-bold mb-1">Inventory & Stock Audit</h4>
                <p class="text-muted small mb-0">Track stock movements, valuation, low-stock alerts, and manual adjustments</p>
            </div>
            <button class="btn btn-warning fw-bold text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#stockModal">
                <i class="fa-solid fa-sliders me-1"></i> ADJUST STOCK
            </button>
        </div>

        <!-- 4 Summary Widgets -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
            <div class="col">
                <div class="stat-card p-3">
                    <span class="small text-muted fw-bold">Inventory Valuation</span>
                    <h4 class="fw-bold text-success mb-0">₹<?php echo number_format($totalValuation, 2); ?></h4>
                    <small class="text-muted">Total Stock Cost Value</small>
                </div>
            </div>
            <div class="col">
                <div class="stat-card p-3">
                    <span class="small text-muted fw-bold">Total Stock Quantity</span>
                    <h4 class="fw-bold text-dark mb-0"><?php echo number_format($totalItemsCount); ?> <small class="fs-6">Units</small></h4>
                    <small class="text-muted">Physical Items in Store</small>
                </div>
            </div>
            <div class="col">
                <div class="stat-card p-3">
                    <span class="small text-muted fw-bold">Low Stock Warning</span>
                    <h4 class="fw-bold text-warning mb-0"><?php echo number_format($lowStockCount); ?> <small class="fs-6">Products</small></h4>
                    <small class="text-muted">Below Reorder Level</small>
                </div>
            </div>
            <div class="col">
                <div class="stat-card p-3">
                    <span class="small text-muted fw-bold">Out of Stock</span>
                    <h4 class="fw-bold text-danger mb-0"><?php echo number_format($outOfStockCount); ?> <small class="fs-6">Products</small></h4>
                    <small class="text-muted">Zero Quantity Left</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left: Current Stock Status Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 p-3">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-warehouse text-primary me-2"></i> Current Stock Status</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Min</th>
                                    <th>Stock</th>
                                    <th>Rack</th>
                                    <th>Stock Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventoryList as $item): ?>
                                    <tr>
                                        <td class="small fw-bold"><?php echo sanitize($item['product_code']); ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo sanitize($item['name']); ?></div>
                                            <small class="text-muted"><?php echo sanitize($item['unit']); ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($item['category_name']); ?></span></td>
                                        <td><span class="badge bg-light text-dark"><?php echo $item['min_stock']; ?></span></td>
                                        <td>
                                            <?php if ($item['current_stock'] == 0): ?>
                                                <span class="badge bg-danger text-white fw-bold">OUT OF STOCK</span>
                                            <?php elseif ($item['current_stock'] <= $item['min_stock']): ?>
                                                <span class="badge bg-warning text-dark fw-bold"><?php echo $item['current_stock']; ?> (Low)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-soft text-success fw-bold"><?php echo $item['current_stock']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-muted"><?php echo sanitize($item['location_rack'] ?? '-'); ?></small></td>
                                        <td class="fw-bold">₹<?php echo number_format($item['current_stock'] * $item['purchase_price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Stock Audit Trail -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 p-3">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Stock Movement Log</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-muted">
                                    <th>Product</th>
                                    <th class="text-center">Type</th>
                                    <th class="text-end">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark small text-truncate" style="max-width:140px;"><?php echo sanitize($h['product_name']); ?></div>
                                            <small class="text-muted" style="font-size:0.7rem;"><?php echo date('d/m H:i', strtotime($h['created_at'])); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border text-uppercase" style="font-size:0.65rem;"><?php echo sanitize($h['transaction_type']); ?></span>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $h['qty_change'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $h['qty_change'] > 0 ? '+'.$h['qty_change'] : $h['qty_change']; ?>
                                        </td>
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

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="adjust_stock">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-sliders me-2 text-warning"></i> Manual Stock Adjustment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Choose product...</option>
                            <?php foreach ($inventoryList as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['name']); ?> (Current Stock: <?php echo $p['current_stock']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Adjustment Type *</label>
                            <select name="adjust_type" class="form-select" required>
                                <option value="add">➕ Add Stock (+)</option>
                                <option value="reduce">➖ Reduce Stock (-)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Quantity *</label>
                            <input type="number" name="qty" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reason / Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Damaged piece, audit count, sample use">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Stock Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
