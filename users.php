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

$page_title = 'Quản lý người dùng';
$current_page = 'users';

// Xử lý xóa người dùng
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = intval($_GET['id'] ?? 0);
    $current_admin_id = $_SESSION['user_id'] ?? 0;

    if ($del_id <= 0) {
        if (function_exists('flash')) flash('error', 'Mã người dùng không hợp lệ!');
        redirect(BASE_URL . 'admin/users.php');
    }

    if ($del_id === $current_admin_id) {
        if (function_exists('flash')) flash('error', 'Bạn không thể tự xóa tài khoản quản trị của chính mình!');
        redirect(BASE_URL . 'admin/users.php');
    }

    // Lấy thông tin user cần xóa
    $stmt_check = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $del_id);
    $stmt_check->execute();
    $target_user = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if (!$target_user) {
        if (function_exists('flash')) flash('error', 'Không tìm thấy tài khoản cần xóa!');
        redirect(BASE_URL . 'admin/users.php');
    }

    // Không được xóa admin
    if ($target_user['role'] === 'admin') {
        if (function_exists('flash')) flash('error', 'Không thể xóa tài khoản Quản trị viên (Admin)!');
        redirect(BASE_URL . 'admin/users.php');
    }

    // Kiểm tra xem khách hàng có đơn hàng không
    $stmt_orders = $conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE user_id = ?");
    $stmt_orders->bind_param("i", $del_id);
    $stmt_orders->execute();
    $order_count = $stmt_orders->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_orders->close();

    if ($order_count > 0) {
        if (function_exists('flash')) {
            flash('error', "Không thể xóa khách hàng '{$target_user['username']}' vì đã có $order_count đơn hàng trong hệ thống!");
        } else {
            $_SESSION['error'] = "Không thể xóa khách hàng '{$target_user['username']}' vì đã có $order_count đơn hàng trong hệ thống!";
        }
        redirect(BASE_URL . 'admin/users.php');
    }

    // Thực hiện xóa khách hàng
    $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt_del->bind_param("i", $del_id);
    if ($stmt_del->execute()) {
        $msg = "Đã xóa khách hàng '{$target_user['username']}' thành công!";
        if (function_exists('flash')) {
            flash('success', $msg);
        } else {
            $_SESSION['success'] = $msg;
        }
    } else {
        $msg = "Lỗi khi xóa người dùng: " . $conn->error;
        if (function_exists('flash')) {
            flash('error', $msg);
        } else {
            $_SESSION['error'] = $msg;
        }
    }
    $stmt_del->close();
    redirect(BASE_URL . 'admin/users.php');
}

// Bộ lọc và tìm kiếm
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = '';

if ($role_filter === 'admin' || $role_filter === 'customer') {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($search !== '') {
    $where_clauses[] = "(username LIKE ? OR fullname LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like_search = '%' . $search . '%';
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $types .= 'ssss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 1. Đếm tổng số người dùng
$count_sql = "SELECT COUNT(*) AS total FROM users $where_sql";
$total_users = 0;

if (!empty($params)) {
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $res_count = $stmt_count->get_result();
    $total_users = $res_count->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();
} else {
    $res_count = $conn->query($count_sql);
    $total_users = $res_count->fetch_assoc()['total'] ?? 0;
    $res_count->free();
}

$total_pages = ceil($total_users / $limit);
if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// 2. Lấy danh sách người dùng
$query = "SELECT id, username, fullname, email, phone, address, role, created_at 
          FROM users 
          $where_sql 
          ORDER BY id DESC 
          LIMIT ? OFFSET ?";

$list_params = $params;
$list_params[] = $limit;
$list_params[] = $offset;
$list_types = $types . 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$users_result = $stmt->get_result();

$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Tạo query link cho phân trang
$page_params = $_GET;
unset($page_params['page']);
$page_query_string = http_build_query($page_params);
$page_link_prefix = 'users.php?' . ($page_query_string ? $page_query_string . '&' : '') . 'page=';
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
                <a href="orders.php" class="nav-link">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>Đơn hàng</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link active">
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
                <h5 class="m-0 text-dark fw-bold"><i class="fa-solid fa-users me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h5>
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

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="users.php" class="row g-2 align-items-center">
                        <div class="col-12 col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Tìm kiếm theo tên đăng nhập, họ tên, email, SĐT..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <select name="role" class="form-select">
                                <option value="">-- Tất cả vai trò --</option>
                                <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Khách hàng (customer)</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Quản trị viên (admin)</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3 d-flex gap-1">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fa-solid fa-filter me-1"></i>Lọc danh sách
                            </button>
                            <?php if ($search !== '' || $role_filter !== ''): ?>
                                <a href="users.php" class="btn btn-outline-secondary" title="Đặt lại bộ lọc">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 fw-bold text-dark">
                        Danh sách tài khoản <span class="badge bg-secondary ms-1"><?= number_format($total_users) ?></span>
                    </h6>
                    <small class="text-muted">Trang <?= $page ?> / <?= max(1, $total_pages) ?></small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 70px;">ID</th>
                                    <th>Tên đăng nhập</th>
                                    <th>Họ và tên</th>
                                    <th>Email</th>
                                    <th>Điện thoại</th>
                                    <th>Vai trò</th>
                                    <th>Ngày tạo</th>
                                    <th class="text-end pe-3" style="width: 120px;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-user-slash fa-3x mb-3 text-secondary d-block"></i>
                                            Không tìm thấy người dùng nào phù hợp.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-muted">#<?= $u['id'] ?></td>
                                            <td>
                                                <strong class="text-dark">
                                                    <i class="fa-regular fa-circle-user text-secondary me-1"></i>
                                                    <?= htmlspecialchars($u['username']) ?>
                                                </strong>
                                            </td>
                                            <td><?= htmlspecialchars($u['fullname'] ?: '---') ?></td>
                                            <td>
                                                <?= !empty($u['email']) ? '<a href="mailto:' . htmlspecialchars($u['email']) . '" class="text-decoration-none">' . htmlspecialchars($u['email']) . '</a>' : '<span class="text-muted">---</span>' ?>
                                            </td>
                                            <td><?= htmlspecialchars($u['phone'] ?: '---') ?></td>
                                            <td>
                                                <?php if ($u['role'] === 'admin'): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fa-solid fa-shield-halved me-1"></i>Quản trị viên
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-dark">
                                                        <i class="fa-solid fa-user me-1"></i>Khách hàng
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted small">
                                                <?= !empty($u['created_at']) ? date('d/m/Y H:i', strtotime($u['created_at'])) : '---' ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <?php if ($u['role'] === 'admin'): ?>
                                                    <span class="badge bg-light text-muted border" title="Không thể xóa Quản trị viên">
                                                        <i class="fa-solid fa-lock me-1"></i>Cố định
                                                    </span>
                                                <?php else: ?>
                                                    <a href="users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" title="Xóa tài khoản khách hàng" onclick="return confirm('Bạn có chắc chắn muốn xóa tài khoản \'<?= htmlspecialchars(addslashes($u['username'])) ?>\'?');">
                                                        <i class="fa-solid fa-trash-can me-1"></i>Xóa
                                                    </a>
                                                <?php endif; ?>
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
