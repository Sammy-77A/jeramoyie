-- ============================================================
-- COMMUNITY MODULE — Withdrawal Requests Migration
-- Creates the community_withdrawal_requests table for
-- admin-managed withdrawal approvals.
-- ============================================================

CREATE TABLE IF NOT EXISTS community_withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    reference_code VARCHAR(50) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_comment TEXT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status),
    INDEX idx_ref (reference_code),
    FOREIGN KEY (customer_id) REFERENCES community_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES community_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
