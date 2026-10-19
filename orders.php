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

$page_title = 'Quản lý đơn hàng';
$current_page = 'orders';

$valid_statuses = [
    'pending' => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao hàng',
    'completed' => 'Đã hoàn thành',
    'cancelled' => 'Đã hủy'
];

// Xử lý cập nhật trạng thái qua POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');

    if ($order_id > 0 && array_key_exists($new_status, $valid_statuses)) {
        $stmt_up = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt_up->bind_param("si", $new_status, $order_id);
        if ($stmt_up->execute()) {
            $msg = "Đã cập nhật trạng thái đơn hàng #$order_id thành '{$valid_statuses[$new_status]}'.";
            if (function_exists('flash')) {
                flash('success', $msg);
            } else {
                $_SESSION['success'] = $msg;
            }
        } else {
            $msg = "Lỗi khi cập nhật trạng thái: " . $conn->error;
            if (function_exists('flash')) {
                flash('error', $msg);
            } else {
                $_SESSION['error'] = $msg;
            }
        }
        $stmt_up->close();
    } else {
        if (function_exists('flash')) {
            flash('error', 'Dữ liệu cập nhật trạng thái không hợp lệ!');
        } else {
            $_SESSION['error'] = 'Dữ liệu cập nhật trạng thái không hợp lệ!';
        }
    }

    // Redirect lại giữ nguyên các query params
    $redirect_url = 'orders.php';
    $qs = [];
    if (!empty($_GET['status'])) $qs['status'] = $_GET['status'];
    if (!empty($_GET['search'])) $qs['search'] = $_GET['search'];
    if (!empty($_GET['page'])) $qs['page'] = $_GET['page'];
    if (!empty($qs)) $redirect_url .= '?' . http_build_query($qs);

    redirect(BASE_URL . 'admin/' . $redirect_url);
}

// Bộ lọc
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = '';

if ($status_filter !== '' && array_key_exists($status_filter, $valid_statuses)) {
    $where_clauses[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search !== '') {
    $where_clauses[] = "(o.id = ? OR o.fullname LIKE ? OR o.phone LIKE ? OR u.fullname LIKE ? OR u.username LIKE ?)";
    $search_id = intval($search);
    $search_like = '%' . $search . '%';
    $params[] = $search_id;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'issss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 1. Đếm tổng số đơn hàng
$count_sql = "SELECT COUNT(*) AS total 
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              $where_sql";
$total_orders = 0;

if (!empty($params)) {
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $res_count = $stmt_count->get_result();
    $total_orders = $res_count->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();
} else {
    $res_count = $conn->query($count_sql);
    $total_orders = $res_count->fetch_assoc()['total'] ?? 0;
    $res_count->free();
}

$total_pages = ceil($total_orders / $limit);
if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// 2. Lấy danh sách đơn hàng
$query = "SELECT o.*, u.fullname AS user_fullname, u.username, u.email AS user_email 
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          $where_sql 
          ORDER BY o.created_at DESC 
          LIMIT ? OFFSET ?";

$list_params = $params;
$list_params[] = $limit;
$list_params[] = $offset;
$list_types = $types . 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$orders_result = $stmt->get_result();

$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Chờ xử lý</span>';
        case 'confirmed':
            return '<span class="badge bg-info text-dark"><i class="fa-solid fa-circle-check me-1"></i>Đã xác nhận</span>';
        case 'shipping':
            return '<span class="badge bg-primary"><i class="fa-solid fa-truck-fast me-1"></i>Đang giao hàng</span>';
        case 'completed':
            return '<span class="badge bg-success"><i class="fa-solid fa-check-double me-1"></i>Đã hoàn thành</span>';
        case 'cancelled':
            return '<span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>Đã hủy</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

// Query parameters cho phân trang
$page_params = $_GET;
unset($page_params['page']);
$page_query_string = http_build_query($page_params);
$page_link_prefix = 'orders.php?' . ($page_query_string ? $page_query_string . '&' : '') . 'page=';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Shop Điện Tử</title>
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
        .status-select-sm {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            width: auto;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <a href="index.php" class="sidebar-brand">
            <i class="fa-solid fa-bolt text-warning me-2"></i>
            <span>Shop Điện Tử</span>
        </a>
        <ul class="sidebar-nav">
            <li class="nav-header">Quản lý hệ thống</li>
            <li class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Tổng quan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="products.php" class="nav-link">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Sản phẩm</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Danh mục</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php" class="nav-link active">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>Đơn hàng</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link">
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

    <!-- Main Content -->
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="d-flex align-items-center">
                <h5 class="m-0 text-dark fw-bold"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h5>
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

        <main class="admin-content">
            <!-- Flash messages -->
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

            <!-- Filter Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="orders.php" class="row g-2 align-items-center">
                        <div class="col-12 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Tìm theo mã đơn (#ID), tên khách, SĐT..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <select name="status" class="form-select">
                                <option value="">-- Tất cả trạng thái --</option>
                                <?php foreach ($valid_statuses as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3 d-flex gap-1">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fa-solid fa-filter me-1"></i>Lọc đơn hàng
                            </button>
                            <?php if ($status_filter !== '' || $search !== ''): ?>
                                <a href="orders.php" class="btn btn-outline-secondary" title="Đặt lại bộ lọc">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 fw-bold text-dark">
                        Danh sách đơn hàng <span class="badge bg-secondary ms-1"><?= number_format($total_orders) ?></span>
                    </h6>
                    <small class="text-muted">Trang <?= $page ?> / <?= max(1, $total_pages) ?></small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 80px;">Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Số điện thoại</th>
                                    <th>Tổng tiền</th>
                                    <th style="width: 140px;">Trạng thái</th>
                                    <th style="width: 250px;">Cập nhật nhanh</th>
                                    <th>Ngày đặt</th>
                                    <th class="text-end pe-3" style="width: 100px;">Chi tiết</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-clipboard-question fa-3x mb-3 text-secondary d-block"></i>
                                            Không tìm thấy đơn hàng nào phù hợp.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-primary">#<?= $order['id'] ?></td>
                                            <td>
                                                <div class="fw-semibold text-dark">
                                                    <?= htmlspecialchars($order['fullname'] ?: ($order['user_fullname'] ?: ($order['username'] ?: 'Khách hàng'))) ?>
                                                </div>
                                                <?php if (!empty($order['user_email'])): ?>
                                                    <small class="text-muted"><i class="fa-regular fa-envelope me-1"></i><?= htmlspecialchars($order['user_email']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($order['phone'] ?: '---') ?></td>
                                            <td class="fw-bold text-danger"><?= formatPrice($order['total_amount']) ?></td>
                                            <td><?= getStatusBadge($order['status']) ?></td>
                                            <td>
                                                <!-- Form update nhanh trạng thái -->
                                                <form action="orders.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" method="POST" class="d-flex align-items-center gap-1">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                    <select name="status" class="form-select form-select-sm status-select-sm">
                                                        <?php foreach ($valid_statuses as $st_key => $st_name): ?>
                                                            <option value="<?= $st_key ?>" <?= $order['status'] === $st_key ? 'selected' : '' ?>>
                                                                <?= $st_name ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Cập nhật trạng thái">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="text-muted small">
                                                <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="order-detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-info" title="Xem hóa đơn và sản phẩm">
                                                    <i class="fa-solid fa-eye me-1"></i>Xem
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white py-3 border-top d-flex justify-content-center">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm m-0">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $page_link_prefix . ($page - 1) ?>">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $page_link_prefix . $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $page_link_prefix . ($page + 1) ?>">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
