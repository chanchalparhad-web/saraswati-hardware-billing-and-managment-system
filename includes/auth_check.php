<?php
require_once __DIR__ . '/../config/database.php';

// Enforce login for all protected pages
require_auth();
?>
