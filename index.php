<?php
/**
 * Trang Chủ (Home Page)
 * Shop Điện Tử - Cửa Hàng Thiết Bị Công Nghệ Chính Hãng
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// ====================================================================
// XỬ LÝ THÊM SẢN PHẨM VÀO GIỎ HÀNG (HỖ TRỢ CẢ FORM POST VÀ AJAX)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity  = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $isAjax    = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_POST['ajax']);

    if ($productId > 0) {
        $stmt = mysqli_prepare($conn, "SELECT id, name, price, quantity FROM products WHERE id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $productId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $product = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($product) {
                if ($product['quantity'] <= 0) {
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => false,
                            'message' => 'Sản phẩm "' . $product['name'] . '" hiện đã hết hàng!'
                        ]);
                        exit();
                    } else {
                        flash('danger', 'Sản phẩm "' . $product['name'] . '" hiện đã hết hàng!');
                        redirect('index.php');
                    }
                }

                // Khởi tạo mảng giỏ hàng nếu chưa tồn tại
                if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }

                $currentQty = 0;
                if (isset($_SESSION['cart'][$productId])) {
                    $currentQty = is_array($_SESSION['cart'][$productId]) 
                        ? (int)($_SESSION['cart'][$productId]['quantity'] ?? 0)
                        : (int)$_SESSION['cart'][$productId];
                }
                $newQty = $currentQty + $quantity;

                // Kiểm tra giới hạn số lượng theo lượng tồn trong kho
                if ($newQty > $product['quantity']) {
                    $newQty = (int)$product['quantity'];
                    $notice = 'Đã cập nhật tối đa ' . $product['quantity'] . ' sản phẩm có sẵn trong kho.';
                } else {
                    $notice = 'Đã thêm "' . $product['name'] . '" vào giỏ hàng thành công!';
                }

                $_SESSION['cart'][$productId] = [
                    'id'       => (int)$product['id'],
                    'name'     => $product['name'],
                    'price'    => (float)$product['price'],
                    'image'    => $product['image'],
                    'quantity' => $newQty
                ];
                $cartTotalCount = getCartCount();

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success'   => true,
                        'message'   => $notice,
                        'cartCount' => $cartTotalCount,
                        'productId' => $productId
                    ]);
                    exit();
                } else {
                    flash('success', $notice);
                    redirect('index.php');
                }
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy sản phẩm yêu cầu.'
        ]);
        exit();
    } else {
        flash('danger', 'Sản phẩm không hợp lệ!');
        redirect('index.php');
    }
}

// ====================================================================
// TRUY VẤN DỮ LIỆU HIỂN THỊ TRANG CHỦ
// ====================================================================

// 1. Lấy danh mục sản phẩm kèm số lượng sản phẩm mỗi loại
$categories = [];
$catQuery = "SELECT c.id, c.name, c.description, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON c.id = p.category_id
             GROUP BY c.id
             ORDER BY c.id ASC";
$catResult = mysqli_query($conn, $catQuery);
if ($catResult) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
    mysqli_free_result($catResult);
}

// 2. Lấy 8 sản phẩm mới nhất / nổi bật
$featuredProducts = [];
$prodQuery = "SELECT p.id, p.category_id, p.name, p.description, p.price, p.quantity, p.image, c.name AS category_name
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              ORDER BY p.id DESC
              LIMIT 8";
$prodResult = mysqli_query($conn, $prodQuery);
if ($prodResult) {
    while ($row = mysqli_fetch_assoc($prodResult)) {
        $featuredProducts[] = $row;
    }
    mysqli_free_result($prodResult);
}

// Hàm gán icon đại diện cho từng loại danh mục
function getCategoryIcon($catName) {
    $catNameLower = mb_strtolower($catName, 'UTF-8');
    if (strpos($catNameLower, 'thoại') !== false) return 'fa-mobile-screen-button';
    if (strpos($catNameLower, 'laptop') !== false) return 'fa-laptop';
    if (strpos($catNameLower, 'bảng') !== false) return 'fa-tablet-screen-button';
    if (strpos($catNameLower, 'phụ kiện') !== false) return 'fa-plug-circle-bolt';
    if (strpos($catNameLower, 'tai nghe') !== false) return 'fa-headphones';
    return 'fa-microchip';
}

$page_title = 'Trang Chủ - Thế Giới Công Nghệ';
include __DIR__ . '/includes/header.php';
?>

<div class="container pb-5">

    <!-- ====================================================================
         1. HERO BANNER SECTION
         ==================================================================== -->
    <section class="my-4">
        <div class="hero-banner">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="hero-badge mb-3">
                        <i class="fa-solid fa-bolt text-warning"></i>
                        <span>Ưu đãi công nghệ mới nhất 2026</span>
                    </div>
                    <h1 class="display-5 fw-extrabold text-white mb-3">
                        Công Nghệ Đỉnh Cao <br>
                        <span class="text-warning">Trải Nghiệm Hoàn Hảo</span>
                    </h1>
                    <p class="lead text-white-50 mb-4 pe-lg-4">
                        Khám phá các dòng điện thoại, laptop, tablet và phụ kiện công nghệ chính hãng hàng đầu với giá ưu đãi cực sốc, miễn phí giao hàng toàn quốc cùng chế độ bảo hành vượt trội.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#featured-products" class="btn btn-warning btn-lg px-4 fw-bold rounded-pill shadow">
                            <i class="fa-solid fa-bag-shopping me-2"></i> Mua sắm ngay
                        </a>
                        <a href="<?php echo $baseUrl; ?>products.php" class="btn btn-outline-light btn-lg px-4 rounded-pill">
                            Xem tất cả sản phẩm <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    </div>

                    <!-- Mini Highlights List -->
                    <div class="d-flex flex-wrap gap-4 mt-4 pt-3 border-top border-white border-opacity-10 text-white-50 small">
                        <div><i class="fa-solid fa-shield-check text-warning me-1"></i> Chính hãng 100%</div>
                        <div><i class="fa-solid fa-rotate-left text-warning me-1"></i> 1 đổi 1 trong 30 ngày</div>
                        <div><i class="fa-solid fa-truck-fast text-warning me-1"></i> Giao nhanh 2 Giờ</div>
                    </div>
                </div>

                <div class="col-lg-5 text-center hero-image-wrap">
                    <div class="p-3 bg-white bg-opacity-10 rounded-4 backdrop-blur shadow-lg border border-white border-opacity-20 d-inline-block">
                        <img src="<?php echo $baseUrl; ?>assets/images/placeholder.svg" alt="Sản phẩm công nghệ nổi bật" class="img-fluid rounded-3" style="max-height: 280px;" onerror="this.src='data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 300 200\'><rect fill=\'%230d6efd\' width=\'300\' height=\'200\'/><text fill=\'%23ffffff\' font-family=\'sans-serif\' font-size=\'20\' dy=\'10.5\' font-weight=\'bold\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'>Shop Điện Tử</text></svg>'">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         2. CATEGORIES SECTION (DANH MỤC NỔI BẬT)
         ==================================================================== -->
    <section class="my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-layer-group text-primary me-2"></i>Danh Mục Sản Phẩm
                </h3>
                <p class="text-muted small mb-0">Các nhóm sản phẩm công nghệ đang được quan tâm nhất</p>
            </div>
            <a href="<?php echo $baseUrl; ?>products.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                Xem tất cả <i class="fa-solid fa-chevron-right ms-1 small"></i>
            </a>
        </div>

        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <div class="col">
                        <a href="<?php echo $baseUrl; ?>products.php?category_id=<?php echo (int)$cat['id']; ?>" class="category-card text-decoration-none">
                            <div class="category-icon">
                                <i class="fa-solid <?php echo getCategoryIcon($cat['name']); ?>"></i>
                            </div>
                            <h6 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                            <span class="badge bg-light text-secondary rounded-pill border">
                                <?php echo (int)$cat['product_count']; ?> sản phẩm
                            </span>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info mb-0">Chưa có danh mục sản phẩm nào trong hệ thống.</div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ====================================================================
         3. FEATURED PRODUCTS SECTION (SẢN PHẨM MỚI NHẤT & NỔI BẬT)
         ==================================================================== -->
    <section id="featured-products" class="my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-fire text-danger me-2"></i>Sản Phẩm Mới & Nổi Bật
                </h3>
                <p class="text-muted small mb-0">Cập nhật những thiết bị hiện đại, bán chạy nhất trong tuần</p>
            </div>
            <a href="<?php echo $baseUrl; ?>products.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                Tất cả sản phẩm <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (!empty($featuredProducts)): ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php foreach ($featuredProducts as $item): ?>
                    <?php
                    $isAvailable = (int)$item['quantity'] > 0;
                    $imgUrl = getProductImage($item['image']);
                    ?>
                    <div class="col">
                        <div class="product-card h-100">
                            <!-- Discount Badge -->
                            <span class="product-badge-discount">Mới</span>

                            <!-- Product Image Wrap -->
                            <div class="product-image-wrap">
                                <a href="<?php echo $baseUrl; ?>product-detail.php?id=<?php echo (int)$item['id']; ?>" class="d-block w-100 h-100 text-center">
                                    <img src="<?php echo $imgUrl; ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" onerror="this.src='<?php echo $baseUrl; ?>assets/images/placeholder.svg';">
                                </a>
                            </div>

                            <!-- Product Details Body -->
                            <div class="product-body">
                                <div class="product-category-tag">
                                    <?php echo htmlspecialchars($item['category_name'] ?? 'Thiết bị', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <h5 class="product-title">
                                    <a href="<?php echo $baseUrl; ?>product-detail.php?id=<?php echo (int)$item['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </h5>

                                <div class="product-price">
                                    <?php echo formatPrice($item['price']); ?>
                                </div>

                                <div class="product-stock-status">
                                    <?php if ($isAvailable): ?>
                                        <span class="text-success small fw-medium">
                                            <i class="fa-solid fa-circle-check me-1"></i> Còn hàng (<?php echo (int)$item['quantity']; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-danger small fw-medium">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> Tạm hết hàng
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Button: Thêm vào giỏ -->
                                <div class="product-footer">
                                    <?php if ($isAvailable): ?>
                                        <form action="<?php echo $baseUrl; ?>index.php" method="POST" class="form-add-to-cart m-0">
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$item['id']; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn-add-cart">
                                                <i class="fa-solid fa-cart-plus me-1"></i> Thêm giỏ hàng
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100 btn-sm disabled" disabled>
                                            <i class="fa-solid fa-ban me-1"></i> Hết hàng
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 bg-white rounded-3 shadow-sm border p-4">
                <i class="fa-solid fa-boxes-packing text-muted fs-1 mb-3"></i>
                <h5 class="text-secondary">Chưa có sản phẩm nào trong hệ thống</h5>
                <p class="text-muted small">Vui lòng nạp cơ sở dữ liệu mẫu từ file <code>sql/database.sql</code> để hiển thị danh sách sản phẩm.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- ====================================================================
         4. WHY CHOOSE US (TẠI SAO CHỌN CHÚNG TÔI)
         ==================================================================== -->
    <section class="my-5 pt-3">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark">
                <i class="fa-solid fa-award text-warning me-2"></i>Tại Sao Chọn Shop Điện Tử?
            </h3>
            <p class="text-muted small">Cam kết mang lại trải nghiệm mua sắm công nghệ an tâm tuyệt đối</p>
        </div>

        <div class="row g-4">
            <!-- Box 1 -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-box text-center">
                    <div class="feature-icon-wrapper bg-primary bg-opacity-10 text-primary mx-auto">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">Chính Hãng 100%</h6>
                    <p class="text-muted small mb-0">
                        Toàn bộ sản phẩm đều có hóa đơn VAT, chứng từ rõ ràng từ các nhà phân phối chính thức.
                    </p>
                </div>
            </div>

            <!-- Box 2 -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-box text-center">
                    <div class="feature-icon-wrapper bg-success bg-opacity-10 text-success mx-auto">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">Giao Hàng Miễn Phí</h6>
                    <p class="text-muted small mb-0">
                        Miễn phí vận chuyển toàn quốc cho đơn hàng từ 500k. Giao nhanh 2h tại khu vực nội thành.
                    </p>
                </div>
            </div>

            <!-- Box 3 -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-box text-center">
                    <div class="feature-icon-wrapper bg-warning bg-opacity-10 text-warning mx-auto">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">Đổi Mới 30 Ngày</h6>
                    <p class="text-muted small mb-0">
                        Chính sách 1 đổi 1 tận nơi trong 30 ngày nếu phát sinh lỗi kỹ thuật từ nhà sản xuất.
                    </p>
                </div>
            </div>

            <!-- Box 4 -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-box text-center">
                    <div class="feature-icon-wrapper bg-info bg-opacity-10 text-info mx-auto">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">Hỗ Trợ Tận Tâm 24/7</h6>
                    <p class="text-muted small mb-0">
                        Tổng đài kỹ thuật và chăm sóc khách hàng luôn sẵn sàng hỗ trợ giải đáp mọi lúc mọi nơi.
                    </p>
                </div>
            </div>
        </div>
    </section>

</div>

<?php
include __DIR__ . '/includes/footer.php';
?>
