<?php
// Guard
if (!function_exists('api_auth_exigir')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Acesso direto não permitido.']); exit;
}
require_once __DIR__ . '/../../app/middleware/modo_restrito.php'; // Categoria 22
/**
 * Desffrut — API v1: Auditoria
 *
 * GET /api/v1/auditoria                       → lista logs (paginado)
 * GET /api/v1/auditoria?acao=X&usuario_id=Y   → filtros
 * GET /api/v1/auditoria/acoes                 → lista de ações distintas
 */

$u  = api_auth_exigir();
api_auth_role(['super_admin']); // somente super_admin
$pdo = db();

// ── GET /auditoria/acoes ──────────────────────────────────────────────────────
if ($id === 'acoes' && $method === 'GET') {
    $stmt = $pdo->query("SELECT DISTINCT acao FROM logs_auditoria ORDER BY acao");
    json_response(['status'=>'ok','data'=>$stmt->fetchAll(PDO::FETCH_COLUMN)]);
}

// ── GET /auditoria ────────────────────────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $acao       = sanitize($_GET['acao']        ?? '');
    $uid        = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT) ?: null;
    $tabela     = sanitize($_GET['tabela']       ?? '');
    $data_ini   = sanitize($_GET['data_ini']     ?? '');
    $data_fim   = sanitize($_GET['data_fim']     ?? '');
    $pagina     = max(1, (int)($_GET['pagina']   ?? 1));
    $por_pagina = min(100, (int)($_GET['por_pagina'] ?? 50));
    $offset     = ($pagina - 1) * $por_pagina;

    $where  = 'WHERE 1=1'; $params = [];
    if ($acao)     { $where .= ' AND la.acao = :acao';                 $params['acao']    = $acao; }
    if ($uid)      { $where .= ' AND la.usuario_id = :uid';            $params['uid']     = $uid; }
    if ($tabela)   { $where .= ' AND la.tabela_afetada = :tabela';     $params['tabela']  = $tabela; }
    if ($data_ini) { $where .= ' AND DATE(la.created_at) >= :d_ini';   $params['d_ini']   = $data_ini; }
    if ($data_fim) { $where .= ' AND DATE(la.created_at) <= :d_fim';   $params['d_fim']   = $data_fim; }

    // Total
    $ct = $pdo->prepare("SELECT COUNT(*) FROM logs_auditoria la {$where}");
    $ct->execute($params); $total = (int)$ct->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT la.id, la.acao, la.tabela_afetada, la.registro_id,
               la.detalhes_json, la.ip, la.created_at,
               u.nome AS usuario_nome, u.role AS usuario_role
        FROM logs_auditoria la
        LEFT JOIN usuarios u ON u.id = la.usuario_id
        {$where}
        ORDER BY la.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $params['limit']  = $por_pagina;
    $params['offset'] = $offset;
    // PDO não aceita :limit/:offset como named param com bindValue normal em alguns drivers
    foreach ($params as $k => &$v) $stmt->bindValue(":$k", $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    json_response(['status'=>'ok','data'=>[
        'logs'       => $rows,
        'total'      => $total,
        'pagina'     => $pagina,
        'por_pagina' => $por_pagina,
        'paginas'    => (int) ceil($total / $por_pagina),
    ]]);
}

json_response(['status'=>'error','message'=>'Método não suportado.','data'=>null],405);
