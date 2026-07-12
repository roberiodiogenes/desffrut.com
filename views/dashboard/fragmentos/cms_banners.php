<?php
/**
 * Desffrut — Fragmento: CMS Central de Banners (Fase 8)
 * Upload, reordenação, ativação e remoção de banners da home.
 */
$pdo     = db();
$banners = $pdo->query("SELECT * FROM banners ORDER BY tipo, ordem ASC, id ASC")->fetchAll();

$banners_desktop = array_values(array_filter($banners, fn($b) => $b['tipo'] === 'desktop'));
$banners_mobile  = array_values(array_filter($banners, fn($b) => $b['tipo'] === 'mobile'));
?>
<style>
.cms-ban-wrap { padding:20px; }
.cms-ban-tabs { display:flex; gap:6px; margin-bottom:16px; }
.cms-ban-tab-btn {
    padding:7px 16px; border:1px solid #ddd; border-radius:6px;
    background:#fff; font-size:.82rem; cursor:pointer; color:#555;
}
.cms-ban-tab-btn.active { background:#1565c0; color:#fff; border-color:#1565c0; }
.cms-ban-panel { display:none; }
.cms-ban-panel.active { display:block; }

.cms-ban-card {
    background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.08);
    padding:16px; margin-bottom:14px;
}
.cms-ban-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; }
.cms-ban-item {
    border:1px solid #e0e0e0; border-radius:8px; overflow:hidden;
    background:#fff; position:relative;
}
.cms-ban-img { width:100%; height:140px; object-fit:cover; display:block; }
.cms-ban-img-placeholder {
    width:100%; height:140px; background:#f5f5f5;
    display:flex; align-items:center; justify-content:center;
    color:#bbb; font-size:2rem;
}
.cms-ban-info { padding:10px; }
.cms-ban-link { font-size:.75rem; color:#1565c0; word-break:break-all; }
.cms-ban-actions { display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; }
.cms-ban-btn {
    padding:4px 10px; border:none; border-radius:5px;
    font-size:.73rem; font-weight:600; cursor:pointer;
}
.cms-ban-badge {
    position:absolute; top:8px; left:8px;
    padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700;
}
.cms-ban-badge-on  { background:#e8f5e9; color:#2e7d32; }
.cms-ban-badge-off { background:#ffebee; color:#c62828; }

.cms-ban-upload-area {
    border:2px dashed #c8e6c9; border-radius:10px; padding:24px;
    text-align:center; cursor:pointer; transition:border-color .2s;
    background:#f9fbe7;
}
.cms-ban-upload-area:hover { border-color:#2e7d32; }
.cms-ban-input { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:6px; font-size:.84rem; }
.cms-ban-save-btn { padding:8px 20px; background:#2e7d32; color:#fff; border:none; border-radius:6px; font-size:.85rem; font-weight:600; cursor:pointer; }
.cms-ban-save-btn:hover { background:#1b5e20; }
.cms-ban-toast {
    position:fixed; bottom:24px; right:24px; z-index:2000;
    background:#2e7d32; color:#fff; padding:10px 20px;
    border-radius:8px; font-size:.85rem; font-weight:600;
    box-shadow:0 4px 16px rgba(0,0,0,.18); display:none;
}
</style>

<div class="cms-ban-wrap">
    <div class="cms-ban-tabs">
        <button class="cms-ban-tab-btn active" onclick="cmsBanTab('desktop',this)">🖥 Desktop</button>
        <button class="cms-ban-tab-btn" onclick="cmsBanTab('mobile',this)">📱 Mobile</button>
        <button class="cms-ban-tab-btn" onclick="cmsBanTab('novo',this)">➕ Novo Banner</button>
    </div>

    <!-- Desktop -->
    <div class="cms-ban-panel active" id="cms-ban-panel-desktop">
        <div class="cms-ban-card">
            <div style="font-weight:600;font-size:.9rem;color:#1b5e20;margin-bottom:12px;">
                Banners Desktop (<?= count($banners_desktop) ?>)
            </div>
            <?php if (empty($banners_desktop)): ?>
            <div style="color:#999;font-size:.83rem;text-align:center;padding:20px;">
                Nenhum banner cadastrado para Desktop.
            </div>
            <?php else: ?>
            <div class="cms-ban-grid" id="cms-ban-list-desktop">
            <?php foreach ($banners_desktop as $idx => $b): ?>
                <div class="cms-ban-item" data-id="<?= $b['id'] ?>">
                    <span class="cms-ban-badge <?= $b['ativo'] ? 'cms-ban-badge-on' : 'cms-ban-badge-off' ?>">
                        <?= $b['ativo'] ? '● Ativo' : '○ Inativo' ?>
                    </span>
                    <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($b['imagem_path']) ?>"
                         class="cms-ban-img" onerror="this.style.display='none'">
                    <div class="cms-ban-info">
                        <?php if ($b['link_destino']): ?>
                        <div class="cms-ban-link">🔗 <?= htmlspecialchars($b['link_destino']) ?></div>
                        <?php endif; ?>
                        <div style="font-size:.73rem;color:#aaa;margin-top:2px;">Ordem: <?= $b['ordem'] ?></div>
                        <div class="cms-ban-actions">
                            <button class="cms-ban-btn" style="background:<?= $b['ativo']?'#fff3e0':'#e8f5e9' ?>;color:<?= $b['ativo']?'#e65100':'#2e7d32' ?>;"
                                onclick="cmsBanToggle(<?= $b['id'] ?>,<?= $b['ativo']?0:1 ?>)">
                                <?= $b['ativo'] ? '⏸ Desativar' : '▶ Ativar' ?>
                            </button>
                            <button class="cms-ban-btn" style="background:#ffebee;color:#c62828;"
                                onclick="cmsBanDeletar(<?= $b['id'] ?>,this)">🗑 Remover</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile -->
    <div class="cms-ban-panel" id="cms-ban-panel-mobile">
        <div class="cms-ban-card">
            <div style="font-weight:600;font-size:.9rem;color:#1b5e20;margin-bottom:12px;">
                Banners Mobile (<?= count($banners_mobile) ?>)
            </div>
            <?php if (empty($banners_mobile)): ?>
            <div style="color:#999;font-size:.83rem;text-align:center;padding:20px;">
                Nenhum banner cadastrado para Mobile.
            </div>
            <?php else: ?>
            <div class="cms-ban-grid" id="cms-ban-list-mobile">
            <?php foreach ($banners_mobile as $b): ?>
                <div class="cms-ban-item" data-id="<?= $b['id'] ?>">
                    <span class="cms-ban-badge <?= $b['ativo'] ? 'cms-ban-badge-on' : 'cms-ban-badge-off' ?>">
                        <?= $b['ativo'] ? '● Ativo' : '○ Inativo' ?>
                    </span>
                    <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($b['imagem_path']) ?>"
                         class="cms-ban-img" onerror="this.style.display='none'">
                    <div class="cms-ban-info">
                        <?php if ($b['link_destino']): ?>
                        <div class="cms-ban-link">🔗 <?= htmlspecialchars($b['link_destino']) ?></div>
                        <?php endif; ?>
                        <div style="font-size:.73rem;color:#aaa;margin-top:2px;">Ordem: <?= $b['ordem'] ?></div>
                        <div class="cms-ban-actions">
                            <button class="cms-ban-btn" style="background:<?= $b['ativo']?'#fff3e0':'#e8f5e9' ?>;color:<?= $b['ativo']?'#e65100':'#2e7d32' ?>;"
                                onclick="cmsBanToggle(<?= $b['id'] ?>,<?= $b['ativo']?0:1 ?>)">
                                <?= $b['ativo'] ? '⏸ Desativar' : '▶ Ativar' ?>
                            </button>
                            <button class="cms-ban-btn" style="background:#ffebee;color:#c62828;"
                                onclick="cmsBanDeletar(<?= $b['id'] ?>,this)">🗑 Remover</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Novo Banner -->
    <div class="cms-ban-panel" id="cms-ban-panel-novo">
        <div class="cms-ban-card">
            <div style="font-weight:600;font-size:.9rem;color:#1b5e20;margin-bottom:16px;">➕ Adicionar Novo Banner</div>
            <div style="display:grid;gap:12px;">
                <div>
                    <div style="font-size:.82rem;color:#555;font-weight:600;margin-bottom:4px;">Imagem (JPG/PNG/WebP, max 6 MB)</div>
                    <div class="cms-ban-upload-area" onclick="document.getElementById('cms-ban-file-input').click()">
                        <div style="font-size:2rem;">🖼️</div>
                        <div style="font-size:.83rem;color:#555;margin-top:4px;">Clique para selecionar a imagem do banner</div>
                        <img id="cms-ban-preview-img" src="" alt=""
                             style="max-height:120px;margin-top:10px;display:none;border-radius:6px;">
                    </div>
                    <input type="file" id="cms-ban-file-input" accept="image/*"
                           style="display:none;" onchange="cmsBanPreviewNovo(this)">
                </div>
                <div>
                    <div style="font-size:.82rem;color:#555;font-weight:600;margin-bottom:4px;">Link de destino (opcional)</div>
                    <input type="url" id="cms-ban-link" class="cms-ban-input" placeholder="https://...">
                </div>
                <div style="display:flex;gap:14px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:140px;">
                        <div style="font-size:.82rem;color:#555;font-weight:600;margin-bottom:4px;">Tipo</div>
                        <select id="cms-ban-tipo" class="cms-ban-input">
                            <option value="desktop">🖥 Desktop</option>
                            <option value="mobile">📱 Mobile</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:80px;">
                        <div style="font-size:.82rem;color:#555;font-weight:600;margin-bottom:4px;">Ordem</div>
                        <input type="number" id="cms-ban-ordem" class="cms-ban-input" value="0" min="0" max="99">
                    </div>
                </div>
                <div style="display:flex;gap:14px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:160px;">
                        <div style="font-size:.82rem;color:#555;font-weight:600;margin-bottom:4px;">Exibir a partir de (opcional)</div>
                        <input type="datetime-local" id="cms-ban-de" class="cms-ban-input">
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <div style="font-size:.82rem;color:#555;font-weight:600;margin-bottom:4px;">Exibir até (opcional)</div>
                        <input type="datetime-local" id="cms-ban-ate" class="cms-ban-input">
                    </div>
                </div>
                <div>
                    <button class="cms-ban-save-btn" onclick="cmsBanCriar()">⬆️ Enviar e Criar Banner</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="cms-ban-toast" id="cms-ban-toast"></div>

<script>
(function(){
const API = APP.api;

// Auth sem enviar Bearer vazio (evita rejeição pelo dual-mode)
function authH(json) {
    const t = (sessionStorage.getItem('desffrut_token') || '').trim();
    const h = {};
    if (json) h['Content-Type'] = 'application/json';
    if (t)    h['Authorization'] = 'Bearer ' + t;
    return h;
}

function toast(msg, ok=true){
    const el=document.getElementById('cms-ban-toast');
    el.textContent=msg; el.style.background=ok?'#2e7d32':'#c62828';
    el.style.display='block'; setTimeout(()=>el.style.display='none',3200);
}

window.cmsBanTab = function(painel, btn){
    document.querySelectorAll('.cms-ban-tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.cms-ban-panel').forEach(p=>p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('cms-ban-panel-'+painel).classList.add('active');
};

window.cmsBanPreviewNovo = function(input){
    const file=input.files[0]; if(!file) return;
    const img=document.getElementById('cms-ban-preview-img');
    img.src=URL.createObjectURL(file); img.style.display='block';
};

window.cmsBanToggle = async function(id, ativo){
    try {
        const r=await fetch(API+'/configuracoes/banners/'+id,{
            method:'PATCH', headers: authH(true), body:JSON.stringify({ativo})
        });
        const j=await r.json();
        if(j.status==='ok'){ toast(ativo?'✅ Banner ativado.':'✅ Banner desativado.'); setTimeout(()=>carregarAbaAtiva(),1200); }
        else toast('❌ Erro: '+j.message,false);
    } catch(e){ toast('❌ Falha de rede.',false); }
};

window.cmsBanDeletar = async function(id, btn){
    if(!confirm('Remover este banner?')) return;
    try {
        const r=await fetch(API+'/configuracoes/banners/'+id,{ method:'DELETE', headers: authH(false) });
        const j=await r.json();
        if(j.status==='ok'){
            btn.closest('.cms-ban-item').remove();
            toast('✅ Banner removido.');
        } else toast('❌ Erro: '+j.message,false);
    } catch(e){ toast('❌ Falha de rede.',false); }
};

window.cmsBanCriar = async function(){
    const file=document.getElementById('cms-ban-file-input').files[0];
    if(!file){ toast('Selecione uma imagem para o banner.',false); return; }

    try {
        // 1. Envia imagem
        const form=new FormData();
        form.append('foto', file);
        form.append('destino', 'banner');
        const ru=await fetch(APP.base+'/api/v1/uploads',{ method:'POST', headers: authH(false), body:form });
        const ju=await ru.json();
        if(ju.status!=='ok'||!ju.data?.path){ toast('❌ Falha no upload: '+(ju.message||''),false); return; }

        // 2. Cria o banner
        const de  = document.getElementById('cms-ban-de').value;
        const ate = document.getElementById('cms-ban-ate').value;
        const payload={
            imagem_path: ju.data.path,
            link_destino: document.getElementById('cms-ban-link').value.trim(),
            tipo: document.getElementById('cms-ban-tipo').value,
            ordem: parseInt(document.getElementById('cms-ban-ordem').value)||0,
            ativo: 1,
            exibe_de:  de  ? de.replace('T',' ')  : null,
            exibe_ate: ate ? ate.replace('T',' ') : null,
        };
        const rb=await fetch(API+'/configuracoes/banners',{ method:'POST', headers: authH(true), body:JSON.stringify(payload) });
        const jb=await rb.json();
        if(jb.status==='ok'){
            toast('✅ Banner criado! Atualizando…');
            setTimeout(()=>carregarAbaAtiva(),1500);
        } else toast('❌ Erro: '+(jb.message||''),false);
    } catch(e){ toast('❌ Falha: '+e.message,false); }
};

})();
</script>
