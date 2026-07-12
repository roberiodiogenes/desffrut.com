-- ============================================================
-- DESFFRUT — Migration 22: Justificativa de abertura/fechamento de caixa
-- Gerente e dono (super_admin) podem abrir ou fechar o caixa de QUALQUER
-- loja (ex.: caixa esquecido aberto, ou falta de operador). Quando quem
-- executa a ação não é o operador de caixa (role 'caixa'), o sistema passa
-- a exigir uma justificativa, além de já registrar quem fez (usuario_id na
-- abertura, fechado_por na migration 20).
--
-- DEPENDE DA MIGRATION 20 (usa a coluna total_contado, criada por ela, como
-- referência de posição). Rode a migration 20 antes desta, se ainda não rodou.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE caixas
    ADD COLUMN justificativa_abertura TEXT NULL
        COMMENT 'Motivo informado quando o caixa e aberto por alguem que nao e o operador (gerente/dono cobrindo ausencia)'
        AFTER usuario_id,
    ADD COLUMN justificativa_fechamento TEXT NULL
        COMMENT 'Motivo informado quando o caixa e fechado por alguem que nao e o operador'
        AFTER total_contado;
