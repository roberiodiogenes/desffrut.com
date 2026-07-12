<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();
header('Location: ' . BASE_PATH . '/dashboard');
exit;

$roles_permitidos = ['super_admin'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$titulo_pagina  = 'Dashboard Admin';
$mostrar_sacola = false;
require_once __DIR__ . '/../../app/views/layout/header.php';

$u = usuario_logado();
?>

<div class="container py-4">
    <h2 class="fw-bold text-verde mb-1">Dashboard Administrativo</h2>
    <p class="text-muted mb-4">Bem-vindo, <?= htmlspecialchars($u['nome']) ?>.</p>

    <div class="row g-3">
        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/admin/lojas" class="text-decoration-none">
                <div class="card h-100 border-success">
                    <div class="card-body text-center py-4">
                        <div style="font-size:2.5rem;">🏪</div>
                        <h5 class="card-title mt-2">Lojas</h5>
                        <p class="card-text text-muted small">Gerenciar filiais</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/admin/usuarios" class="text-decoration-none">
                <div class="card h-100 border-success">
                    <div class="card-body text-center py-4">
                        <div style="font-size:2.5rem;">👥</div>
                        <h5 class="card-title mt-2">Usuários</h5>
                        <p class="card-text text-muted small">Contas e permissões</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/admin/auditoria" class="text-decoration-none">
                <div class="card h-100 border-success">
                    <div class="card-body text-center py-4">
                        <div style="font-size:2.5rem;">📋</div>
                        <h5 class="card-title mt-2">Auditoria</h5>
                        <p class="card-text text-muted small">Logs de ações</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="alert alert-warning mt-4 small">
        ⏳ Dashboard completo (BI consolidado + métricas) implementado na <strong>Fase 7</strong>.
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
