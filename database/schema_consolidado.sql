-- ============================================================
-- DESFFRUT — Schema Consolidado (definitivo)
-- Gerado em 2026-07-12 a partir de:
--   00-schema.sql + migrations 03 a 22 + migrations/023_financeiro_completo.sql
--
-- Este arquivo representa o estado FINAL de todas as tabelas depois
-- de aplicar todas as migrations incrementais — é o único arquivo
-- necessário para criar o banco do zero (instalação nova / servidor
-- de teste). NÃO contém dados de teste (usuários, produtos, preços) —
-- isso está em `seed_teste.sql`, para ser executado depois deste.
--
-- Bancos que já rodaram as migrations 00-022 incrementalmente NÃO
-- precisam rodar este arquivo — já estão neste mesmo estado.
--
-- Ordem de execução para um banco novo:
--   1. mysql -u root -p desffrut_teste < database/schema_consolidado.sql
--   2. mysql -u root -p desffrut_teste < database/seed_teste.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── 01. LOJAS ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lojas (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                  VARCHAR(100) NOT NULL,
    endereco              VARCHAR(255),
    telefone              VARCHAR(20),
    horario_funcionamento VARCHAR(120) NULL COMMENT 'Texto livre, ex: Seg-Sáb 7h-19h, Dom 7h-13h',
    whatsapp_link         VARCHAR(255) NULL COMMENT 'Link wa.me completo',
    ativo                 TINYINT(1) NOT NULL DEFAULT 1,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lojas_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 02. USUARIOS (SSO unificado — todos os perfis em uma tabela) ─────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                     VARCHAR(100) NOT NULL,
    email                    VARCHAR(150) NOT NULL UNIQUE,
    cpf                      VARCHAR(14)  UNIQUE,
    data_nascimento          DATE NULL COMMENT 'Usado para calcular idade automaticamente na ficha do funcionário',
    telefone                 VARCHAR(20)  NULL,
    whatsapp                 VARCHAR(20)  NULL,
    pontos_fidelidade        INT UNSIGNED NOT NULL DEFAULT 0,
    foto_perfil              VARCHAR(255) NULL,
    endereco                 VARCHAR(255) NULL,
    numero                   VARCHAR(20)  NULL,
    complemento              VARCHAR(100) NULL,
    bairro                   VARCHAR(100) NULL,
    cidade                   VARCHAR(100) NULL,
    cep                      VARCHAR(10)  NULL,
    senha_hash               VARCHAR(255) NOT NULL,
    role                     ENUM('cliente','caixa','entregador','gerente','rh_financeiro','super_admin','dev_admin','colaborador')
                             NOT NULL DEFAULT 'cliente'
                             COMMENT "'colaborador' = staff com ficha de RH mas sem acesso ao painel (motorista, auxiliar CEASA)",
    loja_id                  INT UNSIGNED NULL,      -- NULL para clientes / cargos sem filial fixa
    ativo                    TINYINT(1) NOT NULL DEFAULT 1,
    lgpd_aceito_em           TIMESTAMP NULL,
    token_sessao             VARCHAR(128) NULL,
    trocar_senha_prox_login  TINYINT(1) NOT NULL DEFAULT 0
                             COMMENT 'Quando 1, exige que o usuário redefina a senha no próximo acesso',
    codigo_indicacao         CHAR(10)     NULL,
    indicado_por_id          INT UNSIGNED NULL,
    indicacao_bonus_pago     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE SET NULL,
    INDEX idx_role (role),
    INDEX idx_loja (loja_id),
    UNIQUE INDEX idx_codigo_indicacao (codigo_indicacao),
    INDEX idx_indicado_por (indicado_por_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 03. PRODUTOS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produtos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome           VARCHAR(150) NOT NULL,
    descricao      TEXT,
    ean            VARCHAR(20),                   -- Código de barras EAN-13/EAN-8
    codigo_interno VARCHAR(12) NOT NULL UNIQUE COMMENT 'Código interno PRD-XXXXXX gerado automaticamente',
    categoria      ENUM('frutas','verduras','legumes','outros') NOT NULL DEFAULT 'outros',
    unidade_medida ENUM('kg','un') NOT NULL DEFAULT 'kg',
    preco_custo    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ativo          TINYINT(1) NOT NULL DEFAULT 1,
    foto           VARCHAR(255),                  -- Caminho relativo, ex: uploads/produtos/banana.webp
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ean (ean),
    INDEX idx_categoria (categoria),
    INDEX idx_codigo_interno (codigo_interno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 04. PRECOS (por filial + promoções programadas) ─────────────────────────
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
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id                  INT UNSIGNED NOT NULL,
    usuario_id               INT UNSIGNED NOT NULL,          -- Operador que abriu o caixa
    justificativa_abertura   TEXT NULL COMMENT 'Motivo quando aberto por quem não é o operador (gerente/dono cobrindo ausência)',
    status                   ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
    fundo_troco              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    aberto_em                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fechado_em               TIMESTAMP NULL,
    fechado_por              INT UNSIGNED NULL COMMENT 'Usuário que fechou o caixa (pode ser diferente de quem abriu)',
    total_contado            DECIMAL(10,2) NULL COMMENT 'Valor em dinheiro contado fisicamente na conferência de fechamento',
    justificativa_fechamento TEXT NULL COMMENT 'Motivo quando fechado por quem não é o operador',
    FOREIGN KEY (loja_id)     REFERENCES lojas(id),
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id),
    FOREIGN KEY (fechado_por) REFERENCES usuarios(id),
    INDEX idx_status (status),
    INDEX idx_fechado_por (fechado_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 08. MOVIMENTOS_CAIXA (sangrias, suprimentos, abertura, fechamento) ───────
CREATE TABLE IF NOT EXISTS movimentos_caixa (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caixa_id      INT UNSIGNED NOT NULL,
    tipo          ENUM('abertura','suprimento','sangria','fechamento') NOT NULL,
    valor         DECIMAL(10,2) NOT NULL,
    justificativa VARCHAR(255),                 -- Obrigatória em sangrias
    usuario_id    INT UNSIGNED NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caixa_id)   REFERENCES caixas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 09. VENDAS ──────────────────────────────────────────────────────────────
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
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id           INT UNSIGNED NOT NULL,
    loja_id              INT UNSIGNED NOT NULL,
    entregador_id        INT UNSIGNED NULL,
    status               ENUM('aguardando_validacao','aguardando','preparando','saiu_para_entrega','entregue','cancelado')
                         NOT NULL DEFAULT 'aguardando',
    motivo_cancelamento  VARCHAR(255) NULL,
    total                DECIMAL(10,2) NOT NULL,
    forma_pagamento      ENUM('dinheiro_na_entrega','cartao_debito_entrega','cartao_credito_entrega','pix') NULL,
    troco_para           DECIMAL(10,2) NULL,
    pontos_ganhos        INT UNSIGNED NOT NULL DEFAULT 0,
    endereco_entrega     VARCHAR(255) NOT NULL,
    numero               VARCHAR(20)  NULL,
    complemento          VARCHAR(100) NULL,
    bairro               VARCHAR(100) NULL,
    telefone             VARCHAR(20)  NULL,
    observacoes          TEXT,
    canal_origem         ENUM('web','whatsapp') NOT NULL DEFAULT 'web',
    wa_token             CHAR(36)   NULL,
    wa_token_expira_em   DATETIME   NULL,
    wa_token_usado       TINYINT(1) NOT NULL DEFAULT 0,
    origem_utm           JSON NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id)    REFERENCES usuarios(id),
    FOREIGN KEY (loja_id)       REFERENCES lojas(id),
    FOREIGN KEY (entregador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_cliente (cliente_id),
    UNIQUE INDEX idx_wa_token (wa_token)
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
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED NOT NULL UNIQUE,
    cpf           VARCHAR(14)  NULL,
    telefone      VARCHAR(20)  NULL,
    loja_id       INT UNSIGNED NOT NULL,
    cargo         VARCHAR(100) NOT NULL,
    tipo_contrato ENUM('clt','pj','autonomo','estagio') NOT NULL DEFAULT 'clt',
    carga_horaria TINYINT UNSIGNED NOT NULL DEFAULT 8 COMMENT 'Horas diárias contratadas',
    salario_base  DECIMAL(10,2) NOT NULL,
    admitido_em   DATE NOT NULL,
    demitido_em   DATE NULL,
    ativo         TINYINT(1) NOT NULL DEFAULT 1,
    observacoes   TEXT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (loja_id)    REFERENCES lojas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 15. REGISTRO_PONTO ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS registro_ponto (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT UNSIGNED NOT NULL,
    tipo           ENUM('entrada','saida','entrada_intervalo','saida_intervalo') NOT NULL,
    registrado_em  DATETIME NOT NULL,
    registrado_por INT UNSIGNED NULL,    -- NULL = sistema; preenchido = RH registrou manualmente
    observacao     VARCHAR(255) NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_func_data (funcionario_id, registrado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 16. FOLHA_PAGAMENTO ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS folha_pagamento (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT UNSIGNED NOT NULL,
    mes_referencia CHAR(7) NOT NULL COMMENT 'Formato: YYYY-MM',
    salario_base   DECIMAL(10,2) NOT NULL,
    horas_extras   DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
    valor_extras   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descontos      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_liquido  DECIMAL(10,2) GENERATED ALWAYS AS (salario_base + valor_extras - descontos) STORED,
    status         ENUM('calculado','pago') NOT NULL DEFAULT 'calculado',
    pago_em        DATE NULL,
    observacoes    TEXT NULL,
    criado_por     INT UNSIGNED NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_func_mes (funcionario_id, mes_referencia),
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id),
    FOREIGN KEY (criado_por)     REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 17. CONTAS_PAGAR ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contas_pagar (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id     INT UNSIGNED NOT NULL,
    descricao   VARCHAR(255) NOT NULL,
    categoria   ENUM('aluguel','agua','energia','internet','fornecedor','folha','outros') NOT NULL DEFAULT 'outros',
    valor       DECIMAL(10,2) NOT NULL,
    vencimento  DATE NOT NULL,
    pago_em     DATE NULL,
    pago_por    INT UNSIGNED NULL,
    status      ENUM('pendente','pago','vencido') NOT NULL DEFAULT 'pendente',
    recorrente  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = lançamento mensal recorrente',
    observacoes TEXT NULL,
    criado_por  INT UNSIGNED NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id)  REFERENCES lojas(id),
    FOREIGN KEY (pago_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status_venc (status, vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 18. LOGS_AUDITORIA ──────────────────────────────────────────────────────
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
    INDEX idx_data (created_at),
    INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 19. CONFIGURACOES (key-value) ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS configuracoes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave          VARCHAR(80)  NOT NULL UNIQUE COMMENT 'Identificador único da configuração',
    valor          TEXT         NULL COMMENT 'Valor em texto; JSON, URL ou string simples',
    descricao      VARCHAR(255) NULL COMMENT 'Documentação interna da chave',
    atualizado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    atualizado_por INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 20. BANNERS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS banners (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    imagem_path  VARCHAR(255) NOT NULL COMMENT 'Caminho relativo à raiz do projeto (uploads/banners/…)',
    link_destino VARCHAR(500) NULL,
    tipo         ENUM('desktop','mobile') NOT NULL DEFAULT 'desktop',
    ordem        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ativo        TINYINT(1) NOT NULL DEFAULT 1,
    exibe_de     DATETIME NULL,
    exibe_ate    DATETIME NULL,
    criado_por   INT UNSIGNED NULL,
    criado_em    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo_ativo (tipo, ativo),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 21. CAMPANHAS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS campanhas (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome           VARCHAR(120) NOT NULL,
    tipo           ENUM('cupom_global','tema_css') NOT NULL DEFAULT 'cupom_global',
    valor_desconto DECIMAL(5,2) NULL,
    classe_css     VARCHAR(80)  NULL,
    data_inicio    DATETIME NOT NULL,
    data_fim       DATETIME NOT NULL,
    ativo          TINYINT(1) NOT NULL DEFAULT 1,
    criado_por     INT UNSIGNED NULL,
    criado_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ativo_periodo (ativo, data_inicio, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 22. LEADS (CRM Kanban) ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leads (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome           VARCHAR(120) NOT NULL,
    email          VARCHAR(120) NULL,
    telefone       VARCHAR(20)  NOT NULL,
    bairro         VARCHAR(80)  NULL,
    empresa        VARCHAR(120) NULL,
    mensagem       TEXT NULL,
    origem         ENUM('formulario','csv','manual') NOT NULL DEFAULT 'formulario',
    fase           ENUM('novo','contato','proposta','negociacao','fechado','perdido') NOT NULL DEFAULT 'novo',
    temperatura    ENUM('frio','morno','quente') NOT NULL DEFAULT 'frio',
    valor_estimado DECIMAL(10,2) NULL,
    loja_id        INT UNSIGNED NULL,
    atribuido_a    INT UNSIGNED NULL COMMENT 'FK usuarios.id — responsável pelo lead',
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_telefone (telefone),
    INDEX idx_fase (fase),
    INDEX idx_origem (origem),
    INDEX idx_criado (criado_em),
    INDEX idx_temperatura (temperatura),
    INDEX idx_loja (loja_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 23. PERMISSOES_USUARIO (exceções por usuário) ───────────────────────────
CREATE TABLE IF NOT EXISTS permissoes_usuario (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    permissao  VARCHAR(60) NOT NULL COMMENT 'Chave da permissão (ver app/config/permissoes.php)',
    concedida  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=concedida, 0=revogada',
    criado_por INT UNSIGNED NULL,
    criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_perm (usuario_id, permissao),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Exceções de permissão por usuário (sobrepõe padrão do role)';

-- ─── 24. HISTORICO_PONTOS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS historico_pontos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id      INT UNSIGNED NOT NULL,
    operacao        ENUM('credito','debito') NOT NULL,
    pontos          INT NOT NULL DEFAULT 0,
    referencia_id   INT UNSIGNED NULL,
    referencia_tipo VARCHAR(40) NULL COMMENT "'pedido', 'venda', 'indicacao', 'ajuste'",
    descricao       VARCHAR(255) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cliente (cliente_id),
    INDEX idx_ref (referencia_tipo, referencia_id),
    CONSTRAINT fk_hp_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 25. FROTA ───────────────────────────────────────────────────────────────
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

-- ─── 26. CEASA_COLABORADORES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ceasa_colaboradores (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    funcao     ENUM('motorista','auxiliar') DEFAULT 'auxiliar',
    telefone   VARCHAR(20)  DEFAULT NULL,
    ativo      TINYINT(1)   DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 27. CEASA_ROTAS ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ceasa_rotas (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    data_rota              DATE NOT NULL,
    frota_id               INT DEFAULT NULL,
    motorista_id           INT DEFAULT NULL,
    auxiliar1_id           INT DEFAULT NULL,
    auxiliar2_id           INT DEFAULT NULL,
    rota_descricao         VARCHAR(255) DEFAULT NULL,
    status                 ENUM('planejada','em_andamento','concluida') DEFAULT 'planejada',
    houve_atraso           TINYINT(1) NOT NULL DEFAULT 0,
    motivo_atraso          VARCHAR(500) DEFAULT NULL,
    observacoes_conclusao  TEXT DEFAULT NULL,
    concluida_em           DATETIME DEFAULT NULL,
    created_by             INT DEFAULT NULL,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_data_rota    (data_rota),
    KEY idx_cr_frota     (frota_id),
    KEY idx_cr_motorista (motorista_id),
    KEY idx_cr_aux1      (auxiliar1_id),
    KEY idx_cr_aux2      (auxiliar2_id),
    CONSTRAINT fk_cr_frota FOREIGN KEY (frota_id)     REFERENCES frota(id)              ON DELETE SET NULL,
    CONSTRAINT fk_cr_mot   FOREIGN KEY (motorista_id) REFERENCES ceasa_colaboradores(id) ON DELETE SET NULL,
    CONSTRAINT fk_cr_aux1  FOREIGN KEY (auxiliar1_id) REFERENCES ceasa_colaboradores(id) ON DELETE SET NULL,
    CONSTRAINT fk_cr_aux2  FOREIGN KEY (auxiliar2_id) REFERENCES ceasa_colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 28. CEASA_RECEBIMENTOS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ceasa_recebimentos (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    rota_id            INT DEFAULT NULL,
    loja_id            INT NOT NULL,
    data_recebimento   DATE NOT NULL,
    responsavel_id     INT DEFAULT NULL,
    observacoes_gerais TEXT DEFAULT NULL,
    total_itens        INT DEFAULT 0,
    total_recebidos    INT DEFAULT 0,
    status             ENUM('rascunho','confirmado') DEFAULT 'confirmado',
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_receb_data (data_recebimento),
    KEY idx_receb_loja (loja_id),
    KEY idx_receb_rota (rota_id),
    CONSTRAINT fk_receb_rota FOREIGN KEY (rota_id) REFERENCES ceasa_rotas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 29. CEASA_RECEBIMENTO_ITENS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ceasa_recebimento_itens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    recebimento_id INT NOT NULL,
    produto_id     INT NOT NULL,
    qtd_pedida     DECIMAL(10,3) DEFAULT 0.000,
    qtd_recebida   DECIMAL(10,3) DEFAULT 0.000,
    qtd_quebra     DECIMAL(10,3) DEFAULT 0.000,
    nao_entregue   TINYINT(1) DEFAULT 0,
    observacao     VARCHAR(255) DEFAULT NULL,
    KEY idx_ri_receb   (recebimento_id),
    KEY idx_ri_produto (produto_id),
    CONSTRAINT fk_ri_receb FOREIGN KEY (recebimento_id) REFERENCES ceasa_recebimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 30. FINANCEIRO_MOVIMENTACOES ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS financeiro_movimentacoes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id         INT UNSIGNED NOT NULL,
    tipo            ENUM('retirada','despesa_extra','transferencia','custo_ceasa') NOT NULL,
    subtipo         VARCHAR(60) NULL COMMENT 'pro_labore | investimento | transferencia_pessoal | limpeza | manutencao | terceirizado | combustivel | pedagio | outros',
    descricao       VARCHAR(255) NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    data            DATE NOT NULL,
    loja_destino_id INT UNSIGNED NULL COMMENT 'Para transferências entre lojas',
    conta_bancaria  VARCHAR(120) NULL,
    referencia_id   INT UNSIGNED NULL,
    observacoes     TEXT NULL,
    criado_por      INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id)    REFERENCES lojas(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 31. AUXILIARES_PAGAMENTOS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS auxiliares_pagamentos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL COMMENT 'FK -> ceasa_colaboradores.id (INT signed)',
    valor          DECIMAL(10,2) NOT NULL,
    periodo_ini    DATE NOT NULL,
    periodo_fim    DATE NOT NULL,
    tipo           ENUM('semanal','quinzenal','mensal') NOT NULL DEFAULT 'semanal',
    data_pagamento DATE NOT NULL,
    forma          ENUM('dinheiro','pix','transferencia') NOT NULL DEFAULT 'dinheiro',
    observacoes    TEXT NULL,
    criado_por     INT UNSIGNED NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES ceasa_colaboradores(id),
    FOREIGN KEY (criado_por)     REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 32. CONTAS_RECEBER ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contas_receber (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id          INT UNSIGNED NOT NULL,
    descricao        VARCHAR(255) NOT NULL,
    valor            DECIMAL(10,2) NOT NULL,
    data_vencimento  DATE NOT NULL,
    data_recebimento DATE NULL,
    status           ENUM('pendente','recebido','vencido','cancelado') NOT NULL DEFAULT 'pendente',
    categoria        ENUM('fiado','cheque','pix','transferencia','cartao','outros') NOT NULL DEFAULT 'outros',
    cliente_nome     VARCHAR(120) NULL,
    observacoes      TEXT NULL,
    recebido_por     INT UNSIGNED NULL,
    criado_por       INT UNSIGNED NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id)    REFERENCES lojas(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 33. FIN_METAS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fin_metas (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id    INT UNSIGNED NULL COMMENT 'NULL = consolidado (todas as lojas)',
    mes_ref    VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    tipo       ENUM('faturamento','despesa_total','despesa_categoria') NOT NULL,
    categoria  VARCHAR(60) NULL COMMENT 'Quando tipo=despesa_categoria',
    valor_meta DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_meta (loja_id, mes_ref, tipo, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Valores padrão de sistema (não são dados de teste) ──────────────────────
-- Chaves que o painel/portal espera encontrar em `configuracoes` para
-- renderizar com sensatez desde o primeiro acesso (nome, cores, flags).
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
    ('nome_sistema',        'Desffrut',                          'Nome exibido no navbar e título das páginas'),
    ('slogan',              'Frescor direto do campo pra você',  'Subtítulo exibido na home'),
    ('logo_path',           '',                                  'Caminho relativo da logomarca (uploads/logos/logo.webp)'),
    ('cor_primaria',        '#2e7d32',                           'Cor primária do portal (hex)'),
    ('cor_secundaria',      '#a5d6a7',                           'Cor secundária / accent (hex)'),
    ('manutencao_ativa',    '0',                                 'Modo de manutenção: 1 = portal público bloqueado'),
    ('manutencao_msg',      'Sistema em manutenção. Voltamos em breve!', 'Mensagem da tela de manutenção'),
    ('inadimplencia_ativa', '0',                                 'Aviso de inadimplência: 1 = banner fixo nos painéis admin'),
    ('inadimplencia_msg',   'Atenção: existe um débito pendente com a plataforma Desffrut. Entre em contato com o suporte técnico para regularizar.', 'Mensagem do banner de inadimplência'),
    ('pixel_meta_id',       '',                                  'ID do Meta Pixel'),
    ('gtag_id',             '',                                  'Measurement ID do GA4 (ex: G-XXXXXXX)'),
    ('pontos_indicacao',    '100',                                'Pontos creditados ao indicador no 1º pedido entregue'),
    ('tolerancia_quebra_caixa', '3.00',                           'Tolerância (R$) de diferença no fechamento de caixa que não conta como falta/sobra');

SET FOREIGN_KEY_CHECKS = 1;
