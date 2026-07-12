<?php
/**
 * Desffrut — Ficha do Funcionário (RH)
 * Rota: /rh/ficha/{id}
 * Ficha cadastral completa, preenchida automaticamente a partir dos dados
 * de admissão (RH). Idade calculada a partir da data de nascimento.
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
    SELECT f.*, u.nome, u.email, u.cpf, u.telefone, u.whatsapp, u.data_nascimento,
           u.endereco, u.numero, u.complemento, u.bairro, u.cidade, u.foto_perfil, u.role,
           l.nome AS loja_nome, l.endereco AS loja_endereco
    FROM funcionarios f
    JOIN usuarios u ON u.id = f.usuario_id
    JOIN lojas    l ON l.id = f.loja_id
    WHERE f.id = :id
");
$stmt->execute(['id' => $funcionario_id]);
$f = $stmt->fetch();

if (!$f) {
    http_response_code(404);
    exit('Funcionário não encontrado.');
}

// Calcula idade automaticamente a partir da data de nascimento
$idade = null;
if (!empty($f['data_nascimento'])) {
    $nasc = new DateTime($f['data_nascimento']);
    $hoje = new DateTime();
    $idade = $hoje->diff($nasc)->y;
}

function fmt_data_br(?string $d): string {
    if (!$d) return '—';
    $partes = explode('-', $d);
    return count($partes) === 3 ? "{$partes[2]}/{$partes[1]}/{$partes[0]}" : $d;
}
function fmt_moeda_br($v): string {
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}

$endereco_completo = trim(
    ($f['endereco'] ?? '') .
    (!empty($f['numero']) ? ', ' . $f['numero'] : '') .
    (!empty($f['complemento']) ? ' — ' . $f['complemento'] : '')
);
$cidade_bairro = trim(($f['bairro'] ?? '') . (!empty($f['cidade']) ? ' — ' . $f['cidade'] : ''), ' —');

$foto_url = !empty($f['foto_perfil'])
    ? (str_starts_with($f['foto_perfil'], 'http') ? $f['foto_perfil'] : BASE_PATH . '/' . ltrim($f['foto_perfil'], '/'))
    : BASE_PATH . '/public/img/avatar-padrao.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title>Ficha — <?= htmlspecialchars($f['nome']) ?> — <?= NOME_SISTEMA ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        body { background:#f4f6fa; font-family:'Segoe UI',sans-serif; }
        .fc-header { background:#1b5e20; color:#fff; padding:14px 24px;
                     display:flex; align-items:center; gap:16px; }
        .fc-header a { color:rgba(255,255,255,.75); font-size:.85rem; text-decoration:none; }
        .fc-header a:hover { color:#fff; }
        .fc-wrap { max-width:820px; margin:28px auto; padding:0 16px 40px; }
        .fc-card { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.08); overflow:hidden; }
        .fc-top { display:flex; gap:22px; padding:26px; border-bottom:1px solid #eee; align-items:center; flex-wrap:wrap; }
        .fc-foto { width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid #e8f5e9; background:#eee; }
        .fc-nome { font-size:1.4rem; font-weight:800; color:#1b1b1b; margin:0; }
        .fc-cargo { color:#2e7d32; font-weight:600; font-size:.95rem; }
        .fc-badge { display:inline-block; padding:3px 12px; border-radius:20px; font-size:.75rem; font-weight:700; margin-top:6px; }
        .fc-badge.ativo { background:#e8f5e9; color:#2e7d32; }
        .fc-badge.inativo { background:#ffebee; color:#c62828; }
        .fc-section { padding:20px 26px; border-bottom:1px solid #f2f2f2; }
        .fc-section:last-child { border-bottom:none; }
        .fc-section h6 { font-weight:700; color:#555; text-transform:uppercase; font-size:.75rem;
                          letter-spacing:.6px; margin-bottom:12px; }
        .fc-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 24px; }
        .fc-item .lbl { font-size:.72rem; color:#888; }
        .fc-item .val { font-size:.92rem; color:#222; font-weight:600; }
        .fc-actions { padding:18px 26px; display:flex; gap:10px; flex-wrap:wrap; }
        @media (max-width:600px) { .fc-grid { grid-template-columns:1fr; } }
        @media print { .fc-header, .fc-actions { display:none !important; } body { background:#fff; } }
    </style>
</head>
<body>

<div class="fc-header">
    <span style="font-size:1.4rem;">🪪</span>
    <div>
        <div style="font-weight:700;">Ficha do Funcionário</div>
        <div style="font-size:.78rem;opacity:.75;">Dados cadastrais completos</div>
    </div>
    <div class="ms-auto">
        <a href="javascript:history.back()">← Voltar</a>
    </div>
</div>

<div class="fc-wrap">
    <div class="fc-card">
        <div class="fc-top">
            <img class="fc-foto" src="<?= htmlspecialchars($foto_url) ?>" alt="Foto de <?= htmlspecialchars($f['nome']) ?>"
                 onerror="this.style.display='none'">
            <div>
                <p class="fc-nome"><?= htmlspecialchars($f['nome']) ?></p>
                <div class="fc-cargo"><?= htmlspecialchars($f['cargo']) ?> · <?= htmlspecialchars($f['loja_nome']) ?></div>
                <span class="fc-badge <?= $f['ativo'] ? 'ativo' : 'inativo' ?>">
                    <?= $f['ativo'] ? 'Ativo' : 'Inativo/Desligado' ?>
                </span>
            </div>
        </div>

        <div class="fc-section">
            <h6>Dados Pessoais</h6>
            <div class="fc-grid">
                <div class="fc-item"><div class="lbl">Data de Nascimento</div><div class="val"><?= fmt_data_br($f['data_nascimento']) ?></div></div>
                <div class="fc-item"><div class="lbl">Idade</div><div class="val"><?= $idade !== null ? $idade . ' anos' : '—' ?></div></div>
                <div class="fc-item"><div class="lbl">CPF</div><div class="val"><?= htmlspecialchars($f['cpf'] ?: '—') ?></div></div>
                <div class="fc-item"><div class="lbl">E-mail</div><div class="val"><?= htmlspecialchars($f['email']) ?></div></div>
                <div class="fc-item"><div class="lbl">Telefone</div><div class="val"><?= htmlspecialchars($f['telefone'] ?: '—') ?></div></div>
                <div class="fc-item"><div class="lbl">WhatsApp</div><div class="val"><?= htmlspecialchars($f['whatsapp'] ?: '—') ?></div></div>
            </div>
        </div>

        <div class="fc-section">
            <h6>Endereço</h6>
            <div class="fc-grid">
                <div class="fc-item" style="grid-column:1/3;"><div class="lbl">Logradouro</div><div class="val"><?= htmlspecialchars($endereco_completo ?: '—') ?></div></div>
                <div class="fc-item" style="grid-column:1/3;"><div class="lbl">Bairro / Cidade</div><div class="val"><?= htmlspecialchars($cidade_bairro ?: '—') ?></div></div>
            </div>
        </div>

        <div class="fc-section">
            <h6>Dados do Contrato</h6>
            <div class="fc-grid">
                <div class="fc-item"><div class="lbl">Cargo</div><div class="val"><?= htmlspecialchars($f['cargo']) ?></div></div>
                <div class="fc-item"><div class="lbl">Loja</div><div class="val"><?= htmlspecialchars($f['loja_nome']) ?></div></div>
                <div class="fc-item"><div class="lbl">Tipo de Contrato</div><div class="val"><?= strtoupper($f['tipo_contrato'] ?: 'CLT') ?></div></div>
                <div class="fc-item"><div class="lbl">Carga Horária</div><div class="val"><?= (int) ($f['carga_horaria'] ?: 8) ?>h/dia</div></div>
                <div class="fc-item"><div class="lbl">Salário Base</div><div class="val"><?= fmt_moeda_br($f['salario_base']) ?></div></div>
                <div class="fc-item"><div class="lbl">Data de Admissão</div><div class="val"><?= fmt_data_br($f['admitido_em']) ?></div></div>
                <?php if (!empty($f['demitido_em'])): ?>
                <div class="fc-item"><div class="lbl">Data de Demissão</div><div class="val"><?= fmt_data_br($f['demitido_em']) ?></div></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($f['observacoes'])): ?>
            <div class="fc-item mt-3"><div class="lbl">Observações</div><div class="val" style="font-weight:400;"><?= nl2br(htmlspecialchars($f['observacoes'])) ?></div></div>
            <?php endif; ?>
        </div>

        <div class="fc-actions">
            <a class="btn btn-success btn-sm" href="<?= BASE_PATH ?>/rh/cracha/<?= $f['id'] ?>" target="_blank">🎫 Gerar Crachá</a>
            <button class="btn btn-outline-dark btn-sm" onclick="window.print()">🖨 Imprimir Ficha</button>
        </div>
    </div>
</div>

</body>
</html>
