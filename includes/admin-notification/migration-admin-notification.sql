-- ============================================
-- Migración: Añadir campos de notificación admin
-- Fecha: 2025-12-16
-- ============================================

-- Añadir columnas para registro de notificación a admin
ALTER TABLE kwuf_cdp_presupuestos 
ADD COLUMN admin_notificado TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Si se notificó al admin',
ADD COLUMN admin_notificado_at DATETIME DEFAULT NULL COMMENT 'Fecha de notificación al admin',
ADD INDEX idx_admin_notificado (admin_notificado);

-- Verificación
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'kwuf_cdp_presupuestos'
AND COLUMN_NAME IN ('admin_notificado', 'admin_notificado_at');
