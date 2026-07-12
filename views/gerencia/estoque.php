<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/database.php';
iniciar_sessao();

$roles_permitidos = ['gerente', 'super_admin'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$titulo_pagina  = 'Gestão de Estoque';
$mostrar_sacola = false;
require_once __DIR__ . '/../../app/views/layout/header.php';

$u = usuario_logado();

// Carrega lojas para o seletor. Gerente é responsável por todas as lojas (não há
// um gerente por loja) — tem a mesma visão multi-loja do super_admin.
$stmt_lojas = db()->query('SELECT id, nome FROM lojas WHERE ativo = 1 ORDER BY nome');
$lojas      = $stmt_lojas->fetchAll();
$loja_padrao = ($u['role'] === 'gerente' || $u['role'] === 'super_admin')
    ? ($lojas[0]['id'] ?? 1)
    : ($u['loja_id'] ?? ($lojas[0]['id'] ?? 1));
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-verde mb-0">📦 Gestão de Estoque</h2>
            <small class="text-muted">Inventário, ajustes e registro de quebras</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_PATH ?>/gerencia/relatorios" class="btn btn-outline-success btn-sm">
                📊 Relatórios
            </a>
            <button class="btn btn-warning btn-sm fw-bold" onclick="abrirQuebraModal()">
                ⚠️ Registrar Quebra
            </button>
        </div>
    </div>

    <!-- ─── Seletor de loja ──────────────────────────────────────────────── -->
    <div class="card mb-3">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <label class="fw-semibold mb-0">🏪 Loja:</label>
            <select id="select-loja-est" class="form-select form-select-sm" style="max-width:260px;"
                    onchange="carregarEstoque(this.value)">
                <?php foreach ($lojas as $l): ?>
                <option value="<?= $l['id'] ?>"
                    <?= $l['id'] == $loja_padrao ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <input type="search" id="filtro-estoque" class="form-control form-control-sm"
                   style="max-width:240px;" placeholder="🔍 Filtrar produto…"
                   oninput="filtrarEstoque()">

            <span id="alertas-criticos" class="badge bg-danger ms-auto" style="display:none;"></span>
        </div>
    </div>

    <!-- ─── Tabela de estoque ────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-body p-0">
            <div id="est-carregando" class="text-center py-5">
                <div class="spinner-border text-success"></div>
            </div>
            <div class="table-responsive" id="est-wrapper" style="display:none;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-success">
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th class="text-end">Quantidade</th>
                            <th class="text-end">Estoque Mín.</th>
                            <th>Situação</th>
                            <th class="text-center">Ajustar</th>
                        </tr>
                    </thead>
                    <tbody id="est-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ─── Modal: Ajustar Estoque ────────────────────────────────────────────── -->
<div class="modal fade" id="modalEstoque" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">✏️ Ajustar Estoque</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="est-modal-produto" class="fw-bold mb-3"></p>
                <div class="mb-3">
                    <label class="form-label">Nova quantidade (<span id="est-modal-unidade"></span>)</label>
                    <input type="number" id="est-modal-qtd" class="form-control"
                           step="0.001" min="0">
                </div>
                <div class="mb-3">
                    <label class="form-label">Estoque mínimo</label>
                    <input type="number" id="est-modal-min" class="form-control"
                           step="0.001" min="0">
                </div>
                <input type="hidden" id="est-modal-pid">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" onclick="salvarEstoque()">💾 Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- ─── Modal: Registrar Quebra ───────────────────────────────────────────── -->
<div class="modal fade" id="modalQuebra" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">⚠️ Registrar Quebra / Avaria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Produto <span class="text-danger">*</span></label>
                    <select id="qb-produto" class="form-select"></select>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Quantidade perdida <span class="text-danger">*</span></label>
                        <input type="number" id="qb-quantidade" class="form-control"
                               step="0.001" min="0.001" placeholder="Ex.: 2.5">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Loja</label>
                        <select id="qb-loja" class="form-select">
                            <?php foreach ($lojas as $l): ?>
                            <option value="<?= $l['id'] ?>"
                                <?= $l['id'] == $loja_padrao ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3 mt-2">
                    <label class="form-label fw-semibold">Motivo <span class="text-danger">*</span></label>
                    <select id="qb-motivo-select" class="form-select mb-1">
                        <option value="">Selecione ou descreva abaixo…</option>
                        <option value="Vencimento / perecimento">Vencimento / perecimento</option>
                        <option value="Dano no transporte">Dano no transporte</option>
                        <option value="Dano no armazenamento">Dano no armazenamento</option>
                        <option value="Furto">Furto</option>
                        <option value="Erro de pesagem">Erro de pesagem</option>
                    </select>
                    <input type="text" id="qb-motivo" class="form-control"
                           placeholder="Descreva o motivo…">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning fw-bold" onclick="salvarQuebra()">⚠️ Confirmar Quebra</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>

<script>
const token   = sessionStorage.getItem('desffrut_token') || '';
const headers = { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token };

let estoqueAtual = [];
const lojaAtual  = () => parseInt(document.getElementById('select-loja-est').value);

// ── Carregar estoque ──────────────────────────────────────────────────────────
async function carregarEstoque(lojaId) {
    document.getElementById('est-carregando').style.display = '';
    document.getElementById('est-wrapper').style.display    = 'none';

    const r = await fetch(`${APP.api}/estoque?loja_id=${lojaId}`, { headers });
    const j = await r.json();
    estoqueAtual = j.data || [];

    // Conta críticos
    const criticos = estoqueAtual.filter(p => p.situacao === 'critico' || p.situacao === 'sem_estoque').length;
    const badge = document.getElementById('alertas-criticos');
    badge.style.display  = criticos > 0 ? '' : 'none';
    badge.textContent    = `⚠️ ${criticos} crítico(s)`;

    renderEstoque();
}

function filtrarEstoque() {
    renderEstoque();
}

const situacaoBadge = s => ({
    ok:          '<span class="badge bg-success">✅ OK</span>',
    baixo:       '<span class="badge bg-warning text-dark">⚠️ Baixo</span>',
    critico:     '<span class="badge bg-danger">🔴 Crítico</span>',
    sem_estoque: '<span class="badge bg-dark">❌ Zerado</span>',
}[s] || '<span class="badge bg-secondary">—</span>');

const icone = cat => ({ frutas:'🍎', verduras:'🥬', legumes:'🥕', outros:'📦' }[cat] || '📦');

function renderEstoque() {
    const busca = document.getElementById('filtro-estoque').value.toLowerCase();
    const lista = estoqueAtual.filter(p => !busca || p.nome.toLowerCase().includes(busca));

    document.getElementById('est-carregando').style.display = 'none';
    document.getElementById('est-wrapper').style.display    = '';

    document.getElementById('est-body').innerHTML = lista.map(p => {
        const qtd = p.quantidade !== null ? parseFloat(p.quantidade).toFixed(3) : '—';
        const min = p.estoque_minimo ? parseFloat(p.estoque_minimo).toFixed(3) : '—';
        return `
        <tr class="${p.situacao === 'critico' || p.situacao === 'sem_estoque' ? 'table-danger' : ''}">
            <td><span class="fw-semibold">${icone(p.categoria)} ${p.nome}</span></td>
            <td><span class="badge bg-success">${p.categoria}</span></td>
            <td class="text-end fw-bold">${qtd} ${p.unidade_medida}</td>
            <td class="text-end text-muted">${min} ${p.unidade_medida}</td>
            <td>${situacaoBadge(p.situacao)}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary"
                        onclick="abrirAjuste(${p.id}, '${p.nome}', ${p.quantidade || 0}, ${p.estoque_minimo || 0}, '${p.unidade_medida}')">
                    ✏️ Ajustar
                </button>
            </td>
        </tr>`;
    }).join('');
}

// ── Modal ajuste estoque ──────────────────────────────────────────────────────
function abrirAjuste(pid, nome, qtd, min, unidade) {
    document.getElementById('est-modal-produto').textContent = '📦 ' + nome;
    document.getElementById('est-modal-unidade').textContent = unidade;
    document.getElementById('est-modal-qtd').value           = parseFloat(qtd).toFixed(3);
    document.getElementById('est-modal-min').value           = parseFloat(min).toFixed(3);
    document.getElementById('est-modal-pid').value           = pid;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEstoque')).show();
}

async function salvarEstoque() {
    const payload = {
        produto_id:    parseInt(document.getElementById('est-modal-pid').value),
        loja_id:       lojaAtual(),
        quantidade:    parseFloat(document.getElementById('est-modal-qtd').value),
        estoque_minimo:parseFloat(document.getElementById('est-modal-min').value),
    };

    const r = await fetch(APP.api + '/estoque', { method:'PUT', headers, body: JSON.stringify(payload) });
    const j = await r.json();
    if (r.ok) {
        bootstrap.Modal.getInstance(document.getElementById('modalEstoque')).hide();
        await carregarEstoque(lojaAtual());
    } else alert('Erro: ' + j.message);
}

// ── Modal quebra ──────────────────────────────────────────────────────────────
async function abrirQuebraModal() {
    // Popula select de produtos
    const r = await fetch(APP.api + '/produtos', { headers });
    const j = await r.json();
    const select = document.getElementById('qb-produto');
    select.innerHTML = (j.data || []).map(p =>
        `<option value="${p.id}">${p.nome} (${p.unidade})</option>`
    ).join('');

    // Preenche motivo ao selecionar do combo
    document.getElementById('qb-motivo-select').addEventListener('change', function() {
        if (this.value) document.getElementById('qb-motivo').value = this.value;
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQuebra')).show();
}

async function salvarQuebra() {
    const motivo = document.getElementById('qb-motivo').value.trim()
                || document.getElementById('qb-motivo-select').value;
    const payload = {
        produto_id: parseInt(document.getElementById('qb-produto').value),
        loja_id:    parseInt(document.getElementById('qb-loja').value),
        quantidade: parseFloat(document.getElementById('qb-quantidade').value),
        motivo,
    };

    if (!payload.quantidade || !motivo) {
        alert('Preencha quantidade e motivo.'); return;
    }
    if (!confirm(`Confirma baixa de ${payload.quantidade} unidades por quebra/avaria?`)) return;

    const r = await fetch(APP.api + '/estoque/quebra', { method:'POST', headers, body: JSON.stringify(payload) });
    const j = await r.json();
    if (r.ok) {
        bootstrap.Modal.getInstance(document.getElementById('modalQuebra')).hide();
        await carregarEstoque(lojaAtual());
    } else alert('Erro: ' + j.message);
}

// ── Init ──────────────────────────────────────────────────────────────────────
carregarEstoque(<?= (int) $loja_padrao ?>);
</script>
