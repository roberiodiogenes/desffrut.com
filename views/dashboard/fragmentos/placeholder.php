<?php
/* Fragmento genérico: "Em Desenvolvimento" ou card de link direto */
$titulo      = htmlspecialchars($_GET['titulo']    ?? 'Módulo');
$descr_raw   = $_GET['descricao'] ?? 'Esta funcionalidade está sendo desenvolvida.';
$fase        = (int) ($_GET['fase'] ?? 0);

// Se descricao começar com 'link:', exibe um card de acesso direto à página
if (str_starts_with($descr_raw, 'link:')) {
    $url_destino = htmlspecialchars(trim(substr($descr_raw, 5)));
    // Prefixa com BASE_PATH se for caminho relativo
    if (!str_starts_with($url_destino, 'http') && defined('BASE_PATH')) {
        $url_destino = BASE_PATH . $url_destino;
    }
    ?>
    <div class="d-flex align-items-center justify-content-center"
         style="min-height:380px; padding: 40px 24px;">
        <div class="text-center" style="max-width:460px;">
            <div style="font-size:4rem; margin-bottom:16px;">⚙️</div>
            <h4 class="fw-bold mb-2"><?= $titulo ?></h4>
            <p class="text-muted mb-4">Esta página possui uma interface dedicada com mais recursos.</p>
            <a href="<?= $url_destino ?>" class="btn btn-success btn-lg px-5">
                Abrir <?= $titulo ?> →
            </a>
            <div class="mt-3 text-muted small">Abre em tela cheia</div>
        </div>
    </div>
    <?php
    return;
}

$descr = htmlspecialchars($descr_raw);

$fases_txt = [
    4 => 'Fase 4 — PDV Híbrido Offline',
    5 => 'Fase 5 — Relatórios & Analytics',
    6 => 'Fase 6 — Tele-Entrega & Pedidos Online',
    7 => 'Fase 7 — ERP Administrativo',
];
$fase_txt = $fases_txt[$fase] ?? "Fase $fase";
?>

<div class="d-flex align-items-center justify-content-center"
     style="min-height:380px; padding: 40px 24px;">
    <div class="text-center" style="max-width:460px;">
        <div style="font-size:4rem; margin-bottom:16px;">🔧</div>
        <h4 class="fw-bold mb-2"><?= $titulo ?></h4>
        <p class="text-muted mb-4"><?= $descr ?></p>

        <?php if ($fase): ?>
        <div class="alert alert-info d-inline-flex align-items-center gap-2 px-4">
            <span style="font-size:1.2rem;">⏳</span>
            <div class="text-start">
                <div class="fw-semibold">Em desenvolvimento</div>
                <div class="small"><?= $fase_txt ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-4 text-muted small">
            Acompanhe o progresso em
            <code>briefings/status_desenvolvimento.md</code>
        </div>
    </div>
</div>
