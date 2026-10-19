<?php
/**
 * Utility Functions
 * Shop Điện Tử - Hệ thống tiện ích toàn trang
 */

/**
 * Định dạng giá tiền theo chuẩn Việt Nam Đồng (VND)
 * Ví dụ: 29990000 -> "29.990.000 đ"
 *
 * @param float|int|string|null $price
 * @return string
 */
function formatPrice($price): string {
    $numericPrice = is_numeric($price) ? (float)$price : 0.0;
    return number_format($numericPrice, 0, ',', '.') . ' đ';
}

/**
 * Kiểm tra người dùng hiện tại đã đăng nhập hay chưa
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Kiểm tra người dùng hiện tại có quyền Quản trị viên (Admin) hay không
 *
 * @return bool
 */
function isAdmin(): bool {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Chuyển hướng người dùng sang URL khác và kết thúc thực thi kịch bản
 * Tự động gắn BASE_URL nếu URL truyền vào là đường dẫn tương đối nội bộ
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void {
    // Nếu URL không bắt đầu bằng http://, https:// hoặc dấu /, nối với BASE_URL
    if (!preg_match('/^(?:https?:\/\/|\/)/i', $url)) {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/shop-dien-tu/';
        $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }
    header("Location: " . $url);
    exit();
}

/**
 * Làm sạch dữ liệu đầu vào chống tấn công XSS và SQL Injection
 *
 * @param mixed $data
 * @return mixed
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(stripslashes(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Lấy tổng số lượng sản phẩm có trong giỏ hàng hiện tại
 * Giỏ hàng được lưu trong $_SESSION['cart'] theo định dạng:
 * [ product_id => quantity, ... ] hoặc [ product_id => ['quantity' => qty, ...], ... ]
 *
 * @return int
 */
function getCartCount(): int {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return 0;
    }
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        if (is_array($item) && isset($item['quantity'])) {
            $total += max(0, (int)$item['quantity']);
        } elseif (is_numeric($item)) {
            $total += max(0, (int)$item);
        }
    }
    return $total;
}

/**
 * Quản lý Flash Message (Thông báo một lần)
 * - Nếu truyền cả $key và $message: Lưu thông báo vào Session
 * - Nếu chỉ truyền $key: Lấy ra thông báo, xóa khỏi Session và trả về chuỗi
 *
 * @param string $key Tên loại thông báo (success, danger, error, warning, info)
 * @param string $message Nội dung thông báo
 * @return string|null
 */
function flash(string $key, string $message = ''): ?string {
    if ($message !== '') {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    // Mapping 'error' thành 'danger' để tương thích Bootstrap 5
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    if ($key === 'danger' && isset($_SESSION['flash']['error'])) {
        $msg = $_SESSION['flash']['error'];
        unset($_SESSION['flash']['error']);
        return $msg;
    }

    if ($key === 'error' && isset($_SESSION['flash']['danger'])) {
        $msg = $_SESSION['flash']['danger'];
        unset($_SESSION['flash']['danger']);
        return $msg;
    }

    return null;
}

/**
 * Lấy danh sách tất cả danh mục sản phẩm từ CSDL
 *
 * @param mysqli $conn
 * @return array
 */
function getCategories($conn): array {
    $categories = [];
    if (!$conn) {
        return $categories;
    }

    $sql = "SELECT id, name, description, created_at FROM categories ORDER BY id ASC";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }
        mysqli_free_result($result);
    }
    return $categories;
}

/**
 * Hiển thị toàn bộ các thông báo flash message thành các khối alert của Bootstrap 5
 *
 * @return void
 */
function displayFlashMessages(): void {
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return;
    }

    $types = [
        'success' => ['class' => 'alert-success', 'icon' => 'fa-circle-check'],
        'danger'  => ['class' => 'alert-danger',  'icon' => 'fa-circle-exclamation'],
        'error'   => ['class' => 'alert-danger',  'icon' => 'fa-circle-exclamation'],
        'warning' => ['class' => 'alert-warning', 'icon' => 'fa-triangle-exclamation'],
        'info'    => ['class' => 'alert-info',    'icon' => 'fa-circle-info'],
    ];

    echo '<div class="flash-messages-container mb-4">';
    foreach ($_SESSION['flash'] as $key => $message) {
        $type = $types[$key] ?? ['class' => 'alert-primary', 'icon' => 'fa-bell'];
        $alertClass = $type['class'];
        $iconClass = $type['icon'];
        $cleanMessage = htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8');

        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">';
        echo '  <i class="fa-solid ' . $iconClass . ' me-2 fs-5"></i>';
        echo '  <div class="flex-grow-1">' . $cleanMessage . '</div>';
        echo '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    echo '</div>';

    // Xóa hết flash sau khi đã hiển thị
    unset($_SESSION['flash']);
}

/**
 * Lấy đường dẫn hình ảnh sản phẩm hợp lệ, nếu không có trả về ảnh placeholder SVG chuẩn
 *
 * @param string|null $image
 * @return string
 */
function getProductImage(?string $image): string {
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/shop-dien-tu/';
    if (!empty($image)) {
        // Kiểm tra đường dẫn ảnh cục bộ
        $localPath = __DIR__ . '/../assets/images/products/' . $image;
        if (file_exists($localPath)) {
            return $baseUrl . 'assets/images/products/' . htmlspecialchars($image);
        }
    }
    // Trả về ảnh mặc định SVG nếu file chưa tồn tại trên đĩa
    return $baseUrl . 'assets/images/placeholder.svg';
}

/**
 * Lấy thông tin người dùng hiện tại đang đăng nhập
 *
 * @param mysqli $conn
 * @return array|null
 */
function getCurrentUser($conn): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    $userId = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, username, fullname, email, phone, address, role, created_at FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $user ?: null;
}
