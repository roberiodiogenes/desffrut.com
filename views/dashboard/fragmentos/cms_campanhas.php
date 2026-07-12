<?php
/**
 * Desffrut — Fragmento: CMS Campanhas Sazonais (Fase 8)
 * Gerencia cupons de desconto globais e temas CSS por período.
 */
$pdo       = db();
$campanhas = $pdo->query("SELECT * FROM campanhas ORDER BY data_inicio DESC")->fetchAll();
?>
<style>
.cms-camp-wrap { padding:20px; }
.cms-camp-card {
    background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.08);
    padding:18px; margin-bottom:14px;
}
.cms-camp-title { font-weight:700; font-size:.95rem; color:#1b5e20; margin-bottom:14px; }

.cms-camp-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.cms-camp-table th { background:#f9f9f9; padding:8px 10px; text-align:left;
    border-bottom:2px solid #e0e0e0; color:#555; font-weight:600; white-space:nowrap; }
.cms-camp-table td { padding:7px 10px; border-bottom:1px solid #f0f0f0; color:#333; vertical-align:middle; }
.cms-camp-table tr:hover td { background:#fafafa; }

.badge-ativo    { background:#e8f5e9; color:#2e7d32; padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700; }
.badge-inativo  { background:#f5f5f5; color:#999;    padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700; }
.badge-vigente  { background:#e3f2fd; color:#1565c0; padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700; }
.badge-expirada { background:#ffebee; color:#c62828; padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700; }
.badge-futura   { background:#fff3e0; color:#e65100; padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700; }

.cms-camp-btn { padding:4px 10px; border:none; border-radius:5px; font-size:.73rem; font-weight:600; cursor:pointer; }

.cms-camp-form { display:grid; gap:12px; }
.cms-camp-row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
@media(max-width:600px){ .cms-camp-row2 { grid-template-columns:1fr; } }
.cms-camp-label { font-size:.78rem; color:#555; font-weight:600; margin-bottom:3px; }
.cms-camp-input {
    width:100%; padding:7px 10px; border:1px solid #ddd; border-radius:6px;
    font-size:.85rem; color:#333; box-sizing:border-box;
}
.cms-camp-input:focus { outline:none; border-color:#2e7d32; }
.cms-camp-btn-save { padding:8px 20px; background:#2e7d32; color:#fff;
    border:none; border-radius:6px; font-size:.85rem; font-weight:600; cursor:pointer; }
.cms-camp-btn-save:hover { background:#1b5e20; }

.cms-camp-toast {
    position:fixed; bottom:24px; right:24px; z-index:2000;
    background:#2e7d32; color:#fff; padding:10px 20px;
    border-radius:8px; font-size:.85rem; font-weight:600;
    box-shadow:0 4px 16px rgba(0,0,0,.18); display:none;
}
.cms-camp-extra { display:none; }
</style>

<div class="cms-camp-wrap">

    <!-- Lista de Campanhas -->
    <div class="cms-camp-card">
        <div class="cms-camp-title">📅 Campanhas Cadastradas</div>

        <?php if (empty($campanhas)): ?>
        <div style="text-align:center;padding:20px;color:#999;font-size:.83rem;">
            Nenhuma campanha cadastrada ainda. Use o formulário abaixo para criar a primeira.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="cms-camp-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Valor / CSS</th>
                    <th>Período</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $agora = new DateTime();
            foreach ($campanhas as $c):
                $ini   = new DateTime($c['data_inicio']);
                $fim   = new DateTime($c['data_fim']);
                $vigente = $agora >= $ini && $agora <= $fim;
                $expirada= $agora > $fim;
                $futura  = $agora < $ini;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                <td>
                    <?php if($c['tipo']==='cupom_global'): ?>
                    <span style="color:#1565c0;">🎟 Cupom Global</span>
                    <?php else: ?>
                    <span style="color:#6a1b9a;">🎨 Tema CSS</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($c['tipo']==='cupom_global'): ?>
                    <strong><?= number_format((float)$c['valor_desconto'],0) ?>% off</strong>
                    <?php else: ?>
                    <code style="font-size:.75rem;"><?= htmlspecialchars($c['classe_css']??'-') ?></code>
                    <?php endif; ?>
                </td>
                <td style="font-size:.75rem;white-space:nowrap;">
                    <?= date('d/m/y H:i', strtotime($c['data_inicio'])) ?> →
                    <?= date('d/m/y H:i', strtotime($c['data_fim'])) ?>
                </td>
                <td>
                    <?php if(!$c['ativo']): ?>
                    <span class="badge-inativo">Inativa</span>
                    <?php elseif($vigente): ?>
                    <span class="badge-vigente">● Vigente</span>
                    <?php elseif($expirada): ?>
                    <span class="badge-expirada">Expirada</span>
                    <?php else: ?>
                    <span class="badge-futura">Futura</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                        <?php if($c['ativo']): ?>
                        <button class="cms-camp-btn" style="background:#fff3e0;color:#e65100;"
                            onclick="cmsCampToggle(<?= $c['id'] ?>,0)">⏸ Desativar</button>
                        <?php else: ?>
                        <button class="cms-camp-btn" style="background:#e8f5e9;color:#2e7d32;"
                            onclick="cmsCampToggle(<?= $c['id'] ?>,1)">▶ Ativar</button>
                        <?php endif; ?>
                        <button class="cms-camp-btn" style="background:#ffebee;color:#c62828;"
                            onclick="cmsCampDeletar(<?= $c['id'] ?>,this)">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Criar Nova Campanha -->
    <div class="cms-camp-card">
        <div class="cms-camp-title">➕ Nova Campanha</div>
        <div class="cms-camp-form">
            <div class="cms-camp-row2">
                <div>
                    <div class="cms-camp-label">Nome da Campanha</div>
                    <input class="cms-camp-input" id="camp-nome" type="text"
                           placeholder="Ex.: Natal 2026, Black Friday…" maxlength="120">
                </div>
                <div>
                    <div class="cms-camp-label">Tipo</div>
                    <select class="cms-camp-input" id="camp-tipo" onchange="cmsCampTipoChange(this)">
                        <option value="cupom_global">🎟 Cupom Global (desconto % em todos os pedidos)</option>
                        <option value="tema_css">🎨 Tema Visual CSS (classe no body)</option>
                    </select>
                </div>
            </div>

            <div id="camp-extra-cupom">
                <div class="cms-camp-label">Percentual de Desconto (%)</div>
                <input class="cms-camp-input" id="camp-desconto" type="number"
                       min="1" max="100" step="1" placeholder="Ex.: 10" style="max-width:200px;">
                <div style="font-size:.75rem;color:#888;margin-top:3px;">
                    Aplicado automaticamente em todos os pedidos durante o período ativo.
                </div>
            </div>

            <div id="camp-extra-css" class="cms-camp-extra">
                <div class="cms-camp-label">Classe CSS a Injetar no &lt;body&gt;</div>
                <input class="cms-camp-input" id="camp-css" type="text"
                       placeholder="Ex.: tema-natal, tema-festa-junina" style="max-width:300px;">
                <div style="font-size:.75rem;color:#888;margin-top:3px;">
                    Define estilos especiais via CSS. A classe é removida automaticamente após o período.
                </div>
            </div>

            <div class="cms-camp-row2">
                <div>
                    <div class="cms-camp-label">Início</div>
                    <input class="cms-camp-input" id="camp-inicio" type="datetime-local">
                </div>
                <div>
                    <div class="cms-camp-label">Fim</div>
                    <input class="cms-camp-input" id="camp-fim" type="datetime-local">
                </div>
            </div>

            <div>
                <button class="cms-camp-btn-save" onclick="cmsCampCriar()">✅ Criar Campanha</button>
            </div>
        </div>
    </div>
</div>

<div class="cms-camp-toast" id="cms-camp-toast"></div>

<script>
(function(){
const API = APP.api;

function authH(json) {
    const t = (sessionStorage.getItem('desffrut_token') || '').trim();
    const h = {};
    if (json) h['Content-Type'] = 'application/json';
    if (t)    h['Authorization'] = 'Bearer ' + t;
    return h;
}

function toast(msg, ok=true){
    const el=document.getElementById('cms-camp-toast');
    el.textContent=msg; el.style.background=ok?'#2e7d32':'#c62828';
    el.style.display='block'; setTimeout(()=>el.style.display='none',3200);
}

window.cmsCampTipoChange = function(sel){
    const isCupom = sel.value==='cupom_global';
    document.getElementById('camp-extra-cupom').style.display = isCupom?'block':'none';
    document.getElementById('camp-extra-css').style.display   = isCupom?'none':'block';
};

window.cmsCampToggle = async function(id, ativo){
    try {
        const r=await fetch(API+'/configuracoes/campanhas/'+id,{
            method:'PATCH', headers: authH(true), body:JSON.stringify({ativo})
        });
        const j=await r.json();
        if(j.status==='ok'){ toast(ativo?'✅ Ativada.':'✅ Desativada.'); setTimeout(()=>carregarAbaAtiva(),1200); }
        else toast('❌ Erro: '+j.message,false);
    } catch(e){ toast('❌ Falha de rede.',false); }
};

window.cmsCampDeletar = async function(id, btn){
    if(!confirm('Excluir esta campanha permanentemente?')) return;
    try {
        const r=await fetch(API+'/configuracoes/campanhas/'+id,{ method:'DELETE', headers: authH(false) });
        const j=await r.json();
        if(j.status==='ok'){
            btn.closest('tr')?.remove();
            toast('✅ Campanha excluída.');
        } else toast('❌ '+j.message,false);
    } catch(e){ toast('❌ Falha de rede.',false); }
};

window.cmsCampCriar = async function(){
    const tipo    = document.getElementById('camp-tipo').value;
    const nome    = document.getElementById('camp-nome').value.trim();
    const inicio  = document.getElementById('camp-inicio').value;
    const fim     = document.getElementById('camp-fim').value;
    const desconto= tipo==='cupom_global' ? parseFloat(document.getElementById('camp-desconto').value)||null : null;
    const css     = tipo==='tema_css' ? document.getElementById('camp-css').value.trim() : null;

    if(!nome)    { toast('Informe o nome da campanha.',false); return; }
    if(!inicio||!fim){ toast('Informe início e fim da campanha.',false); return; }
    if(tipo==='cupom_global'&&(!desconto||desconto<=0)){ toast('Informe o percentual de desconto.',false); return; }
    if(tipo==='tema_css'&&!css){ toast('Informe a classe CSS.',false); return; }

    // Converte datetime-local para formato MySQL: 2026-06-24T12:00 → 2026-06-24 12:00:00
    const fmtDt = v => v.replace('T',' ') + (v.length===16 ? ':00' : '');

    try {
        const r=await fetch(API+'/configuracoes/campanhas',{
            method:'POST', headers: authH(true),
            body:JSON.stringify({nome, tipo,
                valor_desconto: desconto,
                classe_css: css,
                data_inicio: fmtDt(inicio),
                data_fim:    fmtDt(fim)
            })
        });
        const j=await r.json();
        if(j.status==='ok'){
            toast('✅ Campanha criada!');
            setTimeout(()=>carregarAbaAtiva(),1200);
        } else toast('❌ Erro: '+(j.message||'Tente novamente.'),false);
    } catch(e){ toast('❌ Falha de rede: '+e.message,false); }
};

})();
</script>
