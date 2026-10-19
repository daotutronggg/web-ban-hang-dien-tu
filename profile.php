<?php
/**
 * Thông tin tài khoản (User Profile)
 * Shop Điện Tử - Cập nhật thông tin cá nhân
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    flash('warning', 'Vui lòng đăng nhập để xem thông tin tài khoản.');
    redirect('login.php?redirect=' . urlencode('profile.php'));
}

$userId = (int)$_SESSION['user_id'];
$user = getCurrentUser($conn);

if (!$user) {
    flash('danger', 'Không tìm thấy thông tin tài khoản.');
    redirect('login.php');
}

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $address  = sanitize($_POST['address'] ?? '');
    $password = $_POST['new_password'] ?? '';

    $errors = [];
    if (empty($fullname)) {
        $errors[] = 'Họ và tên không được để trống.';
    }

    if (!empty($phone) && !preg_match('/^(03|05|07|08|09)\d{8}$/', $phone)) {
        $errors[] = 'Số điện thoại không đúng định dạng (10 số, ví dụ 0912345678).';
    }

    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    }

    if (empty($errors)) {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, phone = ?, address = ?, password = ? WHERE id = ?");
            mysqli_stmt_bind_param($updateStmt, "ssssi", $fullname, $phone, $address, $hashedPassword, $userId);
        } else {
            $updateStmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, phone = ?, address = ? WHERE id = ?");
            mysqli_stmt_bind_param($updateStmt, "sssi", $fullname, $phone, $address, $userId);
        }

        if (mysqli_stmt_execute($updateStmt)) {
            $_SESSION['fullname'] = $fullname;
            flash('success', 'Cập nhật thông tin tài khoản thành công!');
            mysqli_stmt_close($updateStmt);
            redirect('profile.php');
        } else {
            $errors[] = 'Đã có lỗi xảy ra trong quá trình cập nhật. Vui lòng thử lại.';
            mysqli_stmt_close($updateStmt);
        }
    }

    if (!empty($errors)) {
        foreach ($errors as $err) {
            flash('danger', $err);
        }
    }
}

$page_title = 'Thông Tin Tài Khoản';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="<?php echo $baseUrl; ?>">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Thông tin tài khoản</li>
        </ol>
    </nav>

    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 text-center bg-primary text-white">
                    <div class="mb-3">
                        <i class="fa-solid fa-circle-user fs-1 text-white-50"></i>
                    </div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['fullname']); ?></h5>
                    <p class="small text-white-50 mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                <div class="list-group list-group-flush">
                    <a href="<?php echo $baseUrl; ?>orders.php" class="list-group-item list-group-item-action py-3">
                        <i class="fa-solid fa-receipt me-2 text-muted"></i> Lịch sử đơn hàng
                    </a>
                    <a href="<?php echo $baseUrl; ?>profile.php" class="list-group-item list-group-item-action active fw-medium py-3">
                        <i class="fa-solid fa-id-card me-2"></i> Thông tin tài khoản
                    </a>
                    <a href="<?php echo $baseUrl; ?>logout.php" class="list-group-item list-group-item-action text-danger py-3">
                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Đăng xuất
                    </a>
                </div>
            </div>
        </div>

        <!-- Profile Edit Form -->
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-user-pen text-primary me-2"></i>Cập Nhật Thông Tin Cá Nhân
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?php echo $baseUrl; ?>profile.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Tên đăng nhập</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <div class="form-text">Tên đăng nhập không thể thay đổi.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Địa chỉ Email</label>
                                <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <div class="form-text">Email gắn liền với định danh tài khoản.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Số điện thoại</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="0912345678">
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Địa chỉ nhận hàng mặc định</label>
                                <textarea name="address" class="form-control" rows="2" placeholder="Số nhà, tên đường, phường/xã, quận/huyện..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="col-12"><hr class="my-3"></div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Đổi mật khẩu mới</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Để trống nếu không muốn đổi">
                                <div class="form-text">Tối thiểu 6 ký tự.</div>
                            </div>

                            <div class="col-12 mt-4 text-end">
                                <button type="submit" class="btn btn-primary px-4 rounded-pill">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Lưu thay đổi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';
?>
