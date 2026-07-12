<?php
/* Fragmento: Gestão de Estoque */
$stmt  = db()->query('SELECT id, nome FROM lojas WHERE ativo = 1 ORDER BY nome');
$lojas = $stmt->fetchAll();
$u_frag = usuario_logado();
// Gerente é responsável por todas as lojas (não há um gerente por loja) — mesma
// visão multi-loja do super_admin, sem loja fixa nem seletor desabilitado.
$loja_padrao = in_array($u_frag['role'], ['gerente', 'super_admin'], true)
    ? ($lojas[0]['id'] ?? 1)
    : ($u_frag['loja_id'] ?? ($lojas[0]['id'] ?? 1));
?>

<div class="frag-wrap px-4 py-3">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="fw-bold text-verde mb-0">📦 Estoque</h4>
        <button class="btn btn-warning btn-sm fw-bold" onclick="estoqueUI.abrirQuebra()">
            ⚠️ Registrar Quebra
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <label class="fw-semibold mb-0">🏪 Loja:</label>
            <select id="est-loja" class="form-select form-select-sm"
                    style="max-width:220px;" onchange="estoqueUI.carregar(this.value)">
                <?php foreach ($lojas as $l): ?>
                <option value="<?= $l['id'] ?>"
                    <?= $l['id'] == $loja_padrao ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="search" id="est-busca" class="form-control form-control-sm"
                   style="max-width:220px;" placeholder="🔍 Filtrar produto…"
                   oninput="estoqueUI.filtrar()">
            <span id="est-badge-critico" class="badge bg-danger ms-auto" style="display:none;"></span>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div id="est-loading" class="text-center py-4">
                <div class="spinner-border text-success"></div>
            </div>
            <div class="table-responsive" id="est-wrapper" style="display:none;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-success">
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th class="text-end">Qtd. Atual</th>
                            <th class="text-end">Mínimo</th>
                            <th>Situação</th>
                            <th class="text-center">Ajustar</th>
                        </tr>
                    </thead>
                    <tbody id="est-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajuste -->
<div class="modal fade" id="m-est-ajuste" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">✏️ Ajustar Estoque</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="est-modal-nome" class="fw-bold"></p>
                <div class="mb-3">
                    <label class="form-label">Quantidade (<span id="est-modal-und"></span>)</label>
                    <input type="number" id="est-modal-qtd" class="form-control" step="0.001" min="0">
                </div>
                <div class="mb-2">
                    <label class="form-label">Estoque mínimo</label>
                    <input type="number" id="est-modal-min" class="form-control" step="0.001" min="0">
                </div>
                <input type="hidden" id="est-modal-pid">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" onclick="estoqueUI.salvarAjuste()">💾 Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Quebra -->
<div class="modal fade" id="m-est-quebra" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">⚠️ Registrar Quebra / Avaria</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Produto <span class="text-danger">*</span></label>
                    <select id="qb-produto" class="form-select"></select>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Quantidade perdida <span class="text-danger">*</span></label>
                        <input type="number" id="qb-qtd" class="form-control"
                               step="0.001" min="0.001">
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
                <div class="mt-2">
                    <label class="form-label fw-semibold">Motivo <span class="text-danger">*</span></label>
                    <select id="qb-motivo-sel" class="form-select mb-1">
                        <option value="">Selecione ou descreva abaixo…</option>
                        <option>Vencimento / perecimento</option>
                        <option>Dano no transporte</option>
                        <option>Dano no armazenamento</option>
                        <option>Furto</option>
                        <option>Erro de pesagem</option>
                    </select>
                    <input type="text" id="qb-motivo" class="form-control"
                           placeholder="Descreva o motivo…">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning fw-bold" onclick="estoqueUI.confirmarQuebra()">
                    ⚠️ Confirmar Quebra
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.text-verde { color: #2e7d32; }
</style>

<script>
window.estoqueUI = (function () {
    const tk  = () => sessionStorage.getItem('desffrut_token') || '';
    const hj  = () => ({ 'Content-Type':'application/json','Authorization':'Bearer '+tk() });
    const lojaAtual = () => parseInt(document.getElementById('est-loja').value);

    const situBadge = s => ({
        ok:          '<span class="badge bg-success">✅ OK</span>',
        baixo:       '<span class="badge bg-warning text-dark">⚠️ Baixo</span>',
        critico:     '<span class="badge bg-danger">🔴 Crítico</span>',
        sem_estoque: '<span class="badge bg-dark">❌ Zerado</span>',
    }[s] || '<span class="badge bg-secondary">—</span>');

    const icons = { frutas:'🍎', verduras:'🥬', legumes:'🥕', outros:'📦' };

    let dados = [];

    async function carregar(lojaId) {
        lojaId = lojaId || lojaAtual();
        document.getElementById('est-loading').style.display  = '';
        document.getElementById('est-wrapper').style.display  = 'none';

        const r = await fetch(`${APP.api}/estoque?loja_id=${lojaId}`, { headers: hj() });
        const j = await r.json();
        dados = j.data || [];

        const criticos = dados.filter(p => p.situacao === 'critico' || p.situacao === 'sem_estoque').length;
        const badge = document.getElementById('est-badge-critico');
        badge.style.display = criticos > 0 ? '' : 'none';
        badge.textContent   = `⚠️ ${criticos} crítico(s)`;

        filtrar();
    }

    function filtrar() {
        const busca = document.getElementById('est-busca').value.toLowerCase();
        const lista = dados.filter(p => !busca || p.nome.toLowerCase().includes(busca));

        document.getElementById('est-loading').style.display = 'none';
        document.getElementById('est-wrapper').style.display = '';

        document.getElementById('est-tbody').innerHTML = lista.map(p => {
            const qtd = p.quantidade != null ? parseFloat(p.quantidade).toFixed(3) : '—';
            const min = p.estoque_minimo ? parseFloat(p.estoque_minimo).toFixed(3) : '—';
            const cls = (p.situacao==='critico'||p.situacao==='sem_estoque') ? 'table-danger' : '';
            return `
            <tr class="${cls}">
                <td><span class="fw-semibold">${icons[p.categoria]||'📦'} ${p.nome}</span></td>
                <td><span class="badge bg-success">${p.categoria}</span></td>
                <td class="text-end fw-bold">${qtd} <small>${p.unidade_medida}</small></td>
                <td class="text-end text-muted">${min} <small>${p.unidade_medida}</small></td>
                <td>${situBadge(p.situacao)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary"
                            onclick="estoqueUI.abrirAjuste(${p.id},'${p.nome}',${p.quantidade||0},${p.estoque_minimo||0},'${p.unidade_medida}')">
                        ✏️ Ajustar
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    function abrirAjuste(pid, nome, qtd, min, und) {
        document.getElementById('est-modal-nome').textContent = '📦 ' + nome;
        document.getElementById('est-modal-und').textContent  = und;
        document.getElementById('est-modal-qtd').value        = parseFloat(qtd).toFixed(3);
        document.getElementById('est-modal-min').value        = parseFloat(min).toFixed(3);
        document.getElementById('est-modal-pid').value        = pid;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('m-est-ajuste')).show();
    }

    async function salvarAjuste() {
        try {
            const r = await fetch(APP.api + '/estoque', {
                method: 'PUT', headers: hj(),
                body: JSON.stringify({
                    produto_id:     parseInt(document.getElementById('est-modal-pid').value),
                    loja_id:        lojaAtual(),
                    quantidade:     parseFloat(document.getElementById('est-modal-qtd').value),
                    estoque_minimo: parseFloat(document.getElementById('est-modal-min').value),
                }),
            });
            const j = await r.json();
            if (r.ok) {
                bootstrap.Modal.getInstance(document.getElementById('m-est-ajuste')).hide();
                await carregar();
            } else {
                alert('Erro ao salvar: ' + (j.message || r.status));
            }
        } catch (err) {
            alert('Erro de conexão ao salvar estoque: ' + err.message);
        }
    }

    async function abrirQuebra() {
        const r = await fetch(APP.api + '/produtos', { headers: hj() });
        const j = await r.json();
        document.getElementById('qb-produto').innerHTML =
            (j.data || []).map(p => `<option value="${p.id}">${p.nome} (${p.unidade})</option>`).join('');
        document.getElementById('qb-motivo-sel').addEventListener('change', function () {
            if (this.value) document.getElementById('qb-motivo').value = this.value;
        });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('m-est-quebra')).show();
    }

    async function confirmarQuebra() {
        const motivo = document.getElementById('qb-motivo').value.trim()
                    || document.getElementById('qb-motivo-sel').value;
        const qtd = parseFloat(document.getElementById('qb-qtd').value);

        if (!qtd || !motivo) { alert('Preencha quantidade e motivo.'); return; }
        if (!confirm(`Confirma baixa de ${qtd} un/kg por quebra?`)) return;

        try {
            const r = await fetch(APP.api + '/estoque/quebra', {
                method: 'POST', headers: hj(),
                body: JSON.stringify({
                    produto_id: parseInt(document.getElementById('qb-produto').value),
                    loja_id:    parseInt(document.getElementById('qb-loja').value),
                    quantidade: qtd, motivo,
                }),
            });
            const j = await r.json();
            if (r.ok) {
                bootstrap.Modal.getInstance(document.getElementById('m-est-quebra')).hide();
                await carregar();
            } else {
                alert('Erro ao registrar quebra: ' + (j.message || r.status));
            }
        } catch (err) {
            alert('Erro de conexão ao registrar quebra: ' + err.message);
        }
    }

    carregar(<?= (int) $loja_padrao ?>);

    return { carregar, filtrar, abrirAjuste, salvarAjuste, abrirQuebra, confirmarQuebra };
})();
</script>
