/* ============================================================
   HARDWARE STORE MANAGEMENT SYSTEM - JAVASCRIPT LOGIC
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // --- POS BILLING SYSTEM ENGINE ---
    const posCartTable = document.getElementById('pos-cart-tbody');
    const searchInput = document.getElementById('pos-product-search');
    const discountInput = document.getElementById('pos-discount-input');
    const discountTypeSelect = document.getElementById('pos-discount-type');
    const paymentModeSelect = document.getElementById('pos-payment-mode');
    const customerSelect = document.getElementById('pos-customer-select');
    const paidAmountInput = document.getElementById('pos-paid-amount');

    // Summary Elements
    const subtotalEl = document.getElementById('pos-subtotal');
    const discountEl = document.getElementById('pos-discount-amount');
    const taxEl = document.getElementById('pos-tax-amount');
    const grandTotalEl = document.getElementById('pos-grand-total');
    const dueEl = document.getElementById('pos-due-amount');
    const totalItemsEl = document.getElementById('pos-total-items');

    let cart = [];
    let currentBillType = 'gst';

    // Toggle GST / Non-GST Bill Mode
    window.setBillType = function (type) {
        currentBillType = (type === 'non_gst') ? 'non_gst' : 'gst';
        const indicator = document.getElementById('bill-mode-indicator');
        const subtotalLabel = document.getElementById('pos-subtotal-label');
        const taxLabel = document.getElementById('pos-tax-label');

        if (currentBillType === 'non_gst') {
            if (indicator) indicator.innerHTML = '📋 Non-GST Bill (Estimate / Cash Memo)';
            if (subtotalLabel) subtotalLabel.textContent = 'Subtotal:';
            if (taxLabel) taxLabel.textContent = 'Tax (0% Non-GST):';
        } else {
            if (indicator) indicator.innerHTML = '🧾 GST Bill (Tax Invoice)';
            if (subtotalLabel) subtotalLabel.textContent = 'Taxable Subtotal:';
            if (taxLabel) taxLabel.textContent = 'GST Tax (Included):';
        }
        renderPOSCart();
    };

    // Initialize POS Keyboard Shortcuts if POS table exists
    if (posCartTable) {
        initPOSShortcuts();
        if (searchInput) searchInput.focus();
    }

    // Keyboard Shortcuts
    function initPOSShortcuts() {
        document.addEventListener('keydown', function (e) {
            // F2: Focus search
            if (e.key === 'F2') {
                e.preventDefault();
                if (searchInput) searchInput.focus();
            }
            // F3: Quick Add Item (focus inline name or trigger modal)
            if (e.key === 'F3') {
                e.preventDefault();
                const inlineInput = document.getElementById('inline-prod-name');
                if (inlineInput) {
                    inlineInput.focus();
                } else {
                    const quickModalBtn = document.querySelector('[data-bs-target="#quickAddProductModal"]');
                    if (quickModalBtn) quickModalBtn.click();
                }
            }
            // F4: Clear / Reset bill
            if (e.key === 'F4') {
                e.preventDefault();
                if (confirm('Are you sure you want to reset the current bill?')) {
                    resetPOSCart();
                }
            }
            // F8: Select Cash
            if (e.key === 'F8') {
                e.preventDefault();
                if (paymentModeSelect) paymentModeSelect.value = 'cash';
            }
            // F9: Select UPI
            if (e.key === 'F9') {
                e.preventDefault();
                if (paymentModeSelect) paymentModeSelect.value = 'upi';
            }
            // F10: Save & Print Bill
            if (e.key === 'F10') {
                e.preventDefault();
                const saveBtn = document.getElementById('btn-save-print');
                if (saveBtn) saveBtn.click();
            }
        });
    }

    // Add product to POS Cart
    window.addToCart = function (product) {
        const prodName = product.name || 'Item';
        const maxStock = (product.current_stock !== undefined && product.current_stock !== null) ? parseInt(product.current_stock) : 999;

        const existingIndex = (product.id && product.id > 0)
            ? cart.findIndex(item => item.id === product.id)
            : cart.findIndex(item => (item.name || '').toLowerCase() === prodName.toLowerCase());

        if (existingIndex > -1) {
            if (cart[existingIndex].qty + 1 > cart[existingIndex].max_stock) {
                alert(`Stock Limit Reached! Only ${cart[existingIndex].max_stock} units available.`);
                return;
            }
            cart[existingIndex].qty += 1;
        } else {
            if (maxStock <= 0) {
                alert('This product is Out of Stock!');
                return;
            }
            cart.push({
                id: product.id || 0,
                name: prodName,
                code: product.product_code || 'PRD-CUSTOM',
                price: parseFloat(product.selling_price || 0),
                gst_rate: parseFloat(product.gst_rate !== undefined ? product.gst_rate : 18),
                qty: 1,
                max_stock: maxStock,
                unit: product.unit || 'Piece'
            });
        }

        renderPOSCart();
    };

    // Remove product from POS Cart
    window.removeFromCart = function (index) {
        cart.splice(index, 1);
        renderPOSCart();
    };

    // Update Item Qty
    window.updateQty = function (index, newQty) {
        newQty = parseInt(newQty);
        if (isNaN(newQty) || newQty <= 0) {
            newQty = 1;
        }
        if (newQty > cart[index].max_stock) {
            alert(`Stock limit reached! Max stock available: ${cart[index].max_stock}`);
            newQty = cart[index].max_stock;
        }
        cart[index].qty = newQty;
        renderPOSCart();
    };

    // Render POS Cart Table
    function renderPOSCart() {
        if (!posCartTable) return;

        posCartTable.innerHTML = '';

        if (cart.length === 0) {
            posCartTable.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-cart-shopping fa-3x mb-3 text-secondary opacity-50"></i>
                        <p class="mb-0">No items added to bill yet.</p>
                        <small>Search product or click from right catalog panel to add.</small>
                    </td>
                </tr>
            `;
            updatePOSSummary(0, 0, 0, 0, 0);
            return;
        }

        let totalGross = 0;
        let taxableSubtotal = 0;
        let totalTax = 0;

        cart.forEach((item, index) => {
            const lineTotal = item.qty * item.price;
            const gstRate = (currentBillType === 'non_gst') ? 0 : (parseFloat(item.gst_rate) || 0);
            const lineTaxable = gstRate > 0 ? (lineTotal / (1 + (gstRate / 100))) : lineTotal;
            const lineTax = lineTotal - lineTaxable;

            totalGross += lineTotal;
            taxableSubtotal += lineTaxable;
            totalTax += lineTax;

            const gstBadge = (currentBillType === 'non_gst')
                ? `<span class="badge bg-secondary">0% (Non-GST)</span>`
                : `<span class="badge bg-light text-dark border">${item.gst_rate}% (Incl)</span>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="align-middle fw-bold">
                    ${item.name}
                    <div class="small text-muted">${item.code} | Unit: ${item.unit}</div>
                </td>
                <td class="align-middle text-center" style="width: 130px;">
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary" type="button" onclick="updateQty(${index}, ${item.qty - 1})">-</button>
                        <input type="number" class="form-control text-center fw-bold" value="${item.qty}" min="1" max="${item.max_stock}" onchange="updateQty(${index}, this.value)">
                        <button class="btn btn-outline-secondary" type="button" onclick="updateQty(${index}, ${item.qty + 1})">+</button>
                    </div>
                </td>
                <td class="align-middle text-end">₹${item.price.toFixed(2)}</td>
                <td class="align-middle text-center">${gstBadge}</td>
                <td class="align-middle text-end fw-bold">₹${lineTotal.toFixed(2)}</td>
                <td class="align-middle text-center">
                    <button class="btn btn-sm btn-link text-danger" onclick="removeFromCart(${index})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;
            posCartTable.appendChild(tr);
        });

        // Calculate Discount
        let discountVal = parseFloat(discountInput ? discountInput.value : 0) || 0;
        let discountType = discountTypeSelect ? discountTypeSelect.value : 'fixed';
        let discountAmount = 0;

        if (discountType === 'percent') {
            discountAmount = (totalGross * discountVal) / 100;
        } else {
            discountAmount = discountVal;
        }

        if (discountAmount > totalGross) {
            discountAmount = totalGross;
        }

        const grandTotal = Math.max(0, totalGross - discountAmount);

        // Update Paid input default to Grand Total if cash/upi
        if (paidAmountInput && (!paidAmountInput.dataset.userEdited || paidAmountInput.dataset.userEdited === 'false')) {
            paidAmountInput.value = grandTotal.toFixed(2);
        }

        let paidAmount = parseFloat(paidAmountInput ? paidAmountInput.value : grandTotal) || 0;
        let dueAmount = Math.max(0, grandTotal - paidAmount);

        updatePOSSummary(taxableSubtotal, discountAmount, totalTax, grandTotal, dueAmount);
    }

    function updatePOSSummary(subtotal, discount, tax, grandTotal, due) {
        if (subtotalEl) subtotalEl.textContent = '₹' + subtotal.toFixed(2);
        if (discountEl) discountEl.textContent = '- ₹' + discount.toFixed(2);
        if (taxEl) taxEl.textContent = '₹' + tax.toFixed(2);
        if (grandTotalEl) grandTotalEl.textContent = '₹' + grandTotal.toFixed(2);
        if (dueEl) dueEl.textContent = '₹' + due.toFixed(2);
        if (totalItemsEl) totalItemsEl.textContent = cart.length;
    }

    // Event Listeners for inputs
    if (discountInput) {
        discountInput.addEventListener('input', renderPOSCart);
    }
    if (discountTypeSelect) {
        discountTypeSelect.addEventListener('change', renderPOSCart);
    }
    if (paidAmountInput) {
        paidAmountInput.addEventListener('input', function() {
            this.dataset.userEdited = 'true';
            renderPOSCart();
        });
    }

    // Reset POS Cart
    window.resetPOSCart = function() {
        cart = [];
        if (discountInput) discountInput.value = 0;
        if (paidAmountInput) {
            paidAmountInput.value = '';
            paidAmountInput.dataset.userEdited = 'false';
        }
        renderPOSCart();
    };

    // Save Bill AJAX
    window.saveBill = function (action = 'save') {
        if (cart.length === 0) {
            alert('Please add at least one product to the bill!');
            return;
        }

        // action can be 'save', 'print', 'download', or boolean (true -> 'print', false -> 'save')
        let mode = 'save';
        if (action === true || action === 'print') {
            mode = 'print';
        } else if (action === 'download') {
            mode = 'download';
        }

        const customerNameInput = document.getElementById('pos-customer-name');
        const customerMobileInput = document.getElementById('pos-customer-mobile');

        const customerName = customerNameInput ? customerNameInput.value.trim() : '';
        const customerMobile = customerMobileInput ? customerMobileInput.value.trim() : '';
        const paymentMode = paymentModeSelect ? paymentModeSelect.value : 'cash';
        const discountVal = parseFloat(discountInput ? discountInput.value : 0) || 0;
        const discountType = discountTypeSelect ? discountTypeSelect.value : 'fixed';
        const paidAmount = parseFloat(paidAmountInput ? paidAmountInput.value : 0);

        if (!customerName) {
            alert('Please enter Customer Name for the bill!');
            if (customerNameInput) customerNameInput.focus();
            return;
        }

        const payload = {
            customer_name: customerName,
            customer_mobile: customerMobile,
            payment_mode: paymentMode,
            bill_type: currentBillType,
            discount: discountVal,
            discount_type: discountType,
            paid_amount: paidAmount,
            items: cart
        };

        const saveBtn = document.getElementById('btn-save-bill');
        const savePrintBtn = document.getElementById('btn-save-print');
        const saveDownloadBtn = document.getElementById('btn-save-download');
        if (saveBtn) saveBtn.disabled = true;
        if (savePrintBtn) savePrintBtn.disabled = true;
        if (saveDownloadBtn) saveDownloadBtn.disabled = true;

        const saveBillUrl = (window.APP_BASE_URL ? window.APP_BASE_URL + '/billing/save_bill.php' : '../billing/save_bill.php');

        fetch(saveBillUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (saveBtn) saveBtn.disabled = false;
            if (savePrintBtn) savePrintBtn.disabled = false;
            if (saveDownloadBtn) saveDownloadBtn.disabled = false;

            if (data.success) {
                const printBaseUrl = (window.APP_BASE_URL ? window.APP_BASE_URL + `/billing/print_invoice.php` : `../billing/print_invoice.php`);
                const typeParam = `type=${currentBillType}`;

                if (mode === 'print') {
                    // Prints bill AND automatically triggers PDF download as requested
                    window.open(`${printBaseUrl}?invoice_no=${data.invoice_no}&${typeParam}&print=1&download=1`, '_blank');
                } else if (mode === 'download') {
                    // Automatically downloads PDF bill
                    window.open(`${printBaseUrl}?invoice_no=${data.invoice_no}&${typeParam}&download=1`, '_blank');
                } else {
                    alert('Bill Generated Successfully! Invoice: ' + data.invoice_no);
                }

                resetPOSCart();
                setTimeout(() => {
                    location.reload();
                }, 800);
            } else {
                alert('Error creating bill: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            if (saveBtn) saveBtn.disabled = false;
            if (savePrintBtn) savePrintBtn.disabled = false;
            if (saveDownloadBtn) saveDownloadBtn.disabled = false;
            console.error('POS Save Error:', err);
            alert('Server error occurred while saving bill.');
        });
    };

    // Live Product Search in POS
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const productCards = document.querySelectorAll('.pos-product-item');

            productCards.forEach(card => {
                const name = (card.getAttribute('data-name') || '').toLowerCase();
                const code = (card.getAttribute('data-code') || '').toLowerCase();

                if (name.includes(query) || code.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

});
