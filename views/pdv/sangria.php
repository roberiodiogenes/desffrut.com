<?php
/**
 * Desffrut — PDV: Sangria / Suprimento
 * Fase 4 — role: caixa, gerente, super_admin
 * Justificativa obrigatória para sangria.
 */
$roles_permitidos = ['caixa', 'gerente', 'super_admin'];
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/database.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$u       = usuario_logado();
$loja_id = (int) ($u['loja_id'] ?? 0);

// Busca caixa aberto
$caixa = null;
if ($loja_id) {
    $stmt = db()->prepare('SELECT id, fundo_troco, aberto_em FROM caixas WHERE loja_id = :lid AND status = "aberto" LIMIT 1');
    $stmt->execute(['lid' => $loja_id]);
    $caixa = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title><?= NOME_SISTEMA ?> — Sangria / Suprimento</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js" defer></script>
    <style>
        body { background: #f0f4f0; }
        .sangria-card {
            max-width: 480px; margin: 50px auto;
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 36px 32px;
        }
        .sangria-header { text-align: center; margin-bottom: 24px; }
        .sangria-header .icon { font-size: 2.8rem; }
        .sangria-header h2 { font-weight: 700; color: #b45309; margin-top: 6px; }
        .tipo-btn { flex: 1; padding: 14px; font-size: 1rem; font-weight: 600; border-radius: 8px; }
        .tipo-btn.active[data-tipo="sangria"]   { background: #dc2626; color: #fff; border-color: #dc2626; }
        .tipo-btn.active[data-tipo="suprimento"] { background: #16a34a; color: #fff; border-color: #16a34a; }
        .btn-confirmar { font-size: 1rem; font-weight: 600; padding: 12px; }
        #hist-body tr:nth-child(odd) { background: #f9fafb; }
    </style>
</head>
<body>

<div class="sangria-card">

    <div class="sangria-header">
        <div class="icon">💸</div>
        <h2>Sangria / Suprimento</h2>
    </div>

    <?php if (!$caixa): ?>
    <div class="alert alert-warning">
        Nenhum caixa aberto. <a href="<?= BASE_PATH ?>/pdv/abertura">Abrir caixa</a> antes de registrar uma sangria.
    </div>
    <?php else: ?>

    <div id="msg-resultado" class="alert d-none mb-3"></div>

    <form id="form-sangria" autocomplete="off">
        <input type="hidden" id="caixa_id" value="<?= $caixa['id'] ?>">

        <!-- Tipo -->
        <div class="mb-4">
            <label class="form-label fw-semibold">Tipo de Movimento</label>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-danger tipo-btn active"
                        data-tipo="sangria" onclick="selecionarTipo('sangria')">
                    ↓ Sangria
                </button>
                <button type="button" class="btn btn-outline-success tipo-btn"
                        data-tipo="suprimento" onclick="selecionarTipo('suprimento')">
                    ↑ Suprimento
                </button>
            </div>
        </div>

        <!-- Valor -->
        <div class="mb-4">
            <label class="form-label fw-semibold">Valor (R$)</label>
            <div class="input-group input-group-lg">
                <span class="input-group-text">R$</span>
                <input type="number" id="valor" name="valor"
                    class="form-control" placeholder="0,00"
                    min="0.01" step="0.01" required autofocus>
            </div>
        </div>

        <!-- Justificativa -->
        <div class="mb-4">
            <label class="form-label fw-semibold">
                Justificativa <span id="just-obrig" class="text-danger">*</span>
            </label>
            <textarea id="justificativa" name="justificativa" class="form-control"
                rows="3" placeholder="Descreva o motivo (obrigatório para sangria)…"
                maxlength="255"></textarea>
        </div>

        <!-- Operador -->
        <div class="mb-4">
            <label class="form-label fw-semibold">Operador</label>
            <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars($u['nome']) ?>">
        </div>

        <button type="submit" class="btn btn-warning btn-confirmar w-100 fw-bold">
            ✅ Confirmar Sangria
        </button>
    </form>

    <!-- Histórico do caixa -->
    <div class="mt-4">
        <h6 class="fw-semibold text-muted mb-2">Movimentos do Caixa Atual</h6>
        <table class="table table-sm small">
            <thead class="table-light">
                <tr><th>Hora</th><th>Tipo</th><th class="text-end">Valor</th><th>Justificativa</th></tr>
            </thead>
            <tbody id="hist-body">
                <tr><td colspan="4" class="text-center text-muted">Carregando…</td></tr>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

    <div class="mt-3 text-center">
        <a href="<?= BASE_PATH ?>/pdv" class="text-muted small">← Voltar ao PDV</a>
    </div>

</div>

<script src="<?= BASE_PATH ?>/public/js/pdv/hardware.js"></script>
<script>
const APP = {
    api:   '<?= API_ROOT ?>',
    base:  '<?= BASE_PATH ?>',
    // O token da sessão PHP atual é sempre a fonte da verdade (garantido válido, pois esta
    // página já exigiu sessão logada). sessionStorage pode ter um token de um login ANTERIOR
    // nesta mesma aba (ex.: trocou de usuário) — se usarmos ele com prioridade, o servidor
    // rejeita como "token inválido" mesmo com sessão ativa. Por isso re-sincroniza sempre.
    token: '<?= addslashes($_SESSION['api_token'] ?? '') ?>' || sessionStorage.getItem('desffrut_token') || '',
};
if (APP.token) {
    sessionStorage.setItem('desffrut_token', APP.token);
}

let tipoAtual = 'sangria';

function selecionarTipo(tipo) {
    tipoAtual = tipo;
    document.querySelectorAll('.tipo-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tipo === tipo);
    });
    const btn = document.querySelector('button[type=submit]');
    btn.textContent = tipo === 'sangria' ? '✅ Confirmar Sangria' : '✅ Confirmar Suprimento';
    btn.className   = `btn btn-confirmar w-100 fw-bold ${tipo === 'sangria' ? 'btn-warning' : 'btn-success'}`;
    document.getElementById('just-obrig').style.display = tipo === 'sangria' ? '' : 'none';
}

document.getElementById('form-sangria')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn          = this.querySelector('button[type=submit]');
    const caixa_id     = parseInt(document.getElementById('caixa_id').value);
    const valor        = parseFloat(document.getElementById('valor').value);
    const justificativa = document.getElementById('justificativa').value.trim();
    const msgEl        = document.getElementById('msg-resultado');

    if (tipoAtual === 'sangria' && !justificativa) {
        msgEl.textContent = 'Justificativa obrigatória para sangria.';
        msgEl.className   = 'alert alert-danger';
        msgEl.classList.remove('d-none');
        return;
    }

    btn.disabled    = true;
    btn.textContent = 'Registrando…';

    try {
        const resp = await fetch(`${APP.api}/caixas/${caixa_id}/sangria`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + APP.token },
            body:    JSON.stringify({ tipo: tipoAtual, valor, justificativa }),
        });
        const json = await resp.json();

        if (json.status === 'ok') {
            msgEl.textContent = json.message;
            msgEl.className   = 'alert alert-success';
            msgEl.classList.remove('d-none');

            // Impressão do comprovante (se a extensão Native Messaging estiver instalada)
            if (typeof DesffrHardware !== 'undefined' &&
                await DesffrHardware.extensaoInstalada() &&
                DesffrHardware.getConfig().printer) {
                try {
                    await DesffrHardware.imprimirSangria({
                        tipo:          tipoAtual,
                        valor,
                        justificativa,
                        caixa_id:      parseInt(document.getElementById('caixa_id').value),
                        operador:      '<?= htmlspecialchars($u['nome']) ?>',
                    });
                } catch (eImp) {
                    console.warn('[Hardware] Falha ao imprimir comprovante:', eImp.message);
                }
            }

            document.getElementById('valor').value         = '';
            document.getElementById('justificativa').value = '';
            carregarHistorico();
            setTimeout(() => {
                window.location.href = `${APP.base}/pdv`;
            }, 1500);
        } else {
            throw new Error(json.message);
        }
    } catch (err) {
        msgEl.textContent = err.message;
        msgEl.className   = 'alert alert-danger';
        msgEl.classList.remove('d-none');
        btn.disabled    = false;
        btn.textContent = tipoAtual === 'sangria' ? '✅ Confirmar Sangria' : '✅ Confirmar Suprimento';
    }
});

async function carregarHistorico() {
    const caixa_id = document.getElementById('caixa_id')?.value;
    if (!caixa_id) return;
    try {
        const resp = await fetch(
            `${APP.api}/caixas/sangrias?loja_id=<?= $loja_id ?>&data_ini=<?= date('Y-m-d') ?>&data_fim=<?= date('Y-m-d') ?>`,
            { headers: { 'Authorization': 'Bearer ' + APP.token } }
        );
        const json = await resp.json();
        const tbody = document.getElementById('hist-body');
        const rows  = (json.data || []).filter(r => parseInt(r.caixa_id) === parseInt(caixa_id));
        tbody.innerHTML = rows.length
            ? rows.map(r => `
                <tr>
                    <td>${new Date(r.created_at).toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit' })}</td>
                    <td><span class="badge ${r.tipo === 'sangria' ? 'bg-danger' : 'bg-success'}">${r.tipo}</span></td>
                    <td class="text-end">R$ ${parseFloat(r.valor).toFixed(2)}</td>
                    <td class="text-muted small">${r.justificativa || '—'}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="4" class="text-center text-muted">Nenhum movimento neste turno.</td></tr>';
    } catch { /* silencioso */ }
}

carregarHistorico();
</script>
</body>
</html>
