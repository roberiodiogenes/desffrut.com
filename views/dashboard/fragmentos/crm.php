<?php
/**
 * Desffrut — Fragmento: Funil de Parcerias / CRM Kanban v2 (Fase 10)
 *
 * Melhorias v2:
 *  - Badges de temperatura (Quente / Morno / Frio) clicáveis nos cards
 *  - Valor estimado de compra nos cards e no modal
 *  - Seletor de Loja Responsável no modal
 *  - Somatório financeiro por coluna no cabeçalho do Kanban
 *  - Indicador de envelhecimento (dias parado) com cor progressiva
 *  - Barra de resumo do funil (ativos, pipeline total, conversão, quentes)
 *  - Importador CSV com drop-zone + preview
 */
$pdo = db();

// Contadores + somas por fase
$stats_raw = $pdo->query("
    SELECT fase,
           COUNT(*)                   AS n,
           COALESCE(SUM(valor_estimado), 0) AS total
    FROM leads GROUP BY fase
")->fetchAll(PDO::FETCH_UNIQUE);

$fases_config = ['novo','contato','proposta','negociacao','fechado','perdido'];
$counts = []; $somas = [];
foreach ($fases_config as $f) {
    $counts[$f] = isset($stats_raw[$f]) ? (int)$stats_raw[$f]['n']    : 0;
    $somas[$f]  = isset($stats_raw[$f]) ? (float)$stats_raw[$f]['total'] : 0.0;
}

// Lojas para o modal
$lojas = $pdo->query("SELECT id, nome FROM lojas WHERE ativo = 1 ORDER BY nome")->fetchAll();
?>
<style>
/* ─── CRM v2 ─────────────────────────────────────────────────── */
.crm-wrap      { padding: 20px; }

/* Tabs */
.crm-tabs      { display: flex; gap: 6px; margin-bottom: 14px; flex-wrap: wrap; }
.crm-tab-btn   {
    padding: 7px 18px; border: 1.5px solid #e0e0e0; border-radius: 20px;
    background: #fff; font-size: .82rem; cursor: pointer; color: #555; font-weight: 600;
    transition: all .15s;
}
.crm-tab-btn.active { background: #1565c0; color: #fff; border-color: #1565c0; }
.crm-panel     { display: none; }
.crm-panel.active { display: block; }

/* Barra de resumo do funil */
.crm-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}
@media(max-width:700px){ .crm-summary { grid-template-columns: repeat(2,1fr); } }
.crm-sum-card {
    background: #fff; border-radius: 10px;
    border: 1px solid #e8eaf6;
    padding: 12px 16px;
    text-align: center;
}
.crm-sum-label { font-size: .72rem; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.crm-sum-val   { font-size: 1.4rem; font-weight: 800; color: #1a1a2e; line-height: 1.2; margin-top: 4px; }
.crm-sum-card.c-ativos  .crm-sum-val { color: #1565c0; }
.crm-sum-card.c-valor   .crm-sum-val { color: #2e7d32; font-size: 1.1rem; }
.crm-sum-card.c-conv    .crm-sum-val { color: #e65100; }
.crm-sum-card.c-quentes .crm-sum-val { color: #c62828; }

/* Toolbar */
.crm-kb-toolbar {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 14px; flex-wrap: wrap;
}
.crm-kb-search {
    flex: 1; min-width: 200px; padding: 8px 12px;
    border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: .88rem;
}
.crm-kb-search:focus { outline: none; border-color: #1565c0; }
.crm-kb-add-btn {
    padding: 8px 16px; background: #1565c0; color: #fff; border: none;
    border-radius: 8px; font-size: .85rem; font-weight: 600; cursor: pointer;
}
.crm-kb-add-btn:hover { background: #0d47a1; }

/* Board */
.crm-board {
    display: grid;
    grid-template-columns: repeat(6, minmax(195px, 1fr));
    gap: 10px; overflow-x: auto; padding-bottom: 12px; min-height: 400px;
}
@media(max-width:900px){ .crm-board { grid-template-columns: repeat(3,minmax(180px,1fr)); } }

.crm-col { min-height: 340px; }

.crm-col-header {
    padding: 8px 10px 4px; border-radius: 10px 10px 0 0;
    font-size: .78rem; font-weight: 700; color: #fff;
}
.crm-col-htop  { display: flex; justify-content: space-between; align-items: center; }
.crm-col-hsum  {
    font-size: .69rem; font-weight: 600; opacity: .85;
    margin-top: 2px; min-height: 14px;
}
.crm-col-body {
    background: #f5f7fa; border: 1px solid #e4e6ea;
    border-radius: 0 0 10px 10px; padding: 8px; min-height: 300px;
}
.crm-count-badge {
    background: rgba(255,255,255,.22); border-radius: 20px;
    padding: 1px 8px; font-size: .72rem;
}

.crm-col[data-fase="novo"]        .crm-col-header { background: #1565c0; }
.crm-col[data-fase="contato"]     .crm-col-header { background: #6a1b9a; }
.crm-col[data-fase="proposta"]    .crm-col-header { background: #e65100; }
.crm-col[data-fase="negociacao"]  .crm-col-header { background: #f57f17; }
.crm-col[data-fase="fechado"]     .crm-col-header { background: #2e7d32; }
.crm-col[data-fase="perdido"]     .crm-col-header { background: #616161; }

/* Cards */
.crm-card {
    background: #fff; border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
    margin-bottom: 6px; overflow: hidden;
    transition: box-shadow .15s;
    border-left: 3px solid transparent;
}
.crm-card-ghost { opacity: .35; background: #e3f2fd; }
.crm-card[data-temp="quente"] { border-left-color: #c62828; }
.crm-card[data-temp="morno"]  { border-left-color: #f57f17; }
.crm-card[data-temp="frio"]   { border-left-color: #90caf9; }

/* ── Linha superior (sempre visível) ── */
.crm-card-top {
    display: flex; align-items: center; gap: 5px;
    padding: 6px 6px 6px 0; min-width: 0;
}
.crm-drag-handle {
    flex-shrink: 0; width: 24px; align-self: stretch;
    display: flex; align-items: center; justify-content: center;
    color: #ccc; font-size: .95rem; cursor: grab;
    border-right: 1px solid #f0f0f0; user-select: none;
    margin-right: 2px;
}
.crm-drag-handle:active { cursor: grabbing; }

/* Badge de temperatura — dentro da linha, sem absolute */
.crm-temp-badge {
    flex-shrink: 0;
    font-size: .66rem; font-weight: 700; padding: 2px 6px;
    border-radius: 20px; cursor: pointer; user-select: none;
    white-space: nowrap; transition: transform .1s;
}
.crm-temp-badge:hover { transform: scale(1.08); }
.crm-temp-badge[data-temp="quente"] { background: #ffebee; color: #c62828; }
.crm-temp-badge[data-temp="morno"]  { background: #fff3e0; color: #e65100; }
.crm-temp-badge[data-temp="frio"]   { background: #e3f2fd; color: #1565c0; }
/* Compact: só emoji, sem texto */
.crm-card.compact .tb-lbl  { display: none; }

/* Botão expandir/recolher */
.crm-toggle-btn {
    flex-shrink: 0; background: none; border: none;
    color: #bbb; font-size: .82rem; cursor: pointer;
    padding: 2px 4px; line-height: 1;
    transition: transform .2s, color .15s;
}
.crm-toggle-btn:hover { color: #555; }
.crm-card:not(.compact) .crm-toggle-btn { transform: rotate(90deg); }

/* ── Detalhes (recolhidos por padrão) ── */
.crm-card-details {
    max-height: 0; overflow: hidden;
    transition: max-height .22s ease, padding .22s ease;
    padding: 0 10px 0 28px;
}
.crm-card:not(.compact) .crm-card-details {
    max-height: 260px;
    padding: 0 10px 10px 28px;
}

/* Card: conteúdo */
.crm-card-nome   { font-size: .85rem; font-weight: 700; color: #1a1a2e;
                   flex: 1; min-width: 0; overflow: hidden;
                   text-overflow: ellipsis; white-space: nowrap; }
.crm-card-emp    { font-size: .74rem; color: #6d28d9; font-weight: 600; }
.crm-card-tel    { font-size: .75rem; color: #555; margin-top: 4px; }
.crm-card-bairro { font-size: .71rem; color: #888; }
.crm-card-valor  { font-size: .74rem; color: #2e7d32; font-weight: 700; margin-top: 3px; }
.crm-card-loja   { font-size: .7rem; color: #777; }

/* Envelhecimento */
.crm-age {
    display: inline-block; font-size: .66rem; font-weight: 700;
    padding: 1px 6px; border-radius: 10px; margin-top: 4px;
}
.crm-age-ok   { background: #e8f5e9; color: #2e7d32; }
.crm-age-warn { background: #fff8e1; color: #f57f17; }
.crm-age-old  { background: #ffebee; color: #c62828; }

/* Ações do card */
.crm-card-actions { display: flex; gap: 5px; margin-top: 8px; flex-wrap: wrap; }
.crm-wa-btn {
    padding: 4px 8px; background: #25d366; color: #fff;
    border: none; border-radius: 5px; font-size: .7rem; font-weight: 700;
    cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 3px;
}
.crm-wa-btn:hover { background: #128c7e; }
.crm-del-btn {
    padding: 4px 8px; background: #ffebee; color: #c62828;
    border: 1px solid #ffcdd2; border-radius: 5px; font-size: .7rem; cursor: pointer;
}
.crm-del-btn:hover { background: #ffcdd2; }

/* Empty / loading */
.crm-col-empty  { text-align: center; color: #ccc; font-size: .8rem; padding: 24px 0; }

/* Modal */
.crm-modal-bg {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 1000;
    align-items: center; justify-content: center;
}
.crm-modal-bg.open { display: flex; }
.crm-modal {
    background: #fff; border-radius: 14px; padding: 26px 22px;
    width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0,0,0,.18);
}
.crm-modal h3   { margin: 0 0 16px; font-size: 1.05rem; color: #1565c0; }
.crm-mgroup     { margin-bottom: 12px; }
.crm-mgroup label { display: block; font-size: .77rem; font-weight: 600; color: #444; margin-bottom: 4px; }
.crm-minput {
    width: 100%; padding: 9px 11px; box-sizing: border-box;
    border: 1.5px solid #e0e0e0; border-radius: 7px; font-size: .9rem;
}
.crm-minput:focus { outline: none; border-color: #1565c0; }
.crm-mrow  { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.crm-mrow3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
.crm-mbtns { display: flex; gap: 8px; margin-top: 8px; }
.crm-msave {
    flex: 1; padding: 10px; background: #1565c0; color: #fff;
    border: none; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: .9rem;
}
.crm-msave:hover   { background: #0d47a1; }
.crm-mcancel {
    padding: 10px 16px; background: #f5f5f5; color: #555;
    border: 1px solid #ddd; border-radius: 7px; cursor: pointer;
}

/* ── Importador CSV ── */
.crm-csv-wrap  { max-width: 680px; }
.crm-csv-drop  {
    border: 2px dashed #90caf9; border-radius: 12px; padding: 36px;
    text-align: center; cursor: pointer; background: #e3f2fd; transition: border-color .2s;
}
.crm-csv-drop.hover { border-color: #1565c0; background: #bbdefb; }
.crm-csv-drop p { margin: 8px 0 0; font-size: .85rem; color: #555; }
.crm-csv-input { display: none; }
.crm-csv-fname { margin-top: 10px; font-size: .82rem; color: #1565c0; font-weight: 600; }
.crm-csv-prev  { margin-top: 16px; overflow-x: auto; }
.crm-csv-tbl   { width: 100%; border-collapse: collapse; font-size: .78rem; }
.crm-csv-tbl th{ background: #1565c0; color: #fff; padding: 6px 10px; text-align: left; }
.crm-csv-tbl td{ padding: 5px 10px; border-bottom: 1px solid #e0e0e0; color: #333; }
.crm-csv-tbl tr:nth-child(even) td { background: #f5f7fa; }
.crm-csv-info  { font-size: .8rem; color: #555; margin: 8px 0; }
.crm-csv-send  {
    margin-top: 12px; padding: 10px 28px; background: #2e7d32; color: #fff;
    border: none; border-radius: 8px; font-size: .9rem; font-weight: 700; cursor: pointer;
}
.crm-csv-send:disabled { background: #aaa; cursor: not-allowed; }
#crm-csv-result {
    margin-top: 14px; padding: 12px; border-radius: 8px;
    font-size: .85rem; display: none;
}
.crm-res-ok  { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
.crm-res-err { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
</style>

<div class="crm-wrap">

    <!-- Tabs -->
    <div class="crm-tabs">
        <button class="crm-tab-btn active" data-tab="funil">🏆 Funil de Parcerias</button>
        <button class="crm-tab-btn"        data-tab="csv">📥 Importar CSV</button>
    </div>

    <!-- ═══ PAINEL 1: FUNIL ══════════════════════════════════════════ -->
    <div class="crm-panel active" data-panel="funil">

        <!-- Barra de resumo -->
        <div class="crm-summary">
            <div class="crm-sum-card c-ativos">
                <div class="crm-sum-label">Leads Ativos</div>
                <div class="crm-sum-val" id="sum-ativos">—</div>
            </div>
            <div class="crm-sum-card c-valor">
                <div class="crm-sum-label">Pipeline Total</div>
                <div class="crm-sum-val" id="sum-valor">—</div>
            </div>
            <div class="crm-sum-card c-conv">
                <div class="crm-sum-label">Taxa Conversão</div>
                <div class="crm-sum-val" id="sum-taxa">—</div>
            </div>
            <div class="crm-sum-card c-quentes">
                <div class="crm-sum-label">🔥 Quentes</div>
                <div class="crm-sum-val" id="sum-quentes">—</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="crm-kb-toolbar">
            <input class="crm-kb-search" type="search" id="crm-search"
                   placeholder="🔍 Buscar nome, empresa ou telefone…">
            <button class="crm-kb-add-btn" id="crm-add-btn">＋ Novo lead</button>
        </div>

        <!-- Board -->
        <div class="crm-board" id="crm-board">
            <?php
            $fases_ui = [
                ['id'=>'novo',       'label'=>'Novo'],
                ['id'=>'contato',    'label'=>'Contato'],
                ['id'=>'proposta',   'label'=>'Proposta'],
                ['id'=>'negociacao', 'label'=>'Negociação'],
                ['id'=>'fechado',    'label'=>'Fechado ✅'],
                ['id'=>'perdido',    'label'=>'Perdido'],
            ];
            foreach ($fases_ui as $f):
                $soma_fmt = $somas[$f['id']] > 0
                    ? 'R$ ' . number_format($somas[$f['id']], 0, ',', '.')
                    : '';
            ?>
            <div class="crm-col" data-fase="<?= $f['id'] ?>">
                <div class="crm-col-header">
                    <div class="crm-col-htop">
                        <span><?= $f['label'] ?></span>
                        <span class="crm-count-badge" id="cnt-<?= $f['id'] ?>"><?= $counts[$f['id']] ?></span>
                    </div>
                    <div class="crm-col-hsum" id="hsum-<?= $f['id'] ?>"><?= $soma_fmt ?></div>
                </div>
                <div class="crm-col-body sortable-list" id="col-<?= $f['id'] ?>">
                    <div class="crm-col-empty" id="load-<?= $f['id'] ?>">Carregando…</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ═══ PAINEL 2: IMPORTAR CSV ══════════════════════════════════ -->
    <div class="crm-panel" data-panel="csv">
        <div class="crm-csv-wrap">
            <div class="crm-csv-drop" id="crm-drop-zone">
                <div style="font-size:2.4rem;">📄</div>
                <strong>Arraste um arquivo .CSV aqui</strong>
                <p>ou clique para selecionar</p>
                <p style="font-size:.75rem;color:#888;margin-top:8px;">
                    Colunas esperadas: <strong>nome</strong>, <strong>telefone</strong>,
                    bairro, empresa, email &nbsp;·&nbsp; Separador: <code>;</code> ou <code>,</code>
                </p>
                <input class="crm-csv-input" type="file" id="crm-csv-file" accept=".csv">
            </div>
            <div class="crm-csv-fname" id="crm-csv-fname"></div>
            <div class="crm-csv-prev"  id="crm-csv-preview"></div>
            <button class="crm-csv-send" id="crm-csv-btn" disabled>⬆️ Importar para o Funil</button>
            <div id="crm-csv-result"></div>
        </div>
    </div>

</div><!-- .crm-wrap -->

<!-- ═══ MODAL: NOVO LEAD MANUAL ══════════════════════════════════ -->
<div class="crm-modal-bg" id="crm-modal">
    <div class="crm-modal">
        <h3>➕ Novo Lead Manual</h3>

        <div class="crm-mrow">
            <div class="crm-mgroup">
                <label>Nome *</label>
                <input class="crm-minput" type="text" id="ml-nome" placeholder="Nome completo">
            </div>
            <div class="crm-mgroup">
                <label>Empresa</label>
                <input class="crm-minput" type="text" id="ml-empresa" placeholder="Empresa / estabelecimento">
            </div>
        </div>

        <div class="crm-mrow">
            <div class="crm-mgroup">
                <label>WhatsApp *</label>
                <input class="crm-minput" type="tel" id="ml-telefone" placeholder="(85) 99999-9999">
            </div>
            <div class="crm-mgroup">
                <label>Bairro</label>
                <input class="crm-minput" type="text" id="ml-bairro" placeholder="Ex.: Messejana">
            </div>
        </div>

        <div class="crm-mrow">
            <div class="crm-mgroup">
                <label>E-mail</label>
                <input class="crm-minput" type="email" id="ml-email" placeholder="opcional">
            </div>
            <div class="crm-mgroup">
                <label>Valor Estimado (R$/mês)</label>
                <input class="crm-minput" type="number" id="ml-valor" min="0" step="0.01"
                       placeholder="0,00">
            </div>
        </div>

        <div class="crm-mrow">
            <div class="crm-mgroup">
                <label>Temperatura</label>
                <select class="crm-minput" id="ml-temp">
                    <option value="frio">❄️ Frio</option>
                    <option value="morno">🌡️ Morno</option>
                    <option value="quente">🔥 Quente</option>
                </select>
            </div>
            <div class="crm-mgroup">
                <label>Loja Responsável</label>
                <select class="crm-minput" id="ml-loja">
                    <option value="">— Nenhuma —</option>
                    <?php foreach ($lojas as $lj): ?>
                    <option value="<?= $lj['id'] ?>"><?= htmlspecialchars($lj['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="crm-mgroup">
            <label>Observações</label>
            <textarea class="crm-minput" id="ml-msg" rows="2" placeholder="Opcional…"></textarea>
        </div>

        <div class="crm-mbtns">
            <button class="crm-msave"   id="ml-save">Salvar Lead</button>
            <button class="crm-mcancel" id="ml-cancel">Cancelar</button>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

const API   = '<?= BASE_PATH ?>/api/v1';
const TOKEN = sessionStorage.getItem('desffrut_token') || '';
const LOJAS = <?= json_encode(array_column($lojas, 'nome', 'id'), JSON_UNESCAPED_UNICODE) ?>;

function authH(extra) {
    const h = Object.assign({ 'Content-Type': 'application/json' }, extra || {});
    if (TOKEN) h['Authorization'] = 'Bearer ' + TOKEN;
    return h;
}

/* ─── Dados ────────────────────────────────────────────────────── */
const fases = ['novo','contato','proposta','negociacao','fechado','perdido'];
const leadsPorFase = {};
fases.forEach(f => leadsPorFase[f] = []);

/* ─── WhatsApp: mensagem por fase ──────────────────────────────── */
const WA = {
    novo:        n => `Olá ${n}! 👋 Vi seu interesse em parceria com a Desffrut. Sou [nome] e gostaria de entender como posso te ajudar. Tem um minuto?`,
    contato:     n => `Olá ${n}! Dando continuidade à nossa conversa sobre parceria com a Desffrut 🌿. Quando podemos conversar melhor?`,
    proposta:    n => `Olá ${n}! Nossa proposta de parceria está pronta 📋. Posso apresentar os detalhes agora?`,
    negociacao:  n => `Olá ${n}! Estamos na reta final para fechar nossa parceria 🤝. Tem alguma dúvida antes de prosseguirmos?`,
    fechado:     n => `Olá ${n}! Bem-vindo(a) à família Desffrut! 🎉 Em breve entraremos em contato para iniciar nossa parceria.`,
    perdido:     n => `Olá ${n}! Não conseguimos avançar, mas ficamos à disposição para qualquer necessidade futura. 😊`,
};

const TEMP_ICON  = { quente: '🔥', morno: '🌡️', frio: '❄️' };
const TEMP_SHORT = { quente: 'Quente', morno: 'Morno', frio: 'Frio' };
const TEMP_LABEL = { quente: '🔥 Quente', morno: '🌡️ Morno', frio: '❄️ Frio' }; // legacy compat
const TEMP_NEXT  = { frio: 'morno', morno: 'quente', quente: 'frio' };

/* ─── Helpers ──────────────────────────────────────────────────── */
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                          .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatMoeda(v) {
    const n = parseFloat(v) || 0;
    return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
function diasDesde(dtStr) {
    if (!dtStr) return null;
    return Math.floor((Date.now() - new Date(dtStr).getTime()) / 86400000);
}
function idadeEl(dias) {
    if (dias === null) return '';
    let cls, txt;
    if (dias < 3)       { cls = 'crm-age-ok';   txt = dias === 0 ? 'Hoje' : `${dias}d`; }
    else if (dias < 7)  { cls = 'crm-age-warn';  txt = `${dias}d ⚠`; }
    else                { cls = 'crm-age-old';   txt = `${dias}d 🔴`; }
    return `<span class="crm-age ${cls}" title="Último movimento há ${dias} dia(s)">${txt}</span>`;
}

/* ─── Resumo do funil ──────────────────────────────────────────── */
function atualizarResumo() {
    const todos   = fases.flatMap(f => leadsPorFase[f]);
    const ativos  = todos.filter(l => l.fase !== 'perdido');
    const valor   = ativos.reduce((s,l) => s + (parseFloat(l.valor_estimado) || 0), 0);
    const fechados= (leadsPorFase['fechado'] || []).length;
    const taxa    = ativos.length > 0 ? Math.round(fechados / ativos.length * 100) : 0;
    const quentes = ativos.filter(l => l.temperatura === 'quente').length;

    document.getElementById('sum-ativos').textContent  = ativos.length;
    document.getElementById('sum-valor').textContent   = valor > 0 ? formatMoeda(valor) : 'R$ 0';
    document.getElementById('sum-taxa').textContent    = taxa + '%';
    document.getElementById('sum-quentes').textContent = quentes;
}

/* ─── Somatório por coluna ─────────────────────────────────────── */
function atualizarSomaColuna(fase) {
    const soma = (leadsPorFase[fase] || []).reduce((s,l) => s + (parseFloat(l.valor_estimado)||0), 0);
    const el   = document.getElementById('hsum-' + fase);
    if (el) el.textContent = soma > 0 ? formatMoeda(soma) : '';
}

/* ─── Render ──────────────────────────────────────────────────── */
function renderFase(fase, filtro) {
    const col   = document.getElementById('col-' + fase);
    const badge = document.getElementById('cnt-' + fase);
    let items   = leadsPorFase[fase];

    if (filtro) {
        const f = filtro.toLowerCase();
        items = items.filter(l =>
            (l.nome     ||'').toLowerCase().includes(f) ||
            (l.empresa  ||'').toLowerCase().includes(f) ||
            (l.telefone ||'').includes(f)
        );
    }

    badge.textContent = leadsPorFase[fase].length;
    col.innerHTML = '';

    if (!items.length) {
        col.innerHTML = '<div class="crm-col-empty">Nenhum lead</div>';
        atualizarSomaColuna(fase);
        atualizarResumo();
        return;
    }
    items.forEach(l => col.appendChild(criarCard(l)));
    atualizarSomaColuna(fase);
    atualizarResumo();
}

function criarCard(l) {
    const div = document.createElement('div');
    div.className  = 'crm-card';
    div.dataset.id   = l.id;
    div.dataset.fase = l.fase;
    div.dataset.temp = l.temperatura || 'frio';

    const tel    = (l.telefone||'').replace(/\D/g,'');
    const waUrl  = tel ? `https://wa.me/55${tel}?text=${encodeURIComponent(WA[l.fase]?.(l.nome||'')||'')}` : null;
    const dias   = diasDesde(l.atualizado_em);
    const lojaNome = l.loja_id ? (LOJAS[l.loja_id] || '') : '';
    const temp   = l.temperatura || 'frio';

    div.innerHTML = `
        <!-- Linha sempre visível (modo compacto) -->
        <div class="crm-card-top">
            <div class="crm-drag-handle" title="Arrastar">⠿</div>
            <div class="crm-card-nome" title="${esc(l.nome)}">${esc(l.nome)}</div>
            <span class="crm-temp-badge" data-temp="${esc(temp)}" title="${TEMP_LABEL[temp]}">
                <span class="tb-icon">${TEMP_ICON[temp]}</span><span class="tb-lbl"> ${TEMP_SHORT[temp]}</span>
            </span>
            <button class="crm-toggle-btn" title="Expandir">›</button>
        </div>
        <!-- Detalhes (ocultos no modo compacto) -->
        <div class="crm-card-details">
            ${l.empresa ? `<div class="crm-card-emp">🏢 ${esc(l.empresa)}</div>` : ''}
            <div class="crm-card-tel">📞 ${esc(l.telefone)}</div>
            ${l.bairro         ? `<div class="crm-card-bairro">📍 ${esc(l.bairro)}</div>`          : ''}
            ${l.valor_estimado > 0 ? `<div class="crm-card-valor">💰 ${formatMoeda(l.valor_estimado)}/mês</div>` : ''}
            ${lojaNome         ? `<div class="crm-card-loja">🏪 ${esc(lojaNome)}</div>`            : ''}
            ${idadeEl(dias)}
            <div class="crm-card-actions">
                ${waUrl ? `<a class="crm-wa-btn" href="${waUrl}" target="_blank" rel="noopener">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 0C5.37 0 0 5.37 0 12c0 2.12.56 4.1 1.53 5.82L.05 24l6.35-1.66A11.94 11.94 0 0 0 12 24c6.63 0 12-5.37 12-12S18.63 0 12 0zm6.18 16.94c-.26.72-1.28 1.32-2.08 1.5-.56.12-1.28.22-3.73-.8-3.13-1.29-5.14-4.47-5.3-4.68-.15-.2-1.22-1.63-1.22-3.1 0-1.48.77-2.2 1.04-2.51.26-.28.57-.35.76-.35h.55c.17 0 .4-.06.63.48.26.6.88 2.16.96 2.32.08.16.13.34.03.55-.1.2-.16.34-.3.52-.16.18-.33.4-.47.54-.14.15-.3.3-.13.6.17.28.78 1.29 1.68 2.09 1.15 1.03 2.12 1.35 2.42 1.5.3.16.47.13.65-.08.18-.2.75-.88.95-1.18.2-.3.4-.25.67-.15.27.1 1.7.8 2 .95.3.14.5.2.57.32.07.12.07.7-.18 1.42z"/>
                    </svg> WhatsApp</a>` : ''}
                <button class="crm-del-btn" data-id="${l.id}">🗑</button>
            </div>
        </div>
    `;

    /* ── Toggle compacto/expandido ── */
    div.querySelector('.crm-toggle-btn').addEventListener('click', e => {
        e.stopPropagation();
        const isCompact = div.classList.toggle('compact');
        e.currentTarget.title = isCompact ? 'Expandir' : 'Recolher';
    });

    /* ── Clique no badge de temperatura ── */
    div.querySelector('.crm-temp-badge').addEventListener('click', async e => {
        e.stopPropagation();
        e.preventDefault();
        const badge  = e.currentTarget;
        const atualT = badge.dataset.temp;
        const novoT  = TEMP_NEXT[atualT];
        const lead   = leadsPorFase[l.fase]?.find(x => x.id === l.id);

        // Atualização visual imediata (optimistic)
        badge.dataset.temp = novoT;
        badge.querySelector('.tb-icon').textContent = TEMP_ICON[novoT];
        badge.querySelector('.tb-lbl').textContent  = ' ' + TEMP_SHORT[novoT];
        badge.title    = TEMP_LABEL[novoT];
        div.dataset.temp = novoT;
        if (lead) lead.temperatura = novoT;
        atualizarResumo();

        try {
            const r = await fetch(API + '/leads/' + l.id, {
                method: 'PATCH',
                headers: authH({}),
                body: JSON.stringify({ temperatura: novoT }),
            });
            const j = await r.json();
            if (j.status !== 'ok') {
                badge.title = '⚠ Salvo localmente — execute 09-migration_crm_v2.sql';
            }
        } catch (_) {
            badge.title = '⚠ Sem conexão — alteração local apenas';
        }
    });

    /* ── Deletar ── */
    div.querySelector('.crm-del-btn').addEventListener('click', async e => {
        e.stopPropagation();
        if (!confirm('Remover este lead permanentemente?')) return;
        const r = await fetch(API + '/leads/' + l.id, { method:'DELETE', headers: authH({}) });
        const j = await r.json();
        if (j.status === 'ok') {
            leadsPorFase[l.fase] = leadsPorFase[l.fase].filter(x => x.id !== l.id);
            renderFase(l.fase, document.getElementById('crm-search').value);
        } else { alert(j.message || 'Erro ao remover.'); }
    });

    return div;
}

/* ─── Carregar fase via API ────────────────────────────────────── */
async function carregarFase(fase) {
    try {
        const r = await fetch(`${API}/leads?fase=${fase}&por_pg=200`, { headers: authH({}) });
        const j = await r.json();
        leadsPorFase[fase] = j.data?.leads || [];
        renderFase(fase);
    } catch(_) {
        const el = document.getElementById('load-' + fase);
        if (el) el.textContent = 'Erro ao carregar.';
    }
}

/* ─── Sortable.js: drag & drop ─────────────────────────────────── */
function initSortable() {
    fases.forEach(fase => {
        Sortable.create(document.getElementById('col-' + fase), {
            group:      'crm-leads',
            animation:  150,
            ghostClass: 'crm-card-ghost',
            handle:     '.crm-drag-handle',   // só arrasta pelo handle ⠿
            onEnd: async function(evt) {
                const novaFase  = evt.to.closest('.crm-col').dataset.fase;
                const velhaFase = evt.from.closest('.crm-col').dataset.fase;
                const leadId    = parseInt(evt.item.dataset.id);
                if (novaFase === velhaFase) return;

                const lead = leadsPorFase[velhaFase]?.find(x => x.id === leadId);
                if (lead) {
                    leadsPorFase[velhaFase] = leadsPorFase[velhaFase].filter(x => x.id !== leadId);
                    lead.fase = novaFase;
                    lead.atualizado_em = new Date().toISOString();
                    leadsPorFase[novaFase].push(lead);
                    // Troca o card por um atualizado (novo link WhatsApp + idade)
                    const novoCard = criarCard(lead);
                    evt.item.replaceWith(novoCard);
                    // Badges e somas
                    document.getElementById('cnt-'+velhaFase).textContent = leadsPorFase[velhaFase].length;
                    document.getElementById('cnt-'+novaFase).textContent  = leadsPorFase[novaFase].length;
                    atualizarSomaColuna(velhaFase);
                    atualizarSomaColuna(novaFase);
                    atualizarResumo();
                    // Coluna vazia?
                    if (!leadsPorFase[velhaFase].length) {
                        document.getElementById('col-'+velhaFase).innerHTML =
                            '<div class="crm-col-empty">Nenhum lead</div>';
                    }
                }

                const r = await fetch(API + '/leads/' + leadId, {
                    method:'PATCH', headers: authH({}),
                    body: JSON.stringify({ fase: novaFase }),
                });
                if (!(await r.json().then(j=>j.status==='ok').catch(()=>false))) {
                    await carregarFase(velhaFase);
                    await carregarFase(novaFase);
                }
            }
        });
    });
}

/* ─── Busca em tempo real ──────────────────────────────────────── */
let buscaTimer;
document.getElementById('crm-search').addEventListener('input', function() {
    clearTimeout(buscaTimer);
    buscaTimer = setTimeout(() => fases.forEach(f => renderFase(f, this.value)), 280);
});

/* ─── Modal: novo lead manual ──────────────────────────────────── */
const modal = document.getElementById('crm-modal');
const modalCampos = ['ml-nome','ml-empresa','ml-telefone','ml-bairro','ml-email','ml-valor','ml-msg'];

document.getElementById('crm-add-btn').addEventListener('click', () => {
    modalCampos.forEach(id => document.getElementById(id).value = '');
    document.getElementById('ml-temp').value = 'frio';
    document.getElementById('ml-loja').value = '';
    modal.classList.add('open');
});
document.getElementById('ml-cancel').addEventListener('click', () => modal.classList.remove('open'));
modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });

document.getElementById('ml-save').addEventListener('click', async () => {
    const nome     = document.getElementById('ml-nome').value.trim();
    const telefone = document.getElementById('ml-telefone').value.replace(/\D/g,'');
    if (!nome || !telefone) { alert('Nome e telefone são obrigatórios.'); return; }

    const body = { nome, telefone };
    ['ml-empresa','ml-bairro','ml-email','ml-msg'].forEach(id => {
        const v = document.getElementById(id).value.trim();
        if (v) body[{ 'ml-empresa':'empresa','ml-bairro':'bairro','ml-email':'email','ml-msg':'mensagem' }[id]] = v;
    });
    const valor = parseFloat(document.getElementById('ml-valor').value);
    if (valor > 0) body.valor_estimado = valor;
    const loja = document.getElementById('ml-loja').value;
    if (loja) body.loja_id = parseInt(loja);
    body.temperatura = document.getElementById('ml-temp').value;

    // POST público /leads/novo (aceita os campos extras via PATCH imediato após criação)
    const r1 = await fetch(API + '/leads/novo', {
        method:'POST', headers: authH({}), body: JSON.stringify(body),
    });
    const j1 = await r1.json();
    if (j1.status !== 'ok') { alert(j1.message || 'Erro ao salvar.'); return; }

    // Se há campos extras que /leads/novo não persiste, faz PATCH
    if (body.valor_estimado || body.loja_id || body.temperatura !== 'frio') {
        await fetch(API + '/leads/' + j1.data.id, {
            method:'PATCH', headers: authH({}),
            body: JSON.stringify({
                temperatura:    body.temperatura,
                valor_estimado: body.valor_estimado || null,
                loja_id:        body.loja_id        || null,
            }),
        });
    }

    modal.classList.remove('open');
    await carregarFase('novo');
});

/* ─── Tabs ─────────────────────────────────────────────────────── */
document.querySelectorAll('.crm-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.crm-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.crm-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.querySelector(`.crm-panel[data-panel="${btn.dataset.tab}"]`).classList.add('active');
    });
});

/* ─── Importador CSV ───────────────────────────────────────────── */
const dropZone = document.getElementById('crm-drop-zone');
const csvInput = document.getElementById('crm-csv-file');
const csvFname = document.getElementById('crm-csv-fname');
const csvPrev  = document.getElementById('crm-csv-preview');
const csvBtn   = document.getElementById('crm-csv-btn');
const csvRes   = document.getElementById('crm-csv-result');
let   csvFile  = null;

dropZone.addEventListener('click',    () => csvInput.click());
dropZone.addEventListener('dragover', e  => { e.preventDefault(); dropZone.classList.add('hover'); });
dropZone.addEventListener('dragleave',()  => dropZone.classList.remove('hover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('hover');
    if (e.dataTransfer.files[0]) processarCSV(e.dataTransfer.files[0]);
});
csvInput.addEventListener('change', e => { if (e.target.files[0]) processarCSV(e.target.files[0]); });

function processarCSV(file) {
    csvFile = file;
    csvFname.textContent = `📎 ${file.name} (${(file.size/1024).toFixed(1)} KB)`;
    csvBtn.disabled = false;
    csvRes.style.display = 'none';

    const reader = new FileReader();
    reader.onload = e => {
        let text = e.target.result;
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        const sep    = text.includes(';') ? ';' : ',';
        const linhas = text.split(/\r?\n/).filter(l => l.trim());
        if (linhas.length < 2) { csvPrev.innerHTML = '<p style="color:#c62828">CSV vazio.</p>'; return; }
        const header = linhas[0].split(sep).map(h => h.trim());
        const amostra= linhas.slice(1,6);
        let html = `<div class="crm-csv-info">✅ <strong>${linhas.length-1}</strong> registros · separador: <code>${sep}</code></div>`;
        html += '<table class="crm-csv-tbl"><thead><tr>' + header.map(h=>`<th>${esc(h)}</th>`).join('') + '</tr></thead><tbody>';
        amostra.forEach(l => {
            html += '<tr>' + l.split(sep).map(c=>`<td>${esc(c.trim())}</td>`).join('') + '</tr>';
        });
        if (linhas.length > 6) html += `<tr><td colspan="${header.length}" style="color:#888;font-style:italic">… e mais ${linhas.length-6} linhas</td></tr>`;
        html += '</tbody></table>';
        csvPrev.innerHTML = html;
    };
    reader.readAsText(file, 'UTF-8');
}

csvBtn.addEventListener('click', async () => {
    if (!csvFile) return;
    csvBtn.disabled = true; csvBtn.textContent = 'Enviando…'; csvRes.style.display = 'none';
    const fd = new FormData();
    fd.append('csv', csvFile);
    const headers = {};
    if (TOKEN) headers['Authorization'] = 'Bearer ' + TOKEN;
    try {
        const r = await fetch(API + '/leads/importar', { method:'POST', headers, body:fd });
        const j = await r.json();
        csvRes.className   = j.status === 'ok' ? 'crm-res-ok' : 'crm-res-err';
        csvRes.textContent = j.message || 'Erro na importação.';
        csvRes.style.display = 'block';
        if (j.status === 'ok') await carregarFase('novo');
    } catch(_) {
        csvRes.className = 'crm-res-err';
        csvRes.textContent = 'Falha de rede ao importar.';
        csvRes.style.display = 'block';
    }
    csvBtn.disabled = false; csvBtn.textContent = '⬆️ Importar para o Funil';
});

/* ─── Carrega Sortable.js dinamicamente (CDN externo é ignorado
       pelo loader de fragmentos do dashboard) ─────────────────── */
function loadSortable() {
    return new Promise(resolve => {
        if (window.Sortable) { resolve(); return; }
        const s   = document.createElement('script');
        s.src     = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
        s.onload  = resolve;
        s.onerror = resolve; // não bloqueia o resto se a CDN falhar
        document.head.appendChild(s);
    });
}

/* ─── Init ─────────────────────────────────────────────────────── */
(async function init() {
    await Promise.all(fases.map(carregarFase));
    await loadSortable();
    if (window.Sortable) {
        initSortable();
    } else {
        console.warn('[CRM] Sortable.js não carregou — drag & drop desativado.');
    }
})();

})();
</script>
