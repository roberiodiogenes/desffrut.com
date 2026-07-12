-- ============================================================
-- DESFFRUT — Migration Fase 6: Tele-Entrega & Logística
-- Execute no phpMyAdmin ou via MySQL CLI:
--   mysql -u root -p desffrut_dev < database/04-migration_fase6_pedidos.sql
-- ============================================================

SET NAMES utf8mb4;

-- ─── Campos adicionais em `usuarios` (endereço para entrega) ────────────────
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS endereco    VARCHAR(255) NULL AFTER foto_perfil,
    ADD COLUMN IF NOT EXISTS numero      VARCHAR(20)  NULL AFTER endereco,
    ADD COLUMN IF NOT EXISTS complemento VARCHAR(100) NULL AFTER numero,
    ADD COLUMN IF NOT EXISTS bairro      VARCHAR(100) NULL AFTER complemento,
    ADD COLUMN IF NOT EXISTS cidade      VARCHAR(100) NULL AFTER bairro,
    ADD COLUMN IF NOT EXISTS cep         VARCHAR(10)  NULL AFTER cidade;

-- ─── Campos adicionais em `pedidos` ─────────────────────────────────────────
ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS forma_pagamento ENUM(
        'dinheiro_na_entrega',
        'cartao_debito_entrega',
        'cartao_credito_entrega',
        'pix'
    ) NULL AFTER total,
    ADD COLUMN IF NOT EXISTS troco_para  DECIMAL(10,2) NULL AFTER forma_pagamento,
    ADD COLUMN IF NOT EXISTS numero      VARCHAR(20)   NULL AFTER endereco_entrega,
    ADD COLUMN IF NOT EXISTS complemento VARCHAR(100)  NULL AFTER numero,
    ADD COLUMN IF NOT EXISTS bairro      VARCHAR(100)  NULL AFTER complemento,
    ADD COLUMN IF NOT EXISTS telefone    VARCHAR(20)   NULL AFTER bairro,
    ADD COLUMN IF NOT EXISTS pontos_ganhos INT UNSIGNED NOT NULL DEFAULT 0 AFTER total;

-- ─── Verificação ─────────────────────────────────────────────────────────────
SELECT 'Migration Fase 6 aplicada com sucesso.' AS status;
