-- ============================================================
-- DESFFRUT — Schema Relacional MySQL
-- Versão 1.0 | Cobre todas as Fases 1–7
-- Executar no XAMPP (phpMyAdmin) ou via MySQL CLI:
--   mysql -u root -p desffrut_dev < database/schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── 01. LOJAS ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lojas (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    endereco   VARCHAR(255),
    telefone   VARCHAR(20),
    ativo      TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 02. USUARIOS (SSO unificado — todos os perfis em uma tabela) ─────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome              VARCHAR(100) NOT NULL,
    email             VARCHAR(150) NOT NULL UNIQUE,
    cpf               VARCHAR(14)  UNIQUE,
    telefone          VARCHAR(20)  NULL,
    whatsapp          VARCHAR(20)  NULL,
    pontos_fidelidade INT UNSIGNED NOT NULL DEFAULT 0,
    foto_perfil       VARCHAR(255) NULL,
    senha_hash        VARCHAR(255) NOT NULL,
    role              ENUM('super_admin','gerente','caixa','entregador','rh_financeiro','cliente')
                      NOT NULL DEFAULT 'cliente',
    loja_id           INT UNSIGNED NULL,           -- NULL para clientes (sem filial fixa)
    ativo             TINYINT(1) NOT NULL DEFAULT 1,
    lgpd_aceito_em    TIMESTAMP NULL,             -- Timestamp do aceite dos termos (LGPD)
    token_sessao      VARCHAR(128) NULL,          -- Token da sessão API ativa
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE SET NULL,
    INDEX idx_role (role),
    INDEX idx_loja (loja_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 03. PRODUTOS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produtos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome           VARCHAR(150) NOT NULL,
    descricao      TEXT,
    ean            VARCHAR(20),                   -- Código de barras EAN-13/EAN-8
    categoria      ENUM('frutas','verduras','legumes','outros') NOT NULL DEFAULT 'outros',
    unidade_medida ENUM('kg','un') NOT NULL DEFAULT 'kg',
    preco_custo    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ativo          TINYINT(1) NOT NULL DEFAULT 1,
    foto           VARCHAR(255),                  -- Caminho relativo em uploads/produtos/
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ean (ean),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 04. PRECOS (por filial + promoções programadas) ─────────────────────────
-- Um produto pode ter preços diferentes por loja.
-- Promoção: se NOW() entre promo_inicio e promo_fim → usa promo_preco.
CREATE TABLE IF NOT EXISTS precos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id   INT UNSIGNED NOT NULL,
    loja_id      INT UNSIGNED NOT NULL,
    preco_venda  DECIMAL(10,2) NOT NULL,
    promo_preco  DECIMAL(10,2) NULL,
    promo_inicio DATETIME NULL,
    promo_fim    DATETIME NULL,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_produto_loja (produto_id, loja_id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id),
    FOREIGN KEY (loja_id)    REFERENCES lojas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 05. ESTOQUE (por filial) ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS estoque (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id     INT UNSIGNED NOT NULL,
    loja_id        INT UNSIGNED NOT NULL,
    quantidade     DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    estoque_minimo DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_produto_loja (produto_id, loja_id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id),
    FOREIGN KEY (loja_id)    REFERENCES lojas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 06. QUEBRAS (perdas de perecíveis) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS quebras (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id  INT UNSIGNED NOT NULL,
    loja_id     INT UNSIGNED NOT NULL,
    quantidade  DECIMAL(10,3) NOT NULL,
    motivo      VARCHAR(255) NOT NULL,
    usuario_id  INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id)  REFERENCES produtos(id),
    FOREIGN KEY (loja_id)     REFERENCES lojas(id),
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 07. CAIXAS ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS caixas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id     INT UNSIGNED NOT NULL,
    usuario_id  INT UNSIGNED NOT NULL,          -- Operador que abriu o caixa
    status      ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
    fundo_troco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    aberto_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fechado_em  TIMESTAMP NULL,
    FOREIGN KEY (loja_id)    REFERENCES lojas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 08. MOVIMENTOS_CAIXA (sangrias, suprimentos, abertura, fechamento) ───────
CREATE TABLE IF NOT EXISTS movimentos_caixa (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caixa_id     INT UNSIGNED NOT NULL,
    tipo         ENUM('abertura','suprimento','sangria','fechamento') NOT NULL,
    valor        DECIMAL(10,2) NOT NULL,
    justificativa VARCHAR(255),                 -- Obrigatória em sangrias
    usuario_id   INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caixa_id)   REFERENCES caixas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 09. VENDAS ──────────────────────────────────────────────────────────────
-- cupom_uuid: gerado offline (UUID v4) para identificar o cupom antes do sync.
-- synced_at NULL = venda offline ainda não enviada para a nuvem.
CREATE TABLE IF NOT EXISTS vendas (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caixa_id         INT UNSIGNED NULL,
    loja_id          INT UNSIGNED NOT NULL,
    cliente_id       INT UNSIGNED NULL,
    total            DECIMAL(10,2) NOT NULL,
    desconto         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    forma_pagamento  ENUM('dinheiro','debito','credito','pix','pontos','misto') NOT NULL,
    pontos_ganhos    INT UNSIGNED NOT NULL DEFAULT 0,
    total_final      DECIMAL(10,2) GENERATED ALWAYS AS (total - desconto) STORED,
    status           ENUM('finalizada','cancelada') NOT NULL DEFAULT 'finalizada',
    cupom_uuid       CHAR(36) NOT NULL UNIQUE,
    synced_at        TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caixa_id)   REFERENCES caixas(id) ON DELETE SET NULL,
    FOREIGN KEY (loja_id)    REFERENCES lojas(id),
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_synced (synced_at),
    INDEX idx_loja_data (loja_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 10. ITENS_VENDA ─────────────────────────────────────────────────────────
-- preco_unitario_snapshot: imutável — preço exato no momento da venda.
-- Base da política de conflito do Background Sync (Seção 3.3 do briefing).
CREATE TABLE IF NOT EXISTS itens_venda (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venda_id                INT UNSIGNED NOT NULL,
    produto_id              INT UNSIGNED NOT NULL,
    quantidade              DECIMAL(10,3) NOT NULL,
    preco_unitario_snapshot DECIMAL(10,2) NOT NULL,
    subtotal                DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venda_id)   REFERENCES vendas(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 11. PEDIDOS (tele-entrega) ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pedidos (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id       INT UNSIGNED NOT NULL,
    loja_id          INT UNSIGNED NOT NULL,
    entregador_id    INT UNSIGNED NULL,
    status           ENUM('aguardando','preparando','saiu_para_entrega','entregue','cancelado')
                     NOT NULL DEFAULT 'aguardando',
    total            DECIMAL(10,2) NOT NULL,
    endereco_entrega VARCHAR(255) NOT NULL,
    observacoes      TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id)    REFERENCES usuarios(id),
    FOREIGN KEY (loja_id)       REFERENCES lojas(id),
    FOREIGN KEY (entregador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 12. ITENS_PEDIDO ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS itens_pedido (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id      INT UNSIGNED NOT NULL,
    produto_id     INT UNSIGNED NOT NULL,
    quantidade     DECIMAL(10,3) NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    subtotal       DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pedido_id)  REFERENCES pedidos(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 13. PONTOS_FIDELIDADE ───────────────────────────────────────────────────
-- R$ 1,00 gasto = 1 ponto | 100 pontos = R$ 1,00 de desconto.
-- Cancelamento de venda gera operacao='estorno'.
CREATE TABLE IF NOT EXISTS pontos_fidelidade (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id      INT UNSIGNED NOT NULL,
    operacao        ENUM('credito','debito','estorno') NOT NULL,
    pontos          INT NOT NULL,
    referencia_id   INT UNSIGNED NULL,
    referencia_tipo ENUM('venda','pedido','resgate','estorno_venda','estorno_pedido') NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    INDEX idx_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 14. FUNCIONARIOS ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS funcionarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL UNIQUE,
    loja_id     INT UNSIGNED NOT NULL,
    cargo       VARCHAR(100) NOT NULL,
    salario_base DECIMAL(10,2) NOT NULL,
    admitido_em DATE NOT NULL,
    demitido_em DATE NULL,
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (loja_id)    REFERENCES lojas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 15. CONTAS_PAGAR ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contas_pagar (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id    INT UNSIGNED NOT NULL,
    descricao  VARCHAR(255) NOT NULL,
    categoria  ENUM('aluguel','agua','energia','internet','fornecedor','folha','outros')
               NOT NULL DEFAULT 'outros',
    valor      DECIMAL(10,2) NOT NULL,
    vencimento DATE NOT NULL,
    pago_em    DATE NULL,
    pago_por   INT UNSIGNED NULL,
    status     ENUM('pendente','pago','vencido') NOT NULL DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id)  REFERENCES lojas(id),
    FOREIGN KEY (pago_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status_venc (status, vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 16. LOGS_AUDITORIA ──────────────────────────────────────────────────────
-- Registra: logins, sangrias, cancelamentos, alterações de preço, demissões.
CREATE TABLE IF NOT EXISTS logs_auditoria (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NULL,
    acao            VARCHAR(100) NOT NULL,
    tabela_afetada  VARCHAR(50),
    registro_id     INT UNSIGNED NULL,
    detalhes_json   JSON,
    ip              VARCHAR(45),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_acao (acao),
    INDEX idx_data (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
