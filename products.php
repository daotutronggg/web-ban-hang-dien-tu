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

$page_title = 'Quản lý sản phẩm';
$current_page = 'products';

// Lấy danh mục cho dropdown filter
$categories = [];
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
if ($cat_res = $conn->query($cat_query)) {
    while ($cat_row = $cat_res->fetch_assoc()) {
        $categories[] = $cat_row;
    }
    $cat_res->free();
}

// Lấy tham số tìm kiếm và lọc
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Xây dựng điều kiện WHERE
$where_clauses = [];
$params = [];
$param_types = '';

if ($search !== '') {
    $where_clauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $like_search = '%' . $search . '%';
    $params[] = $like_search;
    $params[] = $like_search;
    $param_types .= 'ss';
}

if ($category_id > 0) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = $category_id;
    $param_types .= 'i';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 1. Đếm tổng số sản phẩm thỏa mãn điều kiện
$count_sql = "SELECT COUNT(*) AS total FROM products p $where_sql";
$total_products = 0;

if (!empty($params)) {
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param($param_types, ...$params);
    $stmt_count->execute();
    $res_count = $stmt_count->get_result();
    $total_products = $res_count->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();
} else {
    $res_count = $conn->query($count_sql);
    $total_products = $res_count->fetch_assoc()['total'] ?? 0;
    $res_count->free();
}

$total_pages = ceil($total_products / $limit);
if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// 2. Lấy danh sách sản phẩm theo trang
$query = "SELECT p.*, c.name AS category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          $where_sql 
          ORDER BY p.id DESC 
          LIMIT ? OFFSET ?";

$list_params = $params;
$list_params[] = $limit;
$list_params[] = $offset;
$list_types = $param_types . 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$products_result = $stmt->get_result();

$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

// Tạo query string cho các liên kết phân trang
$query_params = $_GET;
unset($query_params['page']);
$base_query_string = http_build_query($query_params);
$page_link_prefix = 'products.php?' . ($base_query_string ? $base_query_string . '&' : '') . 'page=';
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
        .product-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
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
                <h5 class="m-0 text-dark fw-bold"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h5>
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

        <!-- Content -->
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

            <!-- Header Actions and Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-center justify-content-between">
                        <!-- Search & Filter Form -->
                        <div class="col-12 col-md-8">
                            <form method="GET" action="products.php" class="row g-2">
                                <div class="col-12 col-sm-6 col-lg-5">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                        <input type="text" name="search" class="form-control" placeholder="Tìm theo tên sản phẩm..." value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4 col-lg-4">
                                    <select name="category_id" class="form-select">
                                        <option value="0">-- Tất cả danh mục --</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-2 col-lg-3 d-flex gap-1">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="fa-solid fa-filter me-1"></i>Lọc
                                    </button>
                                    <?php if ($search !== '' || $category_id > 0): ?>
                                        <a href="products.php" class="btn btn-outline-secondary" title="Xóa bộ lọc">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <!-- Add Button -->
                        <div class="col-12 col-md-4 text-md-end">
                            <a href="add-product.php" class="btn btn-primary">
                                <i class="fa-solid fa-plus-circle me-1"></i>Thêm sản phẩm mới
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 fw-bold text-dark">
                        Danh sách sản phẩm <span class="badge bg-secondary ms-1"><?= number_format($total_products) ?></span>
                    </h6>
                    <small class="text-muted">Trang <?= $page ?> / <?= max(1, $total_pages) ?></small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 70px;">ID</th>
                                    <th style="width: 80px;">Hình ảnh</th>
                                    <th>Tên sản phẩm</th>
                                    <th>Danh mục</th>
                                    <th>Giá bán</th>
                                    <th style="width: 120px;">Số lượng</th>
                                    <th class="text-end pe-3" style="width: 150px;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-box-open fa-3x mb-3 text-secondary d-block"></i>
                                            Không tìm thấy sản phẩm nào.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $p): ?>
                                        <?php
                                        // Kiểm tra ảnh hiển thị
                                        $image_src = '../assets/images/placeholder.png';
                                        if (!empty($p['image'])) {
                                            $img_path = '../assets/images/products/' . $p['image'];
                                            if (file_exists(__DIR__ . '/' . $img_path)) {
                                                $image_src = $img_path;
                                            } else {
                                                $image_src = $img_path; // link direct
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-muted">#<?= $p['id'] ?></td>
                                            <td>
                                                <img src="<?= htmlspecialchars($image_src) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-thumb" onerror="this.src='https://placehold.co/100x100?text=No+Image';">
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 50, '...')) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?= htmlspecialchars($p['category_name'] ?? 'Chưa phân loại') ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-danger"><?= formatPrice($p['price']) ?></td>
                                            <td>
                                                <?php if ($p['quantity'] <= 0): ?>
                                                    <span class="badge bg-danger">Hết hàng (0)</span>
                                                <?php elseif ($p['quantity'] <= 5): ?>
                                                    <span class="badge bg-warning text-dark">Sắp hết (<?= $p['quantity'] ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><?= $p['quantity'] ?> chiếc</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit-product.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Chỉnh sửa">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                    <a href="delete-product.php?id=<?= $p['id'] ?>" class="btn btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm \'<?= htmlspecialchars(addslashes($p['name'])) ?>\'?');">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
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
