-- ============================================================
-- DESFFRUT — Migration 20: Auditoria de Abertura/Fechamento de Caixa
-- A tabela `caixas` já registra quem ABRIU (usuario_id) e quando
-- (aberto_em/fechado_em), mas não quem FECHOU nem o valor contado
-- fisicamente na conferência — necessário para auditar sobra/falta
-- de caixa quando o fechamento é feito por outra pessoa.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE caixas
    ADD COLUMN fechado_por INT UNSIGNED NULL
        COMMENT 'Usuário que fechou o caixa (pode ser diferente de quem abriu)'
        AFTER fechado_em,
    ADD COLUMN total_contado DECIMAL(10,2) NULL
        COMMENT 'Valor em dinheiro contado fisicamente na conferência de fechamento'
        AFTER fechado_por,
    ADD CONSTRAINT fk_caixas_fechado_por FOREIGN KEY (fechado_por) REFERENCES usuarios(id),
    ADD INDEX idx_fechado_por (fechado_por);
