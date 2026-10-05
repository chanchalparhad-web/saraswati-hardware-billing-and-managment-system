<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Please login to place orders'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items']) || empty($data['address'])) {
    json_response(['success' => false, 'message' => 'Invalid order details provided'], 400);
}

$db = get_db();
$user = current_user();

try {
    $db->beginTransaction();

    // Get customer ID linked to user or default
    $stmtC = $db->prepare("SELECT id FROM customers WHERE email = ? OR mobile = ?");
    $stmtC->execute([$user['email'], $user['username']]);
    $cust = $stmtC->fetch();
    $customerId = $cust ? $cust['id'] : 4;

    // Generate Order No
    $orderNo = 'ORD-' . date('Ym') . '-' . rand(1000, 9999);
    
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        $subtotal += ($item['qty'] * $item['price']) * (1 + ($item['gst'] ?? 18)/100);
    }

    // Insert Online Order
    $stmtOrd = $db->prepare("
        INSERT INTO online_orders (order_no, customer_id, order_date, total_amount, shipping_address, payment_status, order_status)
        VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, 'pending', 'Pending')
    ");
    $stmtOrd->execute([$orderNo, $customerId, $subtotal, sanitize($data['address'])]);
    $orderId = $db->lastInsertId();

    // Insert Items
    $stmtItem = $db->prepare("
        INSERT INTO order_items (order_id, product_id, qty, price, total_amount)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($data['items'] as $item) {
        $tot = $item['qty'] * $item['price'];
        $stmtItem->execute([$orderId, $item['id'], $item['qty'], $item['price'], $tot]);
    }

    $db->commit();

    json_response(['success' => true, 'order_no' => $orderNo]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
