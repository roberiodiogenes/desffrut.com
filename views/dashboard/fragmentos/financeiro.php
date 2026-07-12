<?php
/**
 * Desffrut — Fragmento: Financeiro Completo
 * Painéis: Visão Geral · Alertas · Contas a Pagar · Contas a Receber ·
 *          Pro-labore/Retiradas · Auxiliares CEASA · Despesas Extras ·
 *          Transferências · Metas & Orçamento · Fluxo de Caixa
 */
$pdo   = db();
$lojas = $pdo->query("SELECT id, nome FROM lojas WHERE ativo=1 ORDER BY nome")->fetchAll();
$colaboradores = [];
try {
    $colaboradores = $pdo->query("SELECT id, nome FROM ceasa_colaboradores WHERE ativo=1 ORDER BY nome")->fetchAll();
} catch (Throwable $e) {}

// Painel inicial via query string
$painel_inicial = match($_GET['a'] ?? '') {
    'fin_alertas'   => 'alertas',
    'fin_receber'   => 'receber',
    'fin_retiradas' => 'retiradas',
    'fin_aux'       => 'auxiliares',
    'fin_desp'      => 'despesas',
    'fin_transf'    => 'transferencias',
    'fin_metas'     => 'metas',
    'fin_fluxo'     => 'fluxo',
    'fin_pagar'     => 'pagar',
    default         => 'visao',
};
?>
<style data-frag="financeiro">
/* ── Base ── */
.fin-wrap { padding:14px 16px; }
.fin-tabs { display:flex; gap:4px; margin-bottom:16px; flex-wrap:wrap; }
.fin-tab-btn { padding:6px 13px; border:1.5px solid #e0e0e0; border-radius:7px;
    background:#fff; font-size:.79rem; cursor:pointer; color:#555; transition:all .13s; }
.fin-tab-btn.active { background:#1565c0; color:#fff; border-color:#1565c0; font-weight:600; }
.fin-tab-btn:hover:not(.active) { background:#f0f4ff; border-color:#90caf9; }
.fin-panel { display:none; }
.fin-panel.active { display:block; }

/* ── Cards e tabelas ── */
.fin-card { background:#fff; border-radius:10px; box-shadow:0 1px 5px rgba(0,0,0,.07);
    padding:16px; margin-bottom:14px; }
.fin-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
.fin-card-title  { font-weight:700; font-size:.93rem; color:#222; }
.fin-table { width:100%; border-collapse:collapse; font-size:.81rem; }
.fin-table th { background:#f8f9fa; padding:8px 10px; text-align:left;
    border-bottom:2px solid #e0e0e0; color:#555; font-weight:700; font-size:.74rem; text-transform:uppercase; letter-spacing:.3px; }
.fin-table td { padding:7px 10px; border-bottom:1px solid #f0f0f0; color:#333; vertical-align:middle; }
.fin-table tr:hover td { background:#fafbff; }
.fin-table tfoot td { background:#f5f5f5; font-weight:700; border-top:2px solid #ddd; }
.text-end { text-align:right !important; }

/* ── Botões ── */
.fbtn { padding:6px 13px; border-radius:7px; font-size:.79rem; border:none; cursor:pointer; font-weight:600; transition:all .12s; }
.fbtn-sm { padding:3px 8px; font-size:.72rem; border-radius:5px; border:none; cursor:pointer; }
.fbtn-blue  { background:#1565c0; color:#fff; } .fbtn-blue:hover  { background:#0d47a1; }
.fbtn-green { background:#2e7d32; color:#fff; } .fbtn-green:hover { background:#1b5e20; }
.fbtn-red   { background:#c62828; color:#fff; } .fbtn-red:hover   { background:#b71c1c; }
.fbtn-amber { background:#e65100; color:#fff; } .fbtn-amber:hover { background:#bf360c; }
.fbtn-outline { background:#fff; border:1.5px solid #ccc; color:#555; }
.fbtn-outline:hover { background:#f5f5f5; }

/* ── Badges ── */
.badge-pendente  { background:#fff3e0; color:#e65100;  padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:700; }
.badge-vencido   { background:#ffebee; color:#c62828;  padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:700; }
.badge-pago, .badge-recebido { background:#e8f5e9; color:#2e7d32; padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:700; }
.badge-cancelado { background:#f5f5f5;  color:#9e9e9e; padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:700; }

/* ── KPIs ── */
.fin-kpi-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.fin-kpi { flex:1; min-width:110px; background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.07); padding:14px 14px; border-top:3px solid var(--fkpi,#e0e0e0); }
.fin-kpi .kval { font-size:1.15rem; font-weight:800; color:var(--fkpi,#333); line-height:1.1; }
.fin-kpi .klbl { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#9ca3af; margin-top:3px; }
.fin-kpi .ksub { font-size:.66rem; color:#6b7280; margin-top:2px; }
.kpi-blue   { --fkpi:#1565c0; } .kpi-green  { --fkpi:#2e7d32; }
.kpi-red    { --fkpi:#c62828; } .kpi-amber  { --fkpi:#e65100; }
.kpi-purple { --fkpi:#6a1b9a; } .kpi-teal   { --fkpi:#00695c; }
.kpi-gray   { --fkpi:#546e7a; }

/* ── Filtros ── */
.filtro-bar { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px; }
.filtro-bar label { font-size:.73rem; color:#666; display:block; margin-bottom:2px; font-weight:600; }
.filtro-bar select, .filtro-bar input { padding:6px 9px; border:1.5px solid #ddd;
    border-radius:7px; font-size:.81rem; background:#fff; }
.filtro-bar select:focus, .filtro-bar input:focus { border-color:#1565c0; outline:none; }

/* ── Modal ── */
.fin-modal-bg { position:fixed; inset:0; background:rgba(0,0,0,.45);
    z-index:1050; display:flex; align-items:center; justify-content:center; padding:12px; }
.fin-modal { background:#fff; border-radius:12px; width:100%; max-width:500px;
    padding:20px 22px; box-shadow:0 8px 32px rgba(0,0,0,.2); max-height:90vh; overflow-y:auto; }
.fin-modal h5 { margin:0 0 14px; font-size:.95rem; font-weight:700; color:#222; }
.frow { margin-bottom:10px; }
.frow label { display:block; font-size:.76rem; color:#555; margin-bottom:3px; font-weight:600; }
.frow input, .frow select, .frow textarea {
    width:100%; padding:8px 10px; border:1.5px solid #ddd; border-radius:7px;
    font-size:.83rem; outline:none; }
.frow input:focus, .frow select:focus { border-color:#1565c0; }
.fgrid2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.fgrid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
.modal-footer { display:flex; gap:8px; justify-content:flex-end; margin-top:14px; }

/* ── Alerta card ── */
.fin-alerta-item { display:flex; align-items:center; gap:10px; padding:9px 12px;
    border-radius:8px; background:#fff; border:1px solid #e0e0e0; margin-bottom:6px; }
.fin-alerta-item.vencido { border-color:#ffcdd2; background:#fff5f5; }
.fin-alerta-item.hoje    { border-color:#ffe0b2; background:#fffbf0; }
.fin-alerta-icon { font-size:1.2rem; }
.fin-alerta-desc { flex:1; font-size:.82rem; font-weight:600; color:#222; }
.fin-alerta-val  { font-size:.85rem; font-weight:800; }
.fin-alerta-data { font-size:.7rem; color:#888; }

/* ── Seção ── */
.sec-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
    color:#9ca3af; margin-bottom:8px; padding-bottom:5px; border-bottom:1px solid #f0f0f0; }

/* ── Toast ── */
.fin-toast { position:fixed; bottom:20px; right:20px; z-index:9999;
    padding:10px 18px; border-radius:8px; color:#fff; font-size:.83rem; font-weight:600;
    box-shadow:0 4px 14px rgba(0,0,0,.2); animation:ftslide .3s ease; }
@keyframes ftslide { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }

@media (max-width:600px) { .fgrid2,.fgrid3 { grid-template-columns:1fr; } }
</style>

<div class="fin-wrap">

<div class="fin-tabs" id="fin-tabs-bar">
    <button class="fin-tab-btn <?= $painel_inicial==='visao'         ?'active':'' ?>" onclick="finTab('visao',this)">📊 Visão Geral</button>
    <button class="fin-tab-btn <?= $painel_inicial==='alertas'       ?'active':'' ?>" onclick="finTab('alertas',this)" id="fin-tab-alertas">🔔 Alertas</button>
    <button class="fin-tab-btn <?= $painel_inicial==='pagar'         ?'active':'' ?>" onclick="finTab('pagar',this)">💳 Contas a Pagar</button>
    <button class="fin-tab-btn <?= $painel_inicial==='receber'       ?'active':'' ?>" onclick="finTab('receber',this)">💰 A Receber</button>
    <button class="fin-tab-btn <?= $painel_inicial==='retiradas'     ?'active':'' ?>" onclick="finTab('retiradas',this)">👤 Retiradas</button>
    <button class="fin-tab-btn <?= $painel_inicial==='auxiliares'    ?'active':'' ?>" onclick="finTab('auxiliares',this)">🚛 Aux. CEASA</button>
    <button class="fin-tab-btn <?= $painel_inicial==='despesas'      ?'active':'' ?>" onclick="finTab('despesas',this)">🧹 Desp. Extras</button>
    <button class="fin-tab-btn <?= $painel_inicial==='transferencias'?'active':'' ?>" onclick="finTab('transferencias',this)">↔️ Transferências</button>
    <button class="fin-tab-btn <?= $painel_inicial==='metas'         ?'active':'' ?>" onclick="finTab('metas',this)">🎯 Metas</button>
    <button class="fin-tab-btn <?= $painel_inicial==='fluxo'         ?'active':'' ?>" onclick="finTab('fluxo',this)">📈 Fluxo de Caixa</button>
</div>

<!-- ═══════ VISÃO GERAL ═══════ -->
<div class="fin-panel <?= $painel_inicial==='visao'?'active':'' ?>" id="fin-panel-visao">
    <div class="filtro-bar" style="margin-bottom:14px;">
        <div><label>Mês</label><input type="month" id="vg-mes" value="<?= date('Y-m') ?>" onchange="finCarregarVisao()"></div>
        <?php if(count($lojas)>1): ?>
        <div><label>Loja</label>
            <select id="vg-loja" onchange="finCarregarVisao()">
                <option value="">Todas</option>
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div><?php endif; ?>
    </div>
    <div class="fin-kpi-row" id="vg-kpis">
        <div class="fin-kpi kpi-green"><div class="kval" id="vg-receita">—</div><div class="klbl">Receita Total</div><div class="ksub" id="vg-rec-sub"></div></div>
        <div class="fin-kpi kpi-red">  <div class="kval" id="vg-desp">—</div>   <div class="klbl">Despesas Total</div></div>
        <div class="fin-kpi kpi-blue" id="vg-res-kpi"><div class="kval" id="vg-res">—</div><div class="klbl">Resultado</div></div>
        <div class="fin-kpi kpi-amber"><div class="kval" id="vg-pend-pagar">—</div><div class="klbl">A Pagar (pendente)</div></div>
        <div class="fin-kpi kpi-teal"> <div class="kval" id="vg-pend-rec">—</div>  <div class="klbl">A Receber (pend.)</div></div>
        <div class="fin-kpi kpi-purple"><div class="kval" id="vg-retiradas">—</div><div class="klbl">Retiradas/Extras</div></div>
        <div class="fin-kpi kpi-red">  <div class="kval" id="vg-alertas">—</div>   <div class="klbl">Alertas Vencendo</div><div class="ksub">próximos 3 dias</div></div>
    </div>
    <div id="vg-loading" class="text-center py-3 text-muted small" style="display:none;">Carregando…</div>
</div>

<!-- ═══════ ALERTAS ═══════ -->
<div class="fin-panel <?= $painel_inicial==='alertas'?'active':'' ?>" id="fin-panel-alertas">
    <div class="filtro-bar" style="margin-bottom:14px;">
        <div><label>Janela (dias)</label>
            <select id="al-dias" onchange="finCarregarAlertas()">
                <option value="3">3 dias</option>
                <option value="7" selected>7 dias</option>
                <option value="15">15 dias</option>
                <option value="30">30 dias</option>
            </select>
        </div>
        <?php if(count($lojas)>1): ?>
        <div><label>Loja</label>
            <select id="al-loja" onchange="finCarregarAlertas()">
                <option value="">Todas</option>
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div><?php endif; ?>
    </div>
    <div class="fgrid2" style="gap:14px;" id="al-grid">
        <div>
            <div class="sec-title">💳 Contas a Pagar</div>
            <div id="al-pagar-lista"><div class="text-muted small py-2">Carregando…</div></div>
        </div>
        <div>
            <div class="sec-title">💰 Contas a Receber</div>
            <div id="al-receber-lista"><div class="text-muted small py-2">Carregando…</div></div>
        </div>
    </div>
</div>

<!-- ═══════ CONTAS A PAGAR ═══════ -->
<div class="fin-panel <?= $painel_inicial==='pagar'?'active':'' ?>" id="fin-panel-pagar">
    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">💳 Contas a Pagar</span>
            <button class="fbtn fbtn-blue" onclick="finAbrirModal('pagar')">+ Lançar Conta</button>
        </div>
        <div class="filtro-bar">
            <div><label>Status</label>
                <select id="cp-status" onchange="finCarregarContas()">
                    <option value="">Todos</option>
                    <option value="pendente">Pendente</option>
                    <option value="vencido">Vencido</option>
                    <option value="pago">Pago</option>
                </select>
            </div>
            <div><label>Mês</label><input type="month" id="cp-mes" value="<?= date('Y-m') ?>" onchange="finCarregarContas()"></div>
            <?php if(count($lojas)>1): ?><div><label>Loja</label>
                <select id="cp-loja" onchange="finCarregarContas()">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
                </select></div><?php endif; ?>
        </div>
        <div id="cp-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <table class="fin-table" id="cp-table" style="display:none">
            <thead><tr><th>Descrição</th><th>Categoria</th><th class="text-end">Valor</th><th>Vencimento</th><th>Loja</th><th>Status</th><th></th></tr></thead>
            <tbody id="cp-tbody"></tbody>
            <tfoot id="cp-tfoot"></tfoot>
        </table>
    </div>
</div>

<!-- ═══════ CONTAS A RECEBER ═══════ -->
<div class="fin-panel <?= $painel_inicial==='receber'?'active':'' ?>" id="fin-panel-receber">
    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">💰 Contas a Receber</span>
            <button class="fbtn fbtn-green" onclick="finAbrirModal('receber')">+ Lançar</button>
        </div>
        <div class="filtro-bar">
            <div><label>Status</label>
                <select id="cr-status" onchange="finCarregarReceber()">
                    <option value="">Todos</option>
                    <option value="pendente">Pendente</option>
                    <option value="vencido">Vencido</option>
                    <option value="recebido">Recebido</option>
                </select>
            </div>
            <div><label>Mês</label><input type="month" id="cr-mes" value="<?= date('Y-m') ?>" onchange="finCarregarReceber()"></div>
            <?php if(count($lojas)>1): ?><div><label>Loja</label>
                <select id="cr-loja" onchange="finCarregarReceber()">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
                </select></div><?php endif; ?>
        </div>
        <div id="cr-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <table class="fin-table" id="cr-table" style="display:none">
            <thead><tr><th>Descrição</th><th>Cliente</th><th>Categoria</th><th class="text-end">Valor</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
            <tbody id="cr-tbody"></tbody>
            <tfoot id="cr-tfoot"></tfoot>
        </table>
    </div>
</div>

<!-- ═══════ RETIRADAS / PRO-LABORE ═══════ -->
<div class="fin-panel <?= $painel_inicial==='retiradas'?'active':'' ?>" id="fin-panel-retiradas">
    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">👤 Pro-labore, Retiradas & Investimentos</span>
            <button class="fbtn fbtn-amber" onclick="finAbrirModal('retirada')">+ Lançar Retirada</button>
        </div>
        <div class="filtro-bar">
            <div><label>Mês</label><input type="month" id="rt-mes" value="<?= date('Y-m') ?>" onchange="finCarregarMovs('retirada')"></div>
            <?php if(count($lojas)>1): ?><div><label>Loja</label>
                <select id="rt-loja" onchange="finCarregarMovs('retirada')">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
                </select></div><?php endif; ?>
        </div>
        <div id="rt-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <table class="fin-table" id="rt-table" style="display:none">
            <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Loja</th><th class="text-end">Valor</th><th></th></tr></thead>
            <tbody id="rt-tbody"></tbody>
            <tfoot id="rt-tfoot"></tfoot>
        </table>
    </div>
</div>

<!-- ═══════ AUXILIARES CEASA ═══════ -->
<div class="fin-panel <?= $painel_inicial==='auxiliares'?'active':'' ?>" id="fin-panel-auxiliares">
    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">🚛 Pagamentos Auxiliares CEASA</span>
            <button class="fbtn fbtn-blue" onclick="finAbrirModal('auxiliar')">+ Lançar Pagamento</button>
        </div>
        <div class="filtro-bar">
            <div><label>Mês</label><input type="month" id="ax-mes" value="<?= date('Y-m') ?>" onchange="finCarregarAuxiliares()"></div>
        </div>
        <div id="ax-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <table class="fin-table" id="ax-table" style="display:none">
            <thead><tr><th>Data Pag.</th><th>Colaborador</th><th>Período</th><th>Tipo</th><th>Forma</th><th class="text-end">Valor</th><th></th></tr></thead>
            <tbody id="ax-tbody"></tbody>
            <tfoot id="ax-tfoot"></tfoot>
        </table>
    </div>
</div>

<!-- ═══════ DESPESAS EXTRAS ═══════ -->
<div class="fin-panel <?= $painel_inicial==='despesas'?'active':'' ?>" id="fin-panel-despesas">
    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">🧹 Despesas Extras</span>
            <button class="fbtn fbtn-red" onclick="finAbrirModal('despesa')">+ Lançar Despesa</button>
        </div>
        <div class="filtro-bar">
            <div><label>Mês</label><input type="month" id="de-mes" value="<?= date('Y-m') ?>" onchange="finCarregarMovs('despesa_extra')"></div>
            <?php if(count($lojas)>1): ?><div><label>Loja</label>
                <select id="de-loja" onchange="finCarregarMovs('despesa_extra')">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
                </select></div><?php endif; ?>
        </div>
        <div id="de-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <table class="fin-table" id="de-table" style="display:none">
            <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Loja</th><th class="text-end">Valor</th><th></th></tr></thead>
            <tbody id="de-tbody"></tbody>
            <tfoot id="de-tfoot"></tfoot>
        </table>
    </div>
</div>

<!-- ═══════ TRANSFERÊNCIAS ═══════ -->
<div class="fin-panel <?= $painel_inicial==='transferencias'?'active':'' ?>" id="fin-panel-transferencias">
    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">↔️ Transferências entre Lojas / Contas</span>
            <button class="fbtn fbtn-purple" style="background:#6a1b9a;" onclick="finAbrirModal('transferencia')">+ Registrar Transferência</button>
        </div>
        <div class="filtro-bar">
            <div><label>Mês</label><input type="month" id="tr-mes" value="<?= date('Y-m') ?>" onchange="finCarregarMovs('transferencia')"></div>
        </div>
        <div id="tr-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <table class="fin-table" id="tr-table" style="display:none">
            <thead><tr><th>Data</th><th>Descrição</th><th>Origem</th><th>Destino</th><th class="text-end">Valor</th><th></th></tr></thead>
            <tbody id="tr-tbody"></tbody>
        </table>
    </div>
</div>

<!-- ═══════ METAS & ORÇAMENTO ═══════ -->
<div class="fin-panel <?= $painel_inicial==='metas'?'active':'' ?>" id="fin-panel-metas">
    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">🎯 Metas & Orçamento</span>
            <button class="fbtn fbtn-blue" onclick="finAbrirModal('meta')">+ Definir Meta</button>
        </div>
        <div class="filtro-bar">
            <div><label>Mês</label><input type="month" id="mt-mes" value="<?= date('Y-m') ?>" onchange="finCarregarMetas()"></div>
            <?php if(count($lojas)>1): ?><div><label>Loja</label>
                <select id="mt-loja" onchange="finCarregarMetas()">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
                </select></div><?php endif; ?>
        </div>
        <div id="mt-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <div id="mt-lista"></div>
    </div>
</div>

<!-- ═══════ FLUXO DE CAIXA ═══════ -->
<div class="fin-panel <?= $painel_inicial==='fluxo'?'active':'' ?>" id="fin-panel-fluxo">
    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">📈 Fluxo de Caixa Anual</span>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <label style="font-size:.76rem;color:#666;">Ano</label>
                <input type="number" id="flx-ano" value="<?= date('Y') ?>" min="2020" max="2099"
                    style="width:80px;padding:5px 9px;border:1.5px solid #ddd;border-radius:7px;font-size:.82rem;"
                    onchange="finCarregarFluxo()">
                <?php if(count($lojas)>1): ?>
                <select id="flx-loja" onchange="finCarregarFluxo()" style="padding:5px 9px;border:1.5px solid #ddd;border-radius:7px;font-size:.82rem;">
                    <option value="">Todas as lojas</option>
                    <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
                </select><?php endif; ?>
            </div>
        </div>
        <div id="flx-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <table class="fin-table" id="flx-table" style="display:none">
            <thead><tr>
                <th>Mês</th><th class="text-end">PDV</th><th class="text-end">Delivery</th>
                <th class="text-end">Receita</th><th class="text-end">Contas</th>
                <th class="text-end">Movm.</th><th class="text-end">Aux.</th>
                <th class="text-end">Total Desp.</th><th class="text-end">Resultado</th>
            </tr></thead>
            <tbody id="flx-tbody"></tbody>
            <tfoot id="flx-tfoot"></tfoot>
        </table>
    </div>
</div>

</div><!-- /fin-wrap -->

<!-- ══════════════════════════════════════════════════
     MODAIS
══════════════════════════════════════════════════ -->

<!-- Modal: Contas a Pagar -->
<div class="fin-modal-bg" id="fin-modal-pagar" style="display:none">
<div class="fin-modal">
    <h5>💳 Lançar Conta a Pagar</h5>
    <div class="frow"><label>Descrição *</label><input type="text" id="cp-m-desc" placeholder="Ex.: Aluguel Loja Centro"></div>
    <div class="fgrid2">
        <div class="frow"><label>Categoria *</label>
            <select id="cp-m-cat">
                <option value="aluguel">Aluguel</option><option value="agua">Água</option>
                <option value="energia">Energia</option><option value="internet">Internet</option>
                <option value="fornecedor">Fornecedor</option><option value="folha">Folha</option>
                <option value="outros">Outros</option>
            </select>
        </div>
        <div class="frow"><label>Valor (R$) *</label><input type="number" id="cp-m-val" step="0.01" min="0"></div>
        <div class="frow"><label>Vencimento *</label><input type="date" id="cp-m-venc" value="<?= date('Y-m-d') ?>"></div>
        <div class="frow"><label>Loja *</label>
            <select id="cp-m-loja">
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="frow"><label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" id="cp-m-rec" style="width:auto;"> Recorrente mensal</label></div>
    <div class="frow"><label>Observações</label><textarea id="cp-m-obs" rows="2"></textarea></div>
    <div class="modal-footer">
        <button class="fbtn fbtn-outline" onclick="finFecharModais()">Cancelar</button>
        <button class="fbtn fbtn-blue" onclick="finSalvarContaPagar()">Lançar</button>
    </div>
</div></div>

<!-- Modal: Contas a Receber -->
<div class="fin-modal-bg" id="fin-modal-receber" style="display:none">
<div class="fin-modal">
    <h5>💰 Lançar Conta a Receber</h5>
    <div class="frow"><label>Descrição *</label><input type="text" id="cr-m-desc" placeholder="Ex.: Fiado João da Silva"></div>
    <div class="fgrid2">
        <div class="frow"><label>Categoria *</label>
            <select id="cr-m-cat">
                <option value="fiado">Fiado</option><option value="cheque">Cheque</option>
                <option value="pix">PIX</option><option value="transferencia">Transferência</option>
                <option value="cartao">Cartão</option><option value="outros">Outros</option>
            </select>
        </div>
        <div class="frow"><label>Valor (R$) *</label><input type="number" id="cr-m-val" step="0.01" min="0"></div>
        <div class="frow"><label>Vencimento *</label><input type="date" id="cr-m-venc" value="<?= date('Y-m-d') ?>"></div>
        <div class="frow"><label>Loja *</label>
            <select id="cr-m-loja">
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="frow"><label>Nome do cliente</label><input type="text" id="cr-m-cli" placeholder="Opcional"></div>
    <div class="frow"><label>Observações</label><textarea id="cr-m-obs" rows="2"></textarea></div>
    <div class="modal-footer">
        <button class="fbtn fbtn-outline" onclick="finFecharModais()">Cancelar</button>
        <button class="fbtn fbtn-green" onclick="finSalvarReceber()">Lançar</button>
    </div>
</div></div>

<!-- Modal: Retirada / Pro-labore -->
<div class="fin-modal-bg" id="fin-modal-retirada" style="display:none">
<div class="fin-modal">
    <h5>👤 Lançar Retirada / Pro-labore</h5>
    <div class="frow"><label>Tipo *</label>
        <select id="rt-m-sub">
            <option value="pro_labore">Pro-labore (salário do dono)</option>
            <option value="investimento">Investimento pessoal</option>
            <option value="transferencia_pessoal">Transferência para conta pessoal</option>
            <option value="outros">Outros</option>
        </select>
    </div>
    <div class="frow"><label>Descrição *</label><input type="text" id="rt-m-desc" placeholder="Ex.: Pro-labore junho/2026"></div>
    <div class="fgrid2">
        <div class="frow"><label>Valor (R$) *</label><input type="number" id="rt-m-val" step="0.01" min="0"></div>
        <div class="frow"><label>Data *</label><input type="date" id="rt-m-data" value="<?= date('Y-m-d') ?>"></div>
        <div class="frow"><label>Loja / Caixa *</label>
            <select id="rt-m-loja">
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="frow"><label>Conta bancária destino</label><input type="text" id="rt-m-banco" placeholder="Ex.: Nubank - 0001"></div>
    </div>
    <div class="frow"><label>Observações</label><textarea id="rt-m-obs" rows="2"></textarea></div>
    <div class="modal-footer">
        <button class="fbtn fbtn-outline" onclick="finFecharModais()">Cancelar</button>
        <button class="fbtn fbtn-amber" onclick="finSalvarMovimentacao('retirada')">Lançar</button>
    </div>
</div></div>

<!-- Modal: Auxiliar CEASA -->
<div class="fin-modal-bg" id="fin-modal-auxiliar" style="display:none">
<div class="fin-modal">
    <h5>🚛 Pagamento de Auxiliar CEASA</h5>
    <div class="frow"><label>Colaborador *</label>
        <select id="ax-m-col">
            <option value="">Selecione…</option>
            <?php foreach($colaboradores as $c): ?><option value="<?=$c['id']?>"><?= htmlspecialchars($c['nome']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="fgrid2">
        <div class="frow"><label>Frequência *</label>
            <select id="ax-m-tipo">
                <option value="semanal">Semanal</option>
                <option value="quinzenal">Quinzenal</option>
                <option value="mensal">Mensal</option>
            </select>
        </div>
        <div class="frow"><label>Forma de Pagamento</label>
            <select id="ax-m-forma">
                <option value="dinheiro">Dinheiro</option>
                <option value="pix">PIX</option>
                <option value="transferencia">Transferência</option>
            </select>
        </div>
        <div class="frow"><label>Período Início *</label><input type="date" id="ax-m-pi" value="<?= date('Y-m-d', strtotime('monday this week')) ?>"></div>
        <div class="frow"><label>Período Fim *</label><input type="date" id="ax-m-pf" value="<?= date('Y-m-d') ?>"></div>
        <div class="frow"><label>Data do Pagamento *</label><input type="date" id="ax-m-dpag" value="<?= date('Y-m-d') ?>"></div>
        <div class="frow"><label>Valor Pago (R$) *</label><input type="number" id="ax-m-val" step="0.01" min="0"></div>
    </div>
    <div class="frow"><label>Observações</label><textarea id="ax-m-obs" rows="2"></textarea></div>
    <div class="modal-footer">
        <button class="fbtn fbtn-outline" onclick="finFecharModais()">Cancelar</button>
        <button class="fbtn fbtn-blue" onclick="finSalvarAuxiliar()">Lançar</button>
    </div>
</div></div>

<!-- Modal: Despesa Extra -->
<div class="fin-modal-bg" id="fin-modal-despesa" style="display:none">
<div class="fin-modal">
    <h5>🧹 Lançar Despesa Extra</h5>
    <div class="frow"><label>Tipo *</label>
        <select id="de-m-sub">
            <option value="limpeza">Limpeza</option>
            <option value="manutencao">Manutenção</option>
            <option value="terceirizado">Serviço Terceirizado</option>
            <option value="combustivel">Combustível</option>
            <option value="pedagio">Pedágio</option>
            <option value="outros">Outros</option>
        </select>
    </div>
    <div class="frow"><label>Descrição *</label><input type="text" id="de-m-desc" placeholder="Ex.: Limpeza semanal loja"></div>
    <div class="fgrid2">
        <div class="frow"><label>Valor (R$) *</label><input type="number" id="de-m-val" step="0.01" min="0"></div>
        <div class="frow"><label>Data *</label><input type="date" id="de-m-data" value="<?= date('Y-m-d') ?>"></div>
        <div class="frow"><label>Loja *</label>
            <select id="de-m-loja">
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="frow"><label>Observações</label><textarea id="de-m-obs" rows="2"></textarea></div>
    <div class="modal-footer">
        <button class="fbtn fbtn-outline" onclick="finFecharModais()">Cancelar</button>
        <button class="fbtn fbtn-red" onclick="finSalvarMovimentacao('despesa_extra')">Lançar</button>
    </div>
</div></div>

<!-- Modal: Transferência -->
<div class="fin-modal-bg" id="fin-modal-transferencia" style="display:none">
<div class="fin-modal">
    <h5>↔️ Registrar Transferência</h5>
    <div class="frow"><label>Descrição *</label><input type="text" id="tr-m-desc" placeholder="Ex.: Repasse caixa Loja 1 → Loja 2"></div>
    <div class="fgrid2">
        <div class="frow"><label>Loja Origem *</label>
            <select id="tr-m-loja-ori">
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="frow"><label>Loja Destino</label>
            <select id="tr-m-loja-dest">
                <option value="">Conta Bancária</option>
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="frow"><label>Banco/Conta (se externo)</label><input type="text" id="tr-m-banco" placeholder="Ex.: Banco do Brasil C/C 1234-5"></div>
        <div class="frow"><label>Valor (R$) *</label><input type="number" id="tr-m-val" step="0.01" min="0"></div>
        <div class="frow"><label>Data *</label><input type="date" id="tr-m-data" value="<?= date('Y-m-d') ?>"></div>
    </div>
    <div class="frow"><label>Observações</label><textarea id="tr-m-obs" rows="2"></textarea></div>
    <div class="modal-footer">
        <button class="fbtn fbtn-outline" onclick="finFecharModais()">Cancelar</button>
        <button class="fbtn" style="background:#6a1b9a;color:#fff;" onclick="finSalvarMovimentacao('transferencia')">Registrar</button>
    </div>
</div></div>

<!-- Modal: Meta -->
<div class="fin-modal-bg" id="fin-modal-meta" style="display:none">
<div class="fin-modal">
    <h5>🎯 Definir Meta</h5>
    <div class="fgrid2">
        <div class="frow"><label>Mês de Referência *</label><input type="month" id="mt-m-mes" value="<?= date('Y-m') ?>"></div>
        <div class="frow"><label>Tipo *</label>
            <select id="mt-m-tipo" onchange="finToggleMetaCat()">
                <option value="faturamento">Meta de Faturamento</option>
                <option value="despesa_total">Orçamento Despesas Total</option>
                <option value="despesa_categoria">Orçamento por Categoria</option>
            </select>
        </div>
        <div class="frow" id="mt-m-cat-wrap" style="display:none;"><label>Categoria</label>
            <select id="mt-m-cat">
                <option value="aluguel">Aluguel</option><option value="agua">Água</option>
                <option value="energia">Energia</option><option value="folha">Folha</option>
                <option value="limpeza">Limpeza</option><option value="combustivel">Combustível</option>
                <option value="outros">Outros</option>
            </select>
        </div>
        <div class="frow"><label>Valor Meta (R$) *</label><input type="number" id="mt-m-val" step="0.01" min="0"></div>
        <div class="frow"><label>Loja (vazio = todas)</label>
            <select id="mt-m-loja">
                <option value="">Todas as lojas</option>
                <?php foreach($lojas as $l): ?><option value="<?=$l['id']?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button class="fbtn fbtn-outline" onclick="finFecharModais()">Cancelar</button>
        <button class="fbtn fbtn-blue" onclick="finSalvarMeta()">Salvar</button>
    </div>
</div></div>

<script>
(function(){
'use strict';
const API = window.APP?.api || '/desffrut.com/api/v1';
const tk  = () => sessionStorage.getItem('desffrut_token') || '';
const hdrs = (json=false) => {
    const h = { 'Authorization': 'Bearer ' + tk() };
    if (json) h['Content-Type'] = 'application/json';
    return h;
};
const apiFetch = (url, opts={}) => fetch(url, { headers: hdrs(), ...opts }).then(r => r.json());
const apiPost  = (url, body)    => fetch(url, { method:'POST',   headers: hdrs(true), body: JSON.stringify(body) }).then(r => r.json());
const apiPatch = (url, body={}) => fetch(url, { method:'PATCH',  headers: hdrs(true), body: JSON.stringify(body) }).then(r => r.json());
const apiDel   = (url)          => fetch(url, { method:'DELETE', headers: hdrs() }).then(r => r.json());

const MESES = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
const fmtR  = v => 'R$ ' + parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const fmtD  = d => d ? d.split('-').reverse().join('/') : '—';
const fmtM  = ym => { const p = ym.split('-'); return MESES[+p[1]-1]+'/'+p[0]; };
const qs    = id => document.getElementById(id)?.value || '';

function toast(msg, ok=true) {
    const el = document.createElement('div');
    el.className = 'fin-toast';
    el.style.background = ok ? '#1565c0' : '#c62828';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

/* ── Tabs ── */
window.finTab = function(painel, btn) {
    document.querySelectorAll('.fin-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.fin-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('fin-panel-' + painel).classList.add('active');
    btn.classList.add('active');
    const loaders = {
        visao:         finCarregarVisao,
        alertas:       finCarregarAlertas,
        pagar:         finCarregarContas,
        receber:       finCarregarReceber,
        retiradas:     () => finCarregarMovs('retirada'),
        auxiliares:    finCarregarAuxiliares,
        despesas:      () => finCarregarMovs('despesa_extra'),
        transferencias:() => finCarregarMovs('transferencia'),
        metas:         finCarregarMetas,
        fluxo:         finCarregarFluxo,
    };
    loaders[painel]?.();
};

/* ── Modais ── */
window.finAbrirModal  = m => document.getElementById('fin-modal-' + m).style.display = 'flex';
window.finFecharModais = () => document.querySelectorAll('.fin-modal-bg').forEach(m => m.style.display = 'none');
document.querySelectorAll('.fin-modal-bg').forEach(bg => bg.addEventListener('click', e => { if(e.target===bg) finFecharModais(); }));
window.finToggleMetaCat = () => {
    const show = qs('mt-m-tipo') === 'despesa_categoria';
    document.getElementById('mt-m-cat-wrap').style.display = show ? '' : 'none';
};

/* ══════════ VISÃO GERAL ══════════ */
window.finCarregarVisao = async function() {
    const mes  = qs('vg-mes') || '<?= date('Y-m') ?>';
    const loja = qs('vg-loja');
    try {
        const j = await apiFetch(`${API}/financeiro/dashboard?mes=${mes}${loja?'&loja_id='+loja:''}`);
        const d = j.data || {};
        document.getElementById('vg-receita').textContent    = fmtR(d.receita_total);
        document.getElementById('vg-rec-sub').textContent    = `PDV ${fmtR(d.receita_pdv)} + Del ${fmtR(d.receita_delivery)}`;
        document.getElementById('vg-desp').textContent       = fmtR(d.despesas_total);
        document.getElementById('vg-res').textContent        = fmtR(d.resultado);
        document.getElementById('vg-res-kpi').style.setProperty('--fkpi', d.resultado >= 0 ? '#2e7d32' : '#c62828');
        document.getElementById('vg-pend-pagar').textContent = fmtR(d.contas_pagar_pendente);
        document.getElementById('vg-pend-rec').textContent   = fmtR(d.contas_receber_pendente);
        document.getElementById('vg-retiradas').textContent  = fmtR((d.retiradas_mes||0) + (d.despesas_extras||0) + (d.pagamentos_aux||0));
        document.getElementById('vg-alertas').textContent    = d.alertas_proximos || '0';
    } catch(e) { toast('Erro ao carregar visão geral.', false); }
};

/* ══════════ ALERTAS ══════════ */
window.finCarregarAlertas = async function() {
    const dias = qs('al-dias') || '7';
    const loja = qs('al-loja');
    const [elP, elR] = ['al-pagar-lista','al-receber-lista'].map(id => document.getElementById(id));
    elP.innerHTML = elR.innerHTML = '<div class="text-muted small py-2">Carregando…</div>';
    try {
        const j = await apiFetch(`${API}/financeiro/alertas?dias=${dias}${loja?'&loja_id='+loja:''}`);
        const hoje = new Date().toISOString().slice(0,10);
        const renderItem = (c, tipo) => {
            const venc = c.data_ref;
            const cls  = venc < hoje ? 'vencido' : (venc === hoje ? 'hoje' : '');
            const diff = Math.round((new Date(venc) - new Date(hoje)) / 86400000);
            const tag  = venc < hoje ? '🔴 Vencido' : (diff === 0 ? '🟠 Hoje' : `⚠️ em ${diff}d`);
            return `<div class="fin-alerta-item ${cls}">
                <span class="fin-alerta-icon">${tipo==='pagar'?'💳':'💰'}</span>
                <div style="flex:1;min-width:0;">
                    <div class="fin-alerta-desc" title="${c.descricao}">${c.descricao.slice(0,50)}</div>
                    <div class="fin-alerta-data">${c.loja_nome} · Venc. ${fmtD(venc)}</div>
                </div>
                <div style="text-align:right;">
                    <div class="fin-alerta-val" style="color:${c.status==='vencido'?'#c62828':'#e65100'}">${fmtR(c.valor)}</div>
                    <span class="badge-${c.status}" style="font-size:.65rem;">${tag}</span>
                </div>
            </div>`;
        };
        elP.innerHTML = (j.data?.pagar||[]).map(c => renderItem(c,'pagar')).join('') || '<div class="text-muted small py-2 text-center">Nenhuma conta a pagar.</div>';
        elR.innerHTML = (j.data?.receber||[]).map(c => renderItem(c,'receber')).join('') || '<div class="text-muted small py-2 text-center">Nenhuma conta a receber.</div>';
    } catch(e) { elP.innerHTML = elR.innerHTML = '<div class="text-danger small">Erro ao carregar.</div>'; }
};

/* ══════════ CONTAS A PAGAR ══════════ */
window.finCarregarContas = async function() {
    const [load, tbl, tbody] = ['cp-loading','cp-table','cp-tbody'].map(id => document.getElementById(id));
    load.style.display = ''; tbl.style.display = 'none';
    const url = `${API}/contas_pagar?mes=${qs('cp-mes')}${qs('cp-status')?'&status='+qs('cp-status'):''}${qs('cp-loja')?'&loja_id='+qs('cp-loja'):''}`;
    try {
        const j = await apiFetch(url);
        const lista = j.data || [];
        let sumPend = 0, sumVenc = 0, sumPago = 0;
        tbody.innerHTML = lista.map(c => {
            if (c.status==='pendente') sumPend += +c.valor;
            else if (c.status==='vencido') sumVenc += +c.valor;
            else sumPago += +c.valor;
            return `<tr>
                <td>${c.descricao}${c.recorrente?'<span style="margin-left:5px;font-size:.65rem;background:#e3f2fd;color:#1565c0;border-radius:10px;padding:1px 5px;">🔄</span>':''}</td>
                <td>${c.categoria}</td>
                <td class="text-end">${fmtR(c.valor)}</td>
                <td>${fmtD(c.vencimento)}</td>
                <td style="font-size:.78rem;">${c.loja_nome}</td>
                <td><span class="badge-${c.status}">${c.status}</span></td>
                <td style="white-space:nowrap;">${c.status!=='pago'
                    ? `<button class="fbtn-sm fbtn-green" onclick="finPagarConta(${c.id})">✓</button> <button class="fbtn-sm fbtn-red" onclick="finExcluirConta(${c.id})">✕</button>`
                    : '—'}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="text-center text-muted py-3">Nenhuma conta.</td></tr>';
        document.getElementById('cp-tfoot').innerHTML = `<tr><td colspan="2">Totais:</td><td class="text-end" style="color:#e65100;">${fmtR(sumPend)}<br><span style="font-size:.68rem;">Pendente</span></td><td class="text-end" style="color:#c62828;">${fmtR(sumVenc)}<br><span style="font-size:.68rem;">Vencido</span></td><td class="text-end" style="color:#2e7d32;" colspan="3">${fmtR(sumPago)}<br><span style="font-size:.68rem;">Pago</span></td></tr>`;
        load.style.display = 'none'; tbl.style.display = '';
    } catch(e) { load.textContent = 'Erro: ' + e.message; }
};
window.finPagarConta = async id => { if(!confirm('Confirmar pagamento?')) return; const j = await apiPatch(`${API}/contas_pagar/${id}`); toast(j.message, j.status==='ok'); finCarregarContas(); };
window.finExcluirConta = async id => { if(!confirm('Excluir conta?')) return; const j = await apiDel(`${API}/contas_pagar/${id}`); toast(j.message, j.status==='ok'); finCarregarContas(); };
window.finSalvarContaPagar = async function() {
    const desc = qs('cp-m-desc').trim(); const val = qs('cp-m-val'); const venc = qs('cp-m-venc');
    if (!desc||!val||!venc) { toast('Preencha todos os campos.', false); return; }
    const j = await apiPost(`${API}/contas_pagar`, { loja_id: +qs('cp-m-loja'), descricao: desc, categoria: qs('cp-m-cat'), valor: +val, vencimento: venc, recorrente: document.getElementById('cp-m-rec').checked?1:0, observacoes: qs('cp-m-obs') });
    toast(j.message, j.status==='ok'); if (j.status==='ok') { finFecharModais(); finCarregarContas(); }
};

/* ══════════ CONTAS A RECEBER ══════════ */
window.finCarregarReceber = async function() {
    const [load, tbl, tbody] = ['cr-loading','cr-table','cr-tbody'].map(id => document.getElementById(id));
    load.style.display = ''; tbl.style.display = 'none';
    const url = `${API}/financeiro/contas_receber?mes=${qs('cr-mes')}${qs('cr-status')?'&status='+qs('cr-status'):''}${qs('cr-loja')?'&loja_id='+qs('cr-loja'):''}`;
    try {
        const j = await apiFetch(url);
        const lista = j.data || [];
        let sumP = 0, sumR = 0;
        tbody.innerHTML = lista.map(c => {
            if (c.status==='recebido') sumR += +c.valor; else sumP += +c.valor;
            return `<tr>
                <td>${c.descricao}</td>
                <td style="font-size:.78rem;">${c.cliente_nome||'—'}</td>
                <td>${c.categoria}</td>
                <td class="text-end">${fmtR(c.valor)}</td>
                <td>${fmtD(c.data_vencimento)}</td>
                <td><span class="badge-${c.status}">${c.status}</span></td>
                <td style="white-space:nowrap;">${c.status!=='recebido'&&c.status!=='cancelado'
                    ? `<button class="fbtn-sm fbtn-green" onclick="finBaixarReceber(${c.id})">✓ Rec.</button> <button class="fbtn-sm fbtn-red" onclick="finExcluirReceber(${c.id})">✕</button>`
                    : '—'}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="text-center text-muted py-3">Nenhuma conta.</td></tr>';
        document.getElementById('cr-tfoot').innerHTML = `<tr><td colspan="3">Pendente ${fmtR(sumP)}</td><td colspan="4" class="text-end">Recebido ${fmtR(sumR)}</td></tr>`;
        load.style.display = 'none'; tbl.style.display = '';
    } catch(e) { load.textContent = 'Erro: ' + e.message; }
};
window.finBaixarReceber = async id => { if(!confirm('Marcar como recebido?')) return; const j = await apiPatch(`${API}/financeiro/contas_receber/${id}`); toast(j.message, j.status==='ok'); finCarregarReceber(); };
window.finExcluirReceber = async id => { if(!confirm('Excluir?')) return; const j = await apiDel(`${API}/financeiro/contas_receber/${id}`); toast(j.message, j.status==='ok'); finCarregarReceber(); };
window.finSalvarReceber = async function() {
    const desc = qs('cr-m-desc').trim(); const val = qs('cr-m-val'); const venc = qs('cr-m-venc');
    if (!desc||!val||!venc) { toast('Preencha todos os campos.', false); return; }
    const j = await apiPost(`${API}/financeiro/contas_receber`, { loja_id: +qs('cr-m-loja'), descricao: desc, valor: +val, data_vencimento: venc, categoria: qs('cr-m-cat'), cliente_nome: qs('cr-m-cli'), observacoes: qs('cr-m-obs') });
    toast(j.message, j.status==='ok'); if (j.status==='ok') { finFecharModais(); finCarregarReceber(); }
};

/* ══════════ MOVIMENTAÇÕES (retiradas / despesas / transferências) ══════════ */
const MOV_CFG = {
    retirada:      { pfx:'rt', labelTipo:'Tipo', tbl:'rt-table', loading:'rt-loading', tbody:'rt-tbody', tfoot:'rt-tfoot', mesel:'rt-mes', lojaEl:'rt-loja' },
    despesa_extra: { pfx:'de', labelTipo:'Tipo', tbl:'de-table', loading:'de-loading', tbody:'de-tbody', tfoot:'de-tfoot', mesel:'de-mes', lojaEl:'de-loja' },
    transferencia: { pfx:'tr', labelTipo:'Destino', tbl:'tr-table', loading:'tr-loading', tbody:'tr-tbody', tfoot:null, mesel:'tr-mes', lojaEl:'' },
};
window.finCarregarMovs = async function(tipo) {
    const cfg   = MOV_CFG[tipo];
    const load  = document.getElementById(cfg.loading);
    const tbl   = document.getElementById(cfg.tbl);
    const tbody = document.getElementById(cfg.tbody);
    load.style.display = ''; tbl.style.display = 'none';
    const mes  = qs(cfg.mesel);
    const loja = cfg.lojaEl ? qs(cfg.lojaEl) : '';
    try {
        const j = await apiFetch(`${API}/financeiro/movimentacoes?tipo=${tipo}&mes=${mes}${loja?'&loja_id='+loja:''}`);
        const lista = j.data || [];
        let total = 0;
        tbody.innerHTML = tipo === 'transferencia'
            ? lista.map(m => {
                total += +m.valor;
                return `<tr>
                    <td>${fmtD(m.data)}</td>
                    <td>${m.descricao}</td>
                    <td>${m.loja_nome}</td>
                    <td>${m.loja_destino_nome || m.conta_bancaria || '—'}</td>
                    <td class="text-end" style="font-weight:700;">${fmtR(m.valor)}</td>
                    <td><button class="fbtn-sm fbtn-red" onclick="finExcluirMov(${m.id})">✕</button></td>
                </tr>`;
              }).join('')
            : lista.map(m => {
                total += +m.valor;
                return `<tr>
                    <td>${fmtD(m.data)}</td>
                    <td>${m.subtipo||'—'}</td>
                    <td>${m.descricao}</td>
                    <td style="font-size:.78rem;">${m.loja_nome}</td>
                    <td class="text-end" style="font-weight:700;">${fmtR(m.valor)}</td>
                    <td><button class="fbtn-sm fbtn-red" onclick="finExcluirMov(${m.id})">✕</button></td>
                </tr>`;
              }).join('');
        tbody.innerHTML = tbody.innerHTML || '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum lançamento.</td></tr>';
        if (cfg.tfoot) document.getElementById(cfg.tfoot).innerHTML = `<tr><td colspan="4">Total:</td><td class="text-end">${fmtR(total)}</td><td></td></tr>`;
        load.style.display = 'none'; tbl.style.display = '';
    } catch(e) { load.textContent = 'Erro: ' + e.message; }
};
window.finExcluirMov = async id => { if(!confirm('Excluir?')) return; const j = await apiDel(`${API}/financeiro/movimentacoes/${id}`); toast(j.message, j.status==='ok'); };

window.finSalvarMovimentacao = async function(tipo) {
    let body = {};
    if (tipo === 'retirada') {
        if (!qs('rt-m-desc')||!qs('rt-m-val')||!qs('rt-m-data')) { toast('Preencha os campos.', false); return; }
        body = { tipo, loja_id: +qs('rt-m-loja'), subtipo: qs('rt-m-sub'), descricao: qs('rt-m-desc'), valor: +qs('rt-m-val'), data: qs('rt-m-data'), conta_bancaria: qs('rt-m-banco'), observacoes: qs('rt-m-obs') };
    } else if (tipo === 'despesa_extra') {
        if (!qs('de-m-desc')||!qs('de-m-val')||!qs('de-m-data')) { toast('Preencha os campos.', false); return; }
        body = { tipo, loja_id: +qs('de-m-loja'), subtipo: qs('de-m-sub'), descricao: qs('de-m-desc'), valor: +qs('de-m-val'), data: qs('de-m-data'), observacoes: qs('de-m-obs') };
    } else if (tipo === 'transferencia') {
        if (!qs('tr-m-desc')||!qs('tr-m-val')||!qs('tr-m-data')) { toast('Preencha os campos.', false); return; }
        body = { tipo, loja_id: +qs('tr-m-loja-ori'), descricao: qs('tr-m-desc'), valor: +qs('tr-m-val'), data: qs('tr-m-data'), loja_destino_id: qs('tr-m-loja-dest')||null, conta_bancaria: qs('tr-m-banco'), observacoes: qs('tr-m-obs') };
    }
    const j = await apiPost(`${API}/financeiro/movimentacoes`, body);
    toast(j.message, j.status==='ok');
    if (j.status==='ok') { finFecharModais(); finCarregarMovs(tipo); }
};

/* ══════════ AUXILIARES CEASA ══════════ */
window.finCarregarAuxiliares = async function() {
    const [load, tbl, tbody] = ['ax-loading','ax-table','ax-tbody'].map(id => document.getElementById(id));
    load.style.display = ''; tbl.style.display = 'none';
    try {
        const j = await apiFetch(`${API}/financeiro/auxiliares_pagamentos?mes=${qs('ax-mes')}`);
        const lista = j.data || [];
        let total = 0;
        tbody.innerHTML = lista.map(p => {
            total += +p.valor;
            return `<tr>
                <td>${fmtD(p.data_pagamento)}</td>
                <td style="font-weight:600;">${p.colaborador_nome}</td>
                <td style="font-size:.76rem;">${fmtD(p.periodo_ini)} – ${fmtD(p.periodo_fim)}</td>
                <td><span class="badge-pendente">${p.tipo}</span></td>
                <td><span class="badge-pago">${p.forma}</span></td>
                <td class="text-end" style="font-weight:700;">${fmtR(p.valor)}</td>
                <td><button class="fbtn-sm fbtn-red" onclick="finExcluirAux(${p.id})">✕</button></td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="text-center text-muted py-3">Nenhum pagamento.</td></tr>';
        document.getElementById('ax-tfoot').innerHTML = `<tr><td colspan="5">Total:</td><td class="text-end">${fmtR(total)}</td><td></td></tr>`;
        load.style.display = 'none'; tbl.style.display = '';
    } catch(e) { load.textContent = 'Erro: ' + e.message; }
};
window.finExcluirAux = async id => { if(!confirm('Excluir?')) return; const j = await apiDel(`${API}/financeiro/auxiliares_pagamentos/${id}`); toast(j.message, j.status==='ok'); finCarregarAuxiliares(); };
window.finSalvarAuxiliar = async function() {
    if (!qs('ax-m-col')||!qs('ax-m-val')||!qs('ax-m-pi')||!qs('ax-m-pf')||!qs('ax-m-dpag')) { toast('Preencha todos os campos.', false); return; }
    const j = await apiPost(`${API}/financeiro/auxiliares_pagamentos`, { colaborador_id: +qs('ax-m-col'), valor: +qs('ax-m-val'), periodo_ini: qs('ax-m-pi'), periodo_fim: qs('ax-m-pf'), tipo: qs('ax-m-tipo'), forma: qs('ax-m-forma'), data_pagamento: qs('ax-m-dpag'), observacoes: qs('ax-m-obs') });
    toast(j.message, j.status==='ok'); if (j.status==='ok') { finFecharModais(); finCarregarAuxiliares(); }
};

/* ══════════ METAS ══════════ */
window.finCarregarMetas = async function() {
    const load = document.getElementById('mt-loading');
    const lista = document.getElementById('mt-lista');
    load.style.display = ''; lista.innerHTML = '';
    const mes  = qs('mt-mes') || '<?= date('Y-m') ?>';
    const loja = qs('mt-loja');
    try {
        const [jMeta, jDash] = await Promise.all([
            apiFetch(`${API}/financeiro/metas?mes=${mes}${loja?'&loja_id='+loja:''}`),
            apiFetch(`${API}/financeiro/dashboard?mes=${mes}${loja?'&loja_id='+loja:''}`),
        ]);
        const metas = jMeta.data || [];
        const d     = jDash.data || {};
        const realiz = { faturamento: d.receita_total, despesa_total: d.despesas_total };
        load.style.display = 'none';
        if (!metas.length) { lista.innerHTML = '<div class="text-muted small py-3 text-center">Nenhuma meta definida para este período.</div>'; return; }
        lista.innerHTML = metas.map(m => {
            const real = parseFloat(realiz[m.tipo] || 0);
            const meta = parseFloat(m.valor_meta);
            const pct  = meta > 0 ? Math.min(100, Math.round((real / meta) * 100)) : 0;
            const ok   = m.tipo === 'faturamento' ? pct >= 80 : pct <= 90;
            const cor  = ok ? '#2e7d32' : '#c62828';
            return `<div class="fin-card" style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div>
                        <div style="font-weight:700;font-size:.85rem;">${m.tipo === 'faturamento' ? '🎯 Meta de Faturamento' : (m.tipo === 'despesa_total' ? '📋 Orçamento Despesas' : '📋 Orçamento: ' + (m.categoria||''))} ${m.loja_nome ? '— ' + m.loja_nome : '(Todas)'}</div>
                        <div style="font-size:.73rem;color:#888;">${fmtM(m.mes_ref)}</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:.85rem;font-weight:800;color:${cor};">${fmtR(real)}</div>
                        <div style="font-size:.7rem;color:#888;">de ${fmtR(meta)}</div>
                    </div>
                </div>
                <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin-bottom:4px;">
                    <div style="height:100%;width:${pct}%;background:${cor};border-radius:4px;transition:width .5s;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.7rem;">
                    <span style="color:${cor};font-weight:700;">${pct}% ${m.tipo==='faturamento'?'atingido':'gasto'}</span>
                    <button class="fbtn-sm" style="background:#ffebee;color:#c62828;border:none;cursor:pointer;" onclick="finExcluirMeta(${m.id})">✕ Remover</button>
                </div>
            </div>`;
        }).join('');
    } catch(e) { load.textContent = 'Erro: ' + e.message; }
};
window.finExcluirMeta = async id => { if(!confirm('Remover meta?')) return; const j = await apiDel(`${API}/financeiro/metas/${id}`); toast(j.message, j.status==='ok'); finCarregarMetas(); };
window.finSalvarMeta = async function() {
    if (!qs('mt-m-mes')||!qs('mt-m-val')) { toast('Preencha todos os campos.', false); return; }
    const j = await apiPost(`${API}/financeiro/metas`, { mes_ref: qs('mt-m-mes'), tipo: qs('mt-m-tipo'), valor_meta: +qs('mt-m-val'), loja_id: qs('mt-m-loja')||null, categoria: qs('mt-m-cat')||null });
    toast(j.message, j.status==='ok'); if (j.status==='ok') { finFecharModais(); finCarregarMetas(); }
};

/* ══════════ FLUXO DE CAIXA ══════════ */
window.finCarregarFluxo = async function() {
    const [load, tbl] = ['flx-loading','flx-table'].map(id => document.getElementById(id));
    load.style.display = ''; tbl.style.display = 'none';
    const ano  = qs('flx-ano')  || '<?= date('Y') ?>';
    const loja = qs('flx-loja') || '';
    try {
        const j = await apiFetch(`${API}/financeiro/fluxo?ano=${ano}${loja?'&loja_id='+loja:''}`);
        const meses  = j.data?.meses  || [];
        const totais = j.data?.totais || {};
        const tbody  = document.getElementById('flx-tbody');
        tbody.innerHTML = meses.map(m => {
            const res = m.resultado;
            return `<tr>
                <td><strong>${fmtM(m.mes)}</strong></td>
                <td class="text-end">${fmtR(m.pdv)}</td>
                <td class="text-end">${fmtR(m.delivery)}</td>
                <td class="text-end"><strong>${fmtR(m.receita)}</strong></td>
                <td class="text-end">${fmtR(m.contas_pagar)}</td>
                <td class="text-end">${fmtR(m.movimentacoes)}</td>
                <td class="text-end">${fmtR(m.auxiliares)}</td>
                <td class="text-end"><strong>${fmtR(m.despesas)}</strong></td>
                <td class="text-end" style="font-weight:700;color:${res>=0?'#2e7d32':'#c62828'}">${fmtR(res)}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="9" class="text-center text-muted py-3">Sem dados.</td></tr>';
        const t = totais;
        document.getElementById('flx-tfoot').innerHTML = `<tr style="background:#f5f5f5;font-weight:700;">
            <td>TOTAL ANUAL</td><td colspan="2"></td>
            <td class="text-end">${fmtR(t.receita)}</td>
            <td colspan="3"></td>
            <td class="text-end">${fmtR(t.despesas)}</td>
            <td class="text-end" style="color:${t.resultado>=0?'#2e7d32':'#c62828'}">${fmtR(t.resultado)}</td>
        </tr>`;
        load.style.display = 'none'; tbl.style.display = '';
    } catch(e) { load.textContent = 'Erro: ' + e.message; }
};

/* ── Init ── */
(function init() {
    const painel = '<?= $painel_inicial ?>';
    const loaders = {
        visao: finCarregarVisao, alertas: finCarregarAlertas, pagar: finCarregarContas,
        receber: finCarregarReceber, retiradas: () => finCarregarMovs('retirada'),
        auxiliares: finCarregarAuxiliares, despesas: () => finCarregarMovs('despesa_extra'),
        transferencias: () => finCarregarMovs('transferencia'),
        metas: finCarregarMetas, fluxo: finCarregarFluxo,
    };
    loaders[painel]?.();
})();

})();
</script>
