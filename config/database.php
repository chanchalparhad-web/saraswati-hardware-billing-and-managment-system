<?php
// ============================================================
// HARDWARE STORE MANAGEMENT SYSTEM - DATABASE & CONFIGURATION
// ============================================================

// Configure session save path for serverless environment
if (function_exists('sys_get_temp_dir')) {
    $tempDir = sys_get_temp_dir();
    if ($tempDir && is_writable($tempDir)) {
        @ini_set('session.save_path', $tempDir);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (($_SERVER['SERVER_PORT'] ?? '') == 443) || 
               (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    @session_start();
}

// Restore session from HMAC-signed auth cookie if serverless container has recycled
restore_session_from_cookie();

// Database Credentials & Connection string parsing (DATABASE_URL / MYSQL_URL for Vercel/Cloud deployment)
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
$parsedHost = null; $parsedPort = null; $parsedName = null; $parsedUser = null; $parsedPass = null;
if ($dbUrl) {
    $dbParts = parse_url($dbUrl);
    if ($dbParts) {
        $parsedHost = $dbParts['host'] ?? null;
        $parsedPort = isset($dbParts['port']) ? (string)$dbParts['port'] : null;
        $parsedUser = $dbParts['user'] ?? null;
        $parsedPass = $dbParts['pass'] ?? null;
        $parsedName = isset($dbParts['path']) ? ltrim($dbParts['path'], '/') : null;
    }
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: ($parsedHost ?: 'localhost'));
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: ($parsedPort ?: '3306'));
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: ($parsedName ?: 'hardware_store_db'));
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: ($parsedUser ?: 'root'));
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($parsedPass !== null ? $parsedPass : ''));

// Base URL helper
function base_url($path = '') {
    if (getenv('APP_URL')) {
        $base = rtrim(getenv('APP_URL'), '/');
        return $base . '/' . ltrim($path, '/');
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (($_SERVER['SERVER_PORT'] ?? '') == 443) || 
               (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $protocol = $isHttps ? "https://" : "http://";
    
    $rawHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $host = trim(explode(',', $rawHost)[0]);
    
    // Calculate relative subfolder path if under XAMPP htdocs or Vercel api/
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $baseDir = preg_replace('#/(admin|billing|customer|auth|includes|config|api).*$#', '', $scriptDir);
    if ($baseDir === '/' || $baseDir === '/api') {
        $baseDir = '';
    }
    
    return rtrim($protocol . $host . $baseDir, '/') . '/' . ltrim($path, '/');
}

/**
 * Returns PDO Database Instance.
 * Connects to MySQL if configured/available; falls back gracefully to SQLite on Vercel/local.
 */
function get_db() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $isVercel = !empty(getenv('VERCEL')) || !empty(getenv('NOW_REGION')) || !empty(getenv('VERCEL_ENV'));
    $isRemoteMysql = (DB_HOST !== 'localhost' && DB_HOST !== '127.0.0.1' && !empty(DB_HOST));

    // If running on Vercel and no external MySQL is configured, use SQLite immediately without socket timeout
    if ($isVercel && !$isRemoteMysql) {
        $pdo = get_sqlite_db();
        return $pdo;
    }

    try {
        // Attempt MySQL connection
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 4,
        ];
        if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Auto-initialize MySQL schema if tables don't exist yet
        try {
            $pdo->query("SELECT 1 FROM users LIMIT 1");
        } catch (Exception $e) {
            init_mysql_db($pdo);
        }

    } catch (PDOException $e) {
        // Fallback to SQLite if MySQL Server is not accessible
        $pdo = get_sqlite_db();
    }

    return $pdo;
}

/**
 * Returns SQLite PDO instance with /tmp write support on Vercel.
 */
function get_sqlite_db() {
    $sqliteFile = __DIR__ . '/../hardware_store.sqlite';
    $writableSqlite = $sqliteFile;

    // Vercel serverless environment has read-only root directory; copy to /tmp if writable
    if (function_exists('sys_get_temp_dir')) {
        $tmpDir = sys_get_temp_dir();
        if ($tmpDir && is_writable($tmpDir)) {
            $tmpSqlite = rtrim($tmpDir, '/\\') . '/hardware_store.sqlite';
            if (!file_exists($tmpSqlite)) {
                if (file_exists($sqliteFile) && filesize($sqliteFile) > 0) {
                    @copy($sqliteFile, $tmpSqlite);
                }
            }
            $writableSqlite = $tmpSqlite;
        }
    }

    $pdo = new PDO("sqlite:" . $writableSqlite);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Verify if tables exist
    $needsInit = false;
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
    } catch (Exception $e) {
        $needsInit = true;
    }

    if ($needsInit) {
        init_sqlite_db($pdo);
    }

    return $pdo;
}

/**
 * Helper to initialize MySQL schema from database.sql if needed
 */
function init_mysql_db($pdo) {
    $sqlFile = __DIR__ . '/../database.sql';
    if (!file_exists($sqlFile)) {
        return;
    }
    $sqlContent = file_get_contents($sqlFile);
    $lines = explode("\n", $sqlContent);
    $cleanedSql = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) continue;
        if (stripos($trimmed, 'CREATE DATABASE') === 0) continue;
        if (stripos($trimmed, 'USE ') === 0) continue;
        $cleanedSql .= $line . "\n";
    }

    $statements = array_filter(array_map('trim', explode(';', $cleanedSql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
            } catch (Exception $e) {
                // Ignore individual query errors
            }
        }
    }
}

/**
 * Helper to initialize SQLite schema and seed data when SQLite fallback is active
 */
function init_sqlite_db($pdo) {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT UNIQUE NOT NULL,
      email TEXT UNIQUE NOT NULL,
      password TEXT NOT NULL,
      role TEXT DEFAULT 'staff',
      name TEXT NOT NULL,
      phone TEXT,
      status INTEGER DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT UNIQUE NOT NULL,
      description TEXT,
      status INTEGER DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS products (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      product_code TEXT UNIQUE NOT NULL,
      barcode TEXT,
      name TEXT NOT NULL,
      category_id INTEGER NOT NULL,
      brand TEXT,
      description TEXT,
      unit TEXT DEFAULT 'Piece',
      purchase_price REAL DEFAULT 0.00,
      selling_price REAL DEFAULT 0.00,
      mrp REAL DEFAULT 0.00,
      gst_rate REAL DEFAULT 18.00,
      opening_stock INTEGER DEFAULT 0,
      current_stock INTEGER DEFAULT 0,
      min_stock INTEGER DEFAULT 5,
      location_rack TEXT,
      image_url TEXT,
      status INTEGER DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS suppliers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      supplier_name TEXT NOT NULL,
      company_name TEXT NOT NULL,
      mobile TEXT NOT NULL,
      email TEXT,
      address TEXT,
      gst_number TEXT,
      opening_balance REAL DEFAULT 0.00,
      current_balance REAL DEFAULT 0.00,
      status INTEGER DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS customers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      mobile TEXT NOT NULL,
      email TEXT,
      address TEXT,
      gst_number TEXT,
      credit_limit REAL DEFAULT 15000.00,
      outstanding_amount REAL DEFAULT 0.00,
      status INTEGER DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS purchases (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      purchase_no TEXT UNIQUE NOT NULL,
      supplier_id INTEGER NOT NULL,
      purchase_date TEXT NOT NULL,
      invoice_no TEXT,
      total_amount REAL DEFAULT 0.00,
      tax_amount REAL DEFAULT 0.00,
      discount_amount REAL DEFAULT 0.00,
      grand_total REAL DEFAULT 0.00,
      paid_amount REAL DEFAULT 0.00,
      due_amount REAL DEFAULT 0.00,
      payment_status TEXT DEFAULT 'due',
      status INTEGER DEFAULT 1,
      notes TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS purchase_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      purchase_id INTEGER NOT NULL,
      product_id INTEGER NOT NULL,
      qty INTEGER NOT NULL,
      purchase_rate REAL NOT NULL,
      tax_percent REAL DEFAULT 18.00,
      tax_amount REAL NOT NULL,
      total_amount REAL NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sales (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      invoice_no TEXT UNIQUE NOT NULL,
      customer_id INTEGER,
      sale_type TEXT DEFAULT 'offline',
      sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
      total_amount REAL DEFAULT 0.00,
      discount_amount REAL DEFAULT 0.00,
      tax_amount REAL DEFAULT 0.00,
      grand_total REAL DEFAULT 0.00,
      paid_amount REAL DEFAULT 0.00,
      due_amount REAL DEFAULT 0.00,
      payment_mode TEXT DEFAULT 'cash',
      payment_status TEXT DEFAULT 'paid',
      status TEXT DEFAULT 'completed',
      notes TEXT,
      user_id INTEGER,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS sale_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      sale_id INTEGER NOT NULL,
      product_id INTEGER NOT NULL,
      product_name TEXT NOT NULL,
      qty INTEGER NOT NULL,
      rate REAL NOT NULL,
      tax_percent REAL DEFAULT 18.00,
      tax_amount REAL DEFAULT 0.00,
      total_amount REAL NOT NULL
    );

    CREATE TABLE IF NOT EXISTS inventory_transactions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      product_id INTEGER NOT NULL,
      transaction_type TEXT NOT NULL,
      reference_id TEXT,
      qty_change INTEGER NOT NULL,
      stock_after INTEGER NOT NULL,
      notes TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS payments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      payment_no TEXT UNIQUE NOT NULL,
      type TEXT NOT NULL,
      entity_id INTEGER NOT NULL,
      amount REAL NOT NULL,
      payment_mode TEXT DEFAULT 'cash',
      reference_no TEXT,
      payment_date TEXT NOT NULL,
      notes TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS expenses (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      category TEXT NOT NULL,
      description TEXT NOT NULL,
      amount REAL NOT NULL,
      expense_date TEXT NOT NULL,
      payment_method TEXT DEFAULT 'cash',
      notes TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS online_orders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_no TEXT UNIQUE NOT NULL,
      customer_id INTEGER NOT NULL,
      order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
      total_amount REAL NOT NULL,
      shipping_address TEXT NOT NULL,
      payment_status TEXT DEFAULT 'pending',
      order_status TEXT DEFAULT 'Pending',
      notes TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS order_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_id INTEGER NOT NULL,
      product_id INTEGER NOT NULL,
      qty INTEGER NOT NULL,
      price REAL NOT NULL,
      total_amount REAL NOT NULL
    );

    CREATE TABLE IF NOT EXISTS settings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      setting_key TEXT UNIQUE NOT NULL,
      setting_value TEXT
    );
    ");

    // Seed default admin, staff, customer
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    $staffHash = password_hash('cashier123', PASSWORD_DEFAULT);
    $custHash = password_hash('customer123', PASSWORD_DEFAULT);

    $pdo->exec("
    INSERT OR IGNORE INTO users (id, username, email, password, role, name, phone) VALUES
    (1, 'admin', 'admin@hardwarestore.com', '$passHash', 'admin', 'Store Owner (Admin)', '9876543210'),
    (2, 'cashier', 'cashier@hardwarestore.com', '$staffHash', 'staff', 'Rajesh Cashier', '9876543211'),
    (3, 'customer', 'customer@gmail.com', '$custHash', 'customer', 'Amit Verma', '9876543212');

    INSERT OR IGNORE INTO categories (id, name, description) VALUES
    (1, 'Plumbing', 'Pipes, taps, valves, fittings, and accessories'),
    (2, 'Electrical', 'Wires, switches, MCBs, conduit pipes, lightings'),
    (3, 'Tools', 'Hand tools, power tools, hammers, screwdrivers, drills'),
    (4, 'Paint', 'Emulsion paints, primers, brushes, rollers, thinners'),
    (5, 'Fasteners', 'Screws, bolts, nuts, anchors, nails, washers'),
    (6, 'Pipes & Fittings', 'PVC, CPVC, GI pipes, elbows, tees, unions'),
    (7, 'Safety Equipment', 'Helmets, gloves, safety shoes, goggles, masks'),
    (8, 'Construction Materials', 'Cement, waterproofing, adhesive, putty, sealants'),
    (9, 'Hardware Accessories', 'Hinges, door locks, handles, drawer slides, latches');

    INSERT OR IGNORE INTO suppliers (id, supplier_name, company_name, mobile, email, address, gst_number, opening_balance, current_balance) VALUES
    (1, 'Suresh Patel', 'Supreme Plumbing Supplies Pvt Ltd', '9820011223', 'sales@supreme.com', 'Plot 45, GIDC Industrial Estate, Ahmedabad', '24AAACS1234F1Z1', 0.00, 72400.00),
    (2, 'Vikas Gupta', 'Asian Paints Authorized Distr', '9811122334', 'orders@apdistributors.com', 'Building B, Industrial Area, Phase 2, Mumbai', '27AABCA5678G2Z4', 0.00, 18500.00),
    (3, 'Ramesh Kumar', 'Bosch & Stanley Power Tools Depot', '9833344556', 'ramesh@powertools.in', '78 Hardware Market, Delhi', '07AAACB9101H3Z9', 0.00, 0.00);

    INSERT OR IGNORE INTO customers (id, name, mobile, email, address, gst_number, credit_limit, outstanding_amount) VALUES
    (1, 'Walk-in Customer', '0000000000', 'walkin@store.com', 'Local Store Direct', NULL, 0.00, 0.00),
    (2, 'Rajesh Construction Co', '9844455667', 'rajesh@rajeshconst.com', '12 Civil Lines, Main City', '27AAECR4433P1ZB', 50000.00, 35600.00),
    (3, 'Sharma Electricians', '9855566778', 'sharma.elec@gmail.com', 'Shop 4, Electrical Market', NULL, 20000.00, 4500.00),
    (4, 'Amit Verma', '9876543212', 'customer@gmail.com', 'Flat 402, Sunshine Apartments', NULL, 10000.00, 0.00);

    INSERT OR IGNORE INTO products (id, product_code, barcode, name, category_id, brand, description, unit, purchase_price, selling_price, mrp, gst_rate, opening_stock, current_stock, min_stock, location_rack) VALUES
    (1, 'PRD-PLM-001', '890123456781', 'PVC Pipe 110mm 3 Meter (10ft)', 1, 'Supreme', 'Heavy duty 4kg/cm2 PVC drainage pipe', 'Piece', 180.00, 220.00, 250.00, 18.00, 100, 50, 15, 'Rack A-1'),
    (2, 'PRD-PLM-002', '890123456782', 'Brass Bib Tap 1/2 Inch', 1, 'Jaquar', 'Solid brass chrome finish bib tap', 'Piece', 260.00, 350.00, 420.00, 18.00, 40, 22, 5, 'Rack A-3'),
    (3, 'PRD-PLM-003', '890123456783', 'PVC Elbow 110mm 90 Degree', 1, 'Supreme', 'Drainage PVC elbow fitting', 'Piece', 28.00, 40.00, 50.00, 18.00, 200, 140, 30, 'Rack A-2'),
    (4, 'PRD-ELE-001', '890123456784', 'Havells 2.5 sqmm FR Wire 90m (Red)', 2, 'Havells', 'Flame retardant copper electrical wire coil', 'Roll', 1850.00, 2350.00, 2700.00, 18.00, 25, 12, 5, 'Rack B-1'),
    (5, 'PRD-ELE-002', '890123456785', 'Anchor Modular Switch 16A White', 2, 'Anchor', '16 Ampere 1-way modular rocker switch', 'Piece', 32.00, 48.00, 60.00, 18.00, 150, 85, 20, 'Rack B-3'),
    (6, 'PRD-ELE-003', '890123456786', 'MCB Single Pole 16A C-Curve', 2, 'Schneider', 'Miniature circuit breaker 10kA', 'Piece', 120.00, 175.00, 210.00, 18.00, 50, 8, 10, 'Rack B-2'),
    (7, 'PRD-TLS-001', '890123456787', 'Bosch Professional Angle Grinder 4\"', 3, 'Bosch', '670W 11000 RPM heavy duty angle grinder', 'Piece', 2100.00, 2750.00, 3200.00, 18.00, 15, 6, 2, 'Rack C-1'),
    (8, 'PRD-TLS-002', '890123456788', 'Stanley Claw Hammer 500g Steel Handle', 3, 'Stanley', 'Forged high carbon steel head with rubber grip', 'Piece', 320.00, 450.00, 550.00, 18.00, 30, 18, 5, 'Rack C-2'),
    (9, 'PRD-PNT-001', '890123456789', 'Asian Paints Royale Emulsion White 20L', 4, 'Asian Paints', 'Luxury interior emulsion paint smooth finish', 'Bucket', 4500.00, 5600.00, 6400.00, 18.00, 10, 4, 3, 'Rack D-1'),
    (10, 'PRD-PNT-002', '890123456790', 'Paint Roller 9 Inch Heavy Duty', 4, 'Royal', 'Microfiber lint free paint roller with handle', 'Piece', 75.00, 120.00, 150.00, 18.00, 60, 35, 10, 'Rack D-3'),
    (11, 'PRD-FST-001', '890123456791', 'Stainless Steel Hex Bolt M6 x 50mm (Pack of 50)', 5, 'Unbrako', '304 Grade stainless steel bolts with nuts', 'Packet', 110.00, 160.00, 200.00, 18.00, 80, 42, 15, 'Bin E-1'),
    (12, 'PRD-ADH-001', '890123456792', 'Fevicol SH Synthetic Resin Adhesive 1kg', 8, 'Pidilite', 'Woodworking water resistant adhesive jar', 'Jar', 210.00, 280.00, 320.00, 18.00, 40, 16, 5, 'Rack F-1'),
    (13, 'PRD-SFT-001', '890123456793', 'Industrial Safety Helmet Yellow HDPE', 7, 'Karam', 'Ratchet type adjustable head harness helmet', 'Piece', 140.00, 210.00, 260.00, 18.00, 30, 11, 5, 'Rack G-1');

    INSERT OR IGNORE INTO sales (id, invoice_no, customer_id, sale_type, sale_date, total_amount, discount_amount, tax_amount, grand_total, paid_amount, due_amount, payment_mode, payment_status, status, user_id) VALUES
    (1, 'INV-202610-001', 1, 'offline', CURRENT_TIMESTAMP, 2000.00, 100.00, 342.00, 2242.00, 2242.00, 0.00, 'cash', 'paid', 'completed', 2),
    (2, 'INV-202610-002', 2, 'offline', CURRENT_TIMESTAMP, 30000.00, 0.00, 5400.00, 35400.00, 0.00, 35400.00, 'credit', 'due', 'completed', 1),
    (3, 'INV-202610-003', 3, 'offline', CURRENT_TIMESTAMP, 4200.00, 200.00, 720.00, 4720.00, 4720.00, 0.00, 'upi', 'paid', 'completed', 2);

    INSERT OR IGNORE INTO sale_items (id, sale_id, product_id, product_name, qty, rate, tax_percent, tax_amount, total_amount) VALUES
    (1, 1, 1, 'PVC Pipe 110mm 3 Meter (10ft)', 5, 220.00, 18.00, 198.00, 1100.00),
    (2, 1, 2, 'Brass Bib Tap 1/2 Inch', 2, 350.00, 18.00, 126.00, 700.00),
    (3, 1, 3, 'PVC Elbow 110mm 90 Degree', 5, 40.00, 18.00, 36.00, 200.00);

    INSERT OR IGNORE INTO expenses (id, category, description, amount, expense_date, payment_method, notes) VALUES
    (1, 'Shop Rent', 'Monthly shop premises rent for October', 12000.00, DATE('now'), 'bank_transfer', 'Owner bank account transfer'),
    (2, 'Electricity', 'Electricity bill payment for shop lighting and computer', 2850.00, DATE('now'), 'upi', 'Torrent power online bill'),
    (3, 'Transportation', 'Tempo freight charges for PVC pipes delivery from GIDC', 1400.00, DATE('now'), 'cash', 'Local transport auto drivers payment');

    INSERT OR IGNORE INTO settings (id, setting_key, setting_value) VALUES
    (1, 'shop_name', 'Saraswati Hardware'),
    (2, 'shop_address', '123 Main Hardware Market, Station Road, City'),
    (3, 'shop_phone', '+91 98765 43210'),
    (4, 'shop_email', 'contact@abchardware.com'),
    (5, 'gstin', '27ABCDE1234F1Z5'),
    (6, 'currency', '₹'),
    (7, 'invoice_prefix', 'INV-'),
    (8, 'thermal_printer_width', '80mm'),
    (9, 'footer_note', 'Thank you for shopping with us! Visit again.');
    ");
}

// Utility Helper Functions

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function format_currency($amount) {
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

function get_setting($key, $default = '') {
    $db = get_db();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $res = $stmt->fetchColumn();
    return $res !== false ? $res : $default;
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'name' => $_SESSION['name'] ?? '',
        'role' => $_SESSION['role'] ?? 'staff',
        'email' => $_SESSION['email'] ?? ''
    ];
}

function require_auth() {
    if (!is_logged_in()) {
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }
}

function require_role($roles = []) {
    require_auth();
    if (is_string($roles)) {
        $roles = [$roles];
    }
    $user = current_user();
    if (!in_array($user['role'], $roles)) {
        header('Location: ' . base_url('auth/login.php?error=unauthorized'));
        exit;
    }
}

function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Serverless Authentication Cookie Helpers (HMAC-SHA256 Signed)
function get_app_secret() {
    return getenv('APP_SECRET') ?: 'hw-store-jwt-secure-salt-vercel-2026';
}

function set_auth_cookie($user) {
    $data = [
        'id'       => $user['id'] ?? null,
        'username' => $user['username'] ?? '',
        'name'     => $user['name'] ?? '',
        'role'     => $user['role'] ?? 'staff',
        'email'    => $user['email'] ?? '',
        'time'     => time()
    ];
    $json = json_encode($data);
    $sig = hash_hmac('sha256', $json, get_app_secret());
    $token = base64_encode($sig . '::' . $json);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (($_SERVER['SERVER_PORT'] ?? '') == 443) || 
               (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie('hw_auth_token', $token, [
        'expires'  => time() + (86400 * 7),
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clear_auth_cookie() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (($_SERVER['SERVER_PORT'] ?? '') == 443) || 
               (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie('hw_auth_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE['hw_auth_token']);
}

function restore_session_from_cookie() {
    if (!empty($_SESSION['user_id'])) {
        return;
    }
    if (empty($_COOKIE['hw_auth_token'])) {
        return;
    }
    $raw = base64_decode($_COOKIE['hw_auth_token'], true);
    if (!$raw || strpos($raw, '::') === false) {
        return;
    }
    list($sig, $json) = explode('::', $raw, 2);
    $expectedSig = hash_hmac('sha256', $json, get_app_secret());
    if (hash_equals($expectedSig, $sig)) {
        $userData = json_decode($json, true);
        if ($userData && !empty($userData['id'])) {
            $_SESSION['user_id']  = $userData['id'];
            $_SESSION['username'] = $userData['username'] ?? '';
            $_SESSION['name']     = $userData['name'] ?? '';
            $_SESSION['role']     = $userData['role'] ?? 'staff';
            $_SESSION['email']    = $userData['email'] ?? '';
        }
    }
}
