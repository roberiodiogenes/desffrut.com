<?php
/**
 * Desffrut — Página pública de Parcerias (Fase 10)
 * Formulário de captação B2B. Sem autenticação.
 * Dados enviados para POST /api/v1/leads/novo
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/maintenance_check.php';

$titulo_pagina  = 'Seja Nosso Parceiro';
$og_description = 'Fornecimento de hortifruti para empresas, restaurantes e mercados. Fale conosco e saiba mais sobre compras em volume.';
$canonical_url  = BASE_URL . BASE_PATH . '/parcerias';
$nav_ativa      = 'parcerias';
$mostrar_sacola = true;
$mostrar_busca  = false;
require_once __DIR__ . '/../../app/views/layout/header.php';
?>

<style>
/* ── Parcerias ── */
.parc-hero {
    background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 60%, #43a047 100%);
    color: #fff;
    padding: 64px 20px 48px;
    text-align: center;
}
.parc-hero h1  { font-size: 2rem; font-weight: 800; margin: 0 0 10px; }
.parc-hero p   { font-size: 1.05rem; opacity: .9; max-width: 540px; margin: 0 auto; }

.parc-section  { max-width: 680px; margin: 0 auto; padding: 40px 20px 60px; }

.parc-beneficios {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
    gap: 16px;
    margin-bottom: 36px;
}
.parc-bene-card {
    background: #f1f8e9;
    border: 1px solid #c8e6c9;
    border-radius: 12px;
    padding: 18px 14px;
    text-align: center;
}
.parc-bene-icon { font-size: 1.8rem; margin-bottom: 8px; }
.parc-bene-card strong { display: block; font-size: .88rem; color: #1b5e20; }
.parc-bene-card small  { font-size: .77rem; color: #555; margin-top: 4px; display: block; }

.parc-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,.08);
    padding: 32px 28px;
}
.parc-card h2   { font-size: 1.2rem; color: #1b5e20; margin: 0 0 20px; }
.parc-group     { margin-bottom: 16px; }
.parc-group label {
    display: block; font-size: .8rem; font-weight: 600;
    color: #444; margin-bottom: 5px;
}
.parc-group label span { color: #c62828; }
.parc-input {
    width: 100%; padding: 10px 12px; box-sizing: border-box;
    border: 1.5px solid #e0e0e0; border-radius: 8px;
    font-size: .92rem; transition: border-color .2s;
}
.parc-input:focus { outline: none; border-color: #2e7d32; }
.parc-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media(max-width:520px){ .parc-row { grid-template-columns: 1fr; } }
.parc-btn {
    width: 100%; padding: 13px; background: #2e7d32; color: #fff;
    border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
    cursor: pointer; transition: background .2s; margin-top: 8px;
}
.parc-btn:hover   { background: #1b5e20; }
.parc-btn:disabled{ background: #aaa; cursor: not-allowed; }

#parc-msg {
    display: none; margin-top: 18px; padding: 14px 16px;
    border-radius: 8px; font-size: .9rem; font-weight: 500;
}
.parc-msg-ok  { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
.parc-msg-err { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; }
</style>

<!-- Hero -->
<div class="parc-hero">
    <h1>🤝 Seja Nosso Parceiro</h1>
    <p>Compras em volume com preços diferenciados. Preencha o formulário e entraremos em contato em até 24 horas.</p>
</div>

<div class="parc-section">

    <!-- Benefícios -->
    <div class="parc-beneficios">
        <div class="parc-bene-card">
            <div class="parc-bene-icon">💰</div>
            <strong>Preços de Atacado</strong>
            <small>Condições exclusivas para compras em volume</small>
        </div>
        <div class="parc-bene-card">
            <div class="parc-bene-icon">🚚</div>
            <strong>Entrega Programada</strong>
            <small>Entregas regulares na sua empresa ou estabelecimento</small>
        </div>
        <div class="parc-bene-card">
            <div class="parc-bene-icon">🌱</div>
            <strong>Produtos Frescos</strong>
            <small>Direto do CEASA para você com máxima qualidade</small>
        </div>
    </div>

    <!-- Formulário -->
    <div class="parc-card">
        <h2>📋 Dados para contato</h2>
        <form id="parc-form" autocomplete="on">

            <div class="parc-row">
                <div class="parc-group">
                    <label>Nome <span>*</span></label>
                    <input class="parc-input" type="text" name="nome" required
                           placeholder="Seu nome completo" autocomplete="name">
                </div>
                <div class="parc-group">
                    <label>Empresa / Estabelecimento</label>
                    <input class="parc-input" type="text" name="empresa"
                           placeholder="Ex.: Restaurante Bom Sabor" autocomplete="organization">
                </div>
            </div>

            <div class="parc-row">
                <div class="parc-group">
                    <label>WhatsApp <span>*</span></label>
                    <input class="parc-input" type="tel" name="telefone" required
                           placeholder="(85) 99999-9999" autocomplete="tel">
                </div>
                <div class="parc-group">
                    <label>Bairro</label>
                    <input class="parc-input" type="text" name="bairro"
                           placeholder="Ex.: Messejana" autocomplete="address-level3">
                </div>
            </div>

            <div class="parc-group">
                <label>E-mail</label>
                <input class="parc-input" type="email" name="email"
                       placeholder="seuemail@exemplo.com" autocomplete="email">
            </div>

            <div class="parc-group">
                <label>Mensagem <small style="color:#888;font-weight:400;">(opcional)</small></label>
                <textarea class="parc-input" name="mensagem" rows="3"
                          placeholder="Conte um pouco sobre sua necessidade (produtos, quantidade, frequência)…"></textarea>
            </div>

            <button class="parc-btn" type="submit" id="parc-btn">
                Enviar solicitação de parceria →
            </button>

            <div id="parc-msg"></div>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>

<script>
(function () {
    const API = '<?= BASE_PATH ?>/api/v1';
    const form = document.getElementById('parc-form');
    const btn  = document.getElementById('parc-btn');
    const msg  = document.getElementById('parc-msg');

    form.addEventListener('submit', async e => {
        e.preventDefault();
        btn.disabled = true;
        btn.textContent = 'Enviando…';
        msg.style.display = 'none';

        const data = {};
        new FormData(form).forEach((v, k) => { if (v) data[k] = v; });

        try {
            const r = await fetch(API + '/leads/novo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            const j = await r.json();

            if (j.status === 'ok') {
                msg.className = 'parc-msg-ok';
                msg.textContent = '✅ ' + j.message;
                form.reset();
            } else {
                msg.className = 'parc-msg-err';
                msg.textContent = '❌ ' + (j.message || 'Erro ao enviar. Tente novamente.');
                btn.disabled = false;
                btn.textContent = 'Enviar solicitação de parceria →';
            }
        } catch (_) {
            msg.className = 'parc-msg-err';
            msg.textContent = '❌ Falha de conexão. Verifique sua internet e tente novamente.';
            btn.disabled = false;
            btn.textContent = 'Enviar solicitação de parceria →';
        }

        msg.style.display = 'block';
    });
})();
</script>
