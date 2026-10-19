<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Kiểm tra đăng nhập: Bắt buộc đăng nhập mới được checkout
if (!isLoggedIn()) {
    flash('error', 'Vui lòng đăng nhập để tiến hành thanh toán.');
    redirect('login.php?redirect=' . urlencode('checkout.php'));
    exit;
}

// Kiểm tra giỏ hàng: Không cho thanh toán nếu giỏ hàng rỗng
if (empty($_SESSION['cart'])) {
    flash('error', 'Giỏ hàng của bạn đang trống. Vui lòng thêm sản phẩm trước khi thanh toán.');
    redirect('products.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Lấy thông tin người dùng từ cơ sở dữ liệu để điền sẵn vào form
$userStmt = $conn->prepare("SELECT id, username, fullname, email, phone, address FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();

if (!$userData) {
    flash('error', 'Không tìm thấy thông tin tài khoản.');
    redirect('login.php');
    exit;
}

// Giá trị ban đầu cho form
$fullname = $userData['fullname'] ?? '';
$phone = $userData['phone'] ?? '';
$shipping_address = $userData['address'] ?? '';
$note = '';
$paymentMethod = 'cod';
$errorMessage = '';

// Xử lý khi người dùng nhấn đặt hàng (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cod';

    // Xác thực các trường dữ liệu
    $errors = [];
    if (empty($fullname)) {
        $errors[] = 'Vui lòng nhập họ và tên người nhận hàng.';
    }
    if (empty($phone)) {
        $errors[] = 'Vui lòng nhập số điện thoại người nhận.';
    } elseif (!preg_match('/^[0-9]{9,11}$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ (gồm 9 đến 11 chữ số).';
    }
    if (empty($shipping_address)) {
        $errors[] = 'Vui lòng nhập địa chỉ giao hàng cụ thể.';
    }

    if (empty($errors)) {
        // Bắt đầu Transaction
        $conn->begin_transaction();

        try {
            $totalAmount = 0;

            // 1. Kiểm tra tồn kho thực tế và tính tổng tiền
            foreach ($_SESSION['cart'] as $pId => $item) {
                $pId = (int)$pId;
                $chkStmt = $conn->prepare("SELECT id, name, price, quantity FROM products WHERE id = ? FOR UPDATE");
                $chkStmt->bind_param("i", $pId);
                $chkStmt->execute();
                $prod = $chkStmt->get_result()->fetch_assoc();

                if (!$prod) {
                    throw new Exception("Sản phẩm '" . htmlspecialchars($item['name']) . "' không còn tồn tại trên hệ thống.");
                }

                if ((int)$prod['quantity'] < (int)$item['quantity']) {
                    throw new Exception("Sản phẩm '" . htmlspecialchars($prod['name']) . "' chỉ còn " . $prod['quantity'] . " chiếc trong kho. Vui lòng quay lại giỏ hàng để cập nhật số lượng.");
                }

                $totalAmount += (float)$item['price'] * (int)$item['quantity'];
            }

            // 2. Tạo đơn hàng trong bảng `orders`
            $status = 'pending';
            $fullShippingInfo = $shipping_address;
            if (!empty($note)) {
                $fullShippingInfo .= ' (Ghi chú: ' . $note . ')';
            }

            $orderStmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address, phone, fullname, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $orderStmt->bind_param("idssss", $userId, $totalAmount, $status, $fullShippingInfo, $phone, $fullname);
            
            if (!$orderStmt->execute()) {
                throw new Exception("Lỗi khi tạo đơn hàng: " . $conn->error);
            }
            $orderId = $conn->insert_id;

            // 3. Thêm từng mục vào `order_details` và trừ số lượng tồn kho `products`
            $detailStmt = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stockStmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");

            foreach ($_SESSION['cart'] as $pId => $item) {
                $pId = (int)$pId;
                $itemQty = (int)$item['quantity'];
                $itemPrice = (float)$item['price'];

                // Giảm tồn kho
                $stockStmt->bind_param("iii", $itemQty, $pId, $itemQty);
                if (!$stockStmt->execute() || $stockStmt->affected_rows === 0) {
                    throw new Exception("Không thể cập nhật số lượng cho sản phẩm: " . $item['name']);
                }

                // Thêm chi tiết đơn
                $detailStmt->bind_param("iiid", $orderId, $pId, $itemQty, $itemPrice);
                if (!$detailStmt->execute()) {
                    throw new Exception("Không thể lưu chi tiết đơn hàng: " . $conn->error);
                }
            }

            // 4. Commit giao dịch thành công
            $conn->commit();

            // 5. Xóa giỏ hàng khỏi session
            unset($_SESSION['cart']);

            // 6. Đặt flash message và điều hướng về trang chủ
            flash('success', "Đặt hàng thành công! Mã đơn hàng của bạn là #$orderId. Nhân viên tư vấn sẽ liên hệ với bạn trong thời gian sớm nhất.");
            $homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';
            redirect($homeUrl);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}

// Tính tổng giá trị giỏ hàng cho hiển thị
$cartSubtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartSubtotal += (float)$item['price'] * (int)$item['quantity'];
}

$page_title = 'Thanh toán đơn hàng';
$homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item"><a href="<?= $homeUrl ?>"><i class="bi bi-house-door"></i> Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="cart.php">Giỏ hàng</a></li>
            <li class="breadcrumb-item active" aria-current="page">Thanh toán</li>
        </ol>
    </nav>

    <!-- Thông báo lỗi hoặc flash -->
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (function_exists('flash')): ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="mb-4 fw-bold text-primary">
        <i class="bi bi-credit-card me-2"></i>Thanh toán đơn hàng
    </h2>

    <form action="checkout.php" method="POST" id="checkoutForm">
        <div class="row g-4">
            <!-- Cột thông tin giao hàng (Bên trái) -->
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="card-title mb-0 fw-bold">
                            <i class="bi bi-geo-alt-fill me-2"></i>Thông tin nhận hàng
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <!-- Họ tên người nhận -->
                        <div class="mb-3">
                            <label for="fullname" class="form-label fw-semibold">
                                Họ và tên người nhận <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="fullname" 
                                   name="fullname" 
                                   value="<?= htmlspecialchars($fullname) ?>" 
                                   placeholder="Ví dụ: Nguyễn Văn A" 
                                   required>
                        </div>

                        <!-- Số điện thoại & Email -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">
                                    Số điện thoại <span class="text-danger">*</span>
                                </label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?= htmlspecialchars($phone) ?>" 
                                       placeholder="Ví dụ: 0912345678" 
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">Email tài khoản</label>
                                <input type="email" 
                                       class="form-control bg-light" 
                                       id="email" 
                                       value="<?= htmlspecialchars($userData['email'] ?? '') ?>" 
                                       readonly>
                            </div>
                        </div>

                        <!-- Địa chỉ giao hàng chi tiết -->
                        <div class="mb-3">
                            <label for="shipping_address" class="form-label fw-semibold">
                                Địa chỉ giao hàng chi tiết <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" 
                                      id="shipping_address" 
                                      name="shipping_address" 
                                      rows="3" 
                                      placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố..." 
                                      required><?= htmlspecialchars($shipping_address) ?></textarea>
                        </div>

                        <!-- Ghi chú đơn hàng -->
                        <div class="mb-3">
                            <label for="note" class="form-label fw-semibold">Ghi chú giao hàng (Tùy chọn)</label>
                            <textarea class="form-control" 
                                      id="note" 
                                      name="note" 
                                      rows="2" 
                                      placeholder="Ví dụ: Giao giờ hành chính, gọi trước khi đến..."><?= htmlspecialchars($note) ?></textarea>
                        </div>

                        <hr class="my-4">

                        <!-- Phương thức thanh toán -->
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-wallet2 me-2"></i>Phương thức thanh toán
                        </h6>
                        <div class="border rounded p-3 mb-2 bg-light">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_cod" value="cod" checked>
                                <label class="form-check-label fw-semibold" for="payment_cod">
                                    <i class="bi bi-cash-coin text-success me-1"></i> Thanh toán tiền mặt khi nhận hàng (COD)
                                </label>
                                <div class="text-muted small ps-4">
                                    Bạn chỉ thanh toán khi nhận được hàng và kiểm tra đầy đủ tem mác.
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_bank" value="bank">
                                <label class="form-check-label fw-semibold" for="payment_bank">
                                    <i class="bi bi-bank text-primary me-1"></i> Chuyển khoản ngân hàng (QR Code)
                                </label>
                                <div class="text-muted small ps-4">
                                    Hướng dẫn chuyển khoản sẽ được cung cấp ngay sau khi tạo đơn hàng thành công.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cột tóm tắt đơn hàng (Bên phải) -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px; z-index: 1020;">
                    <div class="card-header bg-light py-3">
                        <h5 class="card-title mb-0 fw-bold">
                            <i class="bi bi-bag-check me-2"></i>Đơn hàng của bạn (<?= count($_SESSION['cart']) ?> món)
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <!-- Danh sách sản phẩm -->
                        <div class="overflow-auto pe-1 mb-3" style="max-height: 280px;">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <?php
                                    $itemImg = !empty($item['image']) 
                                        ? (defined('BASE_URL') ? BASE_URL : '..') . '/assets/images/products/' . htmlspecialchars($item['image'])
                                        : 'https://placehold.co/80x80?text=SP';
                                    $itemLine = (float)$item['price'] * (int)$item['quantity'];
                                ?>
                                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                    <img src="<?= $itemImg ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                         class="rounded border me-3 flex-shrink-0" 
                                         style="width: 55px; height: 55px; object-fit: contain;"
                                         onerror="this.src='https://placehold.co/80x80?text=SP';">
                                    <div class="flex-grow-1 min-w-0">
                                        <h6 class="mb-1 text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($item['name']) ?>">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </h6>
                                        <div class="small text-muted">
                                            <?= (int)$item['quantity'] ?> × <?= formatPrice($item['price']) ?>
                                        </div>
                                    </div>
                                    <div class="fw-bold text-end ps-2 text-danger">
                                        <?= formatPrice($itemLine) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Tính tiền -->
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tạm tính:</span>
                            <span class="fw-semibold"><?= formatPrice($cartSubtotal) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Phí giao hàng:</span>
                            <span class="text-success fw-semibold">Miễn phí toàn quốc</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center my-3">
                            <span class="fs-5 fw-bold">Tổng thanh toán:</span>
                            <span class="fs-3 fw-bold text-danger"><?= formatPrice($cartSubtotal) ?></span>
                        </div>

                        <!-- Nút hoàn tất đặt hàng -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-lg py-3 fw-bold shadow-sm">
                                <i class="bi bi-check2-circle me-2"></i> Xác nhận đặt hàng
                            </button>
                            <a href="cart.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Quay lại sửa giỏ hàng
                            </a>
                        </div>

                        <!-- Cam kết bảo mật -->
                        <div class="alert alert-light border mt-3 mb-0 p-2 text-center small text-muted">
                            <i class="bi bi-shield-lock-fill text-success me-1"></i>
                            Thông tin đặt hàng của bạn được bảo mật tuyệt đối theo tiêu chuẩn SSL 256-bit.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
