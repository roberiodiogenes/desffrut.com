<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();

$roles_permitidos = ['gerente', 'super_admin'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$titulo_pagina  = 'Gestão de Produtos';
$mostrar_sacola = false;
require_once __DIR__ . '/../../app/views/layout/header.php';
$u = usuario_logado();
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-verde mb-0">📦 Gestão de Produtos</h2>
            <small class="text-muted">Cadastro, preços e promoções</small>
        </div>
        <button class="btn btn-success fw-bold" onclick="abrirModal()">
            + Novo Produto
        </button>
    </div>

    <!-- ─── Filtros ─────────────────────────────────────────────────────────── -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="search" id="filtro-busca" class="form-control form-control-sm"
                           placeholder="🔍 Buscar por nome ou EAN…">
                </div>
                <div class="col-auto">
                    <select id="filtro-categoria" class="form-select form-select-sm">
                        <option value="">Todas as categorias</option>
                        <option value="frutas">🍎 Frutas</option>
                        <option value="verduras">🥬 Verduras</option>
                        <option value="legumes">🥕 Legumes</option>
                        <option value="outros">📦 Outros</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select id="filtro-status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="1">✅ Ativos</option>
                        <option value="0">❌ Inativos</option>
                    </select>
                </div>
                <div class="col-auto ms-auto">
                    <span id="total-produtos" class="badge bg-secondary">— produtos</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Tabela ──────────────────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-body p-0">
            <div id="estado-carregando" class="text-center py-5">
                <div class="spinner-border text-success"></div>
                <p class="mt-2 text-muted">Carregando produtos…</p>
            </div>
            <div class="table-responsive" id="tabela-wrapper" style="display:none;">
                <table class="table table-hover align-middle mb-0" id="tabela-produtos">
                    <thead class="table-success">
                        <tr>
                            <th style="width:56px">Foto</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Un.</th>
                            <th class="text-end">Preço Venda</th>
                            <th class="text-end">Estoque</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — Criar / Editar Produto
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalProduto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modal-titulo">Novo Produto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Abas -->
                <ul class="nav nav-tabs mb-3" id="abas-produto">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-dados">
                            📝 Dados Básicos
                        </button>
                    </li>
                    <li class="nav-item" id="aba-precos-li" style="display:none;">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-precos"
                                id="btn-aba-precos">
                            💰 Preços & Promoções
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab 1: Dados Básicos -->
                    <div class="tab-pane fade show active" id="tab-dados">
                        <div class="row g-3">
                            <div class="col-md-3 text-center">
                                <div id="foto-preview" class="produto-foto-placeholder rounded mb-2"
                                     style="height:120px; cursor:pointer;" onclick="document.getElementById('input-foto').click()">
                                    📷
                                </div>
                                <input type="file" id="input-foto" accept="image/*" style="display:none;">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="document.getElementById('input-foto').click()">
                                    Escolher foto
                                </button>
                                <small class="d-block text-muted mt-1">JPG/PNG/WebP, até 6 MB</small>
                                <input type="hidden" id="foto-path" value="">
                            </div>

                            <div class="col-md-9">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                                    <input type="text" id="p-nome" class="form-control" required>
                                </div>
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Categoria <span class="text-danger">*</span></label>
                                        <select id="p-categoria" class="form-select">
                                            <option value="frutas">🍎 Frutas</option>
                                            <option value="verduras">🥬 Verduras</option>
                                            <option value="legumes">🥕 Legumes</option>
                                            <option value="outros">📦 Outros</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Unidade de medida</label>
                                        <div class="d-flex gap-3 mt-1">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="p-unidade" id="un-kg" value="kg" checked>
                                                <label class="form-check-label" for="un-kg">Kg</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="p-unidade" id="un-un" value="un">
                                                <label class="form-check-label" for="un-un">Unidade</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">EAN / Código de barras</label>
                                <input type="text" id="p-ean" class="form-control" placeholder="Ex.: 7891000123456">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">Preço de custo (R$)</label>
                                <input type="number" id="p-custo" class="form-control" step="0.01" min="0" value="0">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Descrição / Observações</label>
                                <textarea id="p-descricao" class="form-control" rows="2"
                                          placeholder="Variedade, origem, características…"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Preços & Promoções -->
                    <div class="tab-pane fade" id="tab-precos">
                        <div id="precos-carregando" class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-success"></div>
                            Carregando lojas…
                        </div>
                        <div id="precos-tabela" style="display:none;">
                            <p class="text-muted small mb-2">
                                Configure o preço de venda e promoções para cada filial.
                                Promoções são aplicadas automaticamente no período informado.
                            </p>
                            <div id="precos-lista"></div>
                        </div>
                    </div>
                </div><!-- /tab-content -->

            </div><!-- /modal-body -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-salvar-produto" onclick="salvarProduto()">
                    💾 Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>

<style>
.produto-row { cursor: default; }
.thumb-produto { width:48px; height:48px; object-fit:cover; border-radius:6px; }
.thumb-placeholder { width:48px; height:48px; border-radius:6px; background:#e8f5e9;
    display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
.preco-bloco { border:1px solid #dee2e6; border-radius:8px; padding:14px; margin-bottom:12px; }
.preco-bloco h6 { color: #2e7d32; margin-bottom:10px; }
</style>

<script>
const token   = sessionStorage.getItem('desffrut_token') || '';
const headers = { 'Content-Type':'application/json', 'Authorization':'Bearer '+token };

let produtoEditandoId = null;
let todosProdutos     = [];

// ── Utilidades ────────────────────────────────────────────────────────────────
const fmtR = v => 'R$ ' + (v ? parseFloat(v).toFixed(2).replace('.',',') : '—');
const icon  = cat => ({ frutas:'🍎', verduras:'🥬', legumes:'🥕', outros:'📦' }[cat] || '📦');

// ── Carregar produtos ─────────────────────────────────────────────────────────
async function carregarProdutos() {
    document.getElementById('estado-carregando').style.display = '';
    document.getElementById('tabela-wrapper').style.display    = 'none';

    const r  = await fetch(APP.api + '/produtos', { headers });
    const j  = await r.json();
    todosProdutos = j.data || [];
    renderTabela();
}

function renderTabela() {
    const busca = document.getElementById('filtro-busca').value.toLowerCase();
    const cat   = document.getElementById('filtro-categoria').value;
    const ativo = document.getElementById('filtro-status').value;

    let lista = todosProdutos.filter(p => {
        if (cat   && p.categoria !== cat)                         return false;
        if (ativo !== '' && String(p.ativo ? 1 : 0) !== ativo)   return false;
        if (busca && !p.nome.toLowerCase().includes(busca) &&
            !(p.ean||'').includes(busca))                         return false;
        return true;
    });

    document.getElementById('total-produtos').textContent = lista.length + ' produto(s)';
    document.getElementById('estado-carregando').style.display = 'none';
    document.getElementById('tabela-wrapper').style.display    = '';

    const foto = p => p.foto
        ? `<img src="${p.foto}" class="thumb-produto" alt="${p.nome}">`
        : `<div class="thumb-placeholder">${icon(p.categoria)}</div>`;

    document.getElementById('tabela-body').innerHTML = lista.map(p => `
        <tr class="produto-row" data-id="${p.id}">
            <td>${foto(p)}</td>
            <td>
                <div class="fw-semibold">${p.nome}</div>
                ${p.ean ? `<small class="text-muted">EAN: ${p.ean}</small>` : ''}
            </td>
            <td><span class="badge bg-success">${icon(p.categoria)} ${p.categoria}</span></td>
            <td><span class="badge bg-light text-dark border">${p.unidade}</span></td>
            <td class="text-end fw-bold text-verde">${fmtR(p.preco_venda)}</td>
            <td class="text-end">${parseFloat(p.estoque_total).toFixed(2)} ${p.unidade}</td>
            <td>
                ${p.ativo
                    ? '<span class="badge bg-success">Ativo</span>'
                    : '<span class="badge bg-secondary">Inativo</span>'}
            </td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary"  onclick="abrirModal(${p.id})"   title="Editar">✏️</button>
                    <button class="btn btn-outline-warning"  onclick="abrirPrecos(${p.id})"  title="Preços">💰</button>
                    <button class="btn btn-outline-${p.ativo ? 'danger' : 'success'}"
                            onclick="toggleAtivo(${p.id}, ${p.ativo})"
                            title="${p.ativo ? 'Inativar' : 'Ativar'}">
                        ${p.ativo ? '🚫' : '✅'}
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// ── Filtros em tempo real ─────────────────────────────────────────────────────
['filtro-busca','filtro-categoria','filtro-status'].forEach(id =>
    document.getElementById(id).addEventListener('input', renderTabela)
);

// ── Abrir modal criar/editar ──────────────────────────────────────────────────
function abrirModal(id = null) {
    produtoEditandoId = id;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProduto'));

    // Reseta form
    ['p-nome','p-ean','p-custo','p-descricao','foto-path'].forEach(i => document.getElementById(i).value = '');
    document.getElementById('foto-preview').innerHTML   = '📷';
    document.getElementById('foto-preview').style.backgroundImage = '';
    document.getElementById('p-categoria').value        = 'frutas';
    document.getElementById('un-kg').checked            = true;
    document.getElementById('aba-precos-li').style.display = 'none';

    // Muda para aba 1
    bootstrap.Tab.getOrCreateInstance(document.querySelector('#abas-produto .nav-link')).show();

    if (id) {
        document.getElementById('modal-titulo').textContent = 'Editar Produto';
        document.getElementById('aba-precos-li').style.display = '';
        fetch(APP.api + '/produtos/' + id, { headers })
            .then(r => r.json())
            .then(j => {
                const p = j.data;
                document.getElementById('p-nome').value      = p.nome;
                document.getElementById('p-ean').value       = p.ean || '';
                document.getElementById('p-custo').value     = p.preco_custo || 0;
                document.getElementById('p-descricao').value = p.descricao || '';
                document.getElementById('p-categoria').value = p.categoria;
                document.querySelector(`input[name="p-unidade"][value="${p.unidade_medida}"]`).checked = true;
                if (p.foto) {
                    document.getElementById('foto-path').value = p.foto.replace(APP.base+'/', '');
                    document.getElementById('foto-preview').innerHTML = '';
                    document.getElementById('foto-preview').style.cssText =
                        `background:url(${p.foto}) center/cover; border-radius:8px; width:100%; height:120px; cursor:pointer;`;
                }
            });
    } else {
        document.getElementById('modal-titulo').textContent = 'Novo Produto';
    }
    modal.show();
}

// ── Upload de foto ────────────────────────────────────────────────────────────
document.getElementById('input-foto').addEventListener('change', async function () {
    const file = this.files[0];
    if (!file) return;

    // Preview local
    const reader = new FileReader();
    reader.onload = e => {
        const prev = document.getElementById('foto-preview');
        prev.innerHTML = '';
        prev.style.cssText = `background:url(${e.target.result}) center/cover; border-radius:8px; width:100%; height:120px; cursor:pointer;`;
    };
    reader.readAsDataURL(file);

    // Upload para API
    const fd = new FormData();
    fd.append('foto', file);
    try {
        const r = await fetch(APP.api + '/uploads/foto', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token },
            body: fd,
        });
        const j = await r.json();
        if (r.ok) {
            document.getElementById('foto-path').value = j.data.path;
        } else {
            alert('Erro no upload: ' + j.message);
        }
    } catch { alert('Erro de conexão no upload da foto.'); }
});

// ── Salvar produto ────────────────────────────────────────────────────────────
async function salvarProduto() {
    const nome = document.getElementById('p-nome').value.trim();
    if (!nome) { alert('Nome é obrigatório.'); return; }

    const payload = {
        nome:          nome,
        categoria:     document.getElementById('p-categoria').value,
        unidade_medida:document.querySelector('input[name="p-unidade"]:checked').value,
        ean:           document.getElementById('p-ean').value.trim(),
        preco_custo:   parseFloat(document.getElementById('p-custo').value) || 0,
        descricao:     document.getElementById('p-descricao').value.trim(),
        foto:          document.getElementById('foto-path').value || null,
    };

    const isEdicao = !!produtoEditandoId;
    const url      = APP.api + '/produtos' + (isEdicao ? '/' + produtoEditandoId : '');
    const method   = isEdicao ? 'PUT' : 'POST';

    try {
        const r = await fetch(url, { method, headers, body: JSON.stringify(payload) });
        const j = await r.json();
        if (r.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalProduto')).hide();
            await carregarProdutos();
        } else {
            alert('Erro: ' + j.message);
        }
    } catch { alert('Erro de conexão.'); }
}

// ── Toggle ativo ──────────────────────────────────────────────────────────────
async function toggleAtivo(id, ativo) {
    const acao = ativo ? 'inativar' : 'ativar';
    if (!confirm(`Confirma ${acao} este produto?`)) return;

    const r = await fetch(APP.api + '/produtos/' + id, { method: 'PATCH', headers });
    if (r.ok) await carregarProdutos();
    else alert('Erro ao alterar status.');
}

// ── Abrir aba de preços (atalho) ──────────────────────────────────────────────
function abrirPrecos(id) {
    abrirModal(id);
    // Pequeno delay para o modal carregar antes de trocar de aba
    setTimeout(() => {
        bootstrap.Tab.getOrCreateInstance(document.getElementById('btn-aba-precos')).show();
        carregarPrecos(id);
    }, 400);
}

// ── Carregar preços ───────────────────────────────────────────────────────────
let precosCarregados = false;
document.getElementById('btn-aba-precos')?.addEventListener('shown.bs.tab', () => {
    if (!precosCarregados && produtoEditandoId) carregarPrecos(produtoEditandoId);
});

async function carregarPrecos(produtoId) {
    document.getElementById('precos-carregando').style.display = '';
    document.getElementById('precos-tabela').style.display     = 'none';
    precosCarregados = true;

    const [rProduto, rLojas] = await Promise.all([
        fetch(APP.api + '/produtos/' + produtoId, { headers }),
        fetch(APP.api + '/lojas'),
    ]);
    const produto = (await rProduto.json()).data;
    const lojas   = (await rLojas.json()).data || [];

    // Monta mapa de preços existentes
    const mapaPrecos = {};
    (produto.precos || []).forEach(p => { mapaPrecos[p.loja_id] = p; });

    document.getElementById('precos-lista').innerHTML = lojas.map(l => {
        const pr = mapaPrecos[l.id] || {};
        const ini = pr.promo_inicio ? pr.promo_inicio.replace(' ', 'T').slice(0,16) : '';
        const fim = pr.promo_fim    ? pr.promo_fim.replace(' ', 'T').slice(0,16)    : '';
        return `
        <div class="preco-bloco" id="bloco-loja-${l.id}">
            <h6>🏪 ${l.nome}</h6>
            <div class="row g-2">
                <div class="col-sm-3">
                    <label class="form-label small">Preço de Venda (R$)</label>
                    <input type="number" class="form-control form-control-sm" step="0.01"
                           id="pv-${l.id}" value="${pr.preco_venda || ''}">
                </div>
                <div class="col-sm-3">
                    <label class="form-label small">💥 Preço Promo (R$)</label>
                    <input type="number" class="form-control form-control-sm" step="0.01"
                           id="pp-${l.id}" value="${pr.promo_preco || ''}">
                </div>
                <div class="col-sm-3">
                    <label class="form-label small">Início da Promoção</label>
                    <input type="datetime-local" class="form-control form-control-sm"
                           id="pi-${l.id}" value="${ini}">
                </div>
                <div class="col-sm-3">
                    <label class="form-label small">Fim da Promoção</label>
                    <input type="datetime-local" class="form-control form-control-sm"
                           id="pf-${l.id}" value="${fim}">
                </div>
                <div class="col-12">
                    <button class="btn btn-sm btn-success"
                            onclick="salvarPreco(${produtoId}, ${l.id})">
                        💾 Salvar preço desta loja
                    </button>
                </div>
            </div>
        </div>`;
    }).join('');

    document.getElementById('precos-carregando').style.display = 'none';
    document.getElementById('precos-tabela').style.display     = '';
}

async function salvarPreco(produtoId, lojaId) {
    const payload = {
        loja_id:      lojaId,
        preco_venda:  parseFloat(document.getElementById(`pv-${lojaId}`).value)  || 0,
        promo_preco:  parseFloat(document.getElementById(`pp-${lojaId}`).value)  || null,
        promo_inicio: document.getElementById(`pi-${lojaId}`).value.replace('T',' ') || null,
        promo_fim:    document.getElementById(`pf-${lojaId}`).value.replace('T',' ') || null,
    };

    const r = await fetch(`${APP.api}/produtos/${produtoId}/preco`, {
        method: 'PUT', headers, body: JSON.stringify(payload),
    });
    const j = await r.json();

    const bloco = document.getElementById(`bloco-loja-${lojaId}`);
    bloco.style.transition = 'background .3s';
    bloco.style.background = r.ok ? '#e8f5e9' : '#ffebee';
    setTimeout(() => { bloco.style.background = ''; }, 1500);

    if (!r.ok) alert('Erro: ' + j.message);
}

// ── Init ──────────────────────────────────────────────────────────────────────
carregarProdutos();
</script>
