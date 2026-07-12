<?php
/**
 * Desffrut — Middleware: Modo Restrito (Categoria 22)
 *
 * Inclua NO TOPO de qualquer endpoint/API que deve ser bloqueado
 * quando o modo_restrito está ativo.
 *
 * Comportamento:
 *   - super_admin e dev_admin: NÃO bloqueados (podem sempre acessar)
 *   - outros roles: HTTP 402 com JSON de erro
 *
 * Usage em API (retorna JSON):
 *   require_once APP . '/middleware/modo_restrito.php';
 *
 * Usage em view (exibe tela de bloqueio):
 *   define('MODO_RESTRITO_HTML', true);
 *   require_once APP . '/middleware/modo_restrito.php';
 */
if (!function_exists('modo_restrito_ativo')) {
    require_once __DIR__ . '/../helpers/functions.php';
}

if (modo_restrito_ativo()) {
    $auth_usr = null;
    // Tenta pegar usuário da sessão ou do token (API)
    if (function_exists('api_auth')) {
        $auth_usr = api_auth();
    } elseif (function_exists('usuario_logado')) {
        $auth_usr = usuario_logado();
    }

    $role = $auth_usr['role'] ?? '';

    // super_admin e dev_admin passam livremente
    if (!in_array($role, ['super_admin', 'dev_admin'], true)) {
        $motivo = function_exists('motivo_restricao')
            ? motivo_restricao()
            : 'Funcionalidade temporariamente indisponível. Entre em contato com o suporte.';

        if (defined('MODO_RESTRITO_HTML') && MODO_RESTRITO_HTML) {
            // Modo view: exibe página de bloqueio
            ?>
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head><meta charset="UTF-8"><title>Acesso Restrito — <?= defined('NOME_SISTEMA') ? NOME_SISTEMA : 'Desffrut' ?></title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
            </head>
            <body class="bg-light">
            <div class="d-flex align-items-center justify-content-center min-vh-100">
                <div class="text-center p-5 bg-white rounded-4 shadow" style="max-width:460px;">
                    <div style="font-size:3.5rem;">🔒</div>
                    <h3 class="mt-3 fw-bold">Acesso temporariamente restrito</h3>
                    <p class="text-muted mt-2 mb-4"><?= htmlspecialchars($motivo) ?></p>
                    <div class="alert alert-warning">
                        Código de status: <strong>402 — Payment Required</strong>
                    </div>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/dashboard" class="btn btn-outline-secondary">← Voltar ao painel</a>
                </div>
            </div>
            </body></html>
            <?php
            exit;
        } else {
            // Modo API: retorna JSON 402
            http_response_code(402);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'      => 'error',
                'code'        => 402,
                'message'     => $motivo,
                'modo_restrito' => true,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
