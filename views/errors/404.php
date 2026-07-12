<?php
/**
 * Desffrut — Página de Erro 404 personalizada
 * Acionada pelo ErrorDocument 404 no .htaccess
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();

http_response_code(404);
$titulo_pagina  = 'Página não encontrada';
$og_description = 'A página que você procura não existe. Navegue pelo catálogo de produtos ou volte à página inicial.';
$canonical_url  = BASE_URL . BASE_PATH . '/404';
$robots         = 'noindex,nofollow';
$mostrar_sacola = true;
require_once __DIR__ . '/../../app/views/layout/header.php';
?>

<style>
.erro-404 {
    min-height: 60vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 60px 20px;
}
.erro-404 .emoji { font-size: 5rem; margin-bottom: 16px; line-height: 1; }
.erro-404 h1 { font-size: 2rem; font-weight: 800; color: #1b5e20; margin-bottom: 8px; }
.erro-404 p  { color: #666; max-width: 420px; margin: 0 auto 28px; }
.erro-404 .sugestoes { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
</style>

<div class="erro-404">
    <div class="emoji">🥦</div>
    <h1>Página não encontrada</h1>
    <p>Ops! Esse endereço sumiu da prateleira. Pode ter sido removido, renomeado ou nunca ter existido.</p>
    <div class="sugestoes">
        <a href="<?= BASE_PATH ?>/" class="btn btn-success">🏠 Ver o catálogo</a>
        <a href="<?= BASE_PATH ?>/lojas" class="btn btn-outline-success">📍 Nossas lojas</a>
        <a href="<?= BASE_PATH ?>/meu-perfil" class="btn btn-outline-secondary">👤 Meu perfil</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
