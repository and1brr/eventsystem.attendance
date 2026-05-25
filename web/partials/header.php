<?php
/** @var string $title */
require_once __DIR__ . '/../auth.php';
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? APP_NAME) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 for Toasts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    
    <!-- QR Code Scanner -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    
    <!-- Chart.js for Dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/comprog/web/includes/ui.css">
    <link rel="stylesheet" href="/comprog/web/includes/toasts.css">
    
    <style>
        :root {
            --primary: #22c55e;
            --primary-600: #16a34a;
            --danger: #ef4444;
            --warning: #f97316;
            --info: #3b82f6;
            --bg: #ffffff;
            --text: #0f172a;
            --border: #e5e7eb;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: var(--bg);
            color: var(--text);
        }
    </style>
    
    <script src="/comprog/web/assets/app.js" defer></script>
</head>
<body>
<div class="d-flex min-vh-100">
    <?php if ($user): ?>
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    
    <div class="flex-grow-1 d-flex flex-column" style="flex: 1;">
        <!-- Top Navbar -->
        <header class="bg-white border-bottom py-3 shadow-sm sticky-top">
            <div class="container-fluid px-4">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <button id="mobileMenuBtn" class="btn btn-link d-md-none p-0"><i class="fa fa-bars"></i></button>
                    </div>
                    <div class="col">
                        <h2 id="pageTitle" class="h5 mb-0"><?= htmlspecialchars($title ?? APP_NAME) ?></h2>
                        <small class="text-muted">Welcome, <?= htmlspecialchars($user['full_name'] ?? '') ?></small>
                    </div>
                    <div class="col-auto">
                        <div class="d-flex gap-3 align-items-center">
                            <div id="clockDisplay" class="text-muted" style="font-size: 14px;"></div>
                            <button id="notificationsBtn" class="btn btn-link p-0" title="Notifications"><i class="fa fa-bell"></i></button>
                            <button id="darkModeToggle" class="btn btn-link p-0" title="Toggle dark mode"><i class="fa fa-moon"></i></button>
                            <div class="dropdown">
                                <button class="btn btn-link dropdown-toggle p-0" type="button" id="profileDropdown" data-bs-toggle="dropdown"><i class="fa fa-user-circle"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                                    <li><a class="dropdown-item" href="/comprog/web/users.php"><i class="fa fa-cog me-2"></i>Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="/comprog/web/logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <main id="main-content" class="flex-grow-1 py-4" style="flex: 1;">
            <div class="container-fluid px-4">
                    <div id="toast-container" class="fixed top-6 right-6 z-50 flex flex-col gap-3"></div>
                    <div id="global-spinner" class="hidden fixed inset-0 z-40 grid place-items-center bg-black/20">
                        <div class="w-20 h-20 rounded-full border-4 border-t-slate-900 animate-spin"></div>
                    </div>

                    <?php if ($message = flash('success')): ?>
                        <div class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 px-4 py-3 border border-emerald-200"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($message = flash('error')): ?>
                        <div class="mb-4 rounded-xl bg-rose-50 text-rose-800 px-4 py-3 border border-rose-200"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
