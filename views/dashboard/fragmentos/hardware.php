<?php
/**
 * Desffrut — Fragmento Dashboard: Hardware Local (Fase 5 — Revisão 2.0)
 *
 * Gerencia periféricos via Native Messaging (extensão Chrome/Edge + host Python).
 * Suporte a 3 tipos de impressora: cupom, etiqueta (Tomate MKV-006), jato de tinta.
 * Mantém: balança serial (Web Serial API) e leitor de código de barras (câmera).
 */
?>
<style data-frag="hardware-v2">

/* ── Reset / Base ────────────────────────────────────────────── */
.hw2-root { --hw-radius: 14px; --hw-gap: 16px; padding: 24px; }

/* ── Header ─────────────────────────────────────────────────── */
.hw2-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
}
.hw2-header-title { display: flex; align-items: center; gap: 12px; }
.hw2-header-title h4 { font-size: 1.25rem; font-weight: 700; margin: 0; color: #1a2332; }
.hw2-header-title small { display: block; font-size: .78rem; color: #8a94a6; margin-top: 2px; }
.hw2-header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Alerta global ───────────────────────────────────────────── */
.hw2-alerta {
    display: none; border-radius: 10px;
    padding: 12px 16px; margin-bottom: 20px;
    font-size: .88rem; font-weight: 500;
}
.hw2-alerta.show { display: flex; align-items: center; gap: 10px; }
.hw2-alerta.success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
.hw2-alerta.danger  { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
.hw2-alerta.warning { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
.hw2-alerta.info    { background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; }

/* ── Status da Extensão (banner superior) ───────────────────── */
.hw2-ext-banner {
    border-radius: var(--hw-radius); padding: 16px 20px;
    margin-bottom: 24px; display: flex; align-items: center;
    gap: 14px; flex-wrap: wrap;
}
.hw2-ext-banner.ok      { background: #f0fdf4; border: 1.5px solid #86efac; }
.hw2-ext-banner.warn    { background: #fffbeb; border: 1.5px solid #fde68a; }
.hw2-ext-banner.err     { background: #fef2f2; border: 1.5px solid #fca5a5; }
.hw2-ext-banner.loading { background: #f8fafc; border: 1.5px solid #e2e8f0; }
.hw2-ext-icon { font-size: 1.5rem; line-height: 1; }
.hw2-ext-info { flex: 1; min-width: 180px; }
.hw2-ext-info strong { display: block; font-size: .95rem; margin-bottom: 2px; }
.hw2-ext-info span { font-size: .8rem; color: #64748b; }
.hw2-ext-badge {
    padding: 4px 12px; border-radius: 20px; font-size: .78rem; font-weight: 600;
}
.hw2-ext-badge.ok   { background: #dcfce7; color: #15803d; }
.hw2-ext-badge.warn { background: #fef9c3; color: #a16207; }
.hw2-ext-badge.err  { background: #fee2e2; color: #b91c1c; }

/* ── Grid de impressoras ─────────────────────────────────────── */
.hw2-printers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--hw-gap);
    margin-bottom: 24px;
}

/* ── Card de impressora ──────────────────────────────────────── */
.hw2-printer-card {
    background: #fff; border: 1.5px solid #e8ecf2;
    border-radius: var(--hw-radius); overflow: hidden;
    transition: box-shadow .2s, border-color .2s;
}
.hw2-printer-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.07); border-color: #c9d4e4; }

.hw2-printer-card-head {
    padding: 16px 20px 12px;
    display: flex; align-items: center; gap: 12px;
    border-bottom: 1px solid #f0f2f7;
}
.hw2-printer-type-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.hw2-printer-type-icon.cupom    { background: #eff6ff; }
.hw2-printer-type-icon.etiqueta { background: #f0fdf4; }
.hw2-printer-type-icon.inkjet   { background: #faf5ff; }

.hw2-printer-title { flex: 1; min-width: 0; }
.hw2-printer-title h6 { font-weight: 700; margin: 0 0 2px; font-size: .95rem; color: #1a2332; }
.hw2-printer-title p  { font-size: .77rem; color: #8a94a6; margin: 0; }

.hw2-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    transition: background .3s;
}
.hw2-dot.ok   { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.15); }
.hw2-dot.warn { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.15); }
.hw2-dot.err  { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.15); }
.hw2-dot.off  { background: #cbd5e1; }

.hw2-printer-card-body { padding: 16px 20px; }

/* Campo de nome da impressora */
.hw2-field-label { font-size: .77rem; font-weight: 600; color: #64748b; margin-bottom: 5px; }
.hw2-printer-name-row { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; }
.hw2-printer-name-row input { flex: 1; }

/* Lista de impressoras detectadas */
.hw2-detected-list {
    max-height: 140px; overflow-y: auto; margin-top: 10px;
    border: 1px solid #e8ecf2; border-radius: 8px; background: #f8fafc;
}
.hw2-detected-item {
    padding: 8px 12px; font-size: .84rem; cursor: pointer;
    border-bottom: 1px solid #f0f2f7; display: flex; align-items: center; gap: 8px;
    transition: background .1s;
}
.hw2-detected-item:last-child { border-bottom: none; }
.hw2-detected-item:hover { background: #f0f9ff; }
.hw2-detected-item.selected { background: #e0f2fe; font-weight: 600; color: #0369a1; }
.hw2-detected-item .hw2-detected-icon { font-size: 1rem; }

/* Opções extras por tipo (papel, tamanho etiqueta) */
.hw2-printer-options {
    background: #f8fafc; border-radius: 8px;
    padding: 12px 14px; margin-bottom: 12px;
    border: 1px solid #f0f2f7;
}
.hw2-printer-options .row { --bs-gutter-x: 8px; }

/* Ações do card */
.hw2-card-actions { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 4px; }
.hw2-btn-detect {
    background: none; border: 1.5px dashed #cbd5e1; border-radius: 8px;
    color: #64748b; font-size: .82rem; padding: 7px 14px; cursor: pointer;
    transition: all .15s; width: 100%; margin-top: 8px; text-align: center;
}
.hw2-btn-detect:hover { border-color: #6366f1; color: #6366f1; background: #f5f3ff; }

/* ── Cards secundários (balança, scanner) ───────────────────── */
.hw2-secondary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--hw-gap);
    margin-bottom: 24px;
}
.hw2-secondary-card {
    background: #fff; border: 1.5px solid #e8ecf2;
    border-radius: var(--hw-radius); padding: 20px;
}
.hw2-secondary-card h6 {
    font-weight: 700; color: #1a2332; margin-bottom: 4px;
    display: flex; align-items: center; gap: 8px; font-size: .95rem;
}
.hw2-secondary-card .hw2-sub { font-size: .78rem; color: #8a94a6; margin-bottom: 16px; }

/* Scanner de câmera */
.hw2-scanner-wrap {
    position: relative; background: #0f172a; border-radius: 10px;
    overflow: hidden; min-height: 180px;
}
.hw2-scanner-line {
    position: absolute; left: 8%; right: 8%; height: 2px;
    background: linear-gradient(90deg, transparent, #22c55e, transparent);
    box-shadow: 0 0 10px rgba(34,197,94,.7);
    animation: hw2-sweep 1.8s ease-in-out infinite; pointer-events: none;
}
@keyframes hw2-sweep { 0%,100% { top:12%; } 50% { top:82%; } }

/* ── Painel de instalação da extensão ───────────────────────── */
.hw2-install-panel {
    background: #fff; border: 1.5px solid #e8ecf2;
    border-radius: var(--hw-radius); padding: 24px;
    margin-bottom: 24px;
}
.hw2-install-steps { counter-reset: hw-step; }
.hw2-install-step {
    display: flex; gap: 14px; align-items: flex-start; margin-bottom: 16px;
}
.hw2-install-step::before {
    counter-increment: hw-step;
    content: counter(hw-step);
    width: 28px; height: 28px; border-radius: 50%;
    background: #6366f1; color: #fff; font-weight: 700; font-size: .85rem;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.hw2-install-step p { margin: 0; font-size: .88rem; color: #374151; line-height: 1.5; }
.hw2-install-step code {
    background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 5px;
    padding: 1px 6px; font-size: .82rem; color: #7c3aed;
}

/* ── Diagnóstico ─────────────────────────────────────────────── */
.hw2-diag-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
}
.hw2-diag-item {
    background: #f8fafc; border: 1px solid #f0f2f7; border-radius: 10px;
    padding: 12px 14px; display: flex; align-items: center; gap: 10px;
}
.hw2-diag-item .hw2-dot { flex-shrink: 0; }
.hw2-diag-label { font-size: .82rem; color: #374151; line-height: 1.3; }
.hw2-diag-label strong { display: block; font-weight: 600; }

</style>

<div class="hw2-root">

    <!-- ── Header ──────────────────────────────────────────────────────── -->
    <div class="hw2-header">
        <div class="hw2-header-title">
            <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">🖨️</div>
            <div>
                <h4>Hardware Local</h4>
                <small>Impressoras, balança e leitor — configurados por estação</small>
            </div>
        </div>
        <div class="hw2-header-actions">
            <button class="btn btn-sm btn-outline-secondary" onclick="hw2Diagnostico()">
                🔍 Diagnóstico
            </button>
            <button class="btn btn-sm btn-outline-primary" onclick="hw2AtualizarStatus()">
                ↺ Atualizar Status
            </button>
        </div>
    </div>

    <!-- ── Alerta global ────────────────────────────────────────────────── -->
    <div class="hw2-alerta" id="hw2-alerta"></div>

    <!-- ── Banner de status da extensão ────────────────────────────────── -->
    <div class="hw2-ext-banner loading" id="hw2-ext-banner">
        <div class="hw2-ext-icon">🔌</div>
        <div class="hw2-ext-info">
            <strong>Verificando extensão Desffrut…</strong>
            <span>Aguarde alguns segundos</span>
        </div>
        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
    </div>

    <!-- ── Grid de Impressoras ──────────────────────────────────────────── -->
    <div class="hw2-printers-grid">

        <!-- Impressora de Cupom -->
        <div class="hw2-printer-card" id="hw2-card-cupom">
            <div class="hw2-printer-card-head">
                <div class="hw2-printer-type-icon cupom">🧾</div>
                <div class="hw2-printer-title">
                    <h6>Impressora de Cupom</h6>
                    <p>ESC/POS · 80mm ou 58mm · Venda, sangria, fechamento</p>
                </div>
                <div class="hw2-dot off" id="hw2-dot-cupom" title="Não configurada"></div>
            </div>
            <div class="hw2-printer-card-body">

                <div class="hw2-field-label">Nome da impressora (Windows)</div>
                <div class="hw2-printer-name-row">
                    <input type="text" id="hw2-cupom-nome" class="form-control form-control-sm"
                        placeholder="Ex: BEMATECH MP-4200, Elgin i9…" autocomplete="off">
                </div>

                <div class="hw2-printer-options">
                    <div class="hw2-field-label mb-2">Largura do Papel</div>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="hw2-cupom-papel"
                                id="hw2-cupom-80" value="80" checked>
                            <label class="form-check-label" for="hw2-cupom-80">80mm (48 cols)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="hw2-cupom-papel"
                                id="hw2-cupom-58" value="58">
                            <label class="form-check-label" for="hw2-cupom-58">58mm (32 cols)</label>
                        </div>
                    </div>
                </div>

                <div id="hw2-cupom-list-wrap" style="display:none">
                    <div class="hw2-field-label">Impressoras detectadas — clique para selecionar:</div>
                    <div class="hw2-detected-list" id="hw2-cupom-list"></div>
                </div>

                <button class="hw2-btn-detect" onclick="hw2Detectar('cupom')">
                    🔍 Detectar impressoras instaladas
                </button>

                <div class="hw2-card-actions mt-3">
                    <button class="btn btn-success btn-sm" onclick="hw2Salvar('cupom')">
                        💾 Salvar
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="hw2Testar('cupom')">
                        🖨️ Imprimir Teste
                    </button>
                </div>
            </div>
        </div>

        <!-- Impressora de Etiqueta -->
        <div class="hw2-printer-card" id="hw2-card-etiqueta">
            <div class="hw2-printer-card-head">
                <div class="hw2-printer-type-icon etiqueta">🏷️</div>
                <div class="hw2-printer-title">
                    <h6>Impressora de Etiqueta</h6>
                    <p>TSPL · Tomate MKV-006 · Envio marketplace, produto</p>
                </div>
                <div class="hw2-dot off" id="hw2-dot-etiqueta" title="Não configurada"></div>
            </div>
            <div class="hw2-printer-card-body">

                <div class="hw2-field-label">Nome da impressora (Windows)</div>
                <div class="hw2-printer-name-row">
                    <input type="text" id="hw2-etiqueta-nome" class="form-control form-control-sm"
                        placeholder="Ex: Tomate MKV-006, ZDesigner…" autocomplete="off">
                </div>

                <div class="hw2-printer-options">
                    <div class="hw2-field-label mb-2">Tamanho da etiqueta (mm)</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" style="font-size:.75rem;color:#64748b;margin-bottom:3px">Largura</label>
                            <input type="number" id="hw2-etiqueta-larg" class="form-control form-control-sm"
                                value="100" min="40" max="200" step="5">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:.75rem;color:#64748b;margin-bottom:3px">Altura</label>
                            <input type="number" id="hw2-etiqueta-alt" class="form-control form-control-sm"
                                value="150" min="30" max="300" step="5">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <button class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:3px 8px"
                            onclick="hw2SetEtiqueta(100,150)">100×150 (ML)</button>
                        <button class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:3px 8px"
                            onclick="hw2SetEtiqueta(100,50)">100×50 (Produto)</button>
                        <button class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:3px 8px"
                            onclick="hw2SetEtiqueta(80,40)">80×40 (Mini)</button>
                    </div>
                </div>

                <div id="hw2-etiqueta-list-wrap" style="display:none">
                    <div class="hw2-field-label">Impressoras detectadas:</div>
                    <div class="hw2-detected-list" id="hw2-etiqueta-list"></div>
                </div>

                <button class="hw2-btn-detect" onclick="hw2Detectar('etiqueta')">
                    🔍 Detectar impressoras instaladas
                </button>

                <div class="hw2-card-actions mt-3">
                    <button class="btn btn-success btn-sm" onclick="hw2Salvar('etiqueta')">
                        💾 Salvar
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="hw2Testar('etiqueta')">
                        🏷️ Imprimir Teste
                    </button>
                </div>
            </div>
        </div>

        <!-- Impressora Jato de Tinta -->
        <div class="hw2-printer-card" id="hw2-card-inkjet">
            <div class="hw2-printer-card-head">
                <div class="hw2-printer-type-icon inkjet">🖨️</div>
                <div class="hw2-printer-title">
                    <h6>Jato de Tinta / Laser</h6>
                    <p>Padrão Windows · Relatórios, pedidos de compra, PDF</p>
                </div>
                <div class="hw2-dot off" id="hw2-dot-inkjet" title="Não configurada"></div>
            </div>
            <div class="hw2-printer-card-body">

                <div class="hw2-field-label">Nome da impressora (Windows)</div>
                <div class="hw2-printer-name-row">
                    <input type="text" id="hw2-inkjet-nome" class="form-control form-control-sm"
                        placeholder="Ex: HP DeskJet 2700, Canon PIXMA…" autocomplete="off">
                </div>

                <div class="hw2-printer-options">
                    <div class="hw2-field-label mb-2">Formato de papel padrão</div>
                    <select id="hw2-inkjet-papel" class="form-select form-select-sm">
                        <option value="A4">A4 (210×297mm)</option>
                        <option value="A5">A5 (148×210mm)</option>
                        <option value="Letter">Carta / Letter</option>
                    </select>
                </div>

                <div id="hw2-inkjet-list-wrap" style="display:none">
                    <div class="hw2-field-label">Impressoras detectadas:</div>
                    <div class="hw2-detected-list" id="hw2-inkjet-list"></div>
                </div>

                <button class="hw2-btn-detect" onclick="hw2Detectar('inkjet')">
                    🔍 Detectar impressoras instaladas
                </button>

                <div class="hw2-card-actions mt-3">
                    <button class="btn btn-success btn-sm" onclick="hw2Salvar('inkjet')">
                        💾 Salvar
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="hw2Testar('inkjet')">
                        🖨️ Imprimir Teste
                    </button>
                </div>
            </div>
        </div>

    </div><!-- /printers-grid -->

    <!-- ── Periféricos secundários ──────────────────────────────────────── -->
    <div class="hw2-secondary-grid">

        <!-- Balança -->
        <div class="hw2-secondary-card">
            <h6>
                <span style="width:32px;height:32px;border-radius:8px;background:#fff7ed;display:inline-flex;align-items:center;justify-content:center">⚖️</span>
                Balança Comercial
            </h6>
            <p class="hw2-sub">Toledo, Filizola · RS-232 / USB-Serial · 9600 baud, 8N1</p>

            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="hw2-dot off" id="hw2-dot-balanca"></div>
                <span id="hw2-balanca-status" class="text-muted" style="font-size:.85rem">
                    Nenhuma porta selecionada
                </span>
            </div>

            <div id="hw2-peso-resultado" style="display:none" class="mb-3">
                <div class="alert alert-success py-2 mb-0" style="border-radius:10px">
                    ⚖️ Peso: <strong id="hw2-peso-valor">—</strong> kg
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-outline-primary" onclick="hw2PortaBalanca()" id="hw2-btn-porta">
                    🔌 Selecionar Porta
                </button>
                <button class="btn btn-sm btn-outline-success" onclick="hw2LerPeso()" id="hw2-btn-peso">
                    ⚖️ Ler Peso
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="hw2LiberarPorta()">
                    ✕ Liberar
                </button>
            </div>
        </div>

        <!-- Leitor de Código de Barras -->
        <div class="hw2-secondary-card">
            <h6>
                <span style="width:32px;height:32px;border-radius:8px;background:#fdf4ff;display:inline-flex;align-items:center;justify-content:center">📷</span>
                Leitor de Código de Barras
            </h6>
            <p class="hw2-sub">USB HID (plug & play) ou câmera · EAN-13, QR Code, Code-128…</p>

            <div class="d-flex gap-3 mb-3">
                <div class="p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;flex:1">
                    <div style="font-size:.78rem;font-weight:600;color:#15803d">🔌 USB HID</div>
                    <div style="font-size:.73rem;color:#166534">Plug & Play — zero config</div>
                </div>
                <div class="p-2 rounded" style="background:#f5f3ff;border:1px solid #ddd6fe;flex:1">
                    <div style="font-size:.78rem;font-weight:600;color:#7c3aed">📷 Câmera</div>
                    <div id="hw2-barcode-status" style="font-size:.73rem;color:#6d28d9">Verificando…</div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-2 flex-wrap">
                <button class="btn btn-sm btn-outline-primary" onclick="hw2AbrirScanner()">
                    📷 Testar câmera
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="hw2FecharScanner()">
                    ✕ Fechar
                </button>
            </div>

            <div id="hw2-scanner-wrap" style="display:none;max-width:380px">
                <div class="hw2-scanner-wrap">
                    <video id="hw2-scanner-video" style="width:100%;max-height:200px;object-fit:cover;display:block" playsinline muted></video>
                    <div class="hw2-scanner-line"></div>
                </div>
                <div id="hw2-scanner-resultado" class="mt-2 text-center text-muted" style="font-size:.82rem">
                    Aponte para um código de barras…
                </div>
            </div>
        </div>

    </div><!-- /secondary-grid -->

    <!-- ── Painel de instalação da extensão (visível quando não instalada) ── -->
    <div class="hw2-install-panel" id="hw2-install-panel" style="display:none">
        <div class="d-flex align-items-center gap-3 mb-20" style="margin-bottom:20px">
            <div style="font-size:2rem">🧩</div>
            <div>
                <h5 class="mb-1" style="font-weight:700">Instalar Extensão Desffrut Hardware</h5>
                <p class="text-muted mb-0" style="font-size:.85rem">
                    Necessária para comunicação com impressoras. Substitui o QZ Tray — mais simples e sem Java.
                </p>
            </div>
        </div>

        <div class="hw2-install-steps">
            <div class="hw2-install-step">
                <p>
                    <strong>Instalar a extensão no Chrome:</strong><br>
                    Abra <code>chrome://extensions</code> → ative <em>Modo desenvolvedor</em> →
                    clique em <em>Carregar sem compactação</em> → selecione a pasta
                    <code>extension/chrome/</code> do projeto.
                </p>
            </div>
            <div class="hw2-install-step">
                <p>
                    <strong>Instalar o host nativo (uma vez por máquina):</strong><br>
                    Execute o arquivo <code>extension/native-host/install_chrome.bat</code>
                    como <em>Administrador</em>. Ele instala o Python e registra o host no Windows.
                </p>
            </div>
            <div class="hw2-install-step">
                <p>
                    <strong>Recarregar esta página</strong> e clicar em
                    <strong>↺ Atualizar Status</strong> acima.
                </p>
            </div>
        </div>

        <div class="d-flex gap-2 mt-2 flex-wrap">
            <button class="btn btn-primary btn-sm" onclick="hw2AtualizarStatus()">
                ↺ Verificar novamente
            </button>
            <a href="https://github.com/desffrut/extension" target="_blank" class="btn btn-outline-secondary btn-sm">
                📦 Instruções completas
            </a>
        </div>
    </div>

    <!-- ── Diagnóstico ───────────────────────────────────────────────────── -->
    <div class="hw2-secondary-card" id="hw2-diag-wrap" style="display:none">
        <h6 style="margin-bottom:16px">🔍 Diagnóstico do Sistema</h6>
        <div class="hw2-diag-grid" id="hw2-diag-grid"></div>
    </div>

</div><!-- /hw2-root -->

<script>
window.moduloHardwareUI = (function () {

    // ── Inicializa ────────────────────────────────────────────────────────
    function init() {
        if (typeof DesffrHardware === 'undefined') {
            _alerta('hardware.js não encontrado. Verifique o servidor.', 'danger');
            return;
        }
        _carregarConfig();
        hw2AtualizarStatus();
    }

    // ── Carrega configuração salva ────────────────────────────────────────
    function _carregarConfig() {
        const cfg = DesffrHardware.getConfig();

        const cupomNome  = document.getElementById('hw2-cupom-nome');
        const etiqNome   = document.getElementById('hw2-etiqueta-nome');
        const inkNome    = document.getElementById('hw2-inkjet-nome');
        const etiqLarg   = document.getElementById('hw2-etiqueta-larg');
        const etiqAlt    = document.getElementById('hw2-etiqueta-alt');
        const inkPapel   = document.getElementById('hw2-inkjet-papel');

        if (cupomNome)  cupomNome.value  = cfg.impressoras.cupom.nome    || '';
        if (etiqNome)   etiqNome.value   = cfg.impressoras.etiqueta.nome  || '';
        if (inkNome)    inkNome.value    = cfg.impressoras.inkjet.nome    || '';
        if (etiqLarg)   etiqLarg.value   = cfg.impressoras.etiqueta.largura || 100;
        if (etiqAlt)    etiqAlt.value    = cfg.impressoras.etiqueta.altura  || 150;
        if (inkPapel)   inkPapel.value   = cfg.impressoras.inkjet.papel   || 'A4';

        // Radio de papel
        const papel = cfg.impressoras.cupom.papel || '80';
        const radio = document.getElementById(`hw2-cupom-${papel}`);
        if (radio) radio.checked = true;

        // Dots iniciais
        _setDot('cupom',    !!cfg.impressoras.cupom.nome);
        _setDot('etiqueta', !!cfg.impressoras.etiqueta.nome);
        _setDot('inkjet',   !!cfg.impressoras.inkjet.nome);
    }

    // ── Status da extensão ────────────────────────────────────────────────
    window.hw2AtualizarStatus = async function () {
        if (typeof DesffrHardware === 'undefined') return;

        const banner = document.getElementById('hw2-ext-banner');
        if (banner) {
            banner.className = 'hw2-ext-banner loading';
            banner.innerHTML = `
                <div class="hw2-ext-icon">🔌</div>
                <div class="hw2-ext-info"><strong>Verificando extensão…</strong><span>Aguarde</span></div>
                <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>`;
        }

        // Badge BarcodeDetector
        const barcodeStatus = document.getElementById('hw2-barcode-status');
        if (barcodeStatus) {
            const ok = DesffrHardware.barcodeDisponivel();
            barcodeStatus.textContent   = ok ? 'Disponível (Chrome/Edge 83+)' : 'Indisponível';
            barcodeStatus.style.color   = ok ? '#16a34a' : '#dc2626';
        }

        try {
            const s = await DesffrHardware.status();
            _renderBanner(s);
            _renderDots(s);
        } catch (e) {
            _renderBannerErro(e.message);
        }
    };

    function _renderBanner(s) {
        const banner  = document.getElementById('hw2-ext-banner');
        const install = document.getElementById('hw2-install-panel');
        if (!banner) return;

        const ext = s.extensao;

        if (ext && ext.instalada) {
            banner.className = 'hw2-ext-banner ok';
            banner.innerHTML = `
                <div class="hw2-ext-icon">🧩</div>
                <div class="hw2-ext-info">
                    <strong>Extensão Desffrut conectada${s.local ? ' <em style="font-weight:400;font-size:.8rem">(localhost)</em>' : ''}</strong>
                    <span>Host nativo ativo · ${ext.versao || 'v2.0'} · Pronto para imprimir</span>
                </div>
                <span class="hw2-ext-badge ok">✓ Online</span>`;
            if (install) install.style.display = 'none';
        } else {
            // Extensão não encontrada — mostra aviso diferente em localhost vs produção
            const localMsg = s.local
                ? 'Extensão não detectada. Em desenvolvimento você pode instalar a extensão normalmente para testar impressão real.'
                : (ext?.erro || 'Instale a extensão e o host nativo para habilitar impressão.');
            banner.className = 'hw2-ext-banner ' + (s.local ? 'warn' : 'err');
            banner.innerHTML = `
                <div class="hw2-ext-icon">${s.local ? '🖥️' : '⚠️'}</div>
                <div class="hw2-ext-info">
                    <strong>${s.local ? 'Modo Local (XAMPP) — extensão não instalada' : 'Extensão não detectada'}</strong>
                    <span>${localMsg}</span>
                </div>
                <span class="hw2-ext-badge ${s.local ? 'warn' : 'err'}">${s.local ? 'Sem extensão' : 'Offline'}</span>`;
            if (install) install.style.display = '';
        }
    }

    function _renderBannerErro(msg) {
        const banner = document.getElementById('hw2-ext-banner');
        if (!banner) return;
        banner.className = 'hw2-ext-banner warn';
        banner.innerHTML = `
            <div class="hw2-ext-icon">⚠️</div>
            <div class="hw2-ext-info">
                <strong>Erro ao verificar extensão</strong>
                <span>${msg}</span>
            </div>
            <span class="hw2-ext-badge warn">Erro</span>`;
    }

    function _renderDots(s) {
        // Balança
        const balDot    = document.getElementById('hw2-dot-balanca');
        const balStatus = document.getElementById('hw2-balanca-status');
        if (balDot) {
            balDot.className = 'hw2-dot ' + (s.porta_serial ? 'ok' : 'off');
        }
        if (balStatus) {
            balStatus.textContent = s.porta_serial ? 'Porta selecionada ✓' : 'Nenhuma porta selecionada';
        }

        // Impressoras (dots pelo nome configurado)
        const cfg = DesffrHardware.getConfig();
        _setDot('cupom',    !!cfg.impressoras.cupom.nome);
        _setDot('etiqueta', !!cfg.impressoras.etiqueta.nome);
        _setDot('inkjet',   !!cfg.impressoras.inkjet.nome);
    }

    function _setDot(tipo, ok) {
        const dot = document.getElementById(`hw2-dot-${tipo}`);
        if (dot) dot.className = 'hw2-dot ' + (ok ? 'ok' : 'off');
    }

    // ── Salvar configuração de impressora ─────────────────────────────────
    window.hw2Salvar = function (tipo) {
        const patch = { impressoras: {} };

        if (tipo === 'cupom') {
            const nome  = document.getElementById('hw2-cupom-nome').value.trim();
            const papel = document.querySelector('input[name="hw2-cupom-papel"]:checked')?.value || '80';
            patch.impressoras.cupom = { nome, papel };
        } else if (tipo === 'etiqueta') {
            const nome    = document.getElementById('hw2-etiqueta-nome').value.trim();
            const largura = parseInt(document.getElementById('hw2-etiqueta-larg').value) || 100;
            const altura  = parseInt(document.getElementById('hw2-etiqueta-alt').value)  || 150;
            patch.impressoras.etiqueta = { nome, largura, altura, protocolo: 'zpl' };
        } else if (tipo === 'inkjet') {
            const nome  = document.getElementById('hw2-inkjet-nome').value.trim();
            const papel = document.getElementById('hw2-inkjet-papel').value || 'A4';
            patch.impressoras.inkjet = { nome, papel };
        }

        DesffrHardware.setConfig(patch);
        _setDot(tipo, !!(patch.impressoras[tipo]?.nome));
        _alerta(`✅ Impressora de ${_labelTipo(tipo)} salva nesta estação.`, 'success');
    };

    // ── Detectar impressoras ──────────────────────────────────────────────
    window.hw2Detectar = async function (tipo) {
        try {
            const lista = await DesffrHardware.listarImpressoras();
            const wrap  = document.getElementById(`hw2-${tipo}-list-wrap`);
            const el    = document.getElementById(`hw2-${tipo}-list`);
            const cfg   = DesffrHardware.cfgImpressora(tipo);

            if (!wrap || !el) return;

            // Se chegamos aqui, a extensão respondeu — atualiza o banner imediatamente
            _renderBannerOnline();

            if (lista.length === 0) {
                wrap.style.display = '';
                el.innerHTML = `
                    <div style="padding:12px 14px;font-size:.82rem;color:#92400e;background:#fffbeb">
                        <strong>⚠️ Nenhuma impressora encontrada.</strong><br>
                        Verifique se a extensão está instalada e a impressora está ligada e instalada no Windows.
                    </div>`;
                return;
            }

            el.innerHTML = lista.map(p => `
                <div class="hw2-detected-item ${p === cfg.nome ? 'selected' : ''}"
                     onclick="hw2SelecionarImpressora('${tipo}','${p.replace(/'/g,"\\'")}')"
                     title="${p}">
                    <span class="hw2-detected-icon">🖨️</span>
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p}</span>
                </div>`).join('');
            wrap.style.display = '';
            _alerta(`✅ ${lista.length} impressora(s) encontrada(s). Clique para selecionar.`, 'success');
        } catch (e) {
            _alerta('❌ ' + e.message, 'danger');
        }
    };

    // Atualiza banner para "online" após qualquer comunicação bem-sucedida com a extensão
    function _renderBannerOnline() {
        const banner  = document.getElementById('hw2-ext-banner');
        const install = document.getElementById('hw2-install-panel');
        if (!banner) return;
        const local = DesffrHardware.isLocal();
        banner.className = 'hw2-ext-banner ok';
        banner.innerHTML = `
            <div class="hw2-ext-icon">🧩</div>
            <div class="hw2-ext-info">
                <strong>Extensão Desffrut conectada${local ? ' <em style="font-weight:400;font-size:.8rem">(localhost)</em>' : ''}</strong>
                <span>Host nativo ativo · v2.0 · Pronto para imprimir</span>
            </div>
            <span class="hw2-ext-badge ok">✓ Online</span>`;
        if (install) install.style.display = 'none';
    }

    window.hw2SelecionarImpressora = function (tipo, nome) {
        const input = document.getElementById(`hw2-${tipo}-nome`);
        if (input) input.value = nome;
        document.querySelectorAll(`#hw2-${tipo}-list .hw2-detected-item`).forEach(el => {
            el.classList.toggle('selected', el.querySelector('span:last-child').textContent === nome);
        });
    };

    // ── Testar impressora ──────────────────────────────────────────────────
    window.hw2Testar = async function (tipo) {
        // Garante que nome atual do campo está salvo antes de testar
        hw2Salvar(tipo);
        try {
            await DesffrHardware.imprimirTeste(tipo);
            _renderBannerOnline(); // extensão respondeu com sucesso
            _alerta(`✅ Teste enviado para a impressora de ${_labelTipo(tipo)}.`, 'success');
        } catch (e) {
            _alerta('❌ ' + e.message, 'danger');
        }
    };

    // ── Preset de tamanho de etiqueta ─────────────────────────────────────
    window.hw2SetEtiqueta = function (l, a) {
        const lg = document.getElementById('hw2-etiqueta-larg');
        const al = document.getElementById('hw2-etiqueta-alt');
        if (lg) lg.value = l;
        if (al) al.value = a;
    };

    // ── Balança ───────────────────────────────────────────────────────────
    window.hw2PortaBalanca = async function () {
        const btn = document.getElementById('hw2-btn-porta');
        try {
            if (btn) { btn.disabled = true; btn.textContent = 'Aguardando…'; }
            await DesffrHardware.selecionarPortaBalanca();
            document.getElementById('hw2-balanca-status').textContent = 'Porta selecionada ✓';
            document.getElementById('hw2-dot-balanca').className = 'hw2-dot ok';
            _alerta('✅ Porta serial selecionada.', 'success');
        } catch (e) {
            if (e.name !== 'NotFoundError') _alerta('❌ ' + e.message, 'danger');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = '🔌 Selecionar Porta'; }
        }
    };

    window.hw2LerPeso = async function () {
        const btn  = document.getElementById('hw2-btn-peso');
        const res  = document.getElementById('hw2-peso-resultado');
        const val  = document.getElementById('hw2-peso-valor');
        try {
            if (btn) { btn.disabled = true; btn.textContent = 'Lendo…'; }
            if (res) res.style.display = 'none';
            const peso = await DesffrHardware.lerPesoBalanca(5000);
            if (val) val.textContent = peso.toFixed(3);
            if (res) res.style.display = '';
        } catch (e) {
            _alerta('❌ ' + e.message, 'danger');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = '⚖️ Ler Peso'; }
        }
    };

    window.hw2LiberarPorta = async function () {
        await DesffrHardware.liberarPortaBalanca();
        document.getElementById('hw2-balanca-status').textContent = 'Nenhuma porta selecionada';
        document.getElementById('hw2-dot-balanca').className = 'hw2-dot off';
        const res = document.getElementById('hw2-peso-resultado');
        if (res) res.style.display = 'none';
        _alerta('Porta serial liberada.', 'info');
    };

    // ── Scanner câmera ────────────────────────────────────────────────────
    let _testStream = null, _testScanner = null;

    window.hw2AbrirScanner = async function () {
        const wrap  = document.getElementById('hw2-scanner-wrap');
        const video = document.getElementById('hw2-scanner-video');
        const res   = document.getElementById('hw2-scanner-resultado');
        if (!DesffrHardware.barcodeDisponivel()) {
            _alerta('BarcodeDetector API não disponível. Use Chrome/Edge 83+.', 'warning');
            return;
        }
        if (wrap)  wrap.style.display = '';
        if (res)   res.textContent = 'Iniciando câmera…';
        try {
            const { stream, scanner } = await DesffrHardware.iniciarScannerCamera(
                video,
                (cod, fmt) => {
                    if (res) {
                        res.textContent = `✅ ${cod}  (${(fmt || '').toUpperCase()})`;
                        res.style.color = '#16a34a';
                    }
                }
            );
            _testStream  = stream;
            _testScanner = scanner;
            if (res) { res.textContent = 'Aponte para um código de barras…'; res.style.color = ''; }
        } catch (e) {
            if (res) { res.textContent = '❌ ' + e.message; res.style.color = '#dc2626'; }
        }
    };

    window.hw2FecharScanner = function () {
        DesffrHardware.fecharScannerCamera(_testStream, _testScanner);
        _testStream  = null;
        _testScanner = null;
        const wrap  = document.getElementById('hw2-scanner-wrap');
        const video = document.getElementById('hw2-scanner-video');
        if (wrap)  wrap.style.display = 'none';
        if (video) video.srcObject = null;
    };

    // ── Diagnóstico ───────────────────────────────────────────────────────
    window.hw2Diagnostico = async function () {
        const wrap = document.getElementById('hw2-diag-wrap');
        const grid = document.getElementById('hw2-diag-grid');
        if (!wrap || !grid) return;

        grid.innerHTML = '<div class="text-muted small">Carregando…</div>';
        wrap.style.display = '';

        try {
            const s = await DesffrHardware.status();
            const cfg = DesffrHardware.getConfig();

            const itens = [
                { ok: s.extensao?.instalada, label: 'Motor de impressão', sub: s.extensao?.instalada ? 'Extensão conectada' : 'Extensão offline' },
                { ok: !!cfg.impressoras.cupom.nome,    label: 'Impressora cupom',    sub: cfg.impressoras.cupom.nome    || 'Não configurada' },
                { ok: !!cfg.impressoras.etiqueta.nome, label: 'Impressora etiqueta', sub: cfg.impressoras.etiqueta.nome || 'Não configurada' },
                { ok: !!cfg.impressoras.inkjet.nome,   label: 'Impressora inkjet',   sub: cfg.impressoras.inkjet.nome   || 'Não configurada' },
                { ok: s.serial_api,   label: 'Web Serial API',   sub: s.serial_api ? 'Disponível' : 'Indisponível' },
                { ok: s.porta_serial, label: 'Porta balança',    sub: s.porta_serial ? 'Selecionada' : 'Nenhuma' },
                { ok: s.barcode_api,  label: 'BarcodeDetector',  sub: s.barcode_api ? 'Disponível' : 'Indisponível' },
                { ok: s.camera_api,   label: 'Câmera API',       sub: s.camera_api ? 'Disponível' : 'Indisponível' },
            ];

            grid.innerHTML = itens.map(i => `
                <div class="hw2-diag-item">
                    <div class="hw2-dot ${i.ok ? 'ok' : 'err'}"></div>
                    <div class="hw2-diag-label">
                        <strong>${i.label}</strong>
                        <span style="font-size:.73rem;color:#64748b">${i.sub}</span>
                    </div>
                </div>`).join('');
        } catch (e) {
            grid.innerHTML = `<div class="text-danger small">Erro: ${e.message}</div>`;
        }
    };

    // ── Helpers ───────────────────────────────────────────────────────────
    function _labelTipo(tipo) {
        return { cupom: 'cupom', etiqueta: 'etiqueta', inkjet: 'jato de tinta' }[tipo] || tipo;
    }

    function _alerta(msg, tipo = 'info') {
        const el = document.getElementById('hw2-alerta');
        if (!el) return;
        el.textContent = msg;
        el.className   = `hw2-alerta ${tipo} show`;
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.className = 'hw2-alerta'; }, 5000);
    }

    // ── Loader dinâmico ───────────────────────────────────────────────────
    function _loadScript(src, cb, onErr) {
        const s = document.createElement('script');
        s.src     = src;
        s.onload  = cb;
        s.onerror = onErr || (() => console.error('Falha: ' + src));
        document.head.appendChild(s);
    }

    function _iniciar() {
        if (typeof DesffrHardware === 'undefined') {
            _alerta('❌ hardware.js não carregado. Verifique o servidor.', 'danger');
            return;
        }
        init();
    }

    const hwSrc = '<?= BASE_PATH ?>/public/js/pdv/hardware.js?v=<?= filemtime(__DIR__ . "/../../../public/js/pdv/hardware.js") ?>';
    if (typeof DesffrHardware !== 'undefined') {
        init();
    } else {
        _loadScript(hwSrc, _iniciar, () => {
            document.getElementById('hw2-alerta').className = 'hw2-alerta danger show';
            document.getElementById('hw2-alerta').textContent = '❌ Falha ao carregar hardware.js.';
        });
    }

    return { init, atualizarStatus: hw2AtualizarStatus };

})();
</script>
