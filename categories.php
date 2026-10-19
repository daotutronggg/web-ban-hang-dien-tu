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

$page_title = 'Quản lý danh mục';
$current_page = 'categories';
$errors = [];

// Xử lý thêm danh mục mới trực tiếp qua POST trên trang này
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = 'Vui lòng nhập tên danh mục.';
    } else {
        // Kiểm tra xem tên danh mục đã tồn tại chưa
        $stmt_check = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        if ($res_check->num_rows > 0) {
            $errors[] = 'Danh mục với tên này đã tồn tại!';
        }
        $stmt_check->close();
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $name, $description);
        if ($stmt->execute()) {
            $stmt->close();
            if (function_exists('flash')) {
                flash('success', 'Thêm danh mục mới thành công!');
            } else {
                $_SESSION['success'] = 'Thêm danh mục mới thành công!';
            }
            redirect(BASE_URL . 'admin/categories.php');
        } else {
            $errors[] = 'Lỗi thêm danh mục: ' . $conn->error;
            $stmt->close();
        }
    }
}

// Lấy danh sách danh mục kèm số lượng sản phẩm
$categories = [];
$query = "SELECT c.*, COUNT(p.id) AS product_count 
          FROM categories c 
          LEFT JOIN products p ON c.id = p.category_id 
          GROUP BY c.id 
          ORDER BY c.id DESC";

if ($result = $conn->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $result->free();
}
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
                <a href="categories.php" class="nav-link active">
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
                <h5 class="m-0 text-dark fw-bold"><i class="fa-solid fa-layer-group me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h5>
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
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Action Bar -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 fw-bold text-dark">Danh mục sản phẩm</h6>
                        <small class="text-muted">Tổng cộng <?= count($categories) ?> danh mục hiện có</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fa-solid fa-plus-circle me-1"></i>Thêm nhanh (Modal)
                        </button>
                        <a href="add-category.php" class="btn btn-outline-primary">
                            <i class="fa-solid fa-file-circle-plus me-1"></i>Trang thêm mới
                        </a>
                    </div>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 80px;">ID</th>
                                    <th style="width: 250px;">Tên danh mục</th>
                                    <th>Mô tả</th>
                                    <th style="width: 160px;">Số sản phẩm</th>
                                    <th style="width: 160px;">Ngày tạo</th>
                                    <th class="text-end pe-3" style="width: 140px;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-layer-group fa-3x mb-3 text-secondary d-block"></i>
                                            Chưa có danh mục nào. Hãy bấm "Thêm danh mục" để bắt đầu!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-muted">#<?= $cat['id'] ?></td>
                                            <td class="fw-semibold text-dark">
                                                <i class="fa-solid fa-tag text-primary me-2"></i><?= htmlspecialchars($cat['name']) ?>
                                            </td>
                                            <td class="text-muted">
                                                <?= htmlspecialchars($cat['description'] ?: 'Không có mô tả') ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                    <?= number_format($cat['product_count']) ?> sản phẩm
                                                </span>
                                            </td>
                                            <td class="text-muted small">
                                                <?= !empty($cat['created_at']) ? date('d/m/Y H:i', strtotime($cat['created_at'])) : '---' ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit-category.php?id=<?= $cat['id'] ?>" class="btn btn-outline-primary" title="Chỉnh sửa">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                    <a href="delete-category.php?id=<?= $cat['id'] ?>" class="btn btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục \'<?= htmlspecialchars(addslashes($cat['name'])) ?>\'?');">
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
            </div>
        </main>
    </div>
</div>

<!-- Modal Thêm Danh Mục -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addCategoryModalLabel"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Thêm danh mục mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories.php" method="POST">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cat_name" class="form-label fw-semibold">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cat_name" name="name" placeholder="VD: Điện thoại, Laptop, Phụ kiện..." required>
                    </div>
                    <div class="mb-3">
                        <label for="cat_desc" class="form-label fw-semibold">Mô tả</label>
                        <textarea class="form-control" id="cat_desc" name="description" rows="3" placeholder="Mô tả thông tin ngắn gọn về nhóm danh mục này..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Lưu danh mục</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
