-- ============================================================
-- DESFFRUT — Migration Fase 8: CMS & Customização do Portal
-- Execute no phpMyAdmin ou via MySQL CLI:
--   mysql -u root -p desffrut_dev < database/06-migration_fase8_cms.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Tabela: CONFIGURACOES (key-value) ───────────────────────────────────────
-- Armazena todas as configurações do portal (identidade visual, flags, etc.)
CREATE TABLE IF NOT EXISTS configuracoes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave       VARCHAR(80)   NOT NULL UNIQUE COMMENT 'Identificador único da configuração',
    valor       TEXT          NULL             COMMENT 'Valor em texto; JSON, URL ou string simples',
    descricao   VARCHAR(255)  NULL             COMMENT 'Documentação interna da chave',
    atualizado_em DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    atualizado_por INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chaves iniciais padrão
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
    ('nome_sistema',   'Desffrut',                        'Nome exibido no navbar e título das páginas'),
    ('slogan',         'Frescor direto do campo pra você','Subtítulo exibido na home'),
    ('logo_path',      '',                                 'Caminho relativo da logomarca (uploads/logos/logo.webp)'),
    ('cor_primaria',   '#2e7d32',                          'Cor primária do portal (hex). Usada em navbar, botões e sidebar'),
    ('cor_secundaria', '#a5d6a7',                          'Cor secundária / accent (hex)'),
    ('manutencao_ativa',  '0',                             'Flag de modo manutenção: 1 = ativo, 0 = inativo'),
    ('inadimplencia_ativa','0',                            'Flag de aviso de inadimplência: 1 = ativo, 0 = inativo');

-- ─── Tabela: BANNERS ────────────────────────────────────────────────────────
-- Banners rotativos exibidos na página inicial
CREATE TABLE IF NOT EXISTS banners (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    imagem_path   VARCHAR(255) NOT NULL COMMENT 'Caminho relativo à raiz do projeto (uploads/banners/…)',
    link_destino  VARCHAR(500) NULL     COMMENT 'URL de destino ao clicar no banner (pode ser relativo ou absoluto)',
    tipo          ENUM('desktop','mobile') NOT NULL DEFAULT 'desktop',
    ordem         TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Menor número aparece primeiro',
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    exibe_de      DATETIME     NULL COMMENT 'Início do período de exibição (NULL = sem restrição)',
    exibe_ate     DATETIME     NULL COMMENT 'Fim do período de exibição (NULL = sem restrição)',
    criado_por    INT UNSIGNED NULL,
    criado_em     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo_ativo (tipo, ativo),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tabela: CAMPANHAS ──────────────────────────────────────────────────────
-- Campanhas sazonais: cupons globais de desconto ou temas CSS no portal
CREATE TABLE IF NOT EXISTS campanhas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120)  NOT NULL,
    tipo            ENUM('cupom_global','tema_css') NOT NULL DEFAULT 'cupom_global',
    valor_desconto  DECIMAL(5,2)  NULL  COMMENT 'Percentual de desconto (para cupom_global, ex: 10.00 = 10%)',
    classe_css      VARCHAR(80)   NULL  COMMENT 'Classe CSS injetada no <body> (para tema_css)',
    data_inicio     DATETIME      NOT NULL,
    data_fim        DATETIME      NOT NULL,
    ativo           TINYINT(1)    NOT NULL DEFAULT 1,
    criado_por      INT UNSIGNED  NULL,
    criado_em       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ativo_periodo (ativo, data_inicio, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Colunas adicionais em LOJAS ─────────────────────────────────────────────
ALTER TABLE lojas
    ADD COLUMN IF NOT EXISTS horario_funcionamento VARCHAR(120) NULL
        COMMENT 'Texto livre, ex: Seg–Sáb 7h–19h, Dom 7h–13h'
        AFTER telefone,
    ADD COLUMN IF NOT EXISTS whatsapp_link VARCHAR(255) NULL
        COMMENT 'Link wa.me completo, ex: https://wa.me/5585999999999'
        AFTER horario_funcionamento;

-- ─── Popula dados CMS das lojas seed (horário e WhatsApp) ───────────────────
-- Idempotente: só atualiza lojas que ainda não têm horário preenchido.
UPDATE lojas SET
    horario_funcionamento = 'Seg–Sáb 07h–19h | Dom 07h–13h',
    whatsapp_link         = 'https://wa.me/5500999990001'
WHERE nome = 'Desffrut — Loja 1' AND (horario_funcionamento IS NULL OR horario_funcionamento = '');

UPDATE lojas SET
    horario_funcionamento = 'Seg–Sáb 07h–19h | Dom 07h–13h',
    whatsapp_link         = 'https://wa.me/5500999990002'
WHERE nome = 'Desffrut — Loja 2' AND (horario_funcionamento IS NULL OR horario_funcionamento = '');

UPDATE lojas SET
    horario_funcionamento = 'Seg–Sáb 07h–19h | Dom 07h–13h',
    whatsapp_link         = 'https://wa.me/5500999990003'
WHERE nome = 'Desffrut — Loja 3' AND (horario_funcionamento IS NULL OR horario_funcionamento = '');

-- ─── Diretório de uploads para logos e banners ──────────────────────────────
-- (crie manualmente ou via PHP na primeira execução)
-- uploads/logos/    — logomarca do sistema
-- uploads/banners/  — banners da home

SET FOREIGN_KEY_CHECKS = 1;
