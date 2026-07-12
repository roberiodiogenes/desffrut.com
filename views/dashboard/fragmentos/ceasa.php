<?php
/**
 * Desffrut — Fragmento Dashboard: Módulo Compras CEASA (Fase 4 · rev.2)
 *
 * Abas: Lista de Compra | Recebimento | Distribuição | Rota Interna
 * Roles: super_admin, gerente
 */

$aba_atual = $_GET['a'] ?? 'lista';
$role      = $u['role'];
$loja_id_u = (int) ($u['loja_id'] ?? 0);

// ── Busca lojas ativas ────────────────────────────────────────────────────────
$lojas_ativas = db()->query("SELECT id, nome FROM lojas WHERE ativo=1 ORDER BY id")->fetchAll();

// Abreviações por loja (iniciais das palavras)
$loja_abrev = [];
foreach ($lojas_ativas as $l) {
    $words = preg_split('/[\s\-]+/', $l['nome']);
    $ab    = implode('', array_map(fn($w) => strtoupper(mb_substr($w, 0, 1)), $words));
    $loja_abrev[$l['id']] = $ab ?: 'L' . $l['id'];
}

// ── Query pivot: todos os produtos com estoque por loja ───────────────────────
$cases_qtd = $cases_min = [];
foreach ($lojas_ativas as $l) {
    $lid = (int) $l['id'];
    $cases_qtd[] = "MAX(CASE WHEN e.loja_id=$lid THEN e.quantidade    ELSE NULL END) AS q_$lid";
    $cases_min[] = "MAX(CASE WHEN e.loja_id=$lid THEN e.estoque_minimo ELSE NULL END) AS m_$lid";
}
$extra = implode(', ', array_merge($cases_qtd, $cases_min));

$todos_produtos = db()->query("
    SELECT
        p.id, p.nome, p.categoria, p.unidade_medida,
        $extra,
        COALESCE(SUM(e.quantidade),    0)  AS estoque_total,
        COALESCE(MAX(e.estoque_minimo),0)  AS minimo_global
    FROM produtos p
    LEFT JOIN estoque e ON e.produto_id = p.id
    WHERE p.ativo = 1
    GROUP BY p.id, p.nome, p.categoria, p.unidade_medida
    ORDER BY p.categoria, p.nome
")->fetchAll();

// Estatísticas do cabeçalho
$cnt_critico = $cnt_baixo = $cnt_ok = $cnt_excesso = 0;
foreach ($todos_produtos as $p) {
    $total = (float)$p['estoque_total'];
    $min   = (float)$p['minimo_global'];
    if ($min <= 0) { $cnt_ok++; continue; }
    $r = $min > 0 ? $total / $min : 99;
    if ($r < 0.5)      $cnt_critico++;
    elseif ($r < 1.0)  $cnt_baixo++;
    elseif ($r < 3.0)  $cnt_ok++;
    else               $cnt_excesso++;
}

// ── Busca frota e colaboradores para o cabeçalho da rota ─────────────────────
$tabela_frota_existe = false;
try {
    db()->query("SELECT 1 FROM frota LIMIT 1");
    $tabela_frota_existe = true;
} catch (Exception $e) { /* migration 18 ainda não rodada */ }

$frota_lista    = $tabela_frota_existe ? db()->query("SELECT id, modelo, cor, placa, documentacao_ok, vencimento_ipva, vencimento_seguro FROM frota WHERE ativo=1 ORDER BY modelo")->fetchAll() : [];
$colab_lista    = $tabela_frota_existe ? db()->query("SELECT id, nome, funcao, telefone FROM ceasa_colaboradores WHERE ativo=1 ORDER BY funcao, nome")->fetchAll() : [];
$motoristas     = array_filter($colab_lista, fn($c) => $c['funcao'] === 'motorista');
$auxiliares     = array_filter($colab_lista, fn($c) => $c['funcao'] === 'auxiliar');

// Rota do dia (última rota da data atual ou mais recente desta semana)
$rota_hoje = null;
if ($tabela_frota_existe) {
    try {
        $rh = db()->query("
            SELECT cr.*, f.modelo AS frota_modelo, f.placa AS frota_placa, f.cor AS frota_cor,
                   f.documentacao_ok, f.vencimento_ipva, f.vencimento_seguro,
                   mot.nome AS motorista_nome, a1.nome AS auxiliar1_nome, a2.nome AS auxiliar2_nome
            FROM ceasa_rotas cr
            LEFT JOIN frota f ON f.id = cr.frota_id
            LEFT JOIN ceasa_colaboradores mot ON mot.id = cr.motorista_id
            LEFT JOIN ceasa_colaboradores a1  ON a1.id  = cr.auxiliar1_id
            LEFT JOIN ceasa_colaboradores a2  ON a2.id  = cr.auxiliar2_id
            WHERE cr.data_rota = CURDATE()
            ORDER BY cr.id DESC LIMIT 1
        ");
        $rota_hoje = $rh->fetch() ?: null;
    } catch (Exception $e) { $rota_hoje = null; }
}

// Histórico de recebimentos recentes
$hist_receb = [];
if ($tabela_frota_existe) {
    try {
        $hr = db()->query("
            SELECT r.id, r.data_recebimento, r.total_itens, r.total_recebidos, r.status, r.created_at,
                   l.nome AS loja_nome, u.nome AS responsavel_nome
            FROM ceasa_recebimentos r
            JOIN lojas l ON l.id = r.loja_id
            LEFT JOIN usuarios u ON u.id = r.responsavel_id
            ORDER BY r.data_recebimento DESC, r.id DESC
            LIMIT 20
        ");
        $hist_receb = $hr->fetchAll();
    } catch (Exception $e) { $hist_receb = []; }
}

// Histórico de todas as rotas CEASA (para aba Rota → sub-aba Histórico)
$hist_rotas = [];
if ($tabela_frota_existe) {
    try {
        // Tenta com colunas novas (migration 19)
        $q_hr = db()->query("
            SELECT cr.id, cr.data_rota, cr.rota_descricao, cr.status,
                   cr.concluida_em, cr.houve_atraso, cr.motivo_atraso, cr.observacoes_conclusao,
                   f.modelo AS frota_modelo, f.placa AS frota_placa,
                   mot.nome AS motorista_nome,
                   a1.nome AS auxiliar1_nome, a2.nome AS auxiliar2_nome
            FROM ceasa_rotas cr
            LEFT JOIN frota f ON f.id = cr.frota_id
            LEFT JOIN ceasa_colaboradores mot ON mot.id = cr.motorista_id
            LEFT JOIN ceasa_colaboradores a1  ON a1.id = cr.auxiliar1_id
            LEFT JOIN ceasa_colaboradores a2  ON a2.id = cr.auxiliar2_id
            ORDER BY cr.data_rota DESC, cr.id DESC
            LIMIT 60
        ");
        $hist_rotas = $q_hr->fetchAll();
    } catch (Throwable $_) {
        try {
            // Fallback sem colunas de conclusão (antes da migration 19)
            $q_hr2 = db()->query("
                SELECT cr.id, cr.data_rota, cr.rota_descricao, cr.status,
                       NULL AS concluida_em, 0 AS houve_atraso,
                       NULL AS motivo_atraso, NULL AS observacoes_conclusao,
                       f.modelo AS frota_modelo, f.placa AS frota_placa,
                       mot.nome AS motorista_nome,
                       a1.nome AS auxiliar1_nome, a2.nome AS auxiliar2_nome
                FROM ceasa_rotas cr
                LEFT JOIN frota f ON f.id = cr.frota_id
                LEFT JOIN ceasa_colaboradores mot ON mot.id = cr.motorista_id
                LEFT JOIN ceasa_colaboradores a1  ON a1.id = cr.auxiliar1_id
                LEFT JOIN ceasa_colaboradores a2  ON a2.id = cr.auxiliar2_id
                ORDER BY cr.data_rota DESC, cr.id DESC
                LIMIT 60
            ");
            $hist_rotas = $q_hr2->fetchAll();
        } catch (Throwable $_) { $hist_rotas = []; }
    }
}

// Hoje — vencimentos de documentos
$hoje_ts = strtotime('today');
foreach ($frota_lista as &$vei) {
    $ipva_ts    = $vei['vencimento_ipva']    ? strtotime($vei['vencimento_ipva'])    : null;
    $seguro_ts  = $vei['vencimento_seguro']  ? strtotime($vei['vencimento_seguro'])  : null;
    $vei['alerta_docs'] = (
        !$vei['documentacao_ok']
        || ($ipva_ts   && $ipva_ts   < $hoje_ts + 30 * 86400)
        || ($seguro_ts && $seguro_ts < $hoje_ts + 30 * 86400)
    );
}
unset($vei);

$lojas_dist = $lojas_ativas;

// Dia da semana
$dias_pt = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$dia_semana = $dias_pt[date('w')];
?>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- ESTILOS                                                                   -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<style data-frag="ceasa">
/* ── Card Rota do Dia ── */
.rota-dia-card {
    background:#fff; border:1.5px solid #e0e0e0; border-radius:10px;
    padding:13px 16px; margin-bottom:14px;
    display:flex; flex-wrap:wrap; align-items:center; gap:10px;
}
.rota-dia-card.configurada { border-color:#a5d6a7; background:#f1f8f2; }
.rota-dia-card.alerta      { border-color:#fca5a5; background:#fff5f5; }
.rota-info-col { display:flex; flex-direction:column; gap:2px; }
.rota-info-col .rota-lbl  { font-size:.66rem; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; font-weight:700; }
.rota-info-col .rota-val  { font-size:.9rem; font-weight:600; color:#212529; }
.rota-sep { width:1px; height:36px; background:#e0e0e0; flex-shrink:0; }
.rota-alert-badge { background:#fef3c7; border:1px solid #d97706; color:#92400e; border-radius:6px; padding:3px 8px; font-size:.72rem; font-weight:700; }
.rota-ok-badge    { background:#d1fae5; border:1px solid #34d399; color:#065f46; border-radius:6px; padding:3px 8px; font-size:.72rem; font-weight:700; }

/* ── Modal frota ── */
#modal-frota-backdrop {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.45); z-index:1050;
    align-items:center; justify-content:center;
}
#modal-frota-backdrop.aberto { display:flex; }
#modal-frota { background:#fff; border-radius:12px; padding:24px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,.18); }
.frota-tab-btns { display:flex; gap:4px; margin-bottom:16px; }
.frota-tab-btn  { flex:1; padding:7px; border:1.5px solid #e0e0e0; border-radius:8px; background:#f9f9f9; cursor:pointer; font-size:.83rem; font-weight:600; color:#555; }
.frota-tab-btn.ativo { border-color:#2e7d32; background:#e8f5e9; color:#1b5e20; }
.frota-panel { display:none; }
.frota-panel.ativo { display:block; }
.frota-item { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:8px; padding:10px 13px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
.frota-item-info small { color:#9ca3af; font-size:.72rem; }
.doc-ok   { color:#16a34a; font-size:.75rem; }
.doc-alert { color:#d97706; font-size:.75rem; font-weight:700; }

/* ── Layout geral ── */
.cc-wrap { padding: 0; }
.cc-header {
    background: linear-gradient(135deg,#1b5e20,#2e7d32);
    color:#fff; padding:16px 22px; border-radius:10px;
    margin-bottom:16px; display:flex; flex-wrap:wrap;
    align-items:center; gap:14px;
}
.cc-header-title { font-size:1.05rem; font-weight:700; margin:0; }
.cc-header-sub   { font-size:.8rem; opacity:.85; margin:2px 0 0; }
.cc-header-stats { margin-left:auto; display:flex; gap:10px; flex-wrap:wrap; }
.cc-stat {
    background:rgba(255,255,255,.14); border-radius:8px;
    padding:6px 14px; text-align:center; min-width:72px;
}
.cc-stat-num  { font-size:1.25rem; font-weight:800; line-height:1; }
.cc-stat-lbl  { font-size:.64rem; opacity:.82; text-transform:uppercase; letter-spacing:.5px; }

/* ── Tabs ── */
.ceasa-tabs { display:flex; gap:2px; border-bottom:2px solid #e0e0e0; margin-bottom:18px; overflow-x:auto; }
.ceasa-tab  { background:none; border:none; padding:9px 16px; font-size:.85rem;
              color:#666; cursor:pointer; border-bottom:2px solid transparent;
              margin-bottom:-2px; white-space:nowrap; font-weight:500; transition:color .12s; }
.ceasa-tab:hover { color:#2e7d32; }
.ceasa-tab.active { color:#2e7d32; border-bottom-color:#2e7d32; font-weight:700; }
.ceasa-panel { display:none; }
.ceasa-panel.active { display:block; }

/* ── Barra de controles ── */
.cc-controls {
    display:flex; flex-wrap:wrap; gap:8px;
    align-items:center; margin-bottom:12px;
}
.cc-search {
    border:1.5px solid #e0e0e0; border-radius:8px;
    padding:6px 12px; font-size:.85rem; flex:1; min-width:160px; max-width:240px;
}
.cc-search:focus { border-color:#2e7d32; outline:none; }
.cc-filter-group { display:flex; gap:4px; flex-wrap:wrap; }
.cc-filter-btn {
    padding:5px 12px; border-radius:20px; font-size:.76rem; font-weight:600;
    border:1.5px solid #e0e0e0; background:#fff; cursor:pointer;
    transition:all .12s; white-space:nowrap;
}
.cc-filter-btn:hover    { border-color:#888; }
.cc-filter-btn.ativo    { border-color:#2e7d32; background:#e8f5e9; color:#1b5e20; }
.cc-filter-btn.f-critico.ativo { border-color:#dc2626; background:#fee2e2; color:#991b1b; }
.cc-filter-btn.f-baixo.ativo   { border-color:#d97706; background:#fef3c7; color:#92400e; }
.cc-filter-btn.f-excesso.ativo { border-color:#2563eb; background:#dbeafe; color:#1e40af; }

/* ── Legenda ── */
.cc-legenda {
    display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; font-size:.74rem;
}
.cc-leg-item { display:flex; align-items:center; gap:5px; }
.cc-leg-dot  { width:11px; height:11px; border-radius:3px; flex-shrink:0; }
.cc-leg-dot.critico  { background:#fca5a5; border:1px solid #dc2626; }
.cc-leg-dot.baixo    { background:#fcd34d; border:1px solid #d97706; }
.cc-leg-dot.ok       { background:#bbf7d0; border:1px solid #16a34a; }
.cc-leg-dot.excesso  { background:#bfdbfe; border:1px solid #2563eb; }
.cc-leg-dot.semmin   { background:#e5e7eb; border:1px solid #9ca3af; }

/* ── Tabela planilha ── */
.cc-table-wrap {
    overflow-x:auto; border:1px solid #e5e7eb; border-radius:10px;
    max-height:72vh; overflow-y:auto;
}
.cc-table {
    width:100%; border-collapse:collapse; font-size:.8rem;
    table-layout:fixed;
}
.cc-table thead th {
    position:sticky; top:0; z-index:2;
    background:#f9fafb; padding:8px 10px; font-size:.7rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px; color:#555;
    border-bottom:2px solid #e0e0e0; white-space:nowrap;
    border-right:1px solid #e5e7eb;
}
.cc-table thead th:first-child { border-radius:10px 0 0 0; }
.cc-table thead th:last-child  { border-radius:0 10px 0 0; border-right:none; }

.cc-table tbody td {
    padding:7px 9px; border-bottom:1px solid #f0f0f0;
    border-right:1px solid #f5f5f5; vertical-align:middle;
    overflow:hidden; text-overflow:ellipsis;
}
.cc-table tbody td:last-child { border-right:none; }
.cc-table tbody tr:last-child td { border-bottom:none; }
.cc-table tbody tr:hover td { filter:brightness(.97); }

/* Larguras fixas */
.col-idx    { width:38px; text-align:center; }
.col-nome   { width:160px; min-width:120px; }
.col-cat    { width:80px; }
.col-loja   { width:65px; text-align:right; }
.col-total  { width:72px; text-align:right; }
.col-min    { width:68px; text-align:right; }
.col-sug    { width:72px; text-align:right; }
.col-compra { width:78px; }
.col-obs    { width:130px; min-width:100px; }

/* Cores de linha por status */
.row-critico td { background:#fef2f2; }
.row-baixo   td { background:#fffbeb; }
.row-ok      td { background:#f0fdf4; }
.row-excesso td { background:#eff6ff; }
.row-semmin  td { background:#f9fafb; }

/* Status dot */
.status-dot {
    display:inline-block; width:8px; height:8px; border-radius:50%;
    margin-right:5px; flex-shrink:0;
}
.dot-critico { background:#dc2626; }
.dot-baixo   { background:#d97706; }
.dot-ok      { background:#16a34a; }
.dot-excesso { background:#2563eb; }
.dot-semmin  { background:#9ca3af; }

/* Valor por loja */
.loja-val { font-size:.8rem; font-weight:600; }
.loja-val.critico { color:#dc2626; }
.loja-val.baixo   { color:#d97706; }
.loja-val.ok      { color:#16a34a; }
.loja-val.excesso { color:#2563eb; }
.loja-val.null    { color:#d1d5db; }

/* Input a comprar */
.input-compra {
    width:100%; text-align:right; border:1.5px solid #e0e0e0; border-radius:6px;
    padding:4px 6px; font-size:.8rem; background:#fff;
    transition:border-color .12s;
}
.input-compra:focus { border-color:#2e7d32; outline:none; }
.input-compra.tem-valor { border-color:#16a34a; background:#f0fdf4; font-weight:700; }

/* Input obs */
.input-obs {
    width:100%; border:1.5px solid #e0e0e0; border-radius:6px;
    padding:3px 6px; font-size:.74rem; background:#fff;
}
.input-obs:focus { border-color:#aaa; outline:none; }

/* Badge categoria */
.badge-cat { padding:2px 7px; border-radius:10px; font-size:.67rem; font-weight:700; }
.cat-frutas   { background:#fff3cd; color:#92400e; }
.cat-verduras { background:#d1fae5; color:#065f46; }
.cat-legumes  { background:#fee2e2; color:#991b1b; }
.cat-outros   { background:#e5e7eb; color:#374151; }

/* Separador de categoria */
.cat-header td {
    background:#f3f4f6 !important; color:#6b7280; font-size:.7rem;
    font-weight:700; text-transform:uppercase; letter-spacing:.5px;
    padding:4px 10px !important; border-bottom:1px solid #e5e7eb !important;
}

/* Rodapé totais */
.cc-table tfoot td {
    background:#f9fafb; font-size:.78rem; font-weight:700;
    border-top:2px solid #e0e0e0; padding:8px 9px;
    position:sticky; bottom:0;
}

/* Barra de ação inferior */
.cc-actions {
    display:flex; gap:8px; align-items:center; flex-wrap:wrap;
    margin-top:12px; padding-top:10px; border-top:1px solid #e5e7eb;
}
.cc-total-chip {
    background:#e8f5e9; border:1px solid #a5d6a7; border-radius:20px;
    padding:4px 14px; font-size:.82rem; font-weight:700; color:#1b5e20;
}

/* Rodapé de "Recebimento" e "Distribuição" */
.receb-form .form-label { font-size:.82rem; font-weight:600; color:#444; }
.distrib-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.distrib-table thead th {
    background:#f5f5f5; padding:8px 10px; font-size:.74rem; font-weight:700;
    text-transform:uppercase; color:#555; border-bottom:2px solid #e0e0e0;
}
.distrib-table tbody td { padding:7px 10px; border-bottom:1px solid #f0f0f0; }
.distrib-input { width:70px; text-align:right; border:1px solid #ced4da; border-radius:4px; padding:3px 6px; }

.rota-card {
    background:#fff; border:1px solid #e0e0e0; border-radius:8px;
    padding:13px 16px; margin-bottom:10px; display:flex; align-items:center; gap:14px;
}
.rota-seq { font-size:1.3rem; font-weight:700; color:#2e7d32; min-width:36px; text-align:center; }

/* ── Distribuição redesign ── */
.distrib-wrap { overflow-x:auto; border:1px solid #e5e7eb; border-radius:10px; max-height:68vh; overflow-y:auto; }
.distrib-table-new { width:100%; border-collapse:collapse; font-size:.81rem; }
.distrib-table-new thead th {
    position:sticky; top:0; z-index:2;
    background:#f9fafb; padding:8px 10px; font-size:.69rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px; color:#555;
    border-bottom:2px solid #e0e0e0; white-space:nowrap;
    border-right:1px solid #e5e7eb;
}
.distrib-table-new thead th:last-child { border-right:none; }
.distrib-table-new tbody td {
    padding:7px 9px; border-bottom:1px solid #f0f0f0;
    border-right:1px solid #f5f5f5; vertical-align:middle;
}
.distrib-table-new tbody td:last-child { border-right:none; }
.distrib-table-new tbody tr:hover td { filter:brightness(.97); }
.distrib-table-new tbody tr.cat-header td {
    background:#f3f4f6 !important; color:#6b7280; font-size:.7rem;
    font-weight:700; text-transform:uppercase; letter-spacing:.5px;
    padding:4px 10px !important; border-bottom:1px solid #e5e7eb !important;
}
.distrib-table-new .row-critico td { background:#fef2f2; }
.distrib-table-new .row-baixo   td { background:#fffbeb; }
.distrib-table-new .row-ok      td { background:#f0fdf4; }
.distrib-chk-done { accent-color:#16a34a; width:16px; height:16px; cursor:pointer; }
.distrib-input-new {
    width:68px; text-align:right; border:1.5px solid #e0e0e0; border-radius:6px;
    padding:3px 6px; font-size:.8rem; background:#fff;
}
.distrib-input-new:focus { border-color:#2e7d32; outline:none; }
.distrib-total-chip {
    background:#e8f5e9; border:1.5px solid #a5d6a7; border-radius:20px;
    padding:4px 12px; font-size:.8rem; font-weight:700; color:#1b5e20;
    display:inline-block;
}

/* ── Rota: status panel e histórico ── */
.rota-status-panel {
    background:#fff; border:1.5px solid #e0e0e0; border-radius:10px;
    padding:13px 16px; margin-bottom:14px;
    display:flex; flex-wrap:wrap; align-items:center; gap:10px;
}
.rota-status-badge {
    display:inline-flex; align-items:center; gap:5px;
    border-radius:20px; padding:4px 12px; font-size:.78rem; font-weight:700;
}
.rota-status-badge.planejada   { background:#fef3c7; color:#92400e; border:1px solid #d97706; }
.rota-status-badge.em_andamento{ background:#dbeafe; color:#1e40af; border:1px solid #3b82f6; }
.rota-status-badge.concluida   { background:#d1fae5; color:#065f46; border:1px solid #34d399; }
.rota-sub-tabs { display:flex; gap:4px; margin-bottom:14px; border-bottom:2px solid #e0e0e0; }
.rota-sub-tab {
    background:none; border:none; padding:8px 14px; font-size:.84rem;
    color:#666; cursor:pointer; border-bottom:2px solid transparent;
    margin-bottom:-2px; white-space:nowrap; font-weight:500;
}
.rota-sub-tab:hover { color:#2e7d32; }
.rota-sub-tab.active { color:#2e7d32; border-bottom-color:#2e7d32; font-weight:700; }
.rota-hist-table { width:100%; border-collapse:collapse; font-size:.8rem; }
.rota-hist-table thead th {
    background:#f9fafb; padding:8px 12px; font-size:.69rem; text-transform:uppercase;
    color:#555; border-bottom:2px solid #e0e0e0; font-weight:700; white-space:nowrap;
}
.rota-hist-table tbody td { padding:7px 12px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.rota-hist-table tbody tr:hover td { background:#f9fafb; }
.rota-hist-status { padding:3px 9px; border-radius:12px; font-size:.72rem; font-weight:700; }
.rota-hist-status.planejada    { background:#fef3c7; color:#92400e; }
.rota-hist-status.em_andamento { background:#dbeafe; color:#1e40af; }
.rota-hist-status.concluida    { background:#d1fae5; color:#065f46; }
.rota-hist-atraso { color:#dc2626; font-size:.72rem; font-weight:700; }

/* Print */
@media print {
    .cc-controls, .cc-actions, .ceasa-tabs { display:none !important; }
    .cc-header { background:#1b5e20 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .cc-table-wrap { max-height:none !important; overflow:visible !important; border:none; }
    .row-critico td { background:#fee2e2 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .row-baixo   td { background:#fef3c7 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .row-ok      td { background:#dcfce7 !important; }
    .row-excesso td { background:#dbeafe !important; }
}

/* Mobile */
@media (max-width:600px) {
    .cc-header-stats { display:none; }
    .col-obs   { display:none; }
    .col-sug   { display:none; }
}
</style>

<div class="cc-wrap">

    <!-- ── Card: Rota do Dia ─────────────────────────────────────────────────── -->
    <?php if (!$tabela_frota_existe): ?>
    <div class="alert alert-info py-2 mb-3" style="font-size:.84rem;">
        ⚠️ Execute a <strong>migration 18</strong> em phpMyAdmin para habilitar Frota e Recebimento avançado.
    </div>
    <?php elseif ($rota_hoje): ?>
    <?php
        $doc_ok  = (int)($rota_hoje['documentacao_ok'] ?? 1);
        $ipva_ok = !$rota_hoje['vencimento_ipva']   || strtotime($rota_hoje['vencimento_ipva'])   >= $hoje_ts;
        $seg_ok  = !$rota_hoje['vencimento_seguro'] || strtotime($rota_hoje['vencimento_seguro']) >= $hoje_ts;
        $tudo_ok = $doc_ok && $ipva_ok && $seg_ok;
    ?>
    <div class="rota-dia-card <?= $tudo_ok ? 'configurada' : 'alerta' ?>">
        <div>
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;font-weight:700;">Rota do Dia</div>
            <div style="font-size:1rem;font-weight:700;color:#1b5e20;">
                <?= $dias_pt[date('w')] ?>, <?= date('d/m/Y') ?>
            </div>
        </div>
        <div class="rota-sep"></div>
        <?php if ($rota_hoje['frota_modelo']): ?>
        <div class="rota-info-col">
            <span class="rota-lbl">🚗 Veículo</span>
            <span class="rota-val"><?= htmlspecialchars($rota_hoje['frota_modelo']) ?> · <?= htmlspecialchars($rota_hoje['frota_cor'] ?? '') ?></span>
            <span style="font-size:.72rem;color:#9ca3af;"><?= htmlspecialchars($rota_hoje['frota_placa'] ?? '') ?></span>
        </div>
        <div class="rota-sep"></div>
        <?php endif; ?>
        <?php if ($rota_hoje['motorista_nome']): ?>
        <div class="rota-info-col">
            <span class="rota-lbl">🧑‍✈️ Motorista</span>
            <span class="rota-val"><?= htmlspecialchars($rota_hoje['motorista_nome']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($rota_hoje['auxiliar1_nome']): ?>
        <div class="rota-sep"></div>
        <div class="rota-info-col">
            <span class="rota-lbl">👷 Auxiliares</span>
            <span class="rota-val"><?= htmlspecialchars($rota_hoje['auxiliar1_nome']) ?>
                <?= $rota_hoje['auxiliar2_nome'] ? ' · ' . htmlspecialchars($rota_hoje['auxiliar2_nome']) : '' ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($rota_hoje['rota_descricao']): ?>
        <div class="rota-sep"></div>
        <div class="rota-info-col">
            <span class="rota-lbl">📍 Rota</span>
            <span class="rota-val"><?= htmlspecialchars($rota_hoje['rota_descricao']) ?></span>
        </div>
        <?php endif; ?>
        <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <?= $tudo_ok
                ? '<span class="rota-ok-badge">✅ Docs OK</span>'
                : '<span class="rota-alert-badge">⚠️ Verificar documentação</span>' ?>
            <button class="btn btn-sm btn-outline-secondary" onclick="abrirModalRota()">✏️ Editar</button>
            <button class="btn btn-sm btn-outline-primary"   onclick="abrirModalFrota()">🚗 Frota</button>
        </div>
    </div>
    <?php else: ?>
    <div class="rota-dia-card" style="background:#fffbeb;border-color:#fcd34d;">
        <div>
            <div style="font-size:.72rem;color:#92400e;font-weight:700;">📋 ROTA DO DIA NÃO CONFIGURADA</div>
            <div style="font-size:.84rem;color:#555;"><?= $dias_pt[date('w')] ?>, <?= date('d/m/Y') ?> — defina veículo, motorista e auxiliares para esta saída.</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;">
            <button class="btn btn-warning btn-sm fw-bold" onclick="abrirModalRota()">+ Configurar Rota</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="abrirModalFrota()">🚗 Gerenciar Frota</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Cabeçalho ──────────────────────────────────────────────────────────── -->
    <div class="cc-header">
        <div>
            <p class="cc-header-title">🛒 Compras CEASA</p>
            <p class="cc-header-sub">
                <?= $dia_semana ?>, <?= date('d/m/Y') ?>
                &nbsp;·&nbsp; Gerado às <?= date('H:i') ?>
                &nbsp;·&nbsp; <?= count($todos_produtos) ?> produtos ativos
            </p>
        </div>
        <div class="cc-header-stats">
            <div class="cc-stat" style="background:rgba(220,38,38,.25);">
                <div class="cc-stat-num"><?= $cnt_critico ?></div>
                <div class="cc-stat-lbl">Críticos</div>
            </div>
            <div class="cc-stat" style="background:rgba(217,119,6,.25);">
                <div class="cc-stat-num"><?= $cnt_baixo ?></div>
                <div class="cc-stat-lbl">Baixo</div>
            </div>
            <div class="cc-stat" style="background:rgba(22,163,74,.25);">
                <div class="cc-stat-num"><?= $cnt_ok ?></div>
                <div class="cc-stat-lbl">OK</div>
            </div>
            <div class="cc-stat" style="background:rgba(37,99,235,.2);">
                <div class="cc-stat-num"><?= $cnt_excesso ?></div>
                <div class="cc-stat-lbl">Excesso</div>
            </div>
        </div>
    </div>

    <!-- ── Tabs ──────────────────────────────────────────────────────────────── -->
    <div class="ceasa-tabs">
        <button class="ceasa-tab <?= $aba_atual==='lista'   ? 'active':'' ?>" onclick="ceasaMostrarAba('lista')">📋 Lista de Compra</button>
        <button class="ceasa-tab <?= $aba_atual==='receb'   ? 'active':'' ?>" onclick="ceasaMostrarAba('receb')">📥 Recebimento</button>
        <button class="ceasa-tab <?= $aba_atual==='distrib' ? 'active':'' ?>" onclick="ceasaMostrarAba('distrib')">📦 Distribuição</button>
        <button class="ceasa-tab <?= $aba_atual==='rota'    ? 'active':'' ?>" onclick="ceasaMostrarAba('rota')">🗺️ Rota</button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ABA: LISTA DE COMPRA                                                   -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="ceasa-panel-lista" class="ceasa-panel <?= $aba_atual==='lista' ? 'active':'' ?>">

        <!-- Controles -->
        <div class="cc-controls">
            <input type="search" class="cc-search" id="cc-busca" placeholder="🔍 Buscar produto…"
                   oninput="ccFiltrar()">

            <div class="cc-filter-group" id="cc-cat-filtros">
                <button class="cc-filter-btn ativo" data-cat="">Todas</button>
                <button class="cc-filter-btn" data-cat="frutas">🍎 Frutas</button>
                <button class="cc-filter-btn" data-cat="verduras">🥬 Verduras</button>
                <button class="cc-filter-btn" data-cat="legumes">🥕 Legumes</button>
                <button class="cc-filter-btn" data-cat="outros">📦 Outros</button>
            </div>

            <div class="cc-filter-group" id="cc-status-filtros">
                <button class="cc-filter-btn ativo" data-status="">Tudo</button>
                <button class="cc-filter-btn f-critico" data-status="critico">🔴 Crítico</button>
                <button class="cc-filter-btn f-baixo"   data-status="baixo">🟡 Baixo</button>
                <button class="cc-filter-btn"           data-status="ok">🟢 OK</button>
                <button class="cc-filter-btn f-excesso" data-status="excesso">🔵 Excesso</button>
            </div>

            <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="window.print()">
                🖨️ Imprimir
            </button>
        </div>

        <!-- Legenda -->
        <div class="cc-legenda">
            <div class="cc-leg-item"><div class="cc-leg-dot critico"></div><span>Crítico — abaixo de 50% do mínimo</span></div>
            <div class="cc-leg-item"><div class="cc-leg-dot baixo"></div><span>Baixo — entre 50% e 100% do mínimo</span></div>
            <div class="cc-leg-item"><div class="cc-leg-dot ok"></div><span>Estoque OK</span></div>
            <div class="cc-leg-item"><div class="cc-leg-dot excesso"></div><span>Excesso — acima de 3× o mínimo</span></div>
            <div class="cc-leg-item"><div class="cc-leg-dot semmin"></div><span>Sem mínimo definido</span></div>
        </div>

        <!-- Tabela planilha -->
        <div class="cc-table-wrap">
        <table class="cc-table" id="cc-tabela">
            <thead>
                <tr>
                    <th class="col-idx">#</th>
                    <th class="col-nome">Produto</th>
                    <th class="col-cat">Categoria</th>
                    <?php foreach ($lojas_ativas as $l): ?>
                    <th class="col-loja" title="<?= htmlspecialchars($l['nome']) ?>">
                        <?= htmlspecialchars($loja_abrev[$l['id']]) ?>
                    </th>
                    <?php endforeach; ?>
                    <th class="col-total text-end">Total</th>
                    <th class="col-min   text-end">Mínimo</th>
                    <th class="col-sug   text-end">Sugerido</th>
                    <th class="col-compra">A comprar</th>
                    <th class="col-obs">Observação</th>
                </tr>
            </thead>
            <tbody id="cc-tbody">
            <?php
            $idx        = 0;
            $cat_atual  = null;
            $labels_cat = [
                'frutas'   => '🍎 Frutas',
                'verduras' => '🥬 Verduras',
                'legumes'  => '🥕 Legumes',
                'outros'   => '📦 Outros',
            ];

            foreach ($todos_produtos as $p):
                $total  = (float) $p['estoque_total'];
                $min    = (float) $p['minimo_global'];
                $un     = $p['unidade_medida'];
                $cat    = $p['categoria'];

                // Status
                if ($min <= 0) {
                    $status = 'semmin';
                } else {
                    $r = $total / $min;
                    $status = $r < 0.5 ? 'critico' : ($r < 1.0 ? 'baixo' : ($r < 3.0 ? 'ok' : 'excesso'));
                }

                // Qtd sugerida
                $sugerida = 0;
                if ($status === 'critico' || $status === 'baixo') {
                    $sugerida = max(0, round(($min * 2) - $total, 3));
                }

                // Cabeçalho de categoria
                if ($cat !== $cat_atual):
                    $cat_atual = $cat;
                    $colspan   = 6 + count($lojas_ativas);
            ?>
            <tr class="cat-header" data-cat="<?= $cat ?>" data-status="">
                <td colspan="<?= $colspan ?>">
                    <?= $labels_cat[$cat] ?? ucfirst($cat) ?>
                </td>
            </tr>
            <?php endif; $idx++; ?>
            <tr class="row-<?= $status ?>" data-cat="<?= $cat ?>" data-status="<?= $status ?>" data-nome="<?= strtolower(htmlspecialchars($p['nome'])) ?>">
                <td class="col-idx text-center text-muted"><?= $idx ?></td>
                <td class="col-nome">
                    <span class="status-dot dot-<?= $status ?>"></span>
                    <span title="<?= htmlspecialchars($p['nome']) ?>"><?= htmlspecialchars($p['nome']) ?></span>
                </td>
                <td class="col-cat">
                    <span class="badge-cat cat-<?= $cat ?>"><?= ucfirst($cat) ?></span>
                </td>

                <?php foreach ($lojas_ativas as $l):
                    $lid = $l['id'];
                    $qv  = isset($p["q_$lid"]) && $p["q_$lid"] !== null ? (float) $p["q_$lid"] : null;
                    // Status individual por loja
                    $minv = isset($p["m_$lid"]) && $p["m_$lid"] !== null ? (float) $p["m_$lid"] : 0;
                    if ($qv === null) {
                        $lcls = 'null';
                        $ltxt = '—';
                    } else {
                        $ltxt = $un === 'kg' ? number_format($qv, 1, ',', '.') : number_format($qv, 0);
                        if ($minv <= 0) $lcls = 'ok';
                        else {
                            $lr   = $qv / $minv;
                            $lcls = $lr < 0.5 ? 'critico' : ($lr < 1.0 ? 'baixo' : ($lr < 3.0 ? 'ok' : 'excesso'));
                        }
                    }
                ?>
                <td class="col-loja">
                    <span class="loja-val <?= $lcls ?>"><?= $ltxt ?></span>
                    <?php if ($qv !== null): ?>
                    <div style="font-size:.6rem;color:#9ca3af;line-height:1;"><?= $un ?></div>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>

                <!-- Total -->
                <td class="col-total text-end fw-semibold <?= $status === 'critico' ? 'text-danger' : ($status === 'baixo' ? 'text-warning-emphasis' : '') ?>">
                    <?= $un === 'kg' ? number_format($total, 1, ',', '.') : number_format($total, 0) ?>
                    <div style="font-size:.6rem;color:#9ca3af;"><?= $un ?></div>
                </td>

                <!-- Mínimo -->
                <td class="col-min text-end text-muted" style="font-size:.75rem;">
                    <?= $min > 0 ? ($un === 'kg' ? number_format($min, 1, ',', '.') : number_format($min, 0)) : '—' ?>
                    <?php if ($min > 0): ?><div style="font-size:.6rem;color:#9ca3af;"><?= $un ?></div><?php endif; ?>
                </td>

                <!-- Sugerido -->
                <td class="col-sug text-end" style="font-size:.78rem; color:#2e7d32; font-weight:<?= $sugerida > 0 ? '700' : '400' ?>;">
                    <?= $sugerida > 0 ? ($un === 'kg' ? number_format($sugerida, 1, ',', '.') : number_format($sugerida, 0)) : '<span style="color:#d1d5db">—</span>' ?>
                    <?php if ($sugerida > 0): ?><div style="font-size:.6rem;color:#9ca3af;"><?= $un ?></div><?php endif; ?>
                </td>

                <!-- A comprar (editável) -->
                <td class="col-compra">
                    <input type="number" class="input-compra"
                           data-produto-id="<?= $p['id'] ?>"
                           data-nome="<?= htmlspecialchars($p['nome']) ?>"
                           data-unidade="<?= $un ?>"
                           data-sugerida="<?= $sugerida ?>"
                           min="0" step="<?= $un === 'kg' ? '0.1' : '1' ?>"
                           value="<?= $sugerida > 0 ? ($un === 'kg' ? number_format($sugerida, 1, '.', '') : $sugerida) : '' ?>"
                           placeholder="0"
                           oninput="ccAtualizarTotal(this)">
                </td>

                <!-- Observação (editável) -->
                <td class="col-obs">
                    <input type="text" class="input-obs"
                           placeholder="obs…" maxlength="120">
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="<?= 3 + count($lojas_ativas) ?>" class="text-muted">
                        Total a comprar:
                    </td>
                    <td colspan="3"></td>
                    <td id="cc-total-geral" colspan="2" class="text-end text-success" style="font-size:.85rem;">—</td>
                </tr>
            </tfoot>
        </table>
        </div>

        <!-- Ações -->
        <div class="cc-actions">
            <span class="text-muted small">
                <span id="cc-visible-count"><?= count($todos_produtos) ?></span> produto(s) visíveis
            </span>
            <button class="btn btn-sm btn-outline-secondary" onclick="ccZerarCompras()">
                🔄 Limpar quantidades
            </button>
            <button class="btn btn-sm btn-outline-success" onclick="ccPreencherSugerido()">
                ✨ Usar qtd sugerida
            </button>
            <button class="btn btn-success btn-sm ms-auto" onclick="ccSalvarLista()">
                💾 Salvar lista (PDF / Imprimir)
            </button>
        </div>

    </div><!-- /lista -->


    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ABA: RECEBIMENTO                                                       -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="ceasa-panel-receb" class="ceasa-panel <?= $aba_atual==='receb' ? 'active':'' ?>">

        <!-- Sub-abas: Registrar | Histórico -->
        <div style="display:flex;gap:4px;margin-bottom:14px;">
            <button class="frota-tab-btn ativo" id="receb-sub-registrar" onclick="recebSubAba('registrar')">📥 Registrar Recebimento</button>
            <button class="frota-tab-btn"       id="receb-sub-historico" onclick="recebSubAba('historico')">📋 Histórico</button>
        </div>

        <!-- ── Sub: Registrar ── -->
        <div id="receb-painel-registrar">
            <div id="receb-msg" class="alert d-none mb-3"></div>

            <!-- Filtro por categoria + busca -->
            <div class="cc-controls mb-2">
                <div class="cc-filter-group" id="receb-cat-filtros">
                    <button class="cc-filter-btn ativo" data-cat="">Todas</button>
                    <button class="cc-filter-btn" data-cat="frutas">🍎 Frutas</button>
                    <button class="cc-filter-btn" data-cat="verduras">🥬 Verduras</button>
                    <button class="cc-filter-btn" data-cat="legumes">🥕 Legumes</button>
                    <button class="cc-filter-btn" data-cat="outros">📦 Outros</button>
                </div>
                <input type="search" class="cc-search" id="receb-busca" placeholder="🔍 Buscar produto…"
                       oninput="recebFiltrar()">
                <span class="text-muted small ms-auto"><span id="receb-visible-count"></span></span>
            </div>

            <!-- Cabeçalho do recebimento -->
            <div class="row g-3 mb-3" style="max-width:600px;">
                <?php if ($role === 'super_admin'): ?>
                <div class="col-sm-5">
                    <label class="form-label fw-semibold" style="font-size:.82rem;">Loja destino</label>
                    <select id="receb-loja" class="form-select form-select-sm">
                        <?php foreach ($lojas_ativas as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" id="receb-loja" value="<?= $loja_id_u ?>">
                <?php endif; ?>
                <div class="col-sm-4">
                    <label class="form-label fw-semibold" style="font-size:.82rem;">Data</label>
                    <input type="date" id="receb-data" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <?php if ($tabela_frota_existe && $rota_hoje): ?>
                <div class="col-sm-3">
                    <label class="form-label fw-semibold" style="font-size:.82rem;">Rota</label>
                    <div class="form-control-sm bg-light border rounded px-2 py-1" style="font-size:.8rem;">
                        <?= htmlspecialchars($rota_hoje['rota_descricao'] ?: 'Rota do dia') ?>
                    </div>
                    <input type="hidden" id="receb-rota-id" value="<?= $rota_hoje['id'] ?>">
                </div>
                <?php else: ?>
                <input type="hidden" id="receb-rota-id" value="">
                <?php endif; ?>
            </div>

            <!-- Tabela de recebimento estilo planilha -->
            <p class="text-muted small mb-2">
                Abaixo estão <strong>todos os produtos</strong> com as quantidades sugeridas pré-preenchidas.
                Marque o que foi recebido, ajuste quantidades e informe quebras ou não-entregas.
            </p>

            <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:10px;max-height:65vh;overflow-y:auto;">
            <table class="cc-table" id="receb-tabela">
                <thead>
                    <tr>
                        <th style="width:30px;text-align:center;">✓</th>
                        <th style="min-width:150px;">Produto</th>
                        <th style="width:65px;text-align:center;">Cat.</th>
                        <th style="width:80px;text-align:right;">Sugerido</th>
                        <th style="width:90px;text-align:right;">Recebido</th>
                        <th style="width:80px;text-align:right;">Quebra</th>
                        <th style="width:80px;text-align:center;">Não ent.</th>
                        <th style="min-width:110px;">Observação</th>
                    </tr>
                </thead>
                <tbody id="receb-tbody">
                <?php foreach ($todos_produtos as $p):
                    $total_p = (float) $p['estoque_total'];
                    $min_p   = (float) $p['minimo_global'];
                    $sug_p   = ($min_p > 0 && $total_p < $min_p * 2)
                        ? max(0, round(($min_p * 2) - $total_p, 3))
                        : 0;
                    $status_p = $min_p > 0 ? ($total_p/$min_p < 0.5 ? 'critico' : ($total_p/$min_p < 1 ? 'baixo' : 'ok')) : 'ok';
                ?>
                <tr class="receb-linha row-<?= $status_p ?>"
                    data-produto-id="<?= $p['id'] ?>"
                    data-un="<?= $p['unidade_medida'] ?>"
                    data-sugerida="<?= $sug_p ?>"
                    data-preco="0"
                    data-cat="<?= $p['categoria'] ?>"
                    data-nome="<?= strtolower(htmlspecialchars($p['nome'])) ?>">
                    <td style="text-align:center;">
                        <input type="checkbox" class="form-check-input receb-chk"
                               <?= $sug_p > 0 ? 'checked' : '' ?>
                               onchange="recebToggleLinha(this)">
                    </td>
                    <td>
                        <span class="status-dot dot-<?= $status_p ?>"></span>
                        <?= htmlspecialchars($p['nome']) ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-cat cat-<?= $p['categoria'] ?>"><?= ucfirst($p['categoria']) ?></span>
                    </td>
                    <td style="text-align:right;font-size:.78rem;color:#6b7280;">
                        <?= $sug_p > 0 ? ($p['unidade_medida']==='kg' ? number_format($sug_p,1,',','.') : (int)$sug_p) : '—' ?>
                        <div style="font-size:.6rem;color:#9ca3af;"><?= $p['unidade_medida'] ?></div>
                    </td>
                    <td>
                        <input type="number" class="input-compra receb-qtd-rec"
                               value="<?= $sug_p > 0 ? ($p['unidade_medida']==='kg' ? number_format($sug_p,3,'.','') : (int)$sug_p) : '' ?>"
                               min="0" step="<?= $p['unidade_medida']==='kg' ? '0.001' : '1' ?>"
                               placeholder="0">
                    </td>
                    <td>
                        <input type="number" class="input-compra receb-qtd-quebra"
                               value="0" min="0" step="<?= $p['unidade_medida']==='kg' ? '0.001' : '1' ?>"
                               placeholder="0">
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" class="form-check-input receb-nao-entregue"
                               title="Não foi entregue"
                               onchange="recebToggleNaoEntregue(this)">
                    </td>
                    <td>
                        <input type="text" class="input-obs receb-obs" placeholder="obs…" maxlength="120">
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Observações gerais -->
            <div class="mt-3" style="max-width:500px;">
                <label class="form-label fw-semibold" style="font-size:.82rem;">Observações gerais</label>
                <textarea id="receb-obs-gerais" class="form-control form-control-sm" rows="2" maxlength="500"
                          placeholder="Anotações sobre o recebimento deste dia…"></textarea>
            </div>

            <div class="d-flex gap-2 align-items-center mt-3">
                <button class="btn btn-sm btn-outline-secondary" onclick="recebDesmarcarTodos()">☐ Desmarcar todos</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="recebMarcarCriticos()">🔴 Só críticos/baixo</button>
                <button class="btn btn-success btn-sm ms-auto fw-bold px-4" onclick="ceasaConfirmarRecebimentoNovo()">
                    ✅ Confirmar Recebimento
                </button>
            </div>
        </div>

        <!-- ── Sub: Histórico ── -->
        <div id="receb-painel-historico" style="display:none;">
            <p class="text-muted small mb-3">Clique em um registro para ver os detalhes completos.</p>
            <?php if (empty($hist_receb)): ?>
            <div class="text-center text-muted py-4">Nenhum recebimento registrado ainda.</div>
            <?php else: ?>
            <div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="padding:8px 12px;font-size:.7rem;text-transform:uppercase;color:#555;border-bottom:2px solid #e0e0e0;font-weight:700;">Data</th>
                        <th style="padding:8px 12px;font-size:.7rem;text-transform:uppercase;color:#555;border-bottom:2px solid #e0e0e0;font-weight:700;">Loja</th>
                        <th style="padding:8px 12px;font-size:.7rem;text-transform:uppercase;color:#555;border-bottom:2px solid #e0e0e0;font-weight:700;text-align:center;">Itens</th>
                        <th style="padding:8px 12px;font-size:.7rem;text-transform:uppercase;color:#555;border-bottom:2px solid #e0e0e0;font-weight:700;">Responsável</th>
                        <th style="padding:8px 12px;font-size:.7rem;text-transform:uppercase;color:#555;border-bottom:2px solid #e0e0e0;font-weight:700;text-align:center;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($hist_receb as $hr): ?>
                <tr style="border-bottom:1px solid #f0f0f0;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background=''">
                    <td style="padding:8px 12px;">
                        <?= date('d/m/Y', strtotime($hr['data_recebimento'])) ?>
                        <div style="font-size:.68rem;color:#9ca3af;"><?= date('H:i', strtotime($hr['created_at'])) ?></div>
                    </td>
                    <td style="padding:8px 12px;"><?= htmlspecialchars($hr['loja_nome']) ?></td>
                    <td style="padding:8px 12px;text-align:center;">
                        <span style="font-weight:700;color:#16a34a;"><?= $hr['total_recebidos'] ?></span>
                        <span style="color:#9ca3af;font-size:.72rem;">/ <?= $hr['total_itens'] ?></span>
                    </td>
                    <td style="padding:8px 12px;font-size:.78rem;"><?= htmlspecialchars($hr['responsavel_nome'] ?? '—') ?></td>
                    <td style="padding:8px 12px;text-align:center;">
                        <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesRecebimento(<?= $hr['id'] ?>)">
                            📄 Detalhes
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /receb -->

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ABA: DISTRIBUIÇÃO                                                      -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="ceasa-panel-distrib" class="ceasa-panel <?= $aba_atual==='distrib' ? 'active':'' ?>">
        <?php
        // Busca todos os produtos que precisam de distribuição (abaixo do mínimo)
        // com informação de estoque por loja
        $cases_qtd_d = $cases_min_d = [];
        foreach ($lojas_ativas as $l) {
            $lid = (int) $l['id'];
            $cases_qtd_d[] = "MAX(CASE WHEN e.loja_id=$lid THEN e.quantidade    ELSE NULL END) AS q_$lid";
            $cases_min_d[] = "MAX(CASE WHEN e.loja_id=$lid THEN e.estoque_minimo ELSE NULL END) AS m_$lid";
        }
        $extra_d = implode(', ', array_merge($cases_qtd_d, $cases_min_d));

        $dist_produtos = db()->query("
            SELECT * FROM (
                SELECT p.id, p.nome, p.categoria, p.unidade_medida,
                       $extra_d,
                       COALESCE(SUM(e.quantidade),0)     AS estoque_total,
                       COALESCE(MAX(e.estoque_minimo),0) AS min_global
                FROM produtos p
                LEFT JOIN estoque e ON e.produto_id = p.id
                WHERE p.ativo = 1
                GROUP BY p.id, p.nome, p.categoria, p.unidade_medida
            ) AS sub
            WHERE estoque_total < min_global AND min_global > 0
            ORDER BY categoria,
                     (estoque_total / NULLIF(min_global, 0)) ASC,
                     nome
        ")->fetchAll();

        $labels_cat_d = [
            'frutas'   => '🍎 Frutas',
            'verduras' => '🥬 Verduras',
            'legumes'  => '🥕 Legumes',
            'outros'   => '📦 Outros',
        ];
        // Categorias presentes
        $cats_dist = array_unique(array_column($dist_produtos, 'categoria'));
        ?>

        <?php if (empty($dist_produtos)): ?>
        <div class="text-center text-muted py-5">
            <div style="font-size:2rem;">✅</div>
            <div class="fw-semibold mt-2">Todos os estoques estão acima do mínimo.</div>
            <div class="small mt-1">Nenhum produto precisa de distribuição no momento.</div>
        </div>
        <?php else: ?>

        <!-- Filtros + busca -->
        <div class="cc-controls mb-2">
            <div class="cc-filter-group" id="distrib-cat-filtros">
                <button class="cc-filter-btn ativo" data-cat="" onclick="distribFiltrar('')">Todas
                    <span class="text-muted" style="font-size:.68rem;">(<?= count($dist_produtos) ?>)</span>
                </button>
                <?php foreach ($cats_dist as $cd): ?>
                <?php $cnt_cd = count(array_filter($dist_produtos, fn($p) => $p['categoria'] === $cd)); ?>
                <button class="cc-filter-btn" data-cat="<?= $cd ?>" onclick="distribFiltrar('<?= $cd ?>')">
                    <?= $labels_cat_d[$cd] ?? ucfirst($cd) ?>
                    <span class="text-muted" style="font-size:.68rem;">(<?= $cnt_cd ?>)</span>
                </button>
                <?php endforeach; ?>
            </div>
            <input type="search" class="cc-search" id="distrib-busca" placeholder="🔍 Buscar produto…"
                   oninput="distribFiltrar()">
        </div>

        <!-- Legenda rápida -->
        <div class="cc-legenda mb-2">
            <div class="cc-leg-item"><div class="cc-leg-dot critico"></div><span>Crítico (&lt;50% mín.)</span></div>
            <div class="cc-leg-item"><div class="cc-leg-dot baixo"></div><span>Baixo (50–100%)</span></div>
            <div class="cc-leg-item" style="margin-left:auto;">
                <span class="text-muted small">☑ = marcado como distribuído</span>
            </div>
        </div>

        <div id="distrib-msg" class="alert d-none mb-3"></div>

        <div class="distrib-wrap">
        <table class="distrib-table-new" id="distrib-tabela">
            <thead>
                <tr>
                    <th style="width:30px;text-align:center;">☑</th>
                    <th style="min-width:150px;">Produto</th>
                    <th style="width:75px;">Categoria</th>
                    <th style="width:80px;text-align:right;">Total</th>
                    <th style="width:72px;text-align:right;">Mín.</th>
                    <?php foreach ($lojas_ativas as $l): ?>
                    <th style="width:80px;text-align:center;" title="<?= htmlspecialchars($l['nome']) ?>">
                        ➡ <?= htmlspecialchars($loja_abrev[$l['id']]) ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="distrib-tbody">
            <?php
            $cat_atual_d = null;
            foreach ($dist_produtos as $c):
                $cat_d   = $c['categoria'];
                $total_d = (float) $c['estoque_total'];
                $min_d   = (float) $c['min_global'];
                $ratio_d = $min_d > 0 ? $total_d / $min_d : 99;
                $st_d    = $ratio_d < 0.5 ? 'critico' : 'baixo';

                if ($cat_d !== $cat_atual_d):
                    $cat_atual_d = $cat_d;
                    $colspan_d   = 5 + count($lojas_ativas);
            ?>
            <tr class="cat-header" data-cat="<?= $cat_d ?>">
                <td colspan="<?= $colspan_d ?>"><?= $labels_cat_d[$cat_d] ?? ucfirst($cat_d) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="row-<?= $st_d ?>"
                data-cat="<?= $cat_d ?>"
                data-nome="<?= strtolower(htmlspecialchars($c['nome'])) ?>">
                <td style="text-align:center;">
                    <input type="checkbox" class="distrib-chk-done"
                           title="Marcar como distribuído"
                           onchange="distribToggleFeito(this)">
                </td>
                <td>
                    <span class="status-dot dot-<?= $st_d ?>"></span>
                    <span class="fw-semibold"><?= htmlspecialchars($c['nome']) ?></span>
                    <div style="font-size:.65rem;color:#9ca3af;"><?= $c['unidade_medida'] ?></div>
                </td>
                <td>
                    <span class="badge-cat cat-<?= $cat_d ?>"><?= ucfirst($cat_d) ?></span>
                </td>
                <td style="text-align:right;" class="<?= $st_d === 'critico' ? 'text-danger' : 'text-warning-emphasis' ?> fw-semibold">
                    <?= number_format($total_d, $c['unidade_medida']==='kg'?1:0, ',', '.') ?>
                </td>
                <td style="text-align:right;color:#9ca3af;font-size:.78rem;">
                    <?= number_format($min_d, $c['unidade_medida']==='kg'?1:0, ',', '.') ?>
                </td>
                <?php foreach ($lojas_ativas as $l):
                    $lid_d = $l['id'];
                    $q_l   = isset($c["q_$lid_d"]) && $c["q_$lid_d"] !== null ? (float)$c["q_$lid_d"] : null;
                    $m_l   = isset($c["m_$lid_d"]) && $c["m_$lid_d"] !== null ? (float)$c["m_$lid_d"] : 0;
                    // Status individual desta loja
                    $st_l  = ($q_l === null) ? '' : (($m_l > 0 && $q_l / $m_l < 0.5) ? 'text-danger' : (($m_l > 0 && $q_l / $m_l < 1) ? 'text-warning-emphasis' : 'text-success'));
                ?>
                <td style="text-align:center;">
                    <div style="font-size:.7rem;<?= $st_l ? 'color:inherit;' : '' ?>" class="<?= $st_l ?>">
                        <?= $q_l !== null ? number_format($q_l, $c['unidade_medida']==='kg'?1:0, ',', '.') : '—' ?>
                    </div>
                    <input type="number" class="distrib-input-new"
                           data-produto-id="<?= $c['id'] ?>"
                           data-loja-id="<?= $l['id'] ?>"
                           placeholder="0" min="0"
                           step="<?= $c['unidade_medida']==='kg'?'0.001':'1' ?>"
                           oninput="distribAtualizarTotal()">
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Ações -->
        <div class="cc-actions mt-3">
            <span class="text-muted small">
                <span id="distrib-visible-count"><?= count($dist_produtos) ?></span> produto(s) para distribuir
            </span>
            <span class="distrib-total-chip" id="distrib-total-chip">0 lançamentos</span>
            <button class="btn btn-outline-secondary btn-sm" onclick="distribZerarTudo()">🔄 Limpar</button>
            <button class="btn btn-success btn-sm ms-auto fw-bold px-4" onclick="ceasaConfirmarDistribuicao()">
                ✅ Confirmar Distribuição
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ABA: ROTA                                                              -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="ceasa-panel-rota" class="ceasa-panel <?= $aba_atual==='rota' ? 'active':'' ?>">

        <?php if ($tabela_frota_existe && $rota_hoje): ?>
        <!-- Painel de status da rota atual -->
        <?php
            $st_rota = $rota_hoje['status'] ?? 'planejada';
            $concl_em = $rota_hoje['concluida_em'] ?? null;
        ?>
        <div class="rota-status-panel <?= $st_rota === 'concluida' ? 'border-success' : ($st_rota === 'em_andamento' ? 'border-primary' : '') ?>"
             style="<?= $st_rota === 'concluida' ? 'background:#f0fdf4;' : ($st_rota === 'em_andamento' ? 'background:#eff6ff;' : '') ?>">
            <div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;font-weight:700;">Status da Rota de Hoje</div>
                <div style="margin-top:4px;">
                    <span class="rota-status-badge <?= $st_rota ?>">
                        <?= match($st_rota) {
                            'planejada'     => '📋 Planejada',
                            'em_andamento'  => '🚐 Em andamento',
                            'concluida'     => '✅ Concluída',
                            default         => ucfirst($st_rota),
                        } ?>
                    </span>
                    <?php if ($concl_em): ?>
                    <span style="font-size:.78rem;color:#6b7280;margin-left:8px;">
                        às <?= date('H:i', strtotime($concl_em)) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($rota_hoje['houve_atraso'])): ?>
                    <span class="rota-hist-atraso ms-2">⚠️ Atraso registrado</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($rota_hoje['observacoes_conclusao'])): ?>
                <div style="font-size:.78rem;color:#6b7280;margin-top:4px;">
                    📝 <?= htmlspecialchars($rota_hoje['observacoes_conclusao']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($rota_hoje['motivo_atraso'])): ?>
                <div style="font-size:.78rem;color:#dc2626;margin-top:2px;">
                    ⏱ Motivo atraso: <?= htmlspecialchars($rota_hoje['motivo_atraso']) ?>
                </div>
                <?php endif; ?>
            </div>
            <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($st_rota === 'planejada'): ?>
                <button class="btn btn-primary btn-sm"
                        onclick="alterarStatusRota(<?= $rota_hoje['id'] ?>,'em_andamento')">
                    🚐 Iniciar saída
                </button>
                <?php endif; ?>
                <?php if ($st_rota !== 'concluida'): ?>
                <button class="btn btn-success btn-sm fw-bold"
                        onclick="abrirModalConcluirRota(<?= $rota_hoje['id'] ?>, '<?= $st_rota ?>')">
                    ✅ Concluir rota
                </button>
                <?php else: ?>
                <span class="text-success small fw-semibold">Rota encerrada</span>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($tabela_frota_existe): ?>
        <div class="alert alert-warning py-2 mb-3" style="font-size:.84rem;">
            ℹ️ Nenhuma rota configurada para hoje. Configure a rota no card "Rota do Dia" acima.
        </div>
        <?php endif; ?>

        <!-- Sub-abas: Rota Atual | Histórico -->
        <div class="rota-sub-tabs">
            <button class="rota-sub-tab active" id="rota-sub-btn-atual" onclick="rotaSubAba('atual')">
                🗺️ Rota de Entrega
            </button>
            <button class="rota-sub-tab" id="rota-sub-btn-hist" onclick="rotaSubAba('hist')">
                📋 Histórico de Rotas
                <?php if (!empty($hist_rotas)): ?>
                <span style="background:#e5e7eb;border-radius:10px;padding:1px 7px;font-size:.7rem;margin-left:4px;">
                    <?= count($hist_rotas) ?>
                </span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Sub-painel: Rota Atual (drag-to-reorder) -->
        <div id="rota-painel-atual">
            <p class="text-muted small mb-3">Arraste os cards para reordenar a sequência de entrega às lojas.</p>
            <div id="rota-lista">
                <?php
                $lojas_rota = db()->query("SELECT id, nome, endereco FROM lojas WHERE ativo=1 ORDER BY nome")->fetchAll();
                foreach ($lojas_rota as $seq => $lr):
                ?>
                <div class="rota-card" draggable="true" data-loja-id="<?= $lr['id'] ?>">
                    <div class="rota-seq"><?= $seq+1 ?>.</div>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:.9rem;">🏪 <?= htmlspecialchars($lr['nome']) ?></div>
                        <div style="font-size:.78rem;color:#888;">📍 <?= htmlspecialchars($lr['endereco'] ?: 'Endereço não cadastrado') ?></div>
                    </div>
                    <a href="https://maps.google.com/?q=<?= urlencode($lr['endereco']?: $lr['nome']) ?>"
                       target="_blank" class="btn btn-sm btn-outline-primary">🗺️ Maps</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sub-painel: Histórico de Rotas -->
        <div id="rota-painel-hist" style="display:none;">
            <?php if (empty($hist_rotas)): ?>
            <div class="text-center text-muted py-5">Nenhuma rota registrada ainda.</div>
            <?php else: ?>
            <p class="text-muted small mb-3">
                Histórico completo de rotas — clique em "Detalhes" para ver a equipe e observações.
            </p>
            <div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
            <table class="rota-hist-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Rota / Descrição</th>
                        <th>Motorista</th>
                        <th>Veículo</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:center;">Atraso</th>
                        <th style="text-align:center;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($hist_rotas as $hr_row): ?>
                <?php
                    $hr_st = $hr_row['status'] ?? 'planejada';
                    $hr_data = date('d/m/Y', strtotime($hr_row['data_rota']));
                    $hr_dow  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w', strtotime($hr_row['data_rota']))];
                ?>
                <tr>
                    <td>
                        <span class="fw-semibold"><?= $hr_data ?></span>
                        <div style="font-size:.68rem;color:#9ca3af;"><?= $hr_dow ?></div>
                    </td>
                    <td style="max-width:160px;">
                        <div style="font-size:.82rem;"><?= htmlspecialchars($hr_row['rota_descricao'] ?: '—') ?></div>
                    </td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($hr_row['motorista_nome'] ?? '—') ?></td>
                    <td style="font-size:.78rem;color:#6b7280;">
                        <?= $hr_row['frota_modelo'] ? htmlspecialchars($hr_row['frota_modelo'] . ' · ' . $hr_row['frota_placa']) : '—' ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="rota-hist-status <?= $hr_st ?>">
                            <?= match($hr_st) {
                                'planejada'    => '📋 Planejada',
                                'em_andamento' => '🚐 Em andamento',
                                'concluida'    => '✅ Concluída',
                                default        => ucfirst($hr_st),
                            } ?>
                        </span>
                        <?php if ($hr_row['concluida_em']): ?>
                        <div style="font-size:.65rem;color:#9ca3af;">
                            <?= date('H:i', strtotime($hr_row['concluida_em'])) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if (!empty($hr_row['houve_atraso'])): ?>
                        <span class="rota-hist-atraso" title="<?= htmlspecialchars($hr_row['motivo_atraso'] ?? '') ?>">
                            ⚠️ Sim
                        </span>
                        <?php else: ?>
                        <span style="color:#9ca3af;font-size:.75rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <button class="btn btn-sm btn-outline-secondary"
                                onclick="verDetalhesRota(<?= $hr_row['id'] ?>)"
                                title="Ver detalhes">
                            📄
                        </button>
                        <?php if ($hr_st !== 'concluida'): ?>
                        <button class="btn btn-sm btn-outline-success"
                                onclick="abrirModalConcluirRota(<?= $hr_row['id'] ?>, '<?= $hr_st ?>')"
                                title="Concluir rota">
                            ✅
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </div>

</div><!-- /cc-wrap -->

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: Configurar Rota do Dia                                              -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div id="modal-rota-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1050;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:480px;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <h5 class="fw-bold mb-4">📋 Rota do Dia — <?= date('d/m/Y') ?></h5>
    <div id="modal-rota-msg" class="alert d-none mb-3"></div>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:.82rem;">🚗 Veículo</label>
        <select id="rota-frota-id" class="form-select form-select-sm">
            <option value="">Nenhum / a pé</option>
            <?php foreach ($frota_lista as $v): ?>
            <option value="<?= $v['id'] ?>" <?= ($rota_hoje && $rota_hoje['frota_id'] == $v['id']) ? 'selected' : '' ?>
                    data-alerta="<?= $v['alerta_docs'] ? '1' : '0' ?>">
                <?= htmlspecialchars($v['modelo']) ?> · <?= htmlspecialchars($v['cor'] ?? '') ?> · <?= htmlspecialchars($v['placa']) ?>
                <?= $v['alerta_docs'] ? ' ⚠️' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:.82rem;">🧑‍✈️ Motorista</label>
        <select id="rota-motorista-id" class="form-select form-select-sm">
            <option value="">Nenhum</option>
            <?php foreach ($motoristas as $m): ?>
            <option value="<?= $m['id'] ?>" <?= ($rota_hoje && $rota_hoje['motorista_id'] == $m['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['nome']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">👷 Auxiliar 1</label>
            <select id="rota-aux1-id" class="form-select form-select-sm">
                <option value="">Nenhum</option>
                <?php foreach ($auxiliares as $ax): ?>
                <option value="<?= $ax['id'] ?>" <?= ($rota_hoje && $rota_hoje['auxiliar1_id'] == $ax['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ax['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">👷 Auxiliar 2</label>
            <select id="rota-aux2-id" class="form-select form-select-sm">
                <option value="">Nenhum</option>
                <?php foreach ($auxiliares as $ax): ?>
                <option value="<?= $ax['id'] ?>" <?= ($rota_hoje && $rota_hoje['auxiliar2_id'] == $ax['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ax['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:.82rem;">📍 Descrição da Rota</label>
        <input type="text" id="rota-descricao" class="form-control form-control-sm"
               value="<?= htmlspecialchars($rota_hoje['rota_descricao'] ?? '') ?>"
               placeholder="Ex.: CEASA → Loja 1 → Loja 3">
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success flex-fill" onclick="salvarRota()">✅ Salvar Rota</button>
        <button class="btn btn-outline-secondary" onclick="fecharModalRota()">Cancelar</button>
    </div>
</div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: Gerenciar Frota                                                     -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div id="modal-frota-backdrop">
<div id="modal-frota">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h5 class="fw-bold mb-0">🚗 Gerenciar Frota e Equipe</h5>
        <button class="btn btn-sm btn-outline-secondary" onclick="fecharModalFrota()">✕</button>
    </div>
    <div id="frota-msg" class="alert d-none mb-3"></div>

    <!-- Sub-abas -->
    <div class="frota-tab-btns">
        <button class="frota-tab-btn ativo" onclick="frotaAba('veiculos')">🚗 Veículos</button>
        <button class="frota-tab-btn"       onclick="frotaAba('equipe')">👷 Equipe CEASA</button>
    </div>

    <!-- Veículos -->
    <div class="frota-panel ativo" id="frota-panel-veiculos">
        <div id="frota-lista-veiculos" class="mb-3">
            <?php foreach ($frota_lista as $v): ?>
            <div class="frota-item">
                <div class="frota-item-info">
                    <strong><?= htmlspecialchars($v['modelo']) ?></strong>
                    <span class="text-muted"> · <?= htmlspecialchars($v['cor'] ?? '') ?> · <?= htmlspecialchars($v['placa']) ?></span>
                    <br>
                    <?= $v['alerta_docs'] ? '<span class="doc-alert">⚠️ Verificar documentação</span>' : '<span class="doc-ok">✅ Documentação OK</span>' ?>
                    <?php if ($v['vencimento_ipva']): ?>
                    <small> · IPVA: <?= date('d/m/Y', strtotime($v['vencimento_ipva'])) ?></small>
                    <?php endif; ?>
                    <?php if ($v['vencimento_seguro']): ?>
                    <small> · Seguro: <?= date('d/m/Y', strtotime($v['vencimento_seguro'])) ?></small>
                    <?php endif; ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary" onclick="frotaEditarVeiculo(<?= $v['id'] ?>)">✏️</button>
            </div>
            <?php endforeach; ?>
            <?php if (empty($frota_lista)): ?>
            <div class="text-muted small mb-3">Nenhum veículo cadastrado ainda.</div>
            <?php endif; ?>
        </div>
        <hr>
        <p class="fw-semibold mb-2" style="font-size:.85rem;">➕ Cadastrar veículo</p>
        <div class="row g-2">
            <div class="col-6"><input type="text" class="form-control form-control-sm" id="v-modelo" placeholder="Modelo (ex.: Fiorino 2020)"></div>
            <div class="col-3"><input type="text" class="form-control form-control-sm" id="v-cor" placeholder="Cor"></div>
            <div class="col-3"><input type="text" class="form-control form-control-sm" id="v-placa" placeholder="Placa" style="text-transform:uppercase;"></div>
            <div class="col-4">
                <label style="font-size:.72rem;color:#6b7280;">Venc. IPVA</label>
                <input type="date" class="form-control form-control-sm" id="v-ipva">
            </div>
            <div class="col-4">
                <label style="font-size:.72rem;color:#6b7280;">Venc. Seguro</label>
                <input type="date" class="form-control form-control-sm" id="v-seguro">
            </div>
            <div class="col-4">
                <label style="font-size:.72rem;color:#6b7280;">Próx. Revisão</label>
                <input type="date" class="form-control form-control-sm" id="v-revisao">
            </div>
            <div class="col-12 d-flex align-items-center gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="v-dok" checked>
                    <label class="form-check-label" style="font-size:.82rem;" for="v-dok">Documentação em dia</label>
                </div>
                <input type="text" class="form-control form-control-sm ms-auto" id="v-obs" placeholder="Observações" style="max-width:200px;">
            </div>
        </div>
        <button class="btn btn-success btn-sm mt-3" onclick="frotaCadastrarVeiculo()">💾 Cadastrar Veículo</button>
    </div>

    <!-- Equipe -->
    <div class="frota-panel" id="frota-panel-equipe">
        <div id="frota-lista-equipe" class="mb-3">
            <?php
            $colab_por_funcao = ['motorista' => [], 'auxiliar' => []];
            foreach ($colab_lista as $cl) { $colab_por_funcao[$cl['funcao']][] = $cl; }
            foreach ($colab_por_funcao as $func => $lista):
                if (empty($lista)) continue;
            ?>
            <p class="fw-semibold mb-1 mt-2" style="font-size:.8rem;color:#555;">
                <?= $func === 'motorista' ? '🧑‍✈️ Motoristas' : '👷 Auxiliares' ?>
            </p>
            <?php foreach ($lista as $cl): ?>
            <div class="frota-item">
                <div class="frota-item-info">
                    <strong><?= htmlspecialchars($cl['nome']) ?></strong>
                    <?= $cl['telefone'] ? '<small> · ' . htmlspecialchars($cl['telefone']) . '</small>' : '' ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (empty($colab_lista)): ?>
            <div class="text-muted small mb-3">Nenhum colaborador cadastrado ainda.</div>
            <?php endif; ?>
        </div>
        <hr>
        <p class="fw-semibold mb-2" style="font-size:.85rem;">➕ Cadastrar colaborador</p>
        <div class="row g-2">
            <div class="col-6"><input type="text" class="form-control form-control-sm" id="c-nome" placeholder="Nome completo"></div>
            <div class="col-3">
                <select class="form-select form-select-sm" id="c-funcao">
                    <option value="auxiliar">Auxiliar</option>
                    <option value="motorista">Motorista</option>
                </select>
            </div>
            <div class="col-3"><input type="text" class="form-control form-control-sm" id="c-tel" placeholder="Telefone"></div>
        </div>
        <button class="btn btn-success btn-sm mt-3" onclick="frotaCadastrarColaborador()">💾 Cadastrar</button>
    </div>

</div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: Concluir Rota                                                       -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div id="modal-concluir-rota-backdrop"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1060;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <h5 class="fw-bold mb-4">✅ Confirmar Conclusão da Rota</h5>
    <div id="modal-concluir-msg" class="alert d-none mb-3"></div>
    <input type="hidden" id="modal-concluir-rota-id" value="">

    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:.82rem;">Como foi a rota?</label>
        <div class="d-flex gap-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="concluir-resultado" id="concluir-ok" value="ok" checked>
                <label class="form-check-label" for="concluir-ok" style="font-size:.85rem;">✅ Concluída sem problemas</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="concluir-resultado" id="concluir-atraso" value="atraso">
                <label class="form-check-label" for="concluir-atraso" style="font-size:.85rem;">⚠️ Houve atraso</label>
            </div>
        </div>
    </div>

    <div id="concluir-atraso-painel" style="display:none;" class="mb-3">
        <label class="form-label fw-semibold" style="font-size:.82rem;">Motivo do atraso</label>
        <select id="concluir-motivo-select" class="form-select form-select-sm mb-2"
                onchange="document.getElementById('concluir-motivo-outro').style.display = this.value === 'outro' ? '' : 'none'">
            <option value="">Selecione…</option>
            <option value="Trânsito intenso">Trânsito intenso</option>
            <option value="Problema mecânico no veículo">Problema mecânico no veículo</option>
            <option value="Falta de mercadoria no CEASA">Falta de mercadoria no CEASA</option>
            <option value="Demora na carga/descarga">Demora na carga/descarga</option>
            <option value="Acidente ou intercorrência">Acidente ou intercorrência</option>
            <option value="Condições climáticas">Condições climáticas</option>
            <option value="outro">Outro (descrever abaixo)</option>
        </select>
        <input type="text" id="concluir-motivo-outro" class="form-control form-control-sm"
               placeholder="Descreva o motivo…" style="display:none;" maxlength="300">
    </div>

    <div class="mb-4">
        <label class="form-label fw-semibold" style="font-size:.82rem;">Observações gerais <span class="text-muted fw-normal">(opcional)</span></label>
        <textarea id="concluir-obs" class="form-control form-control-sm" rows="2"
                  maxlength="500" placeholder="Anotações sobre esta saída…"></textarea>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-success flex-fill fw-bold" onclick="confirmarConcluirRota()">
            ✅ Confirmar e encerrar
        </button>
        <button class="btn btn-outline-secondary" onclick="fecharModalConcluirRota()">Cancelar</button>
    </div>
</div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: Detalhes de Rota (histórico)                                        -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div id="modal-rota-detalhe-backdrop"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1060;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <h5 class="fw-bold mb-0">📋 Detalhes da Rota</h5>
        <button class="btn btn-sm btn-outline-secondary" onclick="fecharDetalhesRota()">✕</button>
    </div>
    <div id="rota-detalhe-corpo">Carregando…</div>
</div>
</div>

<!-- Modal: Detalhes de recebimento -->
<div id="modal-receb-detalhe-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1050;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h5 class="fw-bold mb-0">📄 Detalhes do Recebimento</h5>
        <button class="btn btn-sm btn-outline-secondary" onclick="fecharDetalheRecebimento()">✕</button>
    </div>
    <div id="receb-detalhe-corpo">Carregando…</div>
    <div class="mt-3 text-end">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">🖨️ Imprimir</button>
    </div>
</div>
</div>


<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- SCRIPTS                                                                    -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<script>
window.moduloCeasaUI = (function () {

    // ── Trocar aba ─────────────────────────────────────────────────────────────
    window.ceasaMostrarAba = function (id) {
        document.querySelectorAll('.ceasa-tab').forEach(t => {
            t.classList.toggle('active', t.textContent.toLowerCase().includes(
                {lista:'lista',receb:'receb',distrib:'distri',rota:'rota'}[id]));
        });
        document.querySelectorAll('.ceasa-panel').forEach(p => {
            p.classList.toggle('active', p.id === 'ceasa-panel-' + id);
        });
    };

    // ── Filtros: busca + categoria + status ────────────────────────────────────
    let _filtCat = '', _filtStatus = '';

    // Filtros de categoria
    document.getElementById('cc-cat-filtros')?.addEventListener('click', e => {
        const btn = e.target.closest('.cc-filter-btn');
        if (!btn) return;
        document.querySelectorAll('#cc-cat-filtros .cc-filter-btn').forEach(b => b.classList.remove('ativo'));
        btn.classList.add('ativo');
        _filtCat = btn.dataset.cat;
        ccFiltrar();
    });

    // Filtros de status
    document.getElementById('cc-status-filtros')?.addEventListener('click', e => {
        const btn = e.target.closest('.cc-filter-btn');
        if (!btn) return;
        document.querySelectorAll('#cc-status-filtros .cc-filter-btn').forEach(b => b.classList.remove('ativo'));
        btn.classList.add('ativo');
        _filtStatus = btn.dataset.status;
        ccFiltrar();
    });

    window.ccFiltrar = function () {
        const busca  = (document.getElementById('cc-busca')?.value || '').toLowerCase();
        const tbody  = document.getElementById('cc-tbody');
        if (!tbody) return;

        let visiveis = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const isCatHeader = tr.classList.contains('cat-header');
            if (isCatHeader) {
                // Mostra cabeçalho de categoria se houver linhas visíveis nela
                tr.style.display = '';
                return;
            }
            const cat    = tr.dataset.cat    || '';
            const status = tr.dataset.status || '';
            const nome   = tr.dataset.nome   || '';

            const okCat    = !_filtCat    || cat === _filtCat;
            const okStatus = !_filtStatus || status === _filtStatus;
            const okBusca  = !busca       || nome.includes(busca);

            const vis = okCat && okStatus && okBusca;
            tr.style.display = vis ? '' : 'none';
            if (vis) visiveis++;
        });

        // Oculta cabeçalhos de categoria se nenhuma linha dela estiver visível
        let lastCatHeader = null;
        tbody.querySelectorAll('tr').forEach(tr => {
            if (tr.classList.contains('cat-header')) {
                lastCatHeader = tr;
                return;
            }
            if (lastCatHeader && tr.style.display !== 'none') {
                lastCatHeader.style.display = '';
                lastCatHeader = null;
            } else if (lastCatHeader) {
                lastCatHeader.style.display = 'none';
            }
        });

        const el = document.getElementById('cc-visible-count');
        if (el) el.textContent = visiveis;
    };

    // ── Atualiza total geral ──────────────────────────────────────────────────
    window.ccAtualizarTotal = function (inp) {
        inp.classList.toggle('tem-valor', parseFloat(inp.value) > 0);
        _recalcTotal();
    };

    function _recalcTotal() {
        let itens = [];
        document.querySelectorAll('.input-compra').forEach(inp => {
            const q  = parseFloat(inp.value) || 0;
            const un = inp.dataset.unidade;
            const nm = inp.dataset.nome;
            if (q > 0) itens.push(`${nm}: ${q} ${un}`);
        });
        const el = document.getElementById('cc-total-geral');
        if (el) el.textContent = itens.length ? itens.length + ' produto(s) selecionados' : '—';
    }

    // ── Limpar / Usar sugerido ────────────────────────────────────────────────
    window.ccZerarCompras = function () {
        document.querySelectorAll('.input-compra').forEach(inp => {
            inp.value = '';
            inp.classList.remove('tem-valor');
        });
        _recalcTotal();
    };

    window.ccPreencherSugerido = function () {
        document.querySelectorAll('.input-compra').forEach(inp => {
            const sug = parseFloat(inp.dataset.sugerida) || 0;
            inp.value = sug > 0 ? (inp.dataset.unidade === 'kg' ? sug.toFixed(1) : sug) : '';
            inp.classList.toggle('tem-valor', sug > 0);
        });
        _recalcTotal();
    };

    // ── Salvar / Imprimir lista ───────────────────────────────────────────────
    window.ccSalvarLista = function () {
        const data = new Date().toLocaleString('pt-BR');
        const linhas = [];
        document.querySelectorAll('#cc-tbody tr:not(.cat-header)').forEach(tr => {
            if (tr.style.display === 'none') return;
            const inp = tr.querySelector('.input-compra');
            const obs = tr.querySelector('.input-obs');
            const qtd = parseFloat(inp?.value) || 0;
            if (qtd <= 0) return;
            linhas.push({
                nome: inp?.dataset.nome || '',
                qtd,
                un:   inp?.dataset.unidade || '',
                obs:  obs?.value || '',
            });
        });

        const w = window.open('', '_blank');
        const rows = linhas.map((l, i) =>
            `<tr>
                <td>${i+1}</td>
                <td>${l.nome}</td>
                <td style="text-align:right;font-weight:bold;">${l.qtd} ${l.un}</td>
                <td>${l.obs}</td>
            </tr>`
        ).join('');

        w.document.write(`<!DOCTYPE html><html><head>
            <title>Lista de Compra CEASA — ${data}</title>
            <meta charset="utf-8">
            <style>
                body{font-family:Arial,sans-serif;font-size:13px;margin:24px;}
                h2{color:#1b5e20;margin-bottom:4px;}
                .sub{color:#666;font-size:11px;margin-bottom:16px;}
                table{width:100%;border-collapse:collapse;}
                th,td{border:1px solid #ccc;padding:6px 10px;}
                th{background:#e8f5e9;font-size:11px;text-transform:uppercase;}
                tr:nth-child(even) td{background:#f9f9f9;}
                @media print{button{display:none!important;}}
            </style></head><body>
            <h2>🛒 Lista de Compra CEASA</h2>
            <div class="sub">Gerada em: ${data} · ${linhas.length} produto(s) a comprar</div>
            <table>
                <thead><tr><th>#</th><th>Produto</th><th>Qtd a comprar</th><th>Observação</th></tr></thead>
                <tbody>${rows || '<tr><td colspan="4" style="text-align:center;color:#999;">Nenhum produto com quantidade definida.</td></tr>'}</tbody>
            </table>
            <br><button onclick="window.print()" style="background:#1b5e20;color:#fff;padding:8px 20px;border:none;border-radius:6px;cursor:pointer;font-size:13px;">🖨️ Imprimir</button>
        </body></html>`);
        w.document.close();
    };

    // ── Recebimento ───────────────────────────────────────────────────────────
    let recebIdx = 1;
    window.ceasaAdicionarLinhaReceb = function () {
        const c = document.getElementById('receb-itens');
        const base = document.getElementById('receb-linha-0');
        if (!c || !base) return;
        const nova = base.cloneNode(true);
        nova.id = 'receb-linha-' + recebIdx++;
        nova.querySelectorAll('input').forEach(i => i.value = '');
        nova.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
        nova.querySelectorAll('.receb-un').forEach(u => u.textContent = '—');
        c.appendChild(nova);
        nova.querySelector('.receb-produto')?.addEventListener('change', function () {
            nova.querySelector('.receb-un').textContent = this.options[this.selectedIndex].dataset.un || '—';
        });
    };
    document.querySelector('.receb-produto')?.addEventListener('change', function () {
        document.querySelector('.receb-un').textContent = this.options[this.selectedIndex].dataset.un || '—';
    });

    window.ceasaConfirmarRecebimento = async function () {
        const loja_id = document.getElementById('receb-loja').value;
        const msgEl   = document.getElementById('receb-msg');
        const token   = () => sessionStorage.getItem('desffrut_token') || '';
        const itens   = [];
        document.querySelectorAll('.receb-linha').forEach(l => {
            const pid = l.querySelector('.receb-produto')?.value;
            const qty = parseFloat(l.querySelector('.receb-qtd')?.value || 0);
            if (pid && qty > 0) itens.push({ produto_id: +pid, loja_id: +loja_id, quantidade: qty });
        });
        if (!itens.length || !loja_id) {
            msgEl.textContent = 'Adicione ao menos um item com quantidade.';
            msgEl.className   = 'alert alert-warning'; return;
        }
        let ok = 0, err = 0;
        for (const item of itens) {
            try {
                const r1 = await fetch(`${APP.api}/estoque?loja_id=${item.loja_id}`,
                    { headers: { 'Authorization': 'Bearer ' + token() } });
                const j1 = await r1.json();
                const est = parseFloat((j1.data||[]).find(e => e.id === item.produto_id)?.quantidade || 0);
                const r2  = await fetch(`${APP.api}/estoque`, {
                    method:'PUT', headers:{'Content-Type':'application/json','Authorization':'Bearer '+token()},
                    body: JSON.stringify({ produto_id:item.produto_id, loja_id:item.loja_id, quantidade: est+item.quantidade }),
                });
                const j2 = await r2.json();
                j2.status === 'ok' ? ok++ : err++;
            } catch { err++; }
        }
        msgEl.textContent = `${ok} item(ns) atualizado(s).${err > 0 ? ' '+err+' erro(s).' : ''}`;
        msgEl.className   = 'alert alert-' + (err ? 'warning' : 'success');
    };

    // ── Distribuição ──────────────────────────────────────────────────────────
    window.ceasaConfirmarDistribuicao = async function () {
        const token = () => sessionStorage.getItem('desffrut_token') || '';
        const msgEl = document.getElementById('distrib-msg');
        let ok = 0, err = 0;
        for (const inp of document.querySelectorAll('.distrib-input')) {
            const qty = parseFloat(inp.value || 0);
            if (qty <= 0) continue;
            const pid = +inp.dataset.produtoId, lid = +inp.dataset.lojaId;
            try {
                const r1 = await fetch(`${APP.api}/estoque?loja_id=${lid}`,{ headers:{'Authorization':'Bearer '+token()} });
                const j1 = await r1.json();
                const est = parseFloat((j1.data||[]).find(e => +e.id===pid)?.quantidade||0);
                const r2  = await fetch(`${APP.api}/estoque`,{
                    method:'PUT', headers:{'Content-Type':'application/json','Authorization':'Bearer '+token()},
                    body: JSON.stringify({produto_id:pid,loja_id:lid,quantidade:est+qty}),
                });
                const j2 = await r2.json();
                j2.status==='ok' ? ok++ : err++;
            } catch { err++; }
        }
        if (msgEl) {
            msgEl.textContent = `Distribuição: ${ok} item(ns) atualizados.${err>0?' '+err+' erro(s).':''}`;
            msgEl.className   = 'alert alert-' + (err?'warning':'success');
        }
    };

    // ── Rota: drag-to-reorder ─────────────────────────────────────────────────
    (function initDrag() {
        const lista = document.getElementById('rota-lista');
        if (!lista) return;
        let dragging = null;
        lista.addEventListener('dragstart', e => { dragging = e.target.closest('.rota-card'); dragging?.classList.add('opacity-50'); });
        lista.addEventListener('dragend',   () => { dragging?.classList.remove('opacity-50'); dragging = null; lista.querySelectorAll('.rota-seq').forEach((s,i) => s.textContent=(i+1)+'.'); });
        lista.addEventListener('dragover',  e => {
            e.preventDefault();
            const t = e.target.closest('.rota-card');
            if (t && t !== dragging) {
                const mid = t.getBoundingClientRect().top + t.getBoundingClientRect().height/2;
                e.clientY > mid ? t.after(dragging) : t.before(dragging);
            }
        });
    })();

    // ── Sub-aba do Recebimento ────────────────────────────────────────────────
    window.recebSubAba = function (aba) {
        document.getElementById('receb-painel-registrar').style.display = aba === 'registrar' ? '' : 'none';
        document.getElementById('receb-painel-historico').style.display = aba === 'historico'  ? '' : 'none';
        document.getElementById('receb-sub-registrar').classList.toggle('ativo', aba === 'registrar');
        document.getElementById('receb-sub-historico').classList.toggle('ativo', aba === 'historico');
    };

    // ── Recebimento: toggle checkbox / não entregue ──────────────────────────
    window.recebToggleLinha = function (cb) {
        const tr = cb.closest('tr');
        if (!cb.checked) {
            tr.querySelector('.receb-qtd-rec').value = '';
        }
    };
    window.recebToggleNaoEntregue = function (cb) {
        const tr    = cb.closest('tr');
        const qtdEl = tr.querySelector('.receb-qtd-rec');
        if (cb.checked) {
            tr.classList.add('row-critico');
            tr.querySelector('.receb-chk').checked = false;
            qtdEl.value = '';
            qtdEl.disabled = true;
        } else {
            tr.classList.remove('row-critico');
            qtdEl.disabled = false;
        }
    };
    window.recebDesmarcarTodos = function () {
        document.querySelectorAll('.receb-chk').forEach(cb => { cb.checked = false; });
        document.querySelectorAll('.receb-qtd-rec').forEach(inp => { inp.value = ''; inp.disabled = false; });
        document.querySelectorAll('.receb-nao-entregue').forEach(cb => { cb.checked = false; });
    };
    window.recebMarcarCriticos = function () {
        // Desmarca todos primeiro
        recebDesmarcarTodos();
        // Marca apenas linhas com estoque crítico ou baixo (row-critico / row-baixo)
        document.querySelectorAll('#receb-tbody tr.row-critico, #receb-tbody tr.row-baixo').forEach(tr => {
            const chk = tr.querySelector('.receb-chk');
            const inp = tr.querySelector('.receb-qtd-rec');
            const sug = parseFloat(tr.dataset.sugerida || 0);
            const un  = tr.dataset.un || 'un';
            if (chk) chk.checked = true;
            if (inp && sug > 0) inp.value = un === 'kg' ? sug.toFixed(3) : Math.round(sug);
        });
    };

    // ── Confirmar recebimento novo (planilha) ─────────────────────────────────
    window.ceasaConfirmarRecebimentoNovo = async function () {
        const loja_id  = document.getElementById('receb-loja')?.value;
        const data     = document.getElementById('receb-data')?.value || new Date().toISOString().slice(0,10);
        const rota_id  = document.getElementById('receb-rota-id')?.value || null;
        const obs_ger  = document.getElementById('receb-obs-gerais')?.value || '';
        const msgEl    = document.getElementById('receb-msg');

        if (!loja_id) { msgEl.textContent = 'Selecione a loja destino.'; msgEl.className = 'alert alert-warning'; return; }

        const itens = [];
        document.querySelectorAll('#receb-tbody tr').forEach(tr => {
            const pid      = tr.dataset.produtoId;
            const qtd_rec  = parseFloat(tr.querySelector('.receb-qtd-rec')?.value || 0);
            const qtd_q    = parseFloat(tr.querySelector('.receb-qtd-quebra')?.value || 0);
            const nao_ent  = tr.querySelector('.receb-nao-entregue')?.checked ? 1 : 0;
            const obs_it   = tr.querySelector('.receb-obs')?.value || '';
            const sug      = parseFloat(tr.dataset.sugerida || 0);
            if (!pid) return;
            // Inclui apenas itens marcados, não-entregues, ou com alguma quantidade
            const chk = tr.querySelector('.receb-chk')?.checked;
            if (!chk && qtd_rec === 0 && !nao_ent) return;
            itens.push({ produto_id: +pid, qtd_pedida: sug, qtd_recebida: qtd_rec, qtd_quebra: qtd_q, nao_entregue: nao_ent, observacao: obs_it });
        });

        if (!itens.length) { msgEl.textContent = 'Nenhum produto selecionado.'; msgEl.className = 'alert alert-warning'; return; }

        msgEl.textContent = 'Salvando…';
        msgEl.className   = 'alert alert-info';

        try {
            const resp = await fetch(APP.api + '/ceasa_recebimentos', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ loja_id: +loja_id, data_recebimento: data, rota_id: rota_id ? +rota_id : null, observacoes_gerais: obs_ger, itens }),
            });
            const json = await resp.json();
            if (!resp.ok) throw new Error(json.message);
            msgEl.textContent = '✅ ' + json.message;
            msgEl.className   = 'alert alert-success';
        } catch (e) {
            msgEl.textContent = 'Erro: ' + e.message;
            msgEl.className   = 'alert alert-danger';
        }
    };

    // ── Detalhes de recebimento no histórico ──────────────────────────────────
    window.verDetalhesRecebimento = async function (id) {
        const modal = document.getElementById('modal-receb-detalhe-backdrop');
        const corpo = document.getElementById('receb-detalhe-corpo');
        modal.style.display = 'flex';
        corpo.innerHTML = 'Carregando…';
        try {
            const r = await fetch(`${APP.api}/ceasa_recebimentos/${id}`);
            const j = await r.json();
            if (!r.ok) throw new Error(j.message);
            const d = j.data;
            const rows = (d.itens || []).map(it => {
                const ne  = it.nao_entregue ? '<span class="text-danger">❌ N/E</span>' : '';
                const rec = parseFloat(it.qtd_recebida);
                const cor = rec > 0 ? 'color:#16a34a;font-weight:700;' : 'color:#9ca3af;';
                return `<tr>
                    <td style="padding:5px 10px;border-bottom:1px solid #f0f0f0;">${it.produto_nome}</td>
                    <td style="padding:5px 10px;border-bottom:1px solid #f0f0f0;text-align:right;">${parseFloat(it.qtd_pedida).toFixed(2)} ${it.unidade_medida}</td>
                    <td style="padding:5px 10px;border-bottom:1px solid #f0f0f0;text-align:right;${cor}">${rec.toFixed(2)} ${it.unidade_medida} ${ne}</td>
                    <td style="padding:5px 10px;border-bottom:1px solid #f0f0f0;text-align:right;${parseFloat(it.qtd_quebra)>0?'color:#dc2626;':''}">${parseFloat(it.qtd_quebra).toFixed(2)}</td>
                    <td style="padding:5px 10px;border-bottom:1px solid #f0f0f0;font-size:.75rem;color:#6b7280;">${it.observacao||''}</td>
                </tr>`;
            }).join('');
            corpo.innerHTML = `
                <p class="mb-1"><strong>Data:</strong> ${new Date(d.data_recebimento).toLocaleDateString('pt-BR')}</p>
                <p class="mb-1"><strong>Loja:</strong> ${d.loja_nome}</p>
                ${d.responsavel_nome ? `<p class="mb-1"><strong>Responsável:</strong> ${d.responsavel_nome}</p>` : ''}
                ${d.frota_modelo ? `<p class="mb-1"><strong>Veículo:</strong> ${d.frota_modelo} · ${d.frota_placa}</p>` : ''}
                ${d.motorista_nome ? `<p class="mb-1"><strong>Motorista:</strong> ${d.motorista_nome}</p>` : ''}
                ${d.observacoes_gerais ? `<p class="mb-1"><strong>Obs.:</strong> ${d.observacoes_gerais}</p>` : ''}
                <hr>
                <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                    <thead><tr style="background:#f9f9f9;">
                        <th style="padding:6px 10px;text-align:left;border-bottom:2px solid #e0e0e0;">Produto</th>
                        <th style="padding:6px 10px;text-align:right;border-bottom:2px solid #e0e0e0;">Pedido</th>
                        <th style="padding:6px 10px;text-align:right;border-bottom:2px solid #e0e0e0;">Recebido</th>
                        <th style="padding:6px 10px;text-align:right;border-bottom:2px solid #e0e0e0;">Quebra</th>
                        <th style="padding:6px 10px;border-bottom:2px solid #e0e0e0;">Obs.</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
                <p class="mt-2 text-muted small">${d.total_recebidos} de ${d.total_itens} produtos recebidos.</p>`;
        } catch (e) {
            corpo.innerHTML = '<div class="alert alert-danger">' + e.message + '</div>';
        }
    };
    window.fecharDetalheRecebimento = function () {
        document.getElementById('modal-receb-detalhe-backdrop').style.display = 'none';
    };

    // ── Modal Rota ──────────────────────────────────────────────────────────
    window.abrirModalRota = function () {
        document.getElementById('modal-rota-backdrop').style.display = 'flex';
    };
    window.fecharModalRota = function () {
        document.getElementById('modal-rota-backdrop').style.display = 'none';
    };
    window.salvarRota = async function () {
        const msgEl = document.getElementById('modal-rota-msg');
        const body  = {
            data_rota:       '<?= date('Y-m-d') ?>',
            frota_id:        +document.getElementById('rota-frota-id').value || null,
            motorista_id:    +document.getElementById('rota-motorista-id').value || null,
            auxiliar1_id:    +document.getElementById('rota-aux1-id').value || null,
            auxiliar2_id:    +document.getElementById('rota-aux2-id').value || null,
            rota_descricao:  document.getElementById('rota-descricao').value,
        };
        try {
            const resp = await fetch(APP.api + '/ceasa_rotas', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(body),
            });
            const json = await resp.json();
            if (!resp.ok) throw new Error(json.message);
            msgEl.textContent = '✅ Rota salva! Recarregando…';
            msgEl.className   = 'alert alert-success';
            setTimeout(() => location.reload(), 800);
        } catch (e) {
            msgEl.textContent = 'Erro: ' + e.message;
            msgEl.className   = 'alert alert-danger';
        }
    };

    // ── Modal Frota ──────────────────────────────────────────────────────────
    window.abrirModalFrota = function () {
        document.getElementById('modal-frota-backdrop').classList.add('aberto');
    };
    window.fecharModalFrota = function () {
        document.getElementById('modal-frota-backdrop').classList.remove('aberto');
    };
    window.frotaAba = function (aba) {
        document.querySelectorAll('.frota-tab-btn').forEach((b, i) => {
            b.classList.toggle('ativo', (aba === 'veiculos' && i === 0) || (aba === 'equipe' && i === 1));
        });
        document.getElementById('frota-panel-veiculos').classList.toggle('ativo', aba === 'veiculos');
        document.getElementById('frota-panel-equipe').classList.toggle('ativo',   aba === 'equipe');
    };
    window.frotaCadastrarVeiculo = async function () {
        const msgEl = document.getElementById('frota-msg');
        const body  = {
            modelo:            document.getElementById('v-modelo').value,
            cor:               document.getElementById('v-cor').value,
            placa:             document.getElementById('v-placa').value,
            vencimento_ipva:   document.getElementById('v-ipva').value   || null,
            vencimento_seguro: document.getElementById('v-seguro').value  || null,
            vencimento_revisao:document.getElementById('v-revisao').value || null,
            documentacao_ok:   document.getElementById('v-dok').checked ? 1 : 0,
            observacoes:       document.getElementById('v-obs').value,
        };
        if (!body.modelo || !body.placa) { msgEl.textContent = 'Modelo e Placa são obrigatórios.'; msgEl.className = 'alert alert-warning'; return; }
        try {
            const r = await fetch(APP.api + '/frota', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message);
            msgEl.textContent = '✅ Veículo cadastrado! Recarregando…';
            msgEl.className   = 'alert alert-success';
            setTimeout(() => location.reload(), 800);
        } catch(e) { msgEl.textContent = 'Erro: ' + e.message; msgEl.className = 'alert alert-danger'; }
    };
    window.frotaCadastrarColaborador = async function () {
        const msgEl = document.getElementById('frota-msg');
        const body  = {
            nome:     document.getElementById('c-nome').value,
            funcao:   document.getElementById('c-funcao').value,
            telefone: document.getElementById('c-tel').value,
        };
        if (!body.nome) { msgEl.textContent = 'Nome é obrigatório.'; msgEl.className = 'alert alert-warning'; return; }
        try {
            const r = await fetch(APP.api + '/ceasa_colaboradores', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message);
            msgEl.textContent = '✅ Colaborador cadastrado! Recarregando…';
            msgEl.className   = 'alert alert-success';
            setTimeout(() => location.reload(), 800);
        } catch(e) { msgEl.textContent = 'Erro: ' + e.message; msgEl.className = 'alert alert-danger'; }
    };
    window.frotaEditarVeiculo = function(id) {
        alert('Para editar, use a API PATCH /api/v1/frota/' + id + ' ou aguarde o painel completo de edição.');
    };

    // ══════════════════════════════════════════════════════════════════════════
    // RECEBIMENTO: Filtro por categoria + busca
    // ══════════════════════════════════════════════════════════════════════════
    let _recebFiltCat = '';

    document.getElementById('receb-cat-filtros')?.addEventListener('click', e => {
        const btn = e.target.closest('.cc-filter-btn');
        if (!btn) return;
        document.querySelectorAll('#receb-cat-filtros .cc-filter-btn').forEach(b => b.classList.remove('ativo'));
        btn.classList.add('ativo');
        _recebFiltCat = btn.dataset.cat;
        recebFiltrar();
    });

    window.recebFiltrar = function () {
        const busca = (document.getElementById('receb-busca')?.value || '').toLowerCase();
        let vis = 0;
        document.querySelectorAll('#receb-tbody tr').forEach(tr => {
            const cat  = tr.dataset.cat  || '';
            const nome = tr.dataset.nome || '';
            const okCat   = !_recebFiltCat || cat === _recebFiltCat;
            const okBusca = !busca || nome.includes(busca);
            const show = okCat && okBusca;
            tr.style.display = show ? '' : 'none';
            if (show) vis++;
        });
        const el = document.getElementById('receb-visible-count');
        if (el) el.textContent = vis + ' produto(s)';
    };

    // ══════════════════════════════════════════════════════════════════════════
    // DISTRIBUIÇÃO: Filtro por categoria + busca + checkbox + total
    // ══════════════════════════════════════════════════════════════════════════
    let _distribFiltCat = '';

    window.distribFiltrar = function (cat) {
        if (cat !== undefined) _distribFiltCat = cat;
        const busca = (document.getElementById('distrib-busca')?.value || '').toLowerCase();
        // Atualiza botões
        document.querySelectorAll('#distrib-cat-filtros .cc-filter-btn').forEach(b => {
            b.classList.toggle('ativo', b.dataset.cat === _distribFiltCat);
        });
        let vis = 0;
        const tbody = document.getElementById('distrib-tbody');
        if (!tbody) return;
        let lastCatHdr = null;
        tbody.querySelectorAll('tr').forEach(tr => {
            if (tr.classList.contains('cat-header')) {
                lastCatHdr = tr;
                tr.style.display = '';
                return;
            }
            const c    = tr.dataset.cat  || '';
            const nome = tr.dataset.nome || '';
            const okCat   = !_distribFiltCat || c === _distribFiltCat;
            const okBusca = !busca || nome.includes(busca);
            const show = okCat && okBusca;
            tr.style.display = show ? '' : 'none';
            if (show) vis++;
        });
        // Oculta cabeçalho de categoria sem itens visíveis
        let curHdr = null;
        tbody.querySelectorAll('tr').forEach(tr => {
            if (tr.classList.contains('cat-header')) { curHdr = tr; return; }
            if (curHdr && tr.style.display !== 'none') { curHdr.style.display = ''; curHdr = null; }
            else if (curHdr) curHdr.style.display = 'none';
        });
        const el = document.getElementById('distrib-visible-count');
        if (el) el.textContent = vis;
        distribAtualizarTotal();
    };

    window.distribAtualizarTotal = function () {
        let count = 0;
        document.querySelectorAll('.distrib-input-new').forEach(inp => {
            if (parseFloat(inp.value) > 0) count++;
        });
        const chip = document.getElementById('distrib-total-chip');
        if (chip) chip.textContent = count + ' lançamento(s)';
    };

    window.distribZerarTudo = function () {
        document.querySelectorAll('.distrib-input-new').forEach(inp => inp.value = '');
        document.querySelectorAll('.distrib-chk-done').forEach(cb => cb.checked = false);
        distribAtualizarTotal();
    };

    window.distribToggleFeito = function (cb) {
        const tr = cb.closest('tr');
        if (!tr) return;
        if (cb.checked) {
            tr.style.opacity = '.5';
            tr.style.textDecoration = 'line-through';
        } else {
            tr.style.opacity = '';
            tr.style.textDecoration = '';
        }
    };

    // ══════════════════════════════════════════════════════════════════════════
    // ROTA: Sub-abas Atual / Histórico
    // ══════════════════════════════════════════════════════════════════════════
    window.rotaSubAba = function (aba) {
        document.getElementById('rota-painel-atual').style.display = aba === 'atual' ? '' : 'none';
        document.getElementById('rota-painel-hist').style.display  = aba === 'hist'  ? '' : 'none';
        document.getElementById('rota-sub-btn-atual').classList.toggle('active', aba === 'atual');
        document.getElementById('rota-sub-btn-hist').classList.toggle('active',  aba === 'hist');
    };

    // ══════════════════════════════════════════════════════════════════════════
    // ROTA: Alterar status (planejada → em_andamento)
    // ══════════════════════════════════════════════════════════════════════════
    window.alterarStatusRota = async function (id, novoStatus) {
        try {
            const r = await fetch(`${APP.api}/ceasa_rotas/${id}`, {
                method: 'PATCH',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ status: novoStatus }),
            });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message || 'Erro');
            location.reload();
        } catch (e) {
            alert('Erro ao atualizar status: ' + e.message);
        }
    };

    // ══════════════════════════════════════════════════════════════════════════
    // ROTA: Modal de conclusão
    // ══════════════════════════════════════════════════════════════════════════
    window.abrirModalConcluirRota = function (id, statusAtual) {
        document.getElementById('modal-concluir-rota-id').value = id;
        document.getElementById('modal-concluir-msg').className = 'alert d-none';
        document.getElementById('concluir-obs').value = '';
        document.getElementById('concluir-motivo-select').value = '';
        document.getElementById('concluir-motivo-outro').style.display = 'none';
        document.getElementById('concluir-motivo-outro').value = '';
        document.getElementById('concluir-ok').checked = true;
        document.getElementById('concluir-atraso').checked = false;
        document.getElementById('concluir-atraso-painel').style.display = 'none';
        document.getElementById('modal-concluir-rota-backdrop').style.display = 'flex';
    };

    // Toggle painel de atraso
    document.querySelectorAll('input[name="concluir-resultado"]').forEach(r => {
        r.addEventListener('change', function () {
            const mostrar = this.value === 'atraso';
            document.getElementById('concluir-atraso-painel').style.display = mostrar ? '' : 'none';
        });
    });

    window.fecharModalConcluirRota = function () {
        document.getElementById('modal-concluir-rota-backdrop').style.display = 'none';
    };

    window.confirmarConcluirRota = async function () {
        const id         = document.getElementById('modal-concluir-rota-id').value;
        const resultado  = document.querySelector('input[name="concluir-resultado"]:checked')?.value || 'ok';
        const houve_atraso = resultado === 'atraso' ? 1 : 0;
        const selectMotivo = document.getElementById('concluir-motivo-select')?.value || '';
        const outroMotivo  = document.getElementById('concluir-motivo-outro')?.value  || '';
        const motivo_atraso = selectMotivo === 'outro' ? outroMotivo : selectMotivo;
        const obs          = document.getElementById('concluir-obs')?.value || '';
        const msgEl        = document.getElementById('modal-concluir-msg');

        if (houve_atraso && !motivo_atraso) {
            msgEl.textContent = 'Informe o motivo do atraso.';
            msgEl.className   = 'alert alert-warning';
            return;
        }

        const agora = new Date();
        const concluida_em = agora.getFullYear() + '-' +
            String(agora.getMonth()+1).padStart(2,'0') + '-' +
            String(agora.getDate()).padStart(2,'0') + ' ' +
            String(agora.getHours()).padStart(2,'0') + ':' +
            String(agora.getMinutes()).padStart(2,'0') + ':' +
            String(agora.getSeconds()).padStart(2,'0');

        msgEl.textContent = 'Salvando…';
        msgEl.className   = 'alert alert-info';

        try {
            const r = await fetch(`${APP.api}/ceasa_rotas/${id}`, {
                method:  'PATCH',
                headers: {'Content-Type': 'application/json'},
                body:    JSON.stringify({
                    status:                 'concluida',
                    houve_atraso,
                    motivo_atraso:          houve_atraso ? motivo_atraso : '',
                    observacoes_conclusao:  obs,
                    concluida_em,
                }),
            });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message || 'Erro');
            msgEl.textContent = '✅ Rota concluída com sucesso!';
            msgEl.className   = 'alert alert-success';
            setTimeout(() => location.reload(), 900);
        } catch (e) {
            msgEl.textContent = 'Erro: ' + e.message;
            msgEl.className   = 'alert alert-danger';
        }
    };

    // ══════════════════════════════════════════════════════════════════════════
    // ROTA: Ver detalhes de rota no histórico
    // ══════════════════════════════════════════════════════════════════════════
    window.verDetalhesRota = async function (id) {
        const modal = document.getElementById('modal-rota-detalhe-backdrop');
        const corpo = document.getElementById('rota-detalhe-corpo');
        modal.style.display = 'flex';
        corpo.innerHTML = '<div class="text-center text-muted py-3">Carregando…</div>';
        try {
            const r = await fetch(`${APP.api}/ceasa_rotas?id_detalhe=${id}`);
            const j = await r.json();
            // A API GET /ceasa_rotas retorna lista; filtramos pelo id
            const lista = j.data || [];
            const d = lista.find(x => +x.id === +id) || null;
            if (!d) throw new Error('Rota não encontrada.');

            const status_label = {planejada:'📋 Planejada', em_andamento:'🚐 Em andamento', concluida:'✅ Concluída'};
            const st = d.status || 'planejada';
            corpo.innerHTML = `
                <p class="mb-1"><strong>Data:</strong> ${new Date(d.data_rota + 'T00:00').toLocaleDateString('pt-BR')}</p>
                ${d.rota_descricao ? `<p class="mb-1"><strong>Rota:</strong> ${d.rota_descricao}</p>` : ''}
                ${d.frota_modelo   ? `<p class="mb-1"><strong>Veículo:</strong> ${d.frota_modelo} · ${d.frota_placa}</p>` : ''}
                ${d.motorista_nome ? `<p class="mb-1"><strong>Motorista:</strong> ${d.motorista_nome}</p>` : ''}
                ${d.auxiliar1_nome ? `<p class="mb-1"><strong>Auxiliar:</strong> ${d.auxiliar1_nome}${d.auxiliar2_nome ? ' · ' + d.auxiliar2_nome : ''}</p>` : ''}
                <hr>
                <p class="mb-1"><strong>Status:</strong> <span class="rota-hist-status ${st}">${status_label[st] || st}</span></p>
                ${d.concluida_em
                    ? `<p class="mb-1"><strong>Concluída às:</strong> ${new Date(d.concluida_em).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})}</p>`
                    : ''}
                ${+d.houve_atraso
                    ? `<p class="mb-1 text-danger"><strong>⚠️ Atraso:</strong> ${d.motivo_atraso || '—'}</p>`
                    : (d.concluida_em ? '<p class="mb-1 text-success"><strong>✅ Sem atrasos</strong></p>' : '')}
                ${d.observacoes_conclusao
                    ? `<p class="mb-1"><strong>Observações:</strong> ${d.observacoes_conclusao}</p>`
                    : ''}
            `;
        } catch (e) {
            corpo.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
        }
    };
    window.fecharDetalhesRota = function () {
        document.getElementById('modal-rota-detalhe-backdrop').style.display = 'none';
    };

    return {};
})();
</script>
