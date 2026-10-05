<?php
require_once __DIR__ . '/../config/database.php';

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $error = 'You do not have permission to access that module.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            // Redirect based on role
            if ($user['role'] === 'admin' || $user['role'] === 'staff') {
                header('Location: ' . base_url('admin/dashboard.php'));
            } else {
                header('Location: ' . base_url('customer/products.php'));
            }
            exit;
        } else {
            $error = 'Invalid username/email or password!';
        }
    } else {
        $error = 'Please fill in all fields!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hardware Store Management</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #2563eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 440px;
        }
        .login-header {
            background: #1e293b;
            color: #ffffff;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
        }
        .demo-btn {
            font-size: 0.8rem;
            padding: 0.4rem 0.6rem;
        }
    </style>
</head>
<body>

<div class="container p-3">
    <div class="login-card mx-auto">
        <div class="login-header">
            <div class="mb-3">
                <i class="fa-solid fa-screwdriver-wrench fa-3x text-primary"></i>
            </div>
            <h4 class="fw-bold mb-1">Saraswati Hardware Login</h4>
            <p class="small text-white-50 mb-0">Management & Offline Billing Portal</p>
        </div>

        <div class="p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small fw-bold" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo sanitize($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary small">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="username" id="input-username" class="form-control" placeholder="admin / cashier / customer" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary small">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="input-password" class="form-control" placeholder="••••••••" required>
                        <button type="button" class="btn btn-outline-secondary text-muted" id="toggle-password-btn" onclick="togglePasswordVisibility()" title="Show/Hide Password">
                            <i class="fa-solid fa-eye" id="toggle-password-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-sm">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> LOGIN TO STORE
                </button>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">Customer without an account? <a href="register.php" class="text-primary fw-bold text-decoration-none">Register Here</a></small>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const pwdInput = document.getElementById('input-password');
    const pwdIcon = document.getElementById('toggle-password-icon');
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        pwdIcon.classList.remove('fa-eye');
        pwdIcon.classList.add('fa-eye-slash');
    } else {
        pwdInput.type = 'password';
        pwdIcon.classList.remove('fa-eye-slash');
        pwdIcon.classList.add('fa-eye');
    }
}
</script>

</body>
</html>
