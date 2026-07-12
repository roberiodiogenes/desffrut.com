<?php
/**
 * Desffrut — API v1: Frota (veículos do CEASA)
 *
 * GET    /api/v1/frota        → listar veículos
 * POST   /api/v1/frota        → cadastrar veículo
 * PATCH  /api/v1/frota/{id}   → atualizar veículo
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

// GET /frota
if ($method === 'GET' && $id === null) {
    $stmt = $pdo->query("SELECT * FROM frota ORDER BY ativo DESC, modelo");
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

// GET /frota/{id}
if ($method === 'GET' && $id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM frota WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) json_error('Veículo não encontrado.', 404);
    json_response(['status' => 'ok', 'data' => $row]);
}

// POST /frota
if ($method === 'POST') {
    if (empty($body['modelo']) || empty($body['placa'])) {
        json_error('modelo e placa são obrigatórios.', 422);
    }
    $pdo->prepare("
        INSERT INTO frota (modelo, cor, placa, ano, documentacao_ok,
                           vencimento_ipva, vencimento_seguro, vencimento_revisao, observacoes)
        VALUES (:modelo, :cor, :placa, :ano, :dok, :ipva, :seguro, :revisao, :obs)
    ")->execute([
        'modelo'  => sanitize($body['modelo']),
        'cor'     => sanitize($body['cor']     ?? ''),
        'placa'   => strtoupper(preg_replace('/[^A-Z0-9]/i', '', $body['placa'])),
        'ano'     => filter_var($body['ano'] ?? null, FILTER_VALIDATE_INT) ?: null,
        'dok'     => isset($body['documentacao_ok']) ? (int) $body['documentacao_ok'] : 1,
        'ipva'    => $body['vencimento_ipva']    ?: null,
        'seguro'  => $body['vencimento_seguro']  ?: null,
        'revisao' => $body['vencimento_revisao'] ?: null,
        'obs'     => sanitize($body['observacoes'] ?? ''),
    ]);
    json_response(['status' => 'ok', 'id' => (int) $pdo->lastInsertId()]);
}

// PATCH /frota/{id}
if ($method === 'PATCH' && $id !== null) {
    $allowed = ['modelo','cor','placa','ano','documentacao_ok',
                'vencimento_ipva','vencimento_seguro','vencimento_revisao','observacoes','ativo'];
    $sets = []; $params = ['id' => $id];
    foreach ($allowed as $f) {
        if (!array_key_exists($f, $body)) continue;
        $val = $body[$f];
        if ($f === 'placa') $val = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $val));
        elseif (in_array($f, ['ano','documentacao_ok','ativo'], true)) $val = (int) $val;
        elseif (in_array($f, ['vencimento_ipva','vencimento_seguro','vencimento_revisao'], true)) $val = $val ?: null;
        else $val = sanitize((string) $val);
        $sets[]     = "$f = :$f";
        $params[$f] = $val;
    }
    if (!$sets) json_error('Nada para atualizar.');
    $pdo->prepare("UPDATE frota SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
    json_response(['status' => 'ok']);
}

json_error('Método não suportado.', 405);
