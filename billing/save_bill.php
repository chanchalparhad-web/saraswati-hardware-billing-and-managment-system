<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized access'], 401);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['items'])) {
    json_response(['success' => false, 'message' => 'No products found in bill payload'], 400);
}

$db = get_db();
$user = current_user();

try {
    $db->beginTransaction();

    $customerName = sanitize($data['customer_name'] ?? 'Customer');
    $customerMobile = sanitize($data['customer_mobile'] ?? '');
    $paymentMode = sanitize($data['payment_mode'] ?? 'cash');
    $discountVal = floatval($data['discount'] ?? 0);
    $discountType = sanitize($data['discount_type'] ?? 'fixed');
    $paidAmountInput = floatval($data['paid_amount'] ?? 0);
    $items = $data['items'];

    // Auto find or insert Customer
    $customerId = 1;
    if (!empty($customerMobile)) {
        $stmtFind = $db->prepare("SELECT id FROM customers WHERE mobile = ? LIMIT 1");
        $stmtFind->execute([$customerMobile]);
        $foundId = $stmtFind->fetchColumn();
        if ($foundId) {
            $customerId = $foundId;
            // Update customer name if provided
            $stmtUpd = $db->prepare("UPDATE customers SET name = ? WHERE id = ?");
            $stmtUpd->execute([$customerName, $customerId]);
        } else {
            $stmtIns = $db->prepare("INSERT INTO customers (name, mobile, credit_limit, outstanding_amount) VALUES (?, ?, 15000.00, 0.00)");
            $stmtIns->execute([$customerName, $customerMobile]);
            $customerId = $db->lastInsertId();
        }
    } else {
        // Search by name or create
        $stmtFindName = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
        $stmtFindName->execute([$customerName]);
        $foundId = $stmtFindName->fetchColumn();
        if ($foundId) {
            $customerId = $foundId;
        } else {
            $stmtIns = $db->prepare("INSERT INTO customers (name, mobile, credit_limit, outstanding_amount) VALUES (?, '0000000000', 15000.00, 0.00)");
            $stmtIns->execute([$customerName]);
            $customerId = $db->lastInsertId();
        }
    }

    // Generate Invoice Number
    $invoicePrefix = get_setting('invoice_prefix', 'INV-');
    $datePart = date('Ym');
    
    // Find next serial
    $stmtSeq = $db->query("SELECT COUNT(*) FROM sales");
    $countSales = $stmtSeq->fetchColumn() + 1;
    $invoiceNo = sprintf("%s%s-%04d", $invoicePrefix, $datePart, $countSales);

    // Calculate Bill Totals
    $rawSubtotal = 0;
    $totalTax = 0;

    foreach ($items as $item) {
        $qty = intval($item['qty']);
        $price = floatval($item['price']);
        $gstRate = floatval($item['gst_rate'] ?? 18);

        $lineSubtotal = $qty * $price;
        $lineTax = ($lineSubtotal * $gstRate) / 100;

        $rawSubtotal += $lineSubtotal;
        $totalTax += $lineTax;
    }

    $discountAmount = 0;
    if ($discountType === 'percent') {
        $discountAmount = ($rawSubtotal * $discountVal) / 100;
    } else {
        $discountAmount = $discountVal;
    }

    if ($discountAmount > $rawSubtotal) {
        $discountAmount = $rawSubtotal;
    }

    $grandTotal = max(0, ($rawSubtotal - $discountAmount) + $totalTax);
    
    if ($paymentMode === 'credit') {
        $paidAmount = min($paidAmountInput, $grandTotal);
    } else {
        $paidAmount = $paidAmountInput > 0 ? $paidAmountInput : $grandTotal;
    }

    $dueAmount = max(0, $grandTotal - $paidAmount);
    $paymentStatus = ($dueAmount <= 0) ? 'paid' : (($paidAmount > 0) ? 'partial' : 'due');

    // Insert Sale Record
    $stmtSale = $db->prepare("
        INSERT INTO sales 
        (invoice_no, customer_id, sale_type, sale_date, total_amount, discount_amount, tax_amount, grand_total, paid_amount, due_amount, payment_mode, payment_status, status, user_id)
        VALUES (?, ?, 'offline', CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
    ");
    $stmtSale->execute([
        $invoiceNo,
        $customerId,
        $rawSubtotal,
        $discountAmount,
        $totalTax,
        $grandTotal,
        $paidAmount,
        $dueAmount,
        $paymentMode,
        $paymentStatus,
        $user['id']
    ]);

    $saleId = $db->lastInsertId();

    // Process Sale Items & Stock Updates
    $stmtItem = $db->prepare("
        INSERT INTO sale_items (sale_id, product_id, product_name, qty, rate, tax_percent, tax_amount, total_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtUpdateStock = $db->prepare("
        UPDATE products SET current_stock = current_stock - ? WHERE id = ?
    ");

    $stmtStockTrans = $db->prepare("
        INSERT INTO inventory_transactions (product_id, transaction_type, reference_id, qty_change, stock_after, notes)
        VALUES (?, 'sale', ?, ?, ?, 'POS Offline Sale')
    ");

    $stmtGetStock = $db->prepare("SELECT current_stock FROM products WHERE id = ?");

    foreach ($items as $item) {
        $productId = intval($item['id'] ?? 0);
        $productName = sanitize($item['name'] ?? 'Hardware Item');
        $qty = max(1, intval($item['qty'] ?? 1));
        $rate = floatval($item['price'] ?? 0);
        $gstRate = floatval($item['gst_rate'] ?? 18);

        // If product is newly added without catalog ID, persist it to products catalog table!
        if ($productId <= 0) {
            $stmtFindP = $db->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $stmtFindP->execute([$productName]);
            $foundPId = $stmtFindP->fetchColumn();
            if ($foundPId) {
                $productId = (int)$foundPId;
            } else {
                $unit = sanitize($item['unit'] ?? 'Piece');
                $pCode = 'PRD-HW-' . rand(1000, 9999);
                $purchasePrice = round($rate * 0.8, 2);
                $stmtInsP = $db->prepare("
                    INSERT INTO products 
                    (product_code, barcode, name, category_id, brand, description, unit, purchase_price, selling_price, mrp, gst_rate, opening_stock, current_stock, min_stock, location_rack, status)
                    VALUES (?, '', ?, 1, 'General', 'Added at POS billing', ?, ?, ?, ?, ?, 100, 100, 5, 'Counter', 1)
                ");
                $stmtInsP->execute([$pCode, $productName, $unit, $purchasePrice, $rate, $rate, $gstRate]);
                $productId = (int)$db->lastInsertId();
            }
        }

        $lineSubtotal = $qty * $rate;
        $lineTax = ($lineSubtotal * $gstRate) / 100;
        $lineTotal = $lineSubtotal + $lineTax;

        // Insert Item
        $stmtItem->execute([
            $saleId,
            $productId,
            $productName,
            $qty,
            $rate,
            $gstRate,
            $lineTax,
            $lineTotal
        ]);

        // Deduct Stock
        $stmtUpdateStock->execute([$qty, $productId]);

        // Get Stock After
        $stmtGetStock->execute([$productId]);
        $stockAfter = $stmtGetStock->fetchColumn();

        // Audit Trail
        $stmtStockTrans->execute([
            $productId,
            $invoiceNo,
            -$qty,
            $stockAfter
        ]);
    }

    // Update Customer Dues if Credit or Partial
    if ($dueAmount > 0 && $customerId > 1) {
        $stmtCustDue = $db->prepare("UPDATE customers SET outstanding_amount = outstanding_amount + ? WHERE id = ?");
        $stmtCustDue->execute([$dueAmount, $customerId]);
    }

    $db->commit();

    json_response([
        'success' => true,
        'invoice_no' => $invoiceNo,
        'grand_total' => $grandTotal,
        'message' => 'Sale recorded successfully!'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_response(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()], 500);
}
