<?php
/**
 * Footer Template
 * Shop Điện Tử - Chân trang website
 */

$baseUrl = defined('BASE_URL') ? BASE_URL : '/shop-dien-tu/';
?>
    </main>
    <!-- Main Content Area Ends -->

    <!-- Toast Container for Notifications (AJAX Cart, Alerts) -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
        <div id="appToast" class="toast align-items-center text-white bg-primary border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center">
                    <i id="toastIcon" class="fa-solid fa-circle-check fs-5 me-2"></i>
                    <span id="toastMessage">Thông báo</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    <footer class="bg-dark text-light pt-5 pb-3 mt-5 border-top border-secondary border-opacity-25">
        <div class="container">
            <div class="row g-4 pb-4">
                <!-- Cột 1: Giới thiệu Shop -->
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand d-flex align-items-center mb-3">
                        <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle me-2" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-bag-shopping"></i>
                        </span>
                        <h5 class="text-white fw-bold mb-0">Shop Điện Tử</h5>
                    </div>
                    <p class="text-white-50 small pe-lg-3">
                        Hệ thống bán lẻ điện thoại, laptop, máy tính bảng và phụ kiện công nghệ chính hãng hàng đầu Việt Nam. Cam kết 100% sản phẩm chất lượng, nguồn gốc rõ ràng, bảo hành uy tín.
                    </p>
                    <div class="d-flex gap-2 mt-3">
                        <a href="https://facebook.com" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm rounded-circle text-white social-btn" title="Facebook">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <a href="https://youtube.com" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm rounded-circle text-white social-btn" title="YouTube">
                            <i class="fa-brands fa-youtube"></i>
                        </a>
                        <a href="https://tiktok.com" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm rounded-circle text-white social-btn" title="TikTok">
                            <i class="fa-brands fa-tiktok"></i>
                        </a>
                        <a href="https://zalo.me" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm rounded-circle text-white social-btn" title="Zalo">
                            <i class="fa-solid fa-comment-dots"></i>
                        </a>
                    </div>
                </div>

                <!-- Cột 2: Liên kết nhanh & Chính sách -->
                <div class="col-lg-4 col-md-6">
                    <h6 class="text-white fw-bold text-uppercase mb-3 tracking-wide">Liên kết nhanh</h6>
                    <ul class="list-unstyled footer-links small">
                        <li class="mb-2">
                            <a href="<?php echo $baseUrl; ?>" class="text-white-50 text-decoration-none hover-link">
                                <i class="fa-solid fa-chevron-right me-1 text-primary small"></i> Trang chủ
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo $baseUrl; ?>products.php" class="text-white-50 text-decoration-none hover-link">
                                <i class="fa-solid fa-chevron-right me-1 text-primary small"></i> Tất cả sản phẩm
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo $baseUrl; ?>cart.php" class="text-white-50 text-decoration-none hover-link">
                                <i class="fa-solid fa-chevron-right me-1 text-primary small"></i> Giỏ hàng của bạn
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo $baseUrl; ?>contact.php" class="text-white-50 text-decoration-none hover-link">
                                <i class="fa-solid fa-chevron-right me-1 text-primary small"></i> Liên hệ & Hỗ trợ
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo $baseUrl; ?>contact.php#policy" class="text-white-50 text-decoration-none hover-link">
                                <i class="fa-solid fa-chevron-right me-1 text-primary small"></i> Chính sách bảo hành & Đổi trả
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Cột 3: Thông tin liên hệ & Hỗ trợ -->
                <div class="col-lg-4 col-md-12">
                    <h6 class="text-white fw-bold text-uppercase mb-3 tracking-wide">Thông tin liên hệ</h6>
                    <ul class="list-unstyled text-white-50 small">
                        <li class="d-flex mb-2">
                            <i class="fa-solid fa-location-dot text-primary mt-1 me-2"></i>
                            <span>123 Đường Cầu Giấy, Phường Quan Hoa, Quận Cầu Giấy, Hà Nội</span>
                        </li>
                        <li class="d-flex mb-2">
                            <i class="fa-solid fa-phone text-primary mt-1 me-2"></i>
                            <span>Tổng đài bán hàng: <strong class="text-white">1900 6868</strong> (Miễn phí)</span>
                        </li>
                        <li class="d-flex mb-2">
                            <i class="fa-solid fa-envelope text-primary mt-1 me-2"></i>
                            <span>Email: <a href="mailto:support@shopdientu.vn" class="text-white-50 text-decoration-none">support@shopdientu.vn</a></span>
                        </li>
                        <li class="d-flex mb-2">
                            <i class="fa-solid fa-clock text-primary mt-1 me-2"></i>
                            <span>Giờ làm việc: 08:00 - 21:30 (Cả Thứ Bảy, Chủ Nhật)</span>
                        </li>
                    </ul>
                    <div class="mt-3 p-2 bg-secondary bg-opacity-25 rounded small text-white-50">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i> Chứng nhận an toàn bảo mật thương mại điện tử 100%.
                    </div>
                </div>
            </div>

            <hr class="border-secondary border-opacity-25 my-3">

            <!-- Copyright Row -->
            <div class="row align-items-center small text-white-50">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    &copy; <?php echo date('Y'); ?> <strong>Shop Điện Tử</strong>. Tất cả quyền được bảo lưu. Phát triển bởi PHP & MySQL.
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <span class="me-2">Phương thức thanh toán:</span>
                    <i class="fa-brands fa-cc-visa fs-5 me-1 text-white-50"></i>
                    <i class="fa-brands fa-cc-mastercard fs-5 me-1 text-white-50"></i>
                    <i class="fa-solid fa-money-bill-wave fs-5 text-white-50" title="COD Tiền mặt"></i>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3.0 JS Bundle CDN (Bao gồm Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

    <!-- Font Awesome JS CDN (Hỗ trợ fallback icon) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" integrity="sha512-fD9DI5bZwQxOi7MhYWnnNPlvXdp/2Pj3XSTRrFs5FQa4mizyGLnJcN6tuvUS6LbmgN1ut+XGSABKvjN0H6Aoow==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Biến toàn cục JavaScript dùng cho đường dẫn tĩnh -->
    <script>
        const BASE_URL = '<?php echo $baseUrl; ?>';
    </script>

    <!-- Custom Main JS -->
    <script src="<?php echo $baseUrl; ?>assets/js/main.js"></script>
</body>
</html>
