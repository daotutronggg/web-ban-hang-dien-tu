<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';
$adminUrl = defined('BASE_URL') ? BASE_URL . '/admin/index.php' : '../admin/index.php';

// Nếu người dùng đã đăng nhập, chuyển hướng ngay về trang tương ứng
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect($adminUrl);
    } else {
        redirect($homeUrl);
    }
    exit;
}

$username = '';
$errorMessage = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

// Xử lý đăng nhập khi submit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $errorMessage = 'Vui lòng nhập đầy đủ tên đăng nhập (hoặc email) và mật khẩu.';
    } else {
        // Tìm người dùng theo username hoặc email bằng prepared statement
        $stmt = $conn->prepare("SELECT id, username, password, fullname, email, phone, role FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Thiết lập các biến phiên đăng nhập (session)
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Nếu người dùng chọn "Ghi nhớ đăng nhập", lưu cookie 30 ngày (tuỳ chọn)
            if ($remember) {
                setcookie('remember_username', $user['username'], time() + (86400 * 30), "/");
            } else {
                if (isset($_COOKIE['remember_username'])) {
                    setcookie('remember_username', '', time() - 3600, "/");
                }
            }

            flash('success', 'Đăng nhập thành công! Chào mừng bạn, ' . htmlspecialchars($user['fullname']) . '.');

            // Phân quyền chuyển hướng
            if ($user['role'] === 'admin') {
                redirect($adminUrl);
            } else {
                // Nếu có trang redirect hợp lệ trước đó (ví dụ từ checkout.php)
                if (!empty($redirect) && !str_contains($redirect, '://')) {
                    redirect($redirect);
                } else {
                    redirect($homeUrl);
                }
            }
            exit;
        } else {
            $errorMessage = 'Tên đăng nhập (email) hoặc mật khẩu không chính xác.';
        }
    }
} else {
    // Nếu có cookie nhớ tên đăng nhập thì điền sẵn
    if (isset($_COOKIE['remember_username'])) {
        $username = $_COOKIE['remember_username'];
    }
}

$page_title = 'Đăng nhập tài khoản';
include __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb bg-light p-2 rounded small">
                    <li class="breadcrumb-item"><a href="<?= $homeUrl ?>"><i class="bi bi-house-door"></i> Trang chủ</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Đăng nhập</li>
                </ol>
            </nav>

            <!-- Thông báo Flash nếu có -->
            <?php if (function_exists('flash')): ?>
                <?php if ($msg = flash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?= $msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($msg = flash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Thông báo lỗi khi đăng nhập không thành công -->
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i><?= $errorMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Card Đăng nhập -->
            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-primary text-white text-center py-4 rounded-top-3">
                    <div class="mb-2">
                        <i class="bi bi-person-circle display-4"></i>
                    </div>
                    <h4 class="fw-bold mb-0">Đăng Nhập Tài Khoản</h4>
                    <small class="text-white-50">Nhập thông tin của bạn để tiếp tục</small>
                </div>
                <div class="card-body p-4">
                    <form action="login.php" method="POST">
                        <?php if (!empty($redirect)): ?>
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                        <?php endif; ?>

                        <!-- Username hoặc Email -->
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">
                                Tên đăng nhập hoặc Email <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-fill text-muted"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       value="<?= htmlspecialchars($username) ?>" 
                                       placeholder="Username hoặc email..." 
                                       required 
                                       autofocus>
                            </div>
                        </div>

                        <!-- Mật khẩu -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">
                                Mật khẩu <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill text-muted"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Nhập mật khẩu của bạn..." 
                                       required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Ghi nhớ đăng nhập -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" <?= isset($_COOKIE['remember_username']) ? 'checked' : '' ?>>
                                <label class="form-check-label text-muted small" for="remember">
                                    Ghi nhớ đăng nhập
                                </label>
                            </div>
                            <div>
                                <a href="contact.php" class="text-decoration-none small text-primary">Quên mật khẩu?</a>
                            </div>
                        </div>

                        <!-- Nút Đăng nhập -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Đăng Nhập
                            </button>
                        </div>

                        <!-- Liên kết sang Đăng ký -->
                        <div class="text-center pt-2 border-top">
                            <span class="text-muted small">Bạn chưa có tài khoản?</span>
                            <a href="register.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" class="fw-bold text-decoration-none ms-1">
                                Đăng ký ngay
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    if (!passwordInput || !toggleIcon) return;
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
