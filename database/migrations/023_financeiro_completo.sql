-- ============================================================
-- Migration 023 — Financeiro Completo
-- Desffrut · 2026-06-26
-- Fix: colunas FK devem ser INT UNSIGNED para bater com lojas.id e usuarios.id
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Movimentações financeiras
--    Cobre: retiradas/pro-labore, despesas extras, transferências, custo de viagem CEASA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS financeiro_movimentacoes (
    id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    loja_id         INT UNSIGNED   NOT NULL,
    tipo            ENUM('retirada','despesa_extra','transferencia','custo_ceasa') NOT NULL,
    subtipo         VARCHAR(60)    NULL
        COMMENT 'pro_labore | investimento | transferencia_pessoal | limpeza | manutencao | terceirizado | combustivel | pedagio | outros',
    descricao       VARCHAR(255)   NOT NULL,
    valor           DECIMAL(10,2)  NOT NULL,
    data            DATE           NOT NULL,
    loja_destino_id INT UNSIGNED   NULL COMMENT 'Para transferências entre lojas',
    conta_bancaria  VARCHAR(120)   NULL COMMENT 'Banco/conta destino (transferência)',
    referencia_id   INT UNSIGNED   NULL COMMENT 'FK opcional (ex: ceasa_rotas.id)',
    observacoes     TEXT           NULL,
    criado_por      INT UNSIGNED   NOT NULL,
    created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id)    REFERENCES lojas(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Pagamentos de auxiliares CEASA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auxiliares_pagamentos (
    id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    colaborador_id  INT            NOT NULL COMMENT 'FK → ceasa_colaboradores.id (INT signed)',
    valor           DECIMAL(10,2)  NOT NULL,
    periodo_ini     DATE           NOT NULL,
    periodo_fim     DATE           NOT NULL,
    tipo            ENUM('semanal','quinzenal','mensal') NOT NULL DEFAULT 'semanal',
    data_pagamento  DATE           NOT NULL,
    forma           ENUM('dinheiro','pix','transferencia') NOT NULL DEFAULT 'dinheiro',
    observacoes     TEXT           NULL,
    criado_por      INT UNSIGNED   NOT NULL,
    created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES ceasa_colaboradores(id),
    FOREIGN KEY (criado_por)     REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Contas a receber
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contas_receber (
    id               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    loja_id          INT UNSIGNED   NOT NULL,
    descricao        VARCHAR(255)   NOT NULL,
    valor            DECIMAL(10,2)  NOT NULL,
    data_vencimento  DATE           NOT NULL,
    data_recebimento DATE           NULL,
    status           ENUM('pendente','recebido','vencido','cancelado') NOT NULL DEFAULT 'pendente',
    categoria        ENUM('fiado','cheque','pix','transferencia','cartao','outros') NOT NULL DEFAULT 'outros',
    cliente_nome     VARCHAR(120)   NULL,
    observacoes      TEXT           NULL,
    recebido_por     INT UNSIGNED   NULL,
    criado_por       INT UNSIGNED   NOT NULL,
    created_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id)    REFERENCES lojas(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Metas e orçamento mensal
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fin_metas (
    id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    loja_id     INT UNSIGNED   NULL COMMENT 'NULL = consolidado (todas as lojas)',
    mes_ref     VARCHAR(7)     NOT NULL COMMENT 'YYYY-MM',
    tipo        ENUM('faturamento','despesa_total','despesa_categoria') NOT NULL,
    categoria   VARCHAR(60)    NULL COMMENT 'Quando tipo=despesa_categoria',
    valor_meta  DECIMAL(10,2)  NOT NULL,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_meta (loja_id, mes_ref, tipo, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
