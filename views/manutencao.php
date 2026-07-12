<?php
/**
 * Desffrut — Tela de Manutenção
 * Exibida pelo middleware (app/middleware/auth_check.php) quando
 * configuracoes.manutencao_ativa = 1 (ativado pelo dev_admin, Fase 9).
 * Roles operacionais são isentos e não chegam a esta tela.
 */
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';

http_response_code(503);
header('Retry-After: 3600');

// Carrega CMS para logo e mensagem customizada
$_cms = [];
try {
    $pdo  = db();
    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
    if ($stmt) $_cms = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $_) {}

$_nome      = $_cms['nome_sistema']         ?? NOME_SISTEMA;
$_logo      = $_cms['logo_path']            ?? '';
$_cor1      = $_cms['cor_primaria']         ?? '#2e7d32';
$_mensagem  = $_cms['mensagem_manutencao']  ?? 'Estamos realizando uma atualização rápida. Voltamos em breve!';
$_previsao  = $_cms['previsao_manutencao']  ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title>Em Manutenção — <?= htmlspecialchars($_nome) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        :root { --cor-primaria: <?= htmlspecialchars($_cor1) ?>; }
        body { background: #f1f8e9; display:flex; flex-direction:column;
               align-items:center; justify-content:center; min-height:100vh;
               text-align:center; padding:40px 20px; font-family:system-ui,sans-serif; }
        .manut-card { background:#fff; border-radius:16px; padding:48px 36px;
                      max-width:480px; box-shadow:0 4px 24px rgba(0,0,0,.08); }
        .manut-logo { font-size:3.5rem; margin-bottom:12px; }
        h1 { font-size:1.6rem; font-weight:800; color:var(--cor-primaria); margin-bottom:8px; }
        .msg { color:#555; font-size:1rem; line-height:1.6; margin-bottom:20px; }
        .previsao { font-size:.85rem; color:#888; }
        .badge-manut { background:var(--cor-primaria); color:#fff; border-radius:20px;
                       padding:4px 14px; font-size:.8rem; display:inline-block; margin-bottom:20px; }
    </style>
</head>
<body>
    <div class="manut-card">
        <?php if ($_logo): ?>
            <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($_logo) ?>" alt="<?= htmlspecialchars($_nome) ?>" height="48" style="margin-bottom:12px">
        <?php else: ?>
            <div class="manut-logo">🌿</div>
        <?php endif; ?>

        <span class="badge-manut">🔧 Em manutenção</span>
        <h1><?= htmlspecialchars($_nome) ?></h1>
        <p class="msg"><?= nl2br(htmlspecialchars($_mensagem)) ?></p>

        <?php if ($_previsao): ?>
        <p class="previsao">⏱ Previsão de retorno: <strong><?= htmlspecialchars($_previsao) ?></strong></p>
        <?php endif; ?>

        <p class="previsao mt-3">
            Dúvidas? Fale conosco pelo WhatsApp das nossas
            <a href="<?= BASE_PATH ?>/lojas" style="color:var(--cor-primaria)">filiais</a>.
        </p>
    </div>
</body>
</html>
