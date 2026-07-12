-- ============================================================
-- DESFFRUT — Migration Fase 20: Controle Granular de Permissões
-- Tabela permissoes_usuario (exceções por usuário)
-- Execute APÓS as migrations anteriores.
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS permissoes_usuario (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    permissao   VARCHAR(60)  NOT NULL COMMENT 'Chave da permissão (ver app/config/permissoes.php)',
    concedida   TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=concedida, 0=revogada',
    criado_por  INT UNSIGNED NULL COMMENT 'ID do super_admin que criou a exceção',
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_perm (usuario_id, permissao),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Exceções de permissão por usuário (sobrepõe padrão do role)';
