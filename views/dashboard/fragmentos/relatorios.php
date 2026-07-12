<?php /* Fragmento: Hub de Relatórios */ ?>

<div class="frag-wrap px-4 py-3">
    <h4 class="fw-bold text-verde mb-1">📊 Relatórios</h4>
    <p class="text-muted small mb-4">Selecione um relatório para visualizar ou imprimir.</p>

    <div class="row g-3">

        <!-- Estoque Crítico (abre em nova aba para impressão) -->
        <div class="col-md-4">
            <a href="<?= BASE_PATH ?>/gerencia/relatorio-estoque-critico"
               target="_blank"
               class="card text-decoration-none h-100 border-danger hover-card-frag">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">🔴</div>
                    <div>
                        <h6 class="fw-bold text-danger mb-1">Estoque Crítico</h6>
                        <p class="text-muted small mb-0">
                            Produtos abaixo do mínimo. Lista de compra imprimível, com quantidade sugerida editável.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-danger text-danger small">
                    Abre em nova aba para impressão →
                </div>
            </a>
        </div>

        <!-- Vendas por Período -->
        <div class="col-md-4">
            <div class="card h-100 border-success hover-card-frag cursor-pointer"
                 onclick="relNavAba('vendas_rel')">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">📈</div>
                    <div>
                        <h6 class="fw-bold text-success mb-1">Vendas por Período</h6>
                        <p class="text-muted small mb-0">
                            Faturamento, ticket médio e produtos mais vendidos.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-success text-success small fw-semibold">
                    Abrir relatório →
                </div>
            </div>
        </div>

        <!-- Indicadores & Gráficos -->
        <div class="col-md-4">
            <div class="card h-100 border-primary hover-card-frag cursor-pointer"
                 onclick="relNavAba('bi')">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">📉</div>
                    <div>
                        <h6 class="fw-bold text-primary mb-1">Indicadores & Gráficos</h6>
                        <p class="text-muted small mb-0">
                            Top produtos, horários de pico, desempenho por loja e tendências.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-primary text-primary small fw-semibold">
                    Abrir indicadores →
                </div>
            </div>
        </div>

        <!-- DRE Simplificado -->
        <div class="col-md-4">
            <div class="card h-100 border-warning hover-card-frag cursor-pointer"
                 onclick="relNavAba('dre')">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">💰</div>
                    <div>
                        <h6 class="fw-bold mb-1" style="color:#b45309;">DRE Simplificado</h6>
                        <p class="text-muted small mb-0">
                            Receita, custo de mercadoria e margem por período.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-warning small fw-semibold" style="color:#b45309;">
                    Abrir DRE →
                </div>
            </div>
        </div>

        <!-- Quebras & Perdas -->
        <div class="col-md-4">
            <div class="card h-100 border-secondary hover-card-frag cursor-pointer"
                 onclick="relNavAba('quebras_r')">
                <div class="card-body d-flex gap-3 align-items-start">
                    <div class="fs-2">⚠️</div>
                    <div>
                        <h6 class="fw-bold text-secondary mb-1">Quebras & Perdas</h6>
                        <p class="text-muted small mb-0">
                            Histórico consolidado de perdas com impacto financeiro estimado.
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-secondary text-secondary small fw-semibold">
                    Abrir quebras →
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.text-verde { color: #2e7d32; }
.hover-card-frag { transition: transform .15s, box-shadow .15s; }
.hover-card-frag:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.12); }
.cursor-pointer { cursor: pointer; }
</style>

<script>
function relNavAba(aba) {
    const btn = document.querySelector(`[data-modulo="relatorios"][data-aba="${aba}"]`);
    if (btn) btn.click();
}
</script>
