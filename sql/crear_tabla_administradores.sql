-- SQL: crear_tabla_administradores.sql
-- Crea la tabla `usuarios` con campos básicos y flags de administrador.
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` VARCHAR(64) DEFAULT NULL,
  `username` VARCHAR(120) NOT NULL,
  `nombre` VARCHAR(255) DEFAULT NULL,
  `apellido_paterno` VARCHAR(150) DEFAULT NULL,
  `apellido_materno` VARCHAR(150) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'user',
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_superadmin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `registered_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_usuarios_email` (`email`),
  UNIQUE KEY `ux_usuarios_username` (`username`),
  UNIQUE KEY `ux_usuarios_adminid` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert ejemplo: administrador local (comentado)
-- INSERT INTO `usuarios` (admin_id, username, nombre, email, password, role, is_admin, registered_at)
-- VALUES ('localadmin', 'admin', 'Admin Local', 'admin@local', '<HASH_DE_PASSWORD>', 'admin', 1, NOW());
