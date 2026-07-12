<?php
// Guard
if (!function_exists('api_auth_exigir')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Acesso direto não permitido.']); exit;
}
require_once __DIR__ . '/../../app/middleware/modo_restrito.php'; // Categoria 22
/**
 * Desffrut — API v1: BI / Lucro Líquido / DRE
 *
 * GET /api/v1/bi/faturamento?ano=YYYY&loja_id=X  → vendas + pedidos por mês
 * GET /api/v1/bi/despesas?ano=YYYY&loja_id=X     → contas_pagar por mês/categoria
 * GET /api/v1/bi/dre?ano=YYYY&loja_id=X          → DRE consolidado
 * GET /api/v1/bi/top_produtos?mes=&loja_id=X     → top 10 produtos do mês
 * GET /api/v1/bi/overview                         → KPIs rápidos do mês atual
 */

$u  = api_auth_exigir();
api_auth_role(['super_admin', 'rh_financeiro']);
$pdo = db();

$acao = $id ?? '';

// ── GET /bi/faturamento ───────────────────────────────────────────────────────
if ($acao === 'faturamento' && $method === 'GET') {
    $ano     = (int) ($_GET['ano'] ?? date('Y'));
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $params  = ['ano' => $ano];

    $w_v = 'WHERE YEAR(v.created_at) = :ano AND v.status = \'finalizada\'';
    $w_p = 'WHERE YEAR(p.created_at) = :ano AND p.status = \'entregue\'';
    if ($loja_id) {
        $w_v .= ' AND v.loja_id = :loja_id';
        $w_p .= ' AND p.loja_id = :loja_id';
        $params['loja_id'] = $loja_id;
    }

    // Vendas PDV por mês
    $sv = $pdo->prepare("
        SELECT DATE_FORMAT(v.created_at,'%Y-%m') AS mes,
               SUM(v.total_final) AS receita_pdv,
               COUNT(*) AS qtd_vendas
        FROM vendas v {$w_v}
        GROUP BY mes ORDER BY mes
    ");
    $sv->execute($params);
    $vendas = $sv->fetchAll(PDO::FETCH_ASSOC);
    $mapa_v = array_column($vendas, null, 'mes');

    // Pedidos delivery por mês
    $sp = $pdo->prepare("
        SELECT DATE_FORMAT(p.created_at,'%Y-%m') AS mes,
               SUM(p.total) AS receita_delivery,
               COUNT(*) AS qtd_pedidos
        FROM pedidos p {$w_p}
        GROUP BY mes ORDER BY mes
    ");
    $sp->execute($params);
    $pedidos = $sp->fetchAll(PDO::FETCH_ASSOC);
    $mapa_p = array_column($pedidos, null, 'mes');

    // Mescla por mês
    $meses = array_unique(array_merge(array_keys($mapa_v), array_keys($mapa_p)));
    sort($meses);
    $result = [];
    foreach ($meses as $m) {
        $rv = (float)($mapa_v[$m]['receita_pdv'] ?? 0);
        $rp = (float)($mapa_p[$m]['receita_delivery'] ?? 0);
        $result[] = [
            'mes'              => $m,
            'receita_pdv'      => $rv,
            'receita_delivery' => $rp,
            'receita_total'    => $rv + $rp,
            'qtd_vendas'       => (int)($mapa_v[$m]['qtd_vendas'] ?? 0),
            'qtd_pedidos'      => (int)($mapa_p[$m]['qtd_pedidos'] ?? 0),
        ];
    }
    json_response(['status'=>'ok','data'=>$result]);
}

// ── GET /bi/despesas ──────────────────────────────────────────────────────────
if ($acao === 'despesas' && $method === 'GET') {
    $ano     = (int) ($_GET['ano'] ?? date('Y'));
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $params  = ['ano' => $ano];
    $where   = 'WHERE YEAR(vencimento) = :ano';
    if ($loja_id) { $where .= ' AND loja_id = :loja_id'; $params['loja_id'] = $loja_id; }

    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(vencimento,'%Y-%m') AS mes,
               categoria,
               SUM(valor) AS total
        FROM contas_pagar {$where}
        GROUP BY mes, categoria
        ORDER BY mes, categoria
    ");
    $stmt->execute($params);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

// ── GET /bi/dre ───────────────────────────────────────────────────────────────
if ($acao === 'dre' && $method === 'GET') {
    $ano     = (int) ($_GET['ano'] ?? date('Y'));
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $params  = ['ano' => $ano];
    $pLoja   = $loja_id ? ' AND loja_id = :loja_id' : '';
    if ($loja_id) $params['loja_id'] = $loja_id;

    // Receita PDV
    $sv = $pdo->prepare("SELECT COALESCE(SUM(total_final),0) FROM vendas WHERE YEAR(created_at)=:ano AND status='finalizada'{$pLoja}");
    $sv->execute($params); $rec_pdv = (float)$sv->fetchColumn();

    // Receita Delivery
    $sp = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE YEAR(created_at)=:ano AND status='entregue'{$pLoja}");
    $sp->execute($params); $rec_del = (float)$sp->fetchColumn();

    $receita_bruta = $rec_pdv + $rec_del;

    // Custo produtos (quebras + custo_snapshot das vendas)
    $sc = $pdo->prepare("
        SELECT COALESCE(SUM(iv.quantidade * p.preco_custo), 0)
        FROM itens_venda iv
        JOIN vendas ve ON ve.id = iv.venda_id
        JOIN produtos p ON p.id = iv.produto_id
        WHERE YEAR(ve.created_at) = :ano AND ve.status = 'finalizada'
        " . ($loja_id ? ' AND ve.loja_id = :loja_id' : ''));
    $sc->execute($params); $cme = (float)$sc->fetchColumn();

    $lucro_bruto = $receita_bruta - $cme;

    // Despesas operacionais por categoria
    $sd = $pdo->prepare("
        SELECT categoria, COALESCE(SUM(valor),0) AS total
        FROM contas_pagar
        WHERE YEAR(vencimento)=:ano" . ($loja_id ? ' AND loja_id=:loja_id' : '') . "
        GROUP BY categoria
    ");
    $sd->execute($params);
    $desp_map = []; $desp_total = 0;
    foreach ($sd->fetchAll() as $row) {
        $desp_map[$row['categoria']] = (float)$row['total'];
        $desp_total += (float)$row['total'];
    }

    $ebitda      = $lucro_bruto - $desp_total;
    // Estimativa: depreciação = 0 (pequeno varejo), IR simples = 6% sobre lucro positivo
    $ir_estimado = $ebitda > 0 ? round($ebitda * 0.06, 2) : 0;
    $lucro_liq   = $ebitda - $ir_estimado;

    // Folha de pagamento realizada no ano
    $sf2 = $pdo->prepare("
        SELECT COALESCE(SUM(total_liquido),0)
        FROM folha_pagamento fp
        JOIN funcionarios f ON f.id = fp.funcionario_id
        WHERE LEFT(fp.mes_referencia,4) = :ano
        " . ($loja_id ? ' AND f.loja_id = :loja_id' : ''));
    $sf2->execute($params); $folha = (float)$sf2->fetchColumn();

    json_response(['status'=>'ok','data'=>[
        'ano'             => $ano,
        'receita_pdv'     => $rec_pdv,
        'receita_delivery'=> $rec_del,
        'receita_bruta'   => $receita_bruta,
        'cme'             => $cme,
        'lucro_bruto'     => $lucro_bruto,
        'despesas'        => $desp_map,
        'despesas_total'  => $desp_total,
        'folha_realizada' => $folha,
        'ebitda'          => $ebitda,
        'ir_estimado'     => $ir_estimado,
        'lucro_liquido'   => $lucro_liq,
        'margem_pct'      => $receita_bruta > 0 ? round(($lucro_liq / $receita_bruta) * 100, 2) : 0,
    ]]);
}

// ── GET /bi/top_produtos ──────────────────────────────────────────────────────
if ($acao === 'top_produtos' && $method === 'GET') {
    $mes     = sanitize($_GET['mes'] ?? date('Y-m'));
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $params  = ['mes' => $mes];
    $wv      = "WHERE DATE_FORMAT(v.created_at,'%Y-%m') = :mes AND v.status='finalizada'";
    if ($loja_id) { $wv .= ' AND v.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }
    $stmt = $pdo->prepare("
        SELECT p.nome, p.unidade_medida,
               SUM(iv.quantidade) AS total_qtd,
               SUM(iv.subtotal)   AS total_receita
        FROM itens_venda iv
        JOIN vendas v   ON v.id  = iv.venda_id
        JOIN produtos p ON p.id  = iv.produto_id
        {$wv}
        GROUP BY iv.produto_id
        ORDER BY total_receita DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

// ── GET /bi/overview ─────────────────────────────────────────────────────────
if ($acao === 'overview' && $method === 'GET') {
    $mes     = date('Y-m');
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $params  = ['mes' => $mes];
    $w_l     = $loja_id ? ' AND loja_id = :loja_id' : '';
    if ($loja_id) $params['loja_id'] = $loja_id;

    $sv = $pdo->prepare("SELECT COALESCE(SUM(total_final),0), COUNT(*) FROM vendas WHERE DATE_FORMAT(created_at,'%Y-%m')=:mes AND status='finalizada'{$w_l}");
    $sv->execute($params); [$fat_pdv, $qtd_v] = $sv->fetch(PDO::FETCH_NUM);

    $sp = $pdo->prepare("SELECT COALESCE(SUM(total),0), COUNT(*) FROM pedidos WHERE DATE_FORMAT(created_at,'%Y-%m')=:mes AND status='entregue'{$w_l}");
    $sp->execute($params); [$fat_del, $qtd_p] = $sp->fetch(PDO::FETCH_NUM);

    $sd = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE DATE_FORMAT(vencimento,'%Y-%m')=:mes{$w_l}");
    $sd->execute($params); $desp = (float)$sd->fetchColumn();

    $sc = $pdo->prepare("SELECT COUNT(*) FROM contas_pagar WHERE DATE_FORMAT(vencimento,'%Y-%m')=:mes AND status='pendente'{$w_l}");
    $sc->execute($params); $contas_pendentes = (int)$sc->fetchColumn();

    $sf = $pdo->prepare("SELECT COUNT(*) FROM funcionarios WHERE ativo=1" . ($loja_id ? ' AND loja_id=:loja_id' : ''));
    $sf->execute($loja_id ? ['loja_id'=>$loja_id] : []); $total_func = (int)$sf->fetchColumn();

    $receita_total = (float)$fat_pdv + (float)$fat_del;
    json_response(['status'=>'ok','data'=>[
        'mes'              => $mes,
        'receita_total'    => $receita_total,
        'receita_pdv'      => (float)$fat_pdv,
        'receita_delivery' => (float)$fat_del,
        'qtd_vendas'       => (int)$qtd_v,
        'qtd_pedidos'      => (int)$qtd_p,
        'despesas_mes'     => $desp,
        'resultado_mes'    => $receita_total - $desp,
        'contas_pendentes' => $contas_pendentes,
        'funcionarios_ativos' => $total_func,
    ]]);
}

json_response(['status'=>'error','message'=>'Endpoint BI não encontrado.','data'=>null],404);
