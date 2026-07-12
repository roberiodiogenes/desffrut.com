<?php
/**
 * Desffrut — Middleware de autenticação da API REST
 *
 * Valida o token enviado no header Authorization: Bearer <token>.
 * Retorna o array do usuário logado ou null se inválido.
 */

function api_auth(): ?array {
    // Garante sessão iniciada (necessário para Bearer e fallback de cookie)
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }

    // ── Modo 1: Bearer token (PDV offline, apps externos) ────────────────────
    $headers     = function_exists('getallheaders') ? getallheaders() : [];
    $auth_header = '';
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') { $auth_header = $value; break; }
    }
    if (empty($auth_header) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!empty($auth_header) && str_starts_with($auth_header, 'Bearer ')) {
        $token = trim(substr($auth_header, 7));
        if (
            !empty($token) &&
            isset($_SESSION['api_token'], $_SESSION['usuario']) &&
            hash_equals($_SESSION['api_token'], $token)
        ) {
            return $_SESSION['usuario'];
        }
        // Token enviado mas inválido → rejeita (não faz fallback)
        return null;
    }

    // ── Modo 2: Cookie de sessão PHP (dashboard, mesmo domínio) ─────────────
    // Fetch do browser envia automaticamente o cookie de sessão para
    // requisições de mesma origem — sem necessidade de header extra.
    if (isset($_SESSION['usuario'])) {
        return $_SESSION['usuario'];
    }

    return null;
}

/**
 * Executa a verificação e aborta com 401 caso o token seja inválido.
 * Use em endpoints que exigem autenticação obrigatória.
 */
function api_auth_exigir(): array {
    $usuario = api_auth();
    if ($usuario === null) {
        json_response(['status' => 'error', 'message' => 'Não autorizado. Token ausente ou inválido.'], 401);
    }
    return $usuario;
}

/**
 * Verifica se o usuário autenticado possui um dos roles permitidos.
 * super_admin sempre passa.
 */
function api_auth_role(array $roles_permitidos): array {
    $usuario = api_auth_exigir();
    // dev_admin e super_admin sempre passam em qualquer verificação de role
    if (!in_array($usuario['role'], ['super_admin', 'dev_admin'], true)
        && !in_array($usuario['role'], $roles_permitidos, true)) {
        json_response(['status' => 'error', 'message' => 'Permissão insuficiente.'], 403);
    }
    return $usuario;
}
