<?php
/**
 * Desffrut — PDV: Abertura de Caixa
 * Fase 4 — role: caixa, gerente, super_admin
 */
$roles_permitidos = ['caixa', 'gerente', 'super_admin'];
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/database.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$u           = usuario_logado();
$loja_id_bd  = (int) ($u['loja_id'] ?? 0); // loja cadastrada no usuário (pode ser irrelevante p/ gerente)

// super_admin/dev_admin não têm loja_id fixo (supervisionam todas as lojas), e
// gerente é responsável por TODAS as lojas (não há um gerente por loja, mesmo que
// o cadastro dele tenha uma loja_id preenchida) — em ambos os casos precisa
// escolher manualmente em qual loja o caixa será aberto.
$precisa_escolher_loja = !$loja_id_bd || $u['role'] === 'gerente';
$loja_id = $precisa_escolher_loja ? 0 : $loja_id_bd; // loja "fixa" só existe para caixa

$lojas_disponiveis = [];
if ($precisa_escolher_loja) {
    $lojas_disponiveis = db()->query("SELECT id, nome FROM lojas WHERE ativo = 1 ORDER BY nome")->fetchAll();
}

// Verifica se já há caixa aberto (só é possível saber de antemão quando a loja já é conhecida)
$caixa_aberto = null;
if ($loja_id) {
    $stmt = db()->prepare('
        SELECT c.*, u.nome AS operador_nome
        FROM caixas c JOIN usuarios u ON u.id = c.usuario_id
        WHERE c.loja_id = :lid AND c.status = "aberto"
        LIMIT 1
    ');
    $stmt->execute(['lid' => $loja_id]);
    $caixa_aberto = $stmt->fetch();
}

$titulo_pagina = 'Abertura de Caixa';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title><?= NOME_SISTEMA ?> — Abertura de Caixa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f0f4f0; }
        .abertura-card {
            max-width: 460px; margin: 60px auto;
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 36px 32px;
        }
        .abertura-header { text-align: center; margin-bottom: 28px; }
        .abertura-header .icon { font-size: 3rem; }
        .abertura-header h2 { font-weight: 700; color: #1b5e20; margin-top: 8px; }
        .loja-badge { background: #e8f5e9; color: #2e7d32; padding: 6px 14px;
                      border-radius: 20px; font-size: .85rem; font-weight: 600; }
        .btn-abrir { background: #2e7d32; border: none; font-weight: 600;
                     font-size: 1.05rem; padding: 12px; }
        .btn-abrir:hover { background: #1b5e20; }
        .alerta-aberto { border-left: 4px solid #f59e0b; background: #fffbeb; }
    </style>
</head>
<body>

<div class="abertura-card">

    <div class="abertura-header">
        <div class="icon">🧾</div>
        <h2>Abertura de Caixa</h2>
        <?php if ($loja_id): ?>
            <?php
            $nome_loja = db()->prepare('SELECT nome FROM lojas WHERE id = :id');
            $nome_loja->execute(['id' => $loja_id]);
            $loja = $nome_loja->fetchColumn();
            ?>
            <span class="loja-badge">📍 <?= htmlspecialchars($loja ?: 'Loja #' . $loja_id) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($precisa_escolher_loja): ?>
    <!-- super_admin/dev_admin: precisa escolher em qual loja o caixa será aberto -->
    <div class="mb-4">
        <label class="form-label fw-semibold">Loja *</label>
        <select id="loja_select" class="form-select form-select-lg">
            <option value="">— selecione a loja —</option>
            <?php foreach ($lojas_disponiveis as $l): ?>
            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div id="area-caixa-status">
        <p class="text-muted small text-center py-3">Selecione uma loja para continuar.</p>
    </div>
    <?php else: ?>

    <!-- Alerta: caixa já aberto -->
    <?php if ($caixa_aberto): ?>
    <div class="alert alerta-aberto d-flex gap-3 align-items-start mb-4">
        <span style="font-size:1.4rem;">⚠️</span>
        <div>
            <strong>Caixa já aberto</strong><br>
            <small>Aberto por <strong><?= htmlspecialchars($caixa_aberto['operador_nome']) ?></strong>
            às <?= date('H:i', strtotime($caixa_aberto['aberto_em'])) ?>.
            Para abrir um novo caixa, feche o atual primeiro.</small>
            <div class="mt-2">
                <a href="<?= BASE_PATH ?>/pdv" class="btn btn-success btn-sm">
                    Ir para o PDV →
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- Formulário de abertura -->
    <div id="msg-erro" class="alert alert-danger d-none"></div>
    <div id="msg-sucesso" class="alert alert-success d-none"></div>

    <form id="form-abertura" autocomplete="off">
        <div class="mb-4">
            <label class="form-label fw-semibold">Fundo de Troco (R$)</label>
            <div class="input-group input-group-lg">
                <span class="input-group-text">R$</span>
                <input type="number" id="fundo_troco" name="fundo_troco"
                    class="form-control" placeholder="0,00"
                    min="0" step="0.01" value="0"
                    autofocus required>
            </div>
            <div class="form-text">Valor em dinheiro colocado no caixa para dar troco.</div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Operador</label>
            <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars($u['nome']) ?>">
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Data / Hora de Abertura</label>
            <input type="text" class="form-control" readonly
                value="<?= date('d/m/Y H:i') ?>">
        </div>

        <button type="submit" class="btn btn-abrir btn-success w-100 text-white">
            ✅ Abrir Caixa
        </button>
    </form>

    <?php endif; ?>
    <?php endif; ?>

    <div class="mt-4 text-center">
        <a href="<?= BASE_PATH ?>/dashboard" class="text-muted small">← Voltar ao painel</a>
    </div>

</div>

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

// Loja fixa do operador (caixa/gerente) — 0 quando o usuário (super_admin/dev_admin)
// precisa escolher a loja manualmente no <select id="loja_select">.
const LOJA_FIXA = <?= (int) $loja_id ?>;
const OPERADOR_NOME = <?= json_encode($u['nome'], JSON_UNESCAPED_UNICODE) ?>;
let lojaSelecionada = LOJA_FIXA || null;

function htmlFormAbertura() {
    const agora = new Date().toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    return `
        <div id="msg-erro" class="alert alert-danger d-none"></div>
        <div id="msg-sucesso" class="alert alert-success d-none"></div>
        <form id="form-abertura" autocomplete="off">
            <div class="mb-4">
                <label class="form-label fw-semibold">Fundo de Troco (R$)</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text">R$</span>
                    <input type="number" id="fundo_troco" name="fundo_troco"
                        class="form-control" placeholder="0,00"
                        min="0" step="0.01" value="0" autofocus required>
                </div>
                <div class="form-text">Valor em dinheiro colocado no caixa para dar troco.</div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Operador</label>
                <input type="text" class="form-control" readonly value="${OPERADOR_NOME}">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Data / Hora de Abertura</label>
                <input type="text" class="form-control" readonly value="${agora}">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Justificativa <span class="text-danger">*</span></label>
                <textarea id="justificativa" class="form-control" rows="2" maxlength="255" required
                    placeholder="Ex.: operador ausente hoje, estou abrindo o caixa da loja no lugar dele."></textarea>
                <div class="form-text">Você não é o operador deste caixa — explique o motivo de abrir em nome dele. Fica registrado na auditoria.</div>
            </div>
            <button type="submit" class="btn btn-abrir btn-success w-100 text-white">✅ Abrir Caixa</button>
        </form>
    `;
}

function htmlCaixaAberto(caixa) {
    const hora = caixa.aberto_em ? caixa.aberto_em.split(' ')[1]?.slice(0,5) : '';
    return `
        <div class="alert alerta-aberto d-flex gap-3 align-items-start mb-4">
            <span style="font-size:1.4rem;">⚠️</span>
            <div>
                <strong>Caixa já aberto</strong><br>
                <small>Aberto por <strong>${caixa.operador_nome || ''}</strong>
                às ${hora}. Para abrir um novo caixa, feche o atual primeiro.</small>
                <div class="mt-2">
                    <a href="${APP.base}/pdv?loja_id=${lojaSelecionada}" class="btn btn-success btn-sm">Ir para o PDV →</a>
                </div>
            </div>
        </div>
    `;
}

// Verifica o status do caixa da loja selecionada e renderiza o form ou o alerta
async function atualizarStatusLoja(lojaId) {
    const area = document.getElementById('area-caixa-status');
    if (!area) return;
    if (!lojaId) {
        area.innerHTML = '<p class="text-muted small text-center py-3">Selecione uma loja para continuar.</p>';
        return;
    }
    area.innerHTML = '<p class="text-muted small text-center py-3">Verificando caixa da loja…</p>';
    try {
        const r = await fetch(`${APP.api}/caixas?loja_id=${lojaId}&status=aberto`, {
            headers: { 'Authorization': 'Bearer ' + APP.token },
        });
        const j = await r.json();
        area.innerHTML = j.data ? htmlCaixaAberto(j.data) : htmlFormAbertura();
        ligarFormAbertura();
    } catch (e) {
        area.innerHTML = `<div class="alert alert-danger">Erro ao verificar caixa: ${e.message}</div>`;
    }
}

document.getElementById('loja_select')?.addEventListener('change', function () {
    lojaSelecionada = this.value ? parseInt(this.value) : null;
    atualizarStatusLoja(lojaSelecionada);
});

// Liga o listener de submit no formulário de abertura, seja ele o renderizado
// pelo PHP (loja fixa) ou o injetado dinamicamente pelo JS (super_admin/dev_admin).
function ligarFormAbertura() {
    const form = document.getElementById('form-abertura');
    if (!form) return;
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!lojaSelecionada) {
            alert('Selecione uma loja antes de abrir o caixa.');
            return;
        }
        // Campo de justificativa só existe no form dinâmico (gerente/super_admin,
        // que não são o operador do caixa). Para o operador (role 'caixa') o campo
        // nem aparece — a abertura normal não exige justificativa.
        const campoJust = document.getElementById('justificativa');
        const justificativa = campoJust ? campoJust.value.trim() : '';
        if (campoJust && !justificativa) {
            alert('Informe a justificativa: você não é o operador deste caixa.');
            return;
        }

        const btn   = this.querySelector('button[type=submit]');
        const fundo = parseFloat(document.getElementById('fundo_troco').value) || 0;
        btn.disabled = true;
        btn.textContent = 'Abrindo…';

        try {
            const resp = await fetch(`${APP.api}/caixas`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + APP.token },
                body:    JSON.stringify({ fundo_troco: fundo, loja_id: lojaSelecionada, justificativa }),
            });
            const json = await resp.json();

            if (json.status === 'ok') {
                // Salva caixa_id na sessionStorage para o PDV usar
                sessionStorage.setItem('desffrut_caixa_id', json.data.caixa_id);
                document.getElementById('msg-sucesso').textContent =
                    'Caixa aberto! Redirecionando para o PDV…';
                document.getElementById('msg-sucesso').classList.remove('d-none');
                // Passa loja_id explicitamente: gerente/super_admin podem ter aberto o
                // caixa de uma loja diferente da vinculada à própria conta — sem isso,
                // pdv_loja_check.php cairia de volta na loja "padrão" do usuário.
                setTimeout(() => { window.location.href = `${APP.base}/pdv?loja_id=${lojaSelecionada}`; }, 1200);
            } else {
                throw new Error(json.message || 'Erro ao abrir caixa.');
            }
        } catch (err) {
            document.getElementById('msg-erro').textContent = err.message;
            document.getElementById('msg-erro').classList.remove('d-none');
            btn.disabled = false;
            btn.textContent = '✅ Abrir Caixa';
        }
    });
}

// Caso a loja já venha fixa do servidor (caixa/gerente), o form estático já está
// no HTML — só precisa ligar o submit. Sem loja fixa, espera a escolha no select.
ligarFormAbertura();
</script>
</body>
</html>
