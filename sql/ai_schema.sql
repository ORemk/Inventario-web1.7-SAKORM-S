-- ai_schema.sql
-- Tablas para AI local (ai_docs, ai_rules)

CREATE TABLE IF NOT EXISTS `ai_docs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `path` VARCHAR(512) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `excerpt` TEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_title` (`title`(150))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_rules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pattern` VARCHAR(512) NOT NULL COMMENT 'Regex or simple text to match (case-insensitive)',
  `response` LONGTEXT NOT NULL COMMENT 'Predefined response or HTML',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) DEFAULT 'admin',
  PRIMARY KEY (`id`),
  INDEX `idx_pattern` (`pattern`(250))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
