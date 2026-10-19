<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Xử lý các thao tác giỏ hàng qua POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Cập nhật số lượng giỏ hàng
    if ($action === 'update_cart') {
        $quantities = $_POST['quantities'] ?? [];
        $hasErrors = false;

        if (is_array($quantities)) {
            foreach ($quantities as $pId => $qty) {
                $pId = (int)$pId;
                $qty = (int)$qty;

                if (!isset($_SESSION['cart'][$pId])) {
                    continue;
                }

                if ($qty <= 0) {
                    unset($_SESSION['cart'][$pId]);
                } else {
                    // Kiểm tra số lượng tồn kho thực tế từ database
                    $stmt = $conn->prepare("SELECT name, quantity FROM products WHERE id = ?");
                    $stmt->bind_param("i", $pId);
                    $stmt->execute();
                    $prod = $stmt->get_result()->fetch_assoc();

                    if ($prod) {
                        $stock = (int)$prod['quantity'];
                        if ($qty > $stock) {
                            $_SESSION['cart'][$pId]['quantity'] = $stock;
                            flash('error', 'Sản phẩm "' . htmlspecialchars($prod['name']) . '" chỉ còn ' . $stock . ' trong kho.');
                            $hasErrors = true;
                        } else {
                            $_SESSION['cart'][$pId]['quantity'] = $qty;
                        }
                    } else {
                        // Sản phẩm không còn tồn tại trong DB
                        unset($_SESSION['cart'][$pId]);
                    }
                }
            }
        }

        if (!$hasErrors) {
            flash('success', 'Giỏ hàng đã được cập nhật thành công!');
        }
        redirect('cart.php');
        exit;
    }

    // 2. Xóa 1 sản phẩm khỏi giỏ hàng
    if ($action === 'remove_item') {
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        if (isset($_SESSION['cart'][$productId])) {
            $removedName = $_SESSION['cart'][$productId]['name'] ?? 'Sản phẩm';
            unset($_SESSION['cart'][$productId]);
            flash('success', 'Đã xóa "' . htmlspecialchars($removedName) . '" khỏi giỏ hàng.');
        }
        redirect('cart.php');
        exit;
    }

    // 3. Xóa toàn bộ giỏ hàng
    if ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        flash('success', 'Đã xóa toàn bộ giỏ hàng.');
        redirect('cart.php');
        exit;
    }

    // 4. Thêm sản phẩm vào giỏ hàng (nếu form khác POST tới cart.php)
    if ($action === 'add') {
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $qty = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

        if ($productId > 0) {
            $stmt = $conn->prepare("SELECT id, name, price, quantity, image FROM products WHERE id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $prod = $stmt->get_result()->fetch_assoc();

            if ($prod) {
                if ((int)$prod['quantity'] <= 0) {
                    flash('error', 'Sản phẩm hiện đang hết hàng.');
                } else {
                    $currentQty = isset($_SESSION['cart'][$productId]) ? (int)$_SESSION['cart'][$productId]['quantity'] : 0;
                    $newQty = $currentQty + $qty;
                    if ($newQty > (int)$prod['quantity']) {
                        flash('error', 'Số lượng vượt quá tồn kho (tối đa ' . $prod['quantity'] . ').');
                    } else {
                        $_SESSION['cart'][$productId] = [
                            'id' => (int)$prod['id'],
                            'name' => $prod['name'],
                            'price' => (float)$prod['price'],
                            'image' => $prod['image'],
                            'quantity' => $newQty
                        ];
                        flash('success', 'Đã thêm "' . htmlspecialchars($prod['name']) . '" vào giỏ hàng!');
                    }
                }
            } else {
                flash('error', 'Sản phẩm không tồn tại.');
            }
        }
        redirect('cart.php');
        exit;
    }
}

// Tính tổng tiền giỏ hàng
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
}
$shippingFee = 0; // Miễn phí vận chuyển
$total = $subtotal + $shippingFee;

$page_title = 'Giỏ hàng của bạn';
$homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item"><a href="<?= $homeUrl ?>"><i class="bi bi-house-door"></i> Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="products.php">Sản phẩm</a></li>
            <li class="breadcrumb-item active" aria-current="page">Giỏ hàng</li>
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

    <h2 class="mb-4 fw-bold text-primary">
        <i class="bi bi-cart3 me-2"></i>Giỏ hàng của bạn
        <?php if (!empty($_SESSION['cart'])): ?>
            <span class="fs-6 fw-normal text-muted">(<?= count($_SESSION['cart']) ?> loại sản phẩm)</span>
        <?php endif; ?>
    </h2>

    <?php if (empty($_SESSION['cart'])): ?>
        <!-- Hiển thị khi giỏ hàng trống -->
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">
                <div class="mb-4">
                    <i class="bi bi-cart-x display-1 text-muted"></i>
                </div>
                <h4 class="text-secondary fw-bold mb-3">Giỏ hàng của bạn đang trống!</h4>
                <p class="text-muted mb-4">Hãy khám phá thêm hàng trăm sản phẩm công nghệ chất lượng tại cửa hàng chúng tôi.</p>
                <a href="products.php" class="btn btn-primary btn-lg px-4">
                    <i class="bi bi-arrow-left me-2"></i> Tiếp tục mua sắm
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Bảng danh sách sản phẩm trong giỏ -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-0">
                        <form id="cartForm" action="cart.php" method="POST">
                            <input type="hidden" name="action" value="update_cart">
                            
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" style="min-width: 280px;">Sản phẩm</th>
                                            <th scope="col" class="text-center" style="width: 130px;">Đơn giá</th>
                                            <th scope="col" class="text-center" style="width: 140px;">Số lượng</th>
                                            <th scope="col" class="text-center" style="width: 140px;">Thành tiền</th>
                                            <th scope="col" class="text-center" style="width: 60px;">Xóa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($_SESSION['cart'] as $pId => $item): ?>
                                            <?php
                                                $itemImg = !empty($item['image']) 
                                                    ? (defined('BASE_URL') ? BASE_URL : '..') . '/assets/images/products/' . htmlspecialchars($item['image'])
                                                    : 'https://placehold.co/100x100?text=SP';
                                                $lineTotal = (float)$item['price'] * (int)$item['quantity'];
                                            ?>
                                            <tr>
                                                <!-- Cột Hình ảnh & Tên sản phẩm -->
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <a href="product-detail.php?id=<?= $item['id'] ?>" class="me-3 flex-shrink-0">
                                                            <img src="<?= $itemImg ?>" 
                                                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                                                 class="rounded border" 
                                                                 style="width: 65px; height: 65px; object-fit: contain;"
                                                                 onerror="this.src='https://placehold.co/100x100?text=SP';">
                                                        </a>
                                                        <div>
                                                            <a href="product-detail.php?id=<?= $item['id'] ?>" class="text-decoration-none fw-semibold text-dark d-block">
                                                                <?= htmlspecialchars($item['name']) ?>
                                                            </a>
                                                            <small class="text-muted">Mã SP: #<?= (int)$item['id'] ?></small>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Đơn giá -->
                                                <td class="text-center text-nowrap">
                                                    <?= formatPrice($item['price']) ?>
                                                </td>

                                                <!-- Số lượng (input) -->
                                                <td class="text-center">
                                                    <div class="input-group input-group-sm mx-auto" style="width: 110px;">
                                                        <button class="btn btn-outline-secondary" type="button" onclick="changeQty(<?= $item['id'] ?>, -1)">-</button>
                                                        <input type="number" 
                                                               class="form-control text-center px-1" 
                                                               id="qty_<?= $item['id'] ?>" 
                                                               name="quantities[<?= $item['id'] ?>]" 
                                                               value="<?= (int)$item['quantity'] ?>" 
                                                               min="1" 
                                                               max="99" 
                                                               required>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="changeQty(<?= $item['id'] ?>, 1)">+</button>
                                                    </div>
                                                </td>

                                                <!-- Thành tiền -->
                                                <td class="text-center fw-bold text-danger text-nowrap">
                                                    <?= formatPrice($lineTotal) ?>
                                                </td>

                                                <!-- Nút Xóa -->
                                                <td class="text-center">
                                                    <button type="button" 
                                                            class="btn btn-link text-danger p-0" 
                                                            title="Xóa sản phẩm"
                                                            onclick="confirmRemoveItem(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')">
                                                        <i class="bi bi-trash fs-5"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Thanh hành động bên dưới bảng -->
                            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
                                <a href="products.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Tiếp tục mua sắm
                                </a>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-danger" onclick="confirmClearCart()">
                                        <i class="bi bi-trash me-1"></i> Xóa tất cả
                                    </button>
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-repeat me-1"></i> Cập nhật giỏ hàng
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tóm tắt đơn hàng & nút Đặt hàng -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="card-title mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tạm tính:</span>
                            <span class="fw-semibold"><?= formatPrice($subtotal) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Phí vận chuyển:</span>
                            <span class="text-success fw-semibold">Miễn phí</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fs-5 fw-bold">Tổng thanh toán:</span>
                            <span class="fs-4 fw-bold text-danger"><?= formatPrice($total) ?></span>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="checkout.php" class="btn btn-danger btn-lg py-3 fw-bold">
                                <i class="bi bi-shield-check me-2"></i> Tiến hành đặt hàng
                            </a>
                        </div>

                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="bi bi-shield-lock-fill text-success"></i> Giao dịch được bảo mật và an toàn 100%
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Cam kết dịch vụ -->
                <div class="card shadow-sm border-0 bg-light">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-2"><i class="bi bi-patch-check-fill text-primary me-2"></i>Quyền lợi khách hàng:</h6>
                        <ul class="list-unstyled mb-0 small text-muted">
                            <li class="mb-2"><i class="bi bi-check2 text-success me-1"></i> Kiểm tra hàng trước khi thanh toán</li>
                            <li class="mb-2"><i class="bi bi-check2 text-success me-1"></i> Đổi mới trong 7 ngày nếu lỗi phần cứng</li>
                            <li><i class="bi bi-check2 text-success me-1"></i> Hỗ trợ kỹ thuật 24/7 trọn đời</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Form ẩn xử lý Xóa sản phẩm -->
<form id="removeItemForm" action="cart.php" method="POST" style="display: none;">
    <input type="hidden" name="action" value="remove_item">
    <input type="hidden" name="product_id" id="remove_product_id" value="">
</form>

<!-- Form ẩn xử lý Xóa tất cả giỏ hàng -->
<form id="clearCartForm" action="cart.php" method="POST" style="display: none;">
    <input type="hidden" name="action" value="clear_cart">
</form>

<script>
function changeQty(id, delta) {
    const input = document.getElementById('qty_' + id);
    if (!input) return;
    let val = parseInt(input.value) || 1;
    let min = parseInt(input.getAttribute('min')) || 1;
    let max = parseInt(input.getAttribute('max')) || 99;
    let newVal = val + delta;
    if (newVal >= min && newVal <= max) {
        input.value = newVal;
    }
}

function confirmRemoveItem(id, name) {
    if (confirm('Bạn có chắc chắn muốn xóa sản phẩm "' + name + '" khỏi giỏ hàng?')) {
        document.getElementById('remove_product_id').value = id;
        document.getElementById('removeItemForm').submit();
    }
}

function confirmClearCart() {
    if (confirm('Bạn có chắc chắn muốn xóa toàn bộ sản phẩm trong giỏ hàng?')) {
        document.getElementById('clearCartForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
