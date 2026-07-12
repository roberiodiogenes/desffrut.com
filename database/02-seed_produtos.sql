-- ============================================================
-- DESFFRUT — Seed de Produtos para Teste (Fase 2)
-- Execute após seed.sql:
--   mysql -u root -p desffrut_dev < database/seed_produtos.sql
-- ============================================================

SET NAMES utf8mb4;

-- ─── Produtos ────────────────────────────────────────────────────────────────
INSERT INTO produtos (nome, categoria, unidade_medida, preco_custo, ativo) VALUES
-- Frutas
('Banana Nanica',     'frutas',   'kg',  1.80, 1),
('Maçã Gala',         'frutas',   'kg',  4.20, 1),
('Laranja Pera',      'frutas',   'kg',  1.50, 1),
('Manga Tommy',       'frutas',   'kg',  3.00, 1),
('Uva Thompson',      'frutas',   'kg',  7.50, 1),
('Melancia',          'frutas',   'un',  6.00, 1),
('Abacaxi Pérola',    'frutas',   'un',  2.80, 1),
-- Verduras
('Alface Crespa',     'verduras', 'un',  0.80, 1),
('Couve Manteiga',    'verduras', 'un',  0.90, 1),
('Rúcula',            'verduras', 'un',  1.20, 1),
('Espinafre',         'verduras', 'un',  1.10, 1),
('Agrião',            'verduras', 'un',  1.30, 1),
-- Legumes
('Tomate Salada',     'legumes',  'kg',  3.00, 1),
('Cenoura',           'legumes',  'kg',  2.20, 1),
('Batata Inglesa',    'legumes',  'kg',  3.50, 1),
('Chuchu',            'legumes',  'kg',  1.80, 1),
('Abobrinha',         'legumes',  'kg',  2.50, 1),
('Pimentão Verde',    'legumes',  'kg',  4.00, 1);

-- ─── Preços na Loja 1 (com promoção em alguns itens) ─────────────────────────
INSERT INTO precos (produto_id, loja_id, preco_venda, promo_preco, promo_inicio, promo_fim) VALUES
-- Frutas
(1,  1, 3.99,  2.49, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),  -- Banana em promoção
(2,  1, 7.99,  NULL, NULL, NULL),
(3,  1, 2.99,  NULL, NULL, NULL),
(4,  1, 5.99,  4.99, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY)),  -- Manga em promoção
(5,  1, 14.99, NULL, NULL, NULL),
(6,  1, 12.99, NULL, NULL, NULL),
(7,  1, 5.99,  NULL, NULL, NULL),
-- Verduras
(8,  1, 2.49,  NULL, NULL, NULL),
(9,  1, 2.99,  NULL, NULL, NULL),
(10, 1, 3.49,  NULL, NULL, NULL),
(11, 1, 2.99,  NULL, NULL, NULL),
(12, 1, 3.29,  NULL, NULL, NULL),
-- Legumes
(13, 1, 5.99,  4.49, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY)),  -- Tomate em promoção
(14, 1, 3.99,  NULL, NULL, NULL),
(15, 1, 5.99,  NULL, NULL, NULL),
(16, 1, 2.99,  NULL, NULL, NULL),
(17, 1, 4.49,  NULL, NULL, NULL),
(18, 1, 6.99,  NULL, NULL, NULL);

-- ─── Estoque na Loja 1 ────────────────────────────────────────────────────────
INSERT INTO estoque (produto_id, loja_id, quantidade, estoque_minimo) VALUES
(1,  1, 85.000,  10.000),
(2,  1, 40.000,   5.000),
(3,  1, 120.000, 15.000),
(4,  1, 35.000,   5.000),
(5,  1, 22.000,   3.000),
(6,  1, 8.000,    2.000),
(7,  1, 12.000,   3.000),
(8,  1, 25.000,   5.000),
(9,  1, 18.000,   5.000),
(10, 1, 15.000,   3.000),
(11, 1, 20.000,   5.000),
(12, 1, 10.000,   3.000),
(13, 1, 60.000,  10.000),
(14, 1, 75.000,  10.000),
(15, 1, 50.000,  10.000),
(16, 1, 90.000,  15.000),
(17, 1, 45.000,   8.000),
(18, 1, 30.000,   5.000);
