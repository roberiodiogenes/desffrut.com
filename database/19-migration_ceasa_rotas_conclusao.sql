-- ============================================================
-- Migration 19: CEASA — campos de conclusão de rota
-- Adiciona colunas para registrar o resultado de cada rota:
--   houve_atraso, motivo_atraso, observacoes_conclusao, concluida_em
-- Execute no banco desffrut_dev
-- ============================================================

USE `desffrut_dev`;

ALTER TABLE ceasa_rotas
    ADD COLUMN houve_atraso          TINYINT(1)   NOT NULL DEFAULT 0   AFTER status,
    ADD COLUMN motivo_atraso         VARCHAR(500)          DEFAULT NULL AFTER houve_atraso,
    ADD COLUMN observacoes_conclusao TEXT                  DEFAULT NULL AFTER motivo_atraso,
    ADD COLUMN concluida_em          DATETIME              DEFAULT NULL AFTER observacoes_conclusao;

SELECT 'Migration 19 aplicada com sucesso.' AS status;
