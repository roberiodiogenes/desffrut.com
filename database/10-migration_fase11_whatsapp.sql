-- ============================================================
-- Desffrut — Migration Fase 11: WhatsApp Híbrido
-- Pedidos via WhatsApp com validação em 1 clique pela loja.
-- ============================================================

-- 1. Adiciona status aguardando_validacao ao fluxo de pedidos.
--    Novo fluxo WA: aguardando_validacao → preparando → saiu_para_entrega → entregue | cancelado
ALTER TABLE pedidos MODIFY COLUMN status
    ENUM('aguardando_validacao','aguardando','preparando','saiu_para_entrega','entregue','cancelado')
    NOT NULL DEFAULT 'aguardando';

-- 2. Canal de origem e colunas do token de validação em 1 clique.
ALTER TABLE pedidos
    ADD COLUMN canal_origem       ENUM('web','whatsapp') NOT NULL DEFAULT 'web'
        AFTER observacoes,
    ADD COLUMN wa_token           CHAR(36)   NULL DEFAULT NULL
        AFTER canal_origem,
    ADD COLUMN wa_token_expira_em DATETIME   NULL DEFAULT NULL
        AFTER wa_token,
    ADD COLUMN wa_token_usado     TINYINT(1) NOT NULL DEFAULT 0
        AFTER wa_token_expira_em;

-- Índice único para busca rápida do token (lookup O(1))
ALTER TABLE pedidos
    ADD UNIQUE INDEX idx_wa_token (wa_token);
