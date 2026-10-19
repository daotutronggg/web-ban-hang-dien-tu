<?php
/**
 * Header Template
 * Shop Điện Tử - Giao diện đầu trang
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Thiết lập tiêu đề trang mặc định
$page_title = isset($page_title) && !empty($page_title) ? $page_title . ' - Shop Điện Tử' : 'Shop Điện Tử - Thế Giới Công Nghệ Chính Hãng';

// Đảm bảo BASE_URL đã được nạp
$baseUrl = defined('BASE_URL') ? BASE_URL : '/shop-dien-tu/';

// Xác định trang hiện tại để kích hoạt class active cho navbar
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$isHome = ($currentUri === $baseUrl || $currentUri === $baseUrl . 'index.php' || basename($_SERVER['PHP_SELF']) === 'index.php');
$isProducts = (strpos($currentUri, 'products.php') !== false || strpos($currentUri, 'product-detail.php') !== false);
$isContact = (strpos($currentUri, 'contact.php') !== false);
$cartCount = function_exists('getCartCount') ? getCartCount() : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='%230d6efd' d='M160 112c0-35.3 28.7-64 64-64h64c35.3 0 64 28.7 64 64v48H160v-48zm-48 48H48C21.5 160 0 181.5 0 208v240c0 35.3 28.7 64 64 64h384c35.3 0 64-28.7 64-64V208c0-26.5-21.5-48-48-48H400v-48C400 64.5 335.5 0 256 0h-64c-79.5 0-144 64.5-144 144v16z'/></svg>">

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.0 CDN CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">

    <!-- Font Awesome 6.4.0 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>assets/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

    <!-- Top Bar Mini (Thông tin hotline & thông báo) -->
    <div class="top-bar py-1 bg-dark text-white-50 small border-bottom border-secondary border-opacity-25">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <span class="me-3"><i class="fa-solid fa-phone me-1 text-primary"></i> Hotline: <strong>1900 6868</strong> (Miễn phí)</span>
                <span class="d-none d-md-inline"><i class="fa-solid fa-truck-fast me-1 text-success"></i> Giao hàng toàn quốc - Miễn phí đơn từ 500k</span>
            </div>
            <div>
                <a href="<?php echo $baseUrl; ?>contact.php" class="text-white-50 text-decoration-none me-3 hover-text-white">Hỗ trợ</a>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <a href="<?php echo $baseUrl; ?>admin/" class="badge bg-warning text-dark text-decoration-none"><i class="fa-solid fa-gauge me-1"></i> Trang Quản Trị</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm py-2">
        <div class="container">
            <!-- Brand Logo -->
            <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $baseUrl; ?>">
                <span class="brand-icon me-2 d-inline-flex align-items-center justify-content-center bg-white text-primary rounded-circle shadow-sm" style="width: 38px; height: 38px;">
                    <i class="fa-solid fa-bag-shopping fs-5"></i>
                </span>
                <span class="brand-text text-white tracking-wide">Shop Điện Tử</span>
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar Links & Actions -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <!-- Navigation Items -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 fw-medium">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isHome ? 'active fw-bold' : ''; ?>" href="<?php echo $baseUrl; ?>">
                            <i class="fa-solid fa-house me-1"></i> Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isProducts ? 'active fw-bold' : ''; ?>" href="<?php echo $baseUrl; ?>products.php">
                            <i class="fa-solid fa-box-open me-1"></i> Sản phẩm
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isContact ? 'active fw-bold' : ''; ?>" href="<?php echo $baseUrl; ?>contact.php">
                            <i class="fa-solid fa-envelope me-1"></i> Liên hệ
                        </a>
                    </li>
                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                        <li class="nav-item d-lg-none">
                            <a class="nav-link text-warning fw-bold" href="<?php echo $baseUrl; ?>admin/">
                                <i class="fa-solid fa-screwdriver-wrench me-1"></i> Quản trị hệ thống
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Search Box Quick Form -->
                <form class="d-flex me-lg-3 my-2 my-lg-0 search-form" action="<?php echo $baseUrl; ?>products.php" method="GET">
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm border-0 shadow-none ps-3" name="search" placeholder="Tìm kiếm điện thoại, laptop..." aria-label="Tìm kiếm sản phẩm" value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn btn-light btn-sm text-primary px-3" type="submit" title="Tìm kiếm">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>
                </form>

                <!-- User & Cart Actions -->
                <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
                    <!-- Cart Button with Counter Badge -->
                    <a href="<?php echo $baseUrl; ?>cart.php" class="btn btn-outline-light position-relative btn-sm px-3 rounded-pill d-flex align-items-center" title="Xem giỏ hàng">
                        <i class="fa-solid fa-cart-shopping me-1"></i>
                        <span class="d-none d-sm-inline me-1">Giỏ hàng</span>
                        <span id="cart-badge" class="badge rounded-pill bg-danger shadow-sm ms-1 <?php echo $cartCount === 0 ? 'd-none' : ''; ?>">
                            <?php echo $cartCount; ?>
                        </span>
                    </a>

                    <!-- User Account Dropdown / Auth Buttons -->
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm rounded-pill dropdown-toggle d-flex align-items-center px-3" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-circle-user text-primary me-2 fs-6"></i>
                                <span class="fw-semibold text-truncate" style="max-width: 130px;">
                                    <?php echo htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Tài khoản', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="userMenuDropdown">
                                <li class="dropdown-header small text-muted">
                                    Xin chào, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                                    <li>
                                        <a class="dropdown-item text-primary fw-bold" href="<?php echo $baseUrl; ?>admin/">
                                            <i class="fa-solid fa-chart-line me-2 text-primary"></i> Quản trị website
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $baseUrl; ?>orders.php">
                                        <i class="fa-solid fa-receipt me-2 text-muted"></i> Đơn hàng của tôi
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $baseUrl; ?>profile.php">
                                        <i class="fa-solid fa-id-card me-2 text-muted"></i> Thông tin tài khoản
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo $baseUrl; ?>logout.php">
                                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Đăng xuất
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?php echo $baseUrl; ?>login.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                                <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Đăng nhập
                            </a>
                            <a href="<?php echo $baseUrl; ?>register.php" class="btn btn-warning btn-sm rounded-pill px-3 text-dark fw-bold">
                                Đăng ký
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Global Flash Messages Container -->
    <div class="container mt-3">
        <?php 
        if (function_exists('displayFlashMessages')) {
            displayFlashMessages();
        }
        ?>
    </div>

    <!-- Main Content Area Starts -->
    <main class="flex-grow-1">
