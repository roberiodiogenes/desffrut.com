<?php
/**
 * Desffrut — API v1: Autenticação (SSO)
 *
 * POST /api/v1/auth/login     → valida credenciais, abre sessão, retorna token + role
 * POST /api/v1/auth/registrar → cria conta de cliente com aceite LGPD obrigatório
 * POST /api/v1/auth/logout    → revoga token e destrói sessão
 */

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Mapa de destino por role — prefixado com BASE_PATH para funcionar em subdiretório (XAMPP)
// e na raiz do domínio (produção) sem alteração de código.
// Todos os roles operacionais vão para o dashboard unificado.
// Apenas 'cliente' vai para sua área dedicada.
$destinos = [
    'cliente'       => BASE_PATH . '/',
    'caixa'         => BASE_PATH . '/dashboard',
    'entregador'    => BASE_PATH . '/dashboard',
    'rh_financeiro' => BASE_PATH . '/dashboard',
    'gerente'       => BASE_PATH . '/dashboard',
    'super_admin'   => BASE_PATH . '/dashboard',
    'dev_admin'     => BASE_PATH . '/dev',
];

// ─── POST /api/v1/auth/login ──────────────────────────────────────────────────
if ($id === 'login' && $method === 'POST') {

    $email = trim($body['email'] ?? '');
    $senha = $body['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        json_response(['status' => 'error', 'message' => 'E-mail e senha são obrigatórios.', 'data' => null], 422);
    }

    $model   = new Usuario();
    $usuario = $model->buscarPorEmail($email);

    // Credenciais inválidas — mensagem genérica (não revela se e-mail existe)
    if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
        registrar_log(null, 'login_falhou', 'usuarios', null, ['email' => $email]);
        json_response(['status' => 'error', 'message' => 'E-mail ou senha inválidos.', 'data' => null], 401);
    }

    // Gera token seguro e persiste na sessão
    $token = gerar_token(32);

    iniciar_sessao();
    $_SESSION['api_token']  = $token;
    $_SESSION['usuario_id'] = (int) $usuario['id']; // atalho para os endpoints de API
    $_SESSION['usuario']    = [
        'id'          => (int) $usuario['id'],
        'nome'        => $usuario['nome'],
        'email'       => $usuario['email'],
        'role'        => $usuario['role'],
        'loja_id'     => $usuario['loja_id'] ? (int) $usuario['loja_id'] : null,
        'cpf'         => $usuario['cpf']          ?? null,
        'telefone'    => $usuario['telefone']      ?? null,
        'whatsapp'    => $usuario['whatsapp']      ?? null,
        'endereco'    => $usuario['endereco']      ?? null,
        'numero'      => $usuario['numero']        ?? null,
        'complemento' => $usuario['complemento']   ?? null,
        'bairro'      => $usuario['bairro']        ?? null,
        'foto_perfil' => $usuario['foto_perfil']   ?? null,
    ];

    $model->atualizarToken((int) $usuario['id'], $token);

    registrar_log((int) $usuario['id'], 'login', 'usuarios', (int) $usuario['id']);

    json_response([
        'status'  => 'ok',
        'message' => 'Login realizado com sucesso.',
        'data'    => [
            'token'    => $token,
            'nome'     => $usuario['nome'],
            'role'     => $usuario['role'],
            'loja_id'  => $usuario['loja_id'] ? (int) $usuario['loja_id'] : null,
            'redirect' => $destinos[$usuario['role']] ?? '/',
        ],
    ]);
}

// ─── POST /api/v1/auth/registrar ─────────────────────────────────────────────
if ($id === 'registrar' && $method === 'POST') {

    $nome  = sanitize($body['nome']  ?? '');
    $email = trim($body['email']     ?? '');
    $cpf   = preg_replace('/\D/', '', $body['cpf'] ?? '');
    $senha = $body['senha']          ?? '';
    $lgpd  = !empty($body['lgpd_aceite']);

    // Validações
    if (empty($nome) || empty($email) || empty($senha)) {
        json_response(['status' => 'error', 'message' => 'Nome, e-mail e senha são obrigatórios.', 'data' => null], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['status' => 'error', 'message' => 'E-mail inválido.', 'data' => null], 422);
    }
    if (strlen($senha) < 8) {
        json_response(['status' => 'error', 'message' => 'A senha deve ter no mínimo 8 caracteres.', 'data' => null], 422);
    }
    if (!$lgpd) {
        json_response(['status' => 'error', 'message' => 'O aceite dos Termos de Uso é obrigatório (LGPD).', 'data' => null], 422);
    }

    $model = new Usuario();

    if ($model->emailExiste($email)) {
        json_response(['status' => 'error', 'message' => 'Este e-mail já está cadastrado.', 'data' => null], 409);
    }

    $novo_id = $model->criar([
        'nome'           => $nome,
        'email'          => $email,
        'cpf'            => $cpf ?: null,
        'senha_hash'     => password_hash($senha, PASSWORD_DEFAULT),
        'role'           => 'cliente',
        'loja_id'        => null,
        'lgpd_aceito_em' => date('Y-m-d H:i:s'),
    ]);

    // Fase 12 — Programa de Indicação: vincula ao indicador se vier com ref_code
    $ref_code = preg_replace('/[^A-Z0-9]/', '', strtoupper($body['ref_code'] ?? ''));
    if ($ref_code && $novo_id) {
        try {
            $pdo_reg = db();
            $chk_ind = $pdo_reg->prepare("SELECT id FROM usuarios WHERE codigo_indicacao = :c AND ativo = 1");
            $chk_ind->execute(['c' => $ref_code]);
            $ind_row = $chk_ind->fetch();
            if ($ind_row && (int)$ind_row['id'] !== (int)$novo_id) {
                $pdo_reg->prepare("UPDATE usuarios SET indicado_por_id = :iid WHERE id = :uid")
                    ->execute(['iid' => (int)$ind_row['id'], 'uid' => $novo_id]);
            }
        } catch (\Throwable $_) { /* coluna ainda não existe — silencia */ }
    }

    registrar_log($novo_id, 'cadastro_cliente', 'usuarios', $novo_id);

    json_response([
        'status'  => 'ok',
        'message' => 'Conta criada com sucesso. Faça login para continuar.',
        'data'    => ['id' => $novo_id, 'redirect' => '/login'],
    ], 201);
}

// ─── POST /api/v1/auth/logout ─────────────────────────────────────────────────
if ($id === 'logout' && $method === 'POST') {
    $usuario_sessao = api_auth();

    if ($usuario_sessao) {
        $model = new Usuario();
        $model->revogarToken((int) $usuario_sessao['id']);
        registrar_log((int) $usuario_sessao['id'], 'logout', 'usuarios', (int) $usuario_sessao['id']);
    }

    iniciar_sessao();
    $_SESSION = [];
    session_destroy();

    json_response(['status' => 'ok', 'message' => 'Sessão encerrada.', 'data' => null]);
}

json_response(['status' => 'error', 'message' => 'Rota de autenticação inválida.', 'data' => null], 400);
