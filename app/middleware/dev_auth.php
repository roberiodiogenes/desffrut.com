<?php
/**
 * Desffrut — Middleware exclusivo do Dev Mode (Fase 9)
 * Inclua no TOPO de views/dev/index.php.
 * Bloqueia qualquer role que não seja dev_admin — nem super_admin tem acesso.
 * A página de erro é deliberadamente vaga (403 genérico) para não revelar a rota.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

iniciar_sessao();

$usuario_dev = $_SESSION['usuario'] ?? null;

if (!$usuario_dev || ($usuario_dev['role'] ?? '') !== 'dev_admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="pt-BR"><head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <link rel="icon" type="image/png" href="' . BASE_PATH . '/public/img/favicon.png">
        <title>403 — Proibido</title>
        <style>
            body { margin:0; background:#0d0d0d; color:#555; font-family:monospace;
                   display:flex; align-items:center; justify-content:center; min-height:100vh; }
            .box { text-align:center; }
            h1 { color:#c62828; font-size:3rem; margin:0; }
            p  { color:#444; margin-top:10px; }
        </style>
    </head><body>
        <div class="box">
            <h1>403</h1>
            <p>Acesso negado.</p>
        </div>
    </body></html>';
    exit;
}
