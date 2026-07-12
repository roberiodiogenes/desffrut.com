<?php
/* Fragmento: Histórico de Quebras & Avarias */
$stmt_lojas = db()->query('SELECT id, nome FROM lojas WHERE ativo = 1 ORDER BY nome');
$lojas      = $stmt_lojas->fetchAll();
$u_frag     = usuario_logado();
// Gerente é responsável por todas as lojas (não há um gerente por loja) — mesma
// visão multi-loja do super_admin, sem loja fixa nem seletor desabilitado.
$loja_padrao = in_array($u_frag['role'], ['gerente', 'super_admin'], true)
    ? ($lojas[0]['id'] ?? 1)
    : ($u_frag['loja_id'] ?? ($lojas[0]['id'] ?? 1));
?>

<div class="frag-wrap px-4 py-3">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="fw-bold text-verde mb-0">⚠️ Quebras & Avarias</h4>
        <button class="btn btn-warning btn-sm fw-bold" onclick="quebrasUI.abrirRegistro()">
            + Registrar Quebra
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <select id="qh-loja" class="form-select form-select-sm" style="max-width:220px;"
                    onchange="quebrasUI.carregar()">
                <option value="">Todas as lojas</option>
                <?php foreach ($lojas as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $l['id'] == $loja_padrao ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date" id="qh-data-ini" class="form-control form-control-sm"
                   style="max-width:150px;" onchange="quebrasUI.carregar()">
            <input type="date" id="qh-data-fim" class="form-control form-control-sm"
                   style="max-width:150px;" onchange="quebrasUI.carregar()">
            <span id="qh-total" class="badge bg-secondary ms-auto">— registros</span>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div id="qh-loading" class="text-center py-4">
                <div class="spinner-border text-success"></div>
            </div>
            <div class="table-responsive" id="qh-wrapper" style="display:none;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-warning">
                        <tr>
                            <th>Data</th>
                            <th>Produto</th>
                            <th>Loja</th>
                            <th class="text-end">Quantidade</th>
                            <th>Motivo</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody id="qh-tbody"></tbody>
                </table>
            </div>
            <div id="qh-vazio" class="text-center py-5 text-muted" style="display:none;">
                <div style="font-size:2.5rem;">✅</div>
                <p class="mt-2">Nenhuma quebra registrada no período.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registrar Quebra -->
<div class="modal fade" id="m-qh-registro" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">⚠️ Registrar Quebra / Avaria</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Produto <span class="text-danger">*</span></label>
                    <select id="rq-produto" class="form-select"></select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" id="rq-qtd" class="form-control" step="0.001" min="0.001">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Loja</label>
                        <select id="rq-loja" class="form-select">
                            <?php foreach ($lojas as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $l['id'] == $loja_padrao ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Motivo <span class="text-danger">*</span></label>
                    <select id="rq-motivo-sel" class="form-select mb-1">
                        <option value="">Selecione ou descreva abaixo…</option>
                        <option>Vencimento / perecimento</option>
                        <option>Dano no transporte</option>
                        <option>Dano no armazenamento</option>
                        <option>Furto</option>
                        <option>Erro de pesagem</option>
                    </select>
                    <input type="text" id="rq-motivo" class="form-control" placeholder="Descreva o motivo…">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning fw-bold" onclick="quebrasUI.confirmar()">
                    ⚠️ Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.text-verde { color: #2e7d32; }
</style>

<script>
window.quebrasUI = (function () {
    const tk = () => sessionStorage.getItem('desffrut_token') || '';
    const hj = () => ({ 'Content-Type':'application/json','Authorization':'Bearer '+tk() });

    // Datas padrão: últimos 30 dias
    const hoje     = new Date().toISOString().slice(0,10);
    const ha30dias = new Date(Date.now() - 30*86400000).toISOString().slice(0,10);
    document.getElementById('qh-data-ini').value = ha30dias;
    document.getElementById('qh-data-fim').value = hoje;

    async function carregar() {
        document.getElementById('qh-loading').style.display = '';
        document.getElementById('qh-wrapper').style.display = 'none';
        document.getElementById('qh-vazio').style.display   = 'none';

        const lojaId  = document.getElementById('qh-loja').value;
        const dataIni = document.getElementById('qh-data-ini').value;
        const dataFim = document.getElementById('qh-data-fim').value;

        const params = new URLSearchParams();
        if (lojaId)  params.set('loja_id',   lojaId);
        if (dataIni) params.set('data_ini',  dataIni);
        if (dataFim) params.set('data_fim',  dataFim);

        const r = await fetch(`${APP.api}/estoque/quebras?${params}`, { headers: hj() });
        const j = await r.json();
        const lista = j.data || [];

        document.getElementById('qh-loading').style.display = 'none';
        document.getElementById('qh-total').textContent     = lista.length + ' registro(s)';

        if (!lista.length) {
            document.getElementById('qh-vazio').style.display = '';
            return;
        }

        document.getElementById('qh-wrapper').style.display = '';
        document.getElementById('qh-tbody').innerHTML = lista.map(q => `
            <tr>
                <td><small class="text-muted">${new Date(q.created_at).toLocaleString('pt-BR')}</small></td>
                <td class="fw-semibold">${q.produto_nome}</td>
                <td><span class="badge bg-success">${q.loja_nome}</span></td>
                <td class="text-end fw-bold text-danger">
                    −${parseFloat(q.quantidade).toFixed(3)} ${q.unidade_medida}
                </td>
                <td>${q.motivo}</td>
                <td><small class="text-muted">${q.usuario_nome || '—'}</small></td>
            </tr>`).join('');
    }

    async function abrirRegistro() {
        const r = await fetch(APP.api + '/produtos', { headers: hj() });
        const j = await r.json();
        document.getElementById('rq-produto').innerHTML =
            (j.data || []).map(p => `<option value="${p.id}">${p.nome} (${p.unidade})</option>`).join('');

        document.getElementById('rq-motivo-sel').onchange = function() {
            if (this.value) document.getElementById('rq-motivo').value = this.value;
        };
        bootstrap.Modal.getOrCreateInstance(document.getElementById('m-qh-registro')).show();
    }

    async function confirmar() {
        const motivo = document.getElementById('rq-motivo').value.trim()
                    || document.getElementById('rq-motivo-sel').value;
        const qtd = parseFloat(document.getElementById('rq-qtd').value);

        if (!qtd || !motivo) { alert('Preencha quantidade e motivo.'); return; }
        if (!confirm(`Confirma baixa de ${qtd} por quebra/avaria?`)) return;

        const r = await fetch(APP.api + '/estoque/quebra', {
            method: 'POST', headers: hj(),
            body: JSON.stringify({
                produto_id: parseInt(document.getElementById('rq-produto').value),
                loja_id:    parseInt(document.getElementById('rq-loja').value),
                quantidade: qtd, motivo,
            }),
        });
        if (r.ok) {
            bootstrap.Modal.getInstance(document.getElementById('m-qh-registro')).hide();
            await carregar();
        } else {
            const j = await r.json();
            alert('Erro: ' + j.message);
        }
    }

    carregar();
    return { carregar, abrirRegistro, confirmar };
})();
</script>
