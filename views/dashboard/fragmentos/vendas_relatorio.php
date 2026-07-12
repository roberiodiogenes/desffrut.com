<?php
/**
 * Desffrut — Fragmento Dashboard: Vendas por Período
 * Relatório interativo: PDV + Delivery, ticket médio, top produtos, formas de pagamento
 */
$role      = $u['role'];
$eh_admin  = $role === 'super_admin';
$loja_id_u = (int) ($u['loja_id'] ?? 0);
$lojas_vr  = db()->query("SELECT id, nome FROM lojas WHERE ativo=1 ORDER BY nome")->fetchAll();
?>

<style data-frag="vendas_relatorio">
.vr-wrap { padding:0; }

/* ── Filtros ── */
.vr-filtros {
    display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap;
    background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px;
    padding:12px 16px; margin-bottom:20px;
}
.vr-filtros label { font-size:.72rem; font-weight:700; color:#6b7280;
    text-transform:uppercase; letter-spacing:.3px; margin-bottom:3px; display:block; }
.vr-filtros input, .vr-filtros select {
    border:1.5px solid #e0e0e0; border-radius:7px;
    padding:6px 10px; font-size:.83rem; color:#333; background:#fff; }
.vr-filtros input:focus, .vr-filtros select:focus { border-color:#2e7d32; outline:none; }

/* ── KPI bar ── */
.vr-kpi-bar { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.vr-kpi {
    flex:1; min-width:120px; background:#fff; border:1px solid #e5e7eb;
    border-radius:10px; padding:14px 16px; position:relative; overflow:hidden;
}
.vr-kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background:var(--vr-accent,#e0e0e0); }
.vr-kpi.verde    { --vr-accent:#16a34a; }
.vr-kpi.azul     { --vr-accent:#2563eb; }
.vr-kpi.amarelo  { --vr-accent:#d97706; }
.vr-kpi.cinza    { --vr-accent:#9ca3af; }
.vr-kpi.roxo     { --vr-accent:#7c3aed; }
.vr-kpi-num      { font-size:1.2rem; font-weight:800; color:#111; line-height:1.1; margin-bottom:2px; }
.vr-kpi-num.verde  { color:#16a34a; }
.vr-kpi-num.azul   { color:#2563eb; }
.vr-kpi-num.amarelo{ color:#d97706; }
.vr-kpi-num.roxo   { color:#7c3aed; }
.vr-kpi-label { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#9ca3af; }
.vr-kpi-sub   { font-size:.68rem; color:#6b7280; margin-top:2px; }

/* ── Título de seção ── */
.vr-section { font-size:.72rem; font-weight:700; text-transform:uppercase;
    letter-spacing:.5px; color:#9ca3af; margin-bottom:10px;
    padding-bottom:5px; border-bottom:1px solid #e5e7eb; }

/* ── Grid de seções ── */
.vr-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:20px; }
@media (max-width:700px) { .vr-grid-2 { grid-template-columns:1fr; } }

/* ── Top produtos ── */
.vr-top-prod { }
.vr-prod-row {
    display:flex; align-items:center; gap:10px;
    padding:8px 0; border-bottom:1px solid #f0f0f0;
}
.vr-prod-row:last-child { border-bottom:none; }
.vr-prod-rank {
    font-size:.72rem; font-weight:800; color:#9ca3af;
    min-width:22px; text-align:center;
}
.vr-prod-rank.top3 { color:#d97706; }
.vr-prod-nome  { font-size:.83rem; font-weight:600; color:#222; flex:1; }
.vr-prod-cat   { font-size:.65rem; color:#9ca3af; }
.vr-prod-bar-wrap { flex:1; height:6px; background:#f0f0f0; border-radius:3px; overflow:hidden; }
.vr-prod-bar   { height:100%; background:#16a34a; border-radius:3px; transition:width .5s; }
.vr-prod-val   { font-size:.8rem; font-weight:700; color:#16a34a; white-space:nowrap; min-width:90px; text-align:right; }

/* ── Formas de pagamento ── */
.vr-pag-row { display:flex; gap:8px; flex-wrap:wrap; }
.vr-pag-chip {
    display:flex; flex-direction:column; align-items:center;
    padding:10px 12px; border-radius:10px;
    border:1.5px solid var(--p-cor,#e5e7eb);
    background:var(--p-bg,#f9fafb);
    flex:1; min-width:80px;
}
.vr-pag-icon  { font-size:1.2rem; margin-bottom:2px; }
.vr-pag-label { font-size:.62rem; font-weight:700; text-transform:uppercase; color:#6b7280; }
.vr-pag-total { font-size:.88rem; font-weight:800; color:var(--p-cor,#333); }
.vr-pag-pct   { font-size:.64rem; color:#9ca3af; }
.vr-pag-bbar  { width:100%; height:3px; background:#e5e7eb; border-radius:2px; margin-top:5px; overflow:hidden; }
.vr-pag-bfill { height:100%; background:var(--p-cor,#9ca3af); border-radius:2px; transition:width .5s; }

/* ── Tabela por dia ── */
.vr-table-wrap { overflow-x:auto; border:1px solid #e5e7eb; border-radius:10px; }
.vr-table { width:100%; border-collapse:collapse; font-size:.81rem; }
.vr-table thead th {
    background:#f9fafb; padding:9px 12px; font-size:.68rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px; color:#6b7280;
    border-bottom:2px solid #e0e0e0; white-space:nowrap; }
.vr-table thead th.text-end { text-align:right; }
.vr-table tbody td { padding:9px 12px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.vr-table tbody tr:last-child td { border-bottom:none; }
.vr-table tbody tr:hover td { background:#f9fafb; }
.vr-table tfoot td { background:#f9fafb; font-weight:700; border-top:2px solid #e0e0e0; padding:9px 12px; }

/* ── Vazio / loading ── */
.vr-empty { text-align:center; color:#9ca3af; padding:48px 20px; font-size:.85rem; }

@media print {
    .vr-filtros, .btn { display:none !important; }
    .vr-table-wrap, .vr-grid-2 { page-break-inside:avoid; }
}
</style>

<div class="vr-wrap">

    <div class="vr-filtros">
        <?php if ($eh_admin): ?>
        <div>
            <label>Loja</label>
            <select id="vr-loja">
                <option value="">Todas as lojas</option>
                <?php foreach ($lojas_vr as $l): ?>
                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label>De</label>
            <input type="date" id="vr-de" value="<?= date('Y-m-01') ?>">
        </div>
        <div>
            <label>Até</label>
            <input type="date" id="vr-ate" value="<?= date('Y-m-d') ?>">
        </div>
        <div style="align-self:flex-end;display:flex;gap:6px;flex-wrap:wrap;">
            <button class="btn btn-success btn-sm px-4" onclick="vrCarregar()">🔍 Relatório</button>
            <button id="vr-csv-btn" class="btn btn-outline-secondary btn-sm"
                    style="display:none;" onclick="vrExportarCSV()">⬇️ CSV</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">🖨️</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="vrAtalho(7)">7d</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="vrAtalho(30)">30d</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="vrAtalho(0)">Mês</button>
        </div>
    </div>

    <!-- KPIs -->
    <div id="vr-kpis" style="display:none;">
        <div class="vr-section">Totais do período</div>
        <div class="vr-kpi-bar">
            <div class="vr-kpi verde">
                <div class="vr-kpi-num verde" id="vr-k-fat-total">—</div>
                <div class="vr-kpi-label">Faturamento Total</div>
                <div class="vr-kpi-sub" id="vr-k-fat-sub"></div>
            </div>
            <div class="vr-kpi verde">
                <div class="vr-kpi-num verde" id="vr-k-fat-pdv">—</div>
                <div class="vr-kpi-label">PDV (Caixa)</div>
                <div class="vr-kpi-sub" id="vr-k-qtd-pdv"></div>
            </div>
            <div class="vr-kpi azul">
                <div class="vr-kpi-num azul" id="vr-k-fat-del">—</div>
                <div class="vr-kpi-label">Delivery</div>
                <div class="vr-kpi-sub" id="vr-k-qtd-del"></div>
            </div>
            <div class="vr-kpi amarelo">
                <div class="vr-kpi-num amarelo" id="vr-k-ticket">—</div>
                <div class="vr-kpi-label">Ticket Médio PDV</div>
            </div>
            <div class="vr-kpi cinza">
                <div class="vr-kpi-num" id="vr-k-dias">—</div>
                <div class="vr-kpi-label">Dias com Vendas</div>
            </div>
        </div>
    </div>

    <!-- Grid: Top Produtos + Formas de Pagamento -->
    <div id="vr-grid" style="display:none;" class="vr-grid-2">
        <!-- Top Produtos -->
        <div>
            <div class="vr-section">🏆 Top 10 Produtos</div>
            <div id="vr-top-prod"><div class="vr-empty" style="padding:20px;">Carregando…</div></div>
        </div>
        <!-- Formas de Pagamento -->
        <div>
            <div class="vr-section">💳 Formas de Pagamento</div>
            <div class="vr-pag-row" id="vr-pag-chips"></div>
            <div style="margin-top:14px;">
                <div class="vr-section">📦 Canal de Venda</div>
                <div class="vr-kpi-bar" style="margin-bottom:0;">
                    <div class="vr-kpi verde">
                        <div class="vr-kpi-num verde" id="vr-pct-pdv">—</div>
                        <div class="vr-kpi-label">PDV</div>
                    </div>
                    <div class="vr-kpi azul">
                        <div class="vr-kpi-num azul" id="vr-pct-del">—</div>
                        <div class="vr-kpi-label">Delivery</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela por dia -->
    <div id="vr-tabela-area">
        <div class="vr-empty">Selecione o período e clique em <strong>Relatório</strong>.</div>
    </div>

</div>

<script>
window.vrUI = (function () {
    const API   = APP.api;
    const tk    = () => sessionStorage.getItem('desffrut_token') || '';
    const hdrs  = () => ({ 'Authorization': 'Bearer ' + tk() });
    const fmtR  = v => 'R$ ' + parseFloat(v||0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    const fmtN  = v => parseInt(v||0).toLocaleString('pt-BR');
    const diasSem = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

    const PAG = {
        dinheiro:{ label:'Dinheiro', icon:'💵', cor:'#16a34a', bg:'#f0fdf4' },
        debito:  { label:'Débito',   icon:'💳', cor:'#2563eb', bg:'#eff6ff' },
        credito: { label:'Crédito',  icon:'💳', cor:'#7c3aed', bg:'#f5f3ff' },
        pix:     { label:'Pix',      icon:'⚡', cor:'#0891b2', bg:'#ecfeff' },
        pontos:  { label:'Pontos',   icon:'⭐', cor:'#d97706', bg:'#fffbeb' },
        misto:   { label:'Misto',    icon:'🔀', cor:'#6b7280', bg:'#f9fafb' },
    };

    let _dadosDia = [];

    window.vrAtalho = function (dias) {
        const ate = new Date();
        const de  = dias === 0
            ? new Date(ate.getFullYear(), ate.getMonth(), 1)
            : new Date(ate.getTime() - (dias - 1) * 86400000);
        const fmt = d => d.toISOString().slice(0, 10);
        document.getElementById('vr-de').value  = fmt(de);
        document.getElementById('vr-ate').value = fmt(ate);
        vrCarregar();
    };

    window.vrCarregar = async function () {
        const loja = document.getElementById('vr-loja')?.value || '';
        const de   = document.getElementById('vr-de').value;
        const ate  = document.getElementById('vr-ate').value;
        const elT  = document.getElementById('vr-tabela-area');
        elT.innerHTML = '<div class="vr-empty">Carregando…</div>';
        document.getElementById('vr-kpis').style.display = 'none';
        document.getElementById('vr-grid').style.display = 'none';
        document.getElementById('vr-csv-btn').style.display = 'none';

        const params = new URLSearchParams({data_ini: de, data_fim: ate});
        if (loja) params.set('loja_id', loja);

        try {
            // Busca em paralelo: vendas_periodo + top_produtos + pagamentos
            const [rVen, rTop, rPag] = await Promise.all([
                fetch(`${API}/relatorios/vendas_periodo?${params}`, {headers: hdrs()}),
                fetch(`${API}/relatorios/top_produtos?${params}&limit=10`, {headers: hdrs()}),
                fetch(`${API}/caixas/pagamentos?${params}`, {headers: hdrs()}),
            ]);
            const [jVen, jTop, jPag] = await Promise.all([rVen.json(), rTop.json(), rPag.json()]);

            const totais  = jVen.data?.totais || {};
            const porDia  = jVen.data?.por_dia || [];
            const produtos = jTop.data || [];
            const pagtos   = jPag.data || [];

            if (!porDia.length && !totais.qtd_vendas) {
                elT.innerHTML = '<div class="vr-empty">Nenhuma venda no período selecionado.</div>';
                return;
            }

            // ── KPIs ──
            const fatTotal = (totais.receita_pdv || 0) + (totais.receita_delivery || 0);
            document.getElementById('vr-k-fat-total').textContent = fmtR(fatTotal);
            document.getElementById('vr-k-fat-sub').textContent   = `${porDia.length} dia(s) com mov.`;
            document.getElementById('vr-k-fat-pdv').textContent   = fmtR(totais.receita_pdv);
            document.getElementById('vr-k-qtd-pdv').textContent   = `${fmtN(totais.qtd_vendas)} vendas`;
            document.getElementById('vr-k-fat-del').textContent   = fmtR(totais.receita_delivery);
            document.getElementById('vr-k-qtd-del').textContent   = `${fmtN(totais.qtd_pedidos)} pedidos`;
            document.getElementById('vr-k-ticket').textContent    = fmtR(totais.ticket_medio);
            document.getElementById('vr-k-dias').textContent      = porDia.length;
            document.getElementById('vr-kpis').style.display = '';

            const pctPDV = fatTotal > 0 ? ((totais.receita_pdv / fatTotal) * 100).toFixed(0) : 0;
            const pctDel = fatTotal > 0 ? ((totais.receita_delivery / fatTotal) * 100).toFixed(0) : 0;
            document.getElementById('vr-pct-pdv').textContent = pctPDV + '%';
            document.getElementById('vr-pct-del').textContent = pctDel + '%';

            // ── Top produtos ──
            const maxRec = Math.max(...produtos.map(p => parseFloat(p.total_receita || 0)), 1);
            document.getElementById('vr-top-prod').innerHTML = produtos.length
                ? produtos.map((p, i) => {
                    const rec = parseFloat(p.total_receita || 0);
                    const pct = Math.round((rec / maxRec) * 100);
                    return `
                    <div class="vr-prod-row">
                        <span class="vr-prod-rank ${i < 3 ? 'top3' : ''}">${i + 1}º</span>
                        <div style="flex:1;min-width:0;">
                            <div class="vr-prod-nome">${p.nome}</div>
                            <div class="vr-prod-cat">${p.categoria} · ${parseFloat(p.total_qtd||0).toFixed(3)} ${p.unidade_medida}</div>
                        </div>
                        <div class="vr-prod-bar-wrap">
                            <div class="vr-prod-bar" style="width:${pct}%;"></div>
                        </div>
                        <div class="vr-prod-val">${fmtR(rec)}</div>
                    </div>`;
                }).join('')
                : '<div class="vr-empty" style="padding:16px;">Sem dados de itens para o período.</div>';

            // ── Formas de pagamento ──
            const totalPag = pagtos.reduce((s, p) => s + parseFloat(p.total || 0), 0);
            document.getElementById('vr-pag-chips').innerHTML = pagtos.map(p => {
                const cfg = PAG[p.forma_pagamento] || PAG.misto;
                const tot = parseFloat(p.total || 0);
                const pct = totalPag > 0 ? Math.round((tot / totalPag) * 100) : 0;
                return `
                <div class="vr-pag-chip" style="--p-cor:${cfg.cor};--p-bg:${cfg.bg};">
                    <span class="vr-pag-icon">${cfg.icon}</span>
                    <span class="vr-pag-label">${cfg.label}</span>
                    <span class="vr-pag-total">${fmtR(tot)}</span>
                    <span class="vr-pag-pct">${pct}%</span>
                    <div class="vr-pag-bbar"><div class="vr-pag-bfill" style="width:${pct}%;"></div></div>
                </div>`;
            }).join('') || '<span style="color:#9ca3af;font-size:.82rem;">Sem dados de pagamento.</span>';

            document.getElementById('vr-grid').style.display = '';

            // ── Tabela por dia ──
            _dadosDia = porDia;
            const maxFat = Math.max(...porDia.map(d => parseFloat(d.fat_pdv || 0)), 1);
            let gFatPDV = 0, gFatDel = 0, gQtdPDV = 0, gQtdDel = 0;
            const rows = porDia.map(d => {
                const fPDV = parseFloat(d.fat_pdv || 0);
                const fDel = parseFloat(d.fat_del || 0);
                const fTot = fPDV + fDel;
                gFatPDV += fPDV; gFatDel += fDel;
                gQtdPDV += parseInt(d.qtd_pdv || 0);
                gQtdDel += parseInt(d.qtd_del || 0);
                const dt  = new Date(d.data + 'T00:00');
                const dow = diasSem[dt.getDay()];
                const fds = dt.getDay() === 0 || dt.getDay() === 6;
                const pct = Math.round((fPDV / maxFat) * 100);
                return `
                <tr style="${fds ? 'background:#fffbeb;' : ''}">
                    <td style="white-space:nowrap;">
                        <span class="fw-semibold">${dt.toLocaleDateString('pt-BR')}</span>
                        <span class="badge ms-1" style="background:${fds?'#fef3c7':'#f3f4f6'};color:${fds?'#92400e':'#6b7280'};font-size:.63rem;">${dow}</span>
                    </td>
                    <td class="text-end">${d.qtd_pdv || 0}</td>
                    <td class="text-end fw-semibold" style="color:#16a34a;">
                        ${fmtR(fPDV)}
                        <div style="height:3px;background:#e5e7eb;border-radius:2px;margin-top:3px;">
                            <div style="height:100%;width:${pct}%;background:#16a34a;border-radius:2px;"></div>
                        </div>
                    </td>
                    <td class="text-end">${d.qtd_del || 0}</td>
                    <td class="text-end" style="color:#2563eb;">${fmtR(fDel)}</td>
                    <td class="text-end fw-semibold">${fmtR(fTot)}</td>
                </tr>`;
            }).join('');

            elT.innerHTML = `
                <div class="vr-section">Faturamento por dia</div>
                <div class="vr-table-wrap">
                <table class="vr-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th class="text-end">Vendas PDV</th>
                            <th class="text-end">Fat. PDV</th>
                            <th class="text-end">Pedidos Del.</th>
                            <th class="text-end">Fat. Delivery</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tfoot>
                        <tr>
                            <td style="color:#6b7280;font-size:.75rem;">TOTAL ${porDia.length} dia(s):</td>
                            <td class="text-end">${fmtN(gQtdPDV)}</td>
                            <td class="text-end" style="color:#16a34a;">${fmtR(gFatPDV)}</td>
                            <td class="text-end">${fmtN(gQtdDel)}</td>
                            <td class="text-end" style="color:#2563eb;">${fmtR(gFatDel)}</td>
                            <td class="text-end">${fmtR(gFatPDV + gFatDel)}</td>
                        </tr>
                    </tfoot>
                </table>
                </div>`;

            document.getElementById('vr-csv-btn').style.display = '';

        } catch (e) {
            elT.innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
        }
    };

    window.vrExportarCSV = function () {
        if (!_dadosDia.length) return;
        const headers = ['Data','Dia','Vendas PDV','Faturamento PDV (R$)','Pedidos Del.','Faturamento Delivery (R$)','Total (R$)'];
        const dias_ = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        const rows = _dadosDia.map(d => {
            const dt  = new Date(d.data + 'T00:00');
            const pdv = parseFloat(d.fat_pdv || 0);
            const del = parseFloat(d.fat_del || 0);
            return [dt.toLocaleDateString('pt-BR'), dias_[dt.getDay()],
                    d.qtd_pdv||0, pdv.toFixed(2), d.qtd_del||0, del.toFixed(2), (pdv+del).toFixed(2)];
        });
        const sep = ';';
        const csv = ['﻿' + headers.join(sep), ...rows.map(r => r.join(sep))].join('\n');
        const a   = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8;'})),
            download: `vendas_${document.getElementById('vr-de').value}_${document.getElementById('vr-ate').value}.csv`,
        });
        a.click();
        URL.revokeObjectURL(a.href);
    };

    // Auto-carrega o mês atual
    vrCarregar();

    return {};
})();
</script>
