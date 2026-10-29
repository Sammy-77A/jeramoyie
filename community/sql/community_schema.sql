-- ============================================================
-- PRODUCTS TO THE COMMUNITY — DATABASE SCHEMA
-- Database: jeramoyie1
-- ============================================================

-- 1. COMMUNITY CUSTOMERS
CREATE TABLE IF NOT EXISTS community_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    national_id VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(150) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_phone (phone_number),
    INDEX idx_national_id (national_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. COMMUNITY PRODUCTS
CREATE TABLE IF NOT EXISTS community_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    product_type ENUM('saving_scheme','okoa_loan','custom') NOT NULL DEFAULT 'custom',
    description TEXT,
    minimum_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    maximum_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    penalty_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    duration_in_months INT NOT NULL DEFAULT 1,
    requires_approval TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (product_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. COMMUNITY SAVINGS
CREATE TABLE IF NOT EXISTS community_savings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_type ENUM('deposit','withdrawal') NOT NULL,
    reference_code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_product (product_id),
    INDEX idx_ref (reference_code),
    FOREIGN KEY (customer_id) REFERENCES community_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES community_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. COMMUNITY LOANS
CREATE TABLE IF NOT EXISTS community_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    interest_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_repayable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    penalty_accrued DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','approved','rejected','completed','defaulted') NOT NULL DEFAULT 'pending',
    approved_by INT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status),
    INDEX idx_due (due_date),
    FOREIGN KEY (customer_id) REFERENCES community_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES community_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. COMMUNITY LOAN REPAYMENTS
CREATE TABLE IF NOT EXISTS community_loan_repayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'mpesa',
    reference_code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_loan (loan_id),
    FOREIGN KEY (loan_id) REFERENCES community_loans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. COMMUNITY LOAN APPROVALS
CREATE TABLE IF NOT EXISTS community_loan_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    approved_by INT NOT NULL,
    decision ENUM('approved','rejected') NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_loan (loan_id),
    FOREIGN KEY (loan_id) REFERENCES community_loans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. COMMUNITY AUDIT LOGS
CREATE TABLE IF NOT EXISTS community_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','customer') NOT NULL DEFAULT 'admin',
    user_id INT NOT NULL,
    action VARCHAR(200) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45) DEFAULT '0.0.0.0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_type, user_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. COMMUNITY LOGIN ATTEMPTS (rate limiting)
CREATE TABLE IF NOT EXISTS community_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_time (phone_number, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. FINANCE SUMMARY VIEW
CREATE OR REPLACE VIEW community_finance_summary AS
SELECT
    (SELECT COALESCE(SUM(CASE WHEN transaction_type='deposit' THEN amount ELSE -amount END), 0) FROM community_savings) AS total_savings,
    (SELECT COALESCE(SUM(amount), 0) FROM community_loans WHERE status IN ('approved','completed','defaulted')) AS total_loans_issued,
    (SELECT COALESCE(SUM(interest_amount), 0) FROM community_loans WHERE status IN ('approved','completed','defaulted')) AS total_interest_earned,
    (SELECT COALESCE(SUM(penalty_accrued), 0) FROM community_loans WHERE penalty_accrued > 0) AS total_penalties_collected,
    (SELECT COUNT(*) FROM community_loans WHERE status = 'approved') AS active_loans_count,
    (SELECT COUNT(*) FROM community_loans WHERE status = 'defaulted') AS defaulted_loans_count;

-- 10. SAMPLE SEED DATA
INSERT INTO community_products (product_name, product_type, description, minimum_amount, maximum_amount, interest_rate, penalty_rate, duration_in_months, requires_approval, is_active) VALUES
('Jera Savings', 'saving_scheme', 'Flexible savings account for community members. No minimum lock-in period.', 100.00, 1000000.00, 0.00, 0.00, 0, 0, 1),
('Okoa Loan - Standard', 'okoa_loan', 'Quick emergency loan up to KSh 50,000 with 10% interest over 3 months.', 500.00, 50000.00, 10.00, 5.00, 3, 1, 1),
('Okoa Loan - Premium', 'okoa_loan', 'Higher-tier loan up to KSh 200,000 with 8% interest over 6 months.', 5000.00, 200000.00, 8.00, 3.00, 6, 1, 1),
('Business Boost', 'custom', 'Business development loan with competitive rates over 12 months.', 10000.00, 500000.00, 12.00, 5.00, 12, 1, 1);
