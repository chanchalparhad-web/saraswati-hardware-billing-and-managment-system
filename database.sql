-- ============================================================
-- HARDWARE STORE MANAGEMENT & OFFLINE SALES BILLING SYSTEM
-- Database Schema Script for MySQL / XAMPP (hardware_store_db)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `hardware_store_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hardware_store_db`;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'staff',
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. CATEGORIES TABLE
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. PRODUCTS TABLE
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_code` VARCHAR(50) NOT NULL UNIQUE,
  `barcode` VARCHAR(50) DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `category_id` INT NOT NULL,
  `brand` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `unit` VARCHAR(20) DEFAULT 'Piece',
  `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `mrp` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
  `opening_stock` INT NOT NULL DEFAULT 0,
  `current_stock` INT NOT NULL DEFAULT 0,
  `min_stock` INT NOT NULL DEFAULT 5,
  `location_rack` VARCHAR(50) DEFAULT NULL,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. SUPPLIERS TABLE
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_name` VARCHAR(100) NOT NULL,
  `company_name` VARCHAR(150) NOT NULL,
  `mobile` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `gst_number` VARCHAR(30) DEFAULT NULL,
  `opening_balance` DECIMAL(10,2) DEFAULT 0.00,
  `current_balance` DECIMAL(10,2) DEFAULT 0.00,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. CUSTOMERS TABLE
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `mobile` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `gst_number` VARCHAR(30) DEFAULT NULL,
  `credit_limit` DECIMAL(10,2) DEFAULT 15000.00,
  `outstanding_amount` DECIMAL(10,2) DEFAULT 0.00,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. PURCHASES TABLE
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_no` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT NOT NULL,
  `purchase_date` DATE NOT NULL,
  `invoice_no` VARCHAR(50) DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `due_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('paid', 'partial', 'due') DEFAULT 'due',
  `status` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. PURCHASE ITEMS TABLE
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` INT NOT NULL,
  `purchase_rate` DECIMAL(10,2) NOT NULL,
  `tax_percent` DECIMAL(5,2) DEFAULT 18.00,
  `tax_amount` DECIMAL(10,2) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. SALES TABLE
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT DEFAULT NULL,
  `sale_type` ENUM('offline', 'online') NOT NULL DEFAULT 'offline',
  `sale_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `due_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_mode` ENUM('cash', 'upi', 'card', 'credit') NOT NULL DEFAULT 'cash',
  `payment_status` ENUM('paid', 'partial', 'due') NOT NULL DEFAULT 'paid',
  `status` ENUM('completed', 'cancelled', 'returned') NOT NULL DEFAULT 'completed',
  `notes` TEXT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. SALE ITEMS TABLE
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `qty` INT NOT NULL,
  `rate` DECIMAL(10,2) NOT NULL,
  `tax_percent` DECIMAL(5,2) DEFAULT 18.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. INVENTORY TRANSACTIONS TABLE
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `transaction_type` ENUM('purchase', 'sale', 'purchase_return', 'sale_return', 'adjustment') NOT NULL,
  `reference_id` VARCHAR(50) DEFAULT NULL,
  `qty_change` INT NOT NULL,
  `stock_after` INT NOT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. PAYMENTS TABLE
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_no` VARCHAR(50) NOT NULL UNIQUE,
  `type` ENUM('customer_payment', 'supplier_payment') NOT NULL,
  `entity_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_mode` ENUM('cash', 'upi', 'card', 'cheque', 'bank_transfer') NOT NULL DEFAULT 'cash',
  `reference_no` VARCHAR(100) DEFAULT NULL,
  `payment_date` DATE NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. EXPENSES TABLE
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `payment_method` ENUM('cash', 'upi', 'card', 'bank_transfer') NOT NULL DEFAULT 'cash',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. ONLINE ORDERS TABLE
CREATE TABLE IF NOT EXISTS `online_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_no` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `order_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
  `order_status` ENUM('Pending', 'Confirmed', 'Processing', 'Ready', 'Completed', 'Cancelled') DEFAULT 'Pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. ORDER ITEMS TABLE
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` INT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `online_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. SETTINGS TABLE
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- INITIAL SEED DATA
-- Default Passwords:
-- Admin: admin123  ($2y$10$wT8K7H.fN23uH... standard bcrypt)
-- Cashier: cashier123
-- Customer: customer123
-- ============================================================

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `name`, `phone`, `status`) VALUES
(1, 'admin', 'admin@hardwarestore.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1z6JzC.83u8fVf3WvA7nS1O8z/3eRte', 'admin', 'Store Admin', '9876543210', 1),
(2, 'cashier', 'cashier@hardwarestore.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1z6JzC.83u8fVf3WvA7nS1O8z/3eRte', 'staff', 'Rajesh Cashier', '9876543211', 1),
(3, 'customer', 'customer@gmail.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1z6JzC.83u8fVf3WvA7nS1O8z/3eRte', 'customer', 'Amit Verma', '9876543212', 1)
ON DUPLICATE KEY UPDATE `username`=`username`;

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Plumbing', 'Pipes, taps, valves, fittings, and accessories'),
(2, 'Electrical', 'Wires, switches, MCBs, conduit pipes, lightings'),
(3, 'Tools', 'Hand tools, power tools, hammers, screwdrivers, drills'),
(4, 'Paint', 'Emulsion paints, primers, brushes, rollers, thinners'),
(5, 'Fasteners', 'Screws, bolts, nuts, anchors, nails, washers'),
(6, 'Pipes & Fittings', 'PVC, CPVC, GI pipes, elbows, tees, unions'),
(7, 'Safety Equipment', 'Helmets, gloves, safety shoes, goggles, masks'),
(8, 'Construction Materials', 'Cement, waterproofing, adhesive, putty, sealants'),
(9, 'Hardware Accessories', 'Hinges, door locks, handles, drawer slides, latches')
ON DUPLICATE KEY UPDATE `name`=`name`;

INSERT INTO `suppliers` (`id`, `supplier_name`, `company_name`, `mobile`, `email`, `address`, `gst_number`, `opening_balance`, `current_balance`) VALUES
(1, 'Suresh Patel', 'Supreme Plumbing Supplies Pvt Ltd', '9820011223', 'sales@supreme.com', 'Plot 45, GIDC Industrial Estate, Ahmedabad', '24AAACS1234F1Z1', 0.00, 72400.00),
(2, 'Vikas Gupta', 'Asian Paints Authorized Distr', '9811122334', 'orders@apdistributors.com', 'Building B, Industrial Area, Phase 2, Mumbai', '27AABCA5678G2Z4', 0.00, 18500.00),
(3, 'Ramesh Kumar', 'Bosch & Stanley Power Tools Depot', '9833344556', 'ramesh@powertools.in', '78 Hardware Market, Delhi', '07AAACB9101H3Z9', 0.00, 0.00)
ON DUPLICATE KEY UPDATE `company_name`=`company_name`;

INSERT INTO `customers` (`id`, `name`, `mobile`, `email`, `address`, `gst_number`, `credit_limit`, `outstanding_amount`) VALUES
(1, 'Walk-in Customer', '0000000000', 'walkin@store.com', 'Local Store Direct', NULL, 0.00, 0.00),
(2, 'Rajesh Construction Co', '9844455667', 'rajesh@rajeshconst.com', '12 Civil Lines, Main City', '27AAECR4433P1ZB', 50000.00, 35600.00),
(3, 'Sharma Electricians', '9855566778', 'sharma.elec@gmail.com', 'Shop 4, Electrical Market', NULL, 20000.00, 4500.00),
(4, 'Amit Verma', '9876543212', 'customer@gmail.com', 'Flat 402, Sunshine Apartments', NULL, 10000.00, 0.00)
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Sample Expenses
INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `expense_date`, `payment_method`, `notes`) VALUES
(1, 'Shop Rent', 'Monthly shop premises rent for October', 12000.00, CURRENT_DATE(), 'bank_transfer', 'Owner bank account transfer'),
(2, 'Electricity', 'Electricity bill payment for shop lighting and computer', 2850.00, CURRENT_DATE(), 'upi', 'Torrent power online bill'),
(3, 'Transportation', 'Tempo freight charges for PVC pipes delivery from GIDC', 1400.00, CURRENT_DATE(), 'cash', 'Local transport auto drivers payment')
ON DUPLICATE KEY UPDATE `category`=`category`;

-- Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'Saraswati Hardware'),
('shop_address', '123 Main Hardware Market, Station Road, City'),
('shop_phone', '+91 98765 43210'),
('shop_email', 'contact@abchardware.com'),
('gstin', '27ABCDE1234F1Z5'),
('currency', '₹'),
('invoice_prefix', 'INV-'),
('thermal_printer_width', '80mm'),
('footer_note', 'Thank you for shopping with us! Visit again.')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;
