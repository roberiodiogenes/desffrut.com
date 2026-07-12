-- ============================================================
-- DESFFRUT — Migration 14: Lojas — UNIQUE(nome) + cleanup de duplicatas
-- Execute APENAS SE necessário (banco com lojas duplicadas).
--
-- Antes de rodar, verifique os duplicados:
--   SELECT nome, COUNT(*) c FROM lojas GROUP BY nome HAVING c > 1;
--
-- Este script:
--   1. Remove lojas duplicadas mantendo apenas o ID mais baixo de cada nome.
--   2. Redireciona FK (usuarios.loja_id) para o ID canônico antes de deletar.
--   3. Adiciona UNIQUE(nome) para prevenir futuros duplicados.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Passo 1: Para cada loja duplicada, aponta os usuários para o ID canônico ──
-- (idempotente: UPDATE where loja_id já aponta para o canônico não muda nada)
UPDATE usuarios u
    JOIN (
        SELECT nome, MIN(id) AS id_canonico FROM lojas GROUP BY nome
    ) canon ON canon.nome = (SELECT nome FROM lojas WHERE id = u.loja_id)
SET u.loja_id = canon.id_canonico
WHERE u.loja_id IS NOT NULL;

-- ── Passo 2: Exclui as lojas duplicadas (mantém o menor ID por nome) ──────────
DELETE l
FROM lojas l
INNER JOIN (
    SELECT nome, MIN(id) AS id_canonico FROM lojas GROUP BY nome
) canon ON canon.nome = l.nome
WHERE l.id > canon.id_canonico;

-- ── Passo 3: Adiciona UNIQUE(nome) se ainda não existir (MySQL 5.7 compatível) ─
-- Usa prepared statement via variável para evitar erro se a constraint já existir.
SET @idx_existe = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name   = 'lojas'
      AND index_name   = 'uq_lojas_nome'
);
SET @ddl = IF(
    @idx_existe = 0,
    'ALTER TABLE lojas ADD UNIQUE KEY uq_lojas_nome (nome)',
    'SELECT ''UNIQUE(nome) já existe — nada a fazer'' AS info'
);
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET FOREIGN_KEY_CHECKS = 1;
