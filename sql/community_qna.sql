-- SkateShop: community Q&A table
-- Run once in phpMyAdmin or: mysql -u USER -p DATABASE < community_qna.sql

CREATE TABLE IF NOT EXISTS community_qna (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  username VARCHAR(100) NOT NULL,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  admin_answer TEXT NULL,
  status ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
  rejection_reason TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_status_created (status, created_at),
  KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
