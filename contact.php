<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Khởi tạo các biến form
$fullname = '';
$email = '';
$subject = '';
$content = '';
$errors = [];

// Điền sẵn thông tin nếu người dùng đã đăng nhập
if (isLoggedIn() && isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    if ($userData) {
        $fullname = $userData['fullname'] ?? '';
        $email = $userData['email'] ?? '';
    }
}

// Xử lý gửi biểu mẫu liên hệ qua POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // Kiểm tra tính hợp lệ của dữ liệu nhập
    if (empty($fullname)) {
        $errors[] = 'Vui lòng nhập họ và tên của bạn.';
    } elseif (mb_strlen($fullname) < 2) {
        $errors[] = 'Họ và tên phải có ít nhất 2 ký tự.';
    }

    if (empty($email)) {
        $errors[] = 'Vui lòng nhập địa chỉ email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Địa chỉ email không đúng định dạng hợp lệ.';
    }

    if (empty($subject)) {
        $errors[] = 'Vui lòng nhập tiêu đề liên hệ.';
    }

    if (empty($content)) {
        $errors[] = 'Vui lòng nhập nội dung tin nhắn liên hệ.';
    } elseif (mb_strlen($content) < 10) {
        $errors[] = 'Nội dung tin nhắn cần chi tiết hơn (tối thiểu 10 ký tự).';
    }

    // Nếu không có lỗi, thông báo thành công và chuyển hướng
    if (empty($errors)) {
        flash('success', 'Cảm ơn bạn đã gửi liên hệ! Thông điệp của bạn đã được chuyển tới bộ phận chăm sóc khách hàng. Chúng tôi sẽ phản hồi trong vòng 24 giờ làm việc.');
        redirect('contact.php');
        exit;
    }
}

$page_title = 'Liên hệ với chúng tôi';
$homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item"><a href="<?= $homeUrl ?>"><i class="bi bi-house-door"></i> Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Liên hệ</li>
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
    <?php endif; ?>

    <!-- Thông báo lỗi khi submit form -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Tiêu đề trang -->
    <div class="text-center mb-5">
        <h2 class="fw-bold text-primary">Liên Hệ Với Chúng Tôi</h2>
        <p class="text-muted">Chúng tôi luôn sẵn sàng lắng nghe ý kiến đóng góp và hỗ trợ giải đáp mọi thắc mắc của bạn.</p>
    </div>

    <div class="row g-4 mb-5">
        <!-- Thông tin liên hệ (Bên trái) -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-info-circle-fill me-2"></i>Thông tin liên hệ
                    </h5>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="mb-4">
                        <!-- Địa chỉ -->
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-geo-alt-fill fs-4"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h6 class="fw-bold mb-1">Địa chỉ cửa hàng</h6>
                                <p class="text-muted mb-0">123 Đường Điện Biên Phủ, Phường 15, Quận Bình Thạnh, TP. Hồ Chí Minh</p>
                            </div>
                        </div>

                        <!-- Điện thoại -->
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-success-subtle text-success p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-telephone-fill fs-4"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h6 class="fw-bold mb-1">Hotline tư vấn & hỗ trợ</h6>
                                <p class="text-muted mb-0">
                                    <a href="tel:0988123456" class="text-decoration-none text-dark fw-semibold">0988 123 456</a><br>
                                    <a href="tel:19006868" class="text-decoration-none text-dark fw-semibold">1900 6868</a> (1.000đ/phút)
                                </p>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-warning-subtle text-warning p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-envelope-fill fs-4"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h6 class="fw-bold mb-1">Email hỗ trợ khách hàng</h6>
                                <p class="text-muted mb-0">
                                    <a href="mailto:support@shopdientu.vn" class="text-decoration-none text-dark">support@shopdientu.vn</a><br>
                                    <a href="mailto:kinhdoanh@shopdientu.vn" class="text-decoration-none text-dark">kinhdoanh@shopdientu.vn</a>
                                </p>
                            </div>
                        </div>

                        <!-- Giờ làm việc -->
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-info-subtle text-info p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-clock-fill fs-4"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h6 class="fw-bold mb-1">Thời gian làm việc</h6>
                                <p class="text-muted mb-0">Thứ 2 - Chủ Nhật: 08:00 - 21:30<br><span class="badge bg-success-subtle text-success">Mở cửa cả ngày Lễ & Tết</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Kênh kết nối mạng xã hội -->
                    <div class="pt-3 border-top">
                        <h6 class="fw-bold mb-2">Kết nối với chúng tôi</h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-primary btn-sm rounded-circle" style="width: 36px; height: 36px;"><i class="bi bi-facebook"></i></a>
                            <a href="#" class="btn btn-outline-danger btn-sm rounded-circle" style="width: 36px; height: 36px;"><i class="bi bi-youtube"></i></a>
                            <a href="#" class="btn btn-outline-info btn-sm rounded-circle" style="width: 36px; height: 36px;"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="btn btn-outline-dark btn-sm rounded-circle" style="width: 36px; height: 36px;"><i class="bi bi-tiktok"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biểu mẫu gửi liên hệ (Bên phải) -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-chat-left-dots-fill me-2"></i>Gửi tin nhắn cho chúng tôi
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="contact.php" method="POST">
                        <div class="row g-3 mb-3">
                            <!-- Họ tên -->
                            <div class="col-md-6">
                                <label for="fullname" class="form-label fw-semibold">
                                    Họ và tên của bạn <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="fullname" 
                                           name="fullname" 
                                           value="<?= htmlspecialchars($fullname) ?>" 
                                           placeholder="Nguyễn Văn A" 
                                           required>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">
                                    Địa chỉ email <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?= htmlspecialchars($email) ?>" 
                                           placeholder="example@gmail.com" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <!-- Tiêu đề -->
                        <div class="mb-3">
                            <label for="subject" class="form-label fw-semibold">
                                Tiêu đề thư <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-chat-square-text"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="subject" 
                                       name="subject" 
                                       value="<?= htmlspecialchars($subject) ?>" 
                                       placeholder="Ví dụ: Cần tư vấn thông số kỹ thuật điện thoại..." 
                                       required>
                            </div>
                        </div>

                        <!-- Nội dung tin nhắn -->
                        <div class="mb-4">
                            <label for="content" class="form-label fw-semibold">
                                Nội dung chi tiết <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" 
                                      id="content" 
                                      name="content" 
                                      rows="5" 
                                      placeholder="Hãy mô tả chi tiết thắc mắc hoặc yêu cầu hỗ trợ của bạn tại đây..." 
                                      required><?= htmlspecialchars($content) ?></textarea>
                            <div class="form-text">Tối thiểu 10 ký tự.</div>
                        </div>

                        <!-- Nút gửi -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                <i class="bi bi-send-fill me-2"></i> Gửi thông tin liên hệ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bản đồ Google Maps nhúng -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fw-bold text-primary">
                <i class="bi bi-map-fill me-2"></i>Bản đồ vị trí cửa hàng
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="ratio ratio-21x9" style="min-height: 380px;">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.231241324796!2d106.70234057570341!3d10.793592889356394!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x317528b4923e4d9b%3A0x6b8017d23f773b0a!2zxJBp4buHbiBCacOqbiBQaOG7pywgQsOsbmggVGjhuqFuaCwgSOG7kyBDaMOtIE1pbmgsIFZp4buHdCBOYW0!5e0!3m2!1svi!2s!4v1700000000000!5m2!1svi!2s" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
