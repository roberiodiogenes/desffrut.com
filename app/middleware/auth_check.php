<?php
/**
 * Desffrut — Middleware de proteção de rotas por role
 *
 * COMO USAR: inclua no TOPO de cada view protegida, definindo $roles_permitidos
 * antes de fazer o require. Exemplo:
 *
 *   $roles_permitidos = ['gerente', 'super_admin'];
 *   require_once __DIR__ . '/../../app/middleware/auth_check.php';
 *
 * Se $roles_permitidos não for definido, qualquer usuário logado tem acesso.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

iniciar_sessao();

$usuario = $_SESSION['usuario'] ?? null;

// 1. Sem sessão → redireciona para login
if ($usuario === null) {
    redirecionar(BASE_PATH . '/login');
}

// 2. Verifica role, se especificado
if (!empty($roles_permitidos)) {
    $role_usuario = $usuario['role'] ?? '';
    // dev_admin e super_admin sempre passam
    if (!in_array($role_usuario, ['super_admin', 'dev_admin'], true)
        && !in_array($role_usuario, $roles_permitidos, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
              <title>Acesso Negado — Desffrut</title></head><body>
              <h1>403 — Acesso negado</h1>
              <p>Você não tem permissão para acessar esta área.</p>
              <a href="javascript:history.back()">Voltar</a></body></html>';
        exit;
    }
}
