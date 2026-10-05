<?php
$pageTitle = "My Shopping Cart";
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">

        <h4 class="fw-bold mb-4"><i class="fa-solid fa-basket-shopping text-primary me-2"></i> My Shopping Cart</h4>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 p-3">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="cart-table-body">
                                <!-- Rendered via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 p-4">
                    <h5 class="fw-bold mb-3">Order Summary</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal:</span>
                        <span id="summary-subtotal" class="fw-bold">₹0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">GST Tax:</span>
                        <span id="summary-tax" class="fw-bold">₹0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-4">
                        <span class="fs-5 fw-bold">Grand Total:</span>
                        <span id="summary-total" class="fs-4 fw-bold text-primary">₹0.00</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Delivery / Shipping Address *</label>
                        <textarea id="shipping-address" class="form-control" rows="3" placeholder="Enter full address for order delivery..."></textarea>
                    </div>

                    <button class="btn btn-success w-100 py-3 fw-bold rounded-3 fs-5" onclick="placeOrder()">
                        <i class="fa-solid fa-check-circle me-2"></i> PLACE ORDER NOW
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    renderCart();
});

function renderCart() {
    let cart = JSON.parse(localStorage.getItem('customer_cart') || '[]');
    let tbody = document.getElementById('cart-table-body');
    tbody.innerHTML = '';

    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">Your cart is empty. <a href="products.php">Shop Products</a></td></tr>';
        document.getElementById('summary-subtotal').textContent = '₹0.00';
        document.getElementById('summary-tax').textContent = '₹0.00';
        document.getElementById('summary-total').textContent = '₹0.00';
        return;
    }

    let subtotal = 0;
    let totalTax = 0;

    cart.forEach((item, index) => {
        let lineSubtotal = item.qty * item.price;
        let lineTax = (lineSubtotal * (item.gst || 18)) / 100;
        subtotal += lineSubtotal;
        totalTax += lineTax;

        let tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="fw-bold">${item.name}</td>
            <td class="text-center" style="width:120px;">
                <input type="number" class="form-control form-control-sm text-center fw-bold" value="${item.qty}" min="1" onchange="updateCartQty(${index}, this.value)">
            </td>
            <td class="text-end">₹${parseFloat(item.price).toFixed(2)}</td>
            <td class="text-end fw-bold">₹${(lineSubtotal + lineTax).toFixed(2)}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-link text-danger" onclick="removeCartItem(${index})"><i class="fa-solid fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    let grandTotal = subtotal + totalTax;
    document.getElementById('summary-subtotal').textContent = '₹' + subtotal.toFixed(2);
    document.getElementById('summary-tax').textContent = '₹' + totalTax.toFixed(2);
    document.getElementById('summary-total').textContent = '₹' + grandTotal.toFixed(2);
}

function updateCartQty(index, qty) {
    let cart = JSON.parse(localStorage.getItem('customer_cart') || '[]');
    cart[index].qty = parseInt(qty) || 1;
    localStorage.setItem('customer_cart', JSON.stringify(cart));
    renderCart();
}

function removeCartItem(index) {
    let cart = JSON.parse(localStorage.getItem('customer_cart') || '[]');
    cart.splice(index, 1);
    localStorage.setItem('customer_cart', JSON.stringify(cart));
    renderCart();
}

function placeOrder() {
    let cart = JSON.parse(localStorage.getItem('customer_cart') || '[]');
    let address = document.getElementById('shipping-address').value.trim();

    if (cart.length === 0) {
        alert('Cart is empty!');
        return;
    }
    if (address === '') {
        alert('Please enter shipping delivery address!');
        return;
    }

    fetch('checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: cart, address: address })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Order Placed Successfully! Order No: ' + data.order_no);
            localStorage.removeItem('customer_cart');
            window.location.href = 'orders.php';
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
