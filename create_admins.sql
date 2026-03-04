-- ============================================
-- Intelligent Road Safety System
-- Admin Table Creation Script
-- ============================================

USE accident_prediction_db;

-- Step 1: Create admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    full_name VARCHAR(100),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
);

-- Step 2: Insert default admin account
-- Password: admin123 (hashed using bcrypt)
INSERT IGNORE INTO admins (username, password, email, full_name) 
VALUES (
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- admin123
    'admin@roadsafety.local', 
    'System Administrator'
);

-- Step 3: Display success message
SELECT 'Admins table created successfully!' AS Status;
SELECT * FROM admins;
