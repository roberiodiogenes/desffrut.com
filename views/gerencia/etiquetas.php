<?php
/**
 * Desffrut — Módulo de Etiquetas Térmicas (Categoria 18)
 * Rota: /gerencia/etiquetas
 * Gera etiquetas de expositor (10×5cm) e etiquetas de produto adesivas (Code 128 + QR Code).
 * Impressão via QZ Tray ou window.print().
 */
$roles_permitidos = ['gerente', 'super_admin'];
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$u = usuario_logado();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title>Etiquetas — <?= NOME_SISTEMA ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">

    <!-- QZ Tray (impressora térmica) -->
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js"></script>
    <!-- JsBarcode (Code 128) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
    <!-- QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        body { background:#f4f6fa; font-family:'Segoe UI',sans-serif; }

        /* ── Header ── */
        .etq-header { background:#1b5e20; color:#fff; padding:14px 24px;
                      display:flex; align-items:center; gap:16px; }
        .etq-header a { color:rgba(255,255,255,.75); font-size:.85rem; text-decoration:none; }
        .etq-header a:hover { color:#fff; }

        /* ── Layout ── */
        .etq-body { display:grid; grid-template-columns:340px 1fr; gap:24px;
                    max-width:1200px; margin:24px auto; padding:0 16px; }
        @media(max-width:768px) { .etq-body { grid-template-columns:1fr; } }

        /* ── Painel esquerdo: configuração ── */
        .painel-config { background:#fff; border-radius:12px; border:1px solid #e0e0e0;
                         padding:20px; position:sticky; top:16px; }
        .painel-config h6 { font-weight:700; color:#1b5e20; margin-bottom:12px; }

        /* ── Lista de produtos ── */
        .produto-item { display:flex; align-items:center; gap:10px; padding:8px 10px;
                        border-radius:8px; cursor:pointer; transition:background .15s; }
        .produto-item:hover { background:#f1f8e9; }
        .produto-item.selecionado { background:#e8f5e9; border:1px solid #a5d6a7; }
        .produto-item .cod { font-size:.72rem; color:#999; font-family:monospace; }
        .produto-item .preco { font-size:.85rem; font-weight:700; color:#2e7d32; margin-left:auto; }
        .prod-check { width:18px; height:18px; cursor:pointer; }

        /* ── Preview de etiquetas ── */
        .painel-preview { background:#fff; border-radius:12px; border:1px solid #e0e0e0;
                          padding:20px; }
        .preview-grid { display:flex; flex-wrap:wrap; gap:12px; }

        /* ── Etiqueta Expositor (10×5cm aprox.) ── */
        .etq-expositor {
            width: 280px; height: 140px;
            border: 2px dashed #c8e6c9; border-radius: 8px;
            padding: 10px 12px;
            display: flex; flex-direction: column; justify-content: space-between;
            background: #fff; position: relative; overflow: hidden;
        }
        .etq-expositor .etq-loja { font-size: .65rem; color: #999; text-transform:uppercase; letter-spacing:.5px; }
        .etq-expositor .etq-nome { font-size: 1rem; font-weight: 800; color: #1b1b1b;
                                   line-height: 1.2; max-height: 2.5em; overflow: hidden; }
        .etq-expositor .etq-preco { font-size: 1.9rem; font-weight: 900; color: #1b5e20;
                                    line-height: 1; }
        .etq-expositor .etq-unidade { font-size: .72rem; color: #555; margin-top:2px; }
        .etq-expositor .etq-qr { position: absolute; right: 10px; bottom: 10px; }
        .etq-expositor .etq-qr canvas { width: 52px !important; height: 52px !important; }

        /* ── Etiqueta Produto Adesiva (6×4cm aprox.) ── */
        .etq-produto {
            width: 220px; height: 130px;
            border: 2px dashed #bbdefb; border-radius: 6px;
            padding: 8px 10px;
            display: flex; flex-direction: column; justify-content: space-between;
            background: #fff; overflow: hidden;
        }
        .etq-produto .epn { font-size: .85rem; font-weight: 700; color: #1b1b1b;
                            line-height:1.2; max-height:2.4em; overflow:hidden; }
        .etq-produto .epp { font-size: 1.1rem; font-weight: 800; color: #1b5e20; }
        .etq-produto .epv { font-size: .7rem; color: #c62828; }
        .etq-produto .ep-bottom { display:flex; align-items:flex-end; gap:8px; }
        .etq-produto .ep-bar svg { width: 140px; height: 40px; }

        /* ── Área de impressão ── */
        @media print {
            body * { visibility: hidden; }
            #area-impressao, #area-impressao * { visibility: visible; }
            #area-impressao { position: fixed; top:0; left:0; width:100%; }
            .etq-expositor, .etq-produto { border-style: solid; border-color: #000; }
        }
    </style>
</head>
<body>

<div class="etq-header">
    <span style="font-size:1.4rem;">🏷️</span>
    <div>
        <div style="font-weight:700;">Etiquetas de Produtos</div>
        <div style="font-size:.78rem;opacity:.75;">Expositor e etiqueta adesiva com código de barras</div>
    </div>
    <div class="ms-auto">
        <a href="<?= BASE_PATH ?>/gerencia/produtos">← Produtos</a>
    </div>
</div>

<div class="etq-body">

    <!-- ── Painel de configuração ─────────────────────────────────────────── -->
    <div class="painel-config">
        <h6>⚙️ Configurações</h6>

        <!-- Tipo de etiqueta -->
        <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Tipo</label>
            <div class="d-flex gap-2">
                <label class="btn btn-sm btn-outline-success flex-fill text-center">
                    <input type="radio" name="tipo-etq" value="expositor" checked class="d-none">
                    🏷 Expositor
                </label>
                <label class="btn btn-sm btn-outline-primary flex-fill text-center">
                    <input type="radio" name="tipo-etq" value="produto" class="d-none">
                    📦 Produto
                </label>
            </div>
        </div>

        <!-- Validade (só para etiqueta de produto) -->
        <div class="mb-3" id="campo-validade">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Validade</label>
            <input type="date" id="etq-validade" class="form-control form-control-sm">
            <div class="form-text">Deixe em branco se não se aplica.</div>
        </div>

        <!-- Quantidade de cópias -->
        <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Cópias por produto</label>
            <input type="number" id="etq-copias" class="form-control form-control-sm"
                   value="1" min="1" max="100">
        </div>

        <hr>
        <h6>📦 Produtos selecionados
            <span id="badge-selecionados" class="badge bg-success rounded-pill ms-1">0</span>
        </h6>

        <!-- Busca de produto -->
        <input type="text" id="busca-produto" class="form-control form-control-sm mb-2"
               placeholder="Buscar produto…">

        <div id="lista-produtos" style="max-height:260px;overflow-y:auto;">
            <div class="text-center py-3 text-muted small">Carregando…</div>
        </div>

        <hr>
        <button class="btn btn-success w-100" onclick="gerarPreview()">
            👁 Gerar Preview
        </button>
        <button class="btn btn-outline-dark w-100 mt-2" onclick="window.print()">
            🖨 Imprimir
        </button>
    </div>

    <!-- ── Preview ─────────────────────────────────────────────────────────── -->
    <div class="painel-preview">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0 fw-bold">Preview de Impressão</h6>
            <span class="badge bg-secondary" id="badge-total-etq">0 etiquetas</span>
        </div>
        <div id="area-impressao">
            <div class="preview-grid" id="preview-grid">
                <div class="text-muted small py-4 text-center w-100">
                    Selecione produtos e clique em "Gerar Preview".
                </div>
            </div>
        </div>
    </div>

</div>

<script>
const API_BASE = '<?= BASE_PATH ?>/api/v1';
const LOJA_NOME = '<?= htmlspecialchars(NOME_SISTEMA) ?>';
let todosOsProdutos = [];
let selecionados = new Set();

// ── Inicialização ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await carregarProdutos();

    // Tipo de etiqueta → mostra/oculta campo validade
    document.querySelectorAll('input[name="tipo-etq"]').forEach(r => {
        r.addEventListener('change', () => {
            const isProduto = r.value === 'produto';
            document.getElementById('campo-validade').style.display = isProduto ? '' : 'none';
            // Estilo dos botões radio
            document.querySelectorAll('input[name="tipo-etq"]').forEach(rb => {
                rb.parentElement.classList.toggle('btn-success', rb.value === 'expositor' && !isProduto);
                rb.parentElement.classList.toggle('btn-outline-success', rb.value === 'expositor' && isProduto);
                rb.parentElement.classList.toggle('btn-primary', rb.value === 'produto' && isProduto);
                rb.parentElement.classList.toggle('btn-outline-primary', rb.value === 'produto' && !isProduto);
            });
        });
    });

    // Busca de produto
    document.getElementById('busca-produto').addEventListener('input', e => {
        renderListaProdutos(e.target.value.toLowerCase());
    });
});

async function carregarProdutos() {
    try {
        const r = await fetch(`${API_BASE}/produtos`);
        const j = await r.json();
        todosOsProdutos = (j.data || []).filter(p => p.ativo);
        renderListaProdutos('');
    } catch(e) {
        document.getElementById('lista-produtos').innerHTML =
            '<div class="text-danger small p-2">Erro ao carregar produtos.</div>';
    }
}

function renderListaProdutos(filtro) {
    const lista = document.getElementById('lista-produtos');
    const prods = filtro
        ? todosOsProdutos.filter(p => p.nome.toLowerCase().includes(filtro) ||
                                      (p.codigo_interno||'').toLowerCase().includes(filtro))
        : todosOsProdutos;

    if (!prods.length) {
        lista.innerHTML = '<div class="text-muted small p-2">Nenhum produto encontrado.</div>';
        return;
    }

    lista.innerHTML = prods.map(p => `
        <div class="produto-item ${selecionados.has(p.id) ? 'selecionado' : ''}"
             onclick="toggleSelecionado(${p.id})">
            <input type="checkbox" class="prod-check"
                   ${selecionados.has(p.id) ? 'checked' : ''} onclick="event.stopPropagation()">
            <div style="flex:1;min-width:0;">
                <div style="font-size:.88rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(p.nome)}</div>
                <div class="cod">${esc(p.codigo_interno||'')} | ${p.unidade}</div>
            </div>
            <div class="preco">R$ ${fmt(p.preco_venda||0)}</div>
        </div>
    `).join('');
}

function toggleSelecionado(id) {
    if (selecionados.has(id)) selecionados.delete(id);
    else selecionados.add(id);
    const filtro = document.getElementById('busca-produto').value.toLowerCase();
    renderListaProdutos(filtro);
    document.getElementById('badge-selecionados').textContent = selecionados.size;
}

// ── Gerar Preview ─────────────────────────────────────────────────────────────
function gerarPreview() {
    if (!selecionados.size) {
        alert('Selecione ao menos um produto.');
        return;
    }
    const tipo    = document.querySelector('input[name="tipo-etq"]:checked').value;
    const copias  = Math.max(1, parseInt(document.getElementById('etq-copias').value) || 1);
    const validade = document.getElementById('etq-validade').value;
    const grid    = document.getElementById('preview-grid');
    grid.innerHTML = '';

    const prods = todosOsProdutos.filter(p => selecionados.has(p.id));
    let totalEtq = 0;

    prods.forEach(p => {
        for (let c = 0; c < copias; c++) {
            const wrapper = document.createElement('div');
            if (tipo === 'expositor') {
                wrapper.innerHTML = htmlEtqExpositor(p);
            } else {
                wrapper.innerHTML = htmlEtqProduto(p, validade);
            }
            grid.appendChild(wrapper);
            totalEtq++;

            // Gera QR code
            const qrEl = wrapper.querySelector('.qr-target');
            if (qrEl) {
                const qrData = tipo === 'expositor'
                    ? `${p.nome} | R$${fmt(p.preco_venda||0)} ${p.unidade}`
                    : JSON.stringify({nome:p.nome, preco:'R$'+fmt(p.preco_venda||0), validade:validade||'S/D', codigo:p.codigo_interno});
                new QRCode(qrEl, { text: qrData, width: 52, height: 52, correctLevel: QRCode.CorrectLevel.M });
            }

            // Gera barcode Code 128 (apenas etiqueta produto)
            const barEl = wrapper.querySelector('.bar-target');
            if (barEl && p.codigo_interno) {
                try {
                    JsBarcode(barEl, p.codigo_interno, {
                        format: 'CODE128', lineColor: '#000', width: 1.2, height: 36,
                        displayValue: true, fontSize: 10, margin: 2
                    });
                } catch(_) {}
            }
        }
    });

    document.getElementById('badge-total-etq').textContent = `${totalEtq} etiqueta${totalEtq !== 1 ? 's' : ''}`;
}

function htmlEtqExpositor(p) {
    const preco = p.preco_venda ? `R$ ${fmt(p.preco_venda)}` : 'Consulte';
    const unidade = p.unidade === 'kg' ? '/ kg' : '/ unidade';
    return `
    <div class="etq-expositor">
        <div>
            <div class="etq-loja">${esc(LOJA_NOME)}</div>
            <div class="etq-nome">${esc(p.nome)}</div>
        </div>
        <div>
            <div class="etq-preco">${preco}</div>
            <div class="etq-unidade">${unidade}</div>
        </div>
        <div class="etq-qr"><div class="qr-target"></div></div>
    </div>`;
}

function htmlEtqProduto(p, validade) {
    const preco = p.preco_venda ? `R$ ${fmt(p.preco_venda)}` : 'S/P';
    const unidade = p.unidade === 'kg' ? '/kg' : '/un';
    const val = validade ? fmtData(validade) : '';
    return `
    <div class="etq-produto">
        <div>
            <div class="epn">${esc(p.nome)}</div>
            <div class="epp">${preco} <small style="font-size:.7rem;font-weight:400;color:#555;">${unidade}</small></div>
            ${val ? `<div class="epv">Vál: ${val}</div>` : ''}
        </div>
        <div class="ep-bottom">
            <svg class="bar-target"></svg>
            <div class="qr-target" style="flex-shrink:0;"></div>
        </div>
    </div>`;
}

// ── Utilitários ───────────────────────────────────────────────────────────────
function fmt(v) { return parseFloat(v||0).toFixed(2).replace('.',','); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmtData(iso) {
    if (!iso) return '';
    const [y,m,d] = iso.split('-');
    return `${d}/${m}/${y}`;
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
