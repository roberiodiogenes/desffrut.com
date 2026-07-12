<?php
/**
 * Desffrut — Fragmento Dashboard: Módulo Caixa (rev.3)
 * Abas: Fechamentos do Dia | Sangrias & Suprimentos | Resumo por Período
 *
 * Melhorias rev.3:
 *   1. Gráfico de barras diário (Chart.js) no Resumo por Período
 *   2. Exportação CSV em Fechamentos e Resumo
 *   3. Delta comparativo vs período anterior nos KPIs do Resumo
 *   4. Breakdown por forma de pagamento nos Fechamentos
 *   5. Banner de alerta para turnos prolongados (caixas abertos há muito tempo)
 */

$aba_atual       = $_GET['a'] ?? 'fech';
$role            = $u['role'];
$loja_id_u       = (int) ($u['loja_id'] ?? 0);
$somente_leitura = in_array($role, ['gerente', 'rh_financeiro']);
// Gerente é responsável por todas as lojas (não há um gerente por loja) — tem a
// mesma visão multi-loja do super_admin, então também vê o filtro/seletor de loja.
$eh_admin        = in_array($role, ['super_admin', 'gerente'], true);

$lojas_cx = db()->query("SELECT id, nome FROM lojas WHERE ativo=1 ORDER BY nome")->fetchAll();

// Tolerância de quebra de caixa (arredondamento de troco em dinheiro físico).
// Configurada globalmente pelo dono em `configuracoes` (chave 'tolerancia_quebra_caixa').
// Diferenças de fechamento dentro dessa faixa não contam como falta/sobra.
$eh_dono = ($role === 'super_admin');
$tol_stmt = db()->prepare("SELECT valor FROM configuracoes WHERE chave = 'tolerancia_quebra_caixa'");
$tol_stmt->execute();
$tolerancia_quebra = (float) ($tol_stmt->fetchColumn() ?: 3.00);
?>

<style data-frag="caixa">

/* ── Container ── */
.cx-wrap { padding:0; }

/* ── Alerta turno prolongado ── */
.cx-alerta-prolongado {
    display:flex; align-items:flex-start; gap:10px;
    background:#fffbeb; border:1.5px solid #fbbf24; border-radius:10px;
    padding:10px 14px; margin-bottom:4px; font-size:.82rem;
}
.cx-alerta-prolongado .cx-alerta-icon { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
.cx-alerta-prolongado strong { color:#92400e; }
.cx-alerta-prolongado span   { color:#78350f; }

/* ── Tabs ── */
.cx-tabs     { display:flex; gap:2px; border-bottom:2px solid #e0e0e0; margin-bottom:20px; overflow-x:auto; }
.cx-tab      { background:none; border:none; padding:9px 18px; font-size:.85rem;
               color:#666; cursor:pointer; border-bottom:2px solid transparent;
               margin-bottom:-2px; white-space:nowrap; font-weight:500; transition:color .12s; }
.cx-tab:hover  { color:#2e7d32; }
.cx-tab.active { color:#2e7d32; border-bottom-color:#2e7d32; font-weight:700; }
.cx-tab-panel        { display:none; }
.cx-tab-panel.active { display:block; }

/* ── Barra de filtros ── */
.cx-filtros {
    display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap;
    background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px;
    padding:12px 16px; margin-bottom:20px;
}
.cx-filtros label {
    font-size:.72rem; font-weight:700; color:#6b7280;
    text-transform:uppercase; letter-spacing:.3px;
    margin-bottom:3px; display:block;
}
.cx-filtros input, .cx-filtros select {
    border:1.5px solid #e0e0e0; border-radius:7px;
    padding:6px 10px; font-size:.83rem; color:#333; background:#fff;
}
.cx-filtros input:focus, .cx-filtros select:focus { border-color:#2e7d32; outline:none; }

/* ── KPI bar ── */
.cx-kpi-bar  { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.cx-kpi      {
    flex:1; min-width:110px; background:#fff;
    border:1px solid #e5e7eb; border-radius:10px;
    padding:14px 16px; position:relative; overflow:hidden;
    transition:box-shadow .15s;
}
.cx-kpi:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
.cx-kpi::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background:var(--cx-accent,#e0e0e0);
}
.cx-kpi.verde    { --cx-accent:#16a34a; }
.cx-kpi.vermelho { --cx-accent:#dc2626; }
.cx-kpi.azul     { --cx-accent:#2563eb; }
.cx-kpi.amarelo  { --cx-accent:#d97706; }
.cx-kpi.cinza    { --cx-accent:#9ca3af; }

.cx-kpi-num           { font-size:1.18rem; font-weight:800; color:#111; line-height:1.1; margin-bottom:2px; }
.cx-kpi-num.verde     { color:#16a34a; }
.cx-kpi-num.vermelho  { color:#dc2626; }
.cx-kpi-num.azul      { color:#2563eb; }
.cx-kpi-num.amarelo   { color:#d97706; }
.cx-kpi-label { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#9ca3af; }
.cx-kpi-sub   { font-size:.68rem; color:#6b7280; margin-top:2px; }
.cx-kpi-delta { font-size:.68rem; margin-top:4px; font-weight:600; }
.cx-kpi-delta.up   { color:#16a34a; }
.cx-kpi-delta.down { color:#dc2626; }
.cx-kpi-delta.neu  { color:#9ca3af; }

/* ── Cards por loja ── */
.cx-lojas-row  { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.cx-loja-card  {
    flex:1; min-width:160px; max-width:280px;
    background:#fff; border:1.5px solid #e5e7eb; border-radius:10px;
    padding:14px 16px 12px; position:relative;
}
.cx-loja-card::before {
    content:''; position:absolute; left:0; top:12px; bottom:12px; width:3px;
    background:#2e7d32; border-radius:0 3px 3px 0;
}
.cx-loja-nome  { font-size:.84rem; font-weight:700; color:#1b5e20; margin-bottom:10px; padding-left:8px; }
.cx-loja-grid  { display:grid; grid-template-columns:1fr 1fr 1fr; gap:4px; }
.cx-loja-mini  { text-align:center; }
.cx-loja-mini-val         { font-size:.83rem; font-weight:700; color:#212529; }
.cx-loja-mini-val.verde   { color:#16a34a; }
.cx-loja-mini-val.vermelho{ color:#dc2626; }
.cx-loja-mini-lbl { font-size:.58rem; color:#9ca3af; text-transform:uppercase; }
.cx-loja-progress { margin-top:8px; height:4px; background:#e5e7eb; border-radius:2px; overflow:hidden; }
.cx-loja-progress-bar { height:100%; background:#16a34a; border-radius:2px; transition:width .5s; }
.cx-loja-pct  { font-size:.63rem; color:#9ca3af; text-align:right; margin-top:2px; }

/* ── Formas de pagamento ── */
.cx-pag-row   { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.cx-pag-chip  {
    display:flex; flex-direction:column; align-items:center;
    padding:10px 14px; border-radius:10px; border:1.5px solid var(--pag-cor,#e5e7eb);
    background:var(--pag-bg,#f9fafb); min-width:100px; flex:1;
}
.cx-pag-icon  { font-size:1.3rem; line-height:1; margin-bottom:3px; }
.cx-pag-label { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.3px; color:#6b7280; }
.cx-pag-total { font-size:.9rem; font-weight:800; color:var(--pag-cor,#333); }
.cx-pag-pct   { font-size:.65rem; color:#9ca3af; }
/* Barra proporcional interna */
.cx-pag-bar-wrap { width:100%; height:3px; background:#e5e7eb; border-radius:2px; margin-top:6px; overflow:hidden; }
.cx-pag-bar      { height:100%; background:var(--pag-cor,#9ca3af); border-radius:2px; transition:width .5s; }

/* ── Gráfico ── */
.cx-chart-wrap {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    padding:16px; margin-bottom:20px;
}

/* ── Título de seção ── */
.cx-section-title {
    font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
    color:#9ca3af; margin-bottom:10px; padding-bottom:5px; border-bottom:1px solid #e5e7eb;
}

/* ── Tabelas ── */
.cx-table-wrap { overflow-x:auto; border:1px solid #e5e7eb; border-radius:10px; }
.cx-table { width:100%; border-collapse:collapse; font-size:.81rem; }
.cx-table thead th {
    background:#f9fafb; padding:9px 12px; font-size:.68rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px; color:#6b7280;
    border-bottom:2px solid #e0e0e0; white-space:nowrap;
    border-right:1px solid #f0f0f0;
}
.cx-table thead th:last-child { border-right:none; }
.cx-table thead th.text-end { text-align:right; }
.cx-table tbody td { padding:9px 12px; border-bottom:1px solid #f0f0f0; vertical-align:middle; border-right:1px solid #f9f9f9; }
.cx-table tbody td:last-child { border-right:none; }
.cx-table tbody tr:last-child td { border-bottom:none; }
.cx-table tbody tr:hover td { background:#f9fafb; }
.cx-table tfoot td { background:#f9fafb; font-weight:700; border-top:2px solid #e0e0e0; padding:9px 12px; }
.cx-badge { padding:3px 8px; border-radius:12px; font-size:.72rem; font-weight:600; display:inline-block; }
.cx-badge-sang { background:#fff3cd; color:#92400e; }
.cx-badge-sup  { background:#dbeafe; color:#1e40af; }
.cx-badge-aberto  { background:#dbeafe; color:#1e40af; }
.cx-badge-fechado { background:#e5e7eb; color:#374151; }

/* Delta na tabela */
.cx-delta     { font-size:.72rem; font-weight:600; white-space:nowrap; }
.cx-delta.up  { color:#16a34a; }
.cx-delta.dn  { color:#dc2626; }
.cx-delta.neu { color:#9ca3af; }

/* ── Vazio ── */
.cx-empty { text-align:center; color:#9ca3af; padding:48px 20px; font-size:.85rem; }

/* Mobile */
@media (max-width:600px) {
    .cx-kpi { min-width:calc(50% - 6px); }
    .cx-loja-card { min-width:100%; max-width:none; }
    .cx-pag-chip  { min-width:calc(50% - 4px); }
}

/* Print */
@media print {
    .cx-filtros, .cx-tabs, #cx-alerta-wrap, .cx-chart-wrap, .btn { display:none !important; }
    .cx-table-wrap { border:none; overflow:visible; }
}
</style>

<!-- ── Alertas turno prolongado (preenchidos por JS) ── -->
<div id="cx-alerta-wrap" style="margin-bottom:12px;"></div>

<div class="cx-wrap">

    <!-- ── Tabs ──────────────────────────────────────────────────────────────── -->
    <div class="cx-tabs">
        <button class="cx-tab <?= $aba_atual === 'fech'   ? 'active' : '' ?>" data-tab="fech"
                onclick="cxMostrarAba('fech')">🧾 Fechamentos do Dia</button>
        <button class="cx-tab <?= $aba_atual === 'sang'   ? 'active' : '' ?>" data-tab="sang"
                onclick="cxMostrarAba('sang')">💸 Sangrias & Suprimentos</button>
        <button class="cx-tab <?= $aba_atual === 'resumo' ? 'active' : '' ?>" data-tab="resumo"
                onclick="cxMostrarAba('resumo')">📅 Resumo por Período</button>
        <button class="cx-tab <?= $aba_atual === 'hist'   ? 'active' : '' ?>" data-tab="hist"
                onclick="cxMostrarAba('hist')">🔍 Abertura &amp; Fechamento</button>
    </div>

    <?php if ($somente_leitura): ?>
    <div class="alert alert-info py-2 mb-3" style="font-size:.82rem;">
        👁️ <strong>Modo leitura.</strong> Consulta disponível para o seu perfil.
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ABA: FECHAMENTOS                                                       -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="cx-panel-fech" class="cx-tab-panel <?= $aba_atual === 'fech' ? 'active' : '' ?>">

        <div class="cx-filtros">
            <?php if ($eh_admin): ?>
            <div>
                <label>Loja</label>
                <select id="cx-fech-loja">
                    <option value="">Todas as lojas</option>
                    <?php foreach ($lojas_cx as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label>De</label>
                <input type="date" id="cx-fech-de" value="<?= date('Y-m-01') ?>">
            </div>
            <div>
                <label>Até</label>
                <input type="date" id="cx-fech-ate" value="<?= date('Y-m-d') ?>">
            </div>
            <div style="align-self:flex-end;display:flex;gap:6px;">
                <button class="btn btn-success btn-sm px-4" onclick="cxCarregarFechamentos()">🔍 Filtrar</button>
                <button id="cx-fech-csv-btn" class="btn btn-outline-secondary btn-sm" style="display:none;"
                        onclick="cxExportarFechamentosCSV()">⬇️ CSV</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">🖨️</button>
            </div>
        </div>

        <!-- KPIs Globais -->
        <div id="cx-fech-kpis" style="display:none;">
            <div class="cx-section-title">Totais do período</div>
            <div class="cx-kpi-bar">
                <div class="cx-kpi verde">
                    <div class="cx-kpi-num verde" id="kpi-fat">—</div>
                    <div class="cx-kpi-label">Faturamento</div>
                    <div class="cx-kpi-sub" id="kpi-fat-sub"></div>
                </div>
                <div class="cx-kpi vermelho">
                    <div class="cx-kpi-num vermelho" id="kpi-sang">—</div>
                    <div class="cx-kpi-label">Sangrias</div>
                </div>
                <div class="cx-kpi azul">
                    <div class="cx-kpi-num azul" id="kpi-sup">—</div>
                    <div class="cx-kpi-label">Suprimentos</div>
                </div>
                <div class="cx-kpi verde" id="kpi-liq-card">
                    <div class="cx-kpi-num verde" id="kpi-liq">—</div>
                    <div class="cx-kpi-label">Líquido</div>
                    <div class="cx-kpi-sub" style="font-size:.65rem;">Fat − Sangrias</div>
                </div>
                <div class="cx-kpi cinza">
                    <div class="cx-kpi-num" id="kpi-turnos">—</div>
                    <div class="cx-kpi-label">Turnos</div>
                </div>
                <div class="cx-kpi cinza">
                    <div class="cx-kpi-num" id="kpi-vendas">—</div>
                    <div class="cx-kpi-label">Vendas</div>
                </div>
                <div class="cx-kpi amarelo">
                    <div class="cx-kpi-num amarelo" id="kpi-ticket">—</div>
                    <div class="cx-kpi-label">Ticket Médio</div>
                </div>
            </div>

            <!-- Formas de pagamento -->
            <div id="cx-fech-pagamentos" style="display:none;">
                <div class="cx-section-title">Formas de pagamento</div>
                <div class="cx-pag-row" id="cx-pag-chips"></div>
            </div>

            <!-- Cards por loja (super_admin + "Todas") -->
            <div id="cx-lojas-row-wrap" style="display:none;">
                <div class="cx-section-title">Por filial</div>
                <div class="cx-lojas-row" id="cx-lojas-row"></div>
            </div>

            <div class="cx-section-title">Detalhe por turno</div>
        </div>

        <div id="cx-fech-tabela">
            <div class="cx-empty">Selecione o período e clique em <strong>Filtrar</strong>.</div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ABA: SANGRIAS & SUPRIMENTOS                                            -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="cx-panel-sang" class="cx-tab-panel <?= $aba_atual === 'sang' ? 'active' : '' ?>">

        <div class="cx-filtros">
            <?php if ($eh_admin): ?>
            <div>
                <label>Loja</label>
                <select id="cx-sang-loja">
                    <option value="">Todas as lojas</option>
                    <?php foreach ($lojas_cx as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label>Tipo</label>
                <select id="cx-sang-tipo">
                    <option value="">Todos</option>
                    <option value="sangria">Sangria</option>
                    <option value="suprimento">Suprimento</option>
                </select>
            </div>
            <div>
                <label>De</label>
                <input type="date" id="cx-sang-de" value="<?= date('Y-m-01') ?>">
            </div>
            <div>
                <label>Até</label>
                <input type="date" id="cx-sang-ate" value="<?= date('Y-m-d') ?>">
            </div>
            <div style="align-self:flex-end;">
                <button class="btn btn-success btn-sm px-4" onclick="cxCarregarSangrias()">🔍 Filtrar</button>
            </div>
        </div>

        <div id="cx-sang-kpis" style="display:none;">
            <div class="cx-kpi-bar" style="margin-bottom:20px;">
                <div class="cx-kpi vermelho">
                    <div class="cx-kpi-num vermelho" id="kpi-sang-total">—</div>
                    <div class="cx-kpi-label">Total Sangrias</div>
                </div>
                <div class="cx-kpi azul">
                    <div class="cx-kpi-num azul" id="kpi-sup-total">—</div>
                    <div class="cx-kpi-label">Total Suprimentos</div>
                </div>
                <div class="cx-kpi verde" id="kpi-saldo-card">
                    <div class="cx-kpi-num verde" id="kpi-saldo-mov">—</div>
                    <div class="cx-kpi-label">Saldo líquido</div>
                    <div class="cx-kpi-sub" style="font-size:.65rem;">Sup − Sang</div>
                </div>
                <div class="cx-kpi cinza">
                    <div class="cx-kpi-num" id="kpi-sang-qtd">—</div>
                    <div class="cx-kpi-label">Movimentos</div>
                </div>
            </div>
        </div>

        <div id="cx-sang-tabela">
            <div class="cx-empty">Selecione o período e clique em <strong>Filtrar</strong>.</div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ABA: RESUMO POR PERÍODO                                                -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="cx-panel-resumo" class="cx-tab-panel <?= $aba_atual === 'resumo' ? 'active' : '' ?>">

        <div class="cx-filtros">
            <?php if ($eh_admin): ?>
            <div>
                <label>Loja</label>
                <select id="cx-res-loja">
                    <option value="">Todas as lojas</option>
                    <?php foreach ($lojas_cx as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label>De</label>
                <input type="date" id="cx-res-de" value="<?= date('Y-m-01') ?>">
            </div>
            <div>
                <label>Até</label>
                <input type="date" id="cx-res-ate" value="<?= date('Y-m-d') ?>">
            </div>
            <div style="align-self:flex-end;display:flex;gap:6px;flex-wrap:wrap;">
                <button class="btn btn-success btn-sm px-4" onclick="cxCarregarResumo()">🔍 Filtrar</button>
                <button id="cx-res-csv-btn" class="btn btn-outline-secondary btn-sm" style="display:none;"
                        onclick="cxExportarResumoCSV()">⬇️ CSV</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="cxAtalhoResumo(7)">7d</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="cxAtalhoResumo(15)">15d</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="cxAtalhoResumo(30)">30d</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="cxAtalhoResumo(0)">Mês</button>
            </div>
        </div>

        <div id="cx-res-area">
            <div class="cx-empty">Selecione o período e clique em <strong>Filtrar</strong>.</div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ABA: ABERTURA & FECHAMENTO (AUDITORIA)                                 -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="cx-panel-hist" class="cx-tab-panel <?= $aba_atual === 'hist' ? 'active' : '' ?>">

        <div class="alert alert-secondary py-2 mb-3" style="font-size:.8rem;">
            🔍 Histórico de quem abriu e quem fechou cada caixa, com a diferença entre o valor contado
            fisicamente no fechamento e o valor esperado em dinheiro (fundo + vendas em dinheiro + suprimentos − sangrias).
            Útil para apurar caixas com sobra ou falta.
        </div>

        <!-- Tolerância de quebra de caixa (arredondamento de troco em dinheiro) -->
        <div class="cx-tolerancia-card" style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;
             padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span style="font-size:1.1rem;">🎯</span>
            <div style="flex:1;min-width:220px;">
                <div style="font-weight:600;font-size:.85rem;color:#333;">Tolerância de quebra de caixa</div>
                <div style="font-size:.76rem;color:#777;">
                    Diferenças de até esse valor (pra mais ou pra menos) são tratadas como arredondamento
                    normal do troco em dinheiro — não aparecem como alerta de falta/sobra.
                </div>
            </div>
            <?php if ($eh_dono): ?>
            <div style="display:flex;align-items:center;gap:6px;">
                <span style="font-size:.82rem;color:#555;">R$</span>
                <input type="number" id="cx-tolerancia-input" value="<?= number_format($tolerancia_quebra, 2, '.', '') ?>"
                       step="0.01" min="0" style="width:90px;padding:5px 8px;border:1px solid #ccc;border-radius:6px;font-size:.85rem;">
                <button class="btn btn-success btn-sm" onclick="cxSalvarTolerancia()">Salvar</button>
            </div>
            <?php else: ?>
            <div style="font-weight:700;color:#2e7d32;font-size:.95rem;">
                R$ <?= number_format($tolerancia_quebra, 2, ',', '.') ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="cx-filtros">
            <?php if ($eh_admin): ?>
            <div>
                <label>Loja</label>
                <select id="cx-hist-loja">
                    <option value="">Todas as lojas</option>
                    <?php foreach ($lojas_cx as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label>Status</label>
                <select id="cx-hist-status">
                    <option value="">Todos</option>
                    <option value="aberto">Aberto</option>
                    <option value="fechado">Fechado</option>
                </select>
            </div>
            <div>
                <label>Usuário (abriu ou fechou)</label>
                <input type="text" id="cx-hist-busca" placeholder="Nome do funcionário…" style="min-width:180px;">
            </div>
            <div>
                <label>De</label>
                <input type="date" id="cx-hist-de" value="<?= date('Y-m-01') ?>">
            </div>
            <div>
                <label>Até</label>
                <input type="date" id="cx-hist-ate" value="<?= date('Y-m-d') ?>">
            </div>
            <div style="align-self:flex-end;display:flex;gap:6px;">
                <button class="btn btn-success btn-sm px-4" onclick="cxCarregarHistorico()">🔍 Filtrar</button>
                <button id="cx-hist-csv-btn" class="btn btn-outline-secondary btn-sm" style="display:none;"
                        onclick="cxExportarHistoricoCSV()">⬇️ CSV</button>
            </div>
        </div>

        <div id="cx-hist-kpis" style="display:none;">
            <div class="cx-kpi-bar">
                <div class="cx-kpi cinza">
                    <div class="cx-kpi-num" id="kpi-hist-total">—</div>
                    <div class="cx-kpi-label">Caixas no período</div>
                </div>
                <div class="cx-kpi azul">
                    <div class="cx-kpi-num azul" id="kpi-hist-abertos">—</div>
                    <div class="cx-kpi-label">Atualmente abertos</div>
                </div>
                <div class="cx-kpi vermelho" id="kpi-hist-falta-card">
                    <div class="cx-kpi-num vermelho" id="kpi-hist-falta">—</div>
                    <div class="cx-kpi-label">Caixas com falta</div>
                </div>
                <div class="cx-kpi amarelo" id="kpi-hist-sobra-card">
                    <div class="cx-kpi-num amarelo" id="kpi-hist-sobra">—</div>
                    <div class="cx-kpi-label">Caixas com sobra</div>
                </div>
            </div>
        </div>

        <div id="cx-hist-tabela">
            <div class="cx-empty">Selecione os filtros e clique em <strong>Filtrar</strong>.</div>
        </div>
    </div>

</div><!-- /cx-wrap -->

<script>
window.moduloCaixaUI = (function () {
    const API    = APP.api;
    const token  = () => sessionStorage.getItem('desffrut_token') || '';
    const fmtBRL = v => 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const fmtDT  = s => s ? new Date(s).toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '—';
    const escAttr = s => String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const diasSem = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    const TOLERANCIA_QUEBRA = <?= json_encode($tolerancia_quebra) ?>;

    window.cxSalvarTolerancia = async function () {
        const input = document.getElementById('cx-tolerancia-input');
        const valor = parseFloat(input.value);
        if (isNaN(valor) || valor < 0) { alert('Informe um valor válido (>= 0).'); return; }
        try {
            const r = await fetch(`${API}/configuracoes`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token() },
                body: JSON.stringify({ chave: 'tolerancia_quebra_caixa', valor: valor.toFixed(2) }),
            });
            const j = await r.json();
            if (j.status === 'ok') {
                alert('Tolerância salva! A página será recarregada para aplicar o novo valor.');
                location.reload();
            } else {
                alert(j.message || 'Erro ao salvar.');
            }
        } catch (e) { alert('Erro ao salvar: ' + e.message); }
    };
    const corLiq  = v => parseFloat(v) >= 0 ? '#16a34a' : '#dc2626';

    // Dados em memória para CSV
    let _dadosFech  = [];
    let _dadosResumo = { datas: [], byDate: {}, prevByDate: {} };
    let _dadosHist  = [];

    // ── Formas de pagamento: config visual ──────────────────────────────────
    const PAG_CFG = {
        dinheiro: { label: 'Dinheiro',    icon: '💵', cor: '#16a34a', bg: '#f0fdf4' },
        debito:   { label: 'Débito',      icon: '💳', cor: '#2563eb', bg: '#eff6ff' },
        credito:  { label: 'Crédito',     icon: '💳', cor: '#7c3aed', bg: '#f5f3ff' },
        pix:      { label: 'Pix',         icon: '⚡', cor: '#0891b2', bg: '#ecfeff' },
        pontos:   { label: 'Pontos',      icon: '⭐', cor: '#d97706', bg: '#fffbeb' },
        misto:    { label: 'Misto',       icon: '🔀', cor: '#6b7280', bg: '#f9fafb' },
    };

    // ── Troca de aba ──────────────────────────────────────────────────────────
    window.cxMostrarAba = function (id) {
        document.querySelectorAll('.cx-tab').forEach(t =>
            t.classList.toggle('active', t.dataset.tab === id));
        document.querySelectorAll('.cx-tab-panel').forEach(p =>
            p.classList.toggle('active', p.id === 'cx-panel-' + id));
    };

    // ── Utilitário: calcula delta entre dois valores ──────────────────────────
    function calcDelta(atual, anterior) {
        if (!anterior || anterior === 0) return null;
        return ((atual - anterior) / Math.abs(anterior)) * 100;
    }

    function htmlDeltaKpi(pct) {
        if (pct === null) return '<div class="cx-kpi-delta neu">— sem dado anterior</div>';
        const cls  = pct > 0 ? 'up' : pct < 0 ? 'down' : 'neu';
        const seta = pct > 0 ? '↑' : pct < 0 ? '↓' : '→';
        return `<div class="cx-kpi-delta ${cls}">${seta} ${pct > 0 ? '+' : ''}${pct.toFixed(1)}% vs anterior</div>`;
    }

    // ── Utilitário: CSV download ──────────────────────────────────────────────
    function baixarCSV(headers, rows, filename) {
        const sep = ';';
        const linhas = [headers.join(sep), ...rows.map(r => r.map(c => {
            const s = String(c ?? '').replace(/"/g, '""');
            return s.includes(sep) || s.includes('\n') ? `"${s}"` : s;
        }).join(sep))];
        const blob = new Blob(['﻿' + linhas.join('\n')], {type: 'text/csv;charset=utf-8;'});
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = filename; a.click();
        URL.revokeObjectURL(url);
    }

    window.cxExportarFechamentosCSV = function () {
        if (!_dadosFech.length) return;
        const headers = ['Data','Loja','Operador','Abertura','Fechamento','Fundo (R$)','Vendas','Faturamento (R$)','Sangrias (R$)','Liquido (R$)'];
        const rows = _dadosFech.map(d => {
            const fat  = parseFloat(d.total_vendas   || 0);
            const sang = parseFloat(d.total_sangrias  || 0);
            const liq  = fat - sang;
            const dt   = d.aberto_em ? new Date(d.aberto_em).toLocaleDateString('pt-BR') : '';
            return [dt, d.loja_nome||'', d.operador_nome||'', fmtDT(d.aberto_em), fmtDT(d.fechado_em),
                    parseFloat(d.fundo_troco||0).toFixed(2), d.qtd_vendas,
                    fat.toFixed(2), sang.toFixed(2), liq.toFixed(2)];
        });
        const de  = document.getElementById('cx-fech-de').value;
        const ate = document.getElementById('cx-fech-ate').value;
        baixarCSV(headers, rows, `fechamentos_${de}_${ate}.csv`);
    };

    window.cxExportarHistoricoCSV = function () {
        if (!_dadosHist.length) return;
        const headers = ['Loja','Status','Abriu','Aberto em','Justificativa Abertura','Fechou','Fechado em','Justificativa Fechamento','Fundo (R$)','Contado (R$)','Esperado (R$)','Diferença (R$)','Dentro da Tolerância'];
        const rows = _dadosHist.map(d => [
            d.loja_nome || '', d.status === 'aberto' ? 'Aberto' : 'Fechado',
            d.abriu_nome || '', fmtDT(d.aberto_em), d.justificativa_abertura || '',
            d.fechou_nome || '—', fmtDT(d.fechado_em), d.justificativa_fechamento || '',
            parseFloat(d.fundo_troco || 0).toFixed(2),
            d.total_contado !== null ? parseFloat(d.total_contado).toFixed(2) : '',
            parseFloat(d.esperado || 0).toFixed(2),
            d.diferenca !== null ? parseFloat(d.diferenca).toFixed(2) : '',
            d.diferenca !== null ? (d.dentro_tolerancia ? 'Sim' : 'Não') : '',
        ]);
        const de  = document.getElementById('cx-hist-de').value;
        const ate = document.getElementById('cx-hist-ate').value;
        baixarCSV(headers, rows, `abertura_fechamento_${de}_${ate}.csv`);
    };

    window.cxExportarResumoCSV = function () {
        const {datas, byDate, prevByDate} = _dadosResumo;
        if (!datas.length) return;
        const headers = ['Data','Dia','Lojas','Turnos','Vendas','Faturamento (R$)','Sangrias (R$)','Liquido (R$)','Delta (%)'];
        const rows = datas.map((dt, i) => {
            const v   = byDate[dt];
            const liq = v.fat - v.sang;
            const d   = new Date(dt + 'T00:00');
            const prev = prevByDate[i];
            const delta = prev ? calcDelta(v.fat, prev.fat) : null;
            return [d.toLocaleDateString('pt-BR'), diasSem[d.getDay()], [...v.lojas].join(', '),
                    v.turnos, v.vendas, v.fat.toFixed(2), v.sang.toFixed(2), liq.toFixed(2),
                    delta !== null ? delta.toFixed(1) + '%' : ''];
        });
        const de  = document.getElementById('cx-res-de').value;
        const ate = document.getElementById('cx-res-ate').value;
        baixarCSV(headers, rows, `resumo_${de}_${ate}.csv`);
    };

    // ── Chart.js — carregamento dinâmico ──────────────────────────────────────
    function loadChartJs(cb) {
        if (window.Chart) { cb(); return; }
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js';
        s.onload = cb;
        document.head.appendChild(s);
    }

    let _chartFat = null;
    function renderizarGrafico(datas, byDate) {
        const wrap = document.getElementById('cx-chart-wrap');
        if (!wrap) return;
        wrap.style.display = '';

        loadChartJs(() => {
            const ctx = document.getElementById('cx-chart-fat').getContext('2d');
            if (_chartFat) { _chartFat.destroy(); _chartFat = null; }

            const labels  = datas.map(dt => {
                const d = new Date(dt + 'T00:00');
                return d.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'});
            });
            const fatData = datas.map(dt => byDate[dt].fat);
            const sangData = datas.map(dt => byDate[dt].sang);

            _chartFat = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Faturamento',
                            data: fatData,
                            backgroundColor: 'rgba(22,163,74,.75)',
                            borderColor: '#16a34a',
                            borderWidth: 1,
                            borderRadius: 4,
                            order: 1,
                        },
                        {
                            label: 'Sangrias',
                            data: sangData,
                            backgroundColor: 'rgba(220,38,38,.5)',
                            borderColor: '#dc2626',
                            borderWidth: 1,
                            borderRadius: 4,
                            order: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { font: { size: 11 }, boxWidth: 14 }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` ${ctx.dataset.label}: ${fmtBRL(ctx.raw)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => 'R$ ' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v.toFixed(0))
                            },
                            grid: { color: '#f0f0f0' }
                        },
                        x: {
                            ticks: { maxRotation: 45, font: { size: 10 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        });
    }

    // ── Feature 5: Alerta turno prolongado ───────────────────────────────────
    async function cxVerificarAbertos() {
        const wrap = document.getElementById('cx-alerta-wrap');
        if (!wrap) return;
        try {
            const r = await fetch(`${API}/caixas/abertos`, {headers: {'Authorization': 'Bearer ' + token()}});
            const j = await r.json();
            const abertos = j.data || [];
            const LIMITE_H = 10; // horas para considerar prolongado
            const prolongados = abertos.filter(c => parseInt(c.horas_aberto || 0) >= LIMITE_H);
            if (!prolongados.length) { wrap.innerHTML = ''; return; }

            wrap.innerHTML = prolongados.map(c => {
                const h   = parseInt(c.horas_aberto || 0);
                const min = parseInt(c.minutos_aberto || 0) % 60;
                const tempo = h > 0 ? `${h}h${min > 0 ? min + 'min' : ''}` : `${min}min`;
                const abertura = c.aberto_em
                    ? new Date(c.aberto_em).toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'})
                    : '?';
                return `
                <div class="cx-alerta-prolongado">
                    <span class="cx-alerta-icon">⚠️</span>
                    <div>
                        <strong>${c.loja_nome || 'Loja ?'}</strong>
                        <span> — caixa aberto há <strong>${tempo}</strong> pelo operador
                        <strong>${c.operador_nome || '?'}</strong> (aberto às ${abertura}).
                        Faturamento acumulado: ${fmtBRL(c.total_vendas)}.</span>
                    </div>
                </div>`;
            }).join('');
        } catch (_) { /* silêncio em caso de erro */ }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ABA: FECHAMENTOS
    // ══════════════════════════════════════════════════════════════════════════
    window.cxCarregarFechamentos = async function () {
        const loja = document.getElementById('cx-fech-loja')?.value || '';
        const de   = document.getElementById('cx-fech-de').value;
        const ate  = document.getElementById('cx-fech-ate').value;
        const elT  = document.getElementById('cx-fech-tabela');
        const elK  = document.getElementById('cx-fech-kpis');
        elT.innerHTML = '<div class="cx-empty">Carregando…</div>';
        elK.style.display = 'none';
        document.getElementById('cx-fech-csv-btn').style.display = 'none';
        document.getElementById('cx-fech-pagamentos').style.display = 'none';

        const params = new URLSearchParams({data_ini: de, data_fim: ate});
        if (loja) params.set('loja_id', loja);

        try {
            const r = await fetch(`${API}/caixas/fechamentos?${params}`, {headers: {'Authorization': 'Bearer ' + token()}});
            const j = await r.json();
            _dadosFech = j.data || [];

            if (!_dadosFech.length) {
                elT.innerHTML = '<div class="cx-empty">Nenhum fechamento no período selecionado.</div>';
                return;
            }

            // ── Totais ──
            let totalFat = 0, totalSang = 0, totalSup = 0, totalVendas = 0;
            _dadosFech.forEach(d => {
                totalFat    += parseFloat(d.total_vendas      || 0);
                totalSang   += parseFloat(d.total_sangrias    || 0);
                totalSup    += parseFloat(d.total_suprimentos || 0);
                totalVendas += parseInt(d.qtd_vendas          || 0);
            });
            const totalLiq    = totalFat - totalSang;
            const ticketMedio = totalVendas > 0 ? totalFat / totalVendas : 0;

            // ── KPIs ──
            document.getElementById('kpi-fat').textContent     = fmtBRL(totalFat);
            document.getElementById('kpi-fat-sub').textContent = `${_dadosFech.length} turno(s) · ${totalVendas} vendas`;
            document.getElementById('kpi-sang').textContent    = fmtBRL(totalSang);
            document.getElementById('kpi-sup').textContent     = fmtBRL(totalSup);
            document.getElementById('kpi-liq').textContent     = fmtBRL(totalLiq);
            document.getElementById('kpi-liq').className       = 'cx-kpi-num ' + (totalLiq >= 0 ? 'verde' : 'vermelho');
            document.getElementById('kpi-liq-card').className  = 'cx-kpi ' + (totalLiq >= 0 ? 'verde' : 'vermelho');
            document.getElementById('kpi-turnos').textContent  = _dadosFech.length;
            document.getElementById('kpi-vendas').textContent  = totalVendas;
            document.getElementById('kpi-ticket').textContent  = fmtBRL(ticketMedio);
            elK.style.display = '';
            document.getElementById('cx-fech-csv-btn').style.display = '';

            // ── Cards por loja ──
            const lojasWrap = document.getElementById('cx-lojas-row-wrap');
            const lojasRow  = document.getElementById('cx-lojas-row');
            if (!loja && <?= $eh_admin ? 'true' : 'false' ?>) {
                const byLoja = {};
                _dadosFech.forEach(d => {
                    const k = d.loja_nome || '—';
                    if (!byLoja[k]) byLoja[k] = {fat: 0, sang: 0, vendas: 0, turnos: 0};
                    byLoja[k].fat    += parseFloat(d.total_vendas   || 0);
                    byLoja[k].sang   += parseFloat(d.total_sangrias || 0);
                    byLoja[k].vendas += parseInt(d.qtd_vendas       || 0);
                    byLoja[k].turnos++;
                });
                lojasRow.innerHTML = Object.entries(byLoja).map(([nome, v]) => {
                    const pct = totalFat > 0 ? Math.round((v.fat / totalFat) * 100) : 0;
                    const liq = v.fat - v.sang;
                    return `
                    <div class="cx-loja-card">
                        <div class="cx-loja-nome">🏪 ${nome}</div>
                        <div class="cx-loja-grid">
                            <div class="cx-loja-mini">
                                <div class="cx-loja-mini-val verde">${fmtBRL(v.fat)}</div>
                                <div class="cx-loja-mini-lbl">Faturamento</div>
                            </div>
                            <div class="cx-loja-mini">
                                <div class="cx-loja-mini-val vermelho">${fmtBRL(v.sang)}</div>
                                <div class="cx-loja-mini-lbl">Sangrias</div>
                            </div>
                            <div class="cx-loja-mini">
                                <div class="cx-loja-mini-val" style="color:${corLiq(liq)};">${fmtBRL(liq)}</div>
                                <div class="cx-loja-mini-lbl">Líquido</div>
                            </div>
                        </div>
                        <div style="margin-top:6px;display:flex;gap:10px;">
                            <span style="font-size:.7rem;color:#6b7280;">${v.turnos} turno(s)</span>
                            <span style="font-size:.7rem;color:#6b7280;">${v.vendas} vendas</span>
                        </div>
                        <div class="cx-loja-progress">
                            <div class="cx-loja-progress-bar" style="width:${pct}%;"></div>
                        </div>
                        <div class="cx-loja-pct">${pct}% do total</div>
                    </div>`;
                }).join('');
                lojasWrap.style.display = '';
            } else {
                lojasWrap.style.display = 'none';
            }

            // ── Tabela detalhada ──
            const tbody = _dadosFech.map(d => {
                const fat  = parseFloat(d.total_vendas   || 0);
                const sang = parseFloat(d.total_sangrias || 0);
                const liq  = fat - sang;
                const dtStr = d.aberto_em ? new Date(d.aberto_em).toLocaleDateString('pt-BR') : '—';
                return `
                <tr>
                    <td style="font-size:.76rem;color:#6b7280;white-space:nowrap;">${dtStr}</td>
                    <td style="font-weight:600;">${d.loja_nome||'—'}</td>
                    <td style="font-size:.81rem;">${d.operador_nome||'—'}</td>
                    <td style="font-size:.76rem;white-space:nowrap;">${fmtDT(d.aberto_em)}</td>
                    <td style="font-size:.76rem;white-space:nowrap;">${fmtDT(d.fechado_em)}</td>
                    <td class="text-end" style="font-size:.76rem;color:#9ca3af;">${fmtBRL(d.fundo_troco)}</td>
                    <td class="text-end">${d.qtd_vendas}</td>
                    <td class="text-end fw-semibold" style="color:#16a34a;">${fmtBRL(fat)}</td>
                    <td class="text-end" style="color:#dc2626;">${fmtBRL(sang)}</td>
                    <td class="text-end fw-semibold" style="color:${corLiq(liq)};">${fmtBRL(liq)}</td>
                </tr>`;
            }).join('');

            elT.innerHTML = `
                <div class="cx-table-wrap">
                <table class="cx-table">
                    <thead>
                        <tr>
                            <th>Data</th><th>Loja</th><th>Operador</th>
                            <th>Abertura</th><th>Fechamento</th>
                            <th class="text-end">Fundo</th><th class="text-end">Vendas</th>
                            <th class="text-end">Faturamento</th><th class="text-end">Sangrias</th>
                            <th class="text-end">Líquido</th>
                        </tr>
                    </thead>
                    <tbody>${tbody}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="text-align:right;color:#6b7280;font-size:.75rem;">TOTAL do período:</td>
                            <td class="text-end" style="color:#16a34a;">${fmtBRL(totalFat)}</td>
                            <td class="text-end" style="color:#dc2626;">${fmtBRL(totalSang)}</td>
                            <td class="text-end" style="color:${corLiq(totalLiq)};">${fmtBRL(totalLiq)}</td>
                        </tr>
                    </tfoot>
                </table>
                </div>`;

            // ── Feature 4: Formas de pagamento ──
            cxCarregarPagamentos(loja, de, ate);

        } catch (e) {
            elT.innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
        }
    };

    // ── Feature 4: Carrega formas de pagamento ───────────────────────────────
    async function cxCarregarPagamentos(loja, de, ate) {
        const el = document.getElementById('cx-fech-pagamentos');
        const chips = document.getElementById('cx-pag-chips');
        el.style.display = 'none';
        try {
            const params = new URLSearchParams({data_ini: de, data_fim: ate});
            if (loja) params.set('loja_id', loja);
            const r = await fetch(`${API}/caixas/pagamentos?${params}`, {headers: {'Authorization': 'Bearer ' + token()}});
            const j = await r.json();
            const dados = j.data || [];
            if (!dados.length) return;

            const grandTotal = dados.reduce((s, d) => s + parseFloat(d.total || 0), 0);
            chips.innerHTML = dados.map(d => {
                const cfg   = PAG_CFG[d.forma_pagamento] || PAG_CFG.misto;
                const total = parseFloat(d.total || 0);
                const pct   = grandTotal > 0 ? Math.round((total / grandTotal) * 100) : 0;
                return `
                <div class="cx-pag-chip" style="--pag-cor:${cfg.cor};--pag-bg:${cfg.bg};">
                    <span class="cx-pag-icon">${cfg.icon}</span>
                    <span class="cx-pag-label">${cfg.label}</span>
                    <span class="cx-pag-total">${fmtBRL(total)}</span>
                    <span class="cx-pag-pct">${d.qtd} venda(s) · ${pct}%</span>
                    <div class="cx-pag-bar-wrap">
                        <div class="cx-pag-bar" style="width:${pct}%;"></div>
                    </div>
                </div>`;
            }).join('');
            el.style.display = '';
        } catch (_) { /* silêncio */ }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ABA: SANGRIAS & SUPRIMENTOS
    // ══════════════════════════════════════════════════════════════════════════
    window.cxCarregarSangrias = async function () {
        const loja = document.getElementById('cx-sang-loja')?.value || '';
        const tipo = document.getElementById('cx-sang-tipo').value;
        const de   = document.getElementById('cx-sang-de').value;
        const ate  = document.getElementById('cx-sang-ate').value;
        const elT  = document.getElementById('cx-sang-tabela');
        const elK  = document.getElementById('cx-sang-kpis');
        elT.innerHTML = '<div class="cx-empty">Carregando…</div>';
        elK.style.display = 'none';

        const params = new URLSearchParams({data_ini: de, data_fim: ate});
        if (loja) params.set('loja_id', loja);
        if (tipo) params.set('tipo', tipo);

        try {
            const r = await fetch(`${API}/caixas/sangrias?${params}`, {headers: {'Authorization': 'Bearer ' + token()}});
            const j = await r.json();
            const dados = j.data || [];

            if (!dados.length) {
                elT.innerHTML = '<div class="cx-empty">Nenhum movimento no período.</div>';
                return;
            }

            let totSang = 0, totSup = 0;
            dados.forEach(d => {
                if (d.tipo === 'sangria')    totSang += parseFloat(d.valor || 0);
                if (d.tipo === 'suprimento') totSup  += parseFloat(d.valor || 0);
            });
            const saldo = totSup - totSang;
            document.getElementById('kpi-sang-total').textContent = fmtBRL(totSang);
            document.getElementById('kpi-sup-total').textContent  = fmtBRL(totSup);
            document.getElementById('kpi-saldo-mov').textContent  = fmtBRL(saldo);
            document.getElementById('kpi-saldo-mov').className    = 'cx-kpi-num ' + (saldo >= 0 ? 'verde' : 'vermelho');
            document.getElementById('kpi-saldo-card').className   = 'cx-kpi ' + (saldo >= 0 ? 'verde' : 'vermelho');
            document.getElementById('kpi-sang-qtd').textContent   = dados.length;
            elK.style.display = '';

            const tbody = dados.map(d => `
            <tr>
                <td style="font-size:.76rem;white-space:nowrap;">${fmtDT(d.created_at)}</td>
                <td style="font-weight:600;">${d.loja_nome||'—'}</td>
                <td style="font-size:.81rem;">${d.operador_nome||'—'}</td>
                <td><span class="cx-badge ${d.tipo === 'sangria' ? 'cx-badge-sang' : 'cx-badge-sup'}">
                    ${d.tipo === 'sangria' ? '💸 Sangria' : '💰 Suprimento'}
                </span></td>
                <td class="text-end fw-semibold" style="color:${d.tipo==='sangria'?'#dc2626':'#2563eb'};">
                    ${fmtBRL(d.valor)}</td>
                <td style="font-size:.76rem;color:#6b7280;">${d.justificativa||'—'}</td>
            </tr>`).join('');

            elT.innerHTML = `
                <div class="cx-table-wrap">
                <table class="cx-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th><th>Loja</th><th>Operador</th>
                            <th>Tipo</th><th class="text-end">Valor</th><th>Justificativa</th>
                        </tr>
                    </thead>
                    <tbody>${tbody}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right;color:#6b7280;font-size:.75rem;">TOTAL:</td>
                            <td class="text-end">
                                <span style="color:#dc2626;">Sang: ${fmtBRL(totSang)}</span>
                                &nbsp;|&nbsp;
                                <span style="color:#2563eb;">Sup: ${fmtBRL(totSup)}</span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                </div>`;
        } catch (e) {
            elT.innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
        }
    };

    // ══════════════════════════════════════════════════════════════════════════
    // ABA: RESUMO POR PERÍODO (+ delta + gráfico)
    // ══════════════════════════════════════════════════════════════════════════
    window.cxCarregarResumo = async function () {
        const loja = document.getElementById('cx-res-loja')?.value || '';
        const de   = document.getElementById('cx-res-de').value;
        const ate  = document.getElementById('cx-res-ate').value;
        const elA  = document.getElementById('cx-res-area');
        elA.innerHTML = '<div class="cx-empty">Carregando…</div>';
        document.getElementById('cx-res-csv-btn').style.display = 'none';

        const params = new URLSearchParams({data_ini: de, data_fim: ate});
        if (loja) params.set('loja_id', loja);

        // ── Calcula período anterior (mesma duração, imediatamente antes) ──
        const deDt  = new Date(de  + 'T00:00');
        const ateDt = new Date(ate + 'T00:00');
        const durMs = ateDt - deDt;
        const prevAteDt = new Date(deDt.getTime() - 86400000); // de - 1 dia
        const prevDeDt  = new Date(prevAteDt.getTime() - durMs);
        const fmt       = d => d.toISOString().slice(0,10);
        const prevParams = new URLSearchParams({data_ini: fmt(prevDeDt), data_fim: fmt(prevAteDt)});
        if (loja) prevParams.set('loja_id', loja);

        try {
            // Busca período atual e anterior em paralelo
            const [rAtual, rPrev] = await Promise.all([
                fetch(`${API}/caixas/fechamentos?${params}`,     {headers: {'Authorization': 'Bearer ' + token()}}),
                fetch(`${API}/caixas/fechamentos?${prevParams}`, {headers: {'Authorization': 'Bearer ' + token()}}),
            ]);
            const [jAtual, jPrev] = await Promise.all([rAtual.json(), rPrev.json()]);

            const dados     = jAtual.data || [];
            const dadosPrev = jPrev.data  || [];

            if (!dados.length) {
                elA.innerHTML = '<div class="cx-empty">Nenhum dado no período selecionado.</div>';
                return;
            }

            // ── Agrupa atual por data ──
            const byDate = {};
            dados.forEach(d => {
                const dt = (d.aberto_em||'').slice(0,10) || '—';
                if (!byDate[dt]) byDate[dt] = {fat:0, sang:0, sup:0, vendas:0, turnos:0, lojas:new Set()};
                byDate[dt].fat    += parseFloat(d.total_vendas      ||0);
                byDate[dt].sang   += parseFloat(d.total_sangrias    ||0);
                byDate[dt].sup    += parseFloat(d.total_suprimentos ||0);
                byDate[dt].vendas += parseInt(d.qtd_vendas          ||0);
                byDate[dt].turnos++;
                if (d.loja_nome) byDate[dt].lojas.add(d.loja_nome);
            });

            // ── Agrupa anterior por data (usa índice para comparação relativa) ──
            const prevByDate = {};
            const prevDatasOrdenadas = {};
            dadosPrev.forEach(d => {
                const dt = (d.aberto_em||'').slice(0,10) || '—';
                if (!prevDatasOrdenadas[dt]) prevDatasOrdenadas[dt] = {fat:0, sang:0};
                prevDatasOrdenadas[dt].fat  += parseFloat(d.total_vendas   ||0);
                prevDatasOrdenadas[dt].sang += parseFloat(d.total_sangrias ||0);
            });
            const prevDatasArr = Object.keys(prevDatasOrdenadas).sort();
            prevDatasArr.forEach((dt, i) => { prevByDate[i] = prevDatasOrdenadas[dt]; });

            const datas = Object.keys(byDate).sort();

            // ── Totais atuais ──
            let gFat = 0, gSang = 0, gSup = 0, gVendas = 0;
            datas.forEach(dt => { gFat += byDate[dt].fat; gSang += byDate[dt].sang; gSup += byDate[dt].sup; gVendas += byDate[dt].vendas; });
            const gLiq = gFat - gSang;

            // ── Totais período anterior ──
            let prevFat = 0, prevSang = 0, prevVendas = 0;
            prevDatasArr.forEach(dt => { prevFat += prevDatasOrdenadas[dt].fat; prevSang += prevDatasOrdenadas[dt].sang; });

            // ── Salva para CSV ──
            _dadosResumo = {datas, byDate, prevByDate};

            // ── Delta ──
            const deltaFat   = calcDelta(gFat,   prevFat);
            const deltaSang  = calcDelta(gSang,  prevSang);
            const deltaLiq   = calcDelta(gLiq,   prevFat - prevSang);
            const deltaTicMed = gVendas > 0 && prevVendas > 0
                ? calcDelta(gFat/gVendas, prevFat/prevVendas) : null;

            // ── KPIs com delta ──
            const kpisHtml = `
            <div class="cx-kpi-bar" style="margin-bottom:20px;">
                <div class="cx-kpi verde">
                    <div class="cx-kpi-num verde">${fmtBRL(gFat)}</div>
                    <div class="cx-kpi-label">Faturamento Total</div>
                    <div class="cx-kpi-sub">${datas.length} dia(s)</div>
                    ${htmlDeltaKpi(deltaFat)}
                </div>
                <div class="cx-kpi vermelho">
                    <div class="cx-kpi-num vermelho">${fmtBRL(gSang)}</div>
                    <div class="cx-kpi-label">Total Sangrias</div>
                    ${htmlDeltaKpi(deltaSang !== null ? -deltaSang : null)}
                </div>
                <div class="cx-kpi azul">
                    <div class="cx-kpi-num azul">${fmtBRL(gSup)}</div>
                    <div class="cx-kpi-label">Total Suprimentos</div>
                </div>
                <div class="cx-kpi ${gLiq >= 0 ? 'verde' : 'vermelho'}">
                    <div class="cx-kpi-num ${gLiq >= 0 ? 'verde' : 'vermelho'}">${fmtBRL(gLiq)}</div>
                    <div class="cx-kpi-label">Líquido</div>
                    ${htmlDeltaKpi(deltaLiq)}
                </div>
                <div class="cx-kpi cinza">
                    <div class="cx-kpi-num">${gVendas}</div>
                    <div class="cx-kpi-label">Total Vendas</div>
                </div>
                <div class="cx-kpi amarelo">
                    <div class="cx-kpi-num amarelo">${gVendas > 0 ? fmtBRL(gFat/gVendas) : '—'}</div>
                    <div class="cx-kpi-label">Ticket Médio</div>
                    ${deltaTicMed !== null ? htmlDeltaKpi(deltaTicMed) : ''}
                </div>
            </div>`;

            // ── Tabela por dia ──
            const maxFat = Math.max(...datas.map(dt => byDate[dt].fat), 1);
            const rows = datas.map((dt, i) => {
                const v   = byDate[dt];
                const liq = v.fat - v.sang;
                const d   = new Date(dt + 'T00:00');
                const pct = Math.round((v.fat / maxFat) * 100);
                const fds = d.getDay() === 0 || d.getDay() === 6;
                // Delta dia vs dia anterior do período anterior
                const prev    = prevByDate[i];
                const dPct    = prev ? calcDelta(v.fat, prev.fat) : null;
                const dHtml   = dPct !== null
                    ? `<span class="cx-delta ${dPct > 0 ? 'up' : dPct < 0 ? 'dn' : 'neu'}">
                         ${dPct > 0 ? '↑' : '↓'} ${Math.abs(dPct).toFixed(0)}%
                       </span>`
                    : '<span class="cx-delta neu">—</span>';

                return `
                <tr style="${fds ? 'background:#fffbeb;' : ''}">
                    <td style="white-space:nowrap;">
                        <span class="fw-semibold">${d.toLocaleDateString('pt-BR')}</span>
                        <span class="badge ms-1" style="background:${fds?'#fef3c7':'#f3f4f6'};color:${fds?'#92400e':'#6b7280'};font-size:.63rem;">${diasSem[d.getDay()]}</span>
                    </td>
                    <td style="font-size:.74rem;color:#6b7280;">${[...v.lojas].join(', ')||'—'}</td>
                    <td class="text-end">${v.turnos}</td>
                    <td class="text-end">${v.vendas}</td>
                    <td class="text-end fw-semibold" style="color:#16a34a;">
                        ${fmtBRL(v.fat)}
                        <div style="height:3px;background:#e5e7eb;border-radius:2px;margin-top:3px;">
                            <div style="height:100%;width:${pct}%;background:#16a34a;border-radius:2px;"></div>
                        </div>
                    </td>
                    <td class="text-end" style="color:#dc2626;">${fmtBRL(v.sang)}</td>
                    <td class="text-end fw-semibold" style="color:${corLiq(liq)};">${fmtBRL(liq)}</td>
                    <td class="text-end">${dHtml}</td>
                </tr>`;
            }).join('');

            const infoAnterior = `<small style="color:#9ca3af;font-size:.72rem;">
                Δ vs ${fmt(prevDeDt)} a ${fmt(prevAteDt)}
            </small>`;

            elA.innerHTML = kpisHtml + `
                <!-- Feature 1: Gráfico -->
                <div id="cx-chart-wrap" class="cx-chart-wrap" style="display:none;">
                    <div class="cx-section-title">Faturamento vs Sangrias por dia</div>
                    <canvas id="cx-chart-fat" height="80"></canvas>
                </div>
                <div class="cx-section-title" style="margin-top:4px;">
                    Faturamento por dia &nbsp; ${infoAnterior}
                </div>
                <div class="cx-table-wrap">
                <table class="cx-table">
                    <thead>
                        <tr>
                            <th>Data</th><th>Lojas</th>
                            <th class="text-end">Turnos</th><th class="text-end">Vendas</th>
                            <th class="text-end">Faturamento</th>
                            <th class="text-end">Sangrias</th><th class="text-end">Líquido</th>
                            <th class="text-end">Δ Fat</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right;color:#6b7280;font-size:.75rem;">
                                TOTAL ${datas.length} dia(s):
                            </td>
                            <td class="text-end" style="color:#16a34a;">${fmtBRL(gFat)}</td>
                            <td class="text-end" style="color:#dc2626;">${fmtBRL(gSang)}</td>
                            <td class="text-end" style="color:${corLiq(gLiq)};">${fmtBRL(gLiq)}</td>
                            <td class="text-end">${deltaFat !== null
                                ? `<span class="cx-delta ${deltaFat>0?'up':'dn'}">${deltaFat>0?'↑':'↓'} ${Math.abs(deltaFat).toFixed(1)}%</span>`
                                : '—'}</td>
                        </tr>
                    </tfoot>
                </table>
                </div>`;

            document.getElementById('cx-res-csv-btn').style.display = '';
            renderizarGrafico(datas, byDate);

        } catch (e) {
            elA.innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
        }
    };

    // ══════════════════════════════════════════════════════════════════════════
    // ABA: ABERTURA & FECHAMENTO (AUDITORIA)
    // ══════════════════════════════════════════════════════════════════════════
    window.cxCarregarHistorico = async function () {
        const loja   = document.getElementById('cx-hist-loja')?.value || '';
        const status = document.getElementById('cx-hist-status').value;
        const busca  = document.getElementById('cx-hist-busca').value.trim();
        const de     = document.getElementById('cx-hist-de').value;
        const ate    = document.getElementById('cx-hist-ate').value;
        const elT    = document.getElementById('cx-hist-tabela');
        const elK    = document.getElementById('cx-hist-kpis');
        elT.innerHTML = '<div class="cx-empty">Carregando…</div>';
        elK.style.display = 'none';
        document.getElementById('cx-hist-csv-btn').style.display = 'none';

        const params = new URLSearchParams({ data_ini: de, data_fim: ate });
        if (loja)   params.set('loja_id', loja);
        if (status) params.set('status', status);
        if (busca)  params.set('busca', busca);

        try {
            const r = await fetch(`${API}/caixas/historico?${params}`, {headers: {'Authorization': 'Bearer ' + token()}});
            const j = await r.json();
            _dadosHist = j.data || [];

            if (!_dadosHist.length) {
                elT.innerHTML = '<div class="cx-empty">Nenhum caixa encontrado com esses filtros.</div>';
                return;
            }

            // ── KPIs ──
            const abertos = _dadosHist.filter(d => d.status === 'aberto').length;
            // Diferenças dentro da tolerância de quebra de caixa (arredondamento de troco
            // em dinheiro) não contam como falta/sobra real — ver d.dentro_tolerancia.
            const comFalta = _dadosHist.filter(d => d.diferenca !== null && !d.dentro_tolerancia && d.diferenca < 0).length;
            const comSobra = _dadosHist.filter(d => d.diferenca !== null && !d.dentro_tolerancia && d.diferenca > 0).length;
            document.getElementById('kpi-hist-total').textContent   = _dadosHist.length;
            document.getElementById('kpi-hist-abertos').textContent = abertos;
            document.getElementById('kpi-hist-falta').textContent   = comFalta;
            document.getElementById('kpi-hist-sobra').textContent   = comSobra;
            elK.style.display = '';
            document.getElementById('cx-hist-csv-btn').style.display = '';

            // ── Tabela ──
            const tbody = _dadosHist.map(d => {
                const statusBadge = d.status === 'aberto'
                    ? '<span class="cx-badge cx-badge-aberto">🟢 Aberto</span>'
                    : '<span class="cx-badge cx-badge-fechado">⚪ Fechado</span>';

                let difHtml = '<span style="color:#9ca3af;">—</span>';
                if (d.diferenca !== null) {
                    const dif = parseFloat(d.diferenca);
                    if (Math.abs(dif) < 0.01) {
                        difHtml = '<span style="color:#16a34a;font-weight:600;">✓ Confere</span>';
                    } else if (d.dentro_tolerancia) {
                        // Dentro da tolerância de quebra de caixa — arredondamento normal de
                        // troco em dinheiro, não é falta/sobra real. Mostra discreto, sem alerta.
                        const sinal = dif < 0 ? '−' : '+';
                        difHtml = `<span style="color:#9ca3af;font-weight:500;" title="Dentro da tolerância de quebra de caixa (R$ ${TOLERANCIA_QUEBRA.toFixed(2).replace('.',',')})">${sinal} ${fmtBRL(Math.abs(dif))}</span>`;
                    } else if (dif < 0) {
                        difHtml = `<span style="color:#dc2626;font-weight:700;">⚠️ Falta ${fmtBRL(Math.abs(dif))}</span>`;
                    } else {
                        difHtml = `<span style="color:#d97706;font-weight:700;">↑ Sobra ${fmtBRL(dif)}</span>`;
                    }
                }

                // Ícone de justificativa: aparece quando quem abriu/fechou não é o
                // operador de caixa (gerente/dono cobrindo ausência ou caixa esquecido).
                const notaAbertura = d.justificativa_abertura
                    ? ` <span title="${escAttr(d.justificativa_abertura)}" style="cursor:help;" class="cx-nota-just">📝</span>`
                    : (d.abriu_role && d.abriu_role !== 'caixa' ? ' <span title="Aberto por perfil não-operador, sem justificativa registrada." style="cursor:help;">⚠️</span>' : '');
                const notaFechamento = d.justificativa_fechamento
                    ? ` <span title="${escAttr(d.justificativa_fechamento)}" style="cursor:help;" class="cx-nota-just">📝</span>`
                    : (d.fechou_role && d.fechou_role !== 'caixa' ? ' <span title="Fechado por perfil não-operador, sem justificativa registrada." style="cursor:help;">⚠️</span>' : '');

                return `
                <tr>
                    <td style="font-weight:600;">${d.loja_nome || '—'}</td>
                    <td>${statusBadge}</td>
                    <td style="font-size:.81rem;">${d.abriu_nome || '—'}${notaAbertura}</td>
                    <td style="font-size:.76rem;white-space:nowrap;">${fmtDT(d.aberto_em)}</td>
                    <td style="font-size:.81rem;">${d.fechou_nome ? d.fechou_nome + notaFechamento : '<span style=\'color:#9ca3af;\'>—</span>'}</td>
                    <td style="font-size:.76rem;white-space:nowrap;">${d.fechado_em ? fmtDT(d.fechado_em) : '<span style="color:#9ca3af;">—</span>'}</td>
                    <td class="text-end" style="font-size:.76rem;color:#9ca3af;">${fmtBRL(d.fundo_troco)}</td>
                    <td class="text-end">${d.total_contado !== null ? fmtBRL(d.total_contado) : '—'}</td>
                    <td class="text-end">${difHtml}</td>
                </tr>`;
            }).join('');

            elT.innerHTML = `
                <div class="cx-table-wrap">
                <table class="cx-table">
                    <thead>
                        <tr>
                            <th>Loja</th><th>Status</th>
                            <th>Abriu</th><th>Aberto em</th>
                            <th>Fechou</th><th>Fechado em</th>
                            <th class="text-end">Fundo</th><th class="text-end">Contado</th>
                            <th class="text-end">Diferença</th>
                        </tr>
                    </thead>
                    <tbody>${tbody}</tbody>
                </table>
                </div>`;
        } catch (e) {
            elT.innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
        }
    };

    // ── Atalhos de período ────────────────────────────────────────────────────
    window.cxAtalhoResumo = function (dias_) {
        const ate = new Date();
        let de;
        if (dias_ === 0) {
            de = new Date(ate.getFullYear(), ate.getMonth(), 1);
        } else {
            de = new Date(ate); de.setDate(de.getDate() - dias_ + 1);
        }
        const fmt = d => d.toISOString().slice(0,10);
        document.getElementById('cx-res-de').value  = fmt(de);
        document.getElementById('cx-res-ate').value = fmt(ate);
        cxCarregarResumo();
    };

    // ── Auto-init ─────────────────────────────────────────────────────────────
    cxVerificarAbertos(); // Feature 5 — sempre

    const abaInicial = '<?= $aba_atual ?>';
    if (abaInicial === 'fech')   cxCarregarFechamentos();
    if (abaInicial === 'sang')   cxCarregarSangrias();
    if (abaInicial === 'resumo') cxCarregarResumo();
    if (abaInicial === 'hist')   cxCarregarHistorico();

    return {};
})();
</script>
