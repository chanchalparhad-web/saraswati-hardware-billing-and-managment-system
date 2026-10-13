<?php
$pageTitle = "New Sales Bill (POS)";
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();

// Fetch Active Products
$stmt = $db->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 1 ORDER BY p.id DESC");
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
                            <span class="kbd-shortcut me-1">F3</span>Quick Add
                            <span class="kbd-shortcut me-1">F8</span>Cash
                            <span class="kbd-shortcut me-1">F10</span>Save & Print
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
                                <option value="upi" selected>📱 UPI / QR💵 Cash (F9)</option>
                                <option value="cash">💵 Cash (F8)</option>
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
                                    <small>Search product or use Quick Add to instantly bill items.</small>
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

            <!-- RIGHT PANEL: PRODUCT CATALOG & QUICK ADD (7 Cols) -->
            <div class="col-lg-7 col-xl-7 pos-product-panel">
                
                <!-- Search & Quick Add Section -->
                <div class="bg-white p-3 rounded-3 shadow-sm mb-3">
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-md-7">
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" id="pos-product-search" class="form-control fw-bold" placeholder="Search Product by Name or Code (Press F2)..." autofocus>
                                <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('pos-product-search').value=''; renderPOSCart();"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <button class="btn btn-warning w-100 fw-bold text-dark shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#quickAddProductModal">
                                <i class="fa-solid fa-bolt me-1"></i> Quick Add Product (F3)
                            </button>
                        </div>
                    </div>

                    <!-- Instant Inline Add to Bill & Catalog Form -->
                    <div class="p-2 rounded bg-light border mb-2">
                        <div class="small fw-bold text-secondary mb-1">
                            <i class="fa-solid fa-bolt text-warning me-1"></i> Fast Add Item Directly to Bill & Catalog:
                        </div>
                        <form id="pos-inline-quick-add" onsubmit="handleInlineQuickAdd(event)" class="row g-2 align-items-center">
                            <div class="col-12 col-sm-4">
                                <input type="text" id="inline-prod-name" class="form-control form-control-sm fw-bold" placeholder="Product Name (e.g. Berger Primer 1L)" required>
                            </div>
                            <div class="col-6 col-sm-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" id="inline-prod-price" class="form-control fw-bold" placeholder="Price" required min="0.01">
                                </div>
                            </div>
                            <div class="col-6 col-sm-2">
                                <select id="inline-prod-unit" class="form-select form-select-sm">
                                    <option value="Piece">Piece</option>
                                    <option value="Meter">Meter</option>
                                    <option value="Kg">Kg</option>
                                    <option value="Packet">Packet</option>
                                    <option value="Roll">Roll</option>
                                    <option value="Bucket">Bucket</option>
                                    <option value="Litre">Litre</option>
                                    <option value="Set">Set</option>
                                </select>
                            </div>
                            <div class="col-6 col-sm-2">
                                <select id="inline-prod-gst" class="form-select form-select-sm">
                                    <option value="18" selected>18% GST</option>
                                    <option value="12">12% GST</option>
                                    <option value="5">5% GST</option>
                                    <option value="28">28% GST</option>
                                    <option value="0">0% GST</option>
                                </select>
                            </div>
                            <div class="col-6 col-sm-2">
                                <button type="submit" id="btn-inline-add" class="btn btn-sm btn-success w-100 fw-bold">
                                    <i class="fa-solid fa-plus me-1"></i> Add
                                </button>
                            </div>
                        </form>
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
                    <?php if (empty($products)): ?>
                        <div class="col-12 py-5 text-center text-muted w-100" id="pos-empty-catalog-msg">
                            <i class="fa-solid fa-boxes-stacked fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5 class="fw-bold">No Products in Catalog Yet</h5>
                            <p class="mb-3 small">Use the <strong>Fast Add Item</strong> form above or press <strong>F3</strong> to instantly add items while billing!</p>
                            <button class="btn btn-warning fw-bold text-dark px-3 shadow-sm" onclick="document.getElementById('inline-prod-name').focus()">
                                <i class="fa-solid fa-bolt me-1"></i> Add First Item to Bill
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <div class="col pos-product-item" 
                                 data-category="<?php echo $p['category_id']; ?>"
                                 data-name="<?php echo sanitize($p['name']); ?>"
                                 data-code="<?php echo sanitize($p['product_code']); ?>">
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
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- Modal: Detailed Quick Add Product to Bill & Catalog (F3) -->
<div class="modal fade" id="quickAddProductModal" tabindex="-1" aria-labelledby="quickAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="pos-modal-quick-add" onsubmit="handleModalQuickAdd(event)">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="quickAddModalLabel">
                        <i class="fa-solid fa-bolt text-warning me-2"></i> Quick Add Product to Bill & Catalog
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Product / Item Name *</label>
                            <input type="text" id="quick-prod-name" class="form-control fw-bold" placeholder="e.g. Berger Primer 1L / Havells Wire 90m" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Selling Price (₹) *</label>
                            <input type="number" step="0.01" id="quick-prod-price" class="form-control fw-bold" placeholder="0.00" required min="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Quantity for this Bill *</label>
                            <input type="number" id="quick-prod-bill-qty" class="form-control fw-bold" value="1" min="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Unit</label>
                            <select id="quick-prod-unit" class="form-select">
                                <option value="Piece">Piece</option>
                                <option value="Meter">Meter</option>
                                <option value="Kg">Kg</option>
                                <option value="Bucket">Bucket</option>
                                <option value="Roll">Roll</option>
                                <option value="Packet">Packet</option>
                                <option value="Litre">Litre</option>
                                <option value="Set">Set</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">GST Rate (%)</label>
                            <select id="quick-prod-gst" class="form-select">
                                <option value="18" selected>18% GST</option>
                                <option value="12">12% GST</option>
                                <option value="5">5% GST</option>
                                <option value="28">28% GST</option>
                                <option value="0">0% Exempt</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Category</label>
                            <select id="quick-prod-category" class="form-select">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Stock Quantity</label>
                            <input type="number" id="quick-prod-stock" class="form-control" value="100" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn-modal-quick-save" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Add to Bill & Save to Catalog
                    </button>
                </div>
            </form>
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

// Injects newly added product directly into catalog grid so cashier sees it immediately
function appendProductToGrid(product) {
    const emptyMsg = document.getElementById('pos-empty-catalog-msg');
    if (emptyMsg) {
        emptyMsg.remove();
    }

    const grid = document.getElementById('pos-products-grid');
    if (!grid) return;

    const col = document.createElement('div');
    col.className = 'col pos-product-item';
    col.setAttribute('data-category', product.category_id || '1');
    col.setAttribute('data-name', product.name || '');
    col.setAttribute('data-code', product.product_code || '');

    col.innerHTML = `
        <div class="card h-100 product-card p-2 shadow-sm border-warning">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <span class="badge bg-warning text-dark border">New</span>
                <span class="badge bg-success-soft">Stock: ${product.current_stock || 100}</span>
            </div>
            <h6 class="fw-bold text-dark mb-1 text-truncate" title="${product.name}">
                ${product.name}
            </h6>
            <div class="small text-muted mb-2">${product.product_code || ''}</div>
            <div class="mt-auto d-flex align-items-center justify-content-between border-top pt-2">
                <span class="fw-bold text-primary fs-6">₹${parseFloat(product.selling_price).toFixed(2)}</span>
                <small class="text-muted" style="font-size: 0.75rem;">+${product.gst_rate || 18}% GST</small>
            </div>
        </div>
    `;

    col.querySelector('.product-card').onclick = function() {
        if (typeof addToCart === 'function') {
            addToCart(product);
        }
    };

    grid.insertBefore(col, grid.firstChild);
}

// Handle fast inline addition
function handleInlineQuickAdd(e) {
    e.preventDefault();
    const nameEl = document.getElementById('inline-prod-name');
    const priceEl = document.getElementById('inline-prod-price');
    const unitEl = document.getElementById('inline-prod-unit');
    const gstEl = document.getElementById('inline-prod-gst');
    const btn = document.getElementById('btn-inline-add');

    const name = nameEl.value.trim();
    const price = parseFloat(priceEl.value);

    if (!name || isNaN(price) || price <= 0) {
        alert('Please enter a valid product name and price.');
        return;
    }

    if (btn) btn.disabled = true;

    const payload = {
        name: name,
        selling_price: price,
        unit: unitEl ? unitEl.value : 'Piece',
        gst_rate: gstEl ? parseFloat(gstEl.value) : 18.0,
        current_stock: 100
    };

    const targetUrl = (window.APP_BASE_URL ? window.APP_BASE_URL + '/billing/quick_add_product.php' : '../billing/quick_add_product.php');

    fetch(targetUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (btn) btn.disabled = false;
        if (data.success && data.product) {
            // Add product to cart immediately
            if (typeof addToCart === 'function') {
                addToCart(data.product);
            }
            appendProductToGrid(data.product);

            // Reset inputs & refocus
            nameEl.value = '';
            priceEl.value = '';
            nameEl.focus();
        } else {
            alert('Could not save product: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        console.error('Quick Add error:', err);
        // Fallback: Add client-side custom item to cart
        if (typeof addToCart === 'function') {
            addToCart({
                id: 0,
                name: name,
                product_code: 'PRD-HW-' + Math.floor(1000 + Math.random() * 9000),
                selling_price: price,
                gst_rate: gstEl ? parseFloat(gstEl.value) : 18.0,
                unit: unitEl ? unitEl.value : 'Piece',
                current_stock: 100
            });
        }
        nameEl.value = '';
        priceEl.value = '';
    });
}

// Handle modal quick add
function handleModalQuickAdd(e) {
    e.preventDefault();
    const nameEl = document.getElementById('quick-prod-name');
    const priceEl = document.getElementById('quick-prod-price');
    const qtyEl = document.getElementById('quick-prod-bill-qty');
    const unitEl = document.getElementById('quick-prod-unit');
    const gstEl = document.getElementById('quick-prod-gst');
    const catEl = document.getElementById('quick-prod-category');
    const stockEl = document.getElementById('quick-prod-stock');
    const btn = document.getElementById('btn-modal-quick-save');

    const name = nameEl.value.trim();
    const price = parseFloat(priceEl.value);
    const billQty = parseInt(qtyEl ? qtyEl.value : 1) || 1;

    if (!name || isNaN(price) || price <= 0) {
        alert('Please enter a valid product name and price.');
        return;
    }

    if (btn) btn.disabled = true;

    const payload = {
        name: name,
        selling_price: price,
        unit: unitEl ? unitEl.value : 'Piece',
        gst_rate: gstEl ? parseFloat(gstEl.value) : 18.0,
        category_id: catEl ? parseInt(catEl.value) : 1,
        current_stock: stockEl ? parseInt(stockEl.value) : 100
    };

    const targetUrl = (window.APP_BASE_URL ? window.APP_BASE_URL + '/billing/quick_add_product.php' : '../billing/quick_add_product.php');

    fetch(targetUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (btn) btn.disabled = false;
        if (data.success && data.product) {
            // Add product to cart with chosen quantity
            for (let i = 0; i < billQty; i++) {
                if (typeof addToCart === 'function') {
                    addToCart(data.product);
                }
            }
            appendProductToGrid(data.product);

            // Close modal
            const modalEl = document.getElementById('quickAddProductModal');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            // Reset form
            document.getElementById('pos-modal-quick-add').reset();
            if (qtyEl) qtyEl.value = '1';
            if (stockEl) stockEl.value = '100';
        } else {
            alert('Could not save product: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        console.error('Modal Quick Add error:', err);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
