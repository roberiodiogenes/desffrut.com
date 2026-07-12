<?php
/**
 * Desffrut — Crachá do Funcionário (RH)
 * Rota: /rh/cracha/{id}
 * Gera um crachá minimalista (5,4cm x 8,6cm) para impressão/PDF via navegador:
 * fundo branco, foto, nome, cargo, nome/logo da empresa e QR code (~2cm) no
 * canto inferior direito apontando para a Ficha do Funcionário.
 * Acesso restrito: dono (super_admin), RH e gerente.
 */
$roles_permitidos = ['gerente', 'rh_financeiro', 'super_admin'];
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$u = usuario_logado();
$pdo = db();

$funcionario_id = (int) ($_GET['funcionario_id'] ?? 0);
if (!$funcionario_id) {
    http_response_code(404);
    exit('Funcionário não informado.');
}

$stmt = $pdo->prepare("
    SELECT f.id, f.cargo, u.nome, u.foto_perfil
    FROM funcionarios f
    JOIN usuarios u ON u.id = f.usuario_id
    WHERE f.id = :id
");
$stmt->execute(['id' => $funcionario_id]);
$f = $stmt->fetch();

if (!$f) {
    http_response_code(404);
    exit('Funcionário não encontrado.');
}

// Logo da empresa (configuracoes.logo_path)
$cfgStmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave = 'logo_path'");
$logoRow = $cfgStmt->fetch();
$logo_path = $logoRow['valor'] ?? '';
$logo_url  = $logo_path
    ? (str_starts_with($logo_path, 'http') ? $logo_path : BASE_PATH . '/' . ltrim($logo_path, '/'))
    : '';

$foto_url = !empty($f['foto_perfil'])
    ? (str_starts_with($f['foto_perfil'], 'http') ? $f['foto_perfil'] : BASE_PATH . '/' . ltrim($f['foto_perfil'], '/'))
    : '';

$link_ficha = BASE_URL . BASE_PATH . '/rh/ficha/' . $f['id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title>Crachá — <?= htmlspecialchars($f['nome']) ?> — <?= NOME_SISTEMA ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { background:#eef1f4; font-family:'Segoe UI',sans-serif; margin:0; padding:24px; }
        .cr-toolbar { max-width:520px; margin:0 auto 18px; display:flex; gap:10px; justify-content:center; }
        .cr-btn { padding:8px 18px; border-radius:6px; border:none; cursor:pointer; font-size:.85rem; font-weight:600; }
        .cr-btn-print { background:#2e7d32; color:#fff; }
        .cr-btn-back  { background:#fff; color:#555; border:1px solid #bbb; }

        /* Crachá — 5,4cm x 8,6cm (formato retrato, padrão crachá corporativo) */
        .cracha {
            width: 5.4cm; height: 8.6cm;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 0.25cm;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            padding: 0.35cm 0.3cm;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .cr-topo-linha { width:100%; height:0.12cm; background:#2e7d32; border-radius:2px; margin-bottom:0.22cm; }
        .cr-empresa { display:flex; align-items:center; gap:0.12cm; margin-bottom:0.3cm; }
        .cr-empresa img { height:0.55cm; object-fit:contain; }
        .cr-empresa .nome-empresa { font-size:7.5pt; font-weight:800; color:#1b1b1b; letter-spacing:.4px; text-transform:uppercase; }

        .cr-foto {
            width: 2.6cm; height: 2.6cm; border-radius: 50%;
            object-fit: cover; background:#f0f0f0; border:2px solid #e8f5e9;
            margin-bottom: 0.25cm;
        }
        .cr-foto-vazia {
            width: 2.6cm; height: 2.6cm; border-radius: 50%; background:#eee;
            display:flex; align-items:center; justify-content:center;
            font-size:1.1cm; color:#bbb; margin-bottom:0.25cm;
        }

        .cr-nome { font-size:9.5pt; font-weight:800; color:#111; text-align:center; line-height:1.15; margin-bottom:0.08cm; }
        .cr-cargo { font-size:7.5pt; font-weight:600; color:#2e7d32; text-align:center; text-transform:uppercase; letter-spacing:.4px; }

        .cr-rodape { margin-top:auto; width:100%; display:flex; justify-content:flex-end; align-items:flex-end; }
        .cr-qr { width:2cm; height:2cm; }
        .cr-qr canvas, .cr-qr img { width:2cm !important; height:2cm !important; }

        @media print {
            body { background:#fff; padding:0; margin:0; }
            .cr-toolbar { display:none !important; }
            .cracha { box-shadow:none; border:0.5pt solid #ccc; margin:1cm auto; }
            @page { size: auto; margin: 0.5cm; }
        }
    </style>
</head>
<body>

<div class="cr-toolbar">
    <button class="cr-btn cr-btn-back" onclick="history.back()">← Voltar</button>
    <button class="cr-btn cr-btn-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
</div>

<div class="cracha">
    <div class="cr-topo-linha"></div>
    <div class="cr-empresa">
        <?php if ($logo_url): ?><img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo"><?php endif; ?>
        <span class="nome-empresa"><?= htmlspecialchars(NOME_SISTEMA) ?></span>
    </div>

    <?php if ($foto_url): ?>
        <img class="cr-foto" src="<?= htmlspecialchars($foto_url) ?>" alt="Foto" onerror="this.outerHTML='<div class=&quot;cr-foto-vazia&quot;>👤</div>'">
    <?php else: ?>
        <div class="cr-foto-vazia">👤</div>
    <?php endif; ?>

    <div class="cr-nome"><?= htmlspecialchars($f['nome']) ?></div>
    <div class="cr-cargo"><?= htmlspecialchars($f['cargo']) ?></div>

    <div class="cr-rodape">
        <div class="cr-qr" id="cr-qr"></div>
    </div>
</div>

<script>
new QRCode(document.getElementById('cr-qr'), {
    text: <?= json_encode($link_ficha) ?>,
    width: 76, height: 76,
    correctLevel: QRCode.CorrectLevel.M
});
</script>

</body>
</html>
