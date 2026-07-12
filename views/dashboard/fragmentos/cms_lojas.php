<?php
/**
 * Desffrut — Fragmento: CMS Painel de Lojas (Fase 8)
 * Edita e cria filiais: horário de funcionamento e WhatsApp.
 */
$pdo       = db();
$lojas     = $pdo->query("SELECT id, nome, endereco, telefone, horario_funcionamento, whatsapp_link, ativo FROM lojas ORDER BY nome")->fetchAll();
$is_super  = (usuario_logado()['role'] === 'super_admin');
?>
<style>
.cms-lj-wrap { padding:20px; max-width:900px; }
.cms-lj-header-row {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:18px; flex-wrap:wrap; gap:10px;
}
.cms-lj-title { font-weight:700; font-size:1.05rem; color:#1b5e20; }
.cms-lj-card {
    background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.08);
    padding:18px; margin-bottom:16px;
}
.cms-lj-card-header {
    display:flex; align-items:center; gap:10px; margin-bottom:14px;
}
.cms-lj-nome { font-weight:700; font-size:.98rem; color:#1b5e20; }
.cms-lj-badge-ativo  { background:#e8f5e9; color:#2e7d32; padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700; }
.cms-lj-badge-inativo{ background:#f5f5f5; color:#999;    padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700; }
.cms-lj-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
@media(max-width:600px){ .cms-lj-row { grid-template-columns:1fr; } }
.cms-lj-label { font-size:.78rem; color:#555; font-weight:600; margin-bottom:3px; }
.cms-lj-input {
    width:100%; padding:7px 10px; border:1px solid #ddd; border-radius:6px;
    font-size:.85rem; color:#333; box-sizing:border-box;
}
.cms-lj-input:focus { outline:none; border-color:#2e7d32; }
.cms-lj-input.field-ok  { border-color:#2e7d32; }
.cms-lj-input.field-err { border-color:#c62828; }
.cms-lj-hint { font-size:.73rem; color:#888; margin-top:3px; }
.cms-lj-hint.err { color:#c62828; }
.cms-lj-btn-save {
    padding:7px 18px; background:#2e7d32; color:#fff;
    border:none; border-radius:6px; font-size:.82rem; font-weight:600; cursor:pointer;
}
.cms-lj-btn-save:hover { background:#1b5e20; }
.cms-lj-btn-add {
    padding:8px 18px; background:#1565c0; color:#fff;
    border:none; border-radius:6px; font-size:.84rem; font-weight:600; cursor:pointer;
}
.cms-lj-btn-add:hover { background:#0d47a1; }
/* Modal nova loja */
.cms-lj-modal-bg {
    position:fixed; inset:0; background:rgba(0,0,0,.45);
    z-index:1050; display:flex; align-items:center; justify-content:center;
}
.cms-lj-modal {
    background:#fff; border-radius:12px; width:100%; max-width:480px;
    padding:22px; box-shadow:0 8px 32px rgba(0,0,0,.2);
}
.cms-lj-modal-title { font-weight:700; font-size:1rem; color:#1b5e20; margin-bottom:16px; }
.cms-lj-modal-footer { display:flex; gap:10px; margin-top:16px; justify-content:flex-end; }
.cms-lj-toast {
    position:fixed; bottom:24px; right:24px; z-index:2000;
    color:#fff; padding:10px 20px; border-radius:8px;
    font-size:.85rem; font-weight:600;
    box-shadow:0 4px 16px rgba(0,0,0,.18); display:none;
}
</style>

<div class="cms-lj-wrap">
    <div class="cms-lj-header-row">
        <div class="cms-lj-title">🏪 Detalhes das Lojas (<?= count($lojas) ?> filial<?= count($lojas)!==1?'is':'' ?>)</div>
        <?php if ($is_super): ?>
        <button class="cms-lj-btn-add" onclick="cmsLjModalNova()">➕ Nova Filial</button>
        <?php endif; ?>
    </div>

    <?php if (empty($lojas)): ?>
    <div class="cms-lj-card" style="text-align:center;padding:30px;color:#999;">
        Nenhuma loja cadastrada. Use o botão "Nova Filial" para começar.
    </div>
    <?php endif; ?>

    <?php foreach ($lojas as $lj): ?>
    <div class="cms-lj-card">
        <div class="cms-lj-card-header">
            <div class="cms-lj-nome">🏪 <?= htmlspecialchars($lj['nome']) ?></div>
            <span class="<?= $lj['ativo'] ? 'cms-lj-badge-ativo' : 'cms-lj-badge-inativo' ?>">
                <?= $lj['ativo'] ? '● Ativa' : '○ Inativa' ?>
            </span>
        </div>
        <?php if ($lj['endereco']): ?>
        <div style="font-size:.78rem;color:#888;margin-bottom:10px;">📍 <?= htmlspecialchars($lj['endereco']) ?></div>
        <?php endif; ?>

        <div class="cms-lj-row">
            <div>
                <div class="cms-lj-label">Horário de Funcionamento</div>
                <input class="cms-lj-input"
                       id="lj-horario-<?= $lj['id'] ?>"
                       type="text"
                       value="<?= htmlspecialchars($lj['horario_funcionamento'] ?? '') ?>"
                       placeholder="Ex.: Seg–Sáb 7h–19h · Dom 7h–13h"
                       maxlength="120">
                <div class="cms-lj-hint">Exibido na página pública de Nossas Lojas.</div>
            </div>
            <div>
                <div class="cms-lj-label">WhatsApp Business</div>
                <input class="cms-lj-input"
                       id="lj-whatsapp-<?= $lj['id'] ?>"
                       type="text"
                       value="<?= htmlspecialchars($lj['whatsapp_link'] ?? '') ?>"
                       placeholder="85 9 9999-9999"
                       oninput="cmsLjFormatWA(this)"
                       maxlength="60">
                <div class="cms-lj-hint" id="lj-wa-hint-<?= $lj['id'] ?>">
                    Digite o número com DDD (ex.: 85 9 9999-9999). O link wa.me é gerado automaticamente.
                </div>
            </div>
        </div>
        <button class="cms-lj-btn-save" onclick="cmsLjSalvar(<?= $lj['id'] ?>)">
            💾 Salvar
        </button>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Nova Filial -->
<div class="cms-lj-modal-bg" id="cms-lj-modal" style="display:none;">
    <div class="cms-lj-modal">
        <div class="cms-lj-modal-title">🏪 Nova Filial</div>
        <div style="display:grid;gap:12px;">
            <div>
                <div class="cms-lj-label">Nome da Loja *</div>
                <input class="cms-lj-input" id="nova-lj-nome" type="text" placeholder="Ex.: Desffrut Centro" maxlength="80">
            </div>
            <div>
                <div class="cms-lj-label">Endereço</div>
                <input class="cms-lj-input" id="nova-lj-end" type="text" placeholder="Rua, número, bairro, cidade">
            </div>
            <div class="cms-lj-row" style="margin-bottom:0;">
                <div>
                    <div class="cms-lj-label">Telefone</div>
                    <input class="cms-lj-input" id="nova-lj-tel" type="text" placeholder="(85) 9999-9999">
                </div>
                <div>
                    <div class="cms-lj-label">WhatsApp Business</div>
                    <input class="cms-lj-input" id="nova-lj-wa" type="text"
                           placeholder="85 9 9999-9999" oninput="cmsLjFormatWA(this)">
                    <div class="cms-lj-hint" id="nova-lj-wa-hint">Digite com DDD.</div>
                </div>
            </div>
            <div>
                <div class="cms-lj-label">Horário de Funcionamento</div>
                <input class="cms-lj-input" id="nova-lj-horario" type="text"
                       placeholder="Ex.: Seg–Sáb 7h–19h · Dom 7h–13h" maxlength="120">
            </div>
        </div>
        <div class="cms-lj-modal-footer">
            <button class="cms-lj-btn-save" style="background:#888;" onclick="document.getElementById('cms-lj-modal').style.display='none'">Cancelar</button>
            <button class="cms-lj-btn-add" onclick="cmsLjCriarLoja()">✅ Criar Filial</button>
        </div>
    </div>
</div>

<div class="cms-lj-toast" id="cms-lj-toast"></div>

<script>
(function(){
function authH(json) {
    const t = (sessionStorage.getItem('desffrut_token') || '').trim();
    const h = {};
    if (json) h['Content-Type'] = 'application/json';
    if (t)    h['Authorization'] = 'Bearer ' + t;
    return h;
}

function toast(msg, ok=true){
    const el=document.getElementById('cms-lj-toast');
    el.textContent=msg; el.style.background=ok?'#2e7d32':'#c62828';
    el.style.display='block'; setTimeout(()=>el.style.display='none',3500);
}

// ── WhatsApp formatter ────────────────────────────────────────────────────────
// Aceita qualquer formato de entrada, limpa e valida o número
window.cmsLjFormatWA = function(input) {
    const raw   = input.value;
    const nums  = raw.replace(/\D/g, '');  // só dígitos

    // Remove prefixo 55 se já digitado
    let local = nums.startsWith('55') && nums.length > 11 ? nums.slice(2) : nums;

    // Pega o id do campo de hint (campo nas lojas existentes tem id com sufixo, modal não)
    const idParts = input.id.split('-');
    const hintId  = idParts.length > 1 ? 'lj-wa-hint-' + idParts[idParts.length-1] : 'nova-lj-wa-hint';
    const hint    = document.getElementById(hintId);

    if (local.length === 0) {
        input.classList.remove('field-ok','field-err');
        if (hint) { hint.textContent = 'Digite com DDD (ex.: 85 9 9999-9999).'; hint.className='cms-lj-hint'; }
        input.dataset.waLink = '';
        return;
    }

    // Valida: DDD (2 dígitos) + número (8 ou 9 dígitos) = 10 ou 11 dígitos
    if (local.length < 10 || local.length > 11) {
        input.classList.remove('field-ok'); input.classList.add('field-err');
        if (hint) {
            hint.textContent = `⚠ Número inválido (${local.length} dígitos). Deve ter 10 ou 11 com DDD.`;
            hint.className = 'cms-lj-hint err';
        }
        input.dataset.waLink = '';
        return;
    }

    const link = 'https://wa.me/55' + local;
    input.dataset.waLink = link;
    input.classList.remove('field-err'); input.classList.add('field-ok');
    if (hint) {
        hint.textContent = '✅ Link: ' + link;
        hint.className = 'cms-lj-hint';
    }
};

// Inicializa formatter nos campos existentes
document.querySelectorAll('[id^="lj-whatsapp-"]').forEach(el => {
    if (el.value) cmsLjFormatWA(el);
});

// ── Salvar loja existente ─────────────────────────────────────────────────────
window.cmsLjSalvar = async function(id){
    const horario  = document.getElementById('lj-horario-'+id)?.value.trim()||'';
    const waInput  = document.getElementById('lj-whatsapp-'+id);
    const waLink   = waInput?.dataset.waLink || '';
    const waRaw    = (waInput?.value||'').replace(/\D/g,'');

    // Valida WhatsApp se preenchido
    if (waRaw.length > 0 && !waLink) {
        toast('❌ Número de WhatsApp inválido. Corrija antes de salvar.', false);
        return;
    }

    try {
        const r = await fetch(APP.api+'/lojas/'+id, {
            method: 'PUT',
            headers: authH(true),
            body: JSON.stringify({ horario_funcionamento: horario, whatsapp_link: waLink })
        });
        const j = await r.json();
        if(j.status==='ok'){
            toast('✅ Dados da loja salvos!');
        } else {
            toast('❌ Erro: '+(j.message||'Tente novamente.'), false);
        }
    } catch(e){ toast('❌ Falha de rede: '+e.message, false); }
};

// ── Modal nova loja ───────────────────────────────────────────────────────────
window.cmsLjModalNova = function(){
    document.getElementById('cms-lj-modal').style.display='flex';
    document.getElementById('nova-lj-nome').focus();
};

window.cmsLjCriarLoja = async function(){
    const nome    = document.getElementById('nova-lj-nome').value.trim();
    const end     = document.getElementById('nova-lj-end').value.trim();
    const tel     = document.getElementById('nova-lj-tel').value.trim();
    const horario = document.getElementById('nova-lj-horario').value.trim();
    const waInput = document.getElementById('nova-lj-wa');
    const waLink  = waInput.dataset.waLink || '';
    const waRaw   = waInput.value.replace(/\D/g,'');

    if (!nome) { toast('Nome da loja é obrigatório.', false); return; }
    if (waRaw.length > 0 && !waLink) { toast('❌ Número de WhatsApp inválido.', false); return; }

    try {
        const r = await fetch(APP.api+'/lojas', {
            method: 'POST',
            headers: authH(true),
            body: JSON.stringify({ nome, endereco:end, telefone:tel, horario_funcionamento:horario, whatsapp_link:waLink })
        });
        const j = await r.json();
        if(j.status==='ok'){
            document.getElementById('cms-lj-modal').style.display='none';
            toast('✅ Filial criada! Atualizando…');
            setTimeout(()=>carregarAbaAtiva(), 1200);
        } else {
            toast('❌ Erro: '+(j.message||''), false);
        }
    } catch(e){ toast('❌ Falha de rede: '+e.message, false); }
};

// Fecha modal ao clicar fora
document.getElementById('cms-lj-modal').addEventListener('click', function(e){
    if (e.target === this) this.style.display='none';
});

})();
</script>
