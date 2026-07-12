-- ============================================================
-- Migration 18: CEASA - Frota, Colaboradores, Rotas, Recebimentos
-- Execute com o banco desffrut_dev SELECIONADO na barra lateral
-- do phpMyAdmin (clique no banco, depois aba SQL).
-- ============================================================

USE `desffrut_dev`;

-- 1. Veiculos da frota
CREATE TABLE IF NOT EXISTS frota (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    modelo             VARCHAR(100) NOT NULL,
    cor                VARCHAR(50)  DEFAULT NULL,
    placa              VARCHAR(10)  NOT NULL,
    ano                SMALLINT     DEFAULT NULL,
    documentacao_ok    TINYINT(1)   DEFAULT 1,
    vencimento_ipva    DATE         DEFAULT NULL,
    vencimento_seguro  DATE         DEFAULT NULL,
    vencimento_revisao DATE         DEFAULT NULL,
    observacoes        TEXT         DEFAULT NULL,
    ativo              TINYINT(1)   DEFAULT 1,
    created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Colaboradores externos
CREATE TABLE IF NOT EXISTS ceasa_colaboradores (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    funcao     ENUM('motorista','auxiliar') DEFAULT 'auxiliar',
    telefone   VARCHAR(20)  DEFAULT NULL,
    ativo      TINYINT(1)   DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Rota do dia (FK apenas para tabelas criadas acima)
CREATE TABLE IF NOT EXISTS ceasa_rotas (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    data_rota        DATE         NOT NULL,
    frota_id         INT          DEFAULT NULL,
    motorista_id     INT          DEFAULT NULL,
    auxiliar1_id     INT          DEFAULT NULL,
    auxiliar2_id     INT          DEFAULT NULL,
    rota_descricao   VARCHAR(255) DEFAULT NULL,
    status           ENUM('planejada','em_andamento','concluida') DEFAULT 'planejada',
    created_by       INT          DEFAULT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY idx_data_rota    (data_rota),
    KEY idx_cr_frota     (frota_id),
    KEY idx_cr_motorista (motorista_id),
    KEY idx_cr_aux1      (auxiliar1_id),
    KEY idx_cr_aux2      (auxiliar2_id),
    CONSTRAINT fk_cr_frota  FOREIGN KEY (frota_id)     REFERENCES frota(id)               ON DELETE SET NULL,
    CONSTRAINT fk_cr_mot    FOREIGN KEY (motorista_id) REFERENCES ceasa_colaboradores(id) ON DELETE SET NULL,
    CONSTRAINT fk_cr_aux1   FOREIGN KEY (auxiliar1_id) REFERENCES ceasa_colaboradores(id) ON DELETE SET NULL,
    CONSTRAINT fk_cr_aux2   FOREIGN KEY (auxiliar2_id) REFERENCES ceasa_colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Historico de recebimentos (sem FK para lojas/usuarios - evita incompatibilidade de tipo)
CREATE TABLE IF NOT EXISTS ceasa_recebimentos (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    rota_id            INT          DEFAULT NULL,
    loja_id            INT          NOT NULL,
    data_recebimento   DATE         NOT NULL,
    responsavel_id     INT          DEFAULT NULL,
    observacoes_gerais TEXT         DEFAULT NULL,
    total_itens        INT          DEFAULT 0,
    total_recebidos    INT          DEFAULT 0,
    status             ENUM('rascunho','confirmado') DEFAULT 'confirmado',
    created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY idx_receb_data (data_recebimento),
    KEY idx_receb_loja (loja_id),
    KEY idx_receb_rota (rota_id),
    CONSTRAINT fk_receb_rota FOREIGN KEY (rota_id) REFERENCES ceasa_rotas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Itens de cada recebimento (sem FK para produtos - evita incompatibilidade)
CREATE TABLE IF NOT EXISTS ceasa_recebimento_itens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    recebimento_id INT           NOT NULL,
    produto_id     INT           NOT NULL,
    qtd_pedida     DECIMAL(10,3) DEFAULT 0.000,
    qtd_recebida   DECIMAL(10,3) DEFAULT 0.000,
    qtd_quebra     DECIMAL(10,3) DEFAULT 0.000,
    nao_entregue   TINYINT(1)    DEFAULT 0,
    observacao     VARCHAR(255)  DEFAULT NULL,
    KEY idx_ri_receb   (recebimento_id),
    KEY idx_ri_produto (produto_id),
    CONSTRAINT fk_ri_receb FOREIGN KEY (recebimento_id) REFERENCES ceasa_recebimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Migration 18 aplicada com sucesso.' AS status;
