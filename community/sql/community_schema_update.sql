-- ============================================================
-- COMMUNITY MODULE — SCHEMA UPDATE (Migration)
-- Run this file to add missing tables for pending transactions,
-- rate limiting, security logging, and password resets.
-- ============================================================

-- 1. COMMUNITY PENDING REGISTRATIONS
CREATE TABLE IF NOT EXISTS community_pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    national_id VARCHAR(20) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    checkout_request_id VARCHAR(100) DEFAULT NULL,
    mpesa_receipt VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_phone (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. COMMUNITY PENDING DEPOSITS (savings via M-PESA)
CREATE TABLE IF NOT EXISTS community_pending_deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    checkout_request_id VARCHAR(100) DEFAULT NULL,
    mpesa_receipt VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_customer (customer_id),
    FOREIGN KEY (customer_id) REFERENCES community_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES community_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. COMMUNITY PENDING REPAYMENTS (loan repay via M-PESA)
CREATE TABLE IF NOT EXISTS community_pending_repayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    loan_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    checkout_request_id VARCHAR(100) DEFAULT NULL,
    mpesa_receipt VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_customer (customer_id),
    FOREIGN KEY (customer_id) REFERENCES community_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES community_loans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. COMMUNITY RATE LIMITS
CREATE TABLE IF NOT EXISTS community_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(50) NOT NULL,
    identifier VARCHAR(100) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_id (action_type, identifier),
    INDEX idx_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. COMMUNITY SECURITY LOG
CREATE TABLE IF NOT EXISTS community_security_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    customer_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT '0.0.0.0',
    user_agent VARCHAR(255) DEFAULT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_type),
    INDEX idx_customer (customer_id),
    INDEX idx_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. COMMUNITY PASSWORD RESETS
CREATE TABLE IF NOT EXISTS community_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    checkout_request_id VARCHAR(100) DEFAULT NULL,
    verified TINYINT(1) NOT NULL DEFAULT 0,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 15 MINUTE),
    INDEX idx_token (token),
    INDEX idx_customer (customer_id),
    INDEX idx_phone (phone_number),
    FOREIGN KEY (customer_id) REFERENCES community_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. ADD checkout_request_id columns to existing tables if they don't exist
-- (Safe to run multiple times)
ALTER TABLE community_pending_registrations ADD COLUMN IF NOT EXISTS checkout_request_id VARCHAR(100) DEFAULT NULL;
ALTER TABLE community_pending_deposits ADD COLUMN IF NOT EXISTS checkout_request_id VARCHAR(100) DEFAULT NULL;
ALTER TABLE community_pending_repayments ADD COLUMN IF NOT EXISTS checkout_request_id VARCHAR(100) DEFAULT NULL;

-- 8. Clean up stale pending records (older than 1 hour)
-- This can also be run as a cron job
DELETE FROM community_pending_registrations WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
DELETE FROM community_pending_deposits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
DELETE FROM community_pending_repayments WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
DELETE FROM community_rate_limits WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
