<?php
/**
 * Database Configuration & Connection
 * Shop Điện Tử - Dự án Website Thương Mại Điện Tử
 */

// Định nghĩa thông số kết nối Database
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'shop_dien_tu');
if (!defined('DB_PORT')) define('DB_PORT', 3306);

// Đường dẫn gốc ứng dụng (dùng cho liên kết tĩnh, asset, redirect)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/shop-dien-tu/');
}

// Bật cờ báo lỗi nội bộ an toàn (tắt hiển thị lỗi mặc định thô)
mysqli_report(MYSQLI_REPORT_OFF);

// Khởi tạo kết nối MySQLi
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Xử lý lỗi kết nối
if (!$conn) {
    $error_msg = mysqli_connect_error();
    $error_no = mysqli_connect_errno();
    
    // Giao diện thông báo lỗi thân thiện cho lập trình viên/người dùng
    die('<!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lỗi Kết Nối Cơ Sở Dữ Liệu</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-danger shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">⚠️ Không Thể Kết Nối Cơ Sở Dữ Liệu MySQL</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text text-danger"><strong>Mã lỗi:</strong> #' . htmlspecialchars((string)$error_no) . ' - ' . htmlspecialchars($error_msg) . '</p>
                            <hr>
                            <h6 class="fw-bold">Hướng dẫn khắc phục:</h6>
                            <ol class="mb-3">
                                <li>Kiểm tra phần mềm <strong>XAMPP / WampServer / Laragon</strong> và đảm bảo dịch vụ <strong>MySQL</strong> đã được bật (Running).</li>
                                <li>Kiểm tra database <code>' . htmlspecialchars(DB_NAME) . '</code> đã được tạo chưa.</li>
                                <li>Bạn có thể nhập dữ liệu bằng cách mở <code>phpMyAdmin</code> và import file <code>sql/database.sql</code>.</li>
                                <li>Kiểm tra thông số cấu hình tại file <code>config/database.php</code>.</li>
                            </ol>
                            <a href="' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '#') . '" class="btn btn-outline-danger btn-sm">Thử lại</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>');
}

// Thiết lập bảng mã utf8mb4 cho kết nối để hiển thị tiếng Việt chuẩn xác
if (!mysqli_set_charset($conn, 'utf8mb4')) {
    error_log("Lỗi cài đặt bảng mã utf8mb4: " . mysqli_error($conn));
}
