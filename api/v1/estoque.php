<?php
require_once __DIR__ . '/../../app/middleware/modo_restrito.php'; // Categoria 22
/**
 * Desffrut — API v1: Estoque
 *
 * GET  /api/v1/estoque?loja_id=X         → inventário da filial
 * PUT  /api/v1/estoque                   → atualizar quantidade (body: produto_id, loja_id, quantidade)
 * POST /api/v1/estoque/quebra            → lançar quebra/avaria
 * GET  /api/v1/estoque/critico           → produtos abaixo do estoque mínimo (todas as lojas)
 */
require_once __DIR__ . '/../../app/models/Produto.php';

$u = api_auth_exigir();
api_auth_role(['gerente', 'super_admin']);

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /estoque/critico ──────────────────────────────────────────────────────
if ($id === 'critico' && $method === 'GET') {
    $criticos = (new Produto())->estoqueCritico();
    json_response(['status' => 'ok', 'data' => $criticos, 'message' => '']);
}

// ── GET /estoque/quebras?loja_id=X&data_ini=Y&data_fim=Z ──────────────────────
if ($id === 'quebras' && $method === 'GET') {
    $loja_id  = filter_input(INPUT_GET, 'loja_id',  FILTER_VALIDATE_INT) ?: null;
    $data_ini = filter_input(INPUT_GET, 'data_ini', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

    // Gerente é responsável por todas as lojas — mesma visão multi-loja do super_admin.

    $where  = 'WHERE 1=1';
    $params = [];
    if ($loja_id)  { $where .= ' AND q.loja_id = :loja_id';  $params['loja_id']  = $loja_id; }
    if ($data_ini) { $where .= ' AND DATE(q.created_at) >= :data_ini'; $params['data_ini'] = $data_ini; }
    if ($data_fim) { $where .= ' AND DATE(q.created_at) <= :data_fim'; $params['data_fim'] = $data_fim; }

    $stmt = db()->prepare("
        SELECT
            q.id, q.quantidade, q.motivo, q.created_at,
            p.nome  AS produto_nome, p.unidade_medida,
            l.nome  AS loja_nome,
            u.nome  AS usuario_nome
        FROM quebras q
        JOIN produtos p ON p.id = q.produto_id
        JOIN lojas    l ON l.id = q.loja_id
        LEFT JOIN usuarios u ON u.id = q.usuario_id
        {$where}
        ORDER BY q.created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll(), 'message' => '']);
}

// ── GET /estoque?loja_id=X ────────────────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT);

    // Gerente é responsável por todas as lojas — escolhe a loja explicitamente,
    // como o super_admin.

    if (!$loja_id) {
        json_response(['status' => 'error', 'message' => 'loja_id obrigatório.', 'data' => null], 422);
    }

    $stmt = db()->prepare('
        SELECT
            p.id, p.nome, p.categoria, p.unidade_medida, p.foto, p.ativo,
            e.quantidade, e.estoque_minimo,
            CASE
                WHEN e.quantidade = 0          THEN "sem_estoque"
                WHEN e.quantidade < e.estoque_minimo THEN "critico"
                WHEN e.quantidade < e.estoque_minimo * 1.5 THEN "baixo"
                ELSE "ok"
            END AS situacao
        FROM produtos p
        LEFT JOIN estoque e ON e.produto_id = p.id AND e.loja_id = :loja_id
        WHERE p.ativo = 1
        ORDER BY p.categoria, p.nome
    ');
    $stmt->execute(['loja_id' => $loja_id]);

    json_response(['status' => 'ok', 'data' => $stmt->fetchAll(), 'message' => '']);
}

// ── PUT /estoque (atualizar quantidade) ───────────────────────────────────────
if ($id === null && $method === 'PUT') {
    $produto_id  = (int)   ($body['produto_id']    ?? 0);
    $loja_id     = (int)   ($body['loja_id']        ?? 0);
    $quantidade  = (float) ($body['quantidade']     ?? -1);
    $estoque_min = isset($body['estoque_minimo']) ? (float) $body['estoque_minimo'] : 0.0;

    if (!$produto_id || !$loja_id || $quantidade < 0) {
        json_response(['status' => 'error', 'message' => 'produto_id, loja_id e quantidade (≥0) são obrigatórios.', 'data' => null], 422);
    }

    // Gerente é responsável por todas as lojas e pode alterar o estoque de qualquer uma.

    // IMPORTANTE: PDO com EMULATE_PREPARES=false não permite reutilizar o mesmo
    // named placeholder na mesma query. Por isso usamos nomes distintos para
    // INSERT VALUES e ON DUPLICATE KEY UPDATE.
    db()->prepare('
        INSERT INTO estoque (produto_id, loja_id, quantidade, estoque_minimo)
        VALUES (:pid_v, :lid_v, :qtd_v, :min_v)
        ON DUPLICATE KEY UPDATE
            quantidade     = :qtd_u,
            estoque_minimo = :min_u
    ')->execute([
        ':pid_v' => $produto_id,
        ':lid_v' => $loja_id,
        ':qtd_v' => $quantidade,
        ':min_v' => $estoque_min,
        ':qtd_u' => $quantidade,
        ':min_u' => $estoque_min,
    ]);

    registrar_log((int) $u['id'], 'estoque_ajuste', 'estoque', $produto_id,
        ['loja_id' => $loja_id, 'nova_quantidade' => $quantidade]);

    json_response(['status' => 'ok', 'data' => null, 'message' => 'Estoque atualizado.']);
}

// ── POST /estoque/quebra ──────────────────────────────────────────────────────
if ($id === 'quebra' && $method === 'POST') {
    $produto_id = (int)    ($body['produto_id'] ?? 0);
    $loja_id    = (int)    ($body['loja_id']    ?? 0);
    $quantidade = (float)  ($body['quantidade'] ?? 0);
    $motivo     = sanitize($body['motivo']      ?? '');

    if (!$produto_id || !$loja_id || $quantidade <= 0 || empty($motivo)) {
        json_response(['status' => 'error', 'message' => 'produto_id, loja_id, quantidade e motivo são obrigatórios.', 'data' => null], 422);
    }

    // Gerente é responsável por todas as lojas e pode registrar quebra em qualquer uma.

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Registra a quebra
        $pdo->prepare('
            INSERT INTO quebras (produto_id, loja_id, quantidade, motivo, usuario_id)
            VALUES (:pid, :lid, :qtd, :mot, :uid)
        ')->execute([
            'pid' => $produto_id, 'lid' => $loja_id,
            'qtd' => $quantidade, 'mot' => $motivo,
            'uid' => (int) $u['id'],
        ]);
        $quebra_id = (int) $pdo->lastInsertId();

        // Garante que a linha de estoque existe e deduz a quantidade.
        // INSERT cria a linha zerada se não existir; ON DUPLICATE deduz do saldo atual.
        // Placeholders distintos para cada posição (PDO emulate_prepares=false).
        $pdo->prepare('
            INSERT INTO estoque (produto_id, loja_id, quantidade, estoque_minimo)
            VALUES (:pid_v, :lid_v, GREATEST(0, 0 - :qtd_v), 0)
            ON DUPLICATE KEY UPDATE
                quantidade = GREATEST(0, quantidade - :qtd_u)
        ')->execute([
            ':pid_v' => $produto_id,
            ':lid_v' => $loja_id,
            ':qtd_v' => $quantidade,
            ':qtd_u' => $quantidade,
        ]);

        $pdo->commit();

        registrar_log((int) $u['id'], 'quebra_registrada', 'quebras', $quebra_id,
            ['produto_id' => $produto_id, 'loja_id' => $loja_id, 'quantidade' => $quantidade]);

        json_response(['status' => 'ok', 'data' => ['id' => $quebra_id], 'message' => 'Quebra registrada e estoque deduzido.']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Erro ao registrar quebra.', 'data' => null], 500);
    }
}

json_response(['status' => 'error', 'message' => 'Endpoint não encontrado.', 'data' => null], 404);
