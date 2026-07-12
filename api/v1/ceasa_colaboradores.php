<?php
/**
 * Desffrut — API v1: Colaboradores CEASA (motoristas / auxiliares)
 *
 * GET    /api/v1/ceasa_colaboradores          → listar
 * POST   /api/v1/ceasa_colaboradores          → cadastrar
 * PATCH  /api/v1/ceasa_colaboradores/{id}     → atualizar
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
    $stmt = $pdo->query("SELECT * FROM ceasa_colaboradores ORDER BY funcao, nome");
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

if ($method === 'GET' && $id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM ceasa_colaboradores WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) json_error('Colaborador não encontrado.', 404);
    json_response(['status' => 'ok', 'data' => $row]);
}

if ($method === 'POST') {
    if (empty($body['nome'])) json_error('nome é obrigatório.', 422);
    $funcao = in_array($body['funcao'] ?? '', ['motorista','auxiliar']) ? $body['funcao'] : 'auxiliar';
    $pdo->prepare("INSERT INTO ceasa_colaboradores (nome, funcao, telefone) VALUES (:nome, :funcao, :tel)")
        ->execute([
            'nome'   => sanitize($body['nome']),
            'funcao' => $funcao,
            'tel'    => sanitize($body['telefone'] ?? ''),
        ]);
    json_response(['status' => 'ok', 'id' => (int) $pdo->lastInsertId()]);
}

if ($method === 'PATCH' && $id !== null) {
    $sets = []; $params = ['id' => $id];
    if (isset($body['nome']))      { $sets[] = 'nome = :nome';       $params['nome']   = sanitize($body['nome']); }
    if (isset($body['funcao']) && in_array($body['funcao'], ['motorista','auxiliar'])) {
                                     $sets[] = 'funcao = :funcao';   $params['funcao'] = $body['funcao']; }
    if (isset($body['telefone'])) { $sets[] = 'telefone = :tel';     $params['tel']    = sanitize($body['telefone']); }
    if (isset($body['ativo']))    { $sets[] = 'ativo = :ativo';      $params['ativo']  = (int) $body['ativo']; }
    if (!$sets) json_error('Nada para atualizar.');
    $pdo->prepare("UPDATE ceasa_colaboradores SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
    json_response(['status' => 'ok']);
}

json_error('Método não suportado.', 405);
