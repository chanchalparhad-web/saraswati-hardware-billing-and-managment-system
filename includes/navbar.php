<?php
require_once __DIR__ . '/../config/database.php';
$user = current_user();
$shopName = get_setting('shop_name', 'Saraswati Hardware');
?>
<!-- Main Top Navbar -->
<header class="main-navbar no-print d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center">
        <h5 class="fw-bold mb-0 text-dark me-3">
            <?php echo sanitize($shopName); ?>
        </h5>
        <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill d-none d-md-inline-block">
            <i class="fa-solid fa-store me-1"></i> Local Offline POS Mode Active
        </span>
    </div>

    <div class="d-flex align-items-center gap-3">
        <?php if ($user): ?>
            <div class="dropdown">
                <button class="btn user-badge dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:32px; height:32px; font-weight:bold;">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div class="text-start d-none d-sm-block">
                        <div class="fw-bold lh-1"><?php echo sanitize($user['name']); ?></div>
                        <small class="text-muted text-uppercase" style="font-size:0.7rem;"><?php echo sanitize($user['role']); ?></small>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><a class="dropdown-item" href="#"><i class="fa-solid fa-user me-2 text-muted"></i> Profile</a></li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li><a class="dropdown-item" href="<?php echo base_url('admin/settings.php'); ?>"><i class="fa-solid fa-gear me-2 text-muted"></i> Settings</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo base_url('auth/logout.php'); ?>"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-outline-primary btn-sm px-3">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Login
            </a>
        <?php endif; ?>
    </div>
</header>
