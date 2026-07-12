<?php
/**
 * Desffrut — Fragmento: RH (Fase 7)
 * Módulos: Funcionários · Ponto/Jornada · Folha de Pagamento · Baixas
 */
$role    = $u['role'];
$loja_id = $u['loja_id'] ?? null;

// Busca lojas para seletores (super_admin vê todas)
$pdo   = db();
$lojas = $pdo->query("SELECT id, nome, endereco, telefone FROM lojas WHERE ativo=1 ORDER BY nome")->fetchAll();
$lojas_json = json_encode(array_column($lojas, null, 'id'), JSON_UNESCAPED_UNICODE);
?>
<style>
/* ─── RH Fragment ──────────────────────────────────────────── */
.rh-wrap { padding: 16px; }
.rh-tabs { display:flex; gap:4px; margin-bottom:16px; flex-wrap:wrap; }
.rh-tab-btn {
    padding:7px 16px; border:1px solid #ddd; border-radius:6px;
    background:#fff; font-size:.82rem; cursor:pointer; color:#555;
    transition:all .15s;
}
.rh-tab-btn.active { background:#2e7d32; color:#fff; border-color:#2e7d32; }
.rh-tab-btn:hover:not(.active) { background:#f5f5f5; }
.rh-panel { display:none; }
.rh-panel.active { display:block; }

.rh-card {
    background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.08);
    padding:16px; margin-bottom:14px;
}
.rh-card-header {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:12px;
}
.rh-card-title { font-weight:600; font-size:.95rem; color:#333; }

/* tabela comum */
.rh-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.rh-table th { background:#f9f9f9; padding:8px 10px; text-align:left;
               border-bottom:2px solid #e0e0e0; color:#555; font-weight:600; }
.rh-table td { padding:7px 10px; border-bottom:1px solid #f0f0f0; color:#333; }
.rh-table tr:hover td { background:#fafafa; }

.rh-btn { padding:5px 11px; border-radius:5px; font-size:.78rem; border:none; cursor:pointer; }
.rh-btn-sm  { padding:3px 8px; font-size:.73rem; }
.rh-btn-green  { background:#2e7d32; color:#fff; }
.rh-btn-green:hover  { background:#1b5e20; }
.rh-btn-blue   { background:#1565c0; color:#fff; }
.rh-btn-blue:hover   { background:#0d47a1; }
.rh-btn-red    { background:#c62828; color:#fff; }
.rh-btn-red:hover    { background:#b71c1c; }
.rh-btn-outline { background:#fff; border:1px solid #bbb; color:#555; }
.rh-btn-outline:hover { background:#f5f5f5; }

.badge-ativo   { background:#e8f5e9; color:#2e7d32; padding:2px 8px; border-radius:20px; font-size:.72rem; font-weight:600; }
.badge-inativo { background:#ffebee; color:#c62828; padding:2px 8px; border-radius:20px; font-size:.72rem; font-weight:600; }
.badge-pago    { background:#e3f2fd; color:#1565c0; padding:2px 8px; border-radius:20px; font-size:.72rem; font-weight:600; }
.badge-calc    { background:#fff3e0; color:#e65100; padding:2px 8px; border-radius:20px; font-size:.72rem; font-weight:600; }

/* modal comum */
.rh-modal-bg {
    position:fixed; inset:0; background:rgba(0,0,0,.45);
    z-index:1050; display:flex; align-items:center; justify-content:center;
}
.rh-modal {
    background:#fff; border-radius:12px; width:100%; max-width:500px; padding:20px;
    box-shadow:0 8px 32px rgba(0,0,0,.18); max-height:90vh; overflow-y:auto;
}
.rh-modal h5 { margin:0 0 14px; font-size:.95rem; font-weight:700; color:#333; }
.rh-form-row { margin-bottom:10px; }
.rh-form-row label { display:block; font-size:.78rem; color:#555; margin-bottom:3px; font-weight:500; }
.rh-form-row input, .rh-form-row select, .rh-form-row textarea {
    width:100%; padding:7px 10px; border:1px solid #ccc; border-radius:6px;
    font-size:.83rem; outline:none; background:#fff;
}
.rh-form-row input:focus, .rh-form-row select:focus { border-color:#2e7d32; }
.rh-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

.rh-kpi-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
.rh-kpi {
    flex:1; min-width:120px; background:#fff; border-radius:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.08); padding:14px 16px; text-align:center;
}
.rh-kpi .kpi-val { font-size:1.5rem; font-weight:700; color:#2e7d32; }
.rh-kpi .kpi-lbl { font-size:.72rem; color:#888; margin-top:2px; }

.ponto-dia {
    display:grid; grid-template-columns:90px 80px 80px 80px 80px 80px; gap:4px;
    align-items:center; padding:5px 8px; border-bottom:1px solid #f0f0f0; font-size:.8rem;
}
.ponto-dia:hover { background:#fafafa; }
.saldo-pos { color:#2e7d32; font-weight:600; }
.saldo-neg { color:#c62828; font-weight:600; }

/* ── Contrato / Print ─────────────────────────────────────── */
.rh-btn-amber { background:#e65100; color:#fff; }
.rh-btn-amber:hover { background:#bf360c; }

/* Janela de impressão: usada via window.open() — estilos inline no JS */
</style>

<div class="rh-wrap">

    <!-- Tabs -->
    <div class="rh-tabs">
        <button class="rh-tab-btn active" data-panel="func" onclick="rhTab('func',this)">👥 Funcionários</button>
        <button class="rh-tab-btn" data-panel="ponto" onclick="rhTab('ponto',this)">⏱️ Ponto/Jornada</button>
        <button class="rh-tab-btn" data-panel="folha" onclick="rhTab('folha',this)">💰 Folha de Pagamento</button>
        <button class="rh-tab-btn" data-panel="baixas" onclick="rhTab('baixas',this)">📋 Baixas</button>
    </div>

    <!-- ═══════════════════ PAINEL: FUNCIONÁRIOS ══════════════════ -->
    <div class="rh-panel active" id="rh-panel-func">
        <div class="rh-card">
            <div class="rh-card-header">
                <span class="rh-card-title">Funcionários Ativos</span>
                <button class="rh-btn rh-btn-green" onclick="rhAbrirModalFunc()">+ Admitir</button>
            </div>
            <div id="rh-func-loading" class="text-center py-3 text-muted small">Carregando…</div>
            <table class="rh-table" id="rh-func-table" style="display:none">
                <thead>
                    <tr>
                        <th>Nome</th><th>Cargo</th><th>Loja</th>
                        <th>Contrato</th><th>Salário</th><th>Admissão</th>
                        <th>Status</th><th>Ações</th>
                    </tr>
                </thead>
                <tbody id="rh-func-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- ═══════════════════ PAINEL: PONTO ════════════════════════ -->
    <div class="rh-panel" id="rh-panel-ponto">
        <div class="rh-card">
            <div class="rh-card-header">
                <span class="rh-card-title">Ponto / Jornada</span>
                <button class="rh-btn rh-btn-blue" onclick="rhAbrirModalPonto()">+ Registrar Ponto</button>
            </div>
            <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label style="font-size:.76rem;color:#666;">Funcionário</label>
                    <select id="rh-ponto-func-sel" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:.82rem;" onchange="rhCarregarPonto()">
                        <option value="">— selecione —</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:.76rem;color:#666;">Mês</label>
                    <input type="month" id="rh-ponto-mes" value="<?= date('Y-m') ?>"
                           style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:.82rem;"
                           onchange="rhCarregarPonto()">
                </div>
            </div>
            <!-- KPIs resumo -->
            <div class="rh-kpi-row" id="rh-ponto-kpis" style="display:none">
                <div class="rh-kpi"><div class="kpi-val" id="rh-kpi-trabalhadas">—</div><div class="kpi-lbl">Horas Trabalhadas</div></div>
                <div class="rh-kpi"><div class="kpi-val" id="rh-kpi-esperadas">—</div><div class="kpi-lbl">Horas Esperadas</div></div>
                <div class="rh-kpi"><div class="kpi-val" id="rh-kpi-saldo">—</div><div class="kpi-lbl">Banco de Horas</div></div>
            </div>
            <!-- Header tabela -->
            <div class="ponto-dia" style="font-weight:600;color:#555;border-bottom:2px solid #e0e0e0;">
                <span>Dia</span><span>Entrada</span><span>S.Intervalo</span>
                <span>E.Intervalo</span><span>Saída</span><span>Trabalhadas</span>
            </div>
            <div id="rh-ponto-lista"></div>
        </div>
    </div>

    <!-- ═══════════════════ PAINEL: FOLHA ════════════════════════ -->
    <div class="rh-panel" id="rh-panel-folha">
        <div class="rh-card">
            <div class="rh-card-header">
                <span class="rh-card-title">Folha de Pagamento</span>
                <button class="rh-btn rh-btn-green" onclick="rhAbrirModalFolha()">+ Gerar Folha</button>
            </div>
            <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label style="font-size:.76rem;color:#666;">Mês referência</label>
                    <input type="month" id="rh-folha-mes" value="<?= date('Y-m') ?>"
                           style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:.82rem;"
                           onchange="rhCarregarFolha()">
                </div>
            </div>
            <div id="rh-folha-loading" class="text-center py-3 text-muted small" style="display:none">Carregando…</div>
            <table class="rh-table" id="rh-folha-table">
                <thead>
                    <tr>
                        <th>Funcionário</th><th>Loja</th><th>Salário Base</th>
                        <th>H. Extras</th><th>Valor Extras</th><th>Descontos</th>
                        <th>Total Líquido</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody id="rh-folha-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- ═══════════════════ PAINEL: BAIXAS ═══════════════════════ -->
    <div class="rh-panel" id="rh-panel-baixas">
        <div class="rh-card">
            <div class="rh-card-header">
                <span class="rh-card-title">Histórico de Baixas (Demitidos)</span>
            </div>
            <div id="rh-baixas-loading" class="text-center py-3 text-muted small">Carregando…</div>
            <table class="rh-table" id="rh-baixas-table" style="display:none">
                <thead>
                    <tr><th>Nome</th><th>Cargo</th><th>Loja</th><th>Admissão</th><th>Demissão</th><th>Tempo de Casa</th></tr>
                </thead>
                <tbody id="rh-baixas-tbody"></tbody>
            </table>
        </div>
    </div>

</div><!-- /rh-wrap -->

<!-- ─── Modal: Admitir / Editar Funcionário ────────────────── -->
<div class="rh-modal-bg" id="rh-modal-func" style="display:none">
<div class="rh-modal" style="max-width:640px">
    <h5 id="rh-f-titulo">👤 Admitir Funcionário</h5>
    <input type="hidden" id="rh-f-id">
    <input type="hidden" id="rh-f-usuario-id">

    <!-- Toggle novo cadastro / vincular existente (somente na admissão) -->
    <div class="rh-tabs" id="rh-f-modo-toggle" style="margin-bottom:12px;">
        <button type="button" class="rh-tab-btn active" data-modo="novo" onclick="rhFuncModo('novo',this)">+ Novo Cadastro</button>
        <button type="button" class="rh-tab-btn" data-modo="vincular" onclick="rhFuncModo('vincular',this)">🔗 Vincular Usuário Existente</button>
    </div>

    <!-- Busca de usuário existente -->
    <div class="rh-form-row" id="rh-f-busca-wrap" style="display:none;position:relative;">
        <label>Buscar usuário (nome ou e-mail)</label>
        <input type="text" id="rh-f-busca" placeholder="Digite ao menos 2 letras..." autocomplete="off">
        <div id="rh-f-busca-resultados" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;
             border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);z-index:20;max-height:180px;overflow-y:auto;"></div>
        <p id="rh-f-busca-selecionado" class="text-muted small mt-1" style="display:none;"></p>
    </div>

    <!-- Foto -->
    <div class="rh-form-row" style="display:flex;align-items:center;gap:14px;">
        <img id="rh-f-foto-preview" src="" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;
             background:#eee;display:none;border:1px solid #ddd;">
        <div style="flex:1;">
            <label>Foto do Funcionário</label>
            <input type="file" id="rh-f-foto" accept="image/*">
            <input type="hidden" id="rh-f-foto-path">
            <small class="text-muted" style="font-size:.72rem;">Convertida automaticamente para WebP (&lt; 100 KB).</small>
        </div>
    </div>

    <div class="rh-form-grid">
        <div class="rh-form-row" id="rh-f-nome-wrap">
            <label>Nome completo *</label>
            <input type="text" id="rh-f-nome">
        </div>
        <div class="rh-form-row" id="rh-f-email-wrap">
            <label>E-mail *</label>
            <input type="email" id="rh-f-email">
        </div>
        <div class="rh-form-row">
            <label>Telefone</label>
            <input type="text" id="rh-f-telefone" placeholder="(00) 00000-0000">
        </div>
        <div class="rh-form-row">
            <label>WhatsApp</label>
            <input type="text" id="rh-f-whatsapp" placeholder="(00) 00000-0000">
        </div>
        <div class="rh-form-row">
            <label>CPF</label>
            <input type="text" id="rh-f-cpf" placeholder="000.000.000-00">
        </div>
        <div class="rh-form-row">
            <label>Data de Nascimento</label>
            <input type="date" id="rh-f-nascimento">
        </div>
    </div>

    <div class="rh-form-row"><label style="margin-top:6px;font-weight:700;">Endereço</label></div>
    <div class="rh-form-grid">
        <div class="rh-form-row" style="grid-column:1/3;">
            <label>Rua / Avenida</label>
            <input type="text" id="rh-f-endereco">
        </div>
        <div class="rh-form-row">
            <label>Número</label>
            <input type="text" id="rh-f-numero">
        </div>
        <div class="rh-form-row">
            <label>Complemento</label>
            <input type="text" id="rh-f-complemento">
        </div>
        <div class="rh-form-row">
            <label>Bairro</label>
            <input type="text" id="rh-f-bairro">
        </div>
        <div class="rh-form-row">
            <label>Cidade</label>
            <input type="text" id="rh-f-cidade">
        </div>
    </div>

    <div class="rh-form-row"><label style="margin-top:6px;font-weight:700;">Dados do Contrato</label></div>
    <div class="rh-form-grid">
        <div class="rh-form-row">
            <label>Cargo *</label>
            <select id="rh-f-cargo">
                <option value="">— selecione —</option>
                <option value="Gerente">Gerente</option>
                <option value="RH">RH</option>
                <option value="Caixa/Atendente">Caixa/Atendente</option>
                <option value="Entregador">Entregador</option>
                <option value="Motorista">Motorista</option>
                <option value="Auxiliar (CEASA)">Auxiliar (CEASA)</option>
            </select>
        </div>
        <div class="rh-form-row" id="rh-f-loja-cargo-wrap">
            <label>Loja *</label>
            <select id="rh-f-loja">
                <?php foreach($lojas as $l): ?>
                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="rh-form-row">
            <label>Tipo de Contrato</label>
            <select id="rh-f-tipo">
                <option value="clt">CLT</option>
                <option value="pj">PJ</option>
                <option value="autonomo">Autônomo</option>
                <option value="estagio">Estágio</option>
            </select>
        </div>
        <div class="rh-form-row">
            <label>Salário Base (R$) *</label>
            <input type="number" id="rh-f-salario" step="0.01" min="0" placeholder="0,00">
        </div>
        <div class="rh-form-row">
            <label>Carga Horária (h/dia)</label>
            <input type="number" id="rh-f-carga" value="8" min="1" max="12">
        </div>
        <div class="rh-form-row">
            <label>Data de Admissão *</label>
            <input type="date" id="rh-f-admissao" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
    <div class="rh-form-row">
        <label>Observações</label>
        <textarea id="rh-f-obs" rows="2"></textarea>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
        <button class="rh-btn rh-btn-outline" onclick="rhFecharModalFunc()">Cancelar</button>
        <button class="rh-btn rh-btn-green" id="rh-f-btn-salvar" onclick="rhSalvarFunc()">Admitir</button>
    </div>
</div>
</div>

<!-- ─── Modal: Registrar Ponto ─────────────────────────────── -->
<div class="rh-modal-bg" id="rh-modal-ponto" style="display:none">
<div class="rh-modal">
    <h5>⏱️ Registrar Ponto</h5>
    <div class="rh-form-row">
        <label>Funcionário</label>
        <select id="rh-p-func-sel">
            <option value="">— selecione —</option>
        </select>
    </div>
    <div class="rh-form-grid">
        <div class="rh-form-row">
            <label>Tipo</label>
            <select id="rh-p-tipo">
                <option value="entrada">Entrada</option>
                <option value="saida_intervalo">Saída Intervalo</option>
                <option value="entrada_intervalo">Entrada Intervalo</option>
                <option value="saida">Saída</option>
            </select>
        </div>
        <div class="rh-form-row">
            <label>Data e Hora</label>
            <input type="datetime-local" id="rh-p-dt">
        </div>
    </div>
    <div class="rh-form-row">
        <label>Observação</label>
        <input type="text" id="rh-p-obs" placeholder="Opcional">
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
        <button class="rh-btn rh-btn-outline" onclick="rhFecharModalPonto()">Cancelar</button>
        <button class="rh-btn rh-btn-blue" onclick="rhSalvarPonto()">Registrar</button>
    </div>
</div>
</div>

<!-- ─── Modal: Gerar Folha ─────────────────────────────────── -->
<div class="rh-modal-bg" id="rh-modal-folha" style="display:none">
<div class="rh-modal">
    <h5>💰 Gerar / Editar Folha</h5>
    <div class="rh-form-row">
        <label>Funcionário</label>
        <select id="rh-fo-func-sel"></select>
    </div>
    <div class="rh-form-grid">
        <div class="rh-form-row">
            <label>Mês referência</label>
            <input type="month" id="rh-fo-mes" value="<?= date('Y-m') ?>">
        </div>
        <div class="rh-form-row">
            <label>Salário Base (R$)</label>
            <input type="number" id="rh-fo-sal" step="0.01" min="0">
        </div>
        <div class="rh-form-row">
            <label>Horas Extras (h)</label>
            <input type="number" id="rh-fo-he" step="0.5" min="0" value="0">
        </div>
        <div class="rh-form-row">
            <label>Valor das Extras (R$)</label>
            <input type="number" id="rh-fo-ve" step="0.01" min="0" value="0">
        </div>
        <div class="rh-form-row">
            <label>Descontos (R$)</label>
            <input type="number" id="rh-fo-des" step="0.01" min="0" value="0">
        </div>
    </div>
    <div class="rh-form-row">
        <label>Observações</label>
        <textarea id="rh-fo-obs" rows="2"></textarea>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
        <button class="rh-btn rh-btn-outline" onclick="rhFecharModalFolha()">Cancelar</button>
        <button class="rh-btn rh-btn-green" onclick="rhSalvarFolha()">Salvar</button>
    </div>
</div>
</div>

<!-- ─── Modal: Desligar ────────────────────────────────────── -->
<div class="rh-modal-bg" id="rh-modal-desligar" style="display:none">
<div class="rh-modal">
    <h5>⚠️ Desligar Funcionário</h5>
    <input type="hidden" id="rh-des-id">
    <p id="rh-des-nome" class="text-muted small mb-3"></p>
    <div class="rh-form-row">
        <label>Data de Demissão</label>
        <input type="date" id="rh-des-data" value="<?= date('Y-m-d') ?>">
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
        <button class="rh-btn rh-btn-outline" onclick="rhFecharModalDesligar()">Cancelar</button>
        <button class="rh-btn rh-btn-red" onclick="rhConfirmarDesligar()">Confirmar Desligamento</button>
    </div>
</div>
</div>

<script>
(function(){
// window.APP nunca é definido neste projeto (só existe a chamada opcional
// "window.APP?.x", nunca a atribuição) — usar BASE_PATH injetado via PHP.
const BASE = <?= json_encode(BASE_PATH, JSON_UNESCAPED_SLASHES) ?>;
const API  = BASE + '/api/v1';
let _funcs = []; // cache

function fmt(v){ return 'R$ ' + parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function fmtData(d){ return d ? d.split('-').reverse().join('/') : '—'; }
function toast(msg, ok=true){
    const d = document.createElement('div');
    d.textContent = msg;
    Object.assign(d.style,{
        position:'fixed',bottom:'24px',right:'24px',zIndex:9999,
        background:ok?'#2e7d32':'#c62828',color:'#fff',
        padding:'10px 18px',borderRadius:'8px',boxShadow:'0 4px 12px rgba(0,0,0,.2)',
        fontSize:'.83rem',maxWidth:'320px'
    });
    document.body.appendChild(d);
    setTimeout(()=>d.remove(), 3000);
}

/* ─ Tabs ─────────────────────────────────────────────────── */
window.rhTab = function(panel, btn) {
    document.querySelectorAll('.rh-panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.rh-tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById('rh-panel-'+panel).classList.add('active');
    btn.classList.add('active');
    if(panel==='func')   rhCarregarFuncs(true);
    if(panel==='baixas') rhCarregarBaixas();
    if(panel==='folha')  rhCarregarFolha();
    if(panel==='ponto')  rhPopularSelects();
};

/* ─ Carregar Funcionários ───────────────────────────────── */
async function rhCarregarFuncs(ativos=true) {
    const load = document.getElementById('rh-func-loading');
    const tbl  = document.getElementById('rh-func-table');
    const tbody = document.getElementById('rh-func-tbody');
    load.style.display='';  tbl.style.display='none';
    try {
        const r = await fetch(`${API}/funcionarios?ativos=${ativos?1:0}`);
        const j = await r.json();
        _funcs = j.data || [];
        tbody.innerHTML = _funcs.map(f=>`
            <tr>
                <td><strong>${f.nome}</strong><br><small class="text-muted">${f.email}</small></td>
                <td>${f.cargo}</td>
                <td>${f.loja_nome}</td>
                <td><span style="text-transform:uppercase;font-size:.7rem;">${f.tipo_contrato||'clt'}</span></td>
                <td>${fmt(f.salario_base)}</td>
                <td>${fmtData(f.admitido_em)}</td>
                <td><span class="${f.ativo?'badge-ativo':'badge-inativo'}">${f.ativo?'Ativo':'Inativo'}</span></td>
                <td style="white-space:nowrap;display:flex;gap:4px;flex-wrap:wrap;">
                    <button class="rh-btn rh-btn-sm rh-btn-blue" onclick="rhAbrirEditarFunc(${f.id})">✏️ Editar</button>
                    <a class="rh-btn rh-btn-sm rh-btn-outline" href="${BASE}/rh/ficha/${f.id}" target="_blank">🪪 Ficha</a>
                    <a class="rh-btn rh-btn-sm rh-btn-outline" href="${BASE}/rh/cracha/${f.id}" target="_blank">🎫 Crachá</a>
                    <button class="rh-btn rh-btn-sm rh-btn-amber" onclick="rhGerarContrato(${f.id})">📄 Contrato</button>
                    ${f.ativo?`<button class="rh-btn rh-btn-sm rh-btn-red" onclick="rhAbrirDesligar(${f.id},'${f.nome.replace(/'/g,"\\'")}')">Desligar</button>`:''}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="8" class="text-center text-muted py-3">Nenhum registro encontrado.</td></tr>';
        load.style.display='none'; tbl.style.display='';
        rhPopularSelects();
    } catch(e) {
        load.textContent = 'Erro ao carregar: '+e.message;
    }
}

/* ─ Popular selects de funcionários ─────────────────────── */
function rhPopularSelects() {
    const opts = '<option value="">— selecione —</option>' +
        _funcs.filter(f=>f.ativo).map(f=>`<option value="${f.id}">${f.nome} (${f.loja_nome})</option>`).join('');
    ['rh-ponto-func-sel','rh-p-func-sel','rh-fo-func-sel'].forEach(id=>{
        const el = document.getElementById(id);
        if(el){ el.innerHTML = opts; }
    });
}

/* ─ Carregar Baixas ────────────────────────────────────── */
async function rhCarregarBaixas() {
    const load  = document.getElementById('rh-baixas-loading');
    const tbl   = document.getElementById('rh-baixas-table');
    const tbody = document.getElementById('rh-baixas-tbody');
    load.style.display=''; tbl.style.display='none';
    try {
        const r = await fetch(`${API}/funcionarios?ativos=0`);
        const j = await r.json();
        const lista = j.data || [];
        tbody.innerHTML = lista.map(f=>{
            let tempo = '—';
            if(f.admitido_em && f.demitido_em) {
                const ms = new Date(f.demitido_em) - new Date(f.admitido_em);
                const dias = Math.round(ms/86400000);
                tempo = dias > 365 ? Math.round(dias/365)+'a '+Math.round((dias%365)/30)+'m' : dias+'d';
            }
            return `<tr>
                <td><strong>${f.nome}</strong></td>
                <td>${f.cargo}</td>
                <td>${f.loja_nome}</td>
                <td>${fmtData(f.admitido_em)}</td>
                <td>${fmtData(f.demitido_em)}</td>
                <td>${tempo}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">Nenhuma baixa registrada.</td></tr>';
        load.style.display='none'; tbl.style.display='';
    } catch(e) { load.textContent='Erro: '+e.message; }
}

/* ─ Ponto ────────────────────────────────────────────────── */
window.rhCarregarPonto = async function() {
    const fid = document.getElementById('rh-ponto-func-sel').value;
    const mes = document.getElementById('rh-ponto-mes').value;
    const lista = document.getElementById('rh-ponto-lista');
    const kpis  = document.getElementById('rh-ponto-kpis');
    if(!fid) { lista.innerHTML='<p class="text-muted small text-center py-2">Selecione um funcionário.</p>'; kpis.style.display='none'; return; }
    lista.innerHTML = '<p class="text-center text-muted small py-2">Carregando…</p>';
    try {
        const r = await fetch(`${API}/ponto/resumo?funcionario_id=${fid}&mes=${mes}`);
        const j = await r.json();
        const d = j.data || {};
        document.getElementById('rh-kpi-trabalhadas').textContent = (d.total_horas||0)+'h';
        document.getElementById('rh-kpi-esperadas').textContent   = (d.esperado_horas||0)+'h';
        const saldo = d.saldo_horas||0;
        const el = document.getElementById('rh-kpi-saldo');
        el.textContent = (saldo>=0?'+':'')+saldo+'h';
        el.className = 'kpi-val '+(saldo>=0?'saldo-pos':'saldo-neg');
        kpis.style.display='flex';
        const dias = d.dias || [];
        lista.innerHTML = dias.map(dia=>`
            <div class="ponto-dia">
                <span>${dia.dia.split('-').reverse().join('/')}</span>
                <span>${dia.entrada||'—'}</span>
                <span>${dia.saida_intervalo||'—'}</span>
                <span>${dia.entrada_intervalo||'—'}</span>
                <span>${dia.saida||'—'}</span>
                <span>${dia.horas_trabalhadas}h</span>
            </div>
        `).join('') || '<p class="text-muted small text-center py-2">Nenhum registro neste mês.</p>';
    } catch(e) { lista.innerHTML='<p class="text-danger small text-center">Erro: '+e.message+'</p>'; }
};

/* ─ Folha ───────────────────────────────────────────────── */
window.rhCarregarFolha = async function() {
    const mes   = document.getElementById('rh-folha-mes').value;
    const tbody = document.getElementById('rh-folha-tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Carregando…</td></tr>';
    try {
        const r = await fetch(`${API}/ponto/folha?mes=${mes}`);
        const j = await r.json();
        const lista = j.data || [];
        tbody.innerHTML = lista.map(f=>`
            <tr>
                <td><strong>${f.funcionario_nome}</strong></td>
                <td>${f.loja_nome}</td>
                <td>${fmt(f.salario_base)}</td>
                <td>${f.horas_extras}h</td>
                <td>${fmt(f.valor_extras)}</td>
                <td>${fmt(f.descontos)}</td>
                <td><strong>${fmt(f.total_liquido)}</strong></td>
                <td><span class="${f.status==='pago'?'badge-pago':'badge-calc'}">${f.status==='pago'?'Pago':'Calculado'}</span></td>
                <td>
                    ${f.status!=='pago'?`<button class="rh-btn rh-btn-sm rh-btn-blue" onclick="rhMarcarFolhaPaga(${f.id})">Marcar Pago</button>`:'✅'}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="9" class="text-center text-muted py-2">Nenhuma folha para este mês.</td></tr>';
    } catch(e) { tbody.innerHTML='<tr><td colspan="9" class="text-danger py-2 text-center">Erro: '+e.message+'</td></tr>'; }
};

window.rhMarcarFolhaPaga = async function(id) {
    if(!confirm('Confirmar pagamento desta folha?')) return;
    const r = await fetch(`${API}/ponto/folha/${id}`, { method:'PATCH', headers:{'Content-Type':'application/json'}, body:'{}' });
    const j = await r.json();
    toast(j.message||'Atualizado', j.status==='ok');
    rhCarregarFolha();
};

/* ─ Modais Funcionários ─────────────────────────────────── */
let _rhFuncEditando = null; // id do funcionário em edição, ou null = admissão
let _rhBuscaTimer = null;

function _rhFuncLimparForm() {
    ['rh-f-id','rh-f-usuario-id','rh-f-busca','rh-f-foto-path','rh-f-nome','rh-f-email',
     'rh-f-telefone','rh-f-whatsapp','rh-f-cpf','rh-f-nascimento','rh-f-endereco','rh-f-numero',
     'rh-f-complemento','rh-f-bairro','rh-f-cidade','rh-f-cargo','rh-f-obs'].forEach(id=>{
        const el = document.getElementById(id); if(el) el.value='';
    });
    document.getElementById('rh-f-foto').value = '';
    document.getElementById('rh-f-foto-preview').style.display='none';
    document.getElementById('rh-f-tipo').value = 'clt';
    document.getElementById('rh-f-salario').value = '';
    document.getElementById('rh-f-carga').value = 8;
    document.getElementById('rh-f-admissao').value = '<?= date('Y-m-d') ?>';
    document.getElementById('rh-f-busca-selecionado').style.display='none';
    document.getElementById('rh-f-busca-resultados').style.display='none';
    document.getElementById('rh-f-email').disabled = false;
}

window.rhFuncModo = function(modo, btn) {
    document.querySelectorAll('#rh-f-modo-toggle .rh-tab-btn').forEach(b=>b.classList.remove('active'));
    if(btn) btn.classList.add('active');
    const ehNovo = modo === 'novo';
    document.getElementById('rh-f-busca-wrap').style.display = ehNovo ? 'none' : 'block';
    document.getElementById('rh-f-nome-wrap').style.display  = 'block';
    document.getElementById('rh-f-email-wrap').style.display = 'block';
    document.getElementById('rh-f-usuario-id').value = '';
    if(!ehNovo){
        document.getElementById('rh-f-nome').value = '';
        document.getElementById('rh-f-email').value = '';
    }
};

// Autocomplete de busca de usuário existente
document.getElementById('rh-f-busca')?.addEventListener('input', function(){
    clearTimeout(_rhBuscaTimer);
    const q = this.value.trim();
    const box = document.getElementById('rh-f-busca-resultados');
    if(q.length < 2){ box.style.display='none'; box.innerHTML=''; return; }
    _rhBuscaTimer = setTimeout(async ()=>{
        try {
            const r = await fetch(`${API}/funcionarios/usuarios-busca?q=${encodeURIComponent(q)}`);
            const j = await r.json();
            const lista = j.data || [];
            box.innerHTML = lista.map(u=>`
                <div class="rh-busca-item" style="padding:7px 10px;cursor:pointer;font-size:.82rem;border-bottom:1px solid #f0f0f0;"
                     onmousedown="rhSelecionarUsuario(${u.id},'${(u.nome||'').replace(/'/g,"\\'")}','${(u.email||'').replace(/'/g,"\\'")}')">
                    <strong>${u.nome}</strong><br><small class="text-muted">${u.email}</small>
                </div>`).join('') || '<div class="text-muted small p-2">Nenhum usuário encontrado.</div>';
            box.style.display = 'block';
        } catch(e){ box.style.display='none'; }
    }, 300);
});
document.getElementById('rh-f-busca')?.addEventListener('blur', function(){
    setTimeout(()=>{ document.getElementById('rh-f-busca-resultados').style.display='none'; }, 150);
});

window.rhSelecionarUsuario = function(id, nome, email) {
    document.getElementById('rh-f-usuario-id').value = id;
    document.getElementById('rh-f-busca').value = '';
    document.getElementById('rh-f-busca-resultados').style.display='none';
    const sel = document.getElementById('rh-f-busca-selecionado');
    sel.textContent = `Selecionado: ${nome} (${email})`;
    sel.style.display='block';
    document.getElementById('rh-f-nome-wrap').style.display='none';
    document.getElementById('rh-f-email-wrap').style.display='none';
};

// Upload de foto — envia imediatamente ao selecionar, converte no servidor p/ WebP < 100KB
document.getElementById('rh-f-foto')?.addEventListener('change', async function(){
    const file = this.files[0];
    if(!file) return;
    const fd = new FormData();
    fd.append('foto', file);
    fd.append('destino', 'funcionario');
    const preview = document.getElementById('rh-f-foto-preview');
    try {
        const r = await fetch(`${API}/uploads`, { method:'POST', body: fd });
        const j = await r.json();
        if(j.status === 'ok'){
            document.getElementById('rh-f-foto-path').value = j.data.path;
            preview.src = j.data.url;
            preview.style.display = 'block';
            toast('Foto enviada.', true);
        } else {
            toast(j.message || 'Erro ao enviar foto.', false);
        }
    } catch(e) { toast('Erro ao enviar foto: '+e.message, false); }
});

window.rhAbrirModalFunc = function() {
    _rhFuncEditando = null;
    _rhFuncLimparForm();
    document.getElementById('rh-f-titulo').textContent = '👤 Admitir Funcionário';
    document.getElementById('rh-f-btn-salvar').textContent = 'Admitir';
    document.getElementById('rh-f-modo-toggle').style.display = 'flex';
    rhFuncModo('novo', document.querySelector('#rh-f-modo-toggle .rh-tab-btn'));
    document.getElementById('rh-modal-func').style.display='flex';
};

window.rhAbrirEditarFunc = async function(id) {
    try {
        const r = await fetch(`${API}/funcionarios/${id}`);
        const j = await r.json();
        if(j.status !== 'ok'){ toast(j.message||'Erro ao carregar funcionário.', false); return; }
        const f = j.data;
        _rhFuncEditando = id;
        _rhFuncLimparForm();
        document.getElementById('rh-f-titulo').textContent = '✏️ Editar Funcionário';
        document.getElementById('rh-f-btn-salvar').textContent = 'Salvar Alterações';
        document.getElementById('rh-f-modo-toggle').style.display = 'none';
        document.getElementById('rh-f-busca-wrap').style.display = 'none';
        document.getElementById('rh-f-nome-wrap').style.display = 'block';
        document.getElementById('rh-f-email-wrap').style.display = 'block';

        document.getElementById('rh-f-id').value = f.id;
        document.getElementById('rh-f-nome').value = f.nome || '';
        document.getElementById('rh-f-email').value = f.email || '';
        document.getElementById('rh-f-email').disabled = true;
        document.getElementById('rh-f-telefone').value = f.telefone || '';
        document.getElementById('rh-f-whatsapp').value = f.whatsapp || '';
        document.getElementById('rh-f-cpf').value = f.cpf || '';
        document.getElementById('rh-f-nascimento').value = f.data_nascimento || '';
        document.getElementById('rh-f-endereco').value = f.endereco || '';
        document.getElementById('rh-f-numero').value = f.numero || '';
        document.getElementById('rh-f-complemento').value = f.complemento || '';
        document.getElementById('rh-f-bairro').value = f.bairro || '';
        document.getElementById('rh-f-cidade').value = f.cidade || '';
        document.getElementById('rh-f-cargo').value = f.cargo || '';
        document.getElementById('rh-f-loja').value = f.loja_id || '';
        document.getElementById('rh-f-tipo').value = f.tipo_contrato || 'clt';
        document.getElementById('rh-f-salario').value = f.salario_base || '';
        document.getElementById('rh-f-carga').value = f.carga_horaria || 8;
        document.getElementById('rh-f-admissao').value = f.admitido_em || '';
        document.getElementById('rh-f-obs').value = f.observacoes || '';
        if(f.foto_perfil){
            document.getElementById('rh-f-foto-path').value = f.foto_perfil;
            const preview = document.getElementById('rh-f-foto-preview');
            preview.src = f.foto_perfil.startsWith('http') ? f.foto_perfil : BASE + '/' + f.foto_perfil;
            preview.style.display = 'block';
        }
        document.getElementById('rh-modal-func').style.display='flex';
    } catch(e) { toast('Erro: '+e.message, false); }
};

window.rhFecharModalFunc  = ()=> document.getElementById('rh-modal-func').style.display='none';

window.rhSalvarFunc = async function() {
    const cargo = document.getElementById('rh-f-cargo').value;
    if(!cargo) { toast('Selecione o cargo.', false); return; }
    const salario = parseFloat(document.getElementById('rh-f-salario').value);
    if(!salario && salario !== 0) { toast('Informe o salário base.', false); return; }
    const admissao = document.getElementById('rh-f-admissao').value;
    if(!admissao) { toast('Informe a data de admissão.', false); return; }
    const loja_id = parseInt(document.getElementById('rh-f-loja').value);

    const body = {
        loja_id,
        cargo,
        tipo_contrato:  document.getElementById('rh-f-tipo').value,
        salario_base:   salario,
        carga_horaria:  parseInt(document.getElementById('rh-f-carga').value || 8),
        admitido_em:    admissao,
        observacoes:    document.getElementById('rh-f-obs').value,
        telefone:       document.getElementById('rh-f-telefone').value,
        whatsapp:       document.getElementById('rh-f-whatsapp').value,
        cpf:            document.getElementById('rh-f-cpf').value,
        data_nascimento:document.getElementById('rh-f-nascimento').value,
        endereco:       document.getElementById('rh-f-endereco').value,
        numero:         document.getElementById('rh-f-numero').value,
        complemento:    document.getElementById('rh-f-complemento').value,
        bairro:         document.getElementById('rh-f-bairro').value,
        cidade:         document.getElementById('rh-f-cidade').value,
        foto_perfil:    document.getElementById('rh-f-foto-path').value,
    };

    if(_rhFuncEditando) {
        body.nome = document.getElementById('rh-f-nome').value;
        const r = await fetch(`${API}/funcionarios/${_rhFuncEditando}`,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
        const j = await r.json();
        toast(j.message||'Salvo', j.status==='ok');
        if(j.status==='ok'){ rhFecharModalFunc(); rhCarregarFuncs(); }
        return;
    }

    const usuarioId = document.getElementById('rh-f-usuario-id').value;
    if(usuarioId) {
        body.usuario_id = parseInt(usuarioId);
    } else {
        body.nome  = document.getElementById('rh-f-nome').value;
        body.email = document.getElementById('rh-f-email').value;
        if(!body.nome || !body.email) { toast('Informe nome e e-mail, ou vincule um usuário existente.', false); return; }
    }

    const r = await fetch(`${API}/funcionarios`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j = await r.json();
    if(j.status==='ok'){
        rhFecharModalFunc(); rhCarregarFuncs();
        if(j.data?.senha_temporaria){
            toast(`Funcionário admitido! Senha temporária: ${j.data.senha_temporaria}`, true);
        } else {
            toast(j.message||'Salvo', true);
        }
    } else {
        toast(j.message||'Erro ao salvar', false);
    }
};

/* ─ Modais Ponto ────────────────────────────────────────── */
window.rhAbrirModalPonto = function(){
    const now = new Date();
    const pad = n=>String(n).padStart(2,'0');
    document.getElementById('rh-p-dt').value =
        `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
    document.getElementById('rh-modal-ponto').style.display='flex';
};
window.rhFecharModalPonto = ()=> document.getElementById('rh-modal-ponto').style.display='none';

window.rhSalvarPonto = async function(){
    const fid = document.getElementById('rh-p-func-sel').value;
    if(!fid){ toast('Selecione um funcionário.',false); return; }
    const dt = document.getElementById('rh-p-dt').value.replace('T',' ')+':00';
    const body = {
        funcionario_id: parseInt(fid),
        tipo:           document.getElementById('rh-p-tipo').value,
        registrado_em:  dt,
        observacao:     document.getElementById('rh-p-obs').value,
    };
    const r = await fetch(`${API}/ponto`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j = await r.json();
    toast(j.message||'Registrado', j.status==='ok');
    if(j.status==='ok'){ rhFecharModalPonto(); rhCarregarPonto(); }
};

/* ─ Modais Folha ────────────────────────────────────────── */
window.rhAbrirModalFolha = ()=>{
    document.getElementById('rh-modal-folha').style.display='flex';
};
window.rhFecharModalFolha = ()=> document.getElementById('rh-modal-folha').style.display='none';

window.rhSalvarFolha = async function(){
    const fid = document.getElementById('rh-fo-func-sel').value;
    if(!fid){ toast('Selecione um funcionário.',false); return; }
    const f = _funcs.find(x=>x.id==fid);
    const body = {
        funcionario_id: parseInt(fid),
        mes_referencia: document.getElementById('rh-fo-mes').value,
        salario_base:   parseFloat(document.getElementById('rh-fo-sal').value || f?.salario_base || 0),
        horas_extras:   parseFloat(document.getElementById('rh-fo-he').value||0),
        valor_extras:   parseFloat(document.getElementById('rh-fo-ve').value||0),
        descontos:      parseFloat(document.getElementById('rh-fo-des').value||0),
        observacoes:    document.getElementById('rh-fo-obs').value,
    };
    const r = await fetch(`${API}/ponto/folha`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j = await r.json();
    toast(j.message||'Salvo', j.status==='ok');
    if(j.status==='ok'){ rhFecharModalFolha(); rhCarregarFolha(); }
};

/* Auto-preenche salário ao selecionar funcionário no modal folha */
document.getElementById('rh-fo-func-sel')?.addEventListener('change', function(){
    const f = _funcs.find(x=>x.id==this.value);
    if(f) document.getElementById('rh-fo-sal').value = f.salario_base;
});

/* ─ Desligar ─────────────────────────────────────────────── */
window.rhAbrirDesligar = function(id, nome){
    document.getElementById('rh-des-id').value = id;
    document.getElementById('rh-des-nome').textContent = nome;
    document.getElementById('rh-modal-desligar').style.display='flex';
};
window.rhFecharModalDesligar = ()=> document.getElementById('rh-modal-desligar').style.display='none';
window.rhConfirmarDesligar = async function(){
    const id  = document.getElementById('rh-des-id').value;
    const dem = document.getElementById('rh-des-data').value;
    const r = await fetch(`${API}/funcionarios/${id}`,{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({demitido_em:dem})});
    const j = await r.json();
    toast(j.message||'Desligado', j.status==='ok');
    if(j.status==='ok'){ rhFecharModalDesligar(); rhCarregarFuncs(); }
};

/* ─ Gerador de Contrato ─────────────────────────────────── */
const _LOJAS_MAP = <?= $lojas_json ?? '{}' ?>;

const TIPOS_CONTRATO = {
    clt:      'Contrato Individual de Trabalho por Prazo Indeterminado',
    pj:       'Contrato de Prestação de Serviços (Pessoa Jurídica)',
    autonomo: 'Contrato de Prestação de Serviços Autônomo',
    estagio:  'Termo de Compromisso de Estágio',
};

function _contratoCSS() {
    return `
        body { font-family: 'Times New Roman', serif; font-size: 12pt; margin: 2.5cm; line-height: 1.7; color: #111; }
        h1 { text-align: center; font-size: 14pt; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        h2 { text-align: center; font-size: 11pt; font-weight: normal; margin-top: 0; margin-bottom: 28px; }
        .secao { margin-top: 18px; }
        .secao-titulo { font-weight: bold; text-transform: uppercase; margin-bottom: 4px; font-size: 11pt; }
        .clausula { margin-top: 14px; }
        .clausula-num { font-weight: bold; }
        p { margin: 6px 0; text-align: justify; }
        .dados-box { border: 1px solid #888; border-radius: 4px; padding: 10px 14px; margin: 10px 0; }
        .dados-box p { margin: 3px 0; }
        .label { font-weight: bold; }
        .assinaturas { margin-top: 60px; display: flex; justify-content: space-between; gap: 40px; }
        .assinatura-bloco { flex: 1; text-align: center; }
        .assinatura-linha { border-top: 1px solid #333; padding-top: 6px; margin-top: 48px; font-size: 10pt; }
        .rodape { margin-top: 40px; text-align: center; font-size: 9pt; color: #555; border-top: 1px solid #ccc; padding-top: 8px; }
        .campo-preencher { border-bottom: 1px solid #333; display: inline-block; min-width: 180px; }
        @media print {
            body { margin: 1.5cm; }
            button { display: none !important; }
        }
    `;
}

function _gerarHTMLContrato(f, loja) {
    const hoje  = new Date().toLocaleDateString('pt-BR', { day:'2-digit', month:'long', year:'numeric' });
    const tipo  = f.tipo_contrato || 'clt';
    const titulo = TIPOS_CONTRATO[tipo] || 'Contrato de Trabalho';
    const nomeLoja   = loja?.nome    || 'DESFFRUT LTDA';
    const endLoja    = loja?.endereco || '______________________________________';
    const foneLoja   = loja?.telefone || '(__)________-________';
    const nomeFuncs  = f.nome || '';
    const cpfFuncs   = f.cpf || '___.___.___-__';
    const cargo      = f.cargo || '';
    const admissao   = fmtData(f.admitido_em);
    const salario    = fmt(f.salario_base);
    const carga      = f.carga_horaria || 8;

    // Cláusulas variam por tipo
    let clausulas = '';

    if (tipo === 'clt') {
        clausulas = `
        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 1ª – DO OBJETO:</span>
        O presente contrato tem por objeto a contratação do EMPREGADO para exercer a função de
        <strong>${cargo}</strong>, na empresa CONTRATANTE, com início em ${admissao}.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 2ª – DA JORNADA DE TRABALHO:</span>
        O EMPREGADO cumprirá jornada de <strong>${carga} (${_ext(carga)}) horas diárias</strong>,
        de segunda a sexta-feira, em horários a serem definidos pela CONTRATANTE, podendo ser
        alterados mediante acordo mútuo, respeitados os limites da CLT (Art. 58).</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 3ª – DA REMUNERAÇÃO:</span>
        O EMPREGADO receberá salário mensal de <strong>${salario}</strong>,
        pago até o 5º dia útil do mês subsequente ao trabalhado, via depósito bancário ou PIX
        em conta indicada pelo EMPREGADO.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 4ª – DO PERÍODO DE EXPERIÊNCIA:</span>
        Fica estabelecido período de experiência de <strong>45 (quarenta e cinco) dias</strong>,
        prorrogável por igual período, nos termos do Art. 445, parágrafo único, da CLT.
        Durante o período de experiência, qualquer das partes poderá rescindir o contrato
        mediante aviso prévio de 24 horas.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 5ª – DAS OBRIGAÇÕES DO EMPREGADO:</span>
        O EMPREGADO obriga-se a: (a) cumprir com pontualidade e assiduidade as funções
        atribuídas; (b) observar as normas internas da CONTRATANTE; (c) manter sigilo sobre
        informações estratégicas, cadastro de clientes, preços e dados financeiros da empresa,
        durante e após o vínculo empregatício; (d) zelar pelo patrimônio da CONTRATANTE.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 6ª – DAS OBRIGAÇÕES DA CONTRATANTE:</span>
        A CONTRATANTE obriga-se a: (a) efetuar o registro do EMPREGADO na CTPS e em livro
        de registro; (b) recolher FGTS e contribuições previdenciárias; (c) fornecer condições
        adequadas de trabalho; (d) pagar as verbas trabalhistas em conformidade com a legislação.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 7ª – DA RESCISÃO:</span>
        O contrato poderá ser rescindido por qualquer das partes, mediante aviso prévio de
        30 (trinta) dias, asseguradas as verbas rescisórias previstas na legislação trabalhista
        vigente. Em caso de justa causa (Art. 482, CLT), fica dispensado o aviso prévio.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 8ª – DAS DISPOSIÇÕES GERAIS:</span>
        Aplicam-se ao presente contrato as disposições da Consolidação das Leis do Trabalho
        (CLT), Convenções Coletivas da categoria e demais legislações pertinentes.
        Este instrumento é firmado em 2 (duas) vias de igual teor e valor.</p></div>
        `;
    } else if (tipo === 'pj') {
        clausulas = `
        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 1ª – DO OBJETO:</span>
        O presente contrato tem por objeto a prestação de serviços de <strong>${cargo}</strong>
        pelo CONTRATADO à CONTRATANTE, sem vínculo empregatício, com início em ${admissao}.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 2ª – DA PRESTAÇÃO DOS SERVIÇOS:</span>
        Os serviços serão prestados em regime de <strong>${carga}h diárias</strong>,
        de acordo com demanda e cronograma definido entre as partes, podendo ser realizados
        nas instalações da CONTRATANTE ou remotamente, conforme a natureza da atividade.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 3ª – DOS HONORÁRIOS:</span>
        Pelos serviços prestados, a CONTRATANTE pagará ao CONTRATADO o valor mensal de
        <strong>${salario}</strong>, mediante emissão de Nota Fiscal de Serviços (NFS-e) pelo
        CONTRATADO, até o 5º dia útil do mês subsequente.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 4ª – DA INDEPENDÊNCIA E AUTONOMIA:</span>
        O CONTRATADO atuará com total autonomia técnica e administrativa, sem subordinação
        hierárquica, exclusividade ou habitualidade obrigatória, ficando a seu cargo o
        recolhimento de tributos, encargos e contribuições previdenciárias decorrentes
        desta relação contratual (ISS, PIS, COFINS, CSLL, IR e INSS).</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 5ª – DO SIGILO E CONFIDENCIALIDADE:</span>
        O CONTRATADO compromete-se a manter absoluto sigilo sobre informações técnicas,
        comerciais, financeiras, cadastro de clientes, preços e estratégias da CONTRATANTE,
        durante e por 24 (vinte e quatro) meses após o término deste contrato,
        sob pena de reparação por perdas e danos.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 6ª – DA VIGÊNCIA E RESCISÃO:</span>
        O presente contrato vigorará por prazo indeterminado, podendo ser rescindido por
        qualquer das partes mediante aviso prévio de 30 (trinta) dias, sem ônus para nenhuma
        das partes, salvo havendo serviços em andamento, que deverão ser concluídos ou
        substituídos por acordo mútuo.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 7ª – DAS DISPOSIÇÕES GERAIS:</span>
        As partes declaram que a presente relação é de natureza estritamente civil, regida pelo
        Código Civil Brasileiro (Lei 10.406/2002). Este instrumento é firmado em 2 (duas) vias
        de igual teor e valor.</p></div>
        `;
    } else if (tipo === 'autonomo') {
        clausulas = `
        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 1ª – DO OBJETO:</span>
        O presente contrato tem por objeto a prestação de serviços autônomos de
        <strong>${cargo}</strong> pelo CONTRATADO à CONTRATANTE, sem vínculo empregatício,
        com início em ${admissao}.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 2ª – DOS SERVIÇOS:</span>
        Os serviços serão prestados de forma autônoma, sem exclusividade, com carga horária
        de aproximadamente <strong>${carga}h diárias</strong>, conforme necessidade da
        CONTRATANTE, com horários e local a serem acordados entre as partes.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 3ª – DA REMUNERAÇÃO:</span>
        Pelos serviços prestados, a CONTRATANTE pagará ao CONTRATADO o valor mensal de
        <strong>${salario}</strong>, até o 5º dia útil do mês subsequente.
        O CONTRATADO é responsável pelo recolhimento do INSS como autônomo (contribuinte
        individual) e demais obrigações fiscais.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 4ª – DA AUTONOMIA:</span>
        O CONTRATADO exercerá suas atividades com plena autonomia técnica, sem subordinação
        hierárquica continuada, podendo prestar serviços a outros contratantes, desde que
        não haja conflito de interesses com a CONTRATANTE.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 5ª – DO SIGILO:</span>
        O CONTRATADO obriga-se a manter sigilo sobre informações comerciais, financeiras e
        cadastro de clientes da CONTRATANTE durante e após o vínculo contratual.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 6ª – DA RESCISÃO:</span>
        Qualquer das partes poderá rescindir o presente contrato mediante aviso prévio de
        15 (quinze) dias. Este instrumento é firmado em 2 (duas) vias de igual teor.</p></div>
        `;
    } else { // estagio
        clausulas = `
        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 1ª – DO OBJETO:</span>
        O presente Termo tem por objeto formalizar o estágio de <strong>${nomeFuncs}</strong>
        na função de <strong>${cargo}</strong>, com início em ${admissao}, regido pela
        Lei nº 11.788/2008 (Lei do Estágio).</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 2ª – DA JORNADA:</span>
        A jornada de estágio será de <strong>${carga}h diárias</strong>, não excedendo
        30h semanais para estudantes do ensino regular (Art. 10, Lei 11.788/2008),
        compatível com o horário escolar do ESTAGIÁRIO.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 3ª – DA BOLSA-AUXÍLIO:</span>
        Será concedida bolsa-auxílio mensal no valor de <strong>${salario}</strong>,
        acrescida de auxílio-transporte conforme legislação, paga até o 5º dia útil
        do mês subsequente.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 4ª – DA SUPERVISÃO:</span>
        O ESTAGIÁRIO será supervisionado por profissional da área de atuação, designado
        pela CONTRATANTE, que orientará as atividades e avaliará o desempenho
        periodicamente, em conformidade com o plano de estágio.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 5ª – DAS OBRIGAÇÕES DO ESTAGIÁRIO:</span>
        O ESTAGIÁRIO obriga-se a: (a) manter matrícula e frequência regular na instituição
        de ensino; (b) cumprir as normas internas da CONTRATANTE; (c) apresentar
        comprovante de matrícula semestralmente; (d) manter sigilo sobre informações da empresa.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 6ª – DO SEGURO:</span>
        A CONTRATANTE providenciará seguro contra acidentes pessoais em favor do ESTAGIÁRIO,
        nos termos do Art. 9º, inciso IV da Lei 11.788/2008.</p></div>

        <div class="clausula"><p><span class="clausula-num">CLÁUSULA 7ª – DA RESCISÃO:</span>
        O estágio poderá ser rescindido a qualquer tempo por mútuo acordo, por desempenho
        insatisfatório, por descumprimento de obrigações, ou ao término do período letivo,
        sem ônus para qualquer das partes além das verbas proporcionais devidas.</p></div>
        `;
    }

    const textoIntroducao = tipo === 'estagio'
        ? `<p>Pelo presente instrumento, de um lado, <strong>CONTRATANTE: ${nomeLoja}</strong>,
           estabelecida na ${endLoja}, e de outro, <strong>ESTAGIÁRIO(A): ${nomeFuncs}</strong>,
           portador(a) do CPF nº <strong>${cpfFuncs}</strong>, têm entre si justo e contratado
           o seguinte Termo de Compromisso de Estágio:</p>`
        : tipo === 'clt'
        ? `<p>Pelo presente instrumento particular, de um lado, <strong>EMPREGADORA: ${nomeLoja}</strong>,
           estabelecida na ${endLoja}, telefone: ${foneLoja}, e de outro,
           <strong>EMPREGADO(A): ${nomeFuncs}</strong>, portador(a) do CPF nº <strong>${cpfFuncs}</strong>,
           têm entre si justo e acordado o presente Contrato Individual de Trabalho:</p>`
        : `<p>Pelo presente instrumento particular, de um lado, <strong>CONTRATANTE: ${nomeLoja}</strong>,
           estabelecida na ${endLoja}, telefone: ${foneLoja}, e de outro,
           <strong>CONTRATADO(A): ${nomeFuncs}</strong>, portador(a) do CPF nº <strong>${cpfFuncs}</strong>,
           têm entre si justo e acordado o seguinte:</p>`;

    const labelContratante = tipo === 'clt'   ? 'EMPREGADORA'   : 'CONTRATANTE';
    const labelContratado  = tipo === 'clt'   ? 'EMPREGADO(A)'  :
                             tipo === 'estagio'? 'ESTAGIÁRIO(A)' : 'CONTRATADO(A)';

    return `
    <div style="text-align:center; margin-bottom: 20px;">
        <div style="font-size:10pt; color:#555; text-transform:uppercase; letter-spacing:2px; margin-bottom:6px;">
            ${nomeLoja}
        </div>
        <h1>${titulo}</h1>
        <h2 style="color:#555;">Ref.: ${nomeFuncs} — ${cargo}</h2>
    </div>

    <div class="secao">
        ${textoIntroducao}
    </div>

    <div class="dados-box" style="margin:18px 0;">
        <p><span class="label">Parte:</span> ${labelContratante} — ${nomeLoja}</p>
        <p><span class="label">Endereço:</span> ${endLoja}</p>
        <p><span class="label">Telefone:</span> ${foneLoja}</p>
        <p style="margin-top:8px;"><span class="label">Parte:</span> ${labelContratado} — ${nomeFuncs}</p>
        <p><span class="label">CPF:</span> ${cpfFuncs}</p>
        <p><span class="label">Função/Cargo:</span> ${cargo}</p>
        <p><span class="label">Data de Início:</span> ${admissao}</p>
        <p><span class="label">Remuneração:</span> ${salario}/mês</p>
        <p><span class="label">Carga Horária:</span> ${carga}h/dia</p>
    </div>

    ${clausulas}

    <div class="clausula">
        <p><span class="clausula-num">CLÁUSULA FINAL – DO FORO:</span>
        As partes elegem o Foro da Comarca de <span class="campo-preencher">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
        para dirimir quaisquer controvérsias oriundas do presente instrumento,
        renunciando a qualquer outro, por mais privilegiado que seja.</p>
    </div>

    <p style="margin-top:28px;">
        Por estarem justos e contratados, firmam o presente instrumento em 2 (duas) vias
        de igual teor e forma, juntamente com 2 (duas) testemunhas abaixo identificadas.
    </p>
    <p style="text-align:right; margin-top:4px;">
        <span class="campo-preencher">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>,
        ${hoje}.
    </p>

    <div class="assinaturas">
        <div class="assinatura-bloco">
            <div class="assinatura-linha">
                ${nomeLoja}<br>
                <span style="font-size:10pt;color:#666;">${labelContratante}</span>
            </div>
        </div>
        <div class="assinatura-bloco">
            <div class="assinatura-linha">
                ${nomeFuncs}<br>
                <span style="font-size:10pt;color:#666;">${labelContratado}</span>
            </div>
        </div>
    </div>

    <div class="assinaturas" style="margin-top:50px;">
        <div class="assinatura-bloco">
            <div class="assinatura-linha">
                ________________________________<br>
                <span style="font-size:10pt;color:#666;">Testemunha 1 — CPF: ____________</span>
            </div>
        </div>
        <div class="assinatura-bloco">
            <div class="assinatura-linha">
                ________________________________<br>
                <span style="font-size:10pt;color:#666;">Testemunha 2 — CPF: ____________</span>
            </div>
        </div>
    </div>

    <div class="rodape">
        Documento gerado pelo sistema Desffrut ERP · ${new Date().toLocaleString('pt-BR')}
    </div>
    `;
}

function _ext(n) {
    const map = {1:'uma',2:'duas',3:'três',4:'quatro',5:'cinco',6:'seis',
                 7:'sete',8:'oito',9:'nove',10:'dez',12:'doze'};
    return map[n] || n;
}

window.rhGerarContrato = function(id) {
    const f = _funcs.find(x => x.id == id);
    if (!f) { toast('Funcionário não encontrado na lista.', false); return; }

    // Tenta pegar dados da loja (pode não ter loja_id diretamente no objeto)
    // O objeto f tem loja_nome mas não loja_id; usamos o primeiro que bater por nome
    let loja = null;
    Object.values(_LOJAS_MAP).forEach(l => {
        if (l.nome === f.loja_nome) loja = l;
    });

    const html = _gerarHTMLContrato(f, loja);

    const win = window.open('', '_blank', 'width=900,height=820,menubar=yes,toolbar=yes,scrollbars=yes');
    if (!win) { toast('Permita pop-ups para gerar o contrato.', false); return; }

    win.document.write(`<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Contrato — ${f.nome}</title>
<style>${_contratoCSS()}</style>
</head>
<body>
<div style="margin-bottom:20px;display:flex;gap:10px;align-items:center;" class="no-print">
    <button onclick="window.print()"
        style="padding:8px 20px;background:#2e7d32;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;">
        🖨️ Imprimir / Salvar PDF
    </button>
    <button onclick="window.close()"
        style="padding:8px 16px;background:#fff;color:#555;border:1px solid #bbb;border-radius:6px;cursor:pointer;font-size:14px;">
        ✕ Fechar
    </button>
    <span style="font-size:12px;color:#888;">Dica: no diálogo de impressão, escolha "Salvar como PDF" para exportar.</span>
</div>
${html}
</body>
</html>`);
    win.document.close();
    win.focus();
};

/* ─ Init ────────────────────────────────────────────────── */
rhCarregarFuncs(true);
})();
</script>
