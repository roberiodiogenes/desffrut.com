-- ============================================================
-- DESFFRUT — Migration 15: Adiciona foto_perfil à tabela usuarios
-- Idempotente: usa information_schema para verificar se a coluna já existe.
-- ============================================================

SET NAMES utf8mb4;

SET @col_existe = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
      AND table_name   = 'usuarios'
      AND column_name  = 'foto_perfil'
);

SET @ddl = IF(
    @col_existe = 0,
    'ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) NULL DEFAULT NULL AFTER whatsapp',
    'SELECT ''foto_perfil já existe'' AS info'
);
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;
