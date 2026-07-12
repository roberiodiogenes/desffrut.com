<?php
/**
 * Desffrut — Roteador principal da API REST
 * Todas as requisições para /api/* chegam aqui via .htaccess.
 *
 * Padrão de URL: /api/v1/{recurso}[/{id}]
 * Resposta padrão: { "status": "ok"|"error", "data": ..., "message": "..." }
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/auth.php';

// ─── Headers CORS e Content-Type ──────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responde preflight CORS imediatamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Parse da rota ────────────────────────────────────────────────────────────
// Remove tudo até (e incluindo) /api/v1/ — funciona com ou sem BASE_PATH.
// Ex.: '/desffrut.com/api/v1/auth/login' → 'auth/login'
//      '/api/v1/auth/login'              → 'auth/login'
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri    = preg_replace('#.*/api/v1/?#', '', $uri);
$uri    = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

$partes  = explode('/', $uri);
$recurso = $partes[0] ?? '';
$id      = isset($partes[1]) && $partes[1] !== '' ? $partes[1] : null;
$sub     = isset($partes[2]) && $partes[2] !== '' ? $partes[2] : null; // Ex.: /api/v1/produtos/5/preco

// ─── Rotas públicas (sem token) ───────────────────────────────────────────────
$rotas_publicas = [
    'auth/login',
    'auth/registrar',
    'produtos/catalogo',
    'lojas',
    'leads/novo',          // Fase 10 — formulário público de parcerias
    'configuracoes/banners-publicos', // Fase 8
];

$rota_atual     = $id ? "$recurso/$id" : $recurso;
$requer_auth    = !in_array($rota_atual, $rotas_publicas, true)
               && !in_array($recurso,     $rotas_publicas, true);

// ─── Autenticação ─────────────────────────────────────────────────────────────
if ($requer_auth) {
    api_auth_exigir(); // aborta com 401 se token inválido
}

// ─── Dispatch para o endpoint ─────────────────────────────────────────────────
$arquivo_endpoint = __DIR__ . '/v1/' . $recurso . '.php';

if ($recurso !== '' && file_exists($arquivo_endpoint)) {
    // Expõe $id e $method para o endpoint incluído
    require $arquivo_endpoint;
} else {
    json_response([
        'status'  => 'error',
        'message' => "Endpoint '$recurso' não encontrado.",
        'data'    => null,
    ], 404);
}
