<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Fallback BASE_URL if not defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/shop-dien-tu/');
}

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_URL . 'pages/login.php');
}

$page_title = 'Bảng điều khiển';
$current_page = 'dashboard';

// 1. Thống kê tổng quan
$total_products = 0;
$total_orders = 0;
$total_customers = 0;
$total_revenue = 0;
$pending_orders = 0;

// Tổng sản phẩm
$query_products = "SELECT COUNT(*) AS total FROM products";
if ($result = $conn->query($query_products)) {
    $row = $result->fetch_assoc();
    $total_products = $row['total'] ?? 0;
    $result->free();
}

// Tổng đơn hàng
$query_orders = "SELECT COUNT(*) AS total FROM orders";
if ($result = $conn->query($query_orders)) {
    $row = $result->fetch_assoc();
    $total_orders = $row['total'] ?? 0;
    $result->free();
}

// Tổng khách hàng (role = 'customer')
$query_customers = "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'";
if ($result = $conn->query($query_customers)) {
    $row = $result->fetch_assoc();
    $total_customers = $row['total'] ?? 0;
    $result->free();
}

// Tổng doanh thu (chỉ tính đơn hoàn thành 'completed')
$query_revenue = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE status = 'completed'";
if ($result = $conn->query($query_revenue)) {
    $row = $result->fetch_assoc();
    $total_revenue = $row['total'] ?? 0;
    $result->free();
}

// Đơn hàng đang chờ xử lý
$query_pending = "SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'";
if ($result = $conn->query($query_pending)) {
    $row = $result->fetch_assoc();
    $pending_orders = $row['total'] ?? 0;
    $result->free();
}

// 2. Danh sách 10 đơn hàng gần nhất
$recent_orders = [];
$query_recent = "SELECT o.*, u.fullname AS user_fullname, u.username 
                 FROM orders o 
                 LEFT JOIN users u ON o.user_id = u.id 
                 ORDER BY o.created_at DESC 
                 LIMIT 10";
if ($result = $conn->query($query_recent)) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
    $result->free();
}

// Hàm hỗ trợ hiển thị trạng thái đơn hàng
function getOrderStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Chờ xử lý</span>';
        case 'confirmed':
            return '<span class="badge bg-info text-dark"><i class="fa-solid fa-circle-check me-1"></i>Đã xác nhận</span>';
        case 'shipping':
            return '<span class="badge bg-primary"><i class="fa-solid fa-truck-fast me-1"></i>Đang giao hàng</span>';
        case 'completed':
            return '<span class="badge bg-success"><i class="fa-solid fa-check-double me-1"></i>Hoàn thành</span>';
        case 'cancelled':
            return '<span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>Đã hủy</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Quản trị Shop Điện Tử</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --topbar-height: 65px;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --sidebar-active: #0d6efd;
        }
        body {
            background-color: #f1f5f9;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: #e2e8f0;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            background-color: #0f172a;
            border-bottom: 1px solid #334155;
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
        }
        .sidebar-nav {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
            flex-grow: 1;
        }
        .sidebar-nav .nav-header {
            padding: 0.5rem 1.25rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            font-weight: 700;
        }
        .sidebar-nav .nav-item {
            margin: 0.15rem 0.75rem;
        }
        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 0.7rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 0.375rem;
            transition: all 0.2s;
            font-size: 0.925rem;
        }
        .sidebar-nav .nav-link i {
            width: 1.5rem;
            font-size: 1.1rem;
            margin-right: 0.75rem;
            text-align: center;
        }
        .sidebar-nav .nav-link:hover {
            background-color: var(--sidebar-hover);
            color: #fff;
        }
        .sidebar-nav .nav-link.active {
            background-color: var(--sidebar-active);
            color: #fff;
            font-weight: 600;
            box-shadow: 0 4px 6px -1px rgba(13, 110, 253, 0.4);
        }
        .admin-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .admin-topbar {
            height: var(--topbar-height);
            background-color: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        .admin-content {
            padding: 1.75rem;
            flex-grow: 1;
        }
        .stat-card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            background: #fff;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        }
        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .quick-action-btn {
            border-radius: 0.5rem;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
        <a href="index.php" class="sidebar-brand">
            <i class="fa-solid fa-bolt text-warning me-2"></i>
            <span>Shop Điện Tử</span>
        </a>
        <ul class="sidebar-nav">
            <li class="nav-header">Quản lý hệ thống</li>
            <li class="nav-item">
                <a href="index.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Tổng quan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="products.php" class="nav-link <?= $current_page === 'products' ? 'active' : '' ?>">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Sản phẩm</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link <?= $current_page === 'categories' ? 'active' : '' ?>">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Danh mục</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php" class="nav-link <?= $current_page === 'orders' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>Đơn hàng</span>
                    <?php if ($pending_orders > 0): ?>
                        <span class="badge bg-warning text-dark ms-auto"><?= $pending_orders ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Khách hàng</span>
                </a>
            </li>

            <li class="nav-header mt-3">Lối tắt & Hệ thống</li>
            <li class="nav-item">
                <a href="../index.php" class="nav-link" target="_blank">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>Về trang chủ</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../pages/logout.php" class="nav-link text-danger">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Đăng xuất</span>
                </a>
            </li>
        </ul>
        <div class="p-3 border-top border-secondary text-white-50 text-center small">
            &copy; <?= date('Y') ?> Shop Điện Tử Admin
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="d-flex align-items-center">
                <h5 class="m-0 text-dark fw-bold"><i class="fa-solid fa-gauge-high me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h5>
            </div>
            <div class="d-flex align-items-center">
                <div class="dropdown me-3">
                    <span class="text-secondary small me-2">Xin chào,</span>
                    <strong class="text-dark">
                        <i class="fa-solid fa-circle-user text-primary me-1"></i>
                        <?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin') ?>
                    </strong>
                </div>
                <a href="../pages/logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn đăng xuất?')">
                    <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Đăng xuất
                </a>
            </div>
        </header>

        <!-- Content Area -->
        <main class="admin-content">
            <!-- Flash Message -->
            <?php
            $flash_success = function_exists('flash') ? flash('success') : ($_SESSION['success'] ?? '');
            if (!empty($_SESSION['success']) && !function_exists('flash')) unset($_SESSION['success']);
            $flash_error = function_exists('flash') ? flash('error') : ($_SESSION['error'] ?? '');
            if (!empty($_SESSION['error']) && !function_exists('flash')) unset($_SESSION['error']);
            ?>
            <?php if (!empty($flash_success)): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($flash_success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($flash_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($flash_error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- 1. Statistics Cards -->
            <div class="row g-3 mb-4">
                <!-- Total Products -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100 p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Tổng sản phẩm</div>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_products) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Orders -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100 p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                                <i class="fa-solid fa-clipboard-list"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Tổng đơn hàng</div>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_orders) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Customers -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100 p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Khách hàng</div>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_customers) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100 p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                                <i class="fa-solid fa-coins"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Doanh thu hoàn tất</div>
                                <h4 class="mb-0 fw-bold text-success"><?= formatPrice($total_revenue) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Quick Links -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-bolt me-2 text-warning"></i>Thao tác nhanh</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <a href="add-product.php" class="btn btn-primary w-100 quick-action-btn">
                                <i class="fa-solid fa-plus-circle"></i> Thêm sản phẩm
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="products.php" class="btn btn-outline-primary w-100 quick-action-btn">
                                <i class="fa-solid fa-box-open"></i> Quản lý sản phẩm
                            </a>
                        </div>
                        <div class="col-6 col-md-2">
                            <a href="categories.php" class="btn btn-outline-secondary w-100 quick-action-btn">
                                <i class="fa-solid fa-layer-group"></i> Danh mục
                            </a>
                        </div>
                        <div class="col-6 col-md-2">
                            <a href="orders.php" class="btn btn-outline-success w-100 quick-action-btn">
                                <i class="fa-solid fa-shopping-cart"></i> Đơn hàng
                            </a>
                        </div>
                        <div class="col-12 col-md-2">
                            <a href="users.php" class="btn btn-outline-info w-100 quick-action-btn">
                                <i class="fa-solid fa-users-gear"></i> Khách hàng
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Recent Orders Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Đơn hàng gần đây (10 đơn mới nhất)
                    </h6>
                    <a href="orders.php" class="btn btn-sm btn-outline-primary">
                        Xem tất cả đơn hàng <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 100px;">Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Số điện thoại</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày đặt</th>
                                    <th class="text-end pe-3">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                                            Chưa có đơn hàng nào được ghi nhận.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-primary">#<?= $order['id'] ?></td>
                                            <td>
                                                <div class="fw-semibold text-dark">
                                                    <?= htmlspecialchars($order['fullname'] ?: ($order['user_fullname'] ?: ($order['username'] ?: 'Khách'))) ?>
                                                </div>
                                                <?php if (!empty($order['username'])): ?>
                                                    <small class="text-muted">TK: <?= htmlspecialchars($order['username']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($order['phone'] ?: '---') ?></td>
                                            <td class="fw-bold text-danger"><?= formatPrice($order['total_amount']) ?></td>
                                            <td><?= getOrderStatusBadge($order['status']) ?></td>
                                            <td class="text-muted small">
                                                <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="order-detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Xem chi tiết">
                                                    <i class="fa-solid fa-eye me-1"></i>Chi tiết
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>
</body>
</html>
