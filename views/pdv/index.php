<?php
/**
 * Desffrut — PDV: Frente de Caixa
 * Fase 4 — role: caixa (acesso direto, fora do dashboard)
 * Chrome/Edge obrigatório. QZ Tray necessário para impressão (Fase 5).
 */
$roles_permitidos = ['caixa', 'gerente', 'super_admin'];
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/database.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/auth_check.php';
require_once __DIR__ . '/../../app/middleware/pdv_loja_check.php';
// Após pdv_loja_check.php: $loja_id_pdv (int) e $loja_pdv (array|null) estão definidos

$u       = usuario_logado();
$loja_id = $loja_id_pdv;  // Vem do middleware

// Lista lojas ativas para o seletor do super_admin/gerente
$lojas_disponiveis = [];
if (in_array($u['role'], ['super_admin', 'dev_admin', 'gerente'], true)) {
    try {
        $lojas_disponiveis = db()->query("SELECT id, nome FROM lojas WHERE ativo=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $_) {}
}

// Verifica se há caixa aberto
$caixa = null;
if ($loja_id) {
    $stmt = db()->prepare('
        SELECT c.id, c.fundo_troco, c.aberto_em,
               COALESCE(SUM(v.total_final),0) AS total_vendas,
               COUNT(v.id) AS qtd_vendas
        FROM caixas c
        LEFT JOIN vendas v ON v.caixa_id = c.id AND v.status = "finalizada"
        WHERE c.loja_id = :lid AND c.status = "aberto"
        GROUP BY c.id
        LIMIT 1
    ');
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
    <title><?= NOME_SISTEMA ?> — PDV</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/pdv.css?v=<?= filemtime(__DIR__ . '/../../public/css/pdv.css') ?>">
    <!-- QZ Tray — agente local para impressão térmica (Fase 5) -->
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js" defer></script>
</head>
<body class="pdv-body">

<?php if (!$caixa): ?>
<!-- ── Sem caixa aberto ──────────────────────────────────────────────────── -->
<div class="pdv-sem-caixa">
    <div class="text-center p-5">
        <div style="font-size:4rem;">🔒</div>
        <h3 class="mt-3 fw-bold">Caixa não está aberto</h3>
        <p class="text-muted">Abra o caixa com o fundo de troco antes de iniciar as vendas.</p>
        <a href="<?= BASE_PATH ?>/pdv/abertura" class="btn btn-success btn-lg px-5 mt-2">
            🧾 Abrir Caixa
        </a>
        <div class="mt-3">
            <a href="<?= BASE_PATH ?>/dashboard" class="text-muted small">← Painel</a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── Layout principal do PDV ────────────────────────────────────────────── -->

<!-- Barra superior -->
<header class="pdv-header">
    <div class="pdv-header-left">
        <span class="pdv-brand">🌿 <?= htmlspecialchars(NOME_SISTEMA) ?></span>
        <span class="pdv-sep">|</span>
        <?php if ($lojas_disponiveis): ?>
        <!-- super_admin/gerente: seletor de loja -->
        <form method="GET" action="<?= BASE_PATH ?>/pdv" class="d-flex align-items-center gap-1">
            <select name="loja_id" class="form-select form-select-sm"
                    style="max-width:180px;background:#ffffff22;border-color:#ffffff44;color:#fff;font-size:.8rem;"
                    onchange="this.form.submit()">
                <?php foreach ($lojas_disponiveis as $lv): ?>
                <option value="<?= $lv['id'] ?>" <?= $lv['id'] == $loja_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lv['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php else: ?>
        <span style="font-size:.85rem;opacity:.85;">📍 <?= htmlspecialchars($loja_pdv['nome'] ?? '') ?></span>
        <?php endif; ?>
        <span class="pdv-sep">|</span>
        <span class="pdv-turno">
            Turno: <?= date('H:i', strtotime($caixa['aberto_em'])) ?> →
            <strong>R$ <?= number_format($caixa['total_vendas'], 2, ',', '.') ?></strong>
            (<?= $caixa['qtd_vendas'] ?> vendas)
        </span>
    </div>
    <div class="pdv-header-right">
        <span id="pdv-clock" class="pdv-clock"></span>
        <span id="pdv-status-online" class="badge bg-success">🟢 Online</span>
        <span id="pdv-pendentes-badge" class="badge bg-warning text-dark" style="display:none;"></span>
        <button class="btn btn-sm btn-outline-light ms-2" onclick="window.location.href='<?= BASE_PATH ?>/pdv/sangria'" title="F4 — Sangria">
            💸 Sangria (F4)
        </button>
        <button class="btn btn-sm btn-outline-danger ms-1" id="btn-fechar-caixa" title="Fechar caixa do dia">
            🔒 Fechar
        </button>
        <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-sm btn-outline-light ms-1">Painel</a>
    </div>
</header>

<!-- Alerta toast flutuante -->
<div id="pdv-alerta" class="pdv-alerta-toast" style="display:none;"></div>

<!-- Corpo do PDV: painel esquerdo (60%) + carrinho (40%) -->
<div class="pdv-main">

    <!-- ── Coluna esquerda: catálogo ───────────────────────────────── -->
    <aside class="pdv-left">

        <!-- Busca rápida + CPF -->
        <div class="pdv-search-area">
            <div class="pdv-search-box">
                <div class="pdv-busca-wrap">
                    <input type="text" id="pdv-busca" class="pdv-input-busca"
                        placeholder="🔍 EAN, código ou nome (F1)…"
                        autocomplete="off" autofocus>
                    <button id="pdv-btn-scanner" class="pdv-btn-cam"
                            onclick="abrirScannerPDV()" title="Scanner câmera (F5)">📷</button>
                </div>
                <div id="pdv-lista-produtos" class="list-group pdv-lista-prod" style="display:none;"></div>
            </div>
        </div>

        <!-- Identificação do cliente -->
        <div class="pdv-cliente-inline">
            <span style="font-size:.8rem;color:#8b949e;white-space:nowrap;">👤 CPF</span>
            <input type="text" id="pdv-cpf" placeholder="000.000.000-00" maxlength="14" autocomplete="off">
            <button onclick="PDV.buscarCliente(document.getElementById('pdv-cpf').value)">Buscar</button>
            <button onclick="PDV.abrirCadastroCliente()" title="Cadastrar novo cliente">+ Cadastrar</button>
            <div id="pdv-cliente-info">Sem cliente</div>
        </div>

        <!-- Produto atual — reflete o último item bipado/adicionado -->
        <div class="pdv-produto-atual-wrap">
            <div class="pdv-label" style="padding:8px 14px 0;">Produto atual</div>
            <div id="pdv-produto-atual" class="pdv-produto-atual">
                <div class="pdv-produto-atual-vazio">Nenhum produto adicionado ainda</div>
            </div>
        </div>

        <!-- Chips de categoria -->
        <div class="pdv-cats" id="pdv-cats">
            <div class="pdv-cat-chip ativo" data-cat="">🍃 Todos</div>
        </div>

        <!-- Grid de produtos com foto -->
        <div class="pdv-prod-grid" id="pdv-prod-grid">
            <div style="color:#8b949e;font-size:.82rem;grid-column:1/-1;text-align:center;padding-top:24px;">
                Carregando catálogo…
            </div>
        </div>

        <!-- Preview do cupom (pós-venda) -->
        <pre id="pdv-cupom-preview" class="pdv-cupom mx-3 mb-2" style="display:none;"></pre>
        <button id="pdv-btn-reimprimir" class="btn btn-outline-light btn-sm mx-3 mb-2"
                style="display:none;" onclick="PDV.reimprimirCupom()">
            🖨️ Reimprimir Cupom
        </button>

    </aside>

    <!-- ── Coluna direita: carrinho ─────────────────────────────────── -->
    <section class="pdv-right">

        <div class="pdv-carrinho-header">
            <span>🛒 Carrinho</span>
            <button class="btn btn-sm btn-outline-danger" onclick="PDV.limparVenda()">🗑 Limpar</button>
        </div>

        <div class="pdv-table-wrap">
            <table class="table table-sm pdv-tabela">
                <thead>
                    <tr>
                        <th style="width:22px;">#</th>
                        <th>Produto</th>
                        <th style="width:110px;">Qtd</th>
                        <th class="text-end" style="width:80px;">Preço</th>
                        <th class="text-end" style="width:80px;">Total</th>
                        <th style="width:34px;"></th>
                    </tr>
                </thead>
                <tbody id="pdv-itens-tbody"></tbody>
            </table>
        </div>

        <!-- Subtotal / desconto -->
        <div class="pdv-totais">
            <div class="pdv-total-row">
                <span>Subtotal</span><span id="pdv-total-bruto">R$ 0,00</span>
            </div>
            <div class="pdv-total-row" style="color:#c62828;">
                <span>Desconto</span><span id="pdv-total-desconto">R$ 0,00</span>
            </div>
            <!-- Mantido oculto: usado apenas internamente pelos cálculos de pdv.js -->
            <span id="pdv-total-liquido" style="display:none;"></span>
        </div>

        <!-- TOTAL — elemento de maior destaque da tela, sempre visível -->
        <div class="pdv-pag-total-box">
            <div class="text-muted">Total a pagar</div>
            <div id="pdv-pag-total" class="pdv-pag-total-valor">R$ 0,00</div>
            <div class="text-muted" style="font-size:.75rem;">
                Cliente: <strong id="pdv-pag-pontos-disponiveis">0</strong> pts disponíveis
            </div>
        </div>

        <!-- Forma de pagamento — sempre visível, sem passos escondidos -->
        <div class="pdv-forma-pagamento">
            <label class="pdv-label">Forma de Pagamento</label>
            <input type="hidden" id="pdv-pag-forma" value="dinheiro">
            <div class="pdv-forma-btns">
                <button type="button" class="pdv-forma-btn ativo" data-forma="dinheiro" onclick="PDV.selecionarForma('dinheiro')">💵<span>Dinheiro</span></button>
                <button type="button" class="pdv-forma-btn" data-forma="debito" onclick="PDV.selecionarForma('debito')">💳<span>Débito</span></button>
                <button type="button" class="pdv-forma-btn" data-forma="credito" onclick="PDV.selecionarForma('credito')">💳<span>Crédito</span></button>
                <button type="button" class="pdv-forma-btn" data-forma="pix" onclick="PDV.selecionarForma('pix')">📱<span>Pix</span></button>
                <button type="button" class="pdv-forma-btn" data-forma="pontos" onclick="PDV.selecionarForma('pontos')">🏆<span>Pontos</span></button>
                <button type="button" class="pdv-forma-btn" data-forma="misto" onclick="PDV.selecionarForma('misto')">🔀<span>Misto</span></button>
            </div>
        </div>

        <!-- Recebido / Troco — sempre visível, atualiza em tempo real -->
        <div class="pdv-recebido-troco">
            <div class="pdv-recebido-campo">
                <label class="pdv-label">Recebido (R$)</label>
                <input type="number" id="pdv-pag-valor" class="pdv-input-recebido"
                    placeholder="0,00" step="0.01" min="0" oninput="PDV.calcularTroco()">
                <div class="pdv-pag-quick" id="pdv-quick-vals"></div>
            </div>
            <div class="pdv-troco-campo">
                <label class="pdv-label">Troco</label>
                <div id="pdv-pag-troco" class="pdv-troco-valor">R$ 0,00</div>
            </div>
        </div>

        <!-- Atalhos -->
        <div class="pdv-atalhos-bar">
            <div class="pdv-atalho"><kbd>F1</kbd> Buscar</div>
            <div class="pdv-atalho"><kbd>F2</kbd> Finalizar</div>
            <div class="pdv-atalho"><kbd>F3</kbd> Remover</div>
            <div class="pdv-atalho"><kbd>F4</kbd> Sangria</div>
            <div class="pdv-atalho"><kbd>F5</kbd> Câmera</div>
            <div class="pdv-atalho"><kbd>ESC</kbd> Limpar</div>
        </div>

        <!-- Botão finalizar -->
        <button id="pdv-btn-confirmar-pag" class="btn btn-success pdv-btn-finalizar w-100"
                onclick="PDV.finalizarVenda()">
            ✅ Finalizar Venda (F2)
        </button>

    </section>

</div>

<!-- ── Modal quantidade (Kg) — fora do .pdv-main ──────────────────────────── -->
<div id="pdv-modal-quantidade" class="pdv-modal-overlay" style="display:none;">
    <div class="pdv-modal-box">
        <h5 class="fw-bold mb-1" id="pdv-qty-nome">Produto</h5>
        <div style="font-size:.82rem;color:#8b949e;margin-bottom:14px;">
            Preço: <strong id="pdv-qty-preco">—</strong> / kg
        </div>
        <label class="form-label fw-semibold">Peso (kg)</label>
        <div class="input-group mb-3">
            <input type="number" id="pdv-qty-valor" class="form-control form-control-lg text-end"
                placeholder="0.000" step="0.001" min="0.001">
            <button class="btn btn-outline-secondary" id="pdv-btn-balanca"
                    onclick="lerPesoBalancaPDV()" title="Capturar peso da balança (Web Serial)">⚖️</button>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success flex-fill" onclick="PDV.confirmarQuantidade()">✅ Confirmar</button>
            <button class="btn btn-outline-secondary"
                    onclick="document.getElementById('pdv-modal-quantidade').style.display='none'">Cancelar</button>
        </div>
    </div>
</div>

<!-- ── Modal Cadastrar Cliente ──────────────────────────────────────────── -->
<div id="pdv-modal-cadastro-cliente" class="pdv-modal-overlay" style="display:none;">
    <div class="pdv-modal-box" style="max-width:480px;">
        <h4 class="fw-bold mb-3">👤 Cadastrar Cliente</h4>

        <div class="d-flex gap-3 mb-3">
            <div class="flex-shrink-0 text-center">
                <img id="cad-foto-preview" src="" alt="" style="display:none;width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #30363d;">
                <div id="cad-foto-placeholder" style="width:72px;height:72px;border-radius:50%;background:#161b22;border:1px dashed #30363d;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#484f58;">📷</div>
                <label class="btn btn-sm btn-outline-light mt-2" style="font-size:.72rem;">
                    Foto
                    <input type="file" id="cad-foto" accept="image/*" hidden onchange="PDV.previewFotoCadastro(this)">
                </label>
                <div class="form-text" style="font-size:.68rem;">Opcional</div>
            </div>
            <div class="flex-grow-1">
                <label class="form-label fw-semibold">Nome *</label>
                <input type="text" id="cad-nome" class="form-control mb-2" placeholder="Nome completo" required>
                <label class="form-label fw-semibold">CPF</label>
                <input type="text" id="cad-cpf" class="form-control" placeholder="000.000.000-00" maxlength="14">
                <div class="form-text" style="font-size:.7rem;">Opcional — necessário para pontuar nas próximas compras.</div>
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="form-label fw-semibold">Telefone</label>
                <input type="text" id="cad-telefone" class="form-control" placeholder="(00) 00000-0000">
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold">WhatsApp</label>
                <input type="text" id="cad-whatsapp" class="form-control" placeholder="(00) 00000-0000">
            </div>
        </div>

        <label class="form-label fw-semibold mt-1">Endereço</label>
        <div class="row g-2 mb-2">
            <div class="col-8"><input type="text" id="cad-endereco" class="form-control" placeholder="Rua/Av."></div>
            <div class="col-4"><input type="text" id="cad-numero" class="form-control" placeholder="Número"></div>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-4"><input type="text" id="cad-complemento" class="form-control" placeholder="Complemento"></div>
            <div class="col-4"><input type="text" id="cad-bairro" class="form-control" placeholder="Bairro"></div>
            <div class="col-4"><input type="text" id="cad-cidade" class="form-control" placeholder="Cidade"></div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-success flex-fill fw-bold" id="cad-btn-salvar" onclick="PDV.salvarCadastroCliente()">
                ✅ Cadastrar
            </button>
            <button class="btn btn-outline-secondary" onclick="PDV.fecharCadastroCliente()">Cancelar</button>
        </div>
    </div>
</div>

<!-- ── Modal Scanner de Câmera ───────────────────────────────────────────── -->
<div id="pdv-modal-scanner" class="pdv-modal-overlay" style="display:none;">
    <div class="pdv-modal-box" style="max-width:460px; padding:20px;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="fw-bold mb-0">📷 Scanner</h5>
            <button class="btn btn-sm btn-outline-secondary" onclick="fecharScannerPDV()">✕ Fechar</button>
        </div>
        <div class="pdv-scanner-wrap">
            <video id="pdv-scanner-video" class="pdv-scanner-video" playsinline muted></video>
            <div class="pdv-scanner-linha"></div>
        </div>
        <div id="pdv-scanner-status" class="mt-2 text-center small text-muted">
            Aponte a câmera para o código de barras ou QR Code…
        </div>
        <div id="pdv-scanner-ultimo" class="mt-1 text-center" style="display:none;">
            <span class="badge bg-success fs-6" id="pdv-scanner-codigo"></span>
        </div>
    </div>
</div>

<!-- ── Modal Fechar Caixa ─────────────────────────────────────────────── -->
<div id="modal-fechar-caixa" class="pdv-modal-overlay" style="display:none;">
    <div class="pdv-modal-box" style="max-width:380px;">
        <h4 class="fw-bold mb-3 text-center">🔒 Fechar Caixa</h4>
        <div id="fechar-alerta" class="alert d-none"></div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Total em Dinheiro no Caixa (R$)</label>
            <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="number" id="fechar-dinheiro" class="form-control"
                    placeholder="0,00" step="0.01" min="0">
            </div>
            <div class="form-text">Valor contado fisicamente no gaveta.</div>
        </div>
        <div class="mb-3">
            <?php if ($u['role'] !== 'caixa'): ?>
            <label class="form-label fw-semibold">Justificativa <span class="text-danger">*</span></label>
            <textarea id="fechar-obs" class="form-control" rows="2" maxlength="255" required
                placeholder="Você não é o operador deste caixa — explique o motivo de fechar em nome dele."></textarea>
            <div class="form-text">Obrigatório: fica registrado na auditoria.</div>
            <?php else: ?>
            <label class="form-label fw-semibold">Observações</label>
            <textarea id="fechar-obs" class="form-control" rows="2"
                placeholder="Opcional…" maxlength="255"></textarea>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-danger flex-fill fw-bold" onclick="confirmarFechamento()">
                🔒 Fechar Caixa
            </button>
            <button class="btn btn-outline-secondary"
                onclick="document.getElementById('modal-fechar-caixa').style.display='none'">
                Cancelar
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

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
const PDV_CAIXA_ID = <?= $caixa ? $caixa['id'] : 'null' ?>;
const PDV_LOJA_ID  = <?= $loja_id ?: 'null' ?>;
</script>

<?php if ($caixa): ?>
<?php
    $_pdv_js_dir = __DIR__ . '/../../public/js/pdv/';
    $_v = fn($f) => filemtime($_pdv_js_dir . $f);
?>
<script src="<?= BASE_PATH ?>/public/js/pdv/indexeddb.js?v=<?= $_v('indexeddb.js') ?>"></script>
<script src="<?= BASE_PATH ?>/public/js/pdv/sync.js?v=<?= $_v('sync.js') ?>"></script>
<script src="<?= BASE_PATH ?>/public/js/pdv/hardware.js?v=<?= $_v('hardware.js') ?>"></script>
<script src="<?= BASE_PATH ?>/public/js/pdv/pdv.js?v=<?= $_v('pdv.js') ?>"></script>
<script>
(async function () {
    // Salva caixa_id para uso em outras telas
    if (PDV_CAIXA_ID) sessionStorage.setItem('desffrut_caixa_id', PDV_CAIXA_ID);

    // Verifica e carrega snapshot do dia (se não houver produtos no IndexedDB)
    const info = await DesffrDatabase.info();
    if (info.prods === 0) {
        try {
            await DesffrSync.carregarCarga(PDV_LOJA_ID);
        } catch (e) {
            console.warn('[PDV] Falha ao carregar carga:', e.message);
        }
    }

    // Atualiza badge de pendentes + indicador offline/online
    async function atualizarBadgePendentes() {
        const inf    = await DesffrDatabase.info();
        const badge  = document.getElementById('pdv-pendentes-badge');
        const online = document.getElementById('pdv-status-online');
        if (badge) {
            badge.style.display = inf.pendentes > 0 ? '' : 'none';
            badge.textContent   = inf.pendentes + ' pendente(s)';
        }
        if (online) {
            const isOnline = navigator.onLine;
            online.textContent = isOnline ? '🟢 Online' : '🟡 Offline';
            online.className   = 'badge ' + (isOnline ? 'bg-success' : '');
            if (!isOnline) online.classList.add('offline');
            else           online.classList.remove('offline');
        }
    }

    // Init do PDV
    await PDV.init({ caixa_id: PDV_CAIXA_ID, loja_id: PDV_LOJA_ID });

    // ── Catálogo de cards com foto ─────────────────────────────────────────
    let _todosProds = [];
    let _catAtiva   = '';

    async function carregarCards() {
        try {
            _todosProds = await DesffrDatabase.listarProdutos?.() || [];
        } catch(_) { _todosProds = []; }

        if (!_todosProds.length) {
            // Fallback: busca via API se IndexedDB vazio
            try {
                const r = await fetch(`${APP.api}/produtos`, { headers: { Authorization: 'Bearer ' + APP.token } });
                const j = await r.json();
                _todosProds = (j.data || []).filter(p => p.ativo);
            } catch(_) {}
        }

        // Chips de categoria
        const cats = [...new Set(_todosProds.map(p => p.categoria || '').filter(Boolean))].sort();
        const catsEl = document.getElementById('pdv-cats');
        if (catsEl) {
            catsEl.innerHTML = '<div class="pdv-cat-chip ativo" data-cat="">🍃 Todos</div>' +
                cats.map(c => `<div class="pdv-cat-chip" data-cat="${esc(c)}">${esc(c)}</div>`).join('');
            catsEl.addEventListener('click', e => {
                const chip = e.target.closest('.pdv-cat-chip');
                if (!chip) return;
                catsEl.querySelectorAll('.pdv-cat-chip').forEach(x => x.classList.remove('ativo'));
                chip.classList.add('ativo');
                _catAtiva = chip.dataset.cat;
                renderCards(_catAtiva);
            });
        }

        renderCards('');
    }

    function renderCards(cat) {
        const grid = document.getElementById('pdv-prod-grid');
        if (!grid) return;
        const lista = cat ? _todosProds.filter(p => p.categoria === cat) : _todosProds;
        if (!lista.length) {
            grid.innerHTML = '<div style="color:#8b949e;grid-column:1/-1;text-align:center;padding:24px;">Nenhum produto.</div>';
            return;
        }
        grid.innerHTML = lista.map(p => {
            const foto = p.foto_url
                ? `<img src="${esc(p.foto_url)}" class="pdv-prod-foto" alt="${esc(p.nome)}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">`
                  + `<div class="pdv-prod-foto-placeholder" style="display:none;">🥦</div>`
                : `<div class="pdv-prod-foto-placeholder">🥦</div>`;
            const semEstoque = p.estoque_atual !== undefined && p.estoque_atual <= 0;
            return `
            <div class="pdv-prod-card ${semEstoque ? 'sem-estoque' : ''}"
                 data-id="${p.id}" data-codigo="${esc(p.codigo_interno||p.codigo_barras||p.id)}"
                 onclick="pdvAddCard(this)">
                ${foto}
                <div class="pdv-prod-info">
                    <div class="pdv-prod-nome">${esc(p.nome)}</div>
                    <div class="pdv-prod-preco">R$ ${fmt(p.preco_venda||0)}</div>
                    <div class="pdv-prod-unidade">${esc(p.unidade||'un')}</div>
                </div>
            </div>`;
        }).join('');
    }

    window.pdvAddCard = async function(el) {
        const codigo = el.dataset.codigo;
        el.classList.add('flash');
        setTimeout(() => el.classList.remove('flash'), 600);
        await PDV.adicionarItem(codigo);
    };

    await carregarCards();

    // ── Relógio discreto do header ─────────────────────────────────────────
    function atualizarRelogio() {
        const el = document.getElementById('pdv-clock');
        if (el) el.textContent = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
    atualizarRelogio();
    setInterval(atualizarRelogio, 30_000);

    // ── Inicia sync automático ─────────────────────────────────────────────
    DesffrSync.iniciar();
    setInterval(atualizarBadgePendentes, 15_000);
    atualizarBadgePendentes();
    window.addEventListener('online',  atualizarBadgePendentes);
    window.addEventListener('offline', atualizarBadgePendentes);

    // Fechar caixa
    document.getElementById('btn-fechar-caixa')?.addEventListener('click', () => {
        document.getElementById('modal-fechar-caixa').style.display = 'flex';
        document.getElementById('fechar-dinheiro').focus();
    });
})();

// Utilitários de card
function fmt(v) { return parseFloat(v||0).toFixed(2).replace('.',','); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Scanner câmera: abre modal, detecta código, fecha e busca produto ────────
let _scannerStream  = null;
let _scannerRef     = null;

async function abrirScannerPDV() {
    const modal = document.getElementById('pdv-modal-scanner');
    const video = document.getElementById('pdv-scanner-video');
    const status = document.getElementById('pdv-scanner-status');
    const ultiEl = document.getElementById('pdv-scanner-ultimo');

    if (!DesffrHardware.barcodeDisponivel()) {
        PDV.mostrarAlerta?.('BarcodeDetector não disponível neste navegador. Use Chrome 83+ ou Edge.', 'warning');
        // Exposição via interface pública limitada — usa alert como fallback
        alert('Leitor de câmera requer Chrome 83+ ou Edge.\nUse um leitor USB ou digite o código manualmente.');
        return;
    }

    modal.style.display = 'flex';
    ultiEl.style.display = 'none';
    status.textContent  = 'Iniciando câmera…';

    try {
        const { stream, scanner } = await DesffrHardware.iniciarScannerCamera(
            video,
            async (codigo, formato) => {
                // Exibe código detectado
                document.getElementById('pdv-scanner-codigo').textContent = codigo;
                ultiEl.style.display = '';
                status.textContent   = `✅ Detectado: ${formato?.toUpperCase() || 'código'}`;

                // Pequena pausa visual, depois fecha o modal e busca
                await new Promise(r => setTimeout(r, 600));
                fecharScannerPDV();
                const busca = document.getElementById('pdv-busca');
                if (busca) { busca.value = codigo; }
                await PDV.adicionarItem(codigo);
            }
        );
        _scannerStream = stream;
        _scannerRef    = scanner;
        status.textContent = 'Aponte para o código de barras ou QR Code…';
    } catch (e) {
        status.textContent = '❌ ' + e.message;
        setTimeout(() => fecharScannerPDV(), 3000);
    }
}

function fecharScannerPDV() {
    DesffrHardware.fecharScannerCamera(_scannerStream, _scannerRef);
    _scannerStream = null;
    _scannerRef    = null;
    const modal = document.getElementById('pdv-modal-scanner');
    if (modal) modal.style.display = 'none';
    const video = document.getElementById('pdv-scanner-video');
    if (video) video.srcObject = null;
    document.getElementById('pdv-busca')?.focus();
}

// ── Balança: lê peso e preenche o campo do modal ─────────────────────────────
async function lerPesoBalancaPDV() {
    const btn   = document.getElementById('pdv-btn-balanca');
    const input = document.getElementById('pdv-qty-valor');
    if (!btn || !input) return;
    try {
        btn.disabled    = true;
        btn.textContent = '⏳';
        const peso = await DesffrHardware.lerPesoBalanca(5000);
        input.value = peso.toFixed(3);
        input.focus();
    } catch (e) {
        alert('Erro ao ler balança:\n' + e.message);
    } finally {
        btn.disabled    = false;
        btn.textContent = '⚖️';
    }
}

// ── Impressão automática e reimpressão ────────────────────────────────────────
// pdv.js chama PDV.imprimirCupomHW() após cada venda (se QZ Tray disponível)
// O método é injetado via extensão do módulo PDV aqui:
document.addEventListener('desffrut:venda:concluida', async (e) => {
    const { cupom, totais, pontos_ganhos } = e.detail;
    const reimpBtn = document.getElementById('pdv-btn-reimprimir');
    if (reimpBtn) {
        reimpBtn.style.display = '';
        // Guarda cupom para reimprimir
        reimpBtn._cupom        = cupom;
        reimpBtn._totais       = totais;
        reimpBtn._pontos       = pontos_ganhos;
    }
    // Impressão automática se a extensão (Native Messaging) estiver instalada e a impressora configurada
    if (await DesffrHardware.extensaoInstalada() && DesffrHardware.getConfig().printer) {
        try {
            // Monta mapa de nomes a partir do IndexedDB
            const nomesMap = {};
            for (const item of cupom.itens) {
                const prod = await DesffrDatabase.getProduto(item.produto_id);
                if (prod) nomesMap[item.produto_id] = { nome: prod.nome, unidade_medida: prod.unidade_medida };
            }
            await DesffrHardware.imprimirCupom(cupom, totais, pontos_ganhos, nomesMap);
        } catch (e) {
            console.warn('[Hardware] Falha na impressão automática:', e.message);
        }
    }
});

// Extensão do módulo PDV: expõe reimpressão a partir do botão
PDV.reimprimirCupom = async function () {
    const btn = document.getElementById('pdv-btn-reimprimir');
    if (!btn?._cupom) return;
    try {
        const nomesMap = {};
        for (const item of btn._cupom.itens) {
            const prod = await DesffrDatabase.getProduto(item.produto_id);
            if (prod) nomesMap[item.produto_id] = { nome: prod.nome, unidade_medida: prod.unidade_medida };
        }
        await DesffrHardware.imprimirCupom(btn._cupom, btn._totais, btn._pontos, nomesMap);
    } catch (e) {
        alert('Erro ao reimprimir:\n' + e.message);
    }
};

const EH_OPERADOR_NORMAL = <?= json_encode($u['role'] === 'caixa') ?>;

async function confirmarFechamento() {
    const dinheiro     = parseFloat(document.getElementById('fechar-dinheiro').value) || 0;
    const obs          = document.getElementById('fechar-obs').value.trim();
    const alertEl      = document.getElementById('fechar-alerta');
    const btnFechar     = document.querySelector('#modal-fechar-caixa .btn-danger');
    const btnCancelar   = document.querySelector('#modal-fechar-caixa .btn-outline-secondary');
    const campoDinheiro = document.getElementById('fechar-dinheiro');
    const campoObs      = document.getElementById('fechar-obs');

    if (!EH_OPERADOR_NORMAL && !obs) {
        alertEl.textContent = 'Informe a justificativa: você não é o operador deste caixa.';
        alertEl.className   = 'alert alert-danger';
        alertEl.classList.remove('d-none');
        return;
    }

    btnFechar.disabled     = true;
    btnFechar.textContent  = 'Fechando…';

    try {
        const resp = await fetch(`${APP.api}/caixas/${PDV_CAIXA_ID}/fechar`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + APP.token },
            body:    JSON.stringify({ total_dinheiro: dinheiro, justificativa: obs }),
        });
        const json = await resp.json();
        if (json.status === 'ok') {
            // Impressão do resumo de fechamento (se a extensão Native Messaging estiver instalada)
            if (await DesffrHardware.extensaoInstalada() && DesffrHardware.getConfig().printer) {
                try {
                    const kpi = json.data?.kpi || {};
                    await DesffrHardware.imprimirFechamento({
                        operador:          '<?= htmlspecialchars($u['nome'] ?? '') ?>',
                        caixa_id:          PDV_CAIXA_ID,
                        turno:             '<?= $caixa ? date('H:i', strtotime($caixa['aberto_em'])) : '' ?>',
                        fundo_troco:       <?= $caixa ? $caixa['fundo_troco'] : 0 ?>,
                        total_vendas:      kpi.total_vendas      || 0,
                        qtd_vendas:        kpi.qtd_vendas        || 0,
                        total_sangrias:    kpi.total_sangrias    || 0,
                        total_suprimentos: kpi.total_suprimentos || 0,
                        saldo_final:       kpi.saldo_final       || 0,
                    });
                } catch (_) { /* Não bloqueia o fechamento se impressão falhar */ }
            }
            sessionStorage.removeItem('desffrut_caixa_id');

            // ── Feedback de sucesso antes de sair do PDV ──
            alertEl.textContent = '✅ Caixa fechado com sucesso! Redirecionando para o painel…';
            alertEl.className   = 'alert alert-success';
            alertEl.classList.remove('d-none');
            campoDinheiro.disabled = true;
            campoObs.disabled      = true;
            if (btnCancelar) btnCancelar.disabled = true;
            btnFechar.textContent = '✅ Fechado';

            setTimeout(() => {
                window.location.href = `${APP.base}/dashboard`;
            }, 3500);
        } else {
            throw new Error(json.message);
        }
    } catch (e) {
        alertEl.textContent = e.message;
        alertEl.className   = 'alert alert-danger';
        alertEl.classList.remove('d-none');
        btnFechar.disabled    = false;
        btnFechar.textContent = '🔒 Fechar Caixa';
    }
}
</script>
<?php endif; ?>

</body>
</html>
