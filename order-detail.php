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

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    if (function_exists('flash')) flash('error', 'Mã đơn hàng không hợp lệ!');
    redirect(BASE_URL . 'admin/orders.php');
}

$valid_statuses = [
    'pending' => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao hàng',
    'completed' => 'Đã hoàn thành',
    'cancelled' => 'Đã hủy'
];

// Xử lý cập nhật trạng thái đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $new_status = trim($_POST['status'] ?? '');
    if (array_key_exists($new_status, $valid_statuses)) {
        $stmt_up = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt_up->bind_param("si", $new_status, $id);
        if ($stmt_up->execute()) {
            $msg = "Đã cập nhật trạng thái đơn hàng #$id thành: " . $valid_statuses[$new_status];
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
    }
    redirect(BASE_URL . 'admin/order-detail.php?id=' . $id);
}

// Lấy thông tin đơn hàng
$stmt = $conn->prepare("SELECT o.*, u.username, u.email AS user_email, u.fullname AS user_fullname, u.phone AS user_phone 
                        FROM orders o 
                        LEFT JOIN users u ON o.user_id = u.id 
                        WHERE o.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$order_res = $stmt->get_result();
$order = $order_res->fetch_assoc();
$stmt->close();

if (!$order) {
    if (function_exists('flash')) flash('error', 'Không tìm thấy đơn hàng yêu cầu!');
    redirect(BASE_URL . 'admin/orders.php');
}

// Lấy chi tiết các mặt hàng trong đơn
$stmt_items = $conn->prepare("SELECT od.*, p.name AS product_name, p.image AS product_image 
                             FROM order_details od 
                             LEFT JOIN products p ON od.product_id = p.id 
                             WHERE od.order_id = ?");
$stmt_items->bind_param("i", $id);
$stmt_items->execute();
$items_res = $stmt_items->get_result();
$order_items = [];
while ($item = $items_res->fetch_assoc()) {
    $order_items[] = $item;
}
$stmt_items->close();

$page_title = 'Chi tiết đơn hàng #' . $order['id'];
$current_page = 'orders';

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
        .item-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        /* In ấn chuẩn A4 */
        @media print {
            .admin-sidebar, .admin-topbar, .no-print, .btn, form {
                display: none !important;
            }
            .admin-wrapper {
                display: block !important;
            }
            .admin-main, .admin-content {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            body {
                background: #fff !important;
                color: #000 !important;
            }
            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            .print-header {
                display: block !important;
            }
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
                <h5 class="m-0 text-dark fw-bold"><i class="fa-solid fa-file-invoice me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h5>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm no-print">
                    <i class="fa-solid fa-print me-1"></i>In đơn hàng
                </button>
                <a href="orders.php" class="btn btn-outline-primary btn-sm no-print">
                    <i class="fa-solid fa-arrow-left me-1"></i>Danh sách đơn
                </a>
                <div class="dropdown ms-2">
                    <span class="text-secondary small me-2">Xin chào,</span>
                    <strong class="text-dark">
                        <i class="fa-solid fa-circle-user text-primary me-1"></i>
                        <?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin') ?>
                    </strong>
                </div>
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
                <div class="alert alert-success alert-dismissible fade show shadow-sm no-print" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($flash_success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($flash_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm no-print" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($flash_error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Invoice Header for Print only -->
            <div class="d-none d-print-block mb-4 border-bottom pb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold mb-1">SHOP ĐIỆN TỬ</h2>
                        <div>Địa chỉ: Cần Thơ, Việt Nam</div>
                        <div>Điện thoại: 0900.000.000 - Email: support@shopdientu.vn</div>
                    </div>
                    <div class="text-end">
                        <h3 class="fw-bold text-primary mb-0">HÓA ĐƠN BÁN HÀNG</h3>
                        <div class="text-muted">Mã đơn: #<?= $order['id'] ?></div>
                        <div class="small">Ngày in: <?= date('d/m/Y H:i') ?></div>
                    </div>
                </div>
            </div>

            <!-- Top Cards: Order Info & Status Update -->
            <div class="row g-4 mb-4">
                <!-- Customer Details -->
                <div class="col-12 col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-user-tag me-2 text-primary"></i>Thông tin người nhận & Giao hàng</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-sm-4 text-muted">Họ và tên:</div>
                                <div class="col-sm-8 fw-semibold text-dark">
                                    <?= htmlspecialchars($order['fullname'] ?: ($order['user_fullname'] ?: ($order['username'] ?: 'Chưa cập nhật'))) ?>
                                </div>

                                <div class="col-sm-4 text-muted">Số điện thoại:</div>
                                <div class="col-sm-8 fw-semibold text-dark">
                                    <?= htmlspecialchars($order['phone'] ?: ($order['user_phone'] ?: 'Chưa có')) ?>
                                </div>

                                <div class="col-sm-4 text-muted">Địa chỉ nhận hàng:</div>
                                <div class="col-sm-8 text-dark">
                                    <?= nl2br(htmlspecialchars($order['shipping_address'] ?: 'Chưa cung cấp')) ?>
                                </div>

                                <div class="col-sm-4 text-muted">Tài khoản đặt hàng:</div>
                                <div class="col-sm-8">
                                    <?= !empty($order['username']) ? htmlspecialchars($order['username']) . ' (' . htmlspecialchars($order['user_email'] ?? '') . ')' : '<span class="text-muted">Khách vãng lai</span>' ?>
                                </div>

                                <div class="col-sm-4 text-muted">Thời gian đặt:</div>
                                <div class="col-sm-8 text-dark">
                                    <?= date('d/m/Y H:i:s', strtotime($order['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Update Form -->
                <div class="col-12 col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Trạng thái đơn hàng</h6>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="text-muted">Hiện tại:</span>
                                    <div><?= getStatusBadge($order['status']) ?></div>
                                </div>
                                
                                <form action="order-detail.php?id=<?= $order['id'] ?>" method="POST" class="no-print">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="id" value="<?= $order['id'] ?>">
                                    <div class="mb-3">
                                        <label for="statusSelect" class="form-label small fw-semibold text-muted">Thay đổi trạng thái:</label>
                                        <select name="status" id="statusSelect" class="form-select">
                                            <?php foreach ($valid_statuses as $st_key => $st_name): ?>
                                                <option value="<?= $st_key ?>" <?= $order['status'] === $st_key ? 'selected' : '' ?>>
                                                    <?= $st_name ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>Lưu trạng thái mới
                                    </button>
                                </form>
                            </div>

                            <div class="mt-3 pt-3 border-top text-center no-print">
                                <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fa-solid fa-print me-1"></i>In phiếu xuất kho / Hóa đơn
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Danh sách sản phẩm trong đơn</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 50px;">#</th>
                                    <th style="width: 80px;" class="no-print">Ảnh</th>
                                    <th>Tên sản phẩm</th>
                                    <th class="text-end" style="width: 160px;">Đơn giá</th>
                                    <th class="text-center" style="width: 120px;">Số lượng</th>
                                    <th class="text-end pe-3" style="width: 180px;">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($order_items)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            Không có sản phẩm nào trong chi tiết đơn hàng này.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $stt = 1;
                                    $calc_subtotal = 0;
                                    foreach ($order_items as $item): 
                                        $subtotal = $item['price'] * $item['quantity'];
                                        $calc_subtotal += $subtotal;
                                        $image_src = '../assets/images/placeholder.png';
                                        if (!empty($item['product_image'])) {
                                            $image_src = '../assets/images/products/' . $item['product_image'];
                                        }
                                    ?>
                                        <tr>
                                            <td class="ps-3 text-muted"><?= $stt++ ?></td>
                                            <td class="no-print">
                                                <img src="<?= htmlspecialchars($image_src) ?>" alt="<?= htmlspecialchars($item['product_name'] ?? '') ?>" class="item-thumb" onerror="this.src='https://placehold.co/100x100?text=No+Image';">
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">
                                                    <?= htmlspecialchars($item['product_name'] ?? ('Sản phẩm ID #' . $item['product_id'])) ?>
                                                </div>
                                                <small class="text-muted">Mã SP: #<?= $item['product_id'] ?></small>
                                            </td>
                                            <td class="text-end"><?= formatPrice($item['price']) ?></td>
                                            <td class="text-center fw-semibold"><?= $item['quantity'] ?></td>
                                            <td class="text-end pe-3 fw-bold text-dark"><?= formatPrice($subtotal) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end fw-semibold">Tổng tiền sản phẩm:</td>
                                    <td colspan="2" class="text-end pe-3 fw-semibold"><?= formatPrice($order['total_amount']) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end fw-semibold">Phí vận chuyển:</td>
                                    <td colspan="2" class="text-end pe-3 text-success fw-semibold">Miễn phí (0 đ)</td>
                                </tr>
                                <tr class="fs-5">
                                    <td colspan="4" class="text-end fw-bold text-danger">TỔNG THANH TOÁN:</td>
                                    <td colspan="2" class="text-end pe-3 fw-bold text-danger"><?= formatPrice($order['total_amount']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer Signatures for Print only -->
            <div class="d-none d-print-block mt-5 pt-4">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fw-bold">Người lập phiếu</div>
                        <div class="small text-muted mb-5">(Ký, ghi rõ họ tên)</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold">Người giao hàng</div>
                        <div class="small text-muted mb-5">(Ký, ghi rõ họ tên)</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold">Khách hàng nhận</div>
                        <div class="small text-muted mb-5">(Ký, ghi rõ họ tên)</div>
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
