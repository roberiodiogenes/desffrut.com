<?php
/**
 * Desffrut — API: Permissões Granulares (Categoria 20)
 * Rota: /api/v1/permissoes
 *
 * GET  /api/v1/permissoes/{usuario_id}        → lista permissões do usuário (base + exceções)
 * PUT  /api/v1/permissoes/{usuario_id}        → atualiza lista de exceções (substitui tudo)
 * POST /api/v1/permissoes/{usuario_id}/{perm} → toggle individual (concedida=1/0)
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/permissoes.php';

$auth = api_auth();
api_auth_exigir();
api_auth_role(['super_admin', 'dev_admin']);  // Apenas super_admin gerencia permissões

$pdo = db();
$method   = $_SERVER['REQUEST_METHOD'];
$usuario_id = isset($id) ? (int)$id : 0;
$perm_key   = $sub ?? null; // ex: 'ver_dre'

if (!$usuario_id) {
    json_response(['erro' => 'ID de usuário obrigatório.'], 400);
}

// Verifica se o usuário existe
$stmt = $pdo->prepare("SELECT id, nome, role FROM usuarios WHERE id=:id LIMIT 1");
$stmt->execute([':id' => $usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usuario) {
    json_response(['erro' => 'Usuário não encontrado.'], 404);
}

// ── GET /api/v1/permissoes/{id} ──────────────────────────────────────────────
if ($method === 'GET') {
    try {
        // Busca exceções na tabela
        $stmt = $pdo->prepare("SELECT permissao, concedida FROM permissoes_usuario WHERE usuario_id=:uid");
        $stmt->execute([':uid' => $usuario_id]);
        $excecoes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $excecoes[$row['permissao']] = (bool)$row['concedida'];
        }
    } catch (Throwable $e) {
        // Causa mais comum: tabela `permissoes_usuario` ainda não existe
        // (falta rodar database/13-migration_fase20_permissoes.sql).
        json_response(['erro' => 'Falha ao consultar permissões no banco: ' . $e->getMessage()], 500);
    }

    // Padrão do role
    $padrao = PERMISSOES_POR_ROLE[$usuario['role']] ?? [];

    // Monta mapa final
    $resultado = [];
    foreach (PERMISSOES_DISPONIVEIS as $chave => $descricao) {
        $tem_excecao = array_key_exists($chave, $excecoes);
        $efetiva     = $tem_excecao ? $excecoes[$chave] : in_array($chave, $padrao, true);
        $resultado[] = [
            'permissao'   => $chave,
            'descricao'   => $descricao,
            'padrao_role' => in_array($chave, $padrao, true),
            'tem_excecao' => $tem_excecao,
            'efetiva'     => $efetiva,
        ];
    }

    json_response([
        'usuario' => $usuario,
        'permissoes' => $resultado,
    ]);
}

// ── PUT /api/v1/permissoes/{id} — Substituir todas as exceções ───────────────
if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    // body: {"ver_dre": true, "aplicar_desconto": false, ...}
    // apenas as chaves válidas são processadas
    $validas = array_keys(PERMISSOES_DISPONIVEIS);
    $padrao  = PERMISSOES_POR_ROLE[$usuario['role']] ?? [];

    // Remove exceções existentes
    $pdo->prepare("DELETE FROM permissoes_usuario WHERE usuario_id=:uid")->execute([':uid' => $usuario_id]);

    // Insere apenas onde o valor DIFERE do padrão do role
    $stmt = $pdo->prepare("INSERT INTO permissoes_usuario (usuario_id, permissao, concedida, criado_por) VALUES (:uid, :perm, :con, :by)");
    $inseridos = 0;
    foreach ($body as $chave => $concedida) {
        if (!in_array($chave, $validas, true)) continue;
        $concedida  = (bool)$concedida;
        $eh_padrao  = in_array($chave, $padrao, true);
        // Só insere se for diferente do padrão (exceções-only)
        if ($concedida !== $eh_padrao) {
            $stmt->execute([':uid' => $usuario_id, ':perm' => $chave, ':con' => (int)$concedida, ':by' => $auth['id']]);
            $inseridos++;
        }
    }

    registrar_log($auth['id'], 'permissoes_atualizadas', 'permissoes_usuario', $usuario_id,
        ['usuario_nome' => $usuario['nome'], 'excecoes_inseridas' => $inseridos]);

    json_response(['ok' => true, 'excecoes_salvas' => $inseridos]);
}

// ── POST /api/v1/permissoes/{id}/{perm} — Toggle individual ─────────────────
if ($method === 'POST') {
    if (!$perm_key || !array_key_exists($perm_key, PERMISSOES_DISPONIVEIS)) {
        json_response(['erro' => 'Permissão inválida.'], 400);
    }
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $concedida = isset($body['concedida']) ? (bool)$body['concedida'] : true;
    $padrao    = PERMISSOES_POR_ROLE[$usuario['role']] ?? [];
    $eh_padrao = in_array($perm_key, $padrao, true);

    if ((bool)$concedida === $eh_padrao) {
        // Remove exceção (voltou ao padrão)
        $pdo->prepare("DELETE FROM permissoes_usuario WHERE usuario_id=:uid AND permissao=:perm")
            ->execute([':uid' => $usuario_id, ':perm' => $perm_key]);
        $acao = 'removida_excecao';
    } else {
        // Insere ou atualiza exceção
        $pdo->prepare("INSERT INTO permissoes_usuario (usuario_id, permissao, concedida, criado_por)
                       VALUES (:uid, :perm, :con, :by)
                       ON DUPLICATE KEY UPDATE concedida=VALUES(concedida), criado_por=VALUES(criado_por)")
            ->execute([':uid' => $usuario_id, ':perm' => $perm_key, ':con' => (int)$concedida, ':by' => $auth['id']]);
        $acao = $concedida ? 'permissao_concedida' : 'permissao_revogada';
    }

    registrar_log($auth['id'], $acao, 'permissoes_usuario', $usuario_id,
        ['permissao' => $perm_key, 'concedida' => $concedida, 'usuario_nome' => $usuario['nome']]);

    json_response(['ok' => true, 'permissao' => $perm_key, 'concedida' => $concedida]);
}

json_response(['erro' => 'Método não suportado.'], 405);
