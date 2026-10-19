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
    if (function_exists('flash')) flash('error', 'Mã danh mục không hợp lệ!');
    redirect(BASE_URL . 'admin/categories.php');
}

// 1. Kiểm tra danh mục có tồn tại không
$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$category = $res->fetch_assoc();
$stmt->close();

if (!$category) {
    if (function_exists('flash')) flash('error', 'Không tìm thấy danh mục cần xóa!');
    redirect(BASE_URL . 'admin/categories.php');
}

// 2. Kiểm tra xem danh mục có sản phẩm nào không
$stmt_check = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id = ?");
$stmt_check->bind_param("i", $id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();
$product_count = $res_check->fetch_assoc()['total'] ?? 0;
$stmt_check->close();

if ($product_count > 0) {
    $msg = "Không thể xóa danh mục '{$category['name']}' vì đang có $product_count sản phẩm thuộc danh mục này! Vui lòng xóa hoặc đổi danh mục cho các sản phẩm trước.";
    if (function_exists('flash')) {
        flash('error', $msg);
    } else {
        $_SESSION['error'] = $msg;
    }
    redirect(BASE_URL . 'admin/categories.php');
}

// 3. Xóa danh mục
$stmt_del = $conn->prepare("DELETE FROM categories WHERE id = ?");
$stmt_del->bind_param("i", $id);
if ($stmt_del->execute()) {
    $stmt_del->close();
    $msg = "Đã xóa danh mục '{$category['name']}' thành công!";
    if (function_exists('flash')) {
        flash('success', $msg);
    } else {
        $_SESSION['success'] = $msg;
    }
} else {
    $stmt_del->close();
    $msg = "Lỗi khi xóa danh mục: " . $conn->error;
    if (function_exists('flash')) {
        flash('error', $msg);
    } else {
        $_SESSION['error'] = $msg;
    }
}

redirect(BASE_URL . 'admin/categories.php');
