<?php
class Produto {

    private PDO $db;

    public function __construct() { $this->db = db(); }

    // ── Catálogo público centralizado ─────────────────────────────────────────
    public function catalogo(?string $categoria = null): array {
        $where  = 'WHERE p.ativo = 1';
        $params = [];
        if ($categoria) {
            $where .= ' AND p.categoria = :categoria';
            $params['categoria'] = $categoria;
        }

        $stmt = $this->db->prepare("
            SELECT
                p.id, p.nome, p.categoria, p.unidade_medida, p.foto,
                MIN(pr.preco_venda) AS preco_referencia,
                MIN(CASE
                    WHEN pr.promo_preco IS NOT NULL
                     AND pr.promo_inicio <= NOW() AND pr.promo_fim >= NOW()
                    THEN pr.promo_preco ELSE pr.preco_venda
                END) AS preco_atual,
                SUM(e.quantidade) AS estoque_total,
                MAX(CASE
                    WHEN pr.promo_preco IS NOT NULL
                     AND pr.promo_inicio <= NOW() AND pr.promo_fim >= NOW()
                    THEN 1 ELSE 0
                END) AS em_promocao
            FROM produtos p
            INNER JOIN precos  pr ON pr.produto_id = p.id
            INNER JOIN estoque e  ON e.produto_id = p.id AND e.loja_id = pr.loja_id
            {$where}
            GROUP BY p.id, p.nome, p.categoria, p.unidade_medida, p.foto
            HAVING SUM(e.quantidade) > 0
            ORDER BY p.categoria, p.nome
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Listagem para gerência ────────────────────────────────────────────────
    public function listar(array $filtros = []): array {
        $where  = 'WHERE 1=1';
        $params = [];

        if (isset($filtros['categoria']) && $filtros['categoria']) {
            $where .= ' AND p.categoria = :categoria';
            $params['categoria'] = $filtros['categoria'];
        }
        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $where .= ' AND p.ativo = :ativo';
            $params['ativo'] = (int) $filtros['ativo'];
        }
        if (!empty($filtros['busca'])) {
            $where .= ' AND (p.nome LIKE :busca OR p.ean LIKE :busca2)';
            $params['busca']  = '%' . $filtros['busca'] . '%';
            $params['busca2'] = '%' . $filtros['busca'] . '%';
        }

        $stmt = $this->db->prepare("
            SELECT
                p.id, p.nome, p.descricao, p.categoria, p.unidade_medida,
                p.ean, p.preco_custo, p.ativo, p.foto,
                COALESCE(SUM(e.quantidade), 0) AS estoque_total,
                MIN(pr.preco_venda)             AS preco_referencia
            FROM produtos p
            LEFT JOIN estoque e ON e.produto_id = p.id
            LEFT JOIN precos  pr ON pr.produto_id = p.id
            {$where}
            GROUP BY p.id, p.nome, p.descricao, p.categoria, p.unidade_medida,
                     p.ean, p.preco_custo, p.ativo, p.foto
            ORDER BY p.categoria, p.nome
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Busca produto com preços por loja ─────────────────────────────────────
    public function buscarComPrecos(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM produtos WHERE id = ?');
        $stmt->execute([$id]);
        $produto = $stmt->fetch();
        if (!$produto) return null;

        $stmt2 = $this->db->prepare('
            SELECT pr.*, l.nome AS loja_nome
            FROM precos pr
            JOIN lojas l ON l.id = pr.loja_id
            WHERE pr.produto_id = ?
            ORDER BY l.nome
        ');
        $stmt2->execute([$id]);
        $produto['precos'] = $stmt2->fetchAll();
        return $produto;
    }

    // ── buscarPorId ───────────────────────────────────────────────────────────
    public function buscarPorId(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM produtos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ── Criar ─────────────────────────────────────────────────────────────────
    public function criar(array $d): int {
        $stmt = $this->db->prepare('
            INSERT INTO produtos (nome, descricao, ean, categoria, unidade_medida, preco_custo, foto, ativo)
            VALUES (:nome, :descricao, :ean, :categoria, :unidade_medida, :preco_custo, :foto, 1)
        ');
        $stmt->execute([
            'nome'          => $d['nome'],
            'descricao'     => $d['descricao']     ?? null,
            'ean'           => $d['ean']            ?? null,
            'categoria'     => $d['categoria'],
            'unidade_medida'=> $d['unidade_medida'],
            'preco_custo'   => (float) ($d['preco_custo'] ?? 0),
            'foto'          => $d['foto']           ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ── Atualizar ─────────────────────────────────────────────────────────────
    public function atualizar(int $id, array $d): bool {
        $sets  = ['nome = :nome', 'descricao = :descricao', 'ean = :ean',
                  'categoria = :categoria', 'unidade_medida = :unidade_medida',
                  'preco_custo = :preco_custo', 'updated_at = NOW()'];
        $params = [
            'id'            => $id,
            'nome'          => $d['nome'],
            'descricao'     => $d['descricao']      ?? null,
            'ean'           => $d['ean']             ?? null,
            'categoria'     => $d['categoria'],
            'unidade_medida'=> $d['unidade_medida'],
            'preco_custo'   => (float) ($d['preco_custo'] ?? 0),
        ];

        if (isset($d['foto'])) {
            $sets[]       = 'foto = :foto';
            $params['foto'] = $d['foto'];
        }

        $stmt = $this->db->prepare(
            'UPDATE produtos SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Toggle ativo/inativo (sem apagar histórico) ───────────────────────────
    public function toggleAtivo(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE produtos SET ativo = 1 - ativo WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // ── Preço por loja ────────────────────────────────────────────────────────
    public function salvarPreco(int $produto_id, int $loja_id, array $d): void {
        $this->db->prepare('
            INSERT INTO precos (produto_id, loja_id, preco_venda, promo_preco, promo_inicio, promo_fim)
            VALUES (:pid, :lid, :pv, :pp, :pi, :pf)
            ON DUPLICATE KEY UPDATE
                preco_venda  = VALUES(preco_venda),
                promo_preco  = VALUES(promo_preco),
                promo_inicio = VALUES(promo_inicio),
                promo_fim    = VALUES(promo_fim),
                updated_at   = NOW()
        ')->execute([
            'pid' => $produto_id,
            'lid' => $loja_id,
            'pv'  => (float) $d['preco_venda'],
            'pp'  => !empty($d['promo_preco'])  ? (float) $d['promo_preco']  : null,
            'pi'  => !empty($d['promo_inicio']) ? $d['promo_inicio']         : null,
            'pf'  => !empty($d['promo_fim'])    ? $d['promo_fim']            : null,
        ]);
    }

    // ── Estoque crítico (abaixo do mínimo) ────────────────────────────────────
    public function estoqueCritico(): array {
        return $this->db->query('
            SELECT
                p.id, p.nome, p.categoria, p.unidade_medida,
                l.nome AS loja_nome,
                e.quantidade, e.estoque_minimo,
                (e.estoque_minimo - e.quantidade) AS deficit
            FROM estoque e
            JOIN produtos p ON p.id = e.produto_id
            JOIN lojas    l ON l.id = e.loja_id
            WHERE e.quantidade < e.estoque_minimo AND p.ativo = 1
            ORDER BY deficit DESC, p.nome
        ')->fetchAll();
    }
}
