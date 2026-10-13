<?php
require_once __DIR__ . '/../config/database.php';
$shopName = get_setting('shop_name', 'Saraswati Hardware');
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? sanitize($pageTitle) . ' - ' : ''; ?><?php echo sanitize($shopName); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS (Inline injection guarantees 100% styling on Vercel without CDN 404s) -->
    <?php 
    $cssFile = __DIR__ . '/../assets/css/style.css';
    if (file_exists($cssFile)): ?>
    <style>
    <?php echo file_get_contents($cssFile); ?>
    </style>
    <?php else: ?>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css'); ?>">
    <?php endif; ?>

    <!-- Base URL for Frontend AJAX / Fetch -->
    <script>
        window.APP_BASE_URL = "<?php echo rtrim(base_url(), '/'); ?>";
    </script>
</head>
<body>
<div id="wrapper">
