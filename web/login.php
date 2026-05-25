<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (current_user()) {
    redirect('/comprog/web/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = db()->prepare(
            'SELECT u.id, u.username, u.password_hash, u.full_name, r.name AS role, u.is_active
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        $storedHash = (string) ($user['password_hash'] ?? '');
        $sha256Hash = 'sha256:' . base64_encode(hash('sha256', $password, true));

        if ($user && (int) $user['is_active'] === 1 && (
            password_verify($password, $storedHash) || hash_equals($sha256Hash, $storedHash)
        )) {
            login_user([
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
            ]);
            redirect('/comprog/web/dashboard.php');
        }

        $error = 'Invalid username or password.';
    } else {
        $error = 'Please enter both username and password.';
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- SweetAlert2 for Toast Notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    
    <!-- Toast Styling -->
    <link rel="stylesheet" href="includes/toasts.css">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #ffffff;
        }

        .login-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }

        .logo-container {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .logo-container svg {
            width: 36px;
            height: 36px;
            color: white;
        }

        .form-input {
            background-color: #ffffff;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: #10b981;
        }

        .login-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-btn:hover:not(:disabled) {
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }

        .login-btn:disabled {
            opacity: 0.9;
            cursor: not-allowed;
        }

        .error-message {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-label {
            color: #1f2937;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 8px;
            display: block;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-header h1 {
            color: #1f2937;
            font-size: 28px;
            font-weight: 700;
            margin: 12px 0 8px 0;
            letter-spacing: -0.5px;
        }

        .login-header p {
            color: #6b7280;
            font-size: 14px;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .demo-info {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            padding: 12px 14px;
            margin-top: 20px;
            font-size: 13px;
            color: #065f46;
        }

        .demo-info strong {
            color: #047857;
        }

        code {
            background-color: #d1fae5;
            color: #065f46;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md">
            <!-- Login Card -->
            <div class="login-card rounded-16 p-8">
                <!-- Logo and Header -->
                <div class="login-header">
                    <div class="logo-container">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                        </svg>
                    </div>
                    <h1><?= htmlspecialchars(APP_NAME) ?></h1>
                    <p>Attendance Management System</p>
                </div>

                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="error-message mb-6">
                        <strong>Sign In Failed</strong>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" id="loginForm" class="space-y-5">
                    <!-- Username Field -->
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input w-full"
                            placeholder="Enter your username"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="relative">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input w-full pr-14"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button
                                type="button"
                                id="togglePassword"
                                class="password-toggle"
                            >Show</button>
                        </div>
                    </div>

                    <!-- Login Button -->
                    <button type="submit" id="loginBtn" class="login-btn mt-6">
                        <span id="btnText">Sign In</span>
                        <span id="btnSpinner" class="spinner hidden"></span>
                    </button>
                </form>

                <!-- Demo Info -->
                <div class="demo-info">
                    <strong>Demo Credentials:</strong><br>
                    Admin: <code>admin</code> / <code>admin123</code><br>
                    Staff: <code>staff</code> / <code>staff123</code>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-center text-sm text-gray-500 mt-8">
                &copy; 2026 <?= htmlspecialchars(APP_NAME) ?>. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = 'Hide';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = 'Show';
            }
        });

        // Form submission
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');

        loginForm.addEventListener('submit', function(e) {
            loginBtn.disabled = true;
            btnText.textContent = 'Signing in...';
            btnSpinner.classList.remove('hidden');
        });
    </script>
    
    <!-- Toast Helper Functions -->
    <script src="includes/toasts.js"></script>
</body>
</html>
