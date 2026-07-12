<?php
// Guard
if (!function_exists('api_auth_exigir')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Acesso direto não permitido.']); exit;
}
require_once __DIR__ . '/../../app/middleware/modo_restrito.php'; // Categoria 22
/**
 * Desffrut — API v1: Contas a Pagar (Financeiro)
 *
 * GET    /api/v1/contas_pagar                    → lista (filtros: status, loja, mes)
 * POST   /api/v1/contas_pagar                    → lançar conta
 * PUT    /api/v1/contas_pagar/{id}               → editar
 * PATCH  /api/v1/contas_pagar/{id}               → marcar como paga
 * DELETE /api/v1/contas_pagar/{id}               → excluir (só pendentes)
 * GET    /api/v1/contas_pagar/resumo             → totais por categoria/mês (BI)
 */

$u   = api_auth_exigir();
api_auth_role(['super_admin', 'rh_financeiro']);
$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /contas_pagar/resumo ──────────────────────────────────────────────────
if ($id === 'resumo' && $method === 'GET') {
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $ano     = (int) ($_GET['ano'] ?? date('Y'));
    $where   = 'WHERE YEAR(vencimento) = :ano';
    $params  = ['ano' => $ano];
    if ($loja_id) { $where .= ' AND loja_id = :loja_id'; $params['loja_id'] = $loja_id; }

    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(vencimento,'%Y-%m') AS mes,
            categoria,
            SUM(valor) AS total,
            SUM(CASE WHEN status='pago' THEN valor ELSE 0 END) AS total_pago,
            SUM(CASE WHEN status='pendente' THEN valor ELSE 0 END) AS total_pendente,
            COUNT(*) AS qtd
        FROM contas_pagar
        {$where}
        GROUP BY mes, categoria
        ORDER BY mes, categoria
    ");
    $stmt->execute($params);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

// ── GET /contas_pagar ─────────────────────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $status  = sanitize($_GET['status'] ?? '');
    $mes     = sanitize($_GET['mes'] ?? '');

    $where  = 'WHERE 1=1'; $params = [];
    if ($loja_id) { $where .= ' AND cp.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }
    if ($status && in_array($status,['pendente','pago','vencido'],true)) {
        $where .= ' AND cp.status = :status'; $params['status'] = $status;
    }
    if ($mes) { $where .= " AND DATE_FORMAT(cp.vencimento,'%Y-%m') = :mes"; $params['mes'] = $mes; }

    // Auto-atualiza status vencido
    $pdo->prepare("UPDATE contas_pagar SET status='vencido'
                   WHERE status='pendente' AND vencimento < CURDATE()")->execute();

    $stmt = $pdo->prepare("
        SELECT cp.*, l.nome AS loja_nome, u.nome AS pago_por_nome
        FROM contas_pagar cp
        JOIN lojas    l ON l.id = cp.loja_id
        LEFT JOIN usuarios u ON u.id = cp.pago_por
        {$where}
        ORDER BY cp.vencimento, cp.status
        LIMIT 300
    ");
    $stmt->execute($params);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

// ── POST /contas_pagar ────────────────────────────────────────────────────────
if ($id === null && $method === 'POST') {
    $obrig = ['loja_id','descricao','categoria','valor','vencimento'];
    foreach ($obrig as $c) if (empty($body[$c])) json_response(['status'=>'error','message'=>"Campo '$c' obrigatório.",'data'=>null],422);
    $cats = ['aluguel','agua','energia','internet','fornecedor','folha','outros'];
    if (!in_array($body['categoria'], $cats, true)) json_response(['status'=>'error','message'=>'Categoria inválida.','data'=>null],422);
    $stmt = $pdo->prepare("
        INSERT INTO contas_pagar
            (loja_id, descricao, categoria, valor, vencimento, recorrente, observacoes, criado_por)
        VALUES (:lid, :desc, :cat, :val, :venc, :rec, :obs, :por)
    ");
    $stmt->execute([
        'lid'  => (int) $body['loja_id'],
        'desc' => sanitize($body['descricao']),
        'cat'  => $body['categoria'],
        'val'  => (float) $body['valor'],
        'venc' => $body['vencimento'],
        'rec'  => !empty($body['recorrente']) ? 1 : 0,
        'obs'  => sanitize($body['observacoes'] ?? ''),
        'por'  => $u['id'],
    ]);
    $cid = (int) $pdo->lastInsertId();
    registrar_log($u['id'],'lancamento_conta','contas_pagar',$cid,['descricao'=>$body['descricao'],'valor'=>$body['valor']]);
    json_response(['status'=>'ok','message'=>'Conta lançada.','data'=>['id'=>$cid]], 201);
}

// ── PUT /contas_pagar/{id} ────────────────────────────────────────────────────
if ($id !== null && $sub === null && $method === 'PUT') {
    $campos = ['descricao','categoria','valor','vencimento','recorrente','observacoes'];
    $sets=[]; $params=['id'=>(int)$id];
    foreach ($campos as $c) if (array_key_exists($c,$body)) {
        $sets[] = "$c = :$c";
        $params[$c] = is_string($body[$c]) ? sanitize($body[$c]) : $body[$c];
    }
    if (!$sets) json_response(['status'=>'error','message'=>'Nada para atualizar.','data'=>null],422);
    $pdo->prepare("UPDATE contas_pagar SET ".implode(', ',$sets)." WHERE id = :id")->execute($params);
    json_response(['status'=>'ok','message'=>'Conta atualizada.','data'=>null]);
}

// ── PATCH /contas_pagar/{id} — marcar paga ────────────────────────────────────
if ($id !== null && $sub === null && $method === 'PATCH') {
    $pago_em = sanitize($body['pago_em'] ?? date('Y-m-d'));
    $pdo->prepare("UPDATE contas_pagar SET status='pago', pago_em=:pago, pago_por=:por WHERE id=:id")
        ->execute(['pago'=>$pago_em,'por'=>$u['id'],'id'=>(int)$id]);
    registrar_log($u['id'],'pagamento_conta','contas_pagar',(int)$id,['pago_em'=>$pago_em]);
    json_response(['status'=>'ok','message'=>'Conta marcada como paga.','data'=>null]);
}

// ── DELETE /contas_pagar/{id} ─────────────────────────────────────────────────
if ($id !== null && $method === 'DELETE') {
    $check = $pdo->prepare("SELECT status FROM contas_pagar WHERE id=:id");
    $check->execute(['id'=>(int)$id]);
    $conta = $check->fetch();
    if (!$conta) json_response(['status'=>'error','message'=>'Conta não encontrada.','data'=>null],404);
    if ($conta['status'] === 'pago') json_response(['status'=>'error','message'=>'Contas pagas não podem ser excluídas.','data'=>null],422);
    $pdo->prepare("DELETE FROM contas_pagar WHERE id=:id")->execute(['id'=>(int)$id]);
    json_response(['status'=>'ok','message'=>'Conta excluída.','data'=>null]);
}

json_response(['status'=>'error','message'=>'Método não suportado.','data'=>null],405);
