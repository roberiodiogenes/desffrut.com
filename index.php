<?php
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/helpers/functions.php';
iniciar_sessao();
// Verifica modo manutenção antes de renderizar (Fase 9)
require_once __DIR__ . '/app/middleware/maintenance_check.php';
$titulo_pagina  = 'Frutas, Verduras e Legumes Frescos';
$og_description = 'Compre frutas, verduras e legumes frescos online com entrega rápida. Hortifruti de qualidade na sua porta.';
$canonical_url  = BASE_URL . BASE_PATH . '/';
$mostrar_sacola = true;
$mostrar_busca  = true;
$nav_ativa      = 'catalogo';
$json_ld        = json_encode([
    '@context'      => 'https://schema.org',
    '@type'         => 'FoodEstablishment',
    'name'          => NOME_SISTEMA,
    'url'           => BASE_URL . BASE_PATH . '/',
    'description'   => 'Hortifruti fresco com entrega rápida. Frutas, verduras e legumes selecionados.',
    'servesCuisine' => 'Hortifruti',
    'priceRange'    => '$$',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
require_once __DIR__ . '/app/views/layout/header.php';
?>

<!-- ─── Carrossel de Banners (Fase 8 — CMS) ────────────────────────────── -->
<?php
// Carrega banners ativos do tipo correto (desktop/mobile)
$_banners = [];
try {
    require_once __DIR__ . '/app/config/database.php';
    $_pdo_idx = db();
    $_tipo_banner = (isset($_SERVER['HTTP_USER_AGENT']) &&
        preg_match('/Mobile|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT'] ?? ''))
        ? 'mobile' : 'desktop';
    $_stmt_b = $_pdo_idx->prepare("SELECT imagem_path, link_destino FROM banners
        WHERE ativo=1 AND tipo=:t
          AND (exibe_de IS NULL OR exibe_de <= NOW())
          AND (exibe_ate IS NULL OR exibe_ate >= NOW())
        ORDER BY ordem ASC LIMIT 10");
    $_stmt_b->execute([':t' => $_tipo_banner]);
    $_banners = $_stmt_b->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $_e) { /* tabela ainda não existe */ }
?>
<?php if (!empty($_banners)): ?>
<div id="carrossel-banners" class="carousel slide mb-0" data-bs-ride="carousel" data-bs-interval="5000">
    <div class="carousel-inner">
    <?php foreach ($_banners as $_i => $_b): ?>
        <div class="carousel-item <?= $_i === 0 ? 'active' : '' ?>">
            <?php if ($_b['link_destino']): ?>
            <a href="<?= htmlspecialchars($_b['link_destino']) ?>">
            <?php endif; ?>
                <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($_b['imagem_path']) ?>"
                     class="d-block w-100" alt="Banner <?= $_i + 1 ?>"
                     style="max-height:320px;object-fit:cover;">
            <?php if ($_b['link_destino']): ?></a><?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <?php if (count($_banners) > 1): ?>
    <button class="carousel-control-prev" type="button" data-bs-target="#carrossel-banners" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carrossel-banners" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ─── Abas de categoria ──────────────────────────────────────────────── -->
<div class="categorias-bar shadow-sm">
    <div class="container d-flex gap-1 flex-wrap">
        <button class="btn-categoria ativa" data-cat="">🛒 Todos</button>
        <button class="btn-categoria" data-cat="frutas">🍎 Frutas</button>
        <button class="btn-categoria" data-cat="verduras">🥬 Verduras</button>
        <button class="btn-categoria" data-cat="legumes">🥕 Legumes</button>
    </div>
</div>

<!-- ─── Grid de produtos ───────────────────────────────────────────────── -->
<main class="container my-4">
    <div id="estado-carregando" class="estado-vazio">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-3">Carregando produtos…</p>
    </div>

    <div id="estado-vazio-produtos" class="estado-vazio" style="display:none;">
        <div class="icone">😔</div>
        <p>Nenhum produto encontrado nesta categoria.</p>
    </div>

    <div id="grid-produtos" class="row g-3" style="display:none;"></div>
</main>

<!-- ─── Offcanvas da Sacola ────────────────────────────────────────────── -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="sacolaDrawer">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">🛒 Minha Sacola</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <div id="sacola-vazia" class="estado-vazio flex-grow-1">
            <div class="icone">🛒</div>
            <p>Sua sacola está vazia.</p>
        </div>
        <div id="sacola-itens" style="display:none;" class="flex-grow-1 overflow-auto"></div>
        <div id="sacola-rodape" class="mt-3 pt-3 border-top" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-bold">Total:</span>
                <span id="sacola-total" class="sacola-total">R$ 0,00</span>
            </div>
            <button class="btn-finalizar mb-2" onclick="sacola.finalizar()">
                Finalizar Pedido →
            </button>
            <button class="btn btn-outline-danger btn-sm w-100" onclick="sacola.esvaziar()">
                🗑 Esvaziar sacola
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/app/views/layout/footer.php'; ?>

<script src="<?= BASE_PATH ?>/public/js/sacola.js"></script>
<script src="<?= BASE_PATH ?>/public/js/catalogo.js"></script>
