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

$page_title = 'Chỉnh sửa danh mục';
$current_page = 'categories';

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    if (function_exists('flash')) flash('error', 'Mã danh mục không hợp lệ!');
    redirect(BASE_URL . 'admin/categories.php');
}

// Lấy thông tin danh mục hiện tại
$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$category = $res->fetch_assoc();
$stmt->close();

if (!$category) {
    if (function_exists('flash')) flash('error', 'Không tìm thấy danh mục yêu cầu!');
    redirect(BASE_URL . 'admin/categories.php');
}

$errors = [];
$name = $category['name'];
$description = $category['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = 'Vui lòng nhập tên danh mục.';
    } else {
        // Kiểm tra tên trùng với danh mục khác
        $stmt_check = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $stmt_check->bind_param("si", $name, $id);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        if ($res_check->num_rows > 0) {
            $errors[] = 'Tên danh mục này đã tồn tại ở một danh mục khác!';
        }
        $stmt_check->close();
    }

    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("ssi", $name, $description, $id);
            if ($stmt_update->execute()) {
                $stmt_update->close();
                if (function_exists('flash')) {
                    flash('success', 'Cập nhật danh mục thành công!');
                } else {
                    $_SESSION['success'] = 'Cập nhật danh mục thành công!';
                }
                redirect(BASE_URL . 'admin/categories.php');
            } else {
                $errors[] = 'Lỗi cập nhật: ' . $stmt_update->error;
                $stmt_update->close();
            }
        } else {
            $errors[] = 'Lỗi kết nối cơ sở dữ liệu: ' . $conn->error;
        }
    }
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
                <h5 class="m-0 text-dark fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i><?= htmlspecialchars($page_title) ?> #<?= $category['id'] ?></h5>
            </div>
            <div class="d-flex align-items-center">
                <a href="categories.php" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="fa-solid fa-arrow-left me-1"></i>Quay lại danh sách
                </a>
                <div class="dropdown me-3">
                    <span class="text-secondary small me-2">Xin chào,</span>
                    <strong class="text-dark">
                        <i class="fa-solid fa-circle-user text-primary me-1"></i>
                        <?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin') ?>
                    </strong>
                </div>
            </div>
        </header>

        <main class="admin-content">
            <!-- Errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <h6 class="alert-heading fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Có lỗi xảy ra:</h6>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="m-0 fw-bold text-dark">Chỉnh sửa thông tin danh mục</h6>
                        </div>
                        <div class="card-body p-4">
                            <form action="edit-category.php" method="POST">
                                <input type="hidden" name="id" value="<?= $category['id'] ?>">

                                <div class="mb-3">
                                    <label for="name" class="form-label fw-semibold">Tên danh mục <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                                </div>

                                <div class="mb-4">
                                    <label for="description" class="form-label fw-semibold">Mô tả</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="categories.php" class="btn btn-secondary px-4">Hủy bỏ</a>
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>Lưu thay đổi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
