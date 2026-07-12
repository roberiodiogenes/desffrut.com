-- ============================================================
-- Migration 16: Tabela historico_pontos
-- Registra todos os créditos e débitos de fidelidade por cliente.
-- Compatível com MySQL 5.7+ · Idempotente.
-- ============================================================

CREATE TABLE IF NOT EXISTS historico_pontos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id      INT UNSIGNED NOT NULL,
    operacao        ENUM('credito','debito') NOT NULL,
    pontos          INT NOT NULL DEFAULT 0,
    referencia_id   INT UNSIGNED NULL,                  -- id do pedido, venda, etc.
    referencia_tipo VARCHAR(40) NULL,                    -- 'pedido', 'venda', 'indicacao', 'ajuste'
    descricao       VARCHAR(255) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_cliente   (cliente_id),
    INDEX idx_ref       (referencia_tipo, referencia_id),
    CONSTRAINT fk_hp_cliente
        FOREIGN KEY (cliente_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
