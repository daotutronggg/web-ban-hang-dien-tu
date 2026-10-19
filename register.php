<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';

// Nếu người dùng đã đăng nhập, chuyển hướng về trang chủ
if (isLoggedIn()) {
    redirect($homeUrl);
    exit;
}

$fullname = '';
$username = '';
$email = '';
$phone = '';
$address = '';
$errors = [];
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

// Xử lý gửi biểu mẫu đăng ký (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Kiểm tra các trường bắt buộc
    if (empty($fullname)) {
        $errors[] = 'Vui lòng nhập họ và tên của bạn.';
    } elseif (mb_strlen($fullname) < 2) {
        $errors[] = 'Họ và tên phải có ít nhất 2 ký tự.';
    }

    if (empty($username)) {
        $errors[] = 'Vui lòng nhập tên đăng nhập.';
    } elseif (strlen($username) < 4) {
        $errors[] = 'Tên đăng nhập phải có ít nhất 4 ký tự.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Tên đăng nhập chỉ được chứa các chữ cái, số và dấu gạch dưới (_).';
    }

    if (empty($email)) {
        $errors[] = 'Vui lòng nhập địa chỉ email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Địa chỉ email không đúng định dạng.';
    }

    if (empty($phone)) {
        $errors[] = 'Vui lòng nhập số điện thoại.';
    } elseif (!preg_match('/^[0-9]{9,11}$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ (gồm 9 đến 11 chữ số).';
    }

    if (empty($address)) {
        $errors[] = 'Vui lòng nhập địa chỉ liên hệ/giao hàng.';
    }

    if (empty($password)) {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải chứa ít nhất 6 ký tự.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Mật khẩu xác nhận không khớp với mật khẩu đã nhập.';
    }

    // 2. Kiểm tra trùng lặp Username hoặc Email trong database
    if (empty($errors)) {
        // Kiểm tra username
        $chkUserStmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $chkUserStmt->bind_param("s", $username);
        $chkUserStmt->execute();
        if ($chkUserStmt->get_result()->num_rows > 0) {
            $errors[] = 'Tên đăng nhập "' . htmlspecialchars($username) . '" đã tồn tại. Vui lòng chọn tên khác.';
        }

        // Kiểm tra email
        $chkEmailStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chkEmailStmt->bind_param("s", $email);
        $chkEmailStmt->execute();
        if ($chkEmailStmt->get_result()->num_rows > 0) {
            $errors[] = 'Địa chỉ email "' . htmlspecialchars($email) . '" đã được sử dụng cho một tài khoản khác.';
        }
    }

    // 3. Tiến hành thêm người dùng mới
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'customer';

        $insertStmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, phone, address, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $insertStmt->bind_param("sssssss", $username, $hashedPassword, $fullname, $email, $phone, $address, $role);

        if ($insertStmt->execute()) {
            $newUserId = $conn->insert_id;

            // Tự động đăng nhập sau khi đăng ký thành công
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;

            flash('success', 'Đăng ký tài khoản thành công! Chào mừng ' . htmlspecialchars($fullname) . ' đến với Shop Điện Tử.');

            if (!empty($redirect) && !str_contains($redirect, '://')) {
                redirect($redirect);
            } else {
                redirect($homeUrl);
            }
            exit;
        } else {
            $errors[] = 'Có lỗi xảy ra trong quá trình tạo tài khoản: ' . $conn->error;
        }
    }
}

$page_title = 'Đăng ký tài khoản mới';
include __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb bg-light p-2 rounded small">
                    <li class="breadcrumb-item"><a href="<?= $homeUrl ?>"><i class="bi bi-house-door"></i> Trang chủ</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Đăng ký tài khoản</li>
                </ol>
            </nav>

            <!-- Thông báo lỗi khi đăng ký -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Không thể hoàn tất đăng ký:</strong>
                    <ul class="mb-0 ps-3 mt-1">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Card Đăng ký -->
            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-primary text-white text-center py-4 rounded-top-3">
                    <div class="mb-2">
                        <i class="bi bi-person-plus-fill display-4"></i>
                    </div>
                    <h4 class="fw-bold mb-0">Đăng Ký Tài Khoản Mới</h4>
                    <small class="text-white-50">Tạo tài khoản để trải nghiệm mua sắm nhanh chóng và nhận nhiều ưu đãi</small>
                </div>
                <div class="card-body p-4 p-md-5">
                    <form action="register.php" method="POST">
                        <?php if (!empty($redirect)): ?>
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <!-- Họ và tên -->
                            <div class="col-md-6">
                                <label for="fullname" class="form-label fw-semibold">
                                    Họ và tên <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-heading text-muted"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="fullname" 
                                           name="fullname" 
                                           value="<?= htmlspecialchars($fullname) ?>" 
                                           placeholder="Nguyễn Văn A" 
                                           required>
                                </div>
                            </div>

                            <!-- Tên đăng nhập -->
                            <div class="col-md-6">
                                <label for="username" class="form-label fw-semibold">
                                    Tên đăng nhập <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge text-muted"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           value="<?= htmlspecialchars($username) ?>" 
                                           placeholder="nguyenvana (ít nhất 4 ký tự)" 
                                           required>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">
                                    Địa chỉ email <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope-fill text-muted"></i></span>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?= htmlspecialchars($email) ?>" 
                                           placeholder="example@gmail.com" 
                                           required>
                                </div>
                            </div>

                            <!-- Số điện thoại -->
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">
                                    Số điện thoại <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone-fill text-muted"></i></span>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone" 
                                           name="phone" 
                                           value="<?= htmlspecialchars($phone) ?>" 
                                           placeholder="0912345678" 
                                           required>
                                </div>
                            </div>

                            <!-- Địa chỉ giao hàng -->
                            <div class="col-12">
                                <label for="address" class="form-label fw-semibold">
                                    Địa chỉ mặc định <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt-fill text-muted"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="address" 
                                           name="address" 
                                           value="<?= htmlspecialchars($address) ?>" 
                                           placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố..." 
                                           required>
                                </div>
                            </div>

                            <!-- Mật khẩu -->
                            <div class="col-md-6">
                                <label for="password" class="form-label fw-semibold">
                                    Mật khẩu <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill text-muted"></i></span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Tối thiểu 6 ký tự..." 
                                           required>
                                </div>
                            </div>

                            <!-- Xác nhận mật khẩu -->
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label fw-semibold">
                                    Xác nhận mật khẩu <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-shield-lock-fill text-muted"></i></span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           placeholder="Nhập lại mật khẩu..." 
                                           required>
                                </div>
                            </div>
                        </div>

                        <!-- Điều khoản dịch vụ -->
                        <div class="form-check my-4">
                            <input class="form-check-input" type="checkbox" id="terms" required checked>
                            <label class="form-check-label text-muted small" for="terms">
                                Tôi đồng ý với <a href="#" class="text-decoration-none">Điều khoản sử dụng</a> và <a href="#" class="text-decoration-none">Chính sách bảo mật</a> của Shop Điện Tử.
                            </label>
                        </div>

                        <!-- Nút Đăng ký -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                <i class="bi bi-person-check-fill me-2"></i> Đăng Ký Tài Khoản
                            </button>
                        </div>

                        <!-- Liên kết Đăng nhập -->
                        <div class="text-center pt-2 border-top">
                            <span class="text-muted small">Bạn đã có tài khoản rồi?</span>
                            <a href="login.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" class="fw-bold text-decoration-none ms-1">
                                Đăng nhập ngay
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
