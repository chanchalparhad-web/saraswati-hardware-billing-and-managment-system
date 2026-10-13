<?php
// ============================================================
// VERCEL SERVERLESS ENTRY & ROUTER FOR HARDWARE STORE SYSTEM
// ============================================================

// Set working directory to project root
chdir(dirname(__DIR__));

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';

// Preserve and merge query parameters into $_GET and $_REQUEST
if (isset($parsedUrl['query']) && $parsedUrl['query'] !== '') {
    parse_str($parsedUrl['query'], $queryParams);
    $_GET = array_merge($queryParams, $_GET);
    $_REQUEST = array_merge($_REQUEST, $queryParams);
}

$file = ltrim($path, '/');

// Helper to execute target PHP file with correct server variables
function run_php($filePath) {
    $rootDir = str_replace('\\', '/', dirname(__DIR__));
    $normPath = str_replace('\\', '/', realpath($filePath) ?: $filePath);
    $relPath = '/' . ltrim(str_replace($rootDir, '', $normPath), '/');

    $_SERVER['SCRIPT_NAME'] = $relPath;
    $_SERVER['PHP_SELF'] = $relPath;
    $_SERVER['SCRIPT_FILENAME'] = $normPath;

    require $filePath;
    exit;
}

// 1. Root request -> index.php
if (empty($file) || $file === 'index.php') {
    run_php(__DIR__ . '/../index.php');
}

$targetFile = __DIR__ . '/../' . $file;

// 2. Direct match for PHP or static asset
if (file_exists($targetFile) && !is_dir($targetFile)) {
    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        run_php($targetFile);
    } else {
        $mimeTypes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'json'  => 'application/json',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf'
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
            header('Cache-Control: public, max-age=86400');
            readfile($targetFile);
            exit;
        }
    }
}

// 3. URL without extension (e.g. /auth/login -> /auth/login.php)
if (file_exists($targetFile . '.php')) {
    run_php($targetFile . '.php');
}

// 4. Directory index (e.g. /admin -> /admin/index.php)
if (is_dir($targetFile) && file_exists(rtrim($targetFile, '/') . '/index.php')) {
    run_php(rtrim($targetFile, '/') . '/index.php');
}

// 5. Friendly route aliases
$aliases = [
    'login'             => __DIR__ . '/../auth/login.php',
    'register'          => __DIR__ . '/../auth/register.php',
    'logout'            => __DIR__ . '/../auth/logout.php',
    'dashboard'         => __DIR__ . '/../admin/dashboard.php',
    'new_bill'          => __DIR__ . '/../billing/new_bill.php',
    'save_bill.php'     => __DIR__ . '/../billing/save_bill.php',
    'print_invoice.php' => __DIR__ . '/../billing/print_invoice.php',
    'checkout.php'      => __DIR__ . '/../customer/checkout.php',
];

if (isset($aliases[$file]) && file_exists($aliases[$file])) {
    run_php($aliases[$file]);
}

// 6. Default fallback to main index.php if not an asset request
if (!preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|map)$/i', $file)) {
    if (file_exists(__DIR__ . '/../index.php')) {
        run_php(__DIR__ . '/../index.php');
    }
}

// 7. 404 Not Found Page
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card-404 { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2.5rem; text-align: center; max-width: 450px; }
    </style>
</head>
<body>
    <div class="card-404 shadow-lg">
        <h1 class="display-3 fw-bold text-primary mb-2">404</h1>
        <h4 class="mb-3">Page Not Found</h4>
        <p class="text-white-50 small mb-4">The page or resource you requested (<code><?php echo htmlspecialchars($path); ?></code>) could not be located.</p>
        <a href="/" class="btn btn-primary px-4 py-2 fw-semibold rounded-pill">Return to Home</a>
    </div>
</body>
</html>
