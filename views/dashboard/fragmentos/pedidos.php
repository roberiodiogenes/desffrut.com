<?php
/**
 * Desffrut — Fragmento Dashboard: Pedidos & Despacho (Fase 6)
 *
 * Abas: Pendentes | Em Preparo | Em Rota | Histórico
 * Polling automático a cada 20 s nas abas ativas (sem/pendentes/preparo/rota).
 * Roles: super_admin, gerente, caixa (todas as abas) | entregador (Ativos + Em Rota)
 */
?>
<style data-frag="pedidos">
.ped-card {
    background: #fff; border: 1px solid #e0e0e0; border-radius: 10px;
    padding: 16px; margin-bottom: 12px;
    transition: box-shadow .15s;
}
.ped-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.ped-card .ped-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 8px;
}
.ped-badge {
    font-size: .72rem; padding: 3px 8px; border-radius: 20px;
    font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
}
.ped-badge.aguardando_validacao { background:#e8f5e9; color:#1b5e20; border:1px solid #a5d6a7; }
.ped-badge.aguardando        { background:#fff3cd; color:#856404; }
.ped-badge.preparando        { background:#cfe2ff; color:#084298; }
.ped-badge.saiu_para_entrega { background:#d1e7dd; color:#0a3622; }
.ped-badge.entregue          { background:#e2e3e5; color:#41464b; }
.ped-badge.cancelado         { background:#f8d7da; color:#58151c; }

/* Botão de notificação WhatsApp */
.btn-wa-notify {
    background: #25d366; color: #fff; border: none;
    border-radius: 6px; padding: 4px 10px; font-size: .8rem;
    cursor: pointer; text-decoration: none; display: inline-flex;
    align-items: center; gap: 4px;
}
.btn-wa-notify:hover { background: #1ebe5d; color: #fff; }
/* Botões de contato rápido */
.btn-contato-wa  { background:#25d366; color:#fff!important; border:none; border-radius:6px; padding:4px 9px; font-size:.78rem; text-decoration:none; display:inline-flex; align-items:center; gap:3px; }
.btn-contato-wa:hover { background:#1ebe5d; color:#fff!important; }
.btn-contato-tel { background:#1565c0; color:#fff!important; border:none; border-radius:6px; padding:4px 9px; font-size:.78rem; text-decoration:none; display:inline-flex; align-items:center; gap:3px; }
.btn-contato-tel:hover { background:#0d47a1; color:#fff!important; }
.ped-info { font-size: .85rem; color:#444; margin: 4px 0; }
.ped-info strong { color: #212529; }
.ped-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
.ped-vazio { text-align: center; color: #aaa; padding: 40px 0; font-size: .95rem; }

/* ── Checklist Em Preparo ── */
.preparo-checklist { margin: 8px 0; border: 1.5px solid #e0e0e0; border-radius: 8px; overflow: hidden; }
.preparo-item {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 10px; border-bottom: 1px solid #f0f0f0;
    font-size: .84rem; background: #fff; transition: background .1s;
}
.preparo-item:last-child { border-bottom: none; }
.preparo-item.separado { background: #f0fdf4; }
.preparo-item.separado .preparo-nome { text-decoration: line-through; color: #9ca3af; }
.preparo-nome { flex: 1; font-weight: 500; }
.preparo-qtd-input {
    width: 70px; text-align: right; border: 1.5px solid #e0e0e0;
    border-radius: 6px; padding: 3px 6px; font-size: .8rem;
}
.preparo-qtd-input:focus { border-color: #2e7d32; outline: none; }
.preparo-un { font-size: .72rem; color: #9ca3af; min-width: 20px; }
.preparo-del { background: none; border: none; color: #dc3545; cursor: pointer; padding: 2px 4px; font-size: .85rem; opacity: .5; }
.preparo-del:hover { opacity: 1; }
.preparo-footer { display:flex; gap:8px; padding:8px 10px; background:#f9f9f9; align-items:center; flex-wrap:wrap; }
.preparo-add-select { flex:1; font-size:.8rem; border:1.5px solid #e0e0e0; border-radius:6px; padding:4px 6px; }
.preparo-salvar { font-size:.8rem; padding:5px 14px; }

.pedidos-nav .nav-link { font-size: .9rem; }

/* Modal despacho + modal não entregue */
#modal-despacho-backdrop,
#modal-naoentregue-backdrop {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 1040;
    align-items: center; justify-content: center;
}
#modal-despacho-backdrop.aberto,
#modal-naoentregue-backdrop.aberto { display: flex; }
#modal-despacho,
#modal-naoentregue {
    background: #fff; border-radius: 12px; padding: 28px;
    width: 100%; max-width: 420px; box-shadow: 0 8px 32px rgba(0,0,0,.18);
}

/* Botões de motivo */
.btn-motivo {
    display: block; width: 100%; text-align: left;
    padding: 10px 14px; border: 1.5px solid #e0e0e0; border-radius: 8px;
    margin-bottom: 8px; cursor: pointer; background: #fff;
    font-size: .9rem; transition: border-color .12s, background .12s;
}
.btn-motivo:hover  { border-color: #dc3545; background: #fff5f5; }
.btn-motivo.ativo  { border-color: #dc3545; background: #fff5f5; font-weight: 600; }
.btn-motivo .motivo-icon { margin-right: 8px; font-size: 1rem; }
</style>

<!-- Tabs -->
<?php $role = $_SESSION['usuario']['role'] ?? ''; ?>
<ul class="nav nav-tabs pedidos-nav mb-3" id="ped-tabs" role="tablist">
    <?php if ($role !== 'entregador'): ?>
    <li class="nav-item">
        <button class="nav-link active" data-ped-tab="aguardando">
            ⏳ Pendentes <span class="badge bg-warning text-dark ms-1" id="ped-cnt-aguardando"></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-ped-tab="aguardando_validacao">
            📲 Via WA <span class="badge bg-success ms-1" id="ped-cnt-aguardando_validacao"></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-ped-tab="preparando">
            🧺 Em Preparo <span class="badge bg-primary ms-1" id="ped-cnt-preparando"></span>
        </button>
    </li>
    <?php endif; ?>
    <li class="nav-item">
        <button class="nav-link <?= $role === 'entregador' ? 'active' : '' ?>" data-ped-tab="saiu_para_entrega">
            🛵 Em Rota <span class="badge bg-success ms-1" id="ped-cnt-rota"></span>
        </button>
    </li>
    <?php if ($role !== 'entregador'): ?>
    <li class="nav-item">
        <button class="nav-link" data-ped-tab="historico">
            📋 Histórico
        </button>
    </li>
    <?php endif; ?>
</ul>

<!-- Conteúdo das tabs -->
<div id="ped-conteudo">
    <div class="ped-vazio">Carregando pedidos…</div>
</div>

<!-- Alert global -->
<div id="ped-alerta" class="alert d-none mb-2" role="alert"></div>

<!-- Modal de despacho -->
<div id="modal-despacho-backdrop">
    <div id="modal-despacho">
        <h5 class="fw-bold mb-3">🛵 Despachar pedido #<span id="md-pedido-id"></span></h5>
        <label class="form-label fw-semibold">Selecionar entregador:</label>
        <select class="form-select mb-3" id="md-entregador"></select>
        <div class="d-flex gap-2">
            <button class="btn btn-success flex-fill" onclick="confirmarDespacho()">✅ Despachar</button>
            <button class="btn btn-outline-secondary" onclick="fecharModalDespacho()">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal: Não entregue -->
<div id="modal-naoentregue-backdrop">
    <div id="modal-naoentregue">
        <h5 class="fw-bold mb-1">❌ Registrar não-entrega</h5>
        <p class="text-muted small mb-3">Pedido #<span id="mne-pedido-id"></span> — selecione o motivo:</p>

        <div id="mne-motivos">
            <button class="btn-motivo" data-motivo="Destinatário ausente">
                <span class="motivo-icon">🚪</span> Destinatário ausente
            </button>
            <button class="btn-motivo" data-motivo="Estabelecimento fechado">
                <span class="motivo-icon">🔒</span> Estabelecimento fechado
            </button>
            <button class="btn-motivo" data-motivo="Cliente recusou o pedido">
                <span class="motivo-icon">🙅</span> Cliente recusou o pedido
            </button>
            <button class="btn-motivo" data-motivo="Endereço não encontrado">
                <span class="motivo-icon">📍</span> Endereço não encontrado
            </button>
            <button class="btn-motivo" data-motivo="Outro">
                <span class="motivo-icon">💬</span> Outro motivo…
            </button>
        </div>

        <!-- Campo livre para "Outro" -->
        <div id="mne-outro-wrap" style="display:none;" class="mt-2 mb-2">
            <input type="text" id="mne-outro-txt" class="form-control"
                   placeholder="Descreva o motivo…" maxlength="200">
        </div>

        <div id="mne-motivo-selecionado" class="text-muted small mb-3" style="min-height:20px;"></div>

        <div class="d-flex gap-2">
            <button class="btn btn-danger flex-fill" id="btn-confirmar-nao-entrega"
                    onclick="confirmarNaoEntrega()" disabled>
                ❌ Confirmar não-entrega
            </button>
            <button class="btn btn-outline-secondary" onclick="fecharModalNaoEntrega()">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
window.moduloPedidosUI = (function () {

    let _tabAtual   = <?= $role === 'entregador' ? "'saiu_para_entrega'" : "'aguardando'" ?>;
    let _intervalo  = null;
    let _dispatchId = null;   // ID do pedido sendo despachado

    // ── Inicializa tabs ─────────────────────────────────────────────────────────
    function init() {
        document.querySelectorAll('[data-ped-tab]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-ped-tab]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                _tabAtual = btn.dataset.pedTab;
                carregarPedidos();
            });
        });

        carregarPedidos();
        _intervalo = setInterval(() => {
            if (!['entregue','cancelado','historico'].includes(_tabAtual)) {
                carregarPedidos(true);
            }
        }, 20000);
    }

    // ── Carrega pedidos da aba ativa ────────────────────────────────────────────
    async function carregarPedidos(silencioso = false) {
        if (!silencioso) document.getElementById('ped-conteudo').innerHTML =
            '<div class="ped-vazio">Carregando…</div>';

        let url = APP.api + '/pedidos';

        if (_tabAtual === 'historico') {
            url += '?status=entregue,cancelado';
        } else if (_tabAtual === 'aguardando') {
            // Aba "Pendentes" mostra apenas pedidos web aguardando
            url += '?status=aguardando';
        } else {
            url += '?status=' + _tabAtual;
        }

        try {
            const resp = await fetch(url);
            const json = await resp.json();
            if (!resp.ok) throw new Error(json.message);
            renderizarPedidos(json.data || []);
        } catch (e) {
            document.getElementById('ped-conteudo').innerHTML =
                `<div class="alert alert-danger">${e.message}</div>`;
        }
    }

    // ── Renderiza cards ─────────────────────────────────────────────────────────
    function renderizarPedidos(pedidos) {
        // Atualiza badge da aba atual
        const badgeId = _tabAtual === 'saiu_para_entrega' ? 'ped-cnt-rota' : `ped-cnt-${_tabAtual}`;
        const badgeEl = document.getElementById(badgeId);
        if (badgeEl) badgeEl.textContent = pedidos.length || '';

        if (!pedidos.length) {
            document.getElementById('ped-conteudo').innerHTML =
                '<div class="ped-vazio">Nenhum pedido nesta aba.</div>';
            return;
        }

        const html = pedidos.map(p => _cardPedido(p)).join('');
        document.getElementById('ped-conteudo').innerHTML = html;
    }

    // ── Card individual ─────────────────────────────────────────────────────────
    function _cardPedido(p) {
        const role     = APP.usuario?.role || '';
        const total    = parseFloat(p.total).toFixed(2).replace('.', ',');
        const hora     = new Date(p.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        const data     = new Date(p.created_at).toLocaleDateString('pt-BR');
        const endereco = [p.endereco_entrega, p.numero, p.bairro].filter(Boolean).join(', ');
        const fp = {
            dinheiro_na_entrega:    '💵 Dinheiro',
            cartao_debito_entrega:  '💳 Débito',
            cartao_credito_entrega: '💳 Crédito',
            pix:                    '🔑 Pix',
        }[p.forma_pagamento] || p.forma_pagamento || '—';

        // Checklist (preparo) vs badges (outras abas)
        const mostrarChecklist = p.status === 'preparando'
            && ['super_admin','gerente','caixa'].includes(role);

        const itensHtml = mostrarChecklist
            ? _checklistItens(p)
            : (p.itens || []).slice(0, 3).map(it => {
                const qtd = it.unidade === 'kg'
                    ? parseFloat(it.quantidade).toFixed(3) + ' kg'
                    : it.quantidade + ' un';
                return `<span class="badge bg-light text-dark border me-1">${it.produto_nome} × ${qtd}</span>`;
              }).join('') + (p.itens?.length > 3 ? `<span class="text-muted small">+${p.itens.length - 3} mais</span>` : '');

        // Botões de contato rápido
        const botoesContato = _botoesContato(p);

        const acoes = _acoesCard(p);

        return `<div class="ped-card" id="ped-card-${p.id}">
            <div class="ped-header">
                <div>
                    <strong>#${p.id}</strong>
                    <span class="text-muted small ms-1">${data} ${hora}</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    ${botoesContato}
                    <span class="ped-badge ${p.status}">${_labelStatus(p.status)}</span>
                </div>
            </div>
            <div class="ped-info"><strong>${p.cliente_nome}</strong>
                ${p.cliente_telefone ? `<span class="text-muted">· ${p.cliente_telefone}</span>` : ''}
            </div>
            <div class="ped-info">📍 ${endereco}</div>
            <div class="ped-info">${fp}${p.troco_para ? ` · <strong>Troco para R$ ${parseFloat(p.troco_para).toFixed(2).replace('.', ',')}</strong>` : ''}</div>
            ${p.entregador_nome ? `<div class="ped-info">🛵 <strong>${p.entregador_nome}</strong></div>` : ''}
            <div class="mt-1 mb-1">${itensHtml}</div>
            ${p.observacoes ? `<div class="ped-info text-muted">💬 ${p.observacoes}</div>` : ''}
            ${p.motivo_cancelamento ? `<div class="ped-info text-danger small">❌ <strong>Não entregue:</strong> ${p.motivo_cancelamento}</div>` : ''}
            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-1">
                <strong>R$ ${total}</strong>
                <div class="ped-actions">${acoes}</div>
            </div>
        </div>`;
    }

    // ── Botões de contato rápido com cliente ──────────────────────────────────
    function _botoesContato(p) {
        const role = APP.usuario?.role || '';
        const tel  = (p.cliente_telefone || '').replace(/\D/g, '');
        if (!tel) return '';
        if (['entregue','cancelado'].includes(p.status)) return '';

        const num    = tel.length <= 11 ? '55' + tel : tel;
        const nome   = p.cliente_nome || 'cliente';
        const msgWa  = `Olá ${nome}, estamos em contato sobre seu pedido #${p.id} na Desffrut. 🍃`;
        const waUrl  = `https://wa.me/${num}?text=${encodeURIComponent(msgWa)}`;
        const telUrl = `tel:+${num}`;

        return `<a href="${waUrl}" target="_blank" class="btn-contato-wa" title="Chamar no WhatsApp">📱 WA</a>
                <a href="${telUrl}" class="btn-contato-tel" title="Ligar">📞</a>`;
    }

    // ── Checklist de itens para status "preparando" ───────────────────────────
    function _checklistItens(p) {
        const itensList = (p.itens || []).map((it, i) => {
            const qtd = it.unidade === 'kg'
                ? parseFloat(it.quantidade).toFixed(3)
                : parseInt(it.quantidade);
            return `<div class="preparo-item" id="pi-${p.id}-${i}" data-idx="${i}">
                <input type="checkbox" class="form-check-input"
                       onchange="pedCheckItem(this, ${p.id}, ${i})"
                       title="Marcar como separado">
                <span class="preparo-nome">${it.produto_nome}</span>
                <input type="number" class="preparo-qtd-input"
                       value="${qtd}"
                       data-original="${qtd}"
                       data-item-id="${it.item_id}"
                       data-produto-id="${it.produto_id}"
                       data-preco="${it.preco_unitario}"
                       data-unidade="${it.unidade}"
                       min="0" step="${it.unidade === 'kg' ? '0.001' : '1'}"
                       oninput="pedQtdAlterada(this, ${p.id})">
                <span class="preparo-un">${it.unidade}</span>
                <button class="preparo-del" onclick="pedRemoverItemChecklist(${p.id}, ${i})" title="Remover item">✕</button>
            </div>`;
        }).join('');

        // Produto select para adicionar
        const optsProd = (_produtosList || []).map(pr =>
            `<option value="${pr.id}" data-preco="${pr.preco || 0}" data-un="${pr.unidade_medida}">${pr.nome}</option>`
        ).join('');

        return `<div class="preparo-checklist" id="checklist-${p.id}">
            ${itensList}
            <div class="preparo-footer">
                <select class="preparo-add-select" id="add-prod-${p.id}">
                    <option value="">+ Adicionar produto…</option>
                    ${optsProd}
                </select>
                <input type="number" class="preparo-qtd-input" id="add-qtd-${p.id}"
                       placeholder="Qtd" min="0.001" step="0.001" style="width:60px;">
                <button class="btn btn-sm btn-outline-secondary" onclick="pedAdicionarItemChecklist(${p.id})">Adicionar</button>
                <button class="btn btn-success preparo-salvar ms-auto" onclick="pedSalvarItens(${p.id})">💾 Salvar Alterações</button>
            </div>
        </div>`;
    }

    function _labelStatus(s) {
        const map = {
            aguardando_validacao: '📲 Via WhatsApp',
            aguardando:           'Pendente',
            preparando:           'Em Preparo',
            saiu_para_entrega:    'Em Rota',
            entregue:             'Entregue',
            cancelado:            'Cancelado',
        };
        return map[s] || s;
    }

    // ── Gera link wa.me para notificar CLIENTE ─────────────────────────────────
    function _waClienteUrl(tel, msg) {
        const t = (tel || '').replace(/\D/g, '');
        const num = t.length <= 11 ? '55' + t : t;
        return 'https://wa.me/' + num + '?text=' + encodeURIComponent(msg);
    }

    // ── Botões de ação por status e role ────────────────────────────────────────
    function _acoesCard(p) {
        const role = APP.usuario?.role || '';
        const btns = [];
        const tel  = p.cliente_telefone || '';
        const nome = p.cliente_nome || 'cliente';

        // ── Pedidos via WhatsApp aguardando validação da loja ─────────────────
        if (p.status === 'aguardando_validacao' && ['super_admin','gerente','caixa'].includes(role)) {
            btns.push(`<button class="btn btn-sm btn-primary" onclick="pedAceitar(${p.id})">✅ Aceitar</button>`);
            btns.push(`<button class="btn btn-sm btn-outline-danger" onclick="pedCancelar(${p.id})">Cancelar</button>`);
        }

        // ── Pedidos web aguardando ────────────────────────────────────────────
        if (p.status === 'aguardando' && ['super_admin','gerente','caixa'].includes(role)) {
            btns.push(`<button class="btn btn-sm btn-primary" onclick="pedAceitar(${p.id})">✅ Aceitar</button>`);
            btns.push(`<button class="btn btn-sm btn-outline-danger" onclick="pedCancelar(${p.id})">Cancelar</button>`);
        }

        // ── Em Preparo → Notifica cliente + Despacha ─────────────────────────
        if (p.status === 'preparando' && ['super_admin','gerente','caixa'].includes(role)) {
            if (tel) {
                const msgPrep = `Olá ${nome}! 🍃 Seu pedido #${p.id} na Desffrut está sendo preparado agora. Logo ficará pronto!`;
                btns.push(`<a href="${_waClienteUrl(tel, msgPrep)}" target="_blank" class="btn-wa-notify" title="Avisar cliente via WhatsApp">📲 Avisar</a>`);
            }
            btns.push(`<button class="btn btn-sm btn-success" onclick="pedDespachar(${p.id})">🛵 Despachar</button>`);
            btns.push(`<button class="btn btn-sm btn-outline-danger" onclick="pedCancelar(${p.id})">Cancelar</button>`);
        }

        // ── Em Rota → Notifica + Maps + Confirma entrega ─────────────────────
        if (p.status === 'saiu_para_entrega') {
            if (tel) {
                const entregador = p.entregador_nome ? ` Entregador: ${p.entregador_nome}.` : '';
                const msgRota = `Seu pedido #${p.id} da Desffrut saiu para entrega! 🛵${entregador} Fique atento, está chegando!`;
                btns.push(`<a href="${_waClienteUrl(tel, msgRota)}" target="_blank" class="btn-wa-notify" title="Notificar cliente — pedido em rota">📲 Em Rota</a>`);
            }
            if (role === 'entregador') {
                btns.push(`<a href="https://www.google.com/maps/search/${encodeURIComponent(p.endereco_entrega + ' ' + (p.numero||'') + ' ' + (p.bairro||''))}" target="_blank" class="btn btn-sm btn-outline-primary">📍 Maps</a>`);
                btns.push(`<a href="https://waze.com/ul?q=${encodeURIComponent(p.endereco_entrega + ' ' + (p.bairro||''))}" target="_blank" class="btn btn-sm btn-outline-secondary">🗺 Waze</a>`);
                btns.push(`<button class="btn btn-sm btn-success" onclick="pedEntregar(${p.id})">✅ Entreguei</button>`);
                btns.push(`<button class="btn btn-sm btn-danger" onclick="pedNaoEntregue(${p.id})">❌ Não entregue</button>`);
            } else {
                btns.push(`<a href="https://www.google.com/maps/search/${encodeURIComponent(p.endereco_entrega + ' ' + (p.numero||'') + ' ' + (p.bairro||''))}" target="_blank" class="btn btn-sm btn-outline-primary">📍 Rota</a>`);
            }
        }

        // Link de rastreamento (staff)
        btns.push(`<a href="${APP.base}/pedidos/${p.id}/status" target="_blank" class="btn btn-sm btn-outline-secondary" title="Rastreamento">🔗</a>`);
        return btns.join('');
    }

    // ── Lista de produtos (cache para checklist de preparo) ────────────────────
    let _produtosList = [];
    (async function _carregarProdutos() {
        try {
            const r = await fetch(APP.api + '/produtos');
            const j = await r.json();
            _produtosList = (j.data || []).filter(p => p.ativo);
        } catch (_) {}
    })();

    // ── Checklist: marcar item como separado ───────────────────────────────────
    window.pedCheckItem = function (cb, pedId, idx) {
        const row = document.getElementById(`pi-${pedId}-${idx}`);
        if (!row) return;
        row.classList.toggle('separado', cb.checked);
    };

    // ── Checklist: detectar alteração de quantidade ────────────────────────────
    window.pedQtdAlterada = function (inp, pedId) {
        const original = parseFloat(inp.dataset.original || 0);
        const novo     = parseFloat(inp.value || 0);
        inp.style.borderColor = novo !== original ? '#d97706' : '';
    };

    // ── Checklist: remover item da lista (DOM apenas) ──────────────────────────
    window.pedRemoverItemChecklist = function (pedId, idx) {
        const row = document.getElementById(`pi-${pedId}-${idx}`);
        if (row) {
            row.style.opacity = '0.3';
            row.style.textDecoration = 'line-through';
            row.dataset.removido = '1';
            const inputs = row.querySelectorAll('input');
            inputs.forEach(i => i.disabled = true);
        }
    };

    // ── Checklist: adicionar produto ───────────────────────────────────────────
    window.pedAdicionarItemChecklist = function (pedId) {
        const sel    = document.getElementById(`add-prod-${pedId}`);
        const qtdInp = document.getElementById(`add-qtd-${pedId}`);
        const prodId = sel?.value;
        const qtd    = parseFloat(qtdInp?.value || 0);
        if (!prodId || qtd <= 0) { alert('Selecione um produto e informe a quantidade.'); return; }

        const opt   = sel.options[sel.selectedIndex];
        const preco = parseFloat(opt?.dataset.preco || 0);
        const un    = opt?.dataset.un || 'un';
        const nome  = opt?.text || '';

        const checklist = document.getElementById(`checklist-${pedId}`);
        const footer    = checklist?.querySelector('.preparo-footer');
        if (!footer) return;

        const idx = checklist.querySelectorAll('.preparo-item').length;
        const row = document.createElement('div');
        row.className   = 'preparo-item';
        row.id          = `pi-${pedId}-${idx}`;
        row.dataset.idx = idx;
        row.innerHTML   = `
            <input type="checkbox" class="form-check-input" onchange="pedCheckItem(this,${pedId},${idx})">
            <span class="preparo-nome">${nome} <span class="badge bg-warning text-dark" style="font-size:.65rem;">NOVO</span></span>
            <input type="number" class="preparo-qtd-input"
                   value="${un==='kg'?qtd.toFixed(3):Math.round(qtd)}"
                   data-original="0" data-item-id="" data-produto-id="${prodId}"
                   data-preco="${preco}" data-unidade="${un}"
                   min="0" step="${un==='kg'?'0.001':'1'}"
                   oninput="pedQtdAlterada(this,${pedId})">
            <span class="preparo-un">${un}</span>
            <button class="preparo-del" onclick="pedRemoverItemChecklist(${pedId},${idx})">✕</button>`;
        checklist.insertBefore(row, footer);
        sel.value = '';
        if (qtdInp) qtdInp.value = '';
    };

    // ── Checklist: salvar todas as alterações no servidor ─────────────────────
    window.pedSalvarItens = async function (pedId) {
        const checklist = document.getElementById(`checklist-${pedId}`);
        if (!checklist) return;
        const itens = [];
        checklist.querySelectorAll('.preparo-item').forEach(row => {
            if (row.dataset.removido === '1') return; // excluídos
            const qtdInp = row.querySelector('.preparo-qtd-input');
            const prodId = qtdInp?.dataset.produtoId;
            const preco  = parseFloat(qtdInp?.dataset.preco || 0);
            const qtd    = parseFloat(qtdInp?.value || 0);
            if (!prodId || qtd <= 0) return;
            itens.push({ produto_id: parseInt(prodId), quantidade: qtd, preco_unitario: preco });
        });
        if (!itens.length) { alert('Nenhum item válido para salvar.'); return; }
        try {
            const resp = await fetch(`${APP.api}/pedidos/${pedId}/itens`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ itens }),
            });
            const json = await resp.json();
            if (!resp.ok) throw new Error(json.message);
            _alerta(`✅ Itens do pedido #${pedId} atualizados.`, 'success');
            carregarPedidos(true);
        } catch (e) {
            _alerta('Erro ao salvar: ' + e.message, 'danger');
        }
    };

    // ── Ações ────────────────────────────────────────────────────────────────────
    window.pedAceitar = async function (id) {
        await _patchStatus(id, 'preparando', 'Pedido aceito — em preparo.');
    };

    window.pedCancelar = async function (id) {
        if (!confirm('Cancelar pedido #' + id + '?')) return;
        await _patchStatus(id, 'cancelado', 'Pedido cancelado.');
    };

    window.pedEntregar = async function (id) {
        if (!confirm('Confirmar entrega do pedido #' + id + '?')) return;
        await _patchStatus(id, 'entregue', '✅ Entrega confirmada!');
    };

    // ── Modal: Não entregue ──────────────────────────────────────────────────
    let _naoEntregueId     = null;
    let _motivoSelecionado = null;

    window.pedNaoEntregue = function (id) {
        _naoEntregueId     = id;
        _motivoSelecionado = null;
        document.getElementById('mne-pedido-id').textContent = id;
        document.getElementById('btn-confirmar-nao-entrega').disabled = true;
        document.getElementById('mne-motivo-selecionado').textContent = '';
        document.getElementById('mne-outro-wrap').style.display = 'none';
        document.getElementById('mne-outro-txt').value = '';
        document.querySelectorAll('.btn-motivo').forEach(b => b.classList.remove('ativo'));
        document.getElementById('modal-naoentregue-backdrop').classList.add('aberto');
    };

    window.fecharModalNaoEntrega = function () {
        document.getElementById('modal-naoentregue-backdrop').classList.remove('aberto');
        _naoEntregueId     = null;
        _motivoSelecionado = null;
    };

    // Delegação de clique nos botões de motivo
    document.getElementById('mne-motivos').addEventListener('click', e => {
        const btn = e.target.closest('.btn-motivo');
        if (!btn) return;
        document.querySelectorAll('.btn-motivo').forEach(b => b.classList.remove('ativo'));
        btn.classList.add('ativo');
        const motivo = btn.dataset.motivo;
        const outroWrap = document.getElementById('mne-outro-wrap');
        if (motivo === 'Outro') {
            outroWrap.style.display = '';
            _motivoSelecionado = null; // aguarda texto livre
            document.getElementById('btn-confirmar-nao-entrega').disabled = true;
            document.getElementById('mne-motivo-selecionado').textContent = '';
        } else {
            outroWrap.style.display = 'none';
            _motivoSelecionado = motivo;
            document.getElementById('mne-motivo-selecionado').textContent = '✓ ' + motivo;
            document.getElementById('btn-confirmar-nao-entrega').disabled = false;
        }
    });

    // Input de "Outro"
    document.getElementById('mne-outro-txt').addEventListener('input', e => {
        const val = e.target.value.trim();
        _motivoSelecionado = val || null;
        document.getElementById('btn-confirmar-nao-entrega').disabled = !val;
        document.getElementById('mne-motivo-selecionado').textContent = val ? '✓ ' + val : '';
    });

    window.confirmarNaoEntrega = async function () {
        if (!_naoEntregueId || !_motivoSelecionado) return;
        const id     = _naoEntregueId;
        const motivo = _motivoSelecionado;
        fecharModalNaoEntrega();
        await _patchStatus(id, 'cancelado', '❌ Não-entrega registrada.', { motivo_cancelamento: motivo });
    };

    window.pedDespachar = async function (id) {
        _dispatchId = id;
        document.getElementById('md-pedido-id').textContent = id;
        document.getElementById('md-entregador').innerHTML = '<option value="">Carregando…</option>';
        document.getElementById('modal-despacho-backdrop').classList.add('aberto');

        const resp = await fetch(APP.api + '/entregadores');
        const json = await resp.json();
        const sel  = document.getElementById('md-entregador');
        sel.innerHTML = '<option value="">Selecione o entregador…</option>';
        (json.data || []).forEach(e => {
            const em_rota = e.pedidos_em_rota > 0 ? ` (${e.pedidos_em_rota} em rota)` : '';
            sel.innerHTML += `<option value="${e.id}">${e.nome}${em_rota}</option>`;
        });
    };

    window.fecharModalDespacho = function () {
        document.getElementById('modal-despacho-backdrop').classList.remove('aberto');
        _dispatchId = null;
    };

    window.confirmarDespacho = async function () {
        const eid = document.getElementById('md-entregador').value;
        if (!eid) { alert('Selecione um entregador.'); return; }

        const body = { status: 'saiu_para_entrega', entregador_id: parseInt(eid) };
        await _patchStatus(_dispatchId, null, 'Pedido despachado!', body);
        fecharModalDespacho();
    };

    async function _patchStatus(id, status, msgOk, bodyExtra = {}) {
        try {
            const payload = status ? { status, ...bodyExtra } : bodyExtra;
            const resp = await fetch(APP.api + '/pedidos/' + id, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const json = await resp.json();
            if (!resp.ok) throw new Error(json.message);
            _alerta(msgOk, 'success');
            carregarPedidos(true);
        } catch (e) {
            _alerta('Erro: ' + e.message, 'danger');
        }
    }

    function _alerta(msg, tipo) {
        const el = document.getElementById('ped-alerta');
        el.textContent = msg;
        el.className   = `alert alert-${tipo}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.className = 'alert d-none'; }, 4000);
    }

    init();
    return { recarregar: carregarPedidos };

})();
</script>
