<?php
/**
 * Desffrut — API v1: Produtos
 *
 * Rotas públicas (sem auth):
 *   GET  /api/v1/produtos/catalogo[?categoria=X]  → catálogo centralizado
 *
 * Rotas autenticadas (gerente, super_admin):
 *   GET    /api/v1/produtos               → listagem gerencial
 *   GET    /api/v1/produtos/{id}          → produto com preços por loja
 *   POST   /api/v1/produtos               → criar produto
 *   PUT    /api/v1/produtos/{id}          → atualizar produto
 *   PATCH  /api/v1/produtos/{id}          → toggle ativo/inativo
 *   PUT    /api/v1/produtos/{id}/preco    → salvar preço por loja
 */
require_once __DIR__ . '/../../app/models/Produto.php';

// ── GET /catalogo ─────────────────────────────────────────────────────────────
if ($id === 'catalogo' && $method === 'GET') {
    $categoria = filter_input(INPUT_GET, 'categoria', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $cats_ok   = ['frutas', 'verduras', 'legumes', 'outros'];
    if ($categoria && !in_array($categoria, $cats_ok, true)) {
        json_response(['status' => 'error', 'message' => 'Categoria inválida.', 'data' => null], 422);
    }

    $produtos   = (new Produto())->catalogo($categoria);
    $resultado  = array_map(fn ($p) => [
        'id'          => (int)  $p['id'],
        'nome'        =>        $p['nome'],
        'categoria'   =>        $p['categoria'],
        'unidade'     =>        $p['unidade_medida'],
        'preco_normal'=>(float) $p['preco_referencia'],
        'preco_atual' =>(float) $p['preco_atual'],
        'em_promocao' =>(bool)  $p['em_promocao'] && (float) $p['preco_atual'] < (float) $p['preco_referencia'],
        'foto'        => $p['foto'] ? BASE_PATH . '/' . ltrim($p['foto'], '/') : null,
        'estoque'     =>(float) $p['estoque_total'],
    ], $produtos);

    json_response(['status' => 'ok', 'data' => $resultado, 'message' => '']);
}

// ── A partir daqui: auth obrigatória ─────────────────────────────────────────
$u = api_auth_exigir();
api_auth_role(['gerente', 'super_admin']);

$model = new Produto();
$body  = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /produtos (listagem gerencial) ────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $filtros = [
        'categoria' => filter_input(INPUT_GET, 'categoria'),
        'ativo'     => filter_input(INPUT_GET, 'ativo'),
        'busca'     => filter_input(INPUT_GET, 'busca'),
    ];
    $lista = $model->listar($filtros);

    $resultado = array_map(fn ($p) => [
        'id'              => (int)   $p['id'],
        'nome'            =>         $p['nome'],
        'descricao'       =>         $p['descricao'],
        'categoria'       =>         $p['categoria'],
        'unidade'         =>         $p['unidade_medida'],
        'ean'             =>         $p['ean'],
        'codigo_interno'  =>         $p['codigo_interno'] ?? ('PRD-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT)),
        'preco_custo'     => (float) $p['preco_custo'],
        'ativo'           => (bool)  $p['ativo'],
        'foto'            => $p['foto'] ? BASE_PATH . '/' . ltrim($p['foto'], '/') : null,
        'estoque_total'   => (float) $p['estoque_total'],
        'preco_venda'     => $p['preco_referencia'] ? (float) $p['preco_referencia'] : null,
    ], $lista);

    json_response(['status' => 'ok', 'data' => $resultado, 'message' => '']);
}

// ── GET /produtos/{id} ────────────────────────────────────────────────────────
if ($id && is_numeric($id) && $method === 'GET' && $sub === null) {
    $produto = $model->buscarComPrecos((int) $id);
    if (!$produto) json_response(['status' => 'error', 'message' => 'Produto não encontrado.', 'data' => null], 404);

    $produto['foto'] = $produto['foto'] ? BASE_PATH . '/' . ltrim($produto['foto'], '/') : null;
    json_response(['status' => 'ok', 'data' => $produto, 'message' => '']);
}

// ── POST /produtos (criar) ────────────────────────────────────────────────────
if ($id === null && $method === 'POST') {
    $nome = sanitize($body['nome'] ?? '');
    if (empty($nome)) {
        json_response(['status' => 'error', 'message' => 'Nome é obrigatório.', 'data' => null], 422);
    }

    $cats_ok = ['frutas', 'verduras', 'legumes', 'outros'];
    if (!in_array($body['categoria'] ?? '', $cats_ok, true)) {
        json_response(['status' => 'error', 'message' => 'Categoria inválida.', 'data' => null], 422);
    }

    $novo_id = $model->criar([
        'nome'          => $nome,
        'descricao'     => sanitize($body['descricao'] ?? ''),
        'ean'           => preg_replace('/\D/', '', $body['ean'] ?? '') ?: null,
        'categoria'     => $body['categoria'],
        'unidade_medida'=> $body['unidade_medida'] === 'un' ? 'un' : 'kg',
        'preco_custo'   => (float) ($body['preco_custo'] ?? 0),
        'foto'          => $body['foto'] ?? null,
    ]);

    // Gera codigo_interno automaticamente: PRD-000001
    $codigo = 'PRD-' . str_pad($novo_id, 6, '0', STR_PAD_LEFT);
    db()->prepare("UPDATE produtos SET codigo_interno = :c WHERE id = :id AND (codigo_interno IS NULL OR codigo_interno = '')")
        ->execute([':c' => $codigo, ':id' => $novo_id]);

    registrar_log((int) $u['id'], 'produto_criado', 'produtos', $novo_id, ['codigo_interno' => $codigo]);
    json_response(['status' => 'ok', 'data' => ['id' => $novo_id, 'codigo_interno' => $codigo], 'message' => 'Produto cadastrado.'], 201);
}

// ── PUT /produtos/{id} (atualizar) ────────────────────────────────────────────
if ($id && is_numeric($id) && $method === 'PUT' && $sub === null) {
    $nome = sanitize($body['nome'] ?? '');
    if (empty($nome)) {
        json_response(['status' => 'error', 'message' => 'Nome é obrigatório.', 'data' => null], 422);
    }

    $dados = [
        'nome'          => $nome,
        'descricao'     => sanitize($body['descricao'] ?? ''),
        'ean'           => preg_replace('/\D/', '', $body['ean'] ?? '') ?: null,
        'categoria'     => $body['categoria'],
        'unidade_medida'=> $body['unidade_medida'] === 'un' ? 'un' : 'kg',
        'preco_custo'   => (float) ($body['preco_custo'] ?? 0),
    ];
    if (isset($body['foto'])) $dados['foto'] = $body['foto'];

    $model->atualizar((int) $id, $dados);
    registrar_log((int) $u['id'], 'produto_atualizado', 'produtos', (int) $id);
    json_response(['status' => 'ok', 'data' => null, 'message' => 'Produto atualizado.']);
}

// ── PATCH /produtos/{id} (toggle ativo) ───────────────────────────────────────
if ($id && is_numeric($id) && $method === 'PATCH') {
    $model->toggleAtivo((int) $id);
    registrar_log((int) $u['id'], 'produto_toggle_ativo', 'produtos', (int) $id);
    json_response(['status' => 'ok', 'data' => null, 'message' => 'Status atualizado.']);
}

// ── PUT /produtos/{id}/preco ──────────────────────────────────────────────────
if ($id && is_numeric($id) && $method === 'PUT' && $sub === 'preco') {
    $loja_id = (int) ($body['loja_id'] ?? 0);
    if (!$loja_id) {
        json_response(['status' => 'error', 'message' => 'loja_id obrigatório.', 'data' => null], 422);
    }
    if (empty($body['preco_venda'])) {
        json_response(['status' => 'error', 'message' => 'preco_venda obrigatório.', 'data' => null], 422);
    }

    $model->salvarPreco((int) $id, $loja_id, [
        'preco_venda'  => $body['preco_venda'],
        'promo_preco'  => $body['promo_preco']  ?? null,
        'promo_inicio' => $body['promo_inicio'] ?? null,
        'promo_fim'    => $body['promo_fim']    ?? null,
    ]);

    registrar_log((int) $u['id'], 'preco_atualizado', 'precos', (int) $id, ['loja_id' => $loja_id]);
    json_response(['status' => 'ok', 'data' => null, 'message' => 'Preço salvo.']);
}

json_response(['status' => 'error', 'message' => 'Endpoint não encontrado.', 'data' => null], 404);
