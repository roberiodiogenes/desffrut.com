<?php
/**
 * Desffrut — API v1: Recebimentos CEASA
 *
 * GET  /api/v1/ceasa_recebimentos          → listar histórico (com paginação)
 * GET  /api/v1/ceasa_recebimentos/{id}     → detalhes de um recebimento
 * POST /api/v1/ceasa_recebimentos          → registrar novo recebimento (salva + atualiza estoque)
 */

if (!function_exists('api_auth_exigir')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado.']);
    exit;
}

$u    = api_auth_exigir();
$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

api_auth_role(['super_admin', 'gerente']);

// ── GET /ceasa_recebimentos ────────────────────────────────────────────────────
if ($method === 'GET' && $id === null) {
    $limit  = min((int) ($_GET['limit'] ?? 30), 100);
    $offset = max((int) ($_GET['offset'] ?? 0), 0);

    $stmt = $pdo->prepare("
        SELECT
            r.id, r.data_recebimento, r.status, r.observacoes_gerais,
            r.total_itens, r.total_recebidos, r.created_at,
            l.nome AS loja_nome,
            u.nome AS responsavel_nome,
            cr.rota_descricao,
            f.modelo AS frota_modelo, f.placa AS frota_placa,
            c_mot.nome AS motorista_nome
        FROM ceasa_recebimentos r
        JOIN  lojas   l ON l.id = r.loja_id
        LEFT JOIN usuarios u ON u.id = r.responsavel_id
        LEFT JOIN ceasa_rotas cr ON cr.id = r.rota_id
        LEFT JOIN frota f ON f.id = cr.frota_id
        LEFT JOIN ceasa_colaboradores c_mot ON c_mot.id = cr.motorista_id
        ORDER BY r.data_recebimento DESC, r.id DESC
        LIMIT :lim OFFSET :off
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

// ── GET /ceasa_recebimentos/{id} ───────────────────────────────────────────────
if ($method === 'GET' && $id !== null) {
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            l.nome AS loja_nome,
            u.nome AS responsavel_nome,
            cr.rota_descricao, cr.data_rota,
            f.modelo AS frota_modelo, f.placa AS frota_placa, f.cor AS frota_cor,
            c_mot.nome AS motorista_nome,
            c_a1.nome  AS auxiliar1_nome,
            c_a2.nome  AS auxiliar2_nome
        FROM ceasa_recebimentos r
        JOIN  lojas l ON l.id = r.loja_id
        LEFT JOIN usuarios u ON u.id = r.responsavel_id
        LEFT JOIN ceasa_rotas cr ON cr.id = r.rota_id
        LEFT JOIN frota f ON f.id = cr.frota_id
        LEFT JOIN ceasa_colaboradores c_mot ON c_mot.id = cr.motorista_id
        LEFT JOIN ceasa_colaboradores c_a1  ON c_a1.id  = cr.auxiliar1_id
        LEFT JOIN ceasa_colaboradores c_a2  ON c_a2.id  = cr.auxiliar2_id
        WHERE r.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $rec = $stmt->fetch();
    if (!$rec) json_error('Recebimento não encontrado.', 404);

    // Itens
    $si = $pdo->prepare("
        SELECT ri.*, p.nome AS produto_nome, p.unidade_medida
        FROM ceasa_recebimento_itens ri
        JOIN produtos p ON p.id = ri.produto_id
        WHERE ri.recebimento_id = :rid
        ORDER BY p.nome
    ");
    $si->execute(['rid' => $id]);
    $rec['itens'] = $si->fetchAll();

    json_response(['status' => 'ok', 'data' => $rec]);
}

// ── POST /ceasa_recebimentos ───────────────────────────────────────────────────
if ($method === 'POST') {
    $loja_id = filter_var($body['loja_id'] ?? null, FILTER_VALIDATE_INT);
    $data    = $body['data_recebimento'] ?? date('Y-m-d');
    $itens   = $body['itens'] ?? [];
    $rota_id = filter_var($body['rota_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $obs     = sanitize($body['observacoes_gerais'] ?? '');

    if (!$loja_id) json_error('loja_id é obrigatório.', 422);
    if (empty($itens)) json_error('itens não pode ser vazio.', 422);

    $total_itens    = count($itens);
    $total_recebidos = 0;
    foreach ($itens as $it) {
        if ((float)($it['qtd_recebida'] ?? 0) > 0) $total_recebidos++;
    }

    $pdo->beginTransaction();
    try {
        // Cria o recebimento
        $pdo->prepare("
            INSERT INTO ceasa_recebimentos (rota_id, loja_id, data_recebimento, responsavel_id,
                                            observacoes_gerais, total_itens, total_recebidos, status)
            VALUES (:rid, :lid, :data, :resp, :obs, :ti, :tr, 'confirmado')
        ")->execute([
            'rid'  => $rota_id,
            'lid'  => $loja_id,
            'data' => $data,
            'resp' => $u['id'],
            'obs'  => $obs,
            'ti'   => $total_itens,
            'tr'   => $total_recebidos,
        ]);
        $receb_id = (int) $pdo->lastInsertId();

        foreach ($itens as $it) {
            $pid         = (int)   ($it['produto_id']  ?? 0);
            $qtd_pedida  = (float) ($it['qtd_pedida']  ?? 0);
            $qtd_rec     = (float) ($it['qtd_recebida'] ?? 0);
            $qtd_quebra  = (float) ($it['qtd_quebra']  ?? 0);
            $nao_ent     = (int)   ($it['nao_entregue'] ?? 0);
            $obs_it      = sanitize($it['observacao'] ?? '');

            if (!$pid) continue;

            // Salva item
            $pdo->prepare("
                INSERT INTO ceasa_recebimento_itens
                    (recebimento_id, produto_id, qtd_pedida, qtd_recebida, qtd_quebra, nao_entregue, observacao)
                VALUES (:rid, :pid, :qped, :qrec, :qqueb, :ne, :obs)
            ")->execute([
                'rid'   => $receb_id,
                'pid'   => $pid,
                'qped'  => $qtd_pedida,
                'qrec'  => $qtd_rec,
                'qqueb' => $qtd_quebra,
                'ne'    => $nao_ent,
                'obs'   => $obs_it,
            ]);

            // Atualiza estoque apenas se qtd_recebida > 0
            if ($qtd_rec > 0) {
                // Busca estoque atual
                $se = $pdo->prepare("SELECT id, quantidade FROM estoque WHERE produto_id=:pid AND loja_id=:lid LIMIT 1");
                $se->execute(['pid' => $pid, 'lid' => $loja_id]);
                $est = $se->fetch();

                if ($est) {
                    $nova_qtd = (float) $est['quantidade'] + $qtd_rec - $qtd_quebra;
                    $pdo->prepare("UPDATE estoque SET quantidade=:q WHERE id=:id")
                        ->execute(['q' => max(0, $nova_qtd), 'id' => $est['id']]);
                } else {
                    // Cria registro de estoque se não existir
                    $pdo->prepare("INSERT INTO estoque (produto_id, loja_id, quantidade) VALUES (:pid,:lid,:q)")
                        ->execute(['pid' => $pid, 'lid' => $loja_id, 'q' => max(0, $qtd_rec - $qtd_quebra)]);
                }
            }
        }

        $pdo->commit();
        json_response(['status' => 'ok', 'id' => $receb_id,
            'message' => "$total_recebidos de $total_itens itens recebidos. Estoque atualizado."]);

    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error('Erro ao salvar recebimento: ' . $e->getMessage(), 500);
    }
}

json_error('Método não suportado.', 405);
