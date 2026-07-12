-- ============================================================
-- Desffrut — Migration Fase 12: Marketing & AdTech
-- UTM tracking, programa de indicação e chaves de Pixel.
-- ============================================================

-- 1. Coluna de rastreamento UTM nos pedidos (JSON — MySQL 5.7+)
ALTER TABLE pedidos
    ADD COLUMN origem_utm JSON NULL DEFAULT NULL;

-- 2. Programa "Indique e Ganhe" nos usuários
ALTER TABLE usuarios
    ADD COLUMN codigo_indicacao     CHAR(10)     NULL DEFAULT NULL,
    ADD COLUMN indicado_por_id      INT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN indicacao_bonus_pago TINYINT(1)   NOT NULL DEFAULT 0;

ALTER TABLE usuarios
    ADD UNIQUE INDEX idx_codigo_indicacao (codigo_indicacao),
    ADD INDEX        idx_indicado_por     (indicado_por_id);

-- 3. Chaves de configuração para Pixels & Programa de Indicação
INSERT IGNORE INTO configuracoes (chave, valor) VALUES
    ('pixel_meta_id',    ''),        -- ID do Meta Pixel
    ('gtag_id',          ''),        -- Measurement ID do GA4 (ex: G-XXXXXXX)
    ('pontos_indicacao', '100');     -- Pontos creditados ao indicador no 1º pedido entregue
