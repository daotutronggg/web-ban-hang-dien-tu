<?php
/**
 * Đơn hàng của tôi (My Orders)
 * Shop Điện Tử - Lịch sử đơn hàng khách hàng
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    flash('warning', 'Vui lòng đăng nhập để xem lịch sử đơn hàng của bạn.');
    redirect('login.php?redirect=' . urlencode('orders.php'));
}

$userId = (int)$_SESSION['user_id'];
$orders = [];

// Truy vấn danh sách đơn hàng của người dùng kèm số lượng mặt hàng
$query = "SELECT o.id, o.total_amount, o.status, o.shipping_address, o.phone, o.fullname, o.created_at,
                 COUNT(od.id) AS total_items
          FROM orders o
          LEFT JOIN order_details od ON o.id = od.order_id
          WHERE o.user_id = ?
          GROUP BY o.id
          ORDER BY o.created_at DESC";

if ($stmt = mysqli_prepare($conn, $query)) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Hàm format trạng thái đơn hàng
function getOrderStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge badge-pending px-3 py-2 rounded-pill"><i class="fa-solid fa-clock me-1"></i> Chờ xác nhận</span>';
        case 'confirmed':
            return '<span class="badge badge-confirmed px-3 py-2 rounded-pill"><i class="fa-solid fa-check me-1"></i> Đã xác nhận</span>';
        case 'shipping':
            return '<span class="badge badge-shipping px-3 py-2 rounded-pill"><i class="fa-solid fa-truck-fast me-1"></i> Đang giao hàng</span>';
        case 'completed':
            return '<span class="badge badge-completed px-3 py-2 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i> Hoàn thành</span>';
        case 'cancelled':
            return '<span class="badge badge-cancelled px-3 py-2 rounded-pill"><i class="fa-solid fa-circle-xmark me-1"></i> Đã hủy</span>';
        default:
            return '<span class="badge bg-secondary px-3 py-2 rounded-pill">' . htmlspecialchars($status) . '</span>';
    }
}

$page_title = 'Đơn Hàng Của Tôi';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="<?php echo $baseUrl; ?>">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Đơn hàng của tôi</li>
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
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username']); ?></h5>
                    <p class="small text-white-50 mb-0"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
                </div>
                <div class="list-group list-group-flush">
                    <a href="<?php echo $baseUrl; ?>orders.php" class="list-group-item list-group-item-action active fw-medium py-3">
                        <i class="fa-solid fa-receipt me-2"></i> Lịch sử đơn hàng
                    </a>
                    <a href="<?php echo $baseUrl; ?>profile.php" class="list-group-item list-group-item-action py-3">
                        <i class="fa-solid fa-id-card me-2 text-muted"></i> Thông tin tài khoản
                    </a>
                    <a href="<?php echo $baseUrl; ?>logout.php" class="list-group-item list-group-item-action text-danger py-3">
                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Đăng xuất
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Orders List -->
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-box-archive text-primary me-2"></i>Danh Sách Đơn Hàng Của Bạn
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-uppercase text-muted">
                                    <tr>
                                        <th class="ps-4">Mã Đơn</th>
                                        <th>Ngày Đặt</th>
                                        <th>Số Lượng</th>
                                        <th>Tổng Tiền</th>
                                        <th>Trạng Thái</th>
                                        <th class="text-end pe-4">Thao Tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-primary">
                                                #<?php echo str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT); ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo (int)$order['total_items']; ?> sản phẩm
                                                </span>
                                            </td>
                                            <td class="fw-bold text-danger">
                                                <?php echo formatPrice($order['total_amount']); ?>
                                            </td>
                                            <td>
                                                <?php echo getOrderStatusBadge($order['status']); ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#orderDetail<?php echo $order['id']; ?>" aria-expanded="false">
                                                    Chi tiết <i class="fa-solid fa-chevron-down ms-1 small"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <!-- Collapsible Order Details Row -->
                                        <tr class="collapse bg-light" id="orderDetail<?php echo $order['id']; ?>">
                                            <td colspan="6" class="p-4">
                                                <div class="bg-white p-3 rounded-3 border">
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6 small">
                                                            <strong>Người nhận:</strong> <?php echo htmlspecialchars($order['fullname']); ?><br>
                                                            <strong>Điện thoại:</strong> <?php echo htmlspecialchars($order['phone']); ?><br>
                                                            <strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?>
                                                        </div>
                                                        <div class="col-md-6 small text-md-end">
                                                            <strong>Mã tra cứu:</strong> DH-<?php echo $order['id']; ?><br>
                                                            <strong>Thời gian:</strong> <?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?>
                                                        </div>
                                                    </div>

                                                    <?php
                                                    // Lấy chi tiết các sản phẩm trong đơn
                                                    $detailStmt = mysqli_prepare($conn, "SELECT od.quantity, od.price, p.name, p.image 
                                                                                         FROM order_details od 
                                                                                         LEFT JOIN products p ON od.product_id = p.id 
                                                                                         WHERE od.order_id = ?");
                                                    if ($detailStmt) {
                                                        mysqli_stmt_bind_param($detailStmt, "i", $order['id']);
                                                        mysqli_stmt_execute($detailStmt);
                                                        $detRes = mysqli_stmt_get_result($detailStmt);
                                                        ?>
                                                        <table class="table table-sm align-middle mb-0">
                                                            <thead class="table-light small">
                                                                <tr>
                                                                    <th>Sản phẩm</th>
                                                                    <th class="text-center">Số lượng</th>
                                                                    <th class="text-end">Đơn giá</th>
                                                                    <th class="text-end">Thành tiền</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php while ($item = mysqli_fetch_assoc($detRes)): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($item['name'] ?? 'Sản phẩm đã xóa'); ?></td>
                                                                        <td class="text-center"><?php echo (int)$item['quantity']; ?></td>
                                                                        <td class="text-end"><?php echo formatPrice($item['price']); ?></td>
                                                                        <td class="text-end fw-bold"><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                                                                    </tr>
                                                                <?php endwhile; ?>
                                                            </tbody>
                                                        </table>
                                                        <?php
                                                        mysqli_stmt_close($detailStmt);
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-clipboard-list text-muted fs-1 mb-3"></i>
                            <h5 class="text-secondary">Bạn chưa có đơn hàng nào</h5>
                            <p class="text-muted small mb-3">Hãy dạo quanh cửa hàng và chọn cho mình sản phẩm ưng ý nhất!</p>
                            <a href="<?php echo $baseUrl; ?>products.php" class="btn btn-primary btn-sm rounded-pill px-4">
                                Khám phá sản phẩm
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';
?>
