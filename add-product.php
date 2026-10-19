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

$page_title = 'Thêm sản phẩm mới';
$current_page = 'products';

// Lấy danh sách danh mục
$categories = [];
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
if ($cat_res = $conn->query($cat_query)) {
    while ($cat = $cat_res->fetch_assoc()) {
        $categories[] = $cat;
    }
    $cat_res->free();
}

$errors = [];
$name = '';
$category_id = '';
$price = '';
$quantity = '';
$description = '';

// Xử lý gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $price = trim($_POST['price'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // 1. Kiểm tra validation
    if (empty($name)) {
        $errors[] = 'Vui lòng nhập tên sản phẩm.';
    }

    if ($category_id <= 0) {
        $errors[] = 'Vui lòng chọn danh mục sản phẩm.';
    }

    if ($price === '' || !is_numeric($price) || floatval($price) < 0) {
        $errors[] = 'Giá sản phẩm phải là một số lớn hơn hoặc bằng 0.';
    }

    if ($quantity === '' || !ctype_digit($quantity) || intval($quantity) < 0) {
        $errors[] = 'Số lượng sản phẩm phải là một số nguyên không âm.';
    }

    // 2. Xử lý tải lên hình ảnh
    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = $_FILES['image']['name'];
        $file_size = $_FILES['image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = 'Chỉ chấp nhận các định dạng ảnh: JPG, JPEG, PNG, GIF, WEBP.';
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB
            $errors[] = 'Kích thước tệp hình ảnh không được vượt quá 5MB.';
        } else {
            $upload_dir = __DIR__ . '/../assets/images/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $image_name = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $target_file = $upload_dir . $image_name;

            if (!move_uploaded_file($file_tmp, $target_file)) {
                $errors[] = 'Không thể lưu hình ảnh đã tải lên. Vui lòng thử lại!';
                $image_name = '';
            }
        }
    }

    // 3. Nếu không có lỗi, lưu vào cơ sở dữ liệu
    if (empty($errors)) {
        $float_price = floatval($price);
        $int_quantity = intval($quantity);

        $stmt = $conn->prepare("INSERT INTO products (category_id, name, description, price, quantity, image, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("issdis", $category_id, $name, $description, $float_price, $int_quantity, $image_name);
            if ($stmt->execute()) {
                $stmt->close();
                if (function_exists('flash')) {
                    flash('success', 'Thêm sản phẩm mới thành công!');
                } else {
                    $_SESSION['success'] = 'Thêm sản phẩm mới thành công!';
                }
                redirect(BASE_URL . 'admin/products.php');
            } else {
                $errors[] = 'Lỗi truy vấn cơ sở dữ liệu: ' . $stmt->error;
                $stmt->close();
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
        .image-preview-container {
            width: 160px;
            height: 160px;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #f8fafc;
            position: relative;
        }
        .image-preview-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
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
                <a href="products.php" class="nav-link active">
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
                <h5 class="m-0 text-dark fw-bold"><i class="fa-solid fa-plus-circle me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h5>
            </div>
            <div class="d-flex align-items-center">
                <a href="products.php" class="btn btn-outline-secondary btn-sm me-3">
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
            <!-- Alert Errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <h6 class="alert-heading fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Vui lòng kiểm tra lại:</h6>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark">Thông tin sản phẩm mới</h6>
                </div>
                <div class="card-body p-4">
                    <form action="add-product.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-4">
                            <!-- Left Column: Basic Info -->
                            <div class="col-12 col-lg-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label fw-semibold">Tên sản phẩm <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="VD: Laptop Dell XPS 13" value="<?= htmlspecialchars($name) ?>" required>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label for="category_id" class="form-label fw-semibold">Danh mục <span class="text-danger">*</span></label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">-- Chọn danh mục --</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label for="price" class="form-label fw-semibold">Giá bán (VNĐ) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="price" name="price" placeholder="0" min="0" step="1000" value="<?= htmlspecialchars($price) ?>" required>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label for="quantity" class="form-label fw-semibold">Số lượng <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" placeholder="0" min="0" value="<?= htmlspecialchars($quantity) ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label fw-semibold">Mô tả chi tiết</label>
                                    <textarea class="form-control" id="description" name="description" rows="6" placeholder="Mô tả cấu hình, tính năng, đặc điểm nổi bật..."><?= htmlspecialchars($description) ?></textarea>
                                </div>
                            </div>

                            <!-- Right Column: Image Upload -->
                            <div class="col-12 col-lg-4">
                                <div class="card bg-light border-0 p-3">
                                    <label class="form-label fw-semibold mb-2">Hình ảnh sản phẩm</label>
                                    
                                    <div class="d-flex justify-content-center mb-3">
                                        <div class="image-preview-container">
                                            <img id="imagePreview" src="https://placehold.co/160x160?text=Preview" alt="Xem trước hình ảnh" class="d-none">
                                            <div id="imagePlaceholder" class="text-center text-muted">
                                                <i class="fa-solid fa-image fa-3x mb-2 text-secondary"></i>
                                                <div class="small">Chưa chọn ảnh</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <input type="file" class="form-control form-control-sm" id="imageInput" name="image" accept="image/*" onchange="previewProductImage(event)">
                                        <div class="form-text small">Hỗ trợ: JPG, JPEG, PNG, GIF, WEBP (Tối đa 5MB).</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="products.php" class="btn btn-secondary px-4">Hủy bỏ</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Lưu sản phẩm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewProductImage(event) {
    const input = event.target;
    const preview = document.getElementById('imagePreview');
    const placeholder = document.getElementById('imagePlaceholder');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            placeholder.classList.add('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '';
        preview.classList.add('d-none');
        placeholder.classList.remove('d-none');
    }
}
</script>
</body>
</html>
