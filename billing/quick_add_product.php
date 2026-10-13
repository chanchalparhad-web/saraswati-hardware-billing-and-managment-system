<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized access'], 401);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$name = trim($data['name'] ?? '');
$sellingPrice = floatval($data['selling_price'] ?? 0);

if (empty($name)) {
    json_response(['success' => false, 'message' => 'Product Name is required.'], 400);
}

if ($sellingPrice <= 0) {
    json_response(['success' => false, 'message' => 'Selling Price must be greater than zero.'], 400);
}

$db = get_db();

try {
    // Check if category exists or use first available category
    $categoryId = intval($data['category_id'] ?? 0);
    if ($categoryId <= 0) {
        $catStmt = $db->query("SELECT id FROM categories WHERE status = 1 ORDER BY id ASC LIMIT 1");
        $categoryId = (int)($catStmt->fetchColumn() ?: 1);
    }

    $unit = trim($data['unit'] ?? 'Piece');
    if (empty($unit)) $unit = 'Piece';

    $gstRate = floatval($data['gst_rate'] ?? 18.0);
    $brand = trim($data['brand'] ?? 'General');
    if (empty($brand)) $brand = 'General';

    $mrp = floatval($data['mrp'] ?? 0);
    if ($mrp <= 0) {
        $mrp = $sellingPrice;
    }

    $purchasePrice = floatval($data['purchase_price'] ?? 0);
    if ($purchasePrice <= 0) {
        $purchasePrice = round($sellingPrice * 0.8, 2);
    }

    $stock = intval($data['current_stock'] ?? 100);
    if ($stock < 0) $stock = 100;

    $minStock = intval($data['min_stock'] ?? 5);
    $rack = trim($data['location_rack'] ?? 'Front Counter');

    // Generate unique product code
    $prefix = 'PRD-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
    if (strlen($prefix) < 7) {
        $prefix = 'PRD-HW';
    }
    $productCode = $prefix . '-' . rand(1000, 9999);

    // Check if code exists, regenerate if needed
    $chkStmt = $db->prepare("SELECT id FROM products WHERE product_code = ?");
    $chkStmt->execute([$productCode]);
    if ($chkStmt->fetchColumn()) {
        $productCode = 'PRD-HW-' . time() . '-' . rand(10, 99);
    }

    $stmt = $db->prepare("
        INSERT INTO products 
        (product_code, barcode, name, category_id, brand, description, unit, purchase_price, selling_price, mrp, gst_rate, opening_stock, current_stock, min_stock, location_rack, status)
        VALUES (?, '', ?, ?, ?, 'Quick added from POS billing', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        $productCode,
        $name,
        $categoryId,
        $brand,
        $unit,
        $purchasePrice,
        $sellingPrice,
        $mrp,
        $gstRate,
        $stock,
        $stock,
        $minStock,
        $rack
    ]);

    $newId = (int)$db->lastInsertId();

    // Fetch the inserted product
    $getStmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $getStmt->execute([$newId]);
    $newProduct = $getStmt->fetch();

    json_response([
        'success' => true,
        'message' => 'Product added to catalog and ready for billing!',
        'product' => $newProduct
    ]);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
