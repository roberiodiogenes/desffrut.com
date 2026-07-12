-- ============================================================
-- Desffrut — Migration Fase 10: Prospecção & CRM Kanban
-- Executar uma única vez via phpMyAdmin ou CLI MySQL.
-- ============================================================

-- ── Tabela de Leads ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leads (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(120)     NOT NULL,
    email         VARCHAR(120)     NULL,
    telefone      VARCHAR(20)      NOT NULL,
    bairro        VARCHAR(80)      NULL,
    empresa       VARCHAR(120)     NULL,
    mensagem      TEXT             NULL,
    origem        ENUM('formulario','csv','manual') NOT NULL DEFAULT 'formulario',
    fase          ENUM('novo','contato','proposta','negociacao','fechado','perdido')
                                   NOT NULL DEFAULT 'novo',
    atribuido_a   INT UNSIGNED     NULL COMMENT 'FK usuarios.id — responsável pelo lead',
    criado_em     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uk_telefone  (telefone),
    INDEX        idx_fase     (fase),
    INDEX        idx_origem   (origem),
    INDEX        idx_criado   (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
