-- SQL to create AI support tables: ai_rules and ai_docs
-- Run this against your `inventory` database (MySQL)

CREATE TABLE IF NOT EXISTS ai_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pattern VARCHAR(512) NOT NULL,
  response TEXT NOT NULL,
  created_by VARCHAR(128) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_docs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  path VARCHAR(1024) DEFAULT NULL,
  excerpt TEXT DEFAULT NULL,
  content LONGTEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
