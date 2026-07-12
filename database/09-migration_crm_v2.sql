-- ============================================================
-- Desffrut — Migration: CRM v2 (aperfeiçoamento Fase 10)
-- Adiciona temperatura, valor estimado e loja à tabela leads.
-- ============================================================

ALTER TABLE leads
    ADD COLUMN temperatura    ENUM('frio','morno','quente') NOT NULL DEFAULT 'frio'
        AFTER fase,
    ADD COLUMN valor_estimado DECIMAL(10,2)  NULL DEFAULT NULL
        AFTER temperatura,
    ADD COLUMN loja_id        INT UNSIGNED   NULL DEFAULT NULL
        AFTER valor_estimado;

-- Índice para filtros por temperatura e loja
ALTER TABLE leads
    ADD INDEX idx_temperatura (temperatura),
    ADD INDEX idx_loja        (loja_id);
