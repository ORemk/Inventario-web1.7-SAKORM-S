-- SQL para localizar duplicados antes de aplicar índices UNIQUE
-- Ejecutar en la base de datos y revisar resultados antes de borrar/alterar.

-- Productos: duplicados por codigo (no nulos/vacíos)
SELECT codigo, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
FROM productos
WHERE codigo IS NOT NULL AND TRIM(codigo) <> ''
GROUP BY codigo
HAVING cnt > 1;

-- Productos: duplicados por nombre (normalizado en minúsculas)
SELECT LOWER(TRIM(nombre)) AS nombre_norm, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
FROM productos
GROUP BY nombre_norm
HAVING cnt > 1;

-- Categorías: duplicados por nombre
SELECT LOWER(TRIM(nombre)) AS nombre_norm, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
FROM categorias
GROUP BY nombre_norm
HAVING cnt > 1;

-- Clientes: duplicados por email (cuando exista)
SELECT LOWER(TRIM(email)) AS email_norm, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
FROM clientes
WHERE email IS NOT NULL AND TRIM(email) <> ''
GROUP BY email_norm
HAVING cnt > 1;

-- Clientes: duplicados por nombre (si no usan email)
SELECT LOWER(TRIM(nombre)) AS nombre_norm, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
FROM clientes
GROUP BY nombre_norm
HAVING cnt > 1;

-- Nota:
-- 1) Revise los resultados y confirme qué registros conservar.
-- 2) No ejecutar ALTER TABLE con índices UNIQUE hasta haber eliminado/mergeado duplicados.
-- 3) Use el script PHP en ../tools/apply_unique_constraints.php para ejecutar un plan seguro (primero ver, luego aplicar con --apply).
