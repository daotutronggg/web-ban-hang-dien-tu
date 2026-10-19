<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Kiểm tra tham số ID sản phẩm từ URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash('error', 'Sản phẩm không hợp lệ.');
    redirect('products.php');
    exit;
}

// Lấy thông tin chi tiết sản phẩm và tên danh mục bằng prepared statement
$stmt = $conn->prepare("SELECT p.*, c.name AS category_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

// Nếu không tìm thấy sản phẩm
if (!$product) {
    flash('error', 'Không tìm thấy sản phẩm yêu cầu.');
    redirect('products.php');
    exit;
}

// Xử lý thêm sản phẩm vào giỏ hàng (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    if ($quantity < 1) {
        $quantity = 1;
    }

    if ((int)$product['quantity'] <= 0) {
        flash('error', 'Sản phẩm hiện đang tạm hết hàng.');
        redirect('product-detail.php?id=' . $id);
        exit;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $currentCartQty = isset($_SESSION['cart'][$id]) ? (int)$_SESSION['cart'][$id]['quantity'] : 0;
    $newTotalQty = $currentCartQty + $quantity;

    if ($newTotalQty > (int)$product['quantity']) {
        flash('error', 'Số lượng bạn chọn vượt quá số lượng còn trong kho (tối đa ' . $product['quantity'] . ' sản phẩm).');
    } else {
        $_SESSION['cart'][$id] = [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'image' => $product['image'],
            'quantity' => $newTotalQty
        ];

        // Nếu bấm "Mua ngay", chuyển hướng thẳng tới giỏ hàng
        if (isset($_POST['buy_now']) && $_POST['buy_now'] == '1') {
            flash('success', 'Đã thêm sản phẩm vào giỏ hàng thành công!');
            redirect('cart.php');
            exit;
        }

        flash('success', 'Đã thêm "' . htmlspecialchars($product['name']) . '" vào giỏ hàng thành công!');
    }

    redirect('product-detail.php?id=' . $id);
    exit;
}

// Lấy danh sách sản phẩm liên quan (cùng danh mục, trừ sản phẩm hiện tại, tối đa 4)
$relStmt = $conn->prepare("SELECT p.*, c.name AS category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.category_id = ? AND p.id != ? 
                           ORDER BY p.id DESC 
                           LIMIT 4");
$relStmt->bind_param("ii", $product['category_id'], $id);
$relStmt->execute();
$relatedProducts = $relStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = $product['name'] . ' - Chi tiết sản phẩm';
$homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';

$productImage = !empty($product['image']) 
    ? (defined('BASE_URL') ? BASE_URL : '..') . '/assets/images/products/' . htmlspecialchars($product['image'])
    : 'https://placehold.co/600x500?text=Shop+Dien+Tu';

$inStock = (int)$product['quantity'] > 0;

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Breadcrumb: Trang chủ > Sản phẩm > [Category] > [Product Name] -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item"><a href="<?= $homeUrl ?>"><i class="bi bi-house-door"></i> Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="products.php">Sản phẩm</a></li>
            <?php if (!empty($product['category_name'])): ?>
                <li class="breadcrumb-item">
                    <a href="products.php?category_id=<?= (int)$product['category_id'] ?>">
                        <?= htmlspecialchars($product['category_name']) ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-truncate" style="max-width: 350px;" aria-current="page">
                <?= htmlspecialchars($product['name']) ?>
            </li>
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

    <!-- Chi tiết sản phẩm -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- Hình ảnh sản phẩm lớn -->
                <div class="col-lg-5 col-md-6 text-center">
                    <div class="border rounded p-3 bg-white d-flex align-items-center justify-content-center" style="min-height: 380px; max-height: 480px;">
                        <img src="<?= $productImage ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>" 
                             class="img-fluid rounded" 
                             style="max-height: 400px; width: auto; object-fit: contain;"
                             onerror="this.src='https://placehold.co/600x500?text=No+Image';">
                    </div>
                </div>

                <!-- Thông tin & Thao tác đặt mua -->
                <div class="col-lg-7 col-md-6">
                    <div class="mb-2">
                        <a href="products.php?category_id=<?= (int)$product['category_id'] ?>" class="badge bg-primary text-decoration-none py-2 px-3">
                            <i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars($product['category_name'] ?? 'Chưa phân loại') ?>
                        </a>
                        <span class="badge bg-light text-muted border ms-1 py-2 px-3">Mã SP: #<?= (int)$product['id'] ?></span>
                    </div>

                    <h2 class="fw-bold mb-3"><?= htmlspecialchars($product['name']) ?></h2>

                    <!-- Giá bán -->
                    <div class="p-3 bg-light rounded mb-3">
                        <span class="text-muted small d-block">Giá niêm yết:</span>
                        <div class="text-danger fw-bold fs-2">
                            <?= formatPrice($product['price']) ?>
                        </div>
                    </div>

                    <!-- Trạng thái kho hàng -->
                    <div class="mb-3">
                        <strong>Tình trạng: </strong>
                        <?php if ($inStock): ?>
                            <span class="badge bg-success py-1 px-2">
                                <i class="bi bi-check-circle me-1"></i> Còn hàng (<?= (int)$product['quantity'] ?> sản phẩm có sẵn)
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger py-1 px-2">
                                <i class="bi bi-x-circle me-1"></i> Tạm hết hàng
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Mô tả ngắn gọn -->
                    <?php if (!empty($product['description'])): ?>
                        <div class="text-muted mb-4" style="line-height: 1.6;">
                            <?= nl2br(htmlspecialchars(mb_strimwidth($product['description'], 0, 200, '...'))) ?>
                        </div>
                    <?php endif; ?>

                    <hr class="my-4">

                    <!-- Form chọn số lượng & thêm vào giỏ hàng -->
                    <form action="product-detail.php?id=<?= $product['id'] ?>" method="POST">
                        <input type="hidden" name="action" value="add_to_cart">
                        
                        <div class="row g-3 align-items-center mb-4">
                            <div class="col-auto">
                                <label for="quantity" class="col-form-label fw-bold">Số lượng:</label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group" style="width: 140px;">
                                    <button class="btn btn-outline-secondary" type="button" onclick="adjustQty(-1)" <?= !$inStock ? 'disabled' : '' ?>>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" 
                                           class="form-control text-center" 
                                           id="quantity" 
                                           name="quantity" 
                                           value="1" 
                                           min="1" 
                                           max="<?= (int)$product['quantity'] ?>" 
                                           <?= !$inStock ? 'disabled' : '' ?> 
                                           required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="adjustQty(1)" <?= !$inStock ? 'disabled' : '' ?>>
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">(Kho còn: <?= (int)$product['quantity'] ?>)</small>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-3">
                            <button type="submit" class="btn btn-outline-primary btn-lg px-4" <?= !$inStock ? 'disabled' : '' ?>>
                                <i class="bi bi-cart-plus me-2"></i> Thêm vào giỏ hàng
                            </button>
                            <button type="submit" name="buy_now" value="1" class="btn btn-danger btn-lg px-4" <?= !$inStock ? 'disabled' : '' ?>>
                                <i class="bi bi-bag-check-fill me-2"></i> Mua ngay
                            </button>
                        </div>
                    </form>

                    <!-- Chính sách bán hàng & hỗ trợ -->
                    <div class="row g-3 mt-4 pt-2 border-top">
                        <div class="col-sm-4 text-center text-sm-start">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-truck text-primary fs-3 me-2"></i>
                                <small>Giao hàng toàn quốc nhanh chóng</small>
                            </div>
                        </div>
                        <div class="col-sm-4 text-center text-sm-start">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-shield-check text-success fs-3 me-2"></i>
                                <small>Bảo hành chính hãng 12 tháng</small>
                            </div>
                        </div>
                        <div class="col-sm-4 text-center text-sm-start">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-arrow-counterclockwise text-warning fs-3 me-2"></i>
                                <small>Đổi trả 7 ngày nếu lỗi từ NSX</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab mô tả chi tiết sản phẩm -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white border-bottom">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#desc-tab" type="button">
                        <i class="bi bi-file-text me-2"></i>Mô tả chi tiết sản phẩm
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="desc-tab">
                    <?php if (!empty($product['description'])): ?>
                        <div class="lh-lg">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted fst-italic">Chưa có thông tin mô tả chi tiết cho sản phẩm này.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sản phẩm liên quan -->
    <?php if (!empty($relatedProducts)): ?>
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold text-primary mb-0"><i class="bi bi-grid me-2"></i>Sản phẩm liên quan</h4>
                <a href="products.php?category_id=<?= (int)$product['category_id'] ?>" class="text-decoration-none">
                    Xem thêm <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4">
                <?php foreach ($relatedProducts as $rel): ?>
                    <?php
                        $relImg = !empty($rel['image']) 
                            ? (defined('BASE_URL') ? BASE_URL : '..') . '/assets/images/products/' . htmlspecialchars($rel['image'])
                            : 'https://placehold.co/400x300?text=Shop+Dien+Tu';
                    ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0 product-card">
                            <a href="product-detail.php?id=<?= $rel['id'] ?>" class="text-decoration-none text-dark">
                                <div class="p-3 text-center bg-white" style="height: 180px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <img src="<?= $relImg ?>" 
                                         class="img-fluid" 
                                         alt="<?= htmlspecialchars($rel['name']) ?>"
                                         style="max-height: 100%; max-width: 100%; object-fit: contain;"
                                         onerror="this.src='https://placehold.co/400x300?text=No+Image';">
                                </div>
                            </a>
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title mb-2" style="height: 40px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                    <a href="product-detail.php?id=<?= $rel['id'] ?>" class="text-decoration-none text-dark" title="<?= htmlspecialchars($rel['name']) ?>">
                                        <?= htmlspecialchars($rel['name']) ?>
                                    </a>
                                </h6>
                                <div class="mt-auto">
                                    <div class="text-danger fw-bold mb-2">
                                        <?= formatPrice($rel['price']) ?>
                                    </div>
                                    <a href="product-detail.php?id=<?= $rel['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="bi bi-eye"></i> Xem chi tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function adjustQty(amount) {
    const qtyInput = document.getElementById('quantity');
    if (!qtyInput) return;
    let currentVal = parseInt(qtyInput.value) || 1;
    let minVal = parseInt(qtyInput.getAttribute('min')) || 1;
    let maxVal = parseInt(qtyInput.getAttribute('max')) || 999;
    
    let newVal = currentVal + amount;
    if (newVal >= minVal && newVal <= maxVal) {
        qtyInput.value = newVal;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
