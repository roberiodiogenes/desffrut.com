<?php
/**
 * Desffrut — Rastreamento de Pedido (Polling 20s)
 * Rota: /pedidos/{id}/status
 * Exige: cliente logado
 */

$roles_permitidos = ['cliente', 'caixa', 'gerente', 'super_admin', 'entregador'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

// Pega o ID do pedido via query string (?pedido_id=N) injetado pelo .htaccess
$pedido_id = filter_input(INPUT_GET, 'pedido_id', FILTER_VALIDATE_INT) ?: 0;
// fallback: extrai da URI para compatibilidade com versões anteriores do .htaccess
if (!$pedido_id) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    preg_match('#/pedidos/(\d+)/status#', $uri, $m);
    $pedido_id = isset($m[1]) ? (int) $m[1] : 0;
}

if (!$pedido_id) {
    header('Location: ' . BASE_PATH . '/');
    exit;
}

$titulo_pagina  = 'Rastreando Pedido #' . $pedido_id;
$mostrar_sacola = false;
require_once __DIR__ . '/../../app/views/layout/header.php';
?>

<div class="container py-4" style="max-width:540px;">

    <h2 class="fw-bold mb-1">📦 Pedido #<?= $pedido_id ?></h2>
    <p class="text-muted small mb-4">Atualização automática a cada 20 segundos.</p>

    <div id="track-alerta" class="alert d-none mb-3"></div>

    <!-- Timeline de status -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="track-timeline" id="track-timeline">
                <!-- preenchido via JS -->
            </div>
        </div>
    </div>

    <!-- Detalhes -->
    <div class="card shadow-sm mb-3" id="track-detalhes" style="display:none;">
        <div class="card-header fw-semibold">Detalhes do pedido</div>
        <div class="card-body" id="track-detalhes-body"></div>
    </div>

    <div id="poll-status" class="text-center text-muted small mt-1 mb-3">
        <span id="poll-dot" style="display:inline-block;width:8px;height:8px;border-radius:50%;
              background:#4caf50;margin-right:5px;animation:blink 1.5s infinite;"></span>
        Atualizando automaticamente…
    </div>
    <a href="<?= BASE_PATH ?>/" class="btn btn-outline-secondary btn-sm">← Voltar ao catálogo</a>
</div>
<style>
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.15} }
</style>

<style>
/* Timeline de rastreamento */
.track-step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    opacity: .35;
    transition: opacity .3s;
}
.track-step.ativo  { opacity: 1; }
.track-step.feito  { opacity: .75; }
.track-dot {
    width: 32px; height: 32px; border-radius: 50%;
    background: #dee2e6; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
}
.track-step.ativo  .track-dot { background: #198754; color: #fff; box-shadow: 0 0 0 4px #19875440; }
.track-step.feito  .track-dot { background: #6c757d; color: #fff; }
.track-step.cancel .track-dot { background: #dc3545; color: #fff; opacity: 1; }
.track-label { font-size: .95rem; line-height: 1.3; }
.track-label strong { display: block; }
.track-label small  { color: #6c757d; }
.track-connector {
    width: 2px; height: 20px; background: #dee2e6;
    margin-left: 15px;
}
.track-connector.feito { background: #6c757d; }
</style>

<script>
const PEDIDO_ID = <?= $pedido_id ?>;
let _intervalo;

const PASSOS = [
    { status: 'aguardando',        icon: '🕐', label: 'Pedido recebido',    desc: 'Aguardando confirmação da loja…' },
    { status: 'preparando',        icon: '🧺', label: 'Em preparação',      desc: 'A loja está separando seus produtos.' },
    { status: 'saiu_para_entrega', icon: '🛵', label: 'Saiu para entrega',  desc: 'Seu pedido está a caminho!' },
    { status: 'entregue',          icon: '✅', label: 'Entregue',            desc: 'Pedido concluído. Bom apetite!' },
];
const ORDEM = ['aguardando','preparando','saiu_para_entrega','entregue'];

async function buscarStatus() {
    try {
        const resp = await fetch(APP.api + '/pedidos/' + PEDIDO_ID);
        const json = await resp.json();
        if (!resp.ok) throw new Error(json.message || 'Erro ao buscar pedido.');
        renderizarTimeline(json.data);
        renderizarDetalhes(json.data);

        // Para o polling quando o pedido está concluído
        if (['entregue', 'cancelado'].includes(json.data.status)) {
            clearInterval(_intervalo);
            const ps = document.getElementById('poll-status');
            if (ps) ps.style.display = 'none';
        }
    } catch (e) {
        document.getElementById('track-alerta').textContent = e.message;
        document.getElementById('track-alerta').className = 'alert alert-warning';
    }
}

function renderizarTimeline(pedido) {
    const el     = document.getElementById('track-timeline');
    const atual  = pedido.status;
    const idxAtual = ORDEM.indexOf(atual);

    if (atual === 'cancelado') {
        el.innerHTML = `
            <div class="track-step cancel">
                <div class="track-dot">❌</div>
                <div class="track-label">
                    <strong>Pedido cancelado</strong>
                    <small>Entre em contato com a loja se tiver dúvidas.</small>
                </div>
            </div>`;
        return;
    }

    el.innerHTML = PASSOS.map((p, i) => {
        const feito = i < idxAtual;
        const ativo = p.status === atual;
        const cls   = feito ? 'feito' : ativo ? 'ativo' : '';
        const conCls = feito ? 'feito' : '';
        const conec = i < PASSOS.length - 1
            ? `<div class="track-connector ${conCls}"></div>`
            : '';
        return `
            <div class="track-step ${cls}">
                <div>
                    <div class="track-dot">${feito ? '✓' : p.icon}</div>
                    ${conec}
                </div>
                <div class="track-label">
                    <strong>${p.label}</strong>
                    <small>${ativo ? p.desc : (feito ? '✓ Concluído' : 'Aguardando…')}</small>
                </div>
            </div>`;
    }).join('');
}

function renderizarDetalhes(pedido) {
    const wrap = document.getElementById('track-detalhes');
    const body = document.getElementById('track-detalhes-body');
    wrap.style.display = '';

    const total = parseFloat(pedido.total).toFixed(2).replace('.', ',');
    const fp = {
        dinheiro_na_entrega:    '💵 Dinheiro',
        cartao_debito_entrega:  '💳 Débito',
        cartao_credito_entrega: '💳 Crédito',
        pix:                    '🔑 Pix',
    }[pedido.forma_pagamento] || pedido.forma_pagamento;

    const troco = pedido.troco_para
        ? `<p class="mb-1"><strong>💵 Troco para:</strong> <span class="text-success fw-semibold">R$ ${parseFloat(pedido.troco_para).toFixed(2).replace('.', ',')}</span></p>`
        : '';

    const entregador = pedido.entregador_nome
        ? `<p class="mb-1"><strong>🛵 Entregador:</strong> ${pedido.entregador_nome}</p>`
        : '';

    // Botões de navegação quando saiu para entrega
    const enderecoNav = [pedido.endereco_entrega, pedido.numero, pedido.bairro].filter(Boolean).join(' ');
    const botoes_nav = pedido.status === 'saiu_para_entrega' && enderecoNav ? `
        <div class="d-flex gap-2 mt-2 mb-1">
            <a href="https://www.google.com/maps/search/${encodeURIComponent(enderecoNav)}"
               target="_blank" class="btn btn-sm btn-primary flex-fill">📍 Google Maps</a>
            <a href="https://waze.com/ul?q=${encodeURIComponent(enderecoNav)}"
               target="_blank" class="btn btn-sm btn-info flex-fill text-white">🗺 Waze</a>
        </div>` : '';

    const itens = (pedido.itens || []).map(it => {
        const qtd = it.unidade === 'kg'
            ? parseFloat(it.quantidade).toFixed(3) + ' kg'
            : it.quantidade + ' un';
        return `<li class="list-group-item d-flex justify-content-between py-1">
                    <span>${it.produto_nome} × ${qtd}</span>
                    <span>R$ ${parseFloat(it.subtotal).toFixed(2).replace('.', ',')}</span>
                </li>`;
    }).join('');

    body.innerHTML = `
        <p class="mb-1"><strong>🏪 Loja:</strong> ${pedido.loja_nome || '—'}</p>
        <p class="mb-1"><strong>📍 Entrega:</strong> ${pedido.endereco_entrega || ''}${pedido.numero ? ', ' + pedido.numero : ''}${pedido.complemento ? ' — ' + pedido.complemento : ''}${pedido.bairro ? ' · ' + pedido.bairro : ''}</p>
        ${botoes_nav}
        <p class="mb-1"><strong>💰 Pagamento:</strong> ${fp}</p>
        ${troco}
        ${entregador}
        <hr class="my-2">
        <ul class="list-group list-group-flush mb-2">${itens}</ul>
        <div class="d-flex justify-content-between fw-bold">
            <span>Total</span>
            <span>R$ ${total}</span>
        </div>
        ${pedido.pontos_ganhos > 0 ? `<div class="text-end small text-success mt-1">+${pedido.pontos_ganhos} pontos de fidelidade</div>` : ''}
    `;
}

// Inicia polling
buscarStatus();
_intervalo = setInterval(buscarStatus, 20000);

// Para polling ao sair da página
window.addEventListener('beforeunload', () => clearInterval(_intervalo));
</script>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
