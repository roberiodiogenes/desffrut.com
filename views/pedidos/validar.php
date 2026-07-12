<?php
/**
 * Desffrut — Validação de Pedido via WhatsApp (Fase 11)
 * URL: /pedidos/validar/{token}
 *
 * Página pública (sem auth). A loja clica no link recebido no WhatsApp.
 * Valida o token: marca pedido como "Em Preparo" e token como usado.
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

$token = preg_replace('/[^a-f0-9\-]/', '', $_GET['token'] ?? '');

$resultado = null; // 'ok' | 'invalido' | 'expirado' | 'ja_usado' | 'erro'
$pedido    = null;
$msg_erro  = '';

if (!$token || strlen($token) !== 36) {
    $resultado = 'invalido';
} else {
    try {
        $pdo = db();

        // Busca o pedido pelo token
        $stmt = $pdo->prepare("
            SELECT p.id, p.status, p.total, p.wa_token_expira_em, p.wa_token_usado,
                   p.endereco_entrega, p.numero, p.bairro,
                   c.nome AS cliente_nome,
                   l.nome AS loja_nome
            FROM pedidos p
            JOIN usuarios c ON c.id = p.cliente_id
            JOIN lojas    l ON l.id = p.loja_id
            WHERE p.wa_token = :token
        ");
        $stmt->execute(['token' => $token]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            $resultado = 'invalido';
        } elseif ($pedido['wa_token_usado']) {
            $resultado = 'ja_usado';
        } elseif (new DateTime() > new DateTime($pedido['wa_token_expira_em'])) {
            $resultado = 'expirado';
        } elseif (!in_array($pedido['status'], ['aguardando_validacao', 'aguardando'], true)) {
            $resultado = 'ja_usado'; // Pedido já foi processado
        } else {
            // ✅ Token válido — aceita o pedido
            $pdo->prepare("
                UPDATE pedidos
                SET status = 'preparando',
                    wa_token_usado = 1,
                    updated_at = NOW()
                WHERE wa_token = :token
            ")->execute(['token' => $token]);

            $resultado = 'ok';
        }
    } catch (\Exception $e) {
        $resultado = 'erro';
        $msg_erro  = $e->getMessage();
    }
}

// Ícone e título por resultado
$icone  = ['ok' => '✅', 'invalido' => '❌', 'expirado' => '⏰', 'ja_usado' => '🔁', 'erro' => '⚠️'][$resultado] ?? '⚠️';
$titulo = [
    'ok'       => 'Pedido aceito!',
    'invalido' => 'Link inválido',
    'expirado' => 'Link expirado',
    'ja_usado' => 'Pedido já processado',
    'erro'     => 'Erro interno',
][$resultado] ?? 'Ops!';
$cor    = ['ok' => '#2e7d32', 'invalido' => '#c62828', 'expirado' => '#e65100', 'ja_usado' => '#1565c0', 'erro' => '#6a1b9a'][$resultado] ?? '#333';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title><?= $titulo ?> — Desffrut</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
            padding: 36px 28px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .logo {
            font-size: 1.6rem;
            font-weight: 800;
            color: #2e7d32;
            margin-bottom: 24px;
            letter-spacing: -.5px;
        }
        .logo span { color: #f57f17; }
        .icone {
            font-size: 3.5rem;
            margin-bottom: 12px;
            display: block;
        }
        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: <?= $cor ?>;
            margin-bottom: 10px;
        }
        .descricao {
            font-size: .95rem;
            color: #555;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .pedido-info {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 16px;
            text-align: left;
            margin-bottom: 20px;
            font-size: .9rem;
        }
        .pedido-info strong { color: #212121; }
        .pedido-info div { margin-bottom: 5px; }
        .badge-ok {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: .85rem;
            margin-bottom: 20px;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            margin-bottom: 10px;
        }
        .btn-primary { background: #2e7d32; color: #fff; }
        .btn-outline { background: transparent; border: 1.5px solid #ccc; color: #555; }
        .rodape {
            margin-top: 24px;
            font-size: .78rem;
            color: #aaa;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">Des<span>ff</span>rut</div>

    <span class="icone"><?= $icone ?></span>
    <h1><?= htmlspecialchars($titulo) ?></h1>

    <?php if ($resultado === 'ok' && $pedido): ?>
        <span class="badge-ok">Em Preparo agora</span>
        <div class="pedido-info">
            <div><strong>Pedido:</strong> #<?= (int)$pedido['id'] ?></div>
            <div><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></div>
            <div><strong>Endereço:</strong> <?= htmlspecialchars($pedido['endereco_entrega'] . ', ' . $pedido['numero'] . ' — ' . $pedido['bairro']) ?></div>
            <div><strong>Total:</strong> R$ <?= number_format($pedido['total'], 2, ',', '.') ?></div>
            <div><strong>Filial:</strong> <?= htmlspecialchars($pedido['loja_nome']) ?></div>
        </div>
        <p class="descricao">O pedido foi aceito e agora aparece como <strong>Em Preparo</strong> no Dashboard. A impressão térmica é disparada automaticamente pelo painel.</p>
        <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-primary">Abrir Dashboard →</a>

    <?php elseif ($resultado === 'ja_usado'): ?>
        <?php if ($pedido): ?>
        <div class="pedido-info">
            <div><strong>Pedido:</strong> #<?= (int)$pedido['id'] ?></div>
            <div><strong>Status atual:</strong> <?= htmlspecialchars($pedido['status']) ?></div>
        </div>
        <?php endif; ?>
        <p class="descricao">Este pedido já foi aceito anteriormente. Consulte o Dashboard para acompanhar o status.</p>
        <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-primary">Abrir Dashboard →</a>

    <?php elseif ($resultado === 'expirado'): ?>
        <p class="descricao">O link de validação expirou (válido por 24h). Entre em contato com o cliente para confirmar o pedido manualmente.</p>
        <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-outline">Abrir Dashboard</a>

    <?php else: ?>
        <p class="descricao">Link inválido ou pedido não encontrado. Verifique se o link está completo e tente novamente.</p>
        <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-outline">Abrir Dashboard</a>
    <?php endif; ?>

    <div class="rodape">Desffrut &copy; <?= date('Y') ?> · Sistema de Gestão</div>
</div>
</body>
</html>
