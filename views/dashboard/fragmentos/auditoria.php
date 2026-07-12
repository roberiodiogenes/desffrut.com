<?php
/**
 * Desffrut — Fragmento: Auditoria (Fase 7)
 * Log completo de ações críticas do sistema.
 * Acesso restrito: super_admin.
 */
if ($u['role'] !== 'super_admin') {
    echo '<div class="alert alert-warning m-3">⚠️ Acesso restrito a Administradores.</div>';
    exit;
}
?>
<style>
.aud-wrap { padding:16px; }
.aud-card {
    background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.08);
    padding:16px; margin-bottom:14px;
}
.aud-card-title { font-weight:600; font-size:.95rem; color:#333; margin-bottom:12px;
                  display:flex; align-items:center; justify-content:space-between; }

.aud-filtros {
    display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px;
}
.aud-filtros label { font-size:.76rem; color:#666; display:block; margin-bottom:2px; }
.aud-filtros select, .aud-filtros input {
    padding:6px 10px; border:1px solid #ccc; border-radius:6px; font-size:.82rem;
}
.aud-btn { padding:5px 12px; border-radius:5px; font-size:.8rem; border:none; cursor:pointer; }
.aud-btn-dark { background:#333; color:#fff; }
.aud-btn-dark:hover { background:#111; }
.aud-btn-outline { background:#fff; border:1px solid #bbb; color:#555; }
.aud-btn-outline:hover { background:#f5f5f5; }

.aud-table { width:100%; border-collapse:collapse; font-size:.79rem; }
.aud-table th { background:#f9f9f9; padding:8px 10px; text-align:left;
                border-bottom:2px solid #e0e0e0; color:#555; font-weight:600; }
.aud-table td { padding:7px 10px; border-bottom:1px solid #f0f0f0; color:#333; vertical-align:top; }
.aud-table tr:hover td { background:#fafafa; }

.aud-acao {
    display:inline-block; padding:2px 8px; border-radius:20px; font-size:.7rem;
    font-weight:600; letter-spacing:.3px; background:#e8f5e9; color:#2e7d32;
}
.aud-acao.alerta  { background:#ffebee; color:#c62828; }
.aud-acao.aviso   { background:#fff3e0; color:#e65100; }
.aud-acao.info    { background:#e3f2fd; color:#1565c0; }

.aud-detalhe { font-size:.72rem; color:#888; font-family:monospace; margin-top:2px; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.aud-paginacao { display:flex; align-items:center; justify-content:space-between; margin-top:12px; }
.aud-paginacao span { font-size:.78rem; color:#666; }
.aud-paginacao button { padding:4px 12px; border-radius:5px; border:1px solid #ccc; background:#fff; cursor:pointer; font-size:.78rem; }
.aud-paginacao button:hover { background:#f5f5f5; }
.aud-paginacao button:disabled { opacity:.4; cursor:default; }

.aud-chip-critico { background:#ffebee; color:#b71c1c; }
.aud-chip-normal  { background:#e8f5e9; color:#1b5e20; }
</style>

<div class="aud-wrap">
    <div class="aud-card">
        <div class="aud-card-title">
            🔍 Log de Auditoria
            <button class="aud-btn aud-btn-dark" onclick="audCarregar()">↺ Atualizar</button>
        </div>

        <!-- Filtros -->
        <div class="aud-filtros">
            <div>
                <label>Ação</label>
                <select id="aud-fil-acao" onchange="audReset()">
                    <option value="">Todas</option>
                </select>
            </div>
            <div>
                <label>Tabela</label>
                <select id="aud-fil-tabela" onchange="audReset()">
                    <option value="">Todas</option>
                    <option>funcionarios</option>
                    <option>contas_pagar</option>
                    <option>folha_pagamento</option>
                    <option>pedidos</option>
                    <option>vendas</option>
                    <option>precos</option>
                    <option>movimentos_caixa</option>
                </select>
            </div>
            <div>
                <label>De</label>
                <input type="date" id="aud-fil-ini" onchange="audReset()">
            </div>
            <div>
                <label>Até</label>
                <input type="date" id="aud-fil-fim" onchange="audReset()">
            </div>
        </div>

        <div id="aud-loading" class="text-center py-3 text-muted small">Carregando…</div>
        <table class="aud-table" id="aud-table" style="display:none">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Tabela</th>
                    <th>Reg.</th>
                    <th>IP</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody id="aud-tbody"></tbody>
        </table>

        <!-- Paginação -->
        <div class="aud-paginacao" id="aud-paginacao" style="display:none">
            <span id="aud-info"></span>
            <div style="display:flex;gap:6px;">
                <button id="aud-btn-ant" onclick="audPagina(-1)">← Anterior</button>
                <button id="aud-btn-prox" onclick="audPagina(+1)">Próxima →</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
const API = window.APP?.api || '/desffrut.com/api/v1';
let _pagina = 1;
const POR_PAG = 50;

/* ─ Ações críticas para destaque ─────────────────────────── */
const CRITICOS = new Set([
    'demissao_funcionario','pagamento_conta','pagamento_folha','alteracao_preco',
    'sangria','cancelamento_venda','cancelamento_pedido','edicao_funcionario',
]);
function classeAcao(a){
    if(CRITICOS.has(a)) return 'alerta';
    if(a.startsWith('lancamento')||a.startsWith('admissao')) return 'aviso';
    return '';
}

/* ─ Carrega ações distintas ─────────────────────────────── */
async function audCarregarAcoes(){
    try {
        const r = await fetch(`${API}/auditoria/acoes`);
        const j = await r.json();
        const sel = document.getElementById('aud-fil-acao');
        (j.data||[]).forEach(a=>{
            const o = document.createElement('option');
            o.value=a; o.textContent=a;
            sel.appendChild(o);
        });
    } catch {}
}

/* ─ Carrega logs ────────────────────────────────────────── */
window.audCarregar = async function(){
    const load  = document.getElementById('aud-loading');
    const tbl   = document.getElementById('aud-table');
    const tbody = document.getElementById('aud-tbody');
    const pag   = document.getElementById('aud-paginacao');
    load.style.display=''; tbl.style.display='none'; pag.style.display='none';

    const acao   = document.getElementById('aud-fil-acao').value;
    const tabela = document.getElementById('aud-fil-tabela').value;
    const ini    = document.getElementById('aud-fil-ini').value;
    const fim    = document.getElementById('aud-fil-fim').value;
    let url = `${API}/auditoria?pagina=${_pagina}&por_pagina=${POR_PAG}`;
    if(acao)   url+=`&acao=${encodeURIComponent(acao)}`;
    if(tabela) url+=`&tabela=${encodeURIComponent(tabela)}`;
    if(ini)    url+=`&data_ini=${ini}`;
    if(fim)    url+=`&data_fim=${fim}`;

    try {
        const r = await fetch(url);
        const j = await r.json();
        const d = j.data||{};
        const logs = d.logs||[];

        tbody.innerHTML = logs.map(l=>{
            const dt  = l.created_at ? l.created_at.replace('T',' ').substring(0,19) : '—';
            const cls = classeAcao(l.acao);
            let detalhe = '—';
            try { if(l.detalhes_json) detalhe = JSON.stringify(typeof l.detalhes_json==='string'?JSON.parse(l.detalhes_json):l.detalhes_json); } catch{}
            return `<tr>
                <td style="white-space:nowrap;">${dt}</td>
                <td>
                    <strong>${l.usuario_nome||'Sistema'}</strong>
                    ${l.usuario_role?`<br><span style="font-size:.68rem;color:#aaa;">${l.usuario_role}</span>`:''}
                </td>
                <td><span class="aud-acao ${cls}">${l.acao}</span></td>
                <td style="color:#888;">${l.tabela_afetada||'—'}</td>
                <td style="color:#aaa;">${l.registro_id||'—'}</td>
                <td style="color:#aaa;font-size:.73rem;">${l.ip||'—'}</td>
                <td><div class="aud-detalhe" title="${detalhe}">${detalhe}</div></td>
            </tr>`;
        }).join('')||'<tr><td colspan="7" class="text-center text-muted py-3">Nenhum log encontrado.</td></tr>';

        load.style.display='none'; tbl.style.display='';

        // Paginação
        if(d.paginas>1){
            document.getElementById('aud-info').textContent =
                `Página ${d.pagina}/${d.paginas} — ${d.total} registros`;
            document.getElementById('aud-btn-ant').disabled  = d.pagina<=1;
            document.getElementById('aud-btn-prox').disabled = d.pagina>=d.paginas;
            pag.style.display='flex';
        }
    } catch(e){ load.textContent='Erro: '+e.message; }
};

window.audReset = function(){ _pagina=1; audCarregar(); };
window.audPagina = function(delta){ _pagina=Math.max(1,_pagina+delta); audCarregar(); };

/* ─ Init ────────────────────────────────────────────────── */
audCarregarAcoes();
audCarregar();
})();
</script>
