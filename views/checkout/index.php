<?php
/**
 * Desffrut — Checkout de Tele-Entrega
 * Exige cliente logado. Entrega centralizada na Loja de delivery (ID configurável).
 */

$roles_permitidos = ['cliente'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$titulo_pagina  = 'Finalizar Pedido';
$mostrar_sacola = false;
require_once __DIR__ . '/../../app/views/layout/header.php';

// Loja de delivery centralizada (Loja 3 por padrão)
$loja_delivery = null;
try {
    $loja_delivery = db()->query("SELECT id, nome FROM lojas WHERE id = 3 AND ativo = 1")->fetch();
    if (!$loja_delivery) {
        // fallback: primeira loja ativa
        $loja_delivery = db()->query("SELECT id, nome FROM lojas WHERE ativo = 1 ORDER BY id LIMIT 1")->fetch();
    }
} catch (Throwable $_) {}

$loja_delivery_id   = (int) ($loja_delivery['id'] ?? 3);
$loja_delivery_nome = htmlspecialchars($loja_delivery['nome'] ?? 'Central de Delivery');

// Endereço salvo no perfil (vem da sessão — preenchida no login e ao editar perfil)
$end = [
    'endereco'    => htmlspecialchars($usuario['endereco']    ?? ''),
    'numero'      => htmlspecialchars($usuario['numero']      ?? ''),
    'complemento' => htmlspecialchars($usuario['complemento'] ?? ''),
    'bairro'      => htmlspecialchars($usuario['bairro']      ?? ''),
    'telefone'    => htmlspecialchars($usuario['telefone']    ?? ''),
];
$tem_endereco = !empty(trim($end['endereco']));
?>

<div class="container py-4" style="max-width:640px;">

    <h2 class="fw-bold mb-4">🛒 Finalizar Pedido</h2>

    <div id="checkout-alerta" class="alert d-none mb-3"></div>

    <!-- Resumo da sacola -->
    <div class="card mb-3 shadow-sm">
        <div class="card-header fw-semibold">Sua sacola</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" id="checkout-tabela">
                <thead class="table-light">
                    <tr><th>Produto</th><th class="text-end">Qtd</th><th class="text-end">Subtotal</th></tr>
                </thead>
                <tbody id="checkout-itens"></tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="2" class="text-end">Total</td>
                        <td class="text-end" id="checkout-total">R$ 0,00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <form id="form-checkout" novalidate>

        <!-- Filial — informativa (não selecionável) -->
        <div class="mb-3">
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-0">
                🏪 <span>Entrega via: <strong><?= $loja_delivery_nome ?></strong></span>
            </div>
            <input type="hidden" id="ch-loja" value="<?= $loja_delivery_id ?>">
        </div>

        <!-- Endereço de entrega -->
        <div class="card mb-3 shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                📍 Endereço de entrega
                <?php if ($tem_endereco): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        id="btn-alterar-end" onclick="alternarEndereco()">✏️ Alterar</button>
                <?php endif; ?>
            </div>
            <div class="card-body">

                <?php if ($tem_endereco): ?>
                <!-- Bloco: endereço confirmado -->
                <div id="end-confirmado">
                    <div class="bg-light rounded p-3 mb-2" id="end-resumo">
                        <div class="fw-semibold text-success mb-1">✅ Endereço salvo</div>
                        <div id="end-texto-resumo">
                            <?= $end['endereco'] ?><?= $end['numero'] ? ', ' . $end['numero'] : '' ?>
                            <?= $end['complemento'] ? ' — ' . $end['complemento'] : '' ?>
                            <?= $end['bairro'] ? ' · ' . $end['bairro'] : '' ?>
                        </div>
                        <?php if ($end['telefone']): ?>
                        <div class="text-muted small mt-1">📞 <?= $end['telefone'] ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="ch-confirmar-end"
                               onchange="confirmarEndereco(this.checked)">
                        <label class="form-check-label fw-semibold" for="ch-confirmar-end">
                            Confirmo este endereço para entrega
                        </label>
                    </div>
                </div>

                <!-- Bloco: editar endereço (oculto inicialmente) -->
                <div id="end-editar" style="display:none;">
                <?php endif; ?>

                    <div class="row g-2">
                        <div class="col-8">
                            <label class="form-label">Rua / Avenida <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="endereco_entrega" id="ch-end"
                                   value="<?= $end['endereco'] ?>" placeholder="Nome da rua">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Número</label>
                            <input type="text" class="form-control" name="numero" id="ch-num"
                                   value="<?= $end['numero'] ?>" placeholder="S/N">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Complemento</label>
                            <input type="text" class="form-control" name="complemento" id="ch-comp"
                                   value="<?= $end['complemento'] ?>" placeholder="Apto, bloco…">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Bairro <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="bairro" id="ch-bairro"
                                   value="<?= $end['bairro'] ?>" placeholder="Bairro">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Telefone para contato</label>
                            <input type="tel" class="form-control" name="telefone" id="ch-tel"
                                   value="<?= $end['telefone'] ?>" placeholder="(85) 99999-9999">
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="ch-salvar-end">
                        <label class="form-check-label small" for="ch-salvar-end">
                            Salvar este endereço no meu perfil
                        </label>
                    </div>

                    <?php if ($tem_endereco): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2"
                            onclick="cancelarAlteracaoEndereco()">← Voltar ao endereço salvo</button>
                    <?php endif; ?>

                <?php if ($tem_endereco): ?>
                </div><!-- /end-editar -->
                <?php endif; ?>

            </div>
        </div>

        <!-- Forma de pagamento -->
        <div class="card mb-3 shadow-sm">
            <div class="card-header fw-semibold">💳 Pagamento na entrega</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3" id="ch-formas">
                    <label class="btn btn-outline-secondary ch-fp-btn">
                        <input type="radio" name="forma_pagamento" value="dinheiro_na_entrega" class="d-none">
                        💵 Dinheiro
                    </label>
                    <label class="btn btn-outline-secondary ch-fp-btn">
                        <input type="radio" name="forma_pagamento" value="cartao_debito_entrega" class="d-none">
                        💳 Débito
                    </label>
                    <label class="btn btn-outline-secondary ch-fp-btn">
                        <input type="radio" name="forma_pagamento" value="cartao_credito_entrega" class="d-none">
                        💳 Crédito
                    </label>
                    <label class="btn btn-outline-secondary ch-fp-btn">
                        <input type="radio" name="forma_pagamento" value="pix" class="d-none">
                        🔑 Pix
                    </label>
                </div>
                <div id="ch-troco-wrap" style="display:none;">
                    <label class="form-label small">Troco para quanto?</label>
                    <div class="input-group" style="max-width:200px;">
                        <span class="input-group-text">R$</span>
                        <input type="number" class="form-control" id="ch-troco" name="troco_para"
                               placeholder="0,00" min="0" step="0.01">
                    </div>
                </div>
            </div>
        </div>

        <!-- Observações -->
        <div class="mb-3">
            <label class="form-label">Observações (opcional)</label>
            <textarea class="form-control" name="observacoes" rows="2"
                      placeholder="Ex: sem cebola, interfone 203…"></textarea>
        </div>

        <!-- Botões -->
        <button type="submit" class="btn btn-success btn-lg w-100 fw-bold" id="btn-fazer-pedido">
            ✅ Confirmar Pedido
        </button>

        <div class="ch-ou">ou</div>

        <button type="button" class="btn btn-ch-wa w-100 fw-bold" id="btn-pedido-wa">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="currentColor" style="vertical-align:-.2em;margin-right:6px;">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
            </svg>
            Enviar pedido via WhatsApp
        </button>

        <a href="<?= BASE_PATH ?>/" class="btn btn-link w-100 mt-1">← Voltar ao catálogo</a>

    </form>
</div>

<style>
.ch-fp-btn { cursor: pointer; user-select: none; }
.ch-fp-btn.active { background: #198754; color: #fff; border-color: #198754; }

.btn-ch-wa {
    background: #25d366; color: #fff; border: none;
    font-size: 1rem; padding: 12px; border-radius: 8px;
    transition: background .15s; min-height: 48px;
}
.btn-ch-wa:hover { background: #1ebe5d; color: #fff; }
.btn-ch-wa:disabled { opacity: .6; }

.ch-ou {
    text-align: center; color: #aaa; font-size: .85rem;
    margin: 10px 0; position: relative;
}
.ch-ou::before, .ch-ou::after {
    content: ''; display: inline-block; width: 42%;
    height: 1px; background: #e0e0e0;
    vertical-align: middle; margin: 0 8px;
}
@media (max-width: 576px) {
    .container { padding: 8px !important; }
    h2.fw-bold { font-size: 1.3rem; }
    .card-body { padding: 14px !important; }
    .form-control, .form-select { font-size: 16px !important; min-height: 44px; }
    .ch-fp-btn { min-height: 44px; font-size: .95rem; }
    #btn-fazer-pedido { min-height: 52px; font-size: 1.05rem; }
}
</style>

<script>
// ── Carrega sacola do localStorage ────────────────────────────────────────────
let _sacola;
try {
    _sacola = JSON.parse(localStorage.getItem('desffrut_sacola') || '{"items":[]}');
} catch (_) {
    _sacola = { items: [] };
    localStorage.removeItem('desffrut_sacola');
}

// ── Estado do endereço ────────────────────────────────────────────────────────
const TEM_END_SALVO = <?= $tem_endereco ? 'true' : 'false' ?>;
let enderecoConfirmado = !TEM_END_SALVO; // sem endereço salvo = campo obrigatório mas não precisa de confirm

function alternarEndereco() {
    document.getElementById('end-confirmado').style.display = 'none';
    document.getElementById('end-editar').style.display = '';
    // Ao editar, address confirmed = false até salvar
    enderecoConfirmado = false;
}

function cancelarAlteracaoEndereco() {
    document.getElementById('end-editar').style.display = 'none';
    document.getElementById('end-confirmado').style.display = '';
    enderecoConfirmado = document.getElementById('ch-confirmar-end').checked;
}

function confirmarEndereco(checked) {
    enderecoConfirmado = checked;
}

// ── Renderiza sacola ──────────────────────────────────────────────────────────
(function renderizarSacola() {
    const tbody   = document.getElementById('checkout-itens');
    const totalEl = document.getElementById('checkout-total');

    if (!_sacola.items.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Sacola vazia.</td></tr>';
        document.getElementById('btn-fazer-pedido').disabled = true;
        return;
    }

    let total = 0;
    tbody.innerHTML = _sacola.items.map(it => {
        total += it.subtotal;
        const qtd = it.unidade === 'kg'
            ? it.quantidade.toFixed(3) + ' kg'
            : it.quantidade + ' un';
        return `<tr>
            <td>${it.nome}</td>
            <td class="text-end">${qtd}</td>
            <td class="text-end">R$ ${it.subtotal.toFixed(2).replace('.', ',')}</td>
        </tr>`;
    }).join('');
    totalEl.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
})();

// ── Formas de pagamento ───────────────────────────────────────────────────────
document.querySelectorAll('.ch-fp-btn').forEach(label => {
    label.addEventListener('click', () => {
        document.querySelectorAll('.ch-fp-btn').forEach(l => l.classList.remove('active'));
        label.classList.add('active');
        document.getElementById('ch-troco-wrap').style.display =
            label.querySelector('input').value === 'dinheiro_na_entrega' ? '' : 'none';
    });
});

// ── Helper: monta payload ─────────────────────────────────────────────────────
function montarPayload(canal) {
    // Endereço: usa o pré-preenchido (confirmado) ou o digitado no formulário
    const endConfirmDiv = document.getElementById('end-confirmado');
    const endEditDiv    = document.getElementById('end-editar');
    const editandoEnd   = !TEM_END_SALVO || (endEditDiv && endEditDiv.style.display !== 'none');

    const endereco   = editandoEnd ? document.getElementById('ch-end').value.trim()
                                   : (document.getElementById('ch-end').value.trim());
    const numero     = document.getElementById('ch-num').value.trim();
    const complemento= document.getElementById('ch-comp').value.trim();
    const bairro     = document.getElementById('ch-bairro').value.trim();
    const telefone   = document.getElementById('ch-tel').value.trim();

    return {
        loja_id:          <?= $loja_delivery_id ?>,
        itens:            _sacola.items.map(it => ({
            produto_id: it.produto_id,
            quantidade: it.quantidade,
        })),
        endereco_entrega: endereco,
        numero,
        complemento,
        bairro,
        telefone,
        forma_pagamento:  document.querySelector('input[name="forma_pagamento"]:checked')?.value || '',
        troco_para:       document.querySelector('input[name="forma_pagamento"]:checked')?.value === 'dinheiro_na_entrega'
                              ? parseFloat(document.getElementById('ch-troco').value) || null
                              : null,
        observacoes:      document.querySelector('textarea[name=observacoes]').value.trim(),
        canal_origem:     canal,
    };
}

// ── Validação comum ───────────────────────────────────────────────────────────
function validar() {
    const fp = document.querySelector('input[name="forma_pagamento"]:checked')?.value;
    if (!fp) { mostrarAlerta('Selecione a forma de pagamento.', 'warning'); return false; }

    // Verifica endereço
    if (TEM_END_SALVO && !enderecoConfirmado) {
        // Verifica se está no modo editar com rua preenchida
        const endEditDiv = document.getElementById('end-editar');
        const editando   = endEditDiv && endEditDiv.style.display !== 'none';
        if (!editando) {
            mostrarAlerta('Confirme o endereço de entrega marcando a caixa abaixo do endereço.', 'warning');
            return false;
        }
    }

    const endereco = document.getElementById('ch-end').value.trim();
    if (!endereco) { mostrarAlerta('Informe o endereço de entrega.', 'warning'); return false; }

    return true;
}

// ── Submissão do form (Confirmar Pedido) ──────────────────────────────────────
document.getElementById('form-checkout').addEventListener('submit', async e => {
    e.preventDefault();
    if (!validar()) return;

    const payload = montarPayload('web');
    const btn = document.getElementById('btn-fazer-pedido');
    btn.disabled = true;
    btn.textContent = 'Enviando…';

    try {
        const resp = await fetch(APP.api + '/pedidos', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await resp.json();

        if (!resp.ok || json.status !== 'ok') throw new Error(json.message || 'Erro ao criar pedido.');

        if (document.getElementById('ch-salvar-end')?.checked) {
            fetch(APP.api + '/clientes/perfil', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endereco: payload.endereco_entrega,
                    numero:   payload.numero,
                    complemento: payload.complemento,
                    bairro:   payload.bairro,
                    telefone: payload.telefone,
                }),
            }).catch(() => {});
        }

        localStorage.removeItem('desffrut_sacola');
        window.location.href = APP.base + '/pedidos/' + json.data.pedido_id + '/status';

    } catch (err) {
        mostrarAlerta(err.message, 'danger');
        btn.disabled = false;
        btn.textContent = '✅ Confirmar Pedido';
    }
});

// ── WhatsApp ──────────────────────────────────────────────────────────────────
document.getElementById('btn-pedido-wa').addEventListener('click', async () => {
    if (!validar()) return;

    const payload = montarPayload('whatsapp');
    const btn = document.getElementById('btn-pedido-wa');
    btn.disabled = true;
    btn.textContent = 'Preparando…';

    try {
        const resp = await fetch(APP.api + '/pedidos', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await resp.json();
        if (!resp.ok || json.status !== 'ok') throw new Error(json.message || 'Erro ao criar pedido.');

        if (document.getElementById('ch-salvar-end')?.checked) {
            fetch(APP.api + '/clientes/perfil', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endereco: payload.endereco_entrega,
                    numero:   payload.numero,
                    complemento: payload.complemento,
                    bairro:   payload.bairro,
                    telefone: payload.telefone,
                }),
            }).catch(() => {});
        }

        localStorage.removeItem('desffrut_sacola');

        if (json.data.wa_url) {
            window.open(json.data.wa_url, '_blank');
        }
        setTimeout(() => {
            window.location.href = APP.base + '/pedidos/' + json.data.pedido_id + '/status';
        }, 1000);

    } catch (err) {
        mostrarAlerta(err.message, 'danger');
        btn.disabled = false;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-.2em;margin-right:6px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>Enviar pedido via WhatsApp';
    }
});

function mostrarAlerta(msg, tipo) {
    const el = document.getElementById('checkout-alerta');
    el.textContent = msg;
    el.className   = `alert alert-${tipo}`;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
