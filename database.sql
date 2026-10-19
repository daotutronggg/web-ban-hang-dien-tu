-- ====================================================================
-- SHOP ĐIỆN TỬ - DATABASE INITIALIZATION SCRIPT
-- ====================================================================
-- Tên CSDL: shop_dien_tu
-- Bộ mã: utf8mb4 (Hỗ trợ tiếng Việt đầy đủ và ký tự đặc biệt)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `shop_dien_tu`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `shop_dien_tu`;

-- --------------------------------------------------------------------
-- 1. BẢNG USERS (Người dùng & Quản trị viên)
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `order_details`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 2. BẢNG CATEGORIES (Danh mục sản phẩm)
-- --------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 3. BẢNG PRODUCTS (Sản phẩm)
-- --------------------------------------------------------------------
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `quantity` INT NOT NULL DEFAULT 0,
    `image` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_products_category` (`category_id`),
    INDEX `idx_products_name` (`name`),
    INDEX `idx_products_price` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 4. BẢNG ORDERS (Đơn hàng)
-- --------------------------------------------------------------------
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'confirmed', 'shipping', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `shipping_address` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `fullname` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_orders_user` (`user_id`),
    INDEX `idx_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 5. BẢNG ORDER_DETAILS (Chi tiết đơn hàng)
-- --------------------------------------------------------------------
CREATE TABLE `order_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_order_details_order` (`order_id`),
    INDEX `idx_order_details_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- DỮ LIỆU MẪU (SAMPLE DATA)
-- ====================================================================

-- 1. Thêm tài khoản người dùng
-- Mật khẩu mặc định cho tất cả tài khoản mẫu là: admin123
-- Hash chuẩn sinh bởi password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `email`, `phone`, `address`, `role`) VALUES
(1, 'admin', '$2y$10$il9iJULRoOJWqY4L9PmOGeL32WJnLdPYWjtWtS3KnxWBXxWK6dQnG', 'Quản Trị Viên', 'admin@shopdientu.vn', '0901234567', 'Số 1 Đại Cồ Việt, Hai Bà Trưng, Hà Nội', 'admin'),
(2, 'nguyenvana', '$2y$10$il9iJULRoOJWqY4L9PmOGeL32WJnLdPYWjtWtS3KnxWBXxWK6dQnG', 'Nguyễn Văn A', 'nguyenvana@gmail.com', '0912345678', '123 Đường Cầu Giấy, Cầu Giấy, Hà Nội', 'customer'),
(3, 'tranthib', '$2y$10$il9iJULRoOJWqY4L9PmOGeL32WJnLdPYWjtWtS3KnxWBXxWK6dQnG', 'Trần Thị B', 'tranthib@gmail.com', '0987654321', '456 Lê Lợi, Quận 1, TP. Hồ Chí Minh', 'customer');

-- 2. Thêm 5 danh mục sản phẩm
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Điện thoại', 'Điện thoại thông minh, smartphone chính hãng mới nhất từ Apple, Samsung, Xiaomi,...'),
(2, 'Laptop', 'Máy tính xách tay cao cấp, laptop gaming đồ họa, văn phòng mỏng nhẹ.'),
(3, 'Máy tính bảng', 'Tablet iPad, Galaxy Tab phục vụ học tập, giải trí và làm việc chuyên nghiệp.'),
(4, 'Phụ kiện', 'Cáp sạc nhanh, củ sạc GaN, pin dự phòng, ốp lưng, chuột và bàn phím máy tính.'),
(5, 'Tai nghe', 'Tai nghe không dây True Wireless, tai nghe chụp tai chống ồn chủ động cao cấp.');

-- 3. Thêm danh sách sản phẩm mẫu (14 sản phẩm thực tế kèm giá VND)
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `quantity`, `image`) VALUES
(1, 1, 'iPhone 15 Pro Max 256GB', 'Thiết kế khung viền Titan siêu bền nhẹ, chip Apple A17 Pro cực đỉnh, camera zoom quang 5x chuyên nghiệp, cổng USB-C tốc độ cao.', 29990000.00, 25, 'iphone-15-pro-max.jpg'),
(2, 1, 'Samsung Galaxy S24 Ultra 512GB', 'Màn hình Dynamic AMOLED 2X 6.8 inch, tích hợp bút S-Pen, vi xử lý Snapdragon 8 Gen 3 for Galaxy, công nghệ AI thông minh vượt trội.', 31490000.00, 20, 'galaxy-s24-ultra.jpg'),
(3, 1, 'Xiaomi 14 256GB Chính Hãng', 'Ống kính quang học Leica Summilux huyền thoại, màn hình 1.5K 120Hz mượt mà, sạc siêu nhanh 90W HyperCharge.', 18990000.00, 35, 'xiaomi-14.jpg'),
(4, 2, 'MacBook Pro 14 M3 (16GB - 512GB)', 'Chip Apple M3 mạnh mẽ với GPU thế hệ mới, màn hình Liquid Retina XDR sáng 1600 nits, thời lượng pin lên đến 22 giờ liên tục.', 44990000.00, 15, 'macbook-pro-14-m3.jpg'),
(5, 2, 'Laptop ASUS ROG Zephyrus G16', 'Laptop gaming cao cấp màn OLED 240Hz, Intel Core Ultra 9, card đồ họa NVIDIA RTX 4070 8GB, thiết kế nhôm nguyên khối sang trọng.', 49990000.00, 10, 'rog-zephyrus-g16.jpg'),
(6, 2, 'Laptop Dell XPS 13 Plus 9320', 'Thiết kế tương lai với hàng phím cảm ứng điện dung, màn hình cảm ứng OLED 3.5K sắc nét, siêu mỏng nhẹ chỉ 1.23kg.', 36500000.00, 12, 'dell-xps-13.jpg'),
(7, 3, 'iPad Pro M4 11 inch Wi-Fi 256GB', 'Đột phá với màn hình Ultra Retina XDR Tandem OLED, chip Apple M4 đỉnh cao sức mạnh, độ mỏng ấn tượng chỉ 5.3mm.', 27490000.00, 18, 'ipad-pro-m4.jpg'),
(8, 3, 'Samsung Galaxy Tab S9 Ultra 256GB', 'Màn hình khủng 14.6 inch Dynamic AMOLED 2X, chuẩn kháng nước bụi IP68, hỗ trợ chế độ làm việc DeX thông minh.', 23990000.00, 14, 'galaxy-tab-s9.jpg'),
(9, 5, 'Tai nghe Sony WH-1000XM5 Chống Ồn', 'Khả năng chống ồn hàng đầu với bộ xử lý V1 và QN1, thời lượng pin 30 giờ, micro đàm thoại rõ nét chuẩn Hi-Res Audio.', 7490000.00, 40, 'sony-wh1000xm5.jpg'),
(10, 5, 'Tai nghe Apple AirPods Pro 2 (USB-C)', 'Chip Apple H2, chống ồn chủ động gấp 2 lần, chế độ xuyên âm thích ứng âm thanh không gian cá nhân hóa.', 5690000.00, 50, 'airpods-pro-2.jpg'),
(11, 4, 'Bàn phím cơ Logitech MX Mechanical', 'Bàn phím cơ không dây văn phòng cao cấp, switch Tactile Quiet êm ái, kết nối 3 thiết bị đồng thời qua Bluetooth/Bolt.', 3590000.00, 30, 'logitech-mx-mechanical.jpg'),
(12, 4, 'Chuột không dây Logitech MX Master 3S', 'Cảm biến 8000 DPI lướt êm trên mọi mặt phẳng kính, cuộn siêu tốc MagSpeed cuộn 1.000 dòng/giây, click êm giảm 90% tiếng ồn.', 2190000.00, 45, 'logitech-mx-master-3s.jpg'),
(13, 4, 'Củ sạc nhanh Anker GaNPrime 65W 3 Cổng', 'Công nghệ GaNPrime độc quyền nhỏ gọn, 2 cổng USB-C và 1 USB-A, sạc nhanh cùng lúc cho Laptop, Tablet và Smartphone.', 1150000.00, 60, 'anker-gan-65w.jpg'),
(14, 4, 'Pin dự phòng Baseus Blade 100W 20000mAh', 'Công suất sạc nhanh 100W Power Delivery sạc được cho MacBook và laptop, thiết kế siêu mỏng có màn hình LED báo dung lượng.', 1290000.00, 35, 'baseus-blade-100w.jpg');

-- 4. Thêm đơn hàng mẫu để kiểm thử hệ thống
INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `status`, `shipping_address`, `phone`, `fullname`, `created_at`) VALUES
(1, 2, 35680000.00, 'completed', '123 Đường Cầu Giấy, Cầu Giấy, Hà Nội', '0912345678', 'Nguyễn Văn A', '2026-10-10 09:30:00'),
(2, 3, 5690000.00, 'shipping', '456 Lê Lợi, Quận 1, TP. Hồ Chí Minh', '0987654321', 'Trần Thị B', '2026-10-18 14:15:00');

-- 5. Thêm chi tiết đơn hàng mẫu
INSERT INTO `order_details` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 1, 1, 29990000.00),
(2, 1, 10, 1, 5690000.00),
(3, 2, 10, 1, 5690000.00);
