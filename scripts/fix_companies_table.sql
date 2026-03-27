-- Fix companies table to ensure all fields exist

-- Add whatsapp column if it doesn't exist
ALTER TABLE companies ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) DEFAULT NULL;

-- Add logo_url column if it doesn't exist
ALTER TABLE companies ADD COLUMN IF NOT EXISTS logo_url VARCHAR(255) DEFAULT NULL;

-- Add favicon_url column if it doesn't exist
ALTER TABLE companies ADD COLUMN IF NOT EXISTS favicon_url VARCHAR(255) DEFAULT NULL;

-- Ensure email_settings table exists
CREATE TABLE IF NOT EXISTS email_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    smtp_host VARCHAR(255) DEFAULT NULL,
    smtp_port INT DEFAULT 587,
    smtp_username VARCHAR(255) DEFAULT NULL,
    smtp_password TEXT DEFAULT NULL,
    smtp_encryption ENUM('none', 'tls', 'ssl') DEFAULT 'tls',
    from_email VARCHAR(255) DEFAULT NULL,
    from_name VARCHAR(255) DEFAULT NULL,
    reply_to_email VARCHAR(255) DEFAULT NULL,
    use_smtp TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_email (company_id)
);

-- Ensure api_settings table exists
CREATE TABLE IF NOT EXISTS api_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    api_name VARCHAR(50) NOT NULL,
    api_url VARCHAR(500) DEFAULT NULL,
    api_key TEXT DEFAULT NULL,
    api_secret TEXT DEFAULT NULL,
    additional_config JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_api (company_id, api_name)
);

-- Ensure bank_accounts table exists
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    bank_name VARCHAR(255) NOT NULL,
    account_type ENUM('corriente', 'ahorros', 'detraccion') NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_holder VARCHAR(255) NOT NULL,
    currency CHAR(3) DEFAULT 'PEN',
    cci VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Ensure notifications table exists
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT DEFAULT NULL,
    related_url VARCHAR(500) DEFAULT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_user_company (user_id, company_id),
    INDEX idx_created_at (created_at)
);

-- Ensure activity_logs table exists
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT DEFAULT NULL,
    description TEXT NOT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_user_company (user_id, company_id),
    INDEX idx_created_at (created_at),
    INDEX idx_entity (entity_type, entity_id)
);