<?php
// ============================================================
// VERCEL SERVERLESS ENTRY & ROUTER FOR HARDWARE STORE SYSTEM
// ============================================================

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';

$file = ltrim($path, '/');

// Root request -> index.php
if (empty($file) || $file === 'index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

$targetFile = __DIR__ . '/../' . $file;

// 1. Direct match for PHP or static asset
if (file_exists($targetFile) && !is_dir($targetFile)) {
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    if ($ext === 'php') {
        require $targetFile;
        exit;
    } else {
        $mimeTypes = [
            'css' => 'text/css',
            'js'  => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg'=> 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'json'=> 'application/json'
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
            readfile($targetFile);
            exit;
        }
    }
}

// 2. URL without extension (e.g. /auth/login -> /auth/login.php)
if (file_exists($targetFile . '.php')) {
    require $targetFile . '.php';
    exit;
}

// 3. Directory index (e.g. /admin -> /admin/index.php)
if (is_dir($targetFile) && file_exists(rtrim($targetFile, '/') . '/index.php')) {
    require rtrim($targetFile, '/') . '/index.php';
    exit;
}

// 4. Default fallback to main index.php
if (file_exists(__DIR__ . '/../index.php')) {
    require __DIR__ . '/../index.php';
    exit;
}

http_response_code(404);
echo "404 Not Found";
