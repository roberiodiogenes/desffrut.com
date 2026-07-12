<?php
// Guard: este arquivo só pode ser incluído via api/index.php (roteador).
// Acesso direto pela URL retorna 403.
if (!function_exists('api_auth_exigir')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Acesso direto não permitido. Use /api/v1/entregadores']);
    exit;
}

/**
 * Desffrut — API v1: Entregadores
 *
 * GET /api/v1/entregadores   → lista de entregadores ativos da loja (para modal de despacho)
 */

$u = api_auth_exigir();
api_auth_role(['super_admin', 'gerente', 'caixa']);

$pdo = db();

if ($method === 'GET') {
    $where  = "WHERE u.role = 'entregador' AND u.ativo = 1";
    $params = [];

    // Gerente é responsável por todas as lojas — vê entregadores de qualquer uma
    // (mesmo comportamento já existente para 'caixa' e 'super_admin' aqui).
    $loja_filtro = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    if ($loja_filtro) {
        $where .= ' AND u.loja_id = :loja_id';
        $params['loja_id'] = $loja_filtro;
    }

    $stmt = $pdo->prepare("
        SELECT u.id, u.nome, u.telefone,
               COUNT(p.id) AS pedidos_em_rota
        FROM usuarios u
        LEFT JOIN pedidos p ON p.entregador_id = u.id AND p.status = 'saiu_para_entrega'
        {$where}
        GROUP BY u.id
        ORDER BY u.nome
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

json_response(['status' => 'error', 'message' => 'Método não suportado.', 'data' => null], 405);
