<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Xóa tất cả dữ liệu trong session hiện tại
$_SESSION = [];

// Xóa session cookie trên trình duyệt nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Xóa cookie nhớ đăng nhập nếu có
if (isset($_COOKIE['remember_username'])) {
    setcookie('remember_username', '', time() - 3600, "/");
}

// Hủy phiên làm việc
session_destroy();

// Khởi động lại session mới để gửi thông báo flash tới người dùng
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (function_exists('flash')) {
    flash('success', 'Đã đăng xuất thành công!');
}

$homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';

if (function_exists('redirect')) {
    redirect($homeUrl);
} else {
    header("Location: " . $homeUrl);
}
exit;
