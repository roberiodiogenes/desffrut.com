    -- ============================================================
    -- DESFFRUT — Dados Iniciais (Seed)
    -- Execute APÓS o schema.sql:
    --   mysql -u root -p desffrut_dev < database/seed.sql
    --
    -- ATENÇÃO: Altere a senha do super_admin antes de ir para produção.
    -- Hash abaixo corresponde a: Desffrut@2026
    -- ============================================================

    SET NAMES utf8mb4;

    -- ─── Lojas ───────────────────────────────────────────────────────────────────
    -- Colunas horario_funcionamento e whatsapp_link são adicionadas em 06-migration_fase8_cms.sql.
    -- Este INSERT usa apenas as colunas do schema base (00-schema.sql).
    -- Os dados extras (horário/WhatsApp) são preenchidos após rodar a migration 06,
    -- via painel CMS (Dashboard → CMS & Portal → Lojas) ou pelo UPDATE abaixo.
    INSERT IGNORE INTO lojas (nome, endereco, telefone, ativo) VALUES
    ('Desffrut — Loja 1', 'Rua das Frutas, 100 — Centro',           '(00) 99999-0001', 1),
    ('Desffrut — Loja 2', 'Av. das Verduras, 200 — Bairro Novo',    '(00) 99999-0002', 1),
    ('Desffrut — Loja 3', 'Rua dos Legumes, 300 — Bairro da Serra', '(00) 99999-0003', 1);

    -- ─── Super Admin ─────────────────────────────────────────────────────────────
    -- ⚠️  Hashes de senha NÃO são inseridos aqui para evitar problemas de compatibilidade.
    -- Após executar este seed, acesse:
    --   http://localhost/desffrut.com/database/reset_senhas.php
    -- O script gera os hashes BCrypt reais e atualiza todos os usuários de teste.
    --
    -- Senhas que serão definidas pelo reset_senhas.php (botão "Resetar Todos"):
    --   admin@desffrut.com.br         → admin123
    --   rh@desffrut.com.br            → rh123
    --   gerente1@desffrut.com.br      → gerente123
    --   gerente2@desffrut.com.br      → gerente123
    --   gerente3@desffrut.com.br      → gerente123
    --   caixa1@desffrut.com.br        → caixa123
    --   caixa2@desffrut.com.br        → caixa123
    --   caixa3@desffrut.com.br        → caixa123
    --   entregador1@desffrut.com.br   → entregador123
    --   entregador2@desffrut.com.br   → entregador123
    --   entregador3@desffrut.com.br   → entregador123
    --   cliente@teste.com             → cliente123

    INSERT IGNORE INTO usuarios (nome, email, cpf, senha_hash, role, loja_id, lgpd_aceito_em) VALUES
    -- Administração geral (sem loja fixa — acessa todas)
    ('Administrador',    'admin@desffrut.com.br',         NULL,             'HASH_PENDENTE', 'super_admin',   NULL, NOW()),
    ('RH Financeiro',    'rh@desffrut.com.br',            NULL,             'HASH_PENDENTE', 'rh_financeiro', NULL, NOW()),
    -- Loja 1
    ('Gerente Loja 1',   'gerente1@desffrut.com.br',      NULL,             'HASH_PENDENTE', 'gerente',       1,    NOW()),
    ('Caixa Loja 1',     'caixa1@desffrut.com.br',        NULL,             'HASH_PENDENTE', 'caixa',         1,    NOW()),
    ('Entregador Loja 1','entregador1@desffrut.com.br',   NULL,             'HASH_PENDENTE', 'entregador',    1,    NOW()),
    -- Loja 2
    ('Gerente Loja 2',   'gerente2@desffrut.com.br',      NULL,             'HASH_PENDENTE', 'gerente',       2,    NOW()),
    ('Caixa Loja 2',     'caixa2@desffrut.com.br',        NULL,             'HASH_PENDENTE', 'caixa',         2,    NOW()),
    ('Entregador Loja 2','entregador2@desffrut.com.br',   NULL,             'HASH_PENDENTE', 'entregador',    2,    NOW()),
    -- Loja 3
    ('Gerente Loja 3',   'gerente3@desffrut.com.br',      NULL,             'HASH_PENDENTE', 'gerente',       3,    NOW()),
    ('Caixa Loja 3',     'caixa3@desffrut.com.br',        NULL,             'HASH_PENDENTE', 'caixa',         3,    NOW()),
    ('Entregador Loja 3','entregador3@desffrut.com.br',   NULL,             'HASH_PENDENTE', 'entregador',    3,    NOW()),
    -- Cliente de teste
    ('Cliente Teste',    'cliente@teste.com',              '123.456.789-09','HASH_PENDENTE', 'cliente',       NULL, NOW());
