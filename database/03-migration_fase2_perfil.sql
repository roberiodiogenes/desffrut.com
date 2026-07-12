-- ============================================================
-- DESFFRUT — Migration Fase 2: Perfil do Cliente
-- Execute no phpMyAdmin ou via MySQL CLI:
--   mysql -u root -p desffrut_dev < database/migration_fase2_perfil.sql
-- ============================================================

SET NAMES utf8mb4;

-- ─── Campos adicionais em `usuarios` ────────────────────────────────────────
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS telefone          VARCHAR(20)      NULL AFTER cpf,
    ADD COLUMN IF NOT EXISTS whatsapp          VARCHAR(20)      NULL AFTER telefone,
    ADD COLUMN IF NOT EXISTS pontos_fidelidade INT UNSIGNED NOT NULL DEFAULT 0 AFTER whatsapp,
    ADD COLUMN IF NOT EXISTS foto_perfil       VARCHAR(255)     NULL AFTER pontos_fidelidade;

-- ─── Campo de pontos ganhos por venda ───────────────────────────────────────
ALTER TABLE vendas
    ADD COLUMN IF NOT EXISTS pontos_ganhos INT UNSIGNED NOT NULL DEFAULT 0 AFTER forma_pagamento,
    ADD COLUMN IF NOT EXISTS total_final   DECIMAL(10,2) GENERATED ALWAYS AS (total - desconto) STORED AFTER pontos_ganhos;

-- ─── Verificação ─────────────────────────────────────────────────────────────
SELECT 'Migration aplicada com sucesso.' AS status;
