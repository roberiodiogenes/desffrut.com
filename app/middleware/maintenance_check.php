<?php
/**
 * Desffrut — Middleware: Verificação de Modo Manutenção (Fase 9)
 *
 * COMO USAR: inclua em páginas públicas (index.php, checkout, etc.) APÓS
 * require config.php e helpers/functions.php, mas ANTES de gerar HTML.
 *
 *   require_once __DIR__ . '/app/middleware/maintenance_check.php';
 *
 * Roles isentos (podem navegar normalmente mesmo durante manutenção):
 *   dev_admin, super_admin, gerente, caixa, entregador, rh_financeiro
 */

// Garante que db() esteja disponível
if (!function_exists('db')) {
    require_once __DIR__ . '/../config/database.php';
}

$_manut_ativa = false;
$_manut_msg   = 'Sistema em manutenção. Voltamos em breve! 🌿';

try {
    $_pdo_m = db();
    $_stmt_m = $_pdo_m->query(
        "SELECT chave, valor FROM configuracoes WHERE chave IN ('manutencao_ativa','manutencao_msg')"
    );
    if ($_stmt_m) {
        foreach ($_stmt_m->fetchAll(PDO::FETCH_KEY_PAIR) as $_k => $_v) {
            if ($_k === 'manutencao_ativa') $_manut_ativa = ($_v === '1');
            if ($_k === 'manutencao_msg')   $_manut_msg   = $_v;
        }
    }
} catch (Throwable $_me) {
    $_manut_ativa = false; // Falha silenciosa — não bloqueia o sistema
}

if ($_manut_ativa) {
    // Garante sessão iniciada para verificar o role
    if (session_status() === PHP_SESSION_NONE) {
        if (defined('SESSION_NAME')) { session_name(SESSION_NAME); }
        session_start();
    }
    $_u_manut  = $_SESSION['usuario'] ?? null;
    $_roles_ok = ['dev_admin','super_admin','gerente','caixa','entregador','rh_financeiro'];

    if (!$_u_manut || !in_array($_u_manut['role'], $_roles_ok, true)) {
        http_response_code(503);
        $msg_esc = htmlspecialchars($_manut_msg, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <link rel="icon" type="image/png" href="' . BASE_PATH . '/public/img/favicon.png">
            <title>Manutenção — Desffrut</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
            <style>
                body { background:#f0fdf4; display:flex; align-items:center;
                       justify-content:center; min-height:100vh; font-family:sans-serif; }
                .box { text-align:center; max-width:480px; padding:40px; }
                .icon { font-size:4rem; margin-bottom:20px; }
                h1 { color:#1b5e20; font-size:1.8rem; font-weight:700; }
                p  { color:#555; margin-top:12px; font-size:.95rem; }
                .badge-manut { background:#e8f5e9; color:#2e7d32; padding:4px 14px;
                               border-radius:20px; font-size:.75rem; font-weight:600; }
            </style>
        </head><body>
            <div class="box">
                <div class="icon">🌿</div>
                <h1>Desffrut</h1>
                <p class="badge-manut">Em manutenção</p>
                <p>' . $msg_esc . '</p>
            </div>
        </body></html>';
        exit;
    }
}
