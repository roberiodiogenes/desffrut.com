<?php
/**
 * Desffrut — Fragmento: CMS Identidade Visual (Fase 8)
 * Gerencia nome, slogan, logomarca, paleta de cores e temas sazonais.
 */
$pdo = db();
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
$cfg  = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$is_super = (usuario_logado()['role'] === 'super_admin');
?>
<style>
.cms-id-wrap { padding:20px; max-width:740px; }
.cms-id-card {
    background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.08);
    padding:20px; margin-bottom:18px;
}
.cms-id-title { font-weight:700; font-size:.98rem; color:#1b5e20; margin-bottom:14px;
    display:flex; align-items:center; gap:8px; }
.cms-id-label { font-size:.82rem; color:#555; font-weight:600; margin-bottom:4px; }
.cms-id-input {
    width:100%; padding:8px 12px; border:1px solid #ddd; border-radius:6px;
    font-size:.87rem; color:#333;
    transition: border-color .15s;
}
.cms-id-input:focus { outline:none; border-color:#2e7d32; }
.cms-id-row { display:flex; gap:14px; margin-bottom:14px; flex-wrap:wrap; }
.cms-id-col { flex:1; min-width:180px; }
.cms-id-btn {
    padding:8px 20px; border:none; border-radius:6px; cursor:pointer;
    font-size:.85rem; font-weight:600;
}
.cms-id-btn-save { background:#2e7d32; color:#fff; }
.cms-id-btn-save:hover { background:#1b5e20; }
.cms-id-preview-bar {
    height:40px; border-radius:6px; display:flex; align-items:center;
    padding:0 16px; color:#fff; font-weight:600; font-size:.9rem;
    transition: background .25s;
    margin-top:10px; letter-spacing:.3px;
}
.cms-id-logo-preview {
    max-height:80px; border-radius:8px; object-fit:contain;
    border:1px solid #eee; padding:4px; display:block; margin-bottom:10px;
}
/* Temas sazonais */
.temas-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap:10px; margin-bottom:12px;
}
.tema-btn {
    border:2px solid transparent; border-radius:8px;
    padding:8px 10px; cursor:pointer; font-size:.75rem;
    font-weight:600; text-align:center; transition:all .15s;
    display:flex; flex-direction:column; align-items:center; gap:4px;
}
.tema-btn:hover { transform:translateY(-2px); box-shadow:0 4px 10px rgba(0,0,0,.15); }
.tema-btn .tema-swatches {
    display:flex; gap:4px;
}
.tema-swatch { width:20px; height:20px; border-radius:50%; border:1px solid rgba(0,0,0,.12); }
.cms-id-toast {
    position:fixed; bottom:24px; right:24px; z-index:2000;
    color:#fff; padding:10px 20px; border-radius:8px;
    font-size:.85rem; font-weight:600;
    box-shadow:0 4px 16px rgba(0,0,0,.18); display:none;
}
</style>

<div class="cms-id-wrap">
    <!-- Nome e Slogan -->
    <div class="cms-id-card">
        <div class="cms-id-title">🏷️ Nome e Slogan do Sistema</div>
        <div class="cms-id-row">
            <div class="cms-id-col">
                <div class="cms-id-label">Nome do Sistema</div>
                <input class="cms-id-input" id="cms-nome" type="text"
                       value="<?= htmlspecialchars($cfg['nome_sistema'] ?? 'Desffrut') ?>"
                       maxlength="60">
            </div>
            <div class="cms-id-col">
                <div class="cms-id-label">Slogan</div>
                <input class="cms-id-input" id="cms-slogan" type="text"
                       value="<?= htmlspecialchars($cfg['slogan'] ?? '') ?>"
                       maxlength="120" placeholder="Ex.: Frescor direto do campo pra você">
            </div>
        </div>
        <button class="cms-id-btn cms-id-btn-save" onclick="cmsIdSalvarTexto()">💾 Salvar Nome e Slogan</button>
    </div>

    <!-- Paleta de Cores -->
    <div class="cms-id-card">
        <div class="cms-id-title">🎨 Paleta de Cores</div>

        <!-- Temas sazonais -->
        <div class="cms-id-label" style="margin-bottom:8px;">Temas rápidos por data comemorativa:</div>
        <div class="temas-grid" id="temas-grid">
            <!-- gerado pelo JS abaixo -->
        </div>

        <div class="cms-id-row" style="margin-top:12px;">
            <div class="cms-id-col">
                <div class="cms-id-label">Cor Primária (navbar, botões, sidebar)</div>
                <input class="cms-id-input" id="cms-cor1" type="color"
                       value="<?= htmlspecialchars($cfg['cor_primaria'] ?? '#2e7d32') ?>"
                       style="height:44px;padding:4px 6px;cursor:pointer;"
                       oninput="cmsIdPreview()">
            </div>
            <div class="cms-id-col">
                <div class="cms-id-label">Cor Secundária (accent / destaques)</div>
                <input class="cms-id-input" id="cms-cor2" type="color"
                       value="<?= htmlspecialchars($cfg['cor_secundaria'] ?? '#a5d6a7') ?>"
                       style="height:44px;padding:4px 6px;cursor:pointer;"
                       oninput="cmsIdPreview()">
            </div>
        </div>

        <div class="cms-id-label">Prévia da navbar:</div>
        <div class="cms-id-preview-bar" id="cms-preview-bar"
             style="background:<?= htmlspecialchars($cfg['cor_primaria'] ?? '#2e7d32') ?>;">
            🌿 <?= htmlspecialchars($cfg['nome_sistema'] ?? 'Desffrut') ?>
        </div>

        <div style="margin-top:12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <button class="cms-id-btn cms-id-btn-save" onclick="cmsIdSalvarCores()">🎨 Salvar Cores</button>
            <button class="cms-id-btn" style="background:#f5f5f5;color:#555;border:1px solid #ddd;"
                    onclick="cmsIdAplicarTema('#2e7d32','#a5d6a7','Padrão Desffrut')">↩ Restaurar Padrão</button>
        </div>
    </div>

    <!-- Logomarca (somente super_admin) -->
    <?php if ($is_super): ?>
    <div class="cms-id-card">
        <div class="cms-id-title">🖼️ Logomarca</div>
        <?php $logo = $cfg['logo_path'] ?? ''; ?>
        <?php if ($logo): ?>
        <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($logo) ?>"
             class="cms-id-logo-preview" id="cms-logo-preview"
             onerror="this.style.display='none'">
        <?php else: ?>
        <p id="cms-logo-placeholder" style="color:#999;font-size:.83rem;margin-bottom:10px;">
            Nenhuma logomarca cadastrada — o nome do sistema é exibido no navbar.
        </p>
        <?php endif; ?>

        <div class="cms-id-label" style="margin-bottom:6px;">Enviar nova logo (WebP, max 6 MB)</div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="file" id="cms-logo-file" accept="image/*"
                   style="font-size:.82rem;" onchange="cmsIdPreviewLogo(this)">
            <button class="cms-id-btn cms-id-btn-save" onclick="cmsIdEnviarLogo()">⬆️ Enviar</button>
            <?php if ($logo): ?>
            <button class="cms-id-btn" style="background:#ffebee;color:#c62828;"
                    onclick="cmsIdRemoverLogo()">🗑 Remover</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="cms-id-toast" id="cms-id-toast"></div>

<script>
(function(){
// ── Util: auth headers sem enviar Bearer vazio ────────────────────────────────
function authH(json) {
    const t = (sessionStorage.getItem('desffrut_token') || '').trim();
    const h = {};
    if (json) h['Content-Type'] = 'application/json';
    if (t)    h['Authorization'] = 'Bearer ' + t;
    return h;
}

function toast(msg, ok=true) {
    const el = document.getElementById('cms-id-toast');
    el.textContent = msg;
    el.style.background = ok ? '#2e7d32' : '#c62828';
    el.style.display = 'block';
    setTimeout(() => el.style.display='none', 3500);
}

async function salvarChave(chave, valor) {
    try {
        const r = await fetch(APP.api + '/configuracoes', {
            method: 'POST',
            headers: authH(true),
            body: JSON.stringify({ chave, valor })
        });
        const j = await r.json();
        return j.status === 'ok';
    } catch(e) { return false; }
}

// ── Nome e Slogan ─────────────────────────────────────────────────────────────
window.cmsIdSalvarTexto = async function() {
    const nome   = document.getElementById('cms-nome').value.trim();
    const slogan = document.getElementById('cms-slogan').value.trim();
    if (!nome) { toast('Nome do sistema não pode ser vazio.', false); return; }
    const ok1 = await salvarChave('nome_sistema', nome);
    const ok2 = await salvarChave('slogan', slogan);
    if (ok1 && ok2) {
        document.getElementById('cms-preview-bar').textContent = '🌿 ' + nome;
        toast('✅ Salvo! Recarregue a página para aplicar no portal.');
    } else {
        toast('❌ Erro ao salvar. Verifique se está logado corretamente.', false);
    }
};

// ── Temas sazonais ────────────────────────────────────────────────────────────
const TEMAS = [
    { nome:'🌿 Padrão',     cor1:'#2e7d32', cor2:'#a5d6a7' },
    { nome:'🎭 Carnaval',   cor1:'#6a1b9a', cor2:'#f9a825' },
    { nome:'💝 Namorados',  cor1:'#c62828', cor2:'#f48fb1' },
    { nome:'🌼 Páscoa',     cor1:'#558b2f', cor2:'#ffd54f' },
    { nome:'👩 Dia das Mães',cor1:'#ad1457', cor2:'#f8bbd0' },
    { nome:'👔 Dia dos Pais',cor1:'#1565c0', cor2:'#90caf9' },
    { nome:'🎪 Festa Junina',cor1:'#bf360c', cor2:'#fdd835' },
    { nome:'🇧🇷 7 de Setembro',cor1:'#1b5e20',cor2:'#f9a825' },
    { nome:'👶 Crianças',   cor1:'#e65100', cor2:'#ffe082' },
    { nome:'🛍️ Black Friday',cor1:'#212121',cor2:'#ffeb3b' },
    { nome:'🎄 Natal',      cor1:'#b71c1c', cor2:'#43a047' },
    { nome:'🥂 Ano Novo',   cor1:'#0d47a1', cor2:'#ffd600' },
];

const grid = document.getElementById('temas-grid');
TEMAS.forEach(t => {
    const btn = document.createElement('button');
    btn.className = 'tema-btn';
    btn.style.background = '#fafafa';
    btn.innerHTML = `
        <div class="tema-swatches">
            <div class="tema-swatch" style="background:${t.cor1}"></div>
            <div class="tema-swatch" style="background:${t.cor2}"></div>
        </div>
        <span>${t.nome}</span>`;
    btn.onclick = () => cmsIdAplicarTema(t.cor1, t.cor2, t.nome);
    grid.appendChild(btn);
});

window.cmsIdAplicarTema = function(cor1, cor2, nome) {
    document.getElementById('cms-cor1').value = cor1;
    document.getElementById('cms-cor2').value = cor2;
    document.getElementById('cms-preview-bar').style.background = cor1;
    document.getElementById('cms-preview-bar').textContent =
        '🌿 ' + (document.getElementById('cms-nome').value || 'Desffrut') +
        '  —  ' + nome;
    // Destaca o tema selecionado
    document.querySelectorAll('.tema-btn').forEach(b => b.style.borderColor='transparent');
    event.currentTarget.style.borderColor = cor1;
};

// ── Cores ─────────────────────────────────────────────────────────────────────
window.cmsIdPreview = function() {
    const cor1 = document.getElementById('cms-cor1').value;
    document.getElementById('cms-preview-bar').style.background = cor1;
};

window.cmsIdSalvarCores = async function() {
    const cor1 = document.getElementById('cms-cor1').value;
    const cor2 = document.getElementById('cms-cor2').value;
    const ok1  = await salvarChave('cor_primaria', cor1);
    const ok2  = await salvarChave('cor_secundaria', cor2);
    if (ok1 && ok2) {
        toast('✅ Cores salvas! Recarregue a página para aplicar no portal.');
    } else {
        toast('❌ Erro ao salvar cores. Verifique se está logado.', false);
    }
};

// ── Logo ──────────────────────────────────────────────────────────────────────
window.cmsIdPreviewLogo = function(input) {
    const file = input.files[0];
    if (!file) return;
    let img = document.getElementById('cms-logo-preview');
    if (!img) {
        img = document.createElement('img');
        img.id = 'cms-logo-preview';
        img.className = 'cms-id-logo-preview';
        const ph = document.getElementById('cms-logo-placeholder');
        (ph || input).before(img);
        if (ph) ph.remove();
    }
    img.src = URL.createObjectURL(file);
};

window.cmsIdEnviarLogo = async function() {
    const file = document.getElementById('cms-logo-file').files[0];
    if (!file) { toast('Selecione um arquivo de imagem.', false); return; }
    const form = new FormData();
    form.append('foto', file);
    form.append('destino', 'logo');
    try {
        const r  = await fetch(APP.base + '/api/v1/uploads', { method:'POST', headers: authH(false), body: form });
        const j  = await r.json();
        if (j.status === 'ok' && j.data?.path) {
            const ok = await salvarChave('logo_path', j.data.path);
            toast(ok ? '✅ Logo enviada! Recarregue para ver no navbar.' : '❌ Imagem enviada mas erro ao salvar.', ok);
        } else {
            toast('❌ Erro no upload: ' + (j.message || ''), false);
        }
    } catch(e) { toast('❌ Falha de rede: ' + e.message, false); }
};

window.cmsIdRemoverLogo = async function() {
    if (!confirm('Remover logomarca? O nome do sistema será exibido no lugar.')) return;
    const ok = await salvarChave('logo_path', '');
    if (ok) {
        document.getElementById('cms-logo-preview')?.remove();
        toast('✅ Logomarca removida.');
    } else toast('❌ Erro ao remover.', false);
};

})();
</script>
