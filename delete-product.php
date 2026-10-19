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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    if (function_exists('flash')) flash('error', 'Mã sản phẩm không hợp lệ!');
    redirect(BASE_URL . 'admin/products.php');
}

// 1. Kiểm tra sản phẩm có tồn tại không
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$product = $res->fetch_assoc();
$stmt->close();

if (!$product) {
    if (function_exists('flash')) flash('error', 'Không tìm thấy sản phẩm cần xóa!');
    redirect(BASE_URL . 'admin/products.php');
}

// 2. Kiểm tra ràng buộc khóa ngoại: Sản phẩm có trong đơn hàng nào không?
$stmt_check = $conn->prepare("SELECT COUNT(*) AS total FROM order_details WHERE product_id = ?");
$stmt_check->bind_param("i", $id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();
$order_count = $res_check->fetch_assoc()['total'] ?? 0;
$stmt_check->close();

if ($order_count > 0) {
    $msg = "Không thể xóa sản phẩm '{$product['name']}' vì đã tồn tại trong $order_count đơn hàng. Bạn có thể cập nhật số lượng về 0 thay vì xóa!";
    if (function_exists('flash')) {
        flash('error', $msg);
    } else {
        $_SESSION['error'] = $msg;
    }
    redirect(BASE_URL . 'admin/products.php');
}

// 3. Xóa tệp hình ảnh nếu có
if (!empty($product['image'])) {
    $img_path = __DIR__ . '/../assets/images/products/' . $product['image'];
    if (file_exists($img_path) && is_file($img_path)) {
        @unlink($img_path);
    }
}

// 4. Xóa sản phẩm khỏi bảng products
$stmt_del = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt_del->bind_param("i", $id);
if ($stmt_del->execute()) {
    $stmt_del->close();
    $msg = "Đã xóa sản phẩm '{$product['name']}' thành công!";
    if (function_exists('flash')) {
        flash('success', $msg);
    } else {
        $_SESSION['success'] = $msg;
    }
} else {
    $stmt_del->close();
    $msg = "Lỗi khi xóa sản phẩm: " . $conn->error;
    if (function_exists('flash')) {
        flash('error', $msg);
    } else {
        $_SESSION['error'] = $msg;
    }
}

redirect(BASE_URL . 'admin/products.php');
