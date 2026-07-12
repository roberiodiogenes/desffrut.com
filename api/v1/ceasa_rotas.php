<?php
/**
 * Desffrut — API v1: Rotas CEASA
 *
 * GET  /api/v1/ceasa_rotas          → listar rotas recentes
 * POST /api/v1/ceasa_rotas          → criar rota do dia
 * PATCH /api/v1/ceasa_rotas/{id}    → atualizar rota
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

if ($method === 'GET' && $id === null) {
    $stmt = $pdo->query("
        SELECT cr.*,
               f.modelo AS frota_modelo, f.placa AS frota_placa,
               m.nome   AS motorista_nome,
               a1.nome  AS auxiliar1_nome,
               a2.nome  AS auxiliar2_nome
        FROM ceasa_rotas cr
        LEFT JOIN frota f ON f.id = cr.frota_id
        LEFT JOIN ceasa_colaboradores m  ON m.id  = cr.motorista_id
        LEFT JOIN ceasa_colaboradores a1 ON a1.id = cr.auxiliar1_id
        LEFT JOIN ceasa_colaboradores a2 ON a2.id = cr.auxiliar2_id
        ORDER BY cr.data_rota DESC, cr.id DESC
        LIMIT 30
    ");
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = $body['data_rota'] ?? date('Y-m-d');
    $pdo->prepare("
        INSERT INTO ceasa_rotas
            (data_rota, frota_id, motorista_id, auxiliar1_id, auxiliar2_id, rota_descricao, created_by)
        VALUES (:data, :fid, :mid, :a1, :a2, :desc, :by)
    ")->execute([
        'data' => $data,
        'fid'  => filter_var($body['frota_id']     ?? null, FILTER_VALIDATE_INT) ?: null,
        'mid'  => filter_var($body['motorista_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
        'a1'   => filter_var($body['auxiliar1_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
        'a2'   => filter_var($body['auxiliar2_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
        'desc' => sanitize($body['rota_descricao'] ?? ''),
        'by'   => $u['id'],
    ]);
    json_response(['status' => 'ok', 'id' => (int) $pdo->lastInsertId()]);
}

if ($method === 'PATCH' && $id !== null) {
    $allowed_int  = ['frota_id','motorista_id','auxiliar1_id','auxiliar2_id','houve_atraso'];
    $allowed_str  = ['rota_descricao','status','motivo_atraso','observacoes_conclusao','concluida_em'];
    $allowed      = array_merge($allowed_int, $allowed_str);

    $sets = []; $params = ['id' => $id];
    foreach ($allowed as $f) {
        if (!array_key_exists($f, $body)) continue;
        $val = in_array($f, $allowed_int)
            ? (filter_var($body[$f], FILTER_VALIDATE_INT) ?: ($f === 'houve_atraso' ? 0 : null))
            : sanitize((string) $body[$f]);
        $sets[]     = "$f = :$f";
        $params[$f] = $val;
    }
    if (!$sets) json_error('Nada para atualizar.');
    try {
        $pdo->prepare("UPDATE ceasa_rotas SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
    } catch (Throwable $e) {
        // Colunas de conclusão podem não existir antes da migration 19
        $sets_fallback = array_filter($sets, fn($s) => !preg_match('/houve_atraso|motivo_atraso|observacoes_conclusao|concluida_em/', $s));
        $params_fallback = array_filter($params, fn($k) => !in_array($k, ['houve_atraso','motivo_atraso','observacoes_conclusao','concluida_em']), ARRAY_FILTER_USE_KEY);
        if ($sets_fallback) {
            $pdo->prepare("UPDATE ceasa_rotas SET " . implode(', ', $sets_fallback) . " WHERE id = :id")->execute($params_fallback);
        }
    }
    json_response(['status' => 'ok']);
}

json_error('Método não suportado.', 405);
