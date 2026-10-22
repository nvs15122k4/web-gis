-- WebGIS Sông Hậu — CSDL ứng dụng (MySQL 8+)
-- Chạy: mysql -u root -p < database/sql/mysql.sql
-- Lưu ý: dùng file này HOẶC "php artisan migrate", không dùng cả hai trên cùng một DB.

CREATE DATABASE IF NOT EXISTS webgis_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE webgis_app;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'viewer', -- 'admin' | 'viewer'
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name TEXT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX personal_access_tokens_tokenable_idx (tokenable_type, tokenable_id),
    INDEX personal_access_tokens_expires_at_idx (expires_at)
) ENGINE=InnoDB;

CREATE TABLE threshold_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL UNIQUE,
    value VARCHAR(255) NOT NULL,
    description VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT threshold_configs_updated_by_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT activity_logs_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Dữ liệu khởi tạo (admin dev: admin@webgis.test / password — đổi khi triển khai thật)
INSERT INTO users (name, email, password, role, created_at, updated_at)
VALUES ('Admin', 'admin@webgis.test', '$2y$12$uwpOdYJRtH/1OcUFe6NUcu3UNADiCmbvvo/7AM8Y/5P9SEIopMnom', 'admin', NOW(), NOW());

INSERT INTO threshold_configs (`key`, value, description, created_at, updated_at)
VALUES ('min_erosion_rate', '1.0', 'Tốc độ lùi bờ tối thiểu (m/năm) để hiển thị hotspot trên UI', NOW(), NOW());
