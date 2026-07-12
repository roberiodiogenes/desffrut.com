<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();

$roles_permitidos = ['gerente', 'super_admin'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$titulo_pagina  = 'Relatórios';
$mostrar_sacola = false;
require_once __DIR__ . '/../../app/views/layout/header.php';
?>

<div class="container py-4">

    <h2 class="fw-bold text-verde mb-1">📊 Relatórios</h2>
    <p class="text-muted mb-4">Análises e impressos para gestão da loja.</p>

    <div class="row g-3">

        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/gerencia/relatorio-estoque-critico"
               class="card text-decoration-none h-100 border-danger hover-card">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">🔴</div>
                    <div>
                        <h5 class="fw-bold text-danger mb-1">Estoque Crítico</h5>
                        <p class="text-muted small mb-0">
                            Produtos abaixo do mínimo. Gera lista de compra imprimível.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-danger text-danger small">
                    Clique para visualizar / imprimir →
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-secondary opacity-50">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">📈</div>
                    <div>
                        <h5 class="fw-bold text-secondary mb-1">Vendas por Período</h5>
                        <p class="text-muted small mb-0">
                            Resumo de vendas, ticket médio e produtos mais vendidos.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent small text-secondary">
                    Disponível na Fase 5 (PDV/Caixa)
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-secondary opacity-50">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">💰</div>
                    <div>
                        <h5 class="fw-bold text-secondary mb-1">DRE Simplificado</h5>
                        <p class="text-muted small mb-0">
                            Receita, custo de mercadoria e margem por período.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent small text-secondary">
                    Disponível na Fase 7 (ERP/Financeiro)
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-secondary opacity-50">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">⚠️</div>
                    <div>
                        <h5 class="fw-bold text-secondary mb-1">Quebras & Avarias</h5>
                        <p class="text-muted small mb-0">
                            Histórico de perdas com motivo e impacto financeiro.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent small text-secondary">
                    Disponível na Fase 4 (Relatórios Avançados)
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-secondary opacity-50">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">🏆</div>
                    <div>
                        <h5 class="fw-bold text-secondary mb-1">Clientes Fidelidade</h5>
                        <p class="text-muted small mb-0">
                            Ranking de pontos e compradores frequentes.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent small text-secondary">
                    Disponível na Fase 6 (Pedidos Online)
                </div>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>

<style>
.hover-card { transition: transform .15s, box-shadow .15s; }
.hover-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.12); }
</style>
