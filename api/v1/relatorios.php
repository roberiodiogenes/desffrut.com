<?php
/**
 * Desffrut — API v1: Relatórios
 *
 * GET /api/v1/relatorios/top_produtos
 *     ?data_ini=YYYY-MM-DD &data_fim=YYYY-MM-DD &loja_id=X &limit=10
 *     → Top produtos por faturamento no período
 *
 * GET /api/v1/relatorios/vendas_periodo
 *     ?data_ini=YYYY-MM-DD &data_fim=YYYY-MM-DD &loja_id=X
 *     → Faturamento agregado: total PDV, delivery, ticket médio, por dia
 *
 * Roles: super_admin, gerente, rh_financeiro
 */

if (!function_exists('api_auth_exigir')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado.']);
    exit;
}

$u   = api_auth_exigir();
$pdo = db();

api_auth_role(['super_admin', 'gerente', 'rh_financeiro']);

$acao = $id ?? '';

// ── GET /relatorios/top_produtos ──────────────────────────────────────────────
if ($acao === 'top_produtos' && $method === 'GET') {
    $data_ini = sanitize($_GET['data_ini'] ?? date('Y-m-01'));
    $data_fim = sanitize($_GET['data_fim'] ?? date('Y-m-d'));
    $loja_id  = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $limit    = max(5, min(50, (int) ($_GET['limit'] ?? 10)));

    // Gerente é responsável por todas as lojas — mesma visão multi-loja do super_admin.

    $where  = "WHERE DATE(v.created_at) BETWEEN :di AND :df AND v.status = 'finalizada'";
    $params = ['di' => $data_ini, 'df' => $data_fim];
    if ($loja_id) { $where .= ' AND v.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.nome,
            p.unidade_medida,
            p.categoria,
            SUM(iv.quantidade)   AS total_qtd,
            SUM(iv.subtotal)     AS total_receita,
            COUNT(DISTINCT v.id) AS qtd_vendas
        FROM itens_venda iv
        JOIN vendas   v ON v.id  = iv.venda_id
        JOIN produtos p ON p.id  = iv.produto_id
        {$where}
        GROUP BY iv.produto_id, p.id, p.nome, p.unidade_medida, p.categoria
        ORDER BY total_receita DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

// ── GET /relatorios/vendas_periodo ─────────────────────────────────────────────
if ($acao === 'vendas_periodo' && $method === 'GET') {
    $data_ini = sanitize($_GET['data_ini'] ?? date('Y-m-01'));
    $data_fim = sanitize($_GET['data_fim'] ?? date('Y-m-d'));
    $loja_id  = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;

    // Gerente é responsável por todas as lojas — mesma visão multi-loja do super_admin.

    $params_v = ['di' => $data_ini, 'df' => $data_fim];
    $params_p = ['di' => $data_ini, 'df' => $data_fim];
    $wv = "WHERE DATE(v.created_at) BETWEEN :di AND :df AND v.status = 'finalizada'";
    $wp = "WHERE DATE(p.created_at) BETWEEN :di AND :df AND p.status = 'entregue'";
    if ($loja_id) {
        $wv .= ' AND v.loja_id = :loja_id';
        $wp .= ' AND p.loja_id = :loja_id';
        $params_v['loja_id'] = $loja_id;
        $params_p['loja_id'] = $loja_id;
    }

    // Totais PDV
    $sv = $pdo->prepare("
        SELECT
            COALESCE(SUM(total_final), 0) AS receita_pdv,
            COUNT(*)                       AS qtd_vendas,
            COALESCE(AVG(total_final), 0)  AS ticket_medio
        FROM vendas v {$wv}
    ");
    $sv->execute($params_v);
    $res_pdv = $sv->fetch(PDO::FETCH_ASSOC);

    // Totais Delivery
    $sp = $pdo->prepare("
        SELECT
            COALESCE(SUM(total), 0) AS receita_delivery,
            COUNT(*)                AS qtd_pedidos
        FROM pedidos p {$wp}
    ");
    $sp->execute($params_p);
    $res_del = $sp->fetch(PDO::FETCH_ASSOC);

    // Vendas por dia (PDV)
    $sd = $pdo->prepare("
        SELECT
            DATE(v.created_at)            AS data,
            COALESCE(SUM(v.total_final),0) AS fat_pdv,
            COUNT(v.id)                    AS qtd_pdv,
            COALESCE(SUM(ped.total_del),0) AS fat_del,
            COALESCE(SUM(ped.qtd_del),0)   AS qtd_del
        FROM vendas v
        LEFT JOIN (
            SELECT DATE(created_at) AS d,
                   SUM(total) AS total_del, COUNT(*) AS qtd_del
            FROM pedidos
            WHERE DATE(created_at) BETWEEN :di2 AND :df2 AND status = 'entregue'
            " . ($loja_id ? 'AND loja_id = :loja_id2' : '') . "
            GROUP BY DATE(created_at)
        ) ped ON ped.d = DATE(v.created_at)
        {$wv}
        GROUP BY DATE(v.created_at)
        ORDER BY data ASC
    ");
    $params_day = array_merge($params_v, ['di2' => $data_ini, 'df2' => $data_fim]);
    if ($loja_id) $params_day['loja_id2'] = $loja_id;
    $sd->execute($params_day);
    $por_dia = $sd->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'status' => 'ok',
        'data'   => [
            'totais'   => [
                'receita_pdv'      => (float) $res_pdv['receita_pdv'],
                'qtd_vendas'       => (int)   $res_pdv['qtd_vendas'],
                'ticket_medio'     => (float) $res_pdv['ticket_medio'],
                'receita_delivery' => (float) $res_del['receita_delivery'],
                'qtd_pedidos'      => (int)   $res_del['qtd_pedidos'],
                'receita_total'    => (float) $res_pdv['receita_pdv'] + (float) $res_del['receita_delivery'],
            ],
            'por_dia'  => $por_dia,
        ],
    ]);
}

json_response(['status' => 'error', 'message' => 'Endpoint não encontrado.', 'data' => null], 404);
