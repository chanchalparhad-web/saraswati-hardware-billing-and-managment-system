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

if (!function_exists('get_app_secret')) {
    function get_app_secret() {
        return getenv('APP_SECRET') ?: 'hw-store-jwt-secure-salt-vercel-2026';
    }
}

if (!function_exists('set_auth_cookie')) {
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
}

if (!function_exists('clear_auth_cookie')) {
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
}

if (!function_exists('restore_session_from_cookie')) {
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
    (2, 'cashier', 'cashier@hardwarestore.com', '$staffHash', 'staff', 'Parmeshwar Cashier', '9876543211'),
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

    
   
    
    INSERT OR IGNORE INTO settings (id, setting_key, setting_value) VALUES
    (1, 'shop_name', 'Saraswati Hardware'),
    (2, 'shop_address', ' Karodi, Chhatrapati Sambhaji Nagar, Maharashtra 431136'),
    (3, 'shop_phone', '+91  77739 57946'),
    (4, 'shop_email', 'contact@abchardware.com'),
    (5, 'gstin', '27B0EPG0856F1Z5'),
    (6, 'currency', '₹'),
    (7, 'invoice_prefix', 'BILL NO:-'),
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
