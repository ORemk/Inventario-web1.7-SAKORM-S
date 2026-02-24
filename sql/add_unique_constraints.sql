-- SQL script to add UNIQUE constraints to enforce no-duplicate records
-- Run this on your DB (adjust schema/table names if needed). Backup DB before running.

-- productos: unique on lower(nombre) is not directly supported across all engines,
-- so create functional index if server supports it, otherwise use case-insensitive collation.

-- For MySQL (InnoDB) recommended approach: add UNIQUE indexes on normalized columns
-- If your 'nombre' column uses case-insensitive collation (utf8mb4_general_ci) then a plain UNIQUE works.

ALTER TABLE productos
  ADD UNIQUE KEY ux_productos_codigo (codigo);

-- If nombre is case-insensitive by collation, add unique index:
ALTER TABLE productos
  ADD UNIQUE KEY ux_productos_nombre (nombre);

-- categorias
ALTER TABLE categorias
  ADD UNIQUE KEY ux_categorias_nombre (nombre);

-- clientes: prefer unique email; nombre unique optional
ALTER TABLE clientes
  ADD UNIQUE KEY ux_clientes_email (email);

-- Optional: enforce unique nombre for clientes (uncomment if desired)
-- ALTER TABLE clientes
--   ADD UNIQUE KEY ux_clientes_nombre (nombre);

-- Notes:
-- 1) If any duplicates already exist, these ALTER statements will fail. Remove duplicates first.
-- 2) For case-insensitive uniqueness in MySQL, ensure columns use a CI collation (e.g. utf8mb4_unicode_ci).
-- 3) For PostgreSQL, use: CREATE UNIQUE INDEX ux_products_lower_name ON productos ((lower(nombre)));

COMMIT;
