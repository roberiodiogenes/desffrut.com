-- ============================================================
-- DESFFRUT — Migration Fase 7: ERP Administrativo
-- Execute no phpMyAdmin ou via MySQL CLI:
--   mysql -u root -p desffrut_dev < database/05-migration_fase7_erp.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Campos adicionais em `funcionarios` ────────────────────────────────────
ALTER TABLE funcionarios
    ADD COLUMN IF NOT EXISTS cpf           VARCHAR(14)  NULL AFTER usuario_id,
    ADD COLUMN IF NOT EXISTS telefone      VARCHAR(20)  NULL AFTER cpf,
    ADD COLUMN IF NOT EXISTS tipo_contrato ENUM('clt','pj','autonomo','estagio')
                                           NOT NULL DEFAULT 'clt' AFTER cargo,
    ADD COLUMN IF NOT EXISTS carga_horaria TINYINT UNSIGNED NOT NULL DEFAULT 8
                                           COMMENT 'Horas diárias contratadas' AFTER tipo_contrato,
    ADD COLUMN IF NOT EXISTS observacoes   TEXT NULL AFTER ativo;

-- ─── Tabela: REGISTRO_PONTO ─────────────────────────────────────────────────
-- Cada linha = um batimento de ponto (entrada, saída, intervalo)
CREATE TABLE IF NOT EXISTS registro_ponto (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT UNSIGNED NOT NULL,
    tipo           ENUM('entrada','saida','entrada_intervalo','saida_intervalo')
                   NOT NULL,
    registrado_em  DATETIME NOT NULL,
    registrado_por INT UNSIGNED NULL,    -- NULL = sistema; preenchido = rh registrou manualmente
    observacao     VARCHAR(255) NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_func_data (funcionario_id, registrado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tabela: FOLHA_PAGAMENTO ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS folha_pagamento (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT UNSIGNED NOT NULL,
    mes_referencia CHAR(7) NOT NULL         COMMENT 'Formato: YYYY-MM',
    salario_base   DECIMAL(10,2) NOT NULL,
    horas_extras   DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    valor_extras   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descontos      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_liquido  DECIMAL(10,2) GENERATED ALWAYS AS
                   (salario_base + valor_extras - descontos) STORED,
    status         ENUM('calculado','pago') NOT NULL DEFAULT 'calculado',
    pago_em        DATE NULL,
    observacoes    TEXT NULL,
    criado_por     INT UNSIGNED NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_func_mes (funcionario_id, mes_referencia),
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id),
    FOREIGN KEY (criado_por)     REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Campos adicionais em `contas_pagar` ────────────────────────────────────
ALTER TABLE contas_pagar
    ADD COLUMN IF NOT EXISTS recorrente      TINYINT(1) NOT NULL DEFAULT 0
                                             COMMENT '1 = lançamento mensal recorrente' AFTER status,
    ADD COLUMN IF NOT EXISTS observacoes     TEXT NULL AFTER recorrente,
    ADD COLUMN IF NOT EXISTS criado_por      INT UNSIGNED NULL AFTER observacoes;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Verificação ─────────────────────────────────────────────────────────────
SELECT 'Migration Fase 7 aplicada com sucesso.' AS status;
