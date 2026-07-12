<?php
/**
 * Header compartilhado — inclua no topo de cada página pública.
 *
 * Variáveis opcionais (defina antes de incluir):
 *   $titulo_pagina       string  — título da aba (padrão: NOME_SISTEMA)
 *   $og_description      string  — meta description e og:description
 *   $og_image            string  — URL absoluta da imagem OG (fallback: logo)
 *   $og_url              string  — URL canônica (fallback: URL atual)
 *   $canonical_url       string  — <link rel="canonical"> (fallback: $og_url)
 *   $robots              string  — robots meta (padrão: "index,follow")
 *   $json_ld             string  — JSON-LD serializado para injetar no <head>
 *   $mostrar_sacola      bool    — exibe ícone da sacola (padrão: true)
 *   $mostrar_busca       bool    — exibe campo de busca (padrão: false)
 *   $mostrar_nav_publica bool    — exibe links de navegação pública (padrão: true)
 *   $nav_ativa           string  — slug da página ativa no nav (ex: 'lojas')
 *   $css_extra           string  — URL de CSS adicional
 */
$titulo_pagina       = $titulo_pagina       ?? NOME_SISTEMA;
$mostrar_sacola      = $mostrar_sacola      ?? true;
$mostrar_busca       = $mostrar_busca       ?? false;
$mostrar_nav_publica = $mostrar_nav_publica ?? true;
$nav_ativa           = $nav_ativa           ?? '';
$robots              = $robots              ?? 'index,follow';
$og_description      = $og_description      ?? 'Hortifruti fresco com entrega rápida. Frutas, verduras e legumes direto para a sua mesa.';
$og_image            = $og_image            ?? (BASE_URL . BASE_PATH . '/uploads/logos/og-default.webp');
$og_url              = $og_url              ?? (BASE_URL . BASE_PATH . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));
$canonical_url       = $canonical_url       ?? $og_url;
$json_ld             = $json_ld             ?? '';

// ── UTM capture (Fase 12) ─────────────────────────────────────────────────────
$_utm_keys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
$_utm_novo = [];
foreach ($_utm_keys as $_uk) {
    if (isset($_GET[$_uk]) && $_GET[$_uk] !== '') {
        $_utm_novo[$_uk] = htmlspecialchars(strip_tags($_GET[$_uk]));
    }
}
if ($_utm_novo) {
    $_SESSION['utm'] = $_utm_novo;
}
unset($_utm_keys, $_utm_novo, $_uk);

$usuario = usuario_logado();

// ── CMS: carrega configurações do banco (Fase 8) ─────────────────────────────
if (!function_exists('db')) {
    require_once __DIR__ . '/../../config/database.php';
}
$_cms = [];
try {
    $_pdo_h = db();
    $_stmt  = $_pdo_h->query("SELECT chave, valor FROM configuracoes");
    if ($_stmt) { $_cms = $_stmt->fetchAll(PDO::FETCH_KEY_PAIR); }
} catch (Throwable $_e) {}

$_cms_nome  = $_cms['nome_sistema']   ?? NOME_SISTEMA;
$_cms_logo  = $_cms['logo_path']      ?? '';
$_cms_cor1  = $_cms['cor_primaria']   ?? '#2e7d32';
$_cms_cor2  = $_cms['cor_secundaria'] ?? '#a5d6a7';
unset($_stmt, $_pdo_h, $_e);

// OG image fallback: usa logo do CMS se disponível
if ($_cms_logo) {
    $og_image = BASE_URL . BASE_PATH . '/' . $_cms_logo;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO básico -->
    <title><?= htmlspecialchars($titulo_pagina) ?> — <?= htmlspecialchars($_cms_nome) ?></title>
    <meta name="description" content="<?= htmlspecialchars($og_description) ?>">
    <meta name="robots"      content="<?= htmlspecialchars($robots) ?>">
    <link rel="canonical"    href="<?= htmlspecialchars($canonical_url) ?>">

    <!-- Open Graph / WhatsApp / redes sociais -->
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= htmlspecialchars($_cms_nome) ?>">
    <meta property="og:title"       content="<?= htmlspecialchars($titulo_pagina) ?> — <?= htmlspecialchars($_cms_nome) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
    <meta property="og:image"       content="<?= htmlspecialchars($og_image) ?>">
    <meta property="og:url"         content="<?= htmlspecialchars($canonical_url) ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= htmlspecialchars($titulo_pagina) ?> — <?= htmlspecialchars($_cms_nome) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($og_description) ?>">
    <meta name="twitter:image"       content="<?= htmlspecialchars($og_image) ?>">

    <!-- Favicon -->
    <link rel="icon"       type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <link rel="shortcut icon"               href="<?= BASE_PATH ?>/public/img/favicon.png">

    <!-- PWA -->
    <link rel="manifest"  href="<?= BASE_PATH ?>/manifest.json">
    <meta name="theme-color" content="<?= htmlspecialchars($_cms_cor1) ?>">

    <!-- Estilos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/main.css">
    <?php if (!empty($css_extra)): ?>
    <link rel="stylesheet" href="<?= $css_extra ?>">
    <?php endif; ?>

    <!-- CMS: custom properties dinâmicas -->
    <style>
        :root {
            --cor-primaria:   <?= htmlspecialchars($_cms_cor1) ?>;
            --cor-secundaria: <?= htmlspecialchars($_cms_cor2) ?>;
        }
        .bg-desffrut   { background-color: var(--cor-primaria) !important; }
        .btn-finalizar,
        .btn-categoria.ativa { background: var(--cor-primaria) !important; }
        .text-desffrut { color: var(--cor-primaria) !important; }
    </style>

    <!-- Fase 12: Meta Pixel -->
    <?php if (!empty($_cms['pixel_meta_id'])): ?>
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init','<?= htmlspecialchars($_cms['pixel_meta_id']) ?>');
    fbq('track','PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?= htmlspecialchars($_cms['pixel_meta_id']) ?>&ev=PageView&noscript=1"/></noscript>
    <?php endif; ?>

    <!-- Fase 12: Google Analytics 4 -->
    <?php if (!empty($_cms['gtag_id'])): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($_cms['gtag_id']) ?>"></script>
    <script>
    window.dataLayer=window.dataLayer||[];
    function gtag(){dataLayer.push(arguments);}
    gtag('js',new Date());
    gtag('config','<?= htmlspecialchars($_cms['gtag_id']) ?>');
    </script>
    <?php endif; ?>

    <!-- APP globals + trackEvent helper -->
    <script>
        const APP = {
            base:    '<?= BASE_PATH ?>',
            api:     '<?= API_ROOT ?>',
            usuario: <?= $usuario ? json_encode(['id' => $usuario['id'], 'nome' => $usuario['nome'], 'role' => $usuario['role']]) : 'null' ?>,
            pixel:   '<?= htmlspecialchars($_cms['pixel_meta_id'] ?? '') ?>',
            gtag:    '<?= htmlspecialchars($_cms['gtag_id']       ?? '') ?>',
        };
        function trackEvent(nome, dados) {
            if (APP.pixel && window.fbq) fbq('track', nome, dados || {});
            if (APP.gtag  && window.gtag) gtag('event', nome, dados || {});
        }
    </script>

    <?php if ($json_ld): ?>
    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json"><?= $json_ld ?></script>
    <?php endif; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-desffrut shadow-sm">
    <div class="container-fluid">

        <!-- Logo -->
        <a class="navbar-brand fw-bold" href="<?= BASE_PATH ?>/">
            <?php if ($_cms_logo): ?>
            <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($_cms_logo) ?>"
                 alt="<?= htmlspecialchars($_cms_nome) ?>" height="32" class="me-1">
            <?php else: ?>🌿<?php endif; ?>
            <?= htmlspecialchars($_cms_nome) ?>
        </a>

        <!-- Botão mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">

            <!-- Links de navegação pública -->
            <?php if ($mostrar_nav_publica): ?>
            <ul class="navbar-nav me-auto align-items-lg-center mt-2 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?= $nav_ativa === 'catalogo' ? ' active' : '' ?>"
                       href="<?= BASE_PATH ?>/">🏠 Catálogo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_ativa === 'lojas' ? ' active' : '' ?>"
                       href="<?= BASE_PATH ?>/lojas">📍 Nossas Lojas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_ativa === 'sobre' ? ' active' : '' ?>"
                       href="<?= BASE_PATH ?>/sobre">🌿 Quem Somos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_ativa === 'fidelidade' ? ' active' : '' ?>"
                       href="<?= BASE_PATH ?>/fidelidade">🎁 Fidelidade</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_ativa === 'parcerias' ? ' active' : '' ?>"
                       href="<?= BASE_PATH ?>/parcerias">🤝 Parcerias</a>
                </li>
            </ul>
            <?php elseif ($mostrar_busca): ?>
            <!-- Espaçador quando nav pública está oculta mas busca ativa -->
            <div class="me-auto"></div>
            <?php else: ?>
            <div class="me-auto"></div>
            <?php endif; ?>

            <?php if ($mostrar_busca): ?>
            <!-- Campo de busca (catálogo) -->
            <div class="me-3" style="max-width:320px;width:100%;">
                <input type="search" id="busca-input" class="form-control form-control-sm"
                       placeholder="Buscar produto…" autocomplete="off">
            </div>
            <?php endif; ?>

            <div class="navbar-nav align-items-lg-center gap-2 mt-2 mt-lg-0">

                <?php if ($mostrar_sacola): ?>
                <!-- Sacola -->
                <button class="btn btn-outline-light btn-sm position-relative"
                        data-bs-toggle="offcanvas" data-bs-target="#sacolaDrawer" aria-label="Sacola">
                    🛒 Sacola
                    <span id="sacola-badge" class="position-absolute top-0 start-100 translate-middle
                          badge rounded-pill bg-warning text-dark" style="display:none;">0</span>
                </button>
                <?php endif; ?>

                <?php if ($usuario): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        👤 <?= htmlspecialchars(explode(' ', $usuario['nome'])[0]) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($usuario['role'] === 'cliente'): ?>
                        <li><a class="dropdown-item" href="<?= BASE_PATH ?>/meu-perfil">Meu Perfil &amp; Pontos</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item text-danger" href="#"
                               onclick="fetch(APP.api+'/auth/logout',{method:'POST',headers:{'Authorization':'Bearer '+(sessionStorage.getItem('desffrut_token')||'')}}).finally(()=>window.location.href=APP.base+'/login')">
                                Sair
                            </a>
                        </li>
                    </ul>
                </div>
                <?php else: ?>
                <a href="<?= BASE_PATH ?>/login" class="btn btn-warning btn-sm fw-bold">Entrar</a>
                <?php endif; ?>

            </div>
        </div>
    </div>
</nav>
