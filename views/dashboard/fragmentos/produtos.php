<?php /* Fragmento: Gestão de Produtos (sem header/footer) */ ?>

<div class="frag-wrap px-4 py-3">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="fw-bold text-verde mb-0">🛒 Produtos</h4>
        <button class="btn btn-success btn-sm fw-bold" onclick="produtosUI.abrirModal()">
            + Novo Produto
        </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="search" id="flt-busca" class="form-control form-control-sm"
                           placeholder="🔍 Nome ou EAN…">
                </div>
                <div class="col-auto">
                    <select id="flt-cat" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="frutas">🍎 Frutas</option>
                        <option value="verduras">🥬 Verduras</option>
                        <option value="legumes">🥕 Legumes</option>
                        <option value="outros">📦 Outros</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select id="flt-status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="1">✅ Ativos</option>
                        <option value="0">❌ Inativos</option>
                    </select>
                </div>
                <div class="col-auto ms-auto">
                    <span id="prod-total" class="badge bg-secondary">— produtos</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-body p-0">
            <div id="prod-loading" class="text-center py-4">
                <div class="spinner-border text-success"></div>
            </div>
            <div class="table-responsive" id="prod-wrapper" style="display:none;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-success">
                        <tr>
                            <th style="width:52px">Foto</th>
                            <th>Nome</th>
                            <th>Cat.</th>
                            <th>Un.</th>
                            <th class="text-end">Preço</th>
                            <th class="text-end">Estoque</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="prod-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Produto -->
<div class="modal fade" id="mproduto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="mp-titulo">Novo Produto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="mp-abas">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mp-dados">
                            📝 Dados
                        </button>
                    </li>
                    <li class="nav-item" id="mp-aba-precos-li" style="display:none;">
                        <button class="nav-link" id="mp-btn-precos" data-bs-toggle="tab"
                                data-bs-target="#mp-precos">
                            💰 Preços & Promoções
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <!-- Dados -->
                    <div class="tab-pane fade show active" id="mp-dados">
                        <div class="row g-3">
                            <div class="col-md-3 text-center">
                                <div id="mp-foto-prev" class="produto-foto-placeholder rounded mb-2"
                                     style="height:110px;cursor:pointer;display:flex;align-items:center;justify-content:center;background:#e8f5e9;font-size:2rem;"
                                     onclick="document.getElementById('mp-foto-input').click()">📷</div>
                                <input type="file" id="mp-foto-input" accept="image/*" style="display:none;">
                                <input type="hidden" id="mp-foto-path">
                                <button class="btn btn-outline-secondary btn-sm"
                                        onclick="document.getElementById('mp-foto-input').click()">
                                    Escolher foto
                                </button>
                                <div class="text-muted" style="font-size:.7rem;margin-top:4px;">JPG/PNG/WebP, até 6 MB</div>
                            </div>
                            <div class="col-md-9">
                                <div class="mb-2">
                                    <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                                    <input type="text" id="mp-nome" class="form-control">
                                </div>
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Categoria</label>
                                        <select id="mp-categoria" class="form-select">
                                            <option value="frutas">🍎 Frutas</option>
                                            <option value="verduras">🥬 Verduras</option>
                                            <option value="legumes">🥕 Legumes</option>
                                            <option value="outros">📦 Outros</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Unidade</label>
                                        <div class="d-flex gap-3 mt-1">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                       name="mp-und" id="mp-kg" value="kg" checked>
                                                <label class="form-check-label" for="mp-kg">Kg</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                       name="mp-und" id="mp-un" value="un">
                                                <label class="form-check-label" for="mp-un">Unidade</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">EAN</label>
                                <input type="text" id="mp-ean" class="form-control" placeholder="Opcional">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">Preço de Custo (R$)</label>
                                <input type="number" id="mp-custo" class="form-control" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Descrição</label>
                                <textarea id="mp-descricao" class="form-control" rows="2"
                                          placeholder="Variedade, origem…"></textarea>
                            </div>
                        </div>
                    </div>
                    <!-- Preços -->
                    <div class="tab-pane fade" id="mp-precos">
                        <div id="mp-precos-loading" class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-success"></div> Carregando lojas…
                        </div>
                        <div id="mp-precos-lista" style="display:none;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" onclick="produtosUI.salvar()">💾 Salvar</button>
            </div>
        </div>
    </div>
</div>

<style>
.frag-wrap { min-height: 400px; }
.text-verde { color: #2e7d32; }
.thumb-p { width:46px;height:46px;object-fit:cover;border-radius:6px; }
.thumb-placeholder { width:46px;height:46px;border-radius:6px;background:#e8f5e9;
    display:flex;align-items:center;justify-content:center;font-size:1.3rem; }
.preco-bloco { border:1px solid #dee2e6;border-radius:8px;padding:14px;margin-bottom:12px; }
</style>

<script>
// Namespace para evitar conflito com outras abas
window.produtosUI = (function () {
    const tk = () => sessionStorage.getItem('desffrut_token') || '';
    const hj = () => ({ 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + tk() });

    const fmtR  = v => v != null ? 'R$ ' + parseFloat(v).toFixed(2).replace('.',',') : '—';
    const icons = { frutas:'🍎', verduras:'🥬', legumes:'🥕', outros:'📦' };

    let todos = [];
    let editId = null;
    let precosCarregados = false;

    // ── Carregar produtos ─────────────────────────────────────────
    async function carregar() {
        document.getElementById('prod-loading').style.display = '';
        document.getElementById('prod-wrapper').style.display = 'none';

        const r = await fetch(APP.api + '/produtos', { headers: hj() });
        const j = await r.json();
        todos = j.data || [];
        renderizar();
    }

    function renderizar() {
        const busca  = document.getElementById('flt-busca').value.toLowerCase();
        const cat    = document.getElementById('flt-cat').value;
        const status = document.getElementById('flt-status').value;

        const lista = todos.filter(p => {
            if (cat && p.categoria !== cat) return false;
            if (status !== '' && String(p.ativo ? 1 : 0) !== status) return false;
            if (busca && !p.nome.toLowerCase().includes(busca) &&
                !(p.ean||'').includes(busca)) return false;
            return true;
        });

        document.getElementById('prod-total').textContent = lista.length + ' produto(s)';
        document.getElementById('prod-loading').style.display = 'none';
        document.getElementById('prod-wrapper').style.display = '';

        const fotoEl = p => p.foto
            ? `<img src="${p.foto}" class="thumb-p" alt="">`
            : `<div class="thumb-placeholder">${icons[p.categoria]||'📦'}</div>`;

        document.getElementById('prod-tbody').innerHTML = lista.map(p => `
            <tr>
                <td>${fotoEl(p)}</td>
                <td>
                    <div class="fw-semibold">${p.nome}</div>
                    ${p.ean ? `<small class="text-muted">EAN: ${p.ean}</small>` : ''}
                </td>
                <td><span class="badge bg-success">${icons[p.categoria]||''} ${p.categoria}</span></td>
                <td><span class="badge bg-light text-dark border">${p.unidade}</span></td>
                <td class="text-end fw-bold" style="color:#2e7d32">${fmtR(p.preco_venda)}</td>
                <td class="text-end">${parseFloat(p.estoque_total||0).toFixed(2)} ${p.unidade}</td>
                <td>${p.ativo
                    ? '<span class="badge bg-success">Ativo</span>'
                    : '<span class="badge bg-secondary">Inativo</span>'}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary"
                                onclick="produtosUI.abrirModal(${p.id})" title="Editar">✏️</button>
                        <button class="btn btn-outline-warning"
                                onclick="produtosUI.abrirPrecos(${p.id})" title="Preços">💰</button>
                        <button class="btn btn-outline-${p.ativo?'danger':'success'}"
                                onclick="produtosUI.toggleAtivo(${p.id},${p.ativo})"
                                title="${p.ativo?'Inativar':'Ativar'}">
                            ${p.ativo?'🚫':'✅'}
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    }

    // ── Filtros em tempo real ────────────────────────────────────
    ['flt-busca','flt-cat','flt-status'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', renderizar);
    });

    // ── Abrir modal ──────────────────────────────────────────────
    function abrirModal(id = null) {
        editId = id;
        precosCarregados = false;
        document.getElementById('mp-titulo').textContent = id ? 'Editar Produto' : 'Novo Produto';
        document.getElementById('mp-aba-precos-li').style.display = id ? '' : 'none';

        // Reset
        ['mp-nome','mp-ean','mp-descricao','mp-foto-path'].forEach(i => {
            const el = document.getElementById(i);
            if (el) el.value = '';
        });
        document.getElementById('mp-custo').value = '0';
        document.getElementById('mp-categoria').value = 'frutas';
        document.getElementById('mp-kg').checked = true;
        const prev = document.getElementById('mp-foto-prev');
        prev.innerHTML = '📷';
        prev.style.backgroundImage = '';

        bootstrap.Tab.getOrCreateInstance(
            document.querySelector('#mp-abas .nav-link')
        ).show();

        if (id) {
            fetch(APP.api + '/produtos/' + id, { headers: hj() })
                .then(r => r.json()).then(j => {
                    const p = j.data;
                    document.getElementById('mp-nome').value      = p.nome;
                    document.getElementById('mp-ean').value       = p.ean || '';
                    document.getElementById('mp-custo').value     = p.preco_custo || 0;
                    document.getElementById('mp-descricao').value = p.descricao || '';
                    document.getElementById('mp-categoria').value = p.categoria;
                    document.querySelector(`input[name="mp-und"][value="${p.unidade_medida}"]`).checked = true;
                    if (p.foto) {
                        document.getElementById('mp-foto-path').value = p.foto.replace(APP.base+'/','');
                        prev.innerHTML = '';
                        prev.style.cssText = `background:url(${p.foto}) center/cover;border-radius:8px;height:110px;cursor:pointer;`;
                    }
                });
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('mproduto')).show();
    }

    // ── Upload foto ───────────────────────────────────────────────
    document.getElementById('mp-foto-input')?.addEventListener('change', async function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            const prev = document.getElementById('mp-foto-prev');
            prev.innerHTML = '';
            prev.style.cssText = `background:url(${e.target.result}) center/cover;border-radius:8px;height:110px;cursor:pointer;`;
        };
        reader.readAsDataURL(file);

        const fd = new FormData();
        fd.append('foto', file);
        const r = await fetch(APP.api + '/uploads/foto', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + tk() },
            body: fd,
        });
        const j = await r.json();
        if (r.ok) document.getElementById('mp-foto-path').value = j.data.path;
        else alert('Erro no upload: ' + j.message);
    });

    // ── Salvar produto ────────────────────────────────────────────
    async function salvar() {
        const nome = document.getElementById('mp-nome').value.trim();
        if (!nome) { alert('Nome é obrigatório.'); return; }

        const payload = {
            nome,
            categoria:      document.getElementById('mp-categoria').value,
            unidade_medida: document.querySelector('input[name="mp-und"]:checked').value,
            ean:            document.getElementById('mp-ean').value.trim(),
            preco_custo:    parseFloat(document.getElementById('mp-custo').value) || 0,
            descricao:      document.getElementById('mp-descricao').value.trim(),
            foto:           document.getElementById('mp-foto-path').value || null,
        };

        const url    = APP.api + '/produtos' + (editId ? '/' + editId : '');
        const method = editId ? 'PUT' : 'POST';
        const r = await fetch(url, { method, headers: hj(), body: JSON.stringify(payload) });
        const j = await r.json();

        if (r.ok) {
            bootstrap.Modal.getInstance(document.getElementById('mproduto')).hide();
            await carregar();
        } else alert('Erro: ' + j.message);
    }

    // ── Toggle ativo ──────────────────────────────────────────────
    async function toggleAtivo(id, ativo) {
        if (!confirm(`${ativo ? 'Inativar' : 'Ativar'} este produto?`)) return;
        const r = await fetch(APP.api + '/produtos/' + id, { method: 'PATCH', headers: hj() });
        if (r.ok) await carregar();
        else alert('Erro ao alterar status.');
    }

    // ── Abrir modal direto em Preços ─────────────────────────────
    function abrirPrecos(id) {
        abrirModal(id);
        setTimeout(() => {
            // Apenas muda de aba; o evento 'shown.bs.tab' dispara carregarPrecos.
            // Não chamar carregarPrecos diretamente aqui para evitar double-fetch.
            bootstrap.Tab.getOrCreateInstance(
                document.getElementById('mp-btn-precos')
            ).show();
        }, 400);
    }

    // ── Carregar preços ───────────────────────────────────────────
    document.getElementById('mp-btn-precos')?.addEventListener('shown.bs.tab', () => {
        if (!precosCarregados && editId) carregarPrecos(editId);
    });

    async function carregarPrecos(prodId) {
        document.getElementById('mp-precos-loading').style.display = '';
        document.getElementById('mp-precos-lista').style.display   = 'none';
        precosCarregados = true;

        const [rP, rL] = await Promise.all([
            fetch(APP.api + '/produtos/' + prodId, { headers: hj() }),
            fetch(APP.api + '/lojas'),
        ]);
        const prod  = (await rP.json()).data;
        // Deduplica lojas por id para evitar blocos repetidos caso o banco tenha entradas duplicadas
        const lojasRaw = (await rL.json()).data || [];
        const vistas = new Set();
        const lojas  = lojasRaw.filter(l => { const k = String(l.id); if (vistas.has(k)) return false; vistas.add(k); return true; });

        const mapa = {};
        (prod.precos || []).forEach(p => { mapa[p.loja_id] = p; });

        document.getElementById('mp-precos-lista').innerHTML = lojas.map(l => {
            const pr  = mapa[l.id] || {};
            const ini = pr.promo_inicio ? pr.promo_inicio.replace(' ','T').slice(0,16) : '';
            const fim = pr.promo_fim    ? pr.promo_fim.replace(' ','T').slice(0,16)    : '';
            return `
            <div class="preco-bloco" id="pb-${l.id}">
                <h6 class="fw-bold mb-3" style="color:#2e7d32">🏪 ${l.nome}</h6>
                <div class="row g-2">
                    <div class="col-sm-3">
                        <label class="form-label small">Venda (R$)</label>
                        <input type="number" class="form-control form-control-sm"
                               step="0.01" id="pv-${l.id}" value="${pr.preco_venda||''}">
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small">💥 Promo (R$)</label>
                        <input type="number" class="form-control form-control-sm"
                               step="0.01" id="pp-${l.id}" value="${pr.promo_preco||''}">
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small">Início Promo</label>
                        <input type="datetime-local" class="form-control form-control-sm"
                               id="pi-${l.id}" value="${ini}">
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small">Fim Promo</label>
                        <input type="datetime-local" class="form-control form-control-sm"
                               id="pf-${l.id}" value="${fim}">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-sm btn-success"
                                onclick="produtosUI.salvarPreco(${prodId},${l.id})">
                            💾 Salvar preço desta loja
                        </button>
                    </div>
                </div>
            </div>`;
        }).join('');

        document.getElementById('mp-precos-loading').style.display = 'none';
        document.getElementById('mp-precos-lista').style.display   = '';
    }

    async function salvarPreco(prodId, lojaId) {
        const payload = {
            loja_id:      lojaId,
            preco_venda:  parseFloat(document.getElementById(`pv-${lojaId}`).value) || 0,
            promo_preco:  parseFloat(document.getElementById(`pp-${lojaId}`).value) || null,
            promo_inicio: document.getElementById(`pi-${lojaId}`).value.replace('T',' ') || null,
            promo_fim:    document.getElementById(`pf-${lojaId}`).value.replace('T',' ') || null,
        };
        const r = await fetch(`${APP.api}/produtos/${prodId}/preco`,
            { method: 'PUT', headers: hj(), body: JSON.stringify(payload) });
        const j = await r.json();
        const bloco = document.getElementById(`pb-${lojaId}`);
        bloco.style.transition = 'background .3s';
        bloco.style.background = r.ok ? '#e8f5e9' : '#ffebee';
        setTimeout(() => { bloco.style.background = ''; }, 1500);
        if (!r.ok) alert('Erro: ' + j.message);
    }

    // ── Init ─────────────────────────────────────────────────────
    carregar();

    return { abrirModal, salvar, toggleAtivo, abrirPrecos, salvarPreco };
})();
</script>
