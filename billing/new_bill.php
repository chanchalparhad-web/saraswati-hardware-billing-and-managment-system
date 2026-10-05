<?php
$pageTitle = "New Sales Bill (POS)";
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();

// Fetch Active Products
$stmt = $db->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 1 ORDER BY p.name ASC");
$products = $stmt->fetchAll();

// Fetch Active Customers
$custStmt = $db->query("SELECT * FROM customers WHERE status = 1 ORDER BY id ASC");
$customers = $custStmt->fetchAll();

// Fetch Categories for Filter
$catStmt = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY name ASC");
$categories = $catStmt->fetchAll();
?>

<div id="content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-0 pos-container">
        <div class="row g-0 h-100">

            <!-- LEFT PANEL: CART & BILL SUMMARY (5 Cols) -->
            <div class="col-lg-5 col-xl-5 pos-cart-panel">
                
                <!-- Bill Header -->
                <div class="pos-cart-header">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-danger text-white px-2 py-1"><i class="fa-solid fa-bolt me-1"></i> OFFLINE POS</span>
                        <div class="small">
                            <span class="kbd-shortcut me-1">F2</span>Search
                            <span class="kbd-shortcut me-1">F8</span>Cash
                            <span class="kbd-shortcut me-1">F10</span>Save
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1 text-secondary">Customer Name *</label>
                            <input type="text" id="pos-customer-name" class="form-control form-control-sm fw-bold" placeholder="e.g. Ramesh Kumar" value="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1 text-secondary">Mobile Number</label>
                            <input type="tel" id="pos-customer-mobile" class="form-control form-control-sm fw-bold" placeholder="e.g. 9876543210" value="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1 text-secondary">Payment Mode</label>
                            <select id="pos-payment-mode" class="form-select form-select-sm fw-bold bg-light">
                                <option value="cash" selected>💵 Cash (F8)</option>
                                <option value="upi">📱 UPI / QR (F9)</option>
                                <option value="card">💳 Card</option>
                                <option value="credit">📝 Credit (Udhaar)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Cart Items Table -->
                <div class="pos-cart-table-wrapper">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th>Item</th>
                                <th class="text-center" style="width:110px;">Qty</th>
                                <th class="text-end">Rate</th>
                                <th class="text-center">GST</th>
                                <th class="text-end">Total</th>
                                <th class="text-center"><i class="fa-solid fa-trash"></i></th>
                            </tr>
                        </thead>
                        <tbody id="pos-cart-tbody">
                            <!-- Populated dynamically by app.js -->
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-cart-shopping fa-3x mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">No items added to bill yet.</p>
                                    <small>Search product or click from right catalog panel to add.</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Bill Footer Summary & Save Action -->
                <div class="pos-cart-footer">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Items Count:</span>
                                <span id="pos-total-items" class="fw-bold text-dark">0</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Subtotal:</span>
                                <span id="pos-subtotal" class="fw-bold text-dark">₹0.00</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>GST Tax Total:</span>
                                <span id="pos-tax-amount" class="fw-bold text-dark">₹0.00</span>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="d-flex justify-content-between small align-items-center mb-1">
                                <span class="text-muted">Discount:</span>
                                <div class="input-group input-group-sm" style="width: 120px;">
                                    <input type="number" id="pos-discount-input" class="form-control text-end fw-bold" value="0" min="0">
                                    <select id="pos-discount-type" class="form-select px-1 fw-bold">
                                        <option value="fixed">₹</option>
                                        <option value="percent">%</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Discount Amount:</span>
                                <span id="pos-discount-amount" class="fw-bold text-danger">- ₹0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-dark text-white p-2 rounded-3 d-flex justify-content-between align-items-center mb-3 shadow-sm">
                        <span class="fs-6 text-white-50">GRAND TOTAL:</span>
                        <span id="pos-grand-total" class="fs-3 fw-bold text-warning">₹0.00</span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0 text-secondary">Amount Paid (₹)</label>
                            <input type="number" id="pos-paid-amount" class="form-control form-control-sm fw-bold" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0 text-secondary">Balance / Due</label>
                            <div id="pos-due-amount" class="form-control form-control-sm bg-light fw-bold text-danger">₹0.00</div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex">
                        <button type="button" id="btn-save-bill" class="btn btn-success flex-fill py-2 fw-bold" onclick="saveBill(false)">
                            <i class="fa-solid fa-floppy-disk me-1"></i> SAVE BILL
                        </button>
                        <button type="button" id="btn-save-print" class="btn btn-primary flex-fill py-2 fw-bold" onclick="saveBill(true)">
                            <i class="fa-solid fa-print me-1"></i> SAVE & PRINT (F10)
                        </button>
                    </div>
                </div>

            </div>

            <!-- RIGHT PANEL: PRODUCT CATALOG & BARCODE SEARCH (7 Cols) -->
            <div class="col-lg-7 col-xl-7 pos-product-panel">
                
                <!-- Search & Category Filters -->
                <div class="bg-white p-3 rounded-3 shadow-sm mb-3">
                    <div class="input-group input-group-lg mb-2">
                        <span class="input-group-text bg-primary text-white"><i class="fa-solid fa-barcode"></i></span>
                        <input type="text" id="pos-product-search" class="form-control fw-bold" placeholder="Scan Barcode or Search by Product Name / SKU Code (Press F2)..." autofocus>
                        <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('pos-product-search').value=''; renderPOSCart();"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <div class="d-flex gap-2 overflow-x-auto pb-1" style="white-space: nowrap;">
                        <button class="btn btn-sm btn-dark px-3 rounded-pill" onclick="filterCategory(0)">All Products</button>
                        <?php foreach ($categories as $cat): ?>
                            <button class="btn btn-sm btn-outline-secondary px-3 rounded-pill" onclick="filterCategory(<?php echo $cat['id']; ?>)">
                                <?php echo sanitize($cat['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Product Catalog Grid -->
                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-2" id="pos-products-grid">
                    <?php foreach ($products as $p): ?>
                        <div class="col pos-product-item" 
                             data-category="<?php echo $p['category_id']; ?>"
                             data-name="<?php echo sanitize($p['name']); ?>"
                             data-code="<?php echo sanitize($p['product_code']); ?>"
                             data-barcode="<?php echo sanitize($p['barcode'] ?? ''); ?>">
                            <div class="card h-100 product-card p-2 shadow-sm" onclick='addToCart(<?php echo json_encode($p); ?>)'>
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="badge bg-light text-secondary border"><?php echo sanitize($p['brand'] ?? 'Gen'); ?></span>
                                    <?php if ($p['current_stock'] <= $p['min_stock']): ?>
                                        <span class="badge bg-danger text-white">Low: <?php echo $p['current_stock']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success-soft">Stock: <?php echo $p['current_stock']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?php echo sanitize($p['name']); ?>">
                                    <?php echo sanitize($p['name']); ?>
                                </h6>
                                <div class="small text-muted mb-2"><?php echo sanitize($p['product_code']); ?></div>
                                <div class="mt-auto d-flex align-items-center justify-content-between border-top pt-2">
                                    <span class="fw-bold text-primary fs-6">₹<?php echo number_format($p['selling_price'], 2); ?></span>
                                    <small class="text-muted" style="font-size: 0.75rem;">+<?php echo $p['gst_rate']; ?>% GST</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

        </div>
    </div>
</div>

<script>
function filterCategory(catId) {
    const items = document.querySelectorAll('.pos-product-item');
    items.forEach(item => {
        if (catId === 0 || item.getAttribute('data-category') == catId) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
