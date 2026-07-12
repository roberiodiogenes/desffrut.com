-- ============================================================
-- Desffrut — Migration Fase 9: Dev Mode & Operações de Sistema
-- Executar no phpMyAdmin ou via CLI: mysql -u root desffrut < 07-migration_fase9_dev.sql
-- ============================================================

-- 1. Adiciona role dev_admin ao ENUM de usuarios
ALTER TABLE usuarios
    MODIFY COLUMN role
        ENUM('cliente','caixa','entregador','gerente','rh_financeiro','super_admin','dev_admin')
        NOT NULL DEFAULT 'cliente';

-- 2. Flag para forçar troca de senha no próximo login
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS trocar_senha_prox_login TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Quando 1, exige que o usuário redefina a senha no próximo acesso';

-- 3. Garante coluna de IP em logs_auditoria (pode já existir do schema base)
ALTER TABLE logs_auditoria
    ADD COLUMN IF NOT EXISTS ip VARCHAR(45) NULL DEFAULT NULL
    COMMENT 'Endereço IP do autor da ação';

-- 4. Índice para facilitar filtro forense por IP
ALTER TABLE logs_auditoria
    ADD INDEX IF NOT EXISTS idx_ip (ip);

-- 5. Chaves de configuração da Fase 9 (INSERT IGNORE — não sobrescreve se já existirem)
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
    ('manutencao_ativa',   '0', 'Modo de manutenção: 1 = portal público bloqueado'),
    ('manutencao_msg',     'Sistema em manutenção. Voltamos em breve! 🌿', 'Mensagem exibida na tela de manutenção'),
    ('inadimplencia_ativa','0', 'Aviso de inadimplência: 1 = banner fixo nos painéis admin'),
    ('inadimplencia_msg',  'Atenção: existe um débito pendente com a plataforma Desffrut. Entre em contato com o suporte técnico para regularizar.', 'Mensagem do banner de inadimplência');

-- ============================================================
-- Verificação: após executar, rode:
--   SELECT role FROM usuarios WHERE role = 'dev_admin'; -- deve retornar 0 linhas (nenhum ainda)
--   DESCRIBE usuarios;  -- trocar_senha_prox_login deve aparecer
--   DESCRIBE logs_auditoria;  -- ip deve aparecer
-- Para criar o primeiro dev_admin (substitua o ID real):
--   UPDATE usuarios SET role = 'dev_admin' WHERE id = 1;
-- ============================================================
