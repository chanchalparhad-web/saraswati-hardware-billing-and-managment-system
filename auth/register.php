<?php
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields!';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } else {
        $db = get_db();
        // Check duplicate
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Username or Email is already registered!';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role, name, phone) VALUES (?, ?, ?, 'customer', ?, ?)");
            if ($stmt->execute([$username, $email, $hashedPassword, $name, $phone])) {
                // Also create customer entry
                $custStmt = $db->prepare("INSERT INTO customers (name, mobile, email, credit_limit, outstanding_amount) VALUES (?, ?, ?, 10000.00, 0.00)");
                $custStmt->execute([$name, $phone, $email]);

                $success = 'Registration successful! You can now login.';
            } else {
                $error = 'Failed to register account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Register - Hardware Store</title>
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
            padding: 20px 0;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
    </style>
</head>
<body>

<div class="container p-3">
    <div class="register-card mx-auto">
        <div class="bg-dark text-white p-4 text-center">
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-user-plus text-primary me-2"></i> Customer Registration</h4>
            <p class="small text-white-50 mb-0">Create account for online ordering & bill history</p>
        </div>

        <div class="p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small fw-bold" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo sanitize($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success py-2 small fw-bold" role="alert">
                    <i class="fa-solid fa-circle-check me-1"></i> <?php echo sanitize($success); ?>
                    <a href="login.php" class="alert-link ms-2">Click to Login</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Full Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Ramesh Kumar" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small text-secondary">Username *</label>
                        <input type="text" name="username" class="form-control" placeholder="ramesh123" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small text-secondary">Mobile Number *</label>
                        <input type="tel" name="phone" class="form-control" placeholder="9876543210" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="ramesh@gmail.com" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small text-secondary">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold small text-secondary">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3">
                    <i class="fa-solid fa-check-circle me-2"></i> CREATE ACCOUNT
                </button>
            </form>

            <div class="text-center mt-3">
                <small class="text-muted">Already registered? <a href="login.php" class="text-primary fw-bold text-decoration-none">Back to Login</a></small>
            </div>
        </div>
    </div>
</div>

</body>
</html>
