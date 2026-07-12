<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();
// Redireciona para o dashboard unificado
header('Location: ' . BASE_PATH . '/dashboard');
exit;

$roles_permitidos = ['gerente', 'super_admin'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$titulo_pagina  = 'Dashboard Gerência';
$mostrar_sacola = false;
require_once __DIR__ . '/../../app/views/layout/header.php';

$u = usuario_logado();
?>

<div class="container py-4">
    <h2 class="fw-bold text-verde mb-1">Dashboard — Gerência</h2>
    <p class="text-muted mb-4">Bem-vindo, <?= htmlspecialchars($u['nome']) ?>.</p>

    <div class="row g-3">
        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/gerencia/produtos" class="text-decoration-none">
                <div class="card h-100 border-success hover-card">
                    <div class="card-body text-center py-4">
                        <div style="font-size:2.5rem;">🛒</div>
                        <h5 class="card-title mt-2">Produtos</h5>
                        <p class="card-text text-muted small">Cadastro, preços e promoções</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/gerencia/estoque" class="text-decoration-none">
                <div class="card h-100 border-success hover-card">
                    <div class="card-body text-center py-4">
                        <div style="font-size:2.5rem;">📦</div>
                        <h5 class="card-title mt-2">Estoque</h5>
                        <p class="card-text text-muted small">Inventário e quebras</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/gerencia/despacho" class="text-decoration-none">
                <div class="card h-100 border-success hover-card">
                    <div class="card-body text-center py-4">
                        <div style="font-size:2.5rem;">🚚</div>
                        <h5 class="card-title mt-2">Despacho</h5>
                        <p class="card-text text-muted small">Pedidos para entrega</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/gerencia/relatorios" class="text-decoration-none">
                <div class="card h-100 border-success hover-card">
                    <div class="card-body text-center py-4">
                        <div style="font-size:2.5rem;">📊</div>
                        <h5 class="card-title mt-2">Relatórios</h5>
                        <p class="card-text text-muted small">Estoque crítico, vendas</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Widget estoque crítico -->
    <div id="widget-critico" class="mt-4" style="display:none;">
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-0">
            🔴 <strong id="cnt-critico"></strong> produto(s) com estoque crítico.
            <a href="<?= BASE_PATH ?>/gerencia/relatorio-estoque-critico"
               class="btn btn-sm btn-danger ms-auto">Ver lista de compras</a>
        </div>
    </div>
</div>

<style>
.hover-card { transition: transform .15s, box-shadow .15s; }
.hover-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.12); }
.text-verde { color: #2e7d32; }
</style>

<script>
(async () => {
    const token = sessionStorage.getItem('desffrut_token') || '';
    if (!token) return;
    try {
        const r = await fetch(APP.api + '/estoque/critico', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const j = await r.json();
        const cnt = (j.data || []).length;
        if (cnt > 0) {
            document.getElementById('cnt-critico').textContent = cnt;
            document.getElementById('widget-critico').style.display = '';
        }
    } catch {}
})();
</script>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
