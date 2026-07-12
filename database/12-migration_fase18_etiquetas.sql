-- ============================================================
-- DESFFRUT — Migration Fase 18: Etiquetas Térmicas
-- Adiciona codigo_interno à tabela produtos
-- Execute APÓS as migrations anteriores.
-- ============================================================

SET NAMES utf8mb4;

-- ─── Coluna codigo_interno na tabela produtos ─────────────────────────────────
ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS codigo_interno VARCHAR(12) NULL UNIQUE COMMENT 'Código interno PRD-XXXXXX gerado automaticamente';

-- Popula codigo_interno para produtos já cadastrados (sem código)
-- Formato: PRD-000001, PRD-000002, ...
UPDATE produtos
SET codigo_interno = CONCAT('PRD-', LPAD(id, 6, '0'))
WHERE codigo_interno IS NULL;

-- Torna a coluna NOT NULL após populá-la
ALTER TABLE produtos
    MODIFY COLUMN codigo_interno VARCHAR(12) NOT NULL UNIQUE COMMENT 'Código interno PRD-XXXXXX gerado automaticamente';

-- Índice para busca rápida por código interno (PDV scanner)
ALTER TABLE produtos
    ADD INDEX IF NOT EXISTS idx_codigo_interno (codigo_interno);
