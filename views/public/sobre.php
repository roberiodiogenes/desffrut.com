<?php
/**
 * Desffrut — Quem Somos (página pública)
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/maintenance_check.php';

$titulo_pagina  = 'Quem Somos';
$og_description = 'Conheça o ' . NOME_SISTEMA . ': hortifruti fresco, entrega rápida e compromisso com a sua saúde e bem-estar.';
$canonical_url  = BASE_URL . BASE_PATH . '/sobre';
$nav_ativa      = 'sobre';
$mostrar_sacola = true;
$json_ld        = json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Organization',
    'name'        => NOME_SISTEMA,
    'url'         => BASE_URL . BASE_PATH . '/',
    'logo'        => BASE_URL . BASE_PATH . '/uploads/logos/og-default.webp',
    'description' => 'Hortifruti especializado em frutas, verduras e legumes frescos com entrega rápida.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

require_once __DIR__ . '/../../app/views/layout/header.php';

// Carrega slogan e texto_sobre do CMS (se existirem)
$_cms_sobre = '';
try {
    $pdo  = db();
    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('slogan','texto_sobre')");
    if ($stmt) {
        foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            if ($k === 'slogan')      $_cms_slogan = $v;
            if ($k === 'texto_sobre') $_cms_sobre  = $v;
        }
    }
} catch (Throwable $_) {}

$_slogan = $_cms_slogan ?? 'Hortifruti sempre fresco';
?>

<style>
.sobre-hero {
    background: linear-gradient(135deg,#1b5e20 0%,#2e7d32 60%,#43a047 100%);
    color:#fff; padding:60px 20px 48px; text-align:center;
}
.sobre-hero h1 { font-size:2.2rem; font-weight:800; margin:0 0 10px; }
.sobre-hero p  { opacity:.88; max-width:560px; margin:0 auto; font-size:1.05rem; }

.sobre-body { max-width:820px; margin:0 auto; padding:50px 20px 70px; }

.valores-grid {
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(200px,1fr));
    gap:20px;
    margin:32px 0;
}
.valor-card {
    background:#f9fbe7; border:1px solid #dcedc8; border-radius:12px;
    padding:24px 20px; text-align:center;
}
.valor-card .ico { font-size:2rem; margin-bottom:8px; }
.valor-card h3  { font-size:.95rem; font-weight:700; color:#1b5e20; margin:0 0 6px; }
.valor-card p   { font-size:.85rem; color:#555; margin:0; }

.diferenciais {
    background:#e8f5e9; border-radius:12px; padding:28px 28px;
    margin:36px 0;
}
.diferenciais h2 { font-size:1.1rem; font-weight:700; color:#1b5e20; margin:0 0 16px; }
.diferenciais ul { list-style:none; padding:0; margin:0;
                   display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.diferenciais li { font-size:.9rem; color:#444; display:flex; gap:8px; }
@media(max-width:480px) { .diferenciais ul { grid-template-columns:1fr; } }

.sobre-cta {
    text-align:center; margin-top:40px;
}
</style>

<div class="sobre-hero">
    <h1>🌿 <?= htmlspecialchars(NOME_SISTEMA) ?></h1>
    <p><?= htmlspecialchars($_slogan) ?></p>
</div>

<div class="sobre-body">

    <?php if ($_cms_sobre): ?>
    <div style="font-size:.95rem;color:#444;line-height:1.8;margin-bottom:36px;">
        <?= nl2br(htmlspecialchars($_cms_sobre)) ?>
    </div>
    <?php else: ?>
    <p style="font-size:1rem;color:#444;line-height:1.8;margin-bottom:36px;">
        O <strong><?= htmlspecialchars(NOME_SISTEMA) ?></strong> nasceu da paixão por oferecer produtos frescos, saudáveis e acessíveis para famílias brasileiras. Somos um hortifruti especializado em frutas, verduras e legumes selecionados, com rigoroso controle de qualidade desde a compra no CEASA até a entrega na sua porta.
    </p>
    <p style="font-size:1rem;color:#444;line-height:1.8;margin-bottom:36px;">
        Nossa operação é local e comprometida com a comunidade. Trabalhamos com fornecedores de confiança, valorizamos o produtor regional e acreditamos que comer bem começa com ingredientes de qualidade.
    </p>
    <?php endif; ?>

    <div class="valores-grid">
        <div class="valor-card">
            <div class="ico">🥕</div>
            <h3>Frescor garantido</h3>
            <p>Compramos no CEASA e entregamos no mesmo dia. Sem câmara fria por dias.</p>
        </div>
        <div class="valor-card">
            <div class="ico">🚚</div>
            <h3>Entrega rápida</h3>
            <p>Pedido feito, entrega em andamento. Acompanhe o status em tempo real.</p>
        </div>
        <div class="valor-card">
            <div class="ico">🎯</div>
            <h3>Preço justo</h3>
            <p>Compramos direto do produtor e repassamos a economia para você.</p>
        </div>
        <div class="valor-card">
            <div class="ico">🤝</div>
            <h3>Comunidade local</h3>
            <p>Geramos empregos locais e apoiamos produtores da região.</p>
        </div>
    </div>

    <div class="diferenciais">
        <h2>✅ Por que escolher o <?= htmlspecialchars(NOME_SISTEMA) ?>?</h2>
        <ul>
            <li>🌱 Produtos frescos todos os dias</li>
            <li>📱 Pedido pelo celular em minutos</li>
            <li>🎁 Programa de pontos e fidelidade</li>
            <li>🔔 Acompanhe o entregador em tempo real</li>
            <li>💬 Atendimento pelo WhatsApp da sua filial</li>
            <li>🏪 Várias filiais na cidade</li>
        </ul>
    </div>

    <div class="sobre-cta">
        <a href="<?= BASE_PATH ?>/" class="btn btn-success btn-lg me-2">🛒 Ver catálogo</a>
        <a href="<?= BASE_PATH ?>/lojas" class="btn btn-outline-success btn-lg">📍 Nossas lojas</a>
    </div>

</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
