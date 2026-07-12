<?php
/**
 * Desffrut — Fragmento: BI / Lucro Líquido / DRE (Fase 7)
 */
$pdo   = db();
$lojas = $pdo->query("SELECT id, nome FROM lojas WHERE ativo=1 ORDER BY nome")->fetchAll();
?>
<style>
.bi-wrap { padding:16px; }
.bi-tabs { display:flex; gap:4px; margin-bottom:16px; flex-wrap:wrap; }
.bi-tab-btn {
    padding:7px 16px; border:1px solid #ddd; border-radius:6px;
    background:#fff; font-size:.82rem; cursor:pointer; color:#555;
    transition:all .15s;
}
.bi-tab-btn.active { background:#00695c; color:#fff; border-color:#00695c; }
.bi-tab-btn:hover:not(.active) { background:#f5f5f5; }
.bi-panel { display:none; }
.bi-panel.active { display:block; }

.bi-card {
    background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.08);
    padding:16px; margin-bottom:14px;
}
.bi-card-title { font-weight:600; font-size:.95rem; color:#333; margin-bottom:12px;
                 display:flex; align-items:center; justify-content:space-between; }

.bi-kpi-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.bi-kpi {
    flex:1; min-width:130px; background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.08); padding:14px 16px; text-align:center;
}
.bi-kpi .kv { font-size:1.4rem; font-weight:700; color:#00695c; }
.bi-kpi .kl { font-size:.72rem; color:#888; margin-top:3px; }
.kv-red   { color:#c62828 !important; }
.kv-blue  { color:#1565c0 !important; }
.kv-green { color:#2e7d32 !important; }
.kv-amber { color:#e65100 !important; }

.chart-container { position:relative; height:300px; }

/* DRE */
.dre-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.dre-table tr td { padding:8px 12px; border-bottom:1px solid #f0f0f0; }
.dre-table tr.dre-grupo td { background:#f5f5f5; font-weight:700; color:#333; font-size:.85rem; }
.dre-table tr.dre-total-linha td { background:#e8f5e9; font-weight:700; color:#2e7d32; font-size:.87rem; }
.dre-table tr.dre-negativo td { background:#ffebee; font-weight:700; color:#c62828; font-size:.87rem; }
.dre-table tr.dre-destaque td { background:#fff3e0; font-weight:700; color:#e65100; font-size:.87rem; }
.dre-label-col { width:60%; }
.dre-val-col { text-align:right; min-width:140px; }
.dre-pct-col { text-align:right; min-width:80px; color:#888; font-size:.76rem; }

.filtro-bar-bi { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px; }
.filtro-bar-bi label { font-size:.76rem; color:#666; }
.filtro-bar-bi select, .filtro-bar-bi input {
    padding:6px 10px; border:1px solid #ccc; border-radius:6px; font-size:.82rem;
}

.top-prod-item {
    display:grid; grid-template-columns:auto 1fr 120px 100px;
    gap:8px; align-items:center; padding:6px 4px;
    border-bottom:1px solid #f5f5f5; font-size:.81rem;
}
.top-prod-rank { font-weight:700; color:#888; width:22px; text-align:center; }
.top-prod-bar {
    background:#e8f5e9; border-radius:4px; height:10px; overflow:hidden;
}
.top-prod-bar-fill { background:#2e7d32; height:100%; border-radius:4px; transition:width .6s; }
</style>

<div class="bi-wrap">
    <div class="bi-tabs">
        <button class="bi-tab-btn active" onclick="biTab('overview',this)">📈 Visão Geral</button>
        <button class="bi-tab-btn" onclick="biTab('graficos',this)">📊 Gráficos</button>
        <button class="bi-tab-btn" onclick="biTab('dre',this)">📄 DRE</button>
        <button class="bi-tab-btn" onclick="biTab('produtos',this)">🥦 Top Produtos</button>
    </div>

    <!-- ═══════════════ PAINEL: OVERVIEW ════════════════════ -->
    <div class="bi-panel active" id="bi-panel-overview">
        <div class="filtro-bar-bi" style="margin-bottom:14px;">
            <?php if(count($lojas)>1): ?>
            <div>
                <label>Loja</label>
                <select id="bi-ov-loja" onchange="biCarregarOverview()">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div id="bi-ov-loading" class="text-center py-4 text-muted small">Carregando…</div>
        <div id="bi-ov-content" style="display:none">
            <div class="bi-kpi-row">
                <div class="bi-kpi"><div class="kv kv-green" id="bi-ov-receita">—</div><div class="kl">Receita Total</div></div>
                <div class="bi-kpi"><div class="kv kv-blue"  id="bi-ov-pdv">—</div><div class="kl">PDV</div></div>
                <div class="bi-kpi"><div class="kv kv-amber" id="bi-ov-del">—</div><div class="kl">Delivery</div></div>
                <div class="bi-kpi"><div class="kv" id="bi-ov-desp">—</div><div class="kl">Despesas</div></div>
                <div class="bi-kpi"><div class="kv" id="bi-ov-res">—</div><div class="kl">Resultado</div></div>
                <div class="bi-kpi"><div class="kv kv-amber" id="bi-ov-pend">—</div><div class="kl">Contas Pendentes</div></div>
                <div class="bi-kpi"><div class="kv kv-blue"  id="bi-ov-func">—</div><div class="kl">Funcionários Ativos</div></div>
            </div>
            <p style="font-size:.73rem;color:#aaa;text-align:right;" id="bi-ov-mes"></p>
        </div>
    </div>

    <!-- ═══════════════ PAINEL: GRÁFICOS ════════════════════ -->
    <div class="bi-panel" id="bi-panel-graficos">
        <div class="filtro-bar-bi">
            <div>
                <label>Ano</label>
                <input type="number" id="bi-gr-ano" value="<?= date('Y') ?>" min="2020" max="2099"
                       style="width:85px;" onchange="biCarregarGraficos()">
            </div>
            <?php if(count($lojas)>1): ?>
            <div>
                <label>Loja</label>
                <select id="bi-gr-loja" onchange="biCarregarGraficos()">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="bi-card">
            <div class="bi-card-title">Faturamento Mensal</div>
            <div class="chart-container"><canvas id="bi-chart-fat"></canvas></div>
        </div>
        <div class="bi-card">
            <div class="bi-card-title">Receita vs Despesas</div>
            <div class="chart-container"><canvas id="bi-chart-res"></canvas></div>
        </div>
    </div>

    <!-- ═══════════════ PAINEL: DRE ═════════════════════════ -->
    <div class="bi-panel" id="bi-panel-dre">
        <div class="filtro-bar-bi">
            <div>
                <label>Ano</label>
                <input type="number" id="bi-dre-ano" value="<?= date('Y') ?>" min="2020" max="2099"
                       style="width:85px;" onchange="biCarregarDRE()">
            </div>
            <?php if(count($lojas)>1): ?>
            <div>
                <label>Loja</label>
                <select id="bi-dre-loja" onchange="biCarregarDRE()">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="bi-card">
            <div class="bi-card-title">
                DRE Simplificado
                <span id="bi-dre-ano-label" style="font-size:.8rem;color:#888;font-weight:400;"></span>
            </div>
            <div id="bi-dre-loading" class="text-center py-4 text-muted small">Carregando…</div>
            <table class="dre-table" id="bi-dre-table" style="display:none">
                <colgroup><col class="dre-label-col"><col class="dre-val-col"><col class="dre-pct-col"></colgroup>
                <tbody id="bi-dre-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- ═══════════════ PAINEL: TOP PRODUTOS ════════════════ -->
    <div class="bi-panel" id="bi-panel-produtos">
        <div class="filtro-bar-bi">
            <div>
                <label>Mês</label>
                <input type="month" id="bi-tp-mes" value="<?= date('Y-m') ?>" onchange="biCarregarTopProd()">
            </div>
            <?php if(count($lojas)>1): ?>
            <div>
                <label>Loja</label>
                <select id="bi-tp-loja" onchange="biCarregarTopProd()">
                    <option value="">Todas</option>
                    <?php foreach($lojas as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="bi-card">
            <div class="bi-card-title">Top 10 Produtos — Receita</div>
            <div id="bi-tp-loading" class="text-center py-4 text-muted small">Carregando…</div>
            <div id="bi-tp-lista"></div>
        </div>
    </div>

</div><!-- /bi-wrap -->

<script>
(function(){
const API = window.APP?.api || '/desffrut.com/api/v1';
const MESES = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
let _chartFat = null, _chartRes = null;

function fmt(v){ return 'R$ '+parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function fmtMes(ym){ const p=ym.split('-'); return MESES[parseInt(p[1])-1]; }
function qs(id){ return document.getElementById(id)?.value||''; }
function hdrs(){ return { 'Authorization': 'Bearer ' + (sessionStorage.getItem('desffrut_token')||'') }; }
function apiFetch(url){ return fetch(url, { headers: hdrs() }).then(r=>r.json()); }

/* Carrega Chart.js dinamicamente (scripts externos são ignorados pelo loader de fragmento) */
function loadChartJs(cb) {
    if (window.Chart) { cb(); return; }
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
    s.onload = cb;
    s.onerror = () => console.error('Falha ao carregar Chart.js');
    document.head.appendChild(s);
}

/* ─ Tabs ──────────────────────────────────────────────────── */
window.biTab = function(panel,btn){
    document.querySelectorAll('.bi-panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.bi-tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById('bi-panel-'+panel).classList.add('active');
    btn.classList.add('active');
    if(panel==='overview') biCarregarOverview();
    if(panel==='graficos') biCarregarGraficos();
    if(panel==='dre')      biCarregarDRE();
    if(panel==='produtos') biCarregarTopProd();
};

/* ─ Overview ─────────────────────────────────────────────── */
window.biCarregarOverview = async function(){
    const load    = document.getElementById('bi-ov-loading');
    const content = document.getElementById('bi-ov-content');
    const loja    = qs('bi-ov-loja');
    load.style.display=''; content.style.display='none';
    try {
        const j = await apiFetch(`${API}/bi/overview${loja?'?loja_id='+loja:''}`);
        const d = j.data||{};
        document.getElementById('bi-ov-receita').textContent = fmt(d.receita_total);
        document.getElementById('bi-ov-pdv').textContent     = fmt(d.receita_pdv);
        document.getElementById('bi-ov-del').textContent     = fmt(d.receita_delivery);
        document.getElementById('bi-ov-desp').textContent    = fmt(d.despesas_mes);
        const res = d.resultado_mes||0;
        const elRes = document.getElementById('bi-ov-res');
        elRes.textContent = fmt(res);
        elRes.className   = 'kv '+(res>=0?'kv-green':'kv-red');
        document.getElementById('bi-ov-pend').textContent = d.contas_pendentes||'0';
        document.getElementById('bi-ov-func').textContent = d.funcionarios_ativos||'0';
        document.getElementById('bi-ov-mes').textContent  = 'Dados do mês: '+(d.mes||'');
        load.style.display='none'; content.style.display='block';
    } catch(e){ load.textContent='Erro: '+e.message; }
};

/* ─ Gráficos ─────────────────────────────────────────────── */
window.biCarregarGraficos = function(){
    loadChartJs(_biRenderGraficos);
};

async function _biRenderGraficos(){
    const ano  = qs('bi-gr-ano');
    const loja = qs('bi-gr-loja');
    let url = `${API}/bi/faturamento?ano=${ano}`;
    if(loja) url += '&loja_id='+loja;
    let urlD = `${API}/bi/despesas?ano=${ano}`;
    if(loja) urlD += '&loja_id='+loja;
    try {
        const [rf,rd] = await Promise.all([apiFetch(url), apiFetch(urlD)]);
        const fat  = rf.data||[];
        const desp = rd.data||[];

        if (!fat.length) {
            document.getElementById('bi-chart-fat').closest('.bi-card').insertAdjacentHTML(
                'beforeend', '<p class="text-center text-muted small py-2">Sem dados de faturamento para o ano selecionado.</p>'
            );
            return;
        }

        // Agrupa despesas por mês
        const dMap = {};
        desp.forEach(d=>{ dMap[d.mes]=(dMap[d.mes]||0)+parseFloat(d.total); });

        const labels = fat.map(m=>fmtMes(m.mes));
        const recPDV = fat.map(m=>parseFloat(m.receita_pdv));
        const recDel = fat.map(m=>parseFloat(m.receita_delivery));
        const despArr= fat.map(m=>dMap[m.mes]||0);

        // Gráfico 1: Faturamento PDV + Delivery (barras empilhadas)
        if(_chartFat) _chartFat.destroy();
        const ctx1 = document.getElementById('bi-chart-fat').getContext('2d');
        _chartFat = new Chart(ctx1,{
            type:'bar',
            data:{
                labels,
                datasets:[
                    { label:'PDV',     data:recPDV, backgroundColor:'#4caf50', stack:'receita' },
                    { label:'Delivery',data:recDel, backgroundColor:'#2196f3', stack:'receita' },
                ]
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{ position:'bottom', labels:{font:{size:11}} } },
                scales:{
                    x:{ stacked:true },
                    y:{ stacked:true, ticks:{ callback:v=>'R$'+v.toLocaleString('pt-BR') } }
                }
            }
        });

        // Gráfico 2: Receita Total vs Despesas (linhas)
        const recTotal = fat.map(m=>parseFloat(m.receita_total));
        if(_chartRes) _chartRes.destroy();
        const ctx2 = document.getElementById('bi-chart-res').getContext('2d');
        _chartRes = new Chart(ctx2,{
            type:'line',
            data:{
                labels,
                datasets:[
                    { label:'Receita', data:recTotal, borderColor:'#4caf50', backgroundColor:'rgba(76,175,80,.1)', tension:.3, fill:true },
                    { label:'Despesas',data:despArr,  borderColor:'#f44336', backgroundColor:'rgba(244,67,54,.07)', tension:.3, fill:true },
                ]
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{ position:'bottom', labels:{font:{size:11}} } },
                scales:{ y:{ ticks:{ callback:v=>'R$'+v.toLocaleString('pt-BR') } } }
            }
        });
    } catch(e){ console.error('BI gráficos erro',e); }
}

/* ─ DRE ──────────────────────────────────────────────────── */
window.biCarregarDRE = async function(){
    const load   = document.getElementById('bi-dre-loading');
    const tbl    = document.getElementById('bi-dre-table');
    const tbody  = document.getElementById('bi-dre-tbody');
    const ano    = qs('bi-dre-ano');
    const loja   = qs('bi-dre-loja');
    load.style.display=''; tbl.style.display='none';
    document.getElementById('bi-dre-ano-label').textContent = ano;
    let url = `${API}/bi/dre?ano=${ano}`;
    if(loja) url += '&loja_id='+loja;
    try {
        const j = await apiFetch(url);
        const d = j.data||{};
        const rb = d.receita_bruta||0;

        function pct(v){ return rb>0?(parseFloat(v)/rb*100).toFixed(1)+'%':'—'; }
        function row(label,valor,cls=''){
            return `<tr class="${cls}"><td>${label}</td><td class="dre-val-col">${fmt(valor)}</td><td class="dre-pct-col">${pct(valor)}</td></tr>`;
        }
        const CATS = {aluguel:'Aluguel',agua:'Água',energia:'Energia',internet:'Internet',
                      fornecedor:'Fornecedores',folha:'Folha',outros:'Outros'};
        let despRows = '';
        Object.entries(d.despesas||{}).forEach(([cat,val])=>{
            despRows += `<tr><td>&nbsp;&nbsp;&nbsp;${CATS[cat]||cat}</td><td class="dre-val-col">(${fmt(val)})</td><td class="dre-pct-col">${pct(val)}</td></tr>`;
        });

        tbody.innerHTML = `
            <tr class="dre-grupo"><td colspan="3">RECEITA BRUTA</td></tr>
            <tr><td>&nbsp;&nbsp;&nbsp;PDV (Balcão)</td><td class="dre-val-col">${fmt(d.receita_pdv)}</td><td class="dre-pct-col">${pct(d.receita_pdv)}</td></tr>
            <tr><td>&nbsp;&nbsp;&nbsp;Delivery</td><td class="dre-val-col">${fmt(d.receita_delivery)}</td><td class="dre-pct-col">${pct(d.receita_delivery)}</td></tr>
            ${row('( = ) RECEITA BRUTA TOTAL', rb,'dre-total-linha')}
            <tr class="dre-grupo"><td colspan="3">CUSTO DA MERCADORIA VENDIDA (CMV)</td></tr>
            <tr><td>&nbsp;&nbsp;&nbsp;Custo de Produtos</td><td class="dre-val-col">(${fmt(d.cme)})</td><td class="dre-pct-col">${pct(d.cme)}</td></tr>
            ${row('( = ) LUCRO BRUTO', d.lucro_bruto, d.lucro_bruto>=0?'dre-total-linha':'dre-negativo')}
            <tr class="dre-grupo"><td colspan="3">DESPESAS OPERACIONAIS</td></tr>
            ${despRows}
            <tr><td>&nbsp;&nbsp;&nbsp;Folha Paga (Real)</td><td class="dre-val-col">(${fmt(d.folha_realizada)})</td><td class="dre-pct-col">${pct(d.folha_realizada)}</td></tr>
            ${row('( = ) EBITDA', d.ebitda, d.ebitda>=0?'dre-total-linha':'dre-negativo')}
            <tr class="dre-grupo"><td colspan="3">PROVISÕES E IMPOSTOS</td></tr>
            <tr><td>&nbsp;&nbsp;&nbsp;IR Estimado (6% Simples Nacional)</td><td class="dre-val-col">(${fmt(d.ir_estimado)})</td><td class="dre-pct-col">${pct(d.ir_estimado)}</td></tr>
            ${row('( = ) LUCRO LÍQUIDO', d.lucro_liquido, d.lucro_liquido>=0?'dre-total-linha':'dre-negativo')}
            <tr class="${d.lucro_liquido>=0?'dre-total-linha':'dre-negativo'}">
                <td><strong>MARGEM LÍQUIDA</strong></td>
                <td class="dre-val-col"><strong>${d.margem_pct||0}%</strong></td>
                <td class="dre-pct-col">—</td>
            </tr>
        `;
        load.style.display='none'; tbl.style.display='';
    } catch(e){ load.textContent='Erro ao carregar DRE: '+e.message; }
};

/* ─ Top Produtos ─────────────────────────────────────────── */
window.biCarregarTopProd = async function(){
    const load  = document.getElementById('bi-tp-loading');
    const lista = document.getElementById('bi-tp-lista');
    const mes   = qs('bi-tp-mes');
    const loja  = qs('bi-tp-loja');
    load.style.display=''; lista.innerHTML='';
    let url = `${API}/bi/top_produtos?mes=${mes}`;
    if(loja) url+='&loja_id='+loja;
    try {
        const j = await apiFetch(url);
        const prods = j.data||[];
        if(!prods.length){ load.textContent='Nenhum dado para este período.'; return; }
        const maxRec = Math.max(...prods.map(p=>parseFloat(p.total_receita)));
        lista.innerHTML = prods.map((p,i)=>{
            const pct = maxRec>0?(parseFloat(p.total_receita)/maxRec*100):0;
            return `
            <div class="top-prod-item">
                <span class="top-prod-rank">${i+1}</span>
                <div>
                    <div style="font-weight:600;">${p.nome}</div>
                    <div style="font-size:.73rem;color:#888;">${parseFloat(p.total_qtd).toLocaleString('pt-BR')} ${p.unidade_medida}</div>
                    <div class="top-prod-bar" style="width:140px;margin-top:3px;">
                        <div class="top-prod-bar-fill" style="width:${pct}%;"></div>
                    </div>
                </div>
                <span style="font-weight:700;color:#2e7d32;">${fmt(p.total_receita)}</span>
                <span style="color:#888;font-size:.76rem;">${pct.toFixed(0)}%</span>
            </div>`;
        }).join('');
        load.style.display='none';
    } catch(e){ load.textContent='Erro: '+e.message; }
};

/* ─ Init ──────────────────────────────────────────────────── */
biCarregarOverview();
})();
</script>
