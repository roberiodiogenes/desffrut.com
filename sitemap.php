<?php
/**
 * Desffrut — Sitemap XML dinâmico
 * Acesso: /sitemap.xml (rota mapeada no .htaccess)
 * Gera URLs estáticas + produtos do catálogo (futuro: URL canônica por produto)
 */
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex'); // o sitemap em si não precisa ser indexado

// Só publica o domínio definitivo fixo em produção final; em 'local' e 'teste'
// usa a URL real da requisição, evitando indexar o domínio de teste como se
// fosse o definitivo.
$base = AMBIENTE === 'definitivo' ? URL_CANONICA_DEFINITIVO : BASE_URL . BASE_PATH;

// Páginas estáticas públicas
$estaticas = [
    ['loc' => '/',           'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => '/lojas',      'priority' => '0.9', 'changefreq' => 'weekly'],
    ['loc' => '/sobre',      'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => '/fidelidade', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => '/parcerias',  'priority' => '0.6', 'changefreq' => 'monthly'],
    ['loc' => '/termos',     'priority' => '0.4', 'changefreq' => 'yearly'],
    ['loc' => '/privacidade','priority' => '0.4', 'changefreq' => 'yearly'],
];

$hoje = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($estaticas as $pag) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . $pag['loc']) . "</loc>\n";
    echo "    <lastmod>{$hoje}</lastmod>\n";
    echo "    <changefreq>{$pag['changefreq']}</changefreq>\n";
    echo "    <priority>{$pag['priority']}</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
