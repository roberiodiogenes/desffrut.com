-- ============================================================
-- Migration 17: motivo_cancelamento em pedidos
-- Armazena o motivo quando entregador registra "não entregue".
-- Compatível com MySQL 8+ · Idempotente.
-- ============================================================

ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS motivo_cancelamento VARCHAR(255) NULL DEFAULT NULL AFTER status;

SELECT 'Migration 17 aplicada com sucesso.' AS status;
