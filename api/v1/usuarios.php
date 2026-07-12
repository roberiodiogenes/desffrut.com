<?php
/**
 * Desffrut — API v1: Usuários
 *
 * GET    /api/v1/usuarios           → listar todos (super_admin)
 * GET    /api/v1/usuarios/{id}      → buscar um (super_admin)
 * POST   /api/v1/usuarios           → criar usuário (super_admin)
 * PUT    /api/v1/usuarios/{id}      → editar usuário (super_admin)
 * PATCH  /api/v1/usuarios/{id}      → ativar / desativar (super_admin)
 */

if (!function_exists('api_auth_exigir')) { http_response_code(403); exit; }

$u = api_auth_exigir();
api_auth_role(['super_admin']);

$pdo = db();

// ── GET /usuarios ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $id === null) {
    $stmt = $pdo->query("
        SELECT u.id, u.nome, u.email, u.role, u.loja_id, u.ativo,
               u.created_at, l.nome AS loja_nome
        FROM   usuarios u
        LEFT JOIN lojas l ON l.id = u.loja_id
        ORDER BY u.role ASC, u.nome ASC
    ");
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'message' => '']);
}

// ── GET /usuarios/{id} ────────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.nome, u.email, u.role, u.loja_id, u.ativo,
               u.created_at, l.nome AS loja_nome
        FROM   usuarios u
        LEFT JOIN lojas l ON l.id = u.loja_id
        WHERE  u.id = :id
    ");
    $stmt->execute([':id' => (int)$id]);
    $usr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usr) {
        json_response(['status' => 'error', 'message' => 'Usuário não encontrado.', 'data' => null], 404);
    }
    json_response(['status' => 'ok', 'data' => $usr, 'message' => '']);
}

// ── POST /usuarios — criar ────────────────────────────────────────────────────
if ($method === 'POST' && $id === null) {
    $d     = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome  = sanitize($d['nome']  ?? '');
    $email = strtolower(trim($d['email'] ?? ''));
    $role  = sanitize($d['role']  ?? 'caixa');
    $senha = $d['senha'] ?? '';
    $loja  = !empty($d['loja_id']) ? (int)$d['loja_id'] : null;
    $ativo = isset($d['ativo']) ? (int)(bool)$d['ativo'] : 1;

    $roles_validos = ['caixa','gerente','entregador','rh_financeiro','super_admin','cliente','dev_admin'];

    if (!$nome || !$email) {
        json_response(['status' => 'error', 'message' => 'Nome e e-mail são obrigatórios.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['status' => 'error', 'message' => 'E-mail inválido.'], 422);
    }
    if (strlen($senha) < 8) {
        json_response(['status' => 'error', 'message' => 'Senha deve ter no mínimo 8 caracteres.'], 422);
    }
    if (!in_array($role, $roles_validos, true)) {
        json_response(['status' => 'error', 'message' => 'Role inválido.'], 422);
    }

    // Verifica e-mail duplicado
    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = :e");
    $chk->execute([':e' => $email]);
    if ($chk->fetch()) {
        json_response(['status' => 'error', 'message' => 'E-mail já cadastrado.'], 409);
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nome, email, role, loja_id, senha_hash, ativo, lgpd_aceito_em)
        VALUES (:n, :e, :r, :l, :h, :a, NOW())
    ");
    $stmt->execute([':n'=>$nome, ':e'=>$email, ':r'=>$role, ':l'=>$loja, ':h'=>$hash, ':a'=>$ativo]);
    $novo_id = (int)$pdo->lastInsertId();

    registrar_log($u['id'], 'usuario_criado', 'usuarios', $novo_id,
        ['nome'=>$nome,'email'=>$email,'role'=>$role]);

    json_response(['status' => 'ok', 'message' => 'Usuário criado com sucesso.', 'data' => ['id' => $novo_id]], 201);
}

// ── PUT /usuarios/{id} — editar ────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $uid  = (int)$id;
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];

    // Verifica se o usuário existe
    $existe = $pdo->prepare("SELECT id, role FROM usuarios WHERE id = :id");
    $existe->execute([':id' => $uid]);
    $alvo = $existe->fetch(PDO::FETCH_ASSOC);
    if (!$alvo) {
        json_response(['status' => 'error', 'message' => 'Usuário não encontrado.'], 404);
    }

    // Impede que super_admin rebaixe a si mesmo
    if ((int)$u['id'] === $uid && isset($d['role']) && $d['role'] !== $u['role']) {
        json_response(['status' => 'error', 'message' => 'Você não pode alterar seu próprio role.'], 403);
    }

    $sets  = [];
    $binds = [':id' => $uid];

    if (!empty($d['nome'])) {
        $sets[] = 'nome = :nome';
        $binds[':nome'] = sanitize($d['nome']);
    }
    if (!empty($d['email'])) {
        $email = strtolower(trim($d['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['status' => 'error', 'message' => 'E-mail inválido.'], 422);
        }
        // Verifica duplicidade excluindo o próprio usuário
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = :e AND id != :id");
        $chk->execute([':e' => $email, ':id' => $uid]);
        if ($chk->fetch()) {
            json_response(['status' => 'error', 'message' => 'E-mail já em uso.'], 409);
        }
        $sets[] = 'email = :email';
        $binds[':email'] = $email;
    }
    if (isset($d['role'])) {
        $roles_validos = ['caixa','gerente','entregador','rh_financeiro','super_admin','cliente','dev_admin'];
        if (!in_array($d['role'], $roles_validos, true)) {
            json_response(['status' => 'error', 'message' => 'Role inválido.'], 422);
        }
        $sets[] = 'role = :role';
        $binds[':role'] = $d['role'];
    }
    if (array_key_exists('loja_id', $d)) {
        $sets[] = 'loja_id = :loja_id';
        $binds[':loja_id'] = !empty($d['loja_id']) ? (int)$d['loja_id'] : null;
    }
    if (isset($d['ativo'])) {
        $sets[] = 'ativo = :ativo';
        $binds[':ativo'] = (int)(bool)$d['ativo'];
    }
    if (!empty($d['senha'])) {
        if (strlen($d['senha']) < 8) {
            json_response(['status' => 'error', 'message' => 'Senha deve ter no mínimo 8 caracteres.'], 422);
        }
        $sets[] = 'senha_hash = :senha_hash';
        $binds[':senha_hash'] = password_hash($d['senha'], PASSWORD_BCRYPT);
    }

    if (empty($sets)) {
        json_response(['status' => 'ok', 'message' => 'Nenhum campo para atualizar.']);
    }

    $sql = "UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = :id";
    $pdo->prepare($sql)->execute($binds);

    registrar_log($u['id'], 'usuario_atualizado', 'usuarios', $uid, array_keys($d));
    json_response(['status' => 'ok', 'message' => 'Usuário atualizado com sucesso.']);
}

// ── PATCH /usuarios/{id} — ativar/desativar ───────────────────────────────────
if ($method === 'PATCH' && $id) {
    $uid  = (int)$id;
    if ((int)$u['id'] === $uid) {
        json_response(['status' => 'error', 'message' => 'Você não pode desativar sua própria conta.'], 403);
    }
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $ativo = isset($d['ativo']) ? (int)(bool)$d['ativo'] : null;
    if ($ativo === null) {
        json_response(['status' => 'error', 'message' => 'Campo ativo é obrigatório.'], 422);
    }
    $pdo->prepare("UPDATE usuarios SET ativo = :a WHERE id = :id")->execute([':a'=>$ativo, ':id'=>$uid]);
    registrar_log($u['id'], $ativo ? 'usuario_ativado' : 'usuario_desativado', 'usuarios', $uid);
    json_response(['status' => 'ok', 'message' => $ativo ? 'Usuário ativado.' : 'Usuário desativado.']);
}

json_response(['status' => 'error', 'message' => 'Método não permitido.', 'data' => null], 405);
