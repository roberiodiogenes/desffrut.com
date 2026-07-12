-- ============================================================
-- DESFFRUT — Seed de Teste (usuários + produtos fictícios)
-- Executar DEPOIS de schema_consolidado.sql, em um banco vazio:
--   mysql -u root -p desffrut_teste < database/schema_consolidado.sql
--   mysql -u root -p desffrut_teste < database/seed_teste.sql
--
-- Todos os dados aqui são FICTÍCIOS — apenas para demonstração ao
-- cliente / testes no ambiente de teste. Não usar em produção.
--
-- ⚠️ SENHA: todos os usuários abaixo são inseridos com
-- senha_hash = 'HASH_PENDENTE' (mesma convenção de database/01-seed.sql).
-- Depois de rodar este arquivo, abra (NO AMBIENTE LOCAL/XAMPP — o
-- guard AMBIENTE==='local' bloqueia isso em 'teste'/'definitivo'):
--   http://localhost/desffrut.com/database/reset_senhas.php
-- e clique em "🔑 Resetar Senhas de Teste (@senha01)". Isso grava o
-- hash bcrypt real de "@senha01" para os 14 e-mails abaixo. Só depois
-- disso exporte (mysqldump) o banco local e importe no servidor de
-- teste — assim o hash real já vai junto, sem depender do
-- reset_senhas.php funcionar lá (ele só roda em ambiente 'local').
--
-- Todas as senhas: @senha01
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Lojas ───────────────────────────────────────────────────────────────────
INSERT INTO lojas (nome, endereco, telefone, horario_funcionamento, whatsapp_link, ativo) VALUES
('Desffrut — Loja 1', 'Rua das Frutas, 100 — Centro',           '(85) 3333-0001', 'Seg–Sáb 07h–19h | Dom 07h–13h', 'https://wa.me/5585999990001', 1),
('Desffrut — Loja 2', 'Av. das Verduras, 200 — Bairro Novo',    '(85) 3333-0002', 'Seg–Sáb 07h–19h | Dom 07h–13h', 'https://wa.me/5585999990002', 1),
('Desffrut — Loja 3', 'Rua dos Legumes, 300 — Bairro da Serra', '(85) 3333-0003', 'Seg–Sáb 07h–19h | Dom 07h–13h', 'https://wa.me/5585999990003', 1);
-- Loja 3 é a loja de referência para tele-entrega (entregador_id/pedidos.loja_id = 3).

-- ─── Usuários de teste (14) — senha padrão "@senha01" para todos ────────────
-- id 1  Robério   — Desenvolvedor          → dev_admin
-- id 2  Paulo     — Dono                   → super_admin
-- id 3  Paula     — RH                     → rh_financeiro
-- id 4  Adriana   — Gerente (todas lojas)  → gerente (loja_id NULL de propósito)
-- id 5  Maria     — Caixa Loja 1           → caixa
-- id 6  Josi      — Caixa Loja 2           → caixa
-- id 7  Lívia     — Caixa Loja 3           → caixa
-- id 8  Francisco — Entregador             → entregador
-- id 9  Henrique  — Motorista (CEASA)      → colaborador
-- id 10 Antônio   — Auxiliar Loja 1        → colaborador
-- id 11 Chagas    — Auxiliar Loja 2        → colaborador
-- id 12 Márcia    — Cliente                → cliente
-- id 13 Jessica   — Cliente                → cliente
-- id 14 Costa     — Cliente                → cliente
INSERT INTO usuarios (nome, email, cpf, telefone, whatsapp, senha_hash, role, loja_id, lgpd_aceito_em) VALUES
('Robério',   'roberio.dev@desffrut.com.br',       '100.100.100-01', '(85) 99999-0001', '(85) 99999-0001', 'HASH_PENDENTE', 'dev_admin',     NULL, NOW()),
('Paulo',     'paulo.dono@desffrut.com.br',        '100.100.100-02', '(85) 99999-0002', '(85) 99999-0002', 'HASH_PENDENTE', 'super_admin',   NULL, NOW()),
('Paula',     'paula.rh@desffrut.com.br',          '100.100.100-03', '(85) 99999-0003', '(85) 99999-0003', 'HASH_PENDENTE', 'rh_financeiro', NULL, NOW()),
('Adriana',   'adriana.gerente@desffrut.com.br',   '100.100.100-04', '(85) 99999-0004', '(85) 99999-0004', 'HASH_PENDENTE', 'gerente',       NULL, NOW()),
('Maria',     'maria.caixa1@desffrut.com.br',      '100.100.100-05', '(85) 99999-0005', '(85) 99999-0005', 'HASH_PENDENTE', 'caixa',         1,    NOW()),
('Josi',      'josi.caixa2@desffrut.com.br',       '100.100.100-06', '(85) 99999-0006', '(85) 99999-0006', 'HASH_PENDENTE', 'caixa',         2,    NOW()),
('Lívia',     'livia.caixa3@desffrut.com.br',      '100.100.100-07', '(85) 99999-0007', '(85) 99999-0007', 'HASH_PENDENTE', 'caixa',         3,    NOW()),
('Francisco', 'francisco.entregador@desffrut.com.br','100.100.100-08','(85) 99999-0008', '(85) 99999-0008', 'HASH_PENDENTE', 'entregador',    3,    NOW()),
('Henrique',  'henrique.motorista@desffrut.com.br',  '100.100.100-09','(85) 99999-0009', '(85) 99999-0009', 'HASH_PENDENTE', 'colaborador',   1,    NOW()),
('Antônio',   'antonio.auxiliar1@desffrut.com.br',   '100.100.100-10','(85) 99999-0010', '(85) 99999-0010', 'HASH_PENDENTE', 'colaborador',   1,    NOW()),
('Chagas',    'chagas.auxiliar2@desffrut.com.br',    '100.100.100-11','(85) 99999-0011', '(85) 99999-0011', 'HASH_PENDENTE', 'colaborador',   2,    NOW()),
('Márcia',    'marcia.cliente@teste.com',            '100.100.100-12', NULL,             NULL,              'HASH_PENDENTE', 'cliente',       NULL, NOW()),
('Jessica',   'jessica.cliente@teste.com',           '100.100.100-13', NULL,             NULL,              'HASH_PENDENTE', 'cliente',       NULL, NOW()),
('Costa',     'costa.cliente@teste.com',             '100.100.100-14', NULL,             NULL,              'HASH_PENDENTE', 'cliente',       NULL, NOW());

-- ─── Fichas de funcionário (RH) — não inclui Robério/Paulo (dev/dono) ───────
-- Assume que os 14 usuários acima foram inseridos com id 1-14, nesta ordem
-- (banco vazio). admitido_em/salário são fictícios para demonstração.
INSERT INTO funcionarios (usuario_id, cpf, telefone, loja_id, cargo, tipo_contrato, admitido_em, salario_base, ativo) VALUES
(3,  '100.100.100-03', '(85) 99999-0003', 1, 'RH / Financeiro',    'clt', '2024-02-01', 2800.00, 1), -- Paula
(4,  '100.100.100-04', '(85) 99999-0004', 1, 'Gerente Geral',      'clt', '2023-06-10', 3800.00, 1), -- Adriana
(5,  '100.100.100-05', '(85) 99999-0005', 1, 'Caixa / Atendente',  'clt', '2024-03-15', 1600.00, 1), -- Maria
(6,  '100.100.100-06', '(85) 99999-0006', 2, 'Caixa / Atendente',  'clt', '2024-04-01', 1600.00, 1), -- Josi
(7,  '100.100.100-07', '(85) 99999-0007', 3, 'Caixa / Atendente',  'clt', '2024-05-20', 1600.00, 1), -- Lívia
(8,  '100.100.100-08', '(85) 99999-0008', 3, 'Entregador',         'clt', '2024-06-01', 1700.00, 1), -- Francisco
(9,  '100.100.100-09', '(85) 99999-0009', 1, 'Motorista',          'clt', '2023-11-05', 1900.00, 1), -- Henrique
(10, '100.100.100-10', '(85) 99999-0010', 1, 'Auxiliar (CEASA)',   'clt', '2024-01-20', 1500.00, 1), -- Antônio
(11, '100.100.100-11', '(85) 99999-0011', 2, 'Auxiliar (CEASA)',   'clt', '2024-02-10', 1500.00, 1); -- Chagas

-- ─── Colaboradores CEASA (frota/logística) — espelha Henrique/Antônio/Chagas ─
-- Tabela independente de `usuarios` (usada pelo módulo CEASA de rotas/recebimento).
INSERT INTO ceasa_colaboradores (nome, funcao, telefone, ativo) VALUES
('Henrique', 'motorista', '(85) 99999-0009', 1),
('Antônio',  'auxiliar',  '(85) 99999-0010', 1),
('Chagas',   'auxiliar',  '(85) 99999-0011', 1);

-- ============================================================
-- PRODUTOS — a partir das imagens em uploads/produtos/
-- ============================================================

-- ─── Produtos (28 itens — todas as imagens de uploads/produtos/, exceto .gitkeep) ─
-- codigo_interno segue o padrão PRD-XXXXXX da migration 12 (formato final).
-- ean é fictício (prefixo 789 — apenas para teste de leitor/scanner).
INSERT INTO produtos (nome, categoria, unidade_medida, preco_custo, ean, codigo_interno, foto, ativo) VALUES
('Pepino',           'legumes', 'kg', 2.00,  '7891234560001', 'PRD-000001', 'uploads/produtos/pepino.webp',          1),
('Banana',            'frutas', 'kg', 1.80,  '7891234560002', 'PRD-000002', 'uploads/produtos/banana.webp',          1),
('Abacaxi',            'frutas', 'un', 3.50,  '7891234560003', 'PRD-000003', 'uploads/produtos/abacaxi.webp',         1),
('Abacate',            'frutas', 'kg', 3.00,  '7891234560004', 'PRD-000004', 'uploads/produtos/abacate.webp',         1),
('Acerola',            'frutas', 'kg', 4.00,  '7891234560005', 'PRD-000005', 'uploads/produtos/acerola.webp',         1),
('Batata Doce',       'legumes', 'kg', 2.20,  '7891234560006', 'PRD-000006', 'uploads/produtos/batata-doce.webp',     1),
('Batata Inglesa',    'legumes', 'kg', 2.80,  '7891234560007', 'PRD-000007', 'uploads/produtos/batata-inglesa.webp',  1),
('Melão',              'frutas', 'un', 4.50,  '7891234560008', 'PRD-000008', 'uploads/produtos/melao.webp',           1),
('Melancia',           'frutas', 'un', 6.00,  '7891234560009', 'PRD-000009', 'uploads/produtos/melancia.webp',        1),
('Goiaba',             'frutas', 'kg', 3.20,  '7891234560010', 'PRD-000010', 'uploads/produtos/goiaba.webp',          1),
('Laranja',            'frutas', 'kg', 1.60,  '7891234560011', 'PRD-000011', 'uploads/produtos/laranja.webp',         1),
('Limão',              'frutas', 'kg', 2.50,  '7891234560012', 'PRD-000012', 'uploads/produtos/limao.webp',           1),
('Tomate',            'legumes', 'kg', 3.50,  '7891234560013', 'PRD-000013', 'uploads/produtos/tomate.webp',          1),
('Uva',                'frutas', 'kg', 7.00,  '7891234560014', 'PRD-000014', 'uploads/produtos/uva.webp',             1),
('Jerimum (Abóbora)', 'legumes', 'un', 5.00,  '7891234560015', 'PRD-000015', 'uploads/produtos/jerimum.webp',         1),
('Cebola',            'legumes', 'kg', 2.90,  '7891234560016', 'PRD-000016', 'uploads/produtos/cebola.webp',          1),
('Alface',           'verduras', 'un', 1.20,  '7891234560017', 'PRD-000017', 'uploads/produtos/alface.webp',          1),
('Mamão',              'frutas', 'kg', 2.80,  '7891234560018', 'PRD-000018', 'uploads/produtos/mamao.webp',           1),
('Alho',              'legumes', 'kg', 12.00, '7891234560019', 'PRD-000019', 'uploads/produtos/alho.webp',            1),
('Pitaia',             'frutas', 'un', 6.50,  '7891234560020', 'PRD-000020', 'uploads/produtos/pitaia.webp',          1),
('Brócolis',         'verduras', 'un', 3.80,  '7891234560021', 'PRD-000021', 'uploads/produtos/brocolis.webp',        1),
('Coco Verde',         'frutas', 'un', 3.50,  '7891234560022', 'PRD-000022', 'uploads/produtos/coco-verde.webp',      1),
('Coco Seco',          'frutas', 'un', 4.00,  '7891234560023', 'PRD-000023', 'uploads/produtos/coco-seco.webp',       1),
('Manga',              'frutas', 'kg', 3.50,  '7891234560024', 'PRD-000024', 'uploads/produtos/manga.webp',           1),
('Pimentão',          'legumes', 'kg', 4.50,  '7891234560025', 'PRD-000025', 'uploads/produtos/pimentao.jpg',         1),
('Beterraba',         'legumes', 'kg', 2.60,  '7891234560026', 'PRD-000026', 'uploads/produtos/beterraba.webp',       1),
('Cenoura',           'legumes', 'kg', 2.30,  '7891234560027', 'PRD-000027', 'uploads/produtos/cenoura.webp',         1),
('Maçã',               'frutas', 'kg', 5.50,  '7891234560028', 'PRD-000028', 'uploads/produtos/maca.webp',            1);

-- ─── Preços (mesmo preço de venda nas 3 lojas — 3 itens em promoção) ────────
-- Assume produto_id 1-28 e loja_id 1-3, nesta ordem de inserção (banco vazio).
INSERT INTO precos (produto_id, loja_id, preco_venda, promo_preco, promo_inicio, promo_fim) VALUES
(1,  1, 3.99,  NULL, NULL, NULL), (1,  2, 3.99,  NULL, NULL, NULL), (1,  3, 3.99,  NULL, NULL, NULL), -- Pepino
(2,  1, 3.49,  2.49, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)), (2,  2, 3.49, 2.49, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)), (2,  3, 3.49, 2.49, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)), -- Banana (promo)
(3,  1, 6.99,  NULL, NULL, NULL), (3,  2, 6.99,  NULL, NULL, NULL), (3,  3, 6.99,  NULL, NULL, NULL), -- Abacaxi
(4,  1, 5.99,  NULL, NULL, NULL), (4,  2, 5.99,  NULL, NULL, NULL), (4,  3, 5.99,  NULL, NULL, NULL), -- Abacate
(5,  1, 7.99,  NULL, NULL, NULL), (5,  2, 7.99,  NULL, NULL, NULL), (5,  3, 7.99,  NULL, NULL, NULL), -- Acerola
(6,  1, 3.99,  NULL, NULL, NULL), (6,  2, 3.99,  NULL, NULL, NULL), (6,  3, 3.99,  NULL, NULL, NULL), -- Batata Doce
(7,  1, 4.99,  NULL, NULL, NULL), (7,  2, 4.99,  NULL, NULL, NULL), (7,  3, 4.99,  NULL, NULL, NULL), -- Batata Inglesa
(8,  1, 7.99,  NULL, NULL, NULL), (8,  2, 7.99,  NULL, NULL, NULL), (8,  3, 7.99,  NULL, NULL, NULL), -- Melão
(9,  1, 9.99,  NULL, NULL, NULL), (9,  2, 9.99,  NULL, NULL, NULL), (9,  3, 9.99,  NULL, NULL, NULL), -- Melancia
(10, 1, 5.99,  NULL, NULL, NULL), (10, 2, 5.99,  NULL, NULL, NULL), (10, 3, 5.99,  NULL, NULL, NULL), -- Goiaba
(11, 1, 2.99,  NULL, NULL, NULL), (11, 2, 2.99,  NULL, NULL, NULL), (11, 3, 2.99,  NULL, NULL, NULL), -- Laranja
(12, 1, 4.49,  NULL, NULL, NULL), (12, 2, 4.49,  NULL, NULL, NULL), (12, 3, 4.49,  NULL, NULL, NULL), -- Limão
(13, 1, 5.99,  4.49, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY)), (13, 2, 5.99, 4.49, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY)), (13, 3, 5.99, 4.49, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY)), -- Tomate (promo)
(14, 1, 12.99, NULL, NULL, NULL), (14, 2, 12.99, NULL, NULL, NULL), (14, 3, 12.99, NULL, NULL, NULL), -- Uva
(15, 1, 8.99,  NULL, NULL, NULL), (15, 2, 8.99,  NULL, NULL, NULL), (15, 3, 8.99,  NULL, NULL, NULL), -- Jerimum
(16, 1, 4.99,  NULL, NULL, NULL), (16, 2, 4.99,  NULL, NULL, NULL), (16, 3, 4.99,  NULL, NULL, NULL), -- Cebola
(17, 1, 2.49,  NULL, NULL, NULL), (17, 2, 2.49,  NULL, NULL, NULL), (17, 3, 2.49,  NULL, NULL, NULL), -- Alface
(18, 1, 4.99,  NULL, NULL, NULL), (18, 2, 4.99,  NULL, NULL, NULL), (18, 3, 4.99,  NULL, NULL, NULL), -- Mamão
(19, 1, 19.99, NULL, NULL, NULL), (19, 2, 19.99, NULL, NULL, NULL), (19, 3, 19.99, NULL, NULL, NULL), -- Alho
(20, 1, 11.99, NULL, NULL, NULL), (20, 2, 11.99, NULL, NULL, NULL), (20, 3, 11.99, NULL, NULL, NULL), -- Pitaia
(21, 1, 6.99,  NULL, NULL, NULL), (21, 2, 6.99,  NULL, NULL, NULL), (21, 3, 6.99,  NULL, NULL, NULL), -- Brócolis
(22, 1, 5.99,  NULL, NULL, NULL), (22, 2, 5.99,  NULL, NULL, NULL), (22, 3, 5.99,  NULL, NULL, NULL), -- Coco Verde
(23, 1, 6.99,  NULL, NULL, NULL), (23, 2, 6.99,  NULL, NULL, NULL), (23, 3, 6.99,  NULL, NULL, NULL), -- Coco Seco
(24, 1, 5.99,  4.99, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY)), (24, 2, 5.99, 4.99, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY)), (24, 3, 5.99, 4.99, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY)), -- Manga (promo)
(25, 1, 7.99,  NULL, NULL, NULL), (25, 2, 7.99,  NULL, NULL, NULL), (25, 3, 7.99,  NULL, NULL, NULL), -- Pimentão
(26, 1, 4.49,  NULL, NULL, NULL), (26, 2, 4.49,  NULL, NULL, NULL), (26, 3, 4.49,  NULL, NULL, NULL), -- Beterraba
(27, 1, 3.99,  NULL, NULL, NULL), (27, 2, 3.99,  NULL, NULL, NULL), (27, 3, 3.99,  NULL, NULL, NULL), -- Cenoura
(28, 1, 8.99,  NULL, NULL, NULL), (28, 2, 8.99,  NULL, NULL, NULL), (28, 3, 8.99,  NULL, NULL, NULL); -- Maçã

-- ─── Estoque (quantidades variam levemente por loja, só para parecer real) ──
INSERT INTO estoque (produto_id, loja_id, quantidade, estoque_minimo) VALUES
(1,  1, 60.000, 10.000), (1,  2, 45.000, 10.000), (1,  3, 70.000, 10.000), -- Pepino
(2,  1, 90.000, 15.000), (2,  2, 75.000, 15.000), (2,  3, 100.000,15.000), -- Banana
(3,  1, 40.000,  8.000), (3,  2, 30.000,  8.000), (3,  3, 45.000,  8.000), -- Abacaxi
(4,  1, 35.000,  6.000), (4,  2, 25.000,  6.000), (4,  3, 38.000,  6.000), -- Abacate
(5,  1, 15.000,  3.000), (5,  2, 10.000,  3.000), (5,  3, 18.000,  3.000), -- Acerola
(6,  1, 50.000, 10.000), (6,  2, 40.000, 10.000), (6,  3, 55.000, 10.000), -- Batata Doce
(7,  1, 80.000, 15.000), (7,  2, 65.000, 15.000), (7,  3, 85.000, 15.000), -- Batata Inglesa
(8,  1, 30.000,  6.000), (8,  2, 22.000,  6.000), (8,  3, 33.000,  6.000), -- Melão
(9,  1, 20.000,  5.000), (9,  2, 15.000,  5.000), (9,  3, 24.000,  5.000), -- Melancia
(10, 1, 28.000,  6.000), (10, 2, 20.000,  6.000), (10, 3, 30.000,  6.000), -- Goiaba
(11, 1, 100.000,15.000), (11, 2, 85.000, 15.000), (11, 3, 110.000,15.000), -- Laranja
(12, 1, 45.000,  8.000), (12, 2, 35.000,  8.000), (12, 3, 48.000,  8.000), -- Limão
(13, 1, 70.000, 12.000), (13, 2, 55.000, 12.000), (13, 3, 75.000, 12.000), -- Tomate
(14, 1, 25.000,  5.000), (14, 2, 18.000,  5.000), (14, 3, 28.000,  5.000), -- Uva
(15, 1, 18.000,  4.000), (15, 2, 12.000,  4.000), (15, 3, 20.000,  4.000), -- Jerimum
(16, 1, 65.000, 12.000), (16, 2, 50.000, 12.000), (16, 3, 68.000, 12.000), -- Cebola
(17, 1, 35.000,  8.000), (17, 2, 28.000,  8.000), (17, 3, 38.000,  8.000), -- Alface
(18, 1, 30.000,  6.000), (18, 2, 22.000,  6.000), (18, 3, 32.000,  6.000), -- Mamão
(19, 1, 20.000,  4.000), (19, 2, 15.000,  4.000), (19, 3, 22.000,  4.000), -- Alho
(20, 1, 10.000,  2.000), (20, 2, 7.000,   2.000), (20, 3, 12.000,  2.000), -- Pitaia
(21, 1, 22.000,  5.000), (21, 2, 16.000,  5.000), (21, 3, 24.000,  5.000), -- Brócolis
(22, 1, 25.000,  5.000), (22, 2, 18.000,  5.000), (22, 3, 27.000,  5.000), -- Coco Verde
(23, 1, 18.000,  4.000), (23, 2, 13.000,  4.000), (23, 3, 20.000,  4.000), -- Coco Seco
(24, 1, 40.000,  8.000), (24, 2, 30.000,  8.000), (24, 3, 42.000,  8.000), -- Manga
(25, 1, 32.000,  6.000), (25, 2, 24.000,  6.000), (25, 3, 34.000,  6.000), -- Pimentão
(26, 1, 28.000,  6.000), (26, 2, 20.000,  6.000), (26, 3, 30.000,  6.000), -- Beterraba
(27, 1, 55.000, 10.000), (27, 2, 42.000, 10.000), (27, 3, 58.000, 10.000), -- Cenoura
(28, 1, 38.000,  8.000), (28, 2, 28.000,  8.000), (28, 3, 40.000,  8.000); -- Maçã

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Seed de teste aplicado: 3 lojas, 14 usuários, 28 produtos com preço e estoque nas 3 lojas.' AS status;
