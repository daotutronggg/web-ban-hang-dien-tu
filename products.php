<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Xử lý thêm nhanh vào giỏ hàng từ trang danh sách sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_add') {
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

    if ($productId > 0) {
        $stmt = $conn->prepare("SELECT id, name, price, quantity, image FROM products WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();

        if ($prod) {
            if ($prod['quantity'] <= 0) {
                flash('error', 'Sản phẩm "' . htmlspecialchars($prod['name']) . '" hiện đã hết hàng.');
            } else {
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }

                $currentQty = isset($_SESSION['cart'][$productId]) ? (int)$_SESSION['cart'][$productId]['quantity'] : 0;
                $newQty = $currentQty + $quantity;

                if ($newQty > $prod['quantity']) {
                    flash('error', 'Số lượng bạn chọn vượt quá số lượng trong kho (chỉ còn ' . $prod['quantity'] . ' sản phẩm).');
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

    // Chuyển hướng giữ nguyên các tham số GET hiện tại
    $redirectUrl = 'products.php';
    $params = [];
    if (!empty($_GET['category_id'])) $params['category_id'] = (int)$_GET['category_id'];
    if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
    if (!empty($_GET['sort'])) $params['sort'] = $_GET['sort'];
    if (!empty($_GET['page'])) $params['page'] = (int)$_GET['page'];
    if (!empty($params)) {
        $redirectUrl .= '?' . http_build_query($params);
    }
    redirect($redirectUrl);
    exit;
}

// Lấy các tham số lọc và phân trang
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Lấy danh sách danh mục và đếm số sản phẩm mỗi danh mục
$catSql = "SELECT c.id, c.name, COUNT(p.id) AS product_count 
           FROM categories c 
           LEFT JOIN products p ON c.id = p.category_id 
           GROUP BY c.id, c.name 
           ORDER BY c.name ASC";
$catResult = $conn->query($catSql);
$categories = $catResult ? $catResult->fetch_all(MYSQLI_ASSOC) : [];

// Tổng số tất cả sản phẩm trong hệ thống
$totalAllRes = $conn->query("SELECT COUNT(*) AS total FROM products");
$totalAllCount = $totalAllRes ? (int)$totalAllRes->fetch_assoc()['total'] : 0;

// Tìm tên danh mục hiện tại (nếu đang lọc theo danh mục)
$currentCategoryName = '';
if ($category_id > 0) {
    $cStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $cStmt->bind_param("i", $category_id);
    $cStmt->execute();
    $cRow = $cStmt->get_result()->fetch_assoc();
    if ($cRow) {
        $currentCategoryName = $cRow['name'];
    }
}

// Xây dựng câu truy vấn điều kiện
$whereConditions = ["1=1"];
$params = [];
$types = "";

if ($category_id > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

if ($search !== '') {
    $whereConditions[] = "p.name LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= "s";
}

$whereSql = implode(" AND ", $whereConditions);

// Đếm tổng số sản phẩm thỏa mãn điều kiện
$countSql = "SELECT COUNT(*) AS total FROM products p WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalProducts = (int)$countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalProducts / $limit);
if ($totalPages < 1) $totalPages = 1;

// Xử lý sắp xếp
$orderSql = "p.id DESC";
switch ($sort) {
    case 'price_asc':
        $orderSql = "p.price ASC";
        break;
    case 'price_desc':
        $orderSql = "p.price DESC";
        break;
    case 'name_asc':
        $orderSql = "p.name ASC";
        break;
    case 'name_desc':
        $orderSql = "p.name DESC";
        break;
    default:
        $orderSql = "p.id DESC";
        break;
}

// Lấy danh sách sản phẩm với phân trang và JOIN bảng categories
$querySql = "SELECT p.*, c.name AS category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id 
             WHERE $whereSql 
             ORDER BY $orderSql 
             LIMIT ? OFFSET ?";

$fetchStmt = $conn->prepare($querySql);
$fetchParams = $params;
$fetchParams[] = $limit;
$fetchParams[] = $offset;
$fetchTypes = $types . "ii";
$fetchStmt->bind_param($fetchTypes, ...$fetchParams);
$fetchStmt->execute();
$products = $fetchStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Thiết lập tiêu đề trang
$page_title = 'Danh sách sản phẩm';
if (!empty($currentCategoryName)) {
    $page_title = $currentCategoryName . ' - Danh sách sản phẩm';
} elseif (!empty($search)) {
    $page_title = 'Tìm kiếm: ' . $search . ' - Danh sách sản phẩm';
}

$homeUrl = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item"><a href="<?= $homeUrl ?>"><i class="bi bi-house-door"></i> Trang chủ</a></li>
            <?php if ($category_id > 0 || !empty($search)): ?>
                <li class="breadcrumb-item"><a href="products.php">Sản phẩm</a></li>
                <?php if ($category_id > 0): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($currentCategoryName) ?></li>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <li class="breadcrumb-item active" aria-current="page">Tìm kiếm: "<?= htmlspecialchars($search) ?>"</li>
                <?php endif; ?>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page">Tất cả sản phẩm</li>
            <?php endif; ?>
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

    <div class="row">
        <!-- Sidebar lọc danh mục & tìm kiếm -->
        <aside class="col-lg-3 col-md-4 mb-4">
            <!-- Ô tìm kiếm -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-search me-2"></i>Tìm kiếm</h5>
                </div>
                <div class="card-body">
                    <form action="products.php" method="GET">
                        <?php if ($category_id > 0): ?>
                            <input type="hidden" name="category_id" value="<?= $category_id ?>">
                        <?php endif; ?>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Nhập tên sản phẩm..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <?php if (!empty($search)): ?>
                            <div class="mt-2 text-end">
                                <a href="products.php<?= $category_id > 0 ? '?category_id=' . $category_id : '' ?>" class="small text-danger text-decoration-none">
                                    <i class="bi bi-x-circle"></i> Xóa tìm kiếm
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Danh mục sản phẩm -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-grid-fill me-2"></i>Danh mục sản phẩm</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="products.php<?= !empty($search) ? '?search=' . urlencode($search) : '' ?>" 
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $category_id === 0 ? 'active' : '' ?>">
                        <span><i class="bi bi-collection me-2"></i>Tất cả sản phẩm</span>
                        <span class="badge <?= $category_id === 0 ? 'bg-light text-dark' : 'bg-primary' ?> rounded-pill"><?= $totalAllCount ?></span>
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="products.php?category_id=<?= $cat['id'] ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $category_id === (int)$cat['id'] ? 'active' : '' ?>">
                            <span><i class="bi bi-tag me-2"></i><?= htmlspecialchars($cat['name']) ?></span>
                            <span class="badge <?= $category_id === (int)$cat['id'] ? 'bg-light text-dark' : 'bg-secondary' ?> rounded-pill"><?= (int)$cat['product_count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <!-- Danh sách sản phẩm chính -->
        <main class="col-lg-9 col-md-8">
            <!-- Thanh tiêu đề & bộ sắp xếp -->
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center py-2">
                    <div>
                        <h4 class="mb-1 text-primary">
                            <?php if (!empty($currentCategoryName)): ?>
                                <?= htmlspecialchars($currentCategoryName) ?>
                            <?php elseif (!empty($search)): ?>
                                Kết quả tìm kiếm: "<?= htmlspecialchars($search) ?>"
                            <?php else: ?>
                                Tất cả sản phẩm
                            <?php endif; ?>
                        </h4>
                        <small class="text-muted">
                            Hiển thị <strong><?= count($products) ?></strong> trên tổng số <strong><?= $totalProducts ?></strong> sản phẩm
                        </small>
                    </div>

                    <!-- Dropdown sắp xếp -->
                    <div class="d-flex align-items-center mt-2 mt-sm-0">
                        <label for="sortSelect" class="form-label me-2 mb-0 text-nowrap small text-muted">Sắp xếp:</label>
                        <form method="GET" action="products.php" class="d-inline">
                            <?php if ($category_id > 0): ?>
                                <input type="hidden" name="category_id" value="<?= $category_id ?>">
                            <?php endif; ?>
                            <?php if (!empty($search)): ?>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <?php endif; ?>
                            <select id="sortSelect" name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Tên A - Z</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Tên Z - A</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lưới hiển thị sản phẩm -->
            <?php if (empty($products)): ?>
                <div class="card shadow-sm text-center py-5">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="bi bi-box-seam display-1 text-muted"></i>
                        </div>
                        <h5 class="card-title text-secondary">Không tìm thấy sản phẩm nào!</h5>
                        <p class="card-text text-muted">Không có sản phẩm nào phù hợp với bộ lọc hoặc tìm kiếm của bạn.</p>
                        <a href="products.php" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat me-1"></i> Xem tất cả sản phẩm
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($products as $prod): ?>
                        <?php
                            $imgSrc = !empty($prod['image']) 
                                ? (defined('BASE_URL') ? BASE_URL : '..') . '/assets/images/products/' . htmlspecialchars($prod['image'])
                                : 'https://placehold.co/400x300?text=Shop+Dien+Tu';
                            $inStock = (int)$prod['quantity'] > 0;
                        ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm border-0 product-card position-relative">
                                <!-- Badge tình trạng kho -->
                                <div class="position-absolute top-0 end-0 m-2">
                                    <?php if ($inStock): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Còn hàng</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Hết hàng</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Hình ảnh sản phẩm -->
                                <a href="product-detail.php?id=<?= $prod['id'] ?>" class="text-decoration-none text-dark">
                                    <div class="p-3 text-center bg-white" style="height: 200px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                        <img src="<?= $imgSrc ?>" 
                                             class="img-fluid" 
                                             alt="<?= htmlspecialchars($prod['name']) ?>"
                                             style="max-height: 100%; max-width: 100%; object-fit: contain;"
                                             onerror="this.src='https://placehold.co/400x300?text=No+Image';">
                                    </div>
                                </a>

                                <div class="card-body d-flex flex-column">
                                    <!-- Danh mục -->
                                    <div class="mb-1">
                                        <span class="badge bg-light text-secondary border">
                                            <?= htmlspecialchars($prod['category_name'] ?? 'Điện tử') ?>
                                        </span>
                                    </div>

                                    <!-- Tên sản phẩm -->
                                    <h6 class="card-title mb-2" style="height: 42px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                        <a href="product-detail.php?id=<?= $prod['id'] ?>" class="text-decoration-none text-dark" title="<?= htmlspecialchars($prod['name']) ?>">
                                            <?= htmlspecialchars($prod['name']) ?>
                                        </a>
                                    </h6>

                                    <!-- Giá bán -->
                                    <div class="mt-auto pt-2">
                                        <div class="text-danger fw-bold fs-5 mb-3">
                                            <?= formatPrice($prod['price']) ?>
                                        </div>

                                        <!-- Nút thao tác -->
                                        <div class="d-grid gap-2 d-flex">
                                            <a href="product-detail.php?id=<?= $prod['id'] ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                                                <i class="bi bi-eye"></i> Chi tiết
                                            </a>
                                            <form action="products.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" method="POST" class="flex-fill">
                                                <input type="hidden" name="action" value="quick_add">
                                                <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit" class="btn btn-primary btn-sm w-100" <?= !$inStock ? 'disabled' : '' ?>>
                                                    <i class="bi bi-cart-plus"></i> Thêm
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Phân trang -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <!-- Nút Trước -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <?php
                                    $prevParams = $_GET;
                                    $prevParams['page'] = $page - 1;
                                ?>
                                <a class="page-link" href="products.php?<?= http_build_query($prevParams) ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo; Trang trước</span>
                                </a>
                            </li>

                            <!-- Các số trang -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php
                                    $pageParams = $_GET;
                                    $pageParams['page'] = $i;
                                ?>
                                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                    <a class="page-link" href="products.php?<?= http_build_query($pageParams) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Nút Tiếp -->
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <?php
                                    $nextParams = $_GET;
                                    $nextParams['page'] = $page + 1;
                                ?>
                                <a class="page-link" href="products.php?<?= http_build_query($nextParams) ?>" aria-label="Next">
                                    <span aria-hidden="true">Trang sau &raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
