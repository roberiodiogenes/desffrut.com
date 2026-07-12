<?php
/**
 * Desffrut — API v1: Financeiro Completo
 *
 * GET  /financeiro/alertas                   → contas a pagar/receber vencendo
 * GET  /financeiro/dashboard                 → KPIs consolidados do mês
 * GET  /financeiro/movimentacoes             → lista (tipo, loja, periodo)
 * POST /financeiro/movimentacoes             → lançar (retirada/despesa_extra/transferencia/custo_ceasa)
 * DELETE /financeiro/movimentacoes/{id}      → excluir
 * GET  /financeiro/auxiliares_pagamentos     → lista de pagamentos de auxiliares
 * POST /financeiro/auxiliares_pagamentos     → lançar pagamento de auxiliar
 * DELETE /financeiro/auxiliares_pagamentos/{id}
 * GET  /financeiro/contas_receber            → lista
 * POST /financeiro/contas_receber            → lançar
 * PATCH /financeiro/contas_receber/{id}      → marcar recebido
 * DELETE /financeiro/contas_receber/{id}
 * GET  /financeiro/metas                     → lista metas do mês
 * POST /financeiro/metas                     → salvar/atualizar meta
 * DELETE /financeiro/metas/{id}
 * GET  /financeiro/fluxo                     → fluxo mensal consolidado
 *
 * Roles: super_admin, rh_financeiro
 */

if (!function_exists('api_auth_exigir')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado.']); exit;
}

$u   = api_auth_exigir();
api_auth_role(['super_admin', 'rh_financeiro']);
$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$acao = $id ?? '';

// ─────────────────────────────────────────────────────────────
// GET /financeiro/alertas
// ─────────────────────────────────────────────────────────────
if ($acao === 'alertas' && $method === 'GET') {
    $dias    = max(1, min(30, (int)($_GET['dias'] ?? 7)));
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $hoje    = date('Y-m-d');
    $limite  = date('Y-m-d', strtotime("+{$dias} days"));

    // Auto-atualiza vencidos
    $pdo->exec("UPDATE contas_pagar SET status='vencido' WHERE status='pendente' AND vencimento < '{$hoje}'");
    $pdo->exec("UPDATE contas_receber SET status='vencido' WHERE status='pendente' AND data_vencimento < '{$hoje}'");

    $wl  = $loja_id ? ' AND cp.loja_id = ' . (int)$loja_id : '';
    $wlr = $loja_id ? ' AND cr.loja_id = ' . (int)$loja_id : '';

    // Contas a pagar vencidas ou vencendo
    $sp = $pdo->query("
        SELECT 'pagar' AS tipo, cp.id, cp.descricao, cp.valor, cp.vencimento AS data_ref,
               cp.status, l.nome AS loja_nome
        FROM contas_pagar cp JOIN lojas l ON l.id = cp.loja_id
        WHERE cp.status IN ('pendente','vencido') AND cp.vencimento <= '{$limite}'{$wl}
        ORDER BY cp.vencimento
    ");
    $pagar = $sp->fetchAll(PDO::FETCH_ASSOC);

    // Contas a receber vencidas ou vencendo
    $sr = $pdo->query("
        SELECT 'receber' AS tipo, cr.id, cr.descricao, cr.valor, cr.data_vencimento AS data_ref,
               cr.status, l.nome AS loja_nome
        FROM contas_receber cr JOIN lojas l ON l.id = cr.loja_id
        WHERE cr.status IN ('pendente','vencido') AND cr.data_vencimento <= '{$limite}'{$wlr}
        ORDER BY cr.data_vencimento
    ");
    $receber = $sr->fetchAll(PDO::FETCH_ASSOC);

    json_response(['status' => 'ok', 'data' => [
        'dias'    => $dias,
        'pagar'   => $pagar,
        'receber' => $receber,
        'total'   => count($pagar) + count($receber),
    ]]);
}

// ─────────────────────────────────────────────────────────────
// GET /financeiro/dashboard
// ─────────────────────────────────────────────────────────────
if ($acao === 'dashboard' && $method === 'GET') {
    $mes     = sanitize($_GET['mes'] ?? date('Y-m'));
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $wl  = $loja_id ? ' AND loja_id = ' . (int)$loja_id : '';
    $wlr = $loja_id ? ' AND loja_id = ' . (int)$loja_id : '';
    $hoje = date('Y-m-d');

    // Receita PDV do mês
    $r1 = $pdo->query("SELECT COALESCE(SUM(total_final),0) FROM vendas WHERE DATE_FORMAT(created_at,'%Y-%m')='{$mes}' AND status='finalizada'{$wl}")->fetchColumn();
    // Receita Delivery do mês
    $r2 = $pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE DATE_FORMAT(created_at,'%Y-%m')='{$mes}' AND status='entregue'{$wl}")->fetchColumn();
    // Contas a pagar do mês (total e pagas)
    $cp = $pdo->query("SELECT COALESCE(SUM(valor),0) AS total, COALESCE(SUM(CASE WHEN status='pago' THEN valor END),0) AS pago FROM contas_pagar WHERE DATE_FORMAT(vencimento,'%Y-%m')='{$mes}'{$wl}")->fetch(PDO::FETCH_ASSOC);
    // Contas vencendo hoje + próximos 3 dias
    $cv = (int)$pdo->query("SELECT COUNT(*) FROM contas_pagar WHERE status IN ('pendente','vencido') AND vencimento <= DATE_ADD('{$hoje}',INTERVAL 3 DAY){$wl}")->fetchColumn();
    // Contas a receber pendentes
    $cr = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM contas_receber WHERE status IN ('pendente','vencido') AND DATE_FORMAT(data_vencimento,'%Y-%m')='{$mes}'{$wlr}")->fetchColumn();
    // Retiradas do mês
    $ret = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM financeiro_movimentacoes WHERE tipo='retirada' AND DATE_FORMAT(data,'%Y-%m')='{$mes}'{$wl}")->fetchColumn();
    // Despesas extras do mês
    $dex = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM financeiro_movimentacoes WHERE tipo='despesa_extra' AND DATE_FORMAT(data,'%Y-%m')='{$mes}'{$wl}")->fetchColumn();
    // Pagamentos auxiliares do mês
    $aux = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM auxiliares_pagamentos WHERE DATE_FORMAT(data_pagamento,'%Y-%m')='{$mes}'")->fetchColumn();

    $receita = (float)$r1 + (float)$r2;
    $despesas_total = (float)$cp['total'] + $ret + $dex + $aux;

    json_response(['status' => 'ok', 'data' => [
        'mes'              => $mes,
        'receita_pdv'      => (float)$r1,
        'receita_delivery' => (float)$r2,
        'receita_total'    => $receita,
        'contas_pagar_total'   => (float)$cp['total'],
        'contas_pagar_pagas'   => (float)$cp['pago'],
        'contas_pagar_pendente'=> (float)$cp['total'] - (float)$cp['pago'],
        'alertas_proximos' => $cv,
        'contas_receber_pendente' => (float)$cr,
        'retiradas_mes'    => $ret,
        'despesas_extras'  => $dex,
        'pagamentos_aux'   => $aux,
        'despesas_total'   => $despesas_total,
        'resultado'        => $receita - $despesas_total,
    ]]);
}

// ─────────────────────────────────────────────────────────────
// GET/POST /financeiro/movimentacoes
// ─────────────────────────────────────────────────────────────
if ($acao === 'movimentacoes' && $method === 'GET') {
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $tipo    = sanitize($_GET['tipo'] ?? '');
    $mes     = sanitize($_GET['mes']  ?? '');
    $where   = 'WHERE 1=1'; $params = [];
    if ($loja_id) { $where .= ' AND fm.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }
    if ($tipo && in_array($tipo, ['retirada','despesa_extra','transferencia','custo_ceasa'], true)) {
        $where .= ' AND fm.tipo = :tipo'; $params['tipo'] = $tipo;
    }
    if ($mes) { $where .= " AND DATE_FORMAT(fm.data,'%Y-%m') = :mes"; $params['mes'] = $mes; }

    $stmt = $pdo->prepare("
        SELECT fm.*, l.nome AS loja_nome,
               ld.nome AS loja_destino_nome,
               u.nome AS criado_por_nome
        FROM financeiro_movimentacoes fm
        JOIN lojas l    ON l.id  = fm.loja_id
        LEFT JOIN lojas ld ON ld.id = fm.loja_destino_id
        LEFT JOIN usuarios u ON u.id = fm.criado_por
        {$where}
        ORDER BY fm.data DESC, fm.id DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

if ($acao === 'movimentacoes' && $method === 'POST') {
    $tipos_validos   = ['retirada','despesa_extra','transferencia','custo_ceasa'];
    $subtipos_validos = ['pro_labore','investimento','transferencia_pessoal','limpeza','manutencao',
                         'terceirizado','combustivel','pedagio','outros'];
    foreach (['loja_id','tipo','descricao','valor','data'] as $c) {
        if (empty($body[$c])) json_response(['status'=>'error','message'=>"Campo '{$c}' obrigatório.",'data'=>null], 422);
    }
    if (!in_array($body['tipo'], $tipos_validos, true)) json_response(['status'=>'error','message'=>'Tipo inválido.','data'=>null],422);

    $stmt = $pdo->prepare("
        INSERT INTO financeiro_movimentacoes
            (loja_id, tipo, subtipo, descricao, valor, data, loja_destino_id, conta_bancaria, referencia_id, observacoes, criado_por)
        VALUES (:lid, :tipo, :sub, :desc, :val, :data, :ldest, :banco, :ref, :obs, :por)
    ");
    $stmt->execute([
        'lid'   => (int)$body['loja_id'],
        'tipo'  => $body['tipo'],
        'sub'   => sanitize($body['subtipo'] ?? ''),
        'desc'  => sanitize($body['descricao']),
        'val'   => (float)$body['valor'],
        'data'  => $body['data'],
        'ldest' => !empty($body['loja_destino_id']) ? (int)$body['loja_destino_id'] : null,
        'banco' => sanitize($body['conta_bancaria'] ?? ''),
        'ref'   => !empty($body['referencia_id']) ? (int)$body['referencia_id'] : null,
        'obs'   => sanitize($body['observacoes'] ?? ''),
        'por'   => $u['id'],
    ]);
    $newId = (int)$pdo->lastInsertId();
    registrar_log($u['id'], 'lancamento_movimentacao', 'financeiro_movimentacoes', $newId, [
        'tipo' => $body['tipo'], 'valor' => $body['valor']
    ]);
    json_response(['status'=>'ok','message'=>'Movimentação lançada.','data'=>['id'=>$newId]], 201);
}

if ($acao === 'movimentacoes' && $method === 'DELETE' && $id !== null) {
    // $id aqui é 'movimentacoes', precisamos pegar o sub
}
// DELETE /financeiro/movimentacoes — com ?id= ou via sub-rota
if ($sub !== null && $acao === 'movimentacoes' && $method === 'DELETE') {
    $pdo->prepare("DELETE FROM financeiro_movimentacoes WHERE id = :id")->execute(['id' => (int)$sub]);
    json_response(['status'=>'ok','message'=>'Excluído.','data'=>null]);
}

// ─────────────────────────────────────────────────────────────
// Auxiliares pagamentos
// ─────────────────────────────────────────────────────────────
if ($acao === 'auxiliares_pagamentos' && $method === 'GET') {
    $mes = sanitize($_GET['mes'] ?? date('Y-m'));
    $stmt = $pdo->prepare("
        SELECT ap.*, cc.nome AS colaborador_nome, cc.loja_id,
               l.nome AS loja_nome, u.nome AS criado_por_nome
        FROM auxiliares_pagamentos ap
        JOIN ceasa_colaboradores cc ON cc.id = ap.colaborador_id
        JOIN lojas l  ON l.id  = cc.loja_id
        LEFT JOIN usuarios u ON u.id = ap.criado_por
        WHERE DATE_FORMAT(ap.data_pagamento,'%Y-%m') = :mes
        ORDER BY ap.data_pagamento DESC
    ");
    $stmt->execute(['mes' => $mes]);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

if ($acao === 'auxiliares_pagamentos' && $method === 'POST') {
    foreach (['colaborador_id','valor','periodo_ini','periodo_fim','tipo','data_pagamento'] as $c) {
        if (empty($body[$c])) json_response(['status'=>'error','message'=>"Campo '{$c}' obrigatório.",'data'=>null], 422);
    }
    $stmt = $pdo->prepare("
        INSERT INTO auxiliares_pagamentos
            (colaborador_id, valor, periodo_ini, periodo_fim, tipo, forma, data_pagamento, observacoes, criado_por)
        VALUES (:col, :val, :pi, :pf, :tipo, :forma, :dpag, :obs, :por)
    ");
    $stmt->execute([
        'col'  => (int)$body['colaborador_id'],
        'val'  => (float)$body['valor'],
        'pi'   => $body['periodo_ini'],
        'pf'   => $body['periodo_fim'],
        'tipo' => $body['tipo'],
        'forma'=> $body['forma'] ?? 'dinheiro',
        'dpag' => $body['data_pagamento'],
        'obs'  => sanitize($body['observacoes'] ?? ''),
        'por'  => $u['id'],
    ]);
    $newId = (int)$pdo->lastInsertId();
    json_response(['status'=>'ok','message'=>'Pagamento lançado.','data'=>['id'=>$newId]], 201);
}

if ($acao === 'auxiliares_pagamentos' && $method === 'DELETE' && $sub !== null) {
    $pdo->prepare("DELETE FROM auxiliares_pagamentos WHERE id = :id")->execute(['id' => (int)$sub]);
    json_response(['status'=>'ok','message'=>'Excluído.','data'=>null]);
}

// ─────────────────────────────────────────────────────────────
// Contas a receber
// ─────────────────────────────────────────────────────────────
if ($acao === 'contas_receber' && $method === 'GET') {
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $status  = sanitize($_GET['status'] ?? '');
    $mes     = sanitize($_GET['mes'] ?? date('Y-m'));
    $where   = "WHERE DATE_FORMAT(cr.data_vencimento,'%Y-%m') = :mes"; $params = ['mes' => $mes];
    if ($loja_id) { $where .= ' AND cr.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }
    if ($status)  { $where .= ' AND cr.status = :status';   $params['status']  = $status; }

    // Auto-atualiza vencidos
    $pdo->exec("UPDATE contas_receber SET status='vencido' WHERE status='pendente' AND data_vencimento < '" . date('Y-m-d') . "'");

    $stmt = $pdo->prepare("
        SELECT cr.*, l.nome AS loja_nome, u.nome AS criado_por_nome
        FROM contas_receber cr
        JOIN lojas l ON l.id = cr.loja_id
        LEFT JOIN usuarios u ON u.id = cr.criado_por
        {$where}
        ORDER BY cr.data_vencimento, cr.status
    ");
    $stmt->execute($params);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

if ($acao === 'contas_receber' && $method === 'POST') {
    foreach (['loja_id','descricao','valor','data_vencimento','categoria'] as $c) {
        if (empty($body[$c])) json_response(['status'=>'error','message'=>"Campo '{$c}' obrigatório.",'data'=>null], 422);
    }
    $stmt = $pdo->prepare("
        INSERT INTO contas_receber
            (loja_id, descricao, valor, data_vencimento, categoria, cliente_nome, observacoes, criado_por)
        VALUES (:lid, :desc, :val, :venc, :cat, :cli, :obs, :por)
    ");
    $stmt->execute([
        'lid'  => (int)$body['loja_id'],
        'desc' => sanitize($body['descricao']),
        'val'  => (float)$body['valor'],
        'venc' => $body['data_vencimento'],
        'cat'  => $body['categoria'],
        'cli'  => sanitize($body['cliente_nome'] ?? ''),
        'obs'  => sanitize($body['observacoes'] ?? ''),
        'por'  => $u['id'],
    ]);
    $newId = (int)$pdo->lastInsertId();
    registrar_log($u['id'], 'lancamento_receber', 'contas_receber', $newId, [
        'descricao' => $body['descricao'], 'valor' => $body['valor']
    ]);
    json_response(['status'=>'ok','message'=>'Conta a receber lançada.','data'=>['id'=>$newId]], 201);
}

if ($acao === 'contas_receber' && $method === 'PATCH' && $sub !== null) {
    $data_rec = sanitize($body['data_recebimento'] ?? date('Y-m-d'));
    $pdo->prepare("UPDATE contas_receber SET status='recebido', data_recebimento=:dr, recebido_por=:por WHERE id=:id")
        ->execute(['dr' => $data_rec, 'por' => $u['id'], 'id' => (int)$sub]);
    registrar_log($u['id'], 'baixa_receber', 'contas_receber', (int)$sub, ['data_recebimento' => $data_rec]);
    json_response(['status'=>'ok','message'=>'Conta marcada como recebida.','data'=>null]);
}

if ($acao === 'contas_receber' && $method === 'DELETE' && $sub !== null) {
    $rec = $pdo->query("SELECT status FROM contas_receber WHERE id=" . (int)$sub)->fetch();
    if (!$rec) json_response(['status'=>'error','message'=>'Não encontrado.','data'=>null], 404);
    if ($rec['status'] === 'recebido') json_response(['status'=>'error','message'=>'Contas recebidas não podem ser excluídas.','data'=>null], 422);
    $pdo->prepare("DELETE FROM contas_receber WHERE id = :id")->execute(['id' => (int)$sub]);
    json_response(['status'=>'ok','message'=>'Excluído.','data'=>null]);
}

// ─────────────────────────────────────────────────────────────
// Metas
// ─────────────────────────────────────────────────────────────
if ($acao === 'metas' && $method === 'GET') {
    $mes     = sanitize($_GET['mes'] ?? date('Y-m'));
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $where   = 'WHERE mes_ref = :mes'; $params = ['mes' => $mes];
    if ($loja_id !== null) {
        $where .= ' AND (loja_id = :loja_id OR loja_id IS NULL)'; $params['loja_id'] = $loja_id;
    }
    $stmt = $pdo->prepare("SELECT m.*, l.nome AS loja_nome FROM fin_metas m LEFT JOIN lojas l ON l.id = m.loja_id {$where} ORDER BY tipo, categoria");
    $stmt->execute($params);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

if ($acao === 'metas' && $method === 'POST') {
    foreach (['mes_ref','tipo','valor_meta'] as $c) {
        if (empty($body[$c])) json_response(['status'=>'error','message'=>"Campo '{$c}' obrigatório.",'data'=>null], 422);
    }
    $stmt = $pdo->prepare("
        INSERT INTO fin_metas (loja_id, mes_ref, tipo, categoria, valor_meta)
        VALUES (:lid, :mes, :tipo, :cat, :val)
        ON DUPLICATE KEY UPDATE valor_meta = VALUES(valor_meta)
    ");
    $stmt->execute([
        'lid'  => !empty($body['loja_id']) ? (int)$body['loja_id'] : null,
        'mes'  => $body['mes_ref'],
        'tipo' => $body['tipo'],
        'cat'  => !empty($body['categoria']) ? sanitize($body['categoria']) : null,
        'val'  => (float)$body['valor_meta'],
    ]);
    json_response(['status'=>'ok','message'=>'Meta salva.','data'=>null]);
}

if ($acao === 'metas' && $method === 'DELETE' && $sub !== null) {
    $pdo->prepare("DELETE FROM fin_metas WHERE id = :id")->execute(['id' => (int)$sub]);
    json_response(['status'=>'ok','message'=>'Meta removida.','data'=>null]);
}

// ─────────────────────────────────────────────────────────────
// Fluxo mensal consolidado
// ─────────────────────────────────────────────────────────────
if ($acao === 'fluxo' && $method === 'GET') {
    $ano     = (int)($_GET['ano'] ?? date('Y'));
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $wl  = $loja_id ? ' AND loja_id = ' . (int)$loja_id : '';

    // Receitas por mês
    $rvArr = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') m, SUM(total_final) v FROM vendas WHERE YEAR(created_at)={$ano} AND status='finalizada'{$wl} GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);
    $rpArr = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') m, SUM(total) v FROM pedidos WHERE YEAR(created_at)={$ano} AND status='entregue'{$wl} GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Despesas contas_pagar
    $dcArr = $pdo->query("SELECT DATE_FORMAT(vencimento,'%Y-%m') m, SUM(valor) v FROM contas_pagar WHERE YEAR(vencimento)={$ano}{$wl} GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Movimentações (retiradas + despesas_extras + transferencias)
    $dmArr = $pdo->query("SELECT DATE_FORMAT(data,'%Y-%m') m, SUM(valor) v FROM financeiro_movimentacoes WHERE YEAR(data)={$ano} AND tipo IN ('retirada','despesa_extra'){$wl} GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Pagamentos auxiliares
    $daArr = $pdo->query("SELECT DATE_FORMAT(ap.data_pagamento,'%Y-%m') m, SUM(ap.valor) v FROM auxiliares_pagamentos ap JOIN ceasa_colaboradores cc ON cc.id=ap.colaborador_id WHERE YEAR(ap.data_pagamento)={$ano}" . ($loja_id ? ' AND cc.loja_id='.(int)$loja_id : '') . " GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Meses com movimento
    $allMeses = array_unique(array_merge(array_keys($rvArr), array_keys($rpArr), array_keys($dcArr), array_keys($dmArr), array_keys($daArr)));
    sort($allMeses);

    $result = [];
    $totRec = $totDesp = $totRes = 0;
    foreach ($allMeses as $m) {
        $rec  = (float)($rvArr[$m] ?? 0) + (float)($rpArr[$m] ?? 0);
        $desp = (float)($dcArr[$m] ?? 0) + (float)($dmArr[$m] ?? 0) + (float)($daArr[$m] ?? 0);
        $res  = $rec - $desp;
        $totRec += $rec; $totDesp += $desp; $totRes += $res;
        $result[] = ['mes' => $m, 'receita' => $rec, 'despesas' => $desp, 'resultado' => $res,
                     'pdv' => (float)($rvArr[$m]??0), 'delivery' => (float)($rpArr[$m]??0),
                     'contas_pagar' => (float)($dcArr[$m]??0), 'movimentacoes' => (float)($dmArr[$m]??0),
                     'auxiliares'   => (float)($daArr[$m]??0)];
    }
    json_response(['status'=>'ok','data'=>[
        'meses'  => $result,
        'totais' => ['receita' => $totRec, 'despesas' => $totDesp, 'resultado' => $totRes],
    ]]);
}

json_response(['status'=>'error','message'=>'Endpoint não encontrado.','data'=>null], 404);
