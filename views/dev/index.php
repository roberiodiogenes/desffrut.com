<?php
/**
 * Desffrut — Painel Dev Mode (Fase 9)
 * Rota: /dev  →  apenas dev_admin
 * Layout totalmente isolado do dashboard operacional.
 */
require_once __DIR__ . '/../../app/middleware/dev_auth.php';
require_once __DIR__ . '/../../app/config/database.php';

$u_dev = $_SESSION['usuario'];

// Lê configurações atuais
$_cfg_dev = [];
try {
    $pdo_dev = db();
    $_rows   = $pdo_dev->query("SELECT chave, valor FROM configuracoes")->fetchAll(PDO::FETCH_KEY_PAIR);
    $_cfg_dev = $_rows;
} catch (Throwable $e) { }

$manutencao_ativa    = ($_cfg_dev['manutencao_ativa']    ?? '0') === '1';
$manutencao_msg      = htmlspecialchars($_cfg_dev['manutencao_msg']    ?? '');
$inadimplencia_ativa = ($_cfg_dev['inadimplencia_ativa'] ?? '0') === '1';
$inadimplencia_msg   = htmlspecialchars($_cfg_dev['inadimplencia_msg'] ?? '');
// Modo Restrito (Categoria 22)
$modo_restrito_ativo = ($_cfg_dev['modo_restrito'] ?? '0') === '1';
$motivo_restricao    = htmlspecialchars($_cfg_dev['motivo_restricao'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title>🔐 Dev Mode — Desffrut</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        :root {
            --dev-bg:    #0a0a0f;
            --dev-card:  #13131a;
            --dev-border:#1e1e2e;
            --dev-accent:#7c3aed;
            --dev-green: #22c55e;
            --dev-red:   #ef4444;
            --dev-yellow:#f59e0b;
            --dev-text:  #e2e8f0;
            --dev-muted: #64748b;
        }
        * { box-sizing:border-box; }
        body {
            background:var(--dev-bg); color:var(--dev-text);
            font-family:'Courier New', monospace; min-height:100vh;
        }
        /* ── Top bar ──────────────────────────────────────────────── */
        .dev-topbar {
            background:var(--dev-card); border-bottom:1px solid var(--dev-border);
            padding:12px 24px; display:flex; align-items:center;
            justify-content:space-between; position:sticky; top:0; z-index:100;
        }
        .dev-logo { color:var(--dev-accent); font-size:1.1rem; font-weight:700; letter-spacing:1px; }
        .dev-logo span { color:var(--dev-muted); font-size:.75rem; margin-left:8px; }
        .dev-user { font-size:.8rem; color:var(--dev-muted); }
        .dev-logout { color:var(--dev-red); font-size:.8rem; text-decoration:none; margin-left:12px; }
        .dev-logout:hover { color:#ff6666; }

        /* ── Layout ───────────────────────────────────────────────── */
        .dev-main { max-width:1100px; margin:0 auto; padding:24px 16px; }

        /* ── Cards ────────────────────────────────────────────────── */
        .dev-card {
            background:var(--dev-card); border:1px solid var(--dev-border);
            border-radius:10px; padding:20px; margin-bottom:20px;
        }
        .dev-card-title {
            font-size:.85rem; font-weight:700; letter-spacing:1.5px;
            text-transform:uppercase; color:var(--dev-muted); margin-bottom:16px;
            padding-bottom:8px; border-bottom:1px solid var(--dev-border);
        }

        /* ── Status pills ─────────────────────────────────────────── */
        .dev-status-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; }
        .dev-stat {
            background:#0d0d14; border:1px solid var(--dev-border);
            border-radius:8px; padding:14px 16px;
        }
        .dev-stat-label { font-size:.68rem; color:var(--dev-muted); text-transform:uppercase; letter-spacing:1px; }
        .dev-stat-value { font-size:1.5rem; font-weight:700; margin-top:4px; }
        .dev-pill {
            display:inline-block; padding:3px 10px; border-radius:20px;
            font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
        }
        .dev-pill-on  { background:rgba(34,197,94,.15); color:var(--dev-green); border:1px solid rgba(34,197,94,.3); }
        .dev-pill-off { background:rgba(100,116,139,.15); color:var(--dev-muted); border:1px solid rgba(100,116,139,.3); }
        .dev-pill-warn{ background:rgba(245,158,11,.15); color:var(--dev-yellow); border:1px solid rgba(245,158,11,.3); }

        /* ── Toggle switch ────────────────────────────────────────── */
        .dev-toggle-row { display:flex; align-items:flex-start; gap:14px; margin-bottom:14px; flex-wrap:wrap; }
        .dev-toggle-info { flex:1; min-width:200px; }
        .dev-toggle-info h6 { margin:0 0 4px; font-size:.88rem; }
        .dev-toggle-info p  { margin:0; font-size:.75rem; color:var(--dev-muted); }
        .dev-switch { position:relative; width:48px; height:26px; flex-shrink:0; margin-top:2px; }
        .dev-switch input { opacity:0; width:0; height:0; }
        .dev-slider {
            position:absolute; inset:0; border-radius:26px;
            background:#222; cursor:pointer; transition:.2s;
        }
        .dev-slider::before {
            content:''; position:absolute; width:20px; height:20px;
            left:3px; bottom:3px; border-radius:50%;
            background:#555; transition:.2s;
        }
        .dev-switch input:checked + .dev-slider { background:var(--dev-accent); }
        .dev-switch input:checked + .dev-slider::before { transform:translateX(22px); background:#fff; }
        .dev-switch-warn input:checked + .dev-slider { background:var(--dev-red); }

        /* ── Form elements ────────────────────────────────────────── */
        .dev-input {
            width:100%; background:#0d0d14; border:1px solid var(--dev-border);
            color:var(--dev-text); border-radius:6px; padding:8px 12px;
            font-size:.83rem; font-family:inherit;
        }
        .dev-input:focus { outline:none; border-color:var(--dev-accent); }
        .dev-btn {
            padding:8px 18px; border:none; border-radius:6px;
            font-size:.82rem; font-weight:700; cursor:pointer; font-family:inherit;
        }
        .dev-btn-primary  { background:var(--dev-accent); color:#fff; }
        .dev-btn-primary:hover  { background:#6d28d9; }
        .dev-btn-danger   { background:var(--dev-red); color:#fff; }
        .dev-btn-danger:hover   { background:#dc2626; }
        .dev-btn-success  { background:var(--dev-green); color:#111; }
        .dev-btn-success:hover  { background:#16a34a; }
        .dev-btn-ghost    { background:transparent; color:var(--dev-muted); border:1px solid var(--dev-border); }

        /* ── Auditoria table ──────────────────────────────────────── */
        .dev-table { width:100%; border-collapse:collapse; font-size:.76rem; }
        .dev-table th {
            background:#0d0d14; padding:8px 10px; text-align:left;
            border-bottom:1px solid var(--dev-border); color:var(--dev-muted);
            text-transform:uppercase; letter-spacing:.8px; font-size:.65rem;
        }
        .dev-table td {
            padding:7px 10px; border-bottom:1px solid #0f0f18;
            color:#b0b8c8; vertical-align:top;
        }
        .dev-table tr:hover td { background:#0d0d14; }
        .dev-acao-badge {
            display:inline-block; padding:2px 7px; border-radius:4px; font-size:.65rem;
            font-weight:700; font-family:monospace; letter-spacing:.3px;
        }

        /* ── Toast ────────────────────────────────────────────────── */
        .dev-toast {
            position:fixed; bottom:24px; right:24px; z-index:9999;
            padding:10px 20px; border-radius:8px; font-size:.82rem; font-weight:700;
            display:none; font-family:monospace;
            box-shadow:0 4px 20px rgba(0,0,0,.5);
        }

        /* ── Search user ──────────────────────────────────────────── */
        .dev-user-result {
            background:#0d0d14; border:1px solid var(--dev-border);
            border-radius:6px; margin-top:6px; max-height:200px; overflow-y:auto; display:none;
        }
        .dev-user-item {
            padding:8px 12px; cursor:pointer; border-bottom:1px solid #0f0f18;
            font-size:.78rem;
        }
        .dev-user-item:hover { background:#13131a; }
        .dev-user-item:last-child { border-bottom:none; }

        /* ── Pagination ───────────────────────────────────────────── */
        .dev-pager { display:flex; gap:8px; align-items:center; margin-top:14px; }
        .dev-pager-btn {
            padding:5px 12px; background:#0d0d14; border:1px solid var(--dev-border);
            color:var(--dev-text); border-radius:4px; cursor:pointer; font-family:monospace; font-size:.75rem;
        }
        .dev-pager-btn:hover { border-color:var(--dev-accent); }
        .dev-pager-info { color:var(--dev-muted); font-size:.75rem; }
    </style>
</head>
<body>

<!-- TOP BAR ───────────────────────────────────────────────────────── -->
<div class="dev-topbar">
    <div class="dev-logo">🔐 DEV MODE <span>// Desffrut System Console</span></div>
    <div>
        <span class="dev-user">👤 <?= htmlspecialchars($u_dev['nome']) ?></span>
        <a href="/logout" class="dev-logout">[ SAIR ]</a>
    </div>
</div>

<div class="dev-main">

    <!-- STATUS ────────────────────────────────────────────────────── -->
    <div class="dev-card">
        <div class="dev-card-title">📊 Status do Sistema</div>
        <div class="dev-status-grid" id="dev-status-grid">
            <div class="dev-stat">
                <div class="dev-stat-label">Manutenção</div>
                <div class="dev-stat-value" id="st-manut">
                    <span class="dev-pill <?= $manutencao_ativa ? 'dev-pill-warn' : 'dev-pill-off' ?>">
                        <?= $manutencao_ativa ? 'ATIVA' : 'INATIVA' ?>
                    </span>
                </div>
            </div>
            <div class="dev-stat">
                <div class="dev-stat-label">Inadimplência</div>
                <div class="dev-stat-value" id="st-inadim">
                    <span class="dev-pill <?= $inadimplencia_ativa ? 'dev-pill-warn' : 'dev-pill-off' ?>">
                        <?= $inadimplencia_ativa ? 'ATIVA' : 'INATIVA' ?>
                    </span>
                </div>
            </div>
            <div class="dev-stat">
                <div class="dev-stat-label">Modo Restrito</div>
                <div class="dev-stat-value" id="st-restricao">
                    <span class="dev-pill <?= $modo_restrito_ativo ? 'dev-pill-warn' : 'dev-pill-off' ?>" style="<?= $modo_restrito_ativo ? 'background:#b71c1c;color:#fff;' : '' ?>">
                        <?= $modo_restrito_ativo ? 'ATIVO' : 'INATIVO' ?>
                    </span>
                </div>
            </div>
            <div class="dev-stat">
                <div class="dev-stat-label">Usuários</div>
                <div class="dev-stat-value" id="st-users" style="color:var(--dev-accent);">—</div>
            </div>
            <div class="dev-stat">
                <div class="dev-stat-label">Pedidos Hoje</div>
                <div class="dev-stat-value" id="st-pedidos" style="color:var(--dev-green);">—</div>
            </div>
            <div class="dev-stat">
                <div class="dev-stat-label">Logs Hoje</div>
                <div class="dev-stat-value" id="st-logs" style="color:var(--dev-yellow);">—</div>
            </div>
            <div class="dev-stat">
                <div class="dev-stat-label">PHP</div>
                <div class="dev-stat-value" id="st-php" style="font-size:.95rem;color:var(--dev-muted);">—</div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- MODO MANUTENÇÃO ───────────────────────────────────────── -->
        <div class="col-md-6">
            <div class="dev-card" style="height:100%;">
                <div class="dev-card-title">🔧 Modo Manutenção</div>
                <div class="dev-toggle-row">
                    <div class="dev-toggle-info">
                        <h6>Portal Público Bloqueado</h6>
                        <p>Quando ativo, exibe tela de manutenção para visitantes e clientes. Operadores e gerentes continuam com acesso.</p>
                    </div>
                    <label class="dev-switch dev-switch-warn">
                        <input type="checkbox" id="toggle-manut" <?= $manutencao_ativa ? 'checked' : '' ?>
                               onchange="devToggleManut(this.checked)">
                        <span class="dev-slider"></span>
                    </label>
                </div>
                <div>
                    <label style="font-size:.75rem;color:var(--dev-muted);margin-bottom:4px;display:block;">Mensagem pública:</label>
                    <textarea class="dev-input" id="manut-msg" rows="2" placeholder="Mensagem exibida durante a manutenção…"><?= $manutencao_msg ?></textarea>
                    <button class="dev-btn dev-btn-ghost mt-2" onclick="devSalvarManutMsg()" style="font-size:.75rem;">💾 Salvar mensagem</button>
                </div>
            </div>
        </div>

        <!-- INADIMPLÊNCIA ─────────────────────────────────────────── -->
        <div class="col-md-6">
            <div class="dev-card" style="height:100%;">
                <div class="dev-card-title">⚠️ Aviso de Inadimplência</div>
                <div class="dev-toggle-row">
                    <div class="dev-toggle-info">
                        <h6>Banner nos Painéis Admin</h6>
                        <p>Quando ativo, exibe banner fixo e visível no topo de todos os dashboards administrativos. A operação NÃO é interrompida.</p>
                    </div>
                    <label class="dev-switch dev-switch-warn">
                        <input type="checkbox" id="toggle-inadim" <?= $inadimplencia_ativa ? 'checked' : '' ?>
                               onchange="devToggleInadim(this.checked)">
                        <span class="dev-slider"></span>
                    </label>
                </div>
                <div>
                    <label style="font-size:.75rem;color:var(--dev-muted);margin-bottom:4px;display:block;">Mensagem do banner:</label>
                    <textarea class="dev-input" id="inadim-msg" rows="2" placeholder="Mensagem exibida no banner…"><?= $inadimplencia_msg ?></textarea>
                    <button class="dev-btn dev-btn-ghost mt-2" onclick="devSalvarInadimMsg()" style="font-size:.75rem;">💾 Salvar mensagem</button>
                </div>
            </div>
        </div>

    </div>

    <!-- MODO RESTRITO (Categoria 22) ────────────────────────────────── -->
    <div class="dev-card" style="border-color:#c62828;">
        <div class="dev-card-title" style="color:#ef5350;">🔒 Modo Restrito</div>
        <div class="dev-toggle-row">
            <div class="dev-toggle-info">
                <h6>Bloqueio Parcial de Funcionalidades</h6>
                <p>Quando ativo, bloqueia BI, Auditoria, Ponto, Estoque, Contas a Pagar, Funcionários e Campanhas com HTTP <strong>402</strong>.
                   PDV, Catálogo e Pedidos continuam funcionando. super_admin não é bloqueado.</p>
            </div>
            <label class="dev-switch dev-switch-warn">
                <input type="checkbox" id="toggle-restricao" <?= $modo_restrito_ativo ? 'checked' : '' ?>
                       onchange="devToggleRestricao(this.checked)">
                <span class="dev-slider"></span>
            </label>
        </div>
        <div>
            <label style="font-size:.75rem;color:var(--dev-muted);margin-bottom:4px;display:block;">
                Motivo exibido ao usuário bloqueado:
            </label>
            <textarea class="dev-input" id="restricao-motivo" rows="2"
                      placeholder="Ex.: Plano suspenso por inadimplência. Entre em contato com o suporte."><?= $motivo_restricao ?></textarea>
            <button class="dev-btn dev-btn-ghost mt-2" onclick="devSalvarMotivoRestricao()" style="font-size:.75rem;">💾 Salvar motivo</button>
        </div>
    </div>

    <!-- RESET FORÇADO DE SENHA ─────────────────────────────────────── -->
    <div class="dev-card">
        <div class="dev-card-title">🔑 Reset Forçado de Senha</div>
        <p style="font-size:.78rem;color:var(--dev-muted);margin-bottom:14px;">
            Gera uma senha temporária aleatória, salva o hash bcrypt no banco e envia por e-mail ao titular.
            Não armazena nem exibe a senha em texto — compatível com LGPD.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:200px;">
                <label style="font-size:.75rem;color:var(--dev-muted);margin-bottom:4px;display:block;">Buscar usuário (nome ou e-mail):</label>
                <input class="dev-input" id="reset-busca" type="text" placeholder="Digite para buscar…"
                       oninput="devBuscarUsuario(this.value)">
                <div class="dev-user-result" id="reset-resultados"></div>
                <div id="reset-selecionado" style="margin-top:6px;font-size:.75rem;color:var(--dev-green);display:none;"></div>
                <input type="hidden" id="reset-uid" value="">
            </div>
            <button class="dev-btn dev-btn-danger" onclick="devResetSenha()" style="margin-bottom:0;">
                🔑 Resetar Senha
            </button>
        </div>
        <div id="reset-resultado" style="margin-top:12px;font-size:.78rem;display:none;"></div>
    </div>

    <!-- AUDITORIA FORENSE ──────────────────────────────────────────── -->
    <div class="dev-card">
        <div class="dev-card-title">🔍 Auditoria Forense</div>
        <p style="font-size:.75rem;color:var(--dev-muted);margin-bottom:12px;">
            Log imutável de todas as ações críticas do sistema. Não é possível excluir registros por design.
        </p>

        <!-- Filtros -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <input class="dev-input" id="aud-ip"   type="text"   placeholder="Filtrar por IP…" style="max-width:160px;">
            <input class="dev-input" id="aud-acao" type="text"   placeholder="Ação (ex.: login)…" style="max-width:180px;">
            <input class="dev-input" id="aud-de"   type="date"   style="max-width:140px;">
            <input class="dev-input" id="aud-ate"  type="date"   style="max-width:140px;">
            <button class="dev-btn dev-btn-primary" onclick="devCarregarAuditoria(1)">🔍 Filtrar</button>
            <button class="dev-btn dev-btn-ghost" onclick="devLimparFiltros()">✕ Limpar</button>
        </div>

        <div id="aud-wrap" style="overflow-x:auto;">
            <table class="dev-table" id="aud-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Data/Hora</th>
                        <th>IP</th>
                        <th>Usuário</th>
                        <th>Role</th>
                        <th>Ação</th>
                        <th>Tabela</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody id="aud-tbody">
                    <tr><td colspan="8" style="text-align:center;color:var(--dev-muted);padding:20px;">
                        Use os filtros acima para carregar os registros.
                    </td></tr>
                </tbody>
            </table>
        </div>
        <div class="dev-pager" id="aud-pager" style="display:none;">
            <button class="dev-pager-btn" id="aud-prev" onclick="devPaginaAnterior()">◄ Anterior</button>
            <span class="dev-pager-info" id="aud-info"></span>
            <button class="dev-pager-btn" id="aud-next" onclick="devPaginaProxima()">Próxima ►</button>
        </div>
    </div>

</div><!-- /dev-main -->

<div class="dev-toast" id="dev-toast"></div>

<script>
(function(){
const API = '<?= BASE_PATH ?>/api/v1';
let _aud_pg = 1;
let _aud_total_pg = 1;

// ── Util ──────────────────────────────────────────────────────────────────────
function authH(json) {
    const t = (sessionStorage.getItem('desffrut_token') || '').trim();
    const h = {};
    if (json) h['Content-Type'] = 'application/json';
    if (t)    h['Authorization'] = 'Bearer ' + t;
    return h;
}

function toast(msg, tipo='ok'){
    const el = document.getElementById('dev-toast');
    el.textContent = msg;
    el.style.background = tipo === 'ok'   ? '#22c55e'
                        : tipo === 'warn' ? '#f59e0b'
                        : '#ef4444';
    el.style.color = tipo === 'ok' ? '#111' : '#fff';
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 3500);
}

// ── Status ────────────────────────────────────────────────────────────────────
async function carregarStatus() {
    try {
        const r = await fetch(API+'/dev/status', { headers: authH(false) });
        const j = await r.json();
        if (j.status !== 'ok') return;
        const d = j.data;
        const c = d.counts || {};
        document.getElementById('st-users').textContent   = c.usuarios    ?? '—';
        document.getElementById('st-pedidos').textContent = c.pedidos_hoje ?? '—';
        document.getElementById('st-logs').textContent    = c.logs_hoje    ?? '—';
        document.getElementById('st-php').textContent     = 'PHP ' + (d.servidor_php || '—');
    } catch(e) {}
}
carregarStatus();

// ── Modo Manutenção ───────────────────────────────────────────────────────────
window.devToggleManut = async function(ativo) {
    try {
        const r = await fetch(API+'/dev/manutencao', {
            method:'POST', headers: authH(true),
            body: JSON.stringify({ ativo })
        });
        const j = await r.json();
        toast(j.message || (ativo ? 'Manutenção ATIVADA' : 'Manutenção DESATIVADA'), ativo ? 'warn' : 'ok');
    } catch(e) { toast('Erro de rede', 'err'); }
};

window.devSalvarManutMsg = async function() {
    const msg = document.getElementById('manut-msg').value.trim();
    try {
        const r = await fetch(API+'/dev/manutencao', {
            method:'POST', headers: authH(true),
            body: JSON.stringify({ msg })
        });
        const j = await r.json();
        toast(j.status === 'ok' ? '✅ Mensagem salva.' : '❌ Erro.', j.status === 'ok' ? 'ok' : 'err');
    } catch(e) { toast('Erro de rede', 'err'); }
};

// ── Inadimplência ─────────────────────────────────────────────────────────────
window.devToggleInadim = async function(ativo) {
    try {
        const r = await fetch(API+'/dev/inadimplencia', {
            method:'POST', headers: authH(true),
            body: JSON.stringify({ ativo })
        });
        const j = await r.json();
        toast(j.message || (ativo ? 'Aviso ATIVADO' : 'Aviso DESATIVADO'), ativo ? 'warn' : 'ok');
    } catch(e) { toast('Erro de rede', 'err'); }
};

window.devSalvarInadimMsg = async function() {
    const msg = document.getElementById('inadim-msg').value.trim();
    try {
        const r = await fetch(API+'/dev/inadimplencia', {
            method:'POST', headers: authH(true),
            body: JSON.stringify({ msg })
        });
        const j = await r.json();
        toast(j.status === 'ok' ? '✅ Mensagem salva.' : '❌ Erro.', j.status === 'ok' ? 'ok' : 'err');
    } catch(e) { toast('Erro de rede', 'err'); }
};

// ── Modo Restrito (Categoria 22) ──────────────────────────────────────────────
window.devToggleRestricao = async function(ativo) {
    try {
        const r = await fetch(API + '/dev/modo-restrito', {
            method: 'POST', headers: authH(true),
            body: JSON.stringify({ ativo })
        });
        const j = await r.json();
        const pill = document.getElementById('st-restricao')?.querySelector('.dev-pill');
        if (pill) {
            pill.textContent  = ativo ? 'ATIVO' : 'INATIVO';
            pill.className    = 'dev-pill ' + (ativo ? 'dev-pill-warn' : 'dev-pill-off');
            pill.style.cssText = ativo ? 'background:#b71c1c;color:#fff;' : '';
        }
        toast(j.message || (ativo ? '🔒 Modo Restrito ATIVADO' : '🔓 Modo Restrito DESATIVADO'), ativo ? 'warn' : 'ok');
    } catch(e) { toast('Erro de rede', 'err'); }
};

window.devSalvarMotivoRestricao = async function() {
    const msg = document.getElementById('restricao-motivo').value.trim();
    try {
        const r = await fetch(API + '/dev/modo-restrito', {
            method: 'POST', headers: authH(true),
            body: JSON.stringify({ motivo: msg })
        });
        const j = await r.json();
        toast(j.status === 'ok' ? '✅ Motivo salvo.' : '❌ Erro.', j.status === 'ok' ? 'ok' : 'err');
    } catch(e) { toast('Erro de rede', 'err'); }
};

// ── Reset de Senha ────────────────────────────────────────────────────────────
let _busca_timer = null;
let _uid_selecionado = null;

window.devBuscarUsuario = function(q) {
    clearTimeout(_busca_timer);
    if (q.length < 2) {
        document.getElementById('reset-resultados').style.display='none'; return;
    }
    _busca_timer = setTimeout(async () => {
        try {
            const r = await fetch(API+'/dev/usuarios?q='+encodeURIComponent(q), { headers: authH(false) });
            const j = await r.json();
            const el = document.getElementById('reset-resultados');
            if (!j.data || !j.data.length) {
                el.innerHTML = '<div class="dev-user-item" style="color:var(--dev-muted);">Nenhum resultado.</div>';
            } else {
                el.innerHTML = j.data.map(u =>
                    `<div class="dev-user-item" onclick="devSelecionarUser(${u.id},'${u.nome.replace(/'/g,"\\'")}','${u.email}','${u.role}')">
                        <strong>${u.nome}</strong> <span style="color:var(--dev-muted)">${u.email}</span>
                        <span style="color:var(--dev-accent);font-size:.65rem;margin-left:6px;">[${u.role}]</span>
                    </div>`
                ).join('');
            }
            el.style.display = 'block';
        } catch(e) {}
    }, 300);
};

window.devSelecionarUser = function(id, nome, email, role) {
    _uid_selecionado = id;
    document.getElementById('reset-uid').value = id;
    document.getElementById('reset-busca').value = nome + ' <' + email + '>';
    document.getElementById('reset-resultados').style.display = 'none';
    const sel = document.getElementById('reset-selecionado');
    sel.textContent = '✅ Selecionado: ' + nome + ' [' + role + '] — ' + email;
    sel.style.display = 'block';
};

window.devResetSenha = async function() {
    if (!_uid_selecionado) { toast('Selecione um usuário.', 'warn'); return; }
    if (!confirm('Resetar a senha de ' + document.getElementById('reset-busca').value + '?')) return;
    try {
        const r = await fetch(API+'/dev/reset-senha', {
            method:'POST', headers: authH(true),
            body: JSON.stringify({ usuario_id: _uid_selecionado })
        });
        const j = await r.json();
        const el = document.getElementById('reset-resultado');
        if (j.status === 'ok') {
            const d = j.data;
            let html = `<div style="border:1px solid ${d.email_enviado ? 'var(--dev-green)' : 'var(--dev-yellow)'};border-radius:6px;padding:10px;">`;
            html += `<div style="color:${d.email_enviado ? 'var(--dev-green)' : 'var(--dev-yellow)'};">
                        ${d.email_enviado ? '✅' : '⚠️'} ${d.aviso}</div>`;
            if (!d.email_enviado && d.senha_temp) {
                html += `<div style="margin-top:8px;font-size:.8rem;">
                    Senha temporária: <code style="background:#1a1a24;padding:3px 8px;border-radius:4px;color:#f59e0b;">${d.senha_temp}</code>
                    <span style="color:var(--dev-red);font-size:.68rem;margin-left:6px;">⚠ Repasse por canal seguro e apague este registro.</span>
                </div>`;
            }
            html += '</div>';
            el.innerHTML = html;
            toast(d.email_enviado ? '✅ Senha resetada e e-mail enviado.' : '⚠️ Senha resetada. E-mail não enviado.', d.email_enviado ? 'ok' : 'warn');
        } else {
            el.textContent = '❌ Erro: ' + j.message;
            toast('Erro: ' + j.message, 'err');
        }
        el.style.display = 'block';
    } catch(e) { toast('Erro de rede.', 'err'); }
};

// ── Auditoria Forense ─────────────────────────────────────────────────────────
const ACAO_CORES = {
    'login':                   ['#1565c0','#e3f2fd'],
    'manutencao_ativada':      ['#bf360c','#ffebee'],
    'manutencao_desativada':   ['#2e7d32','#e8f5e9'],
    'inadimplencia_ativada':   ['#e65100','#fff3e0'],
    'inadimplencia_desativada':['#2e7d32','#e8f5e9'],
    'reset_senha_forcado':     ['#6a1b9a','#f3e5f5'],
    'campanha_criada':         ['#1565c0','#e3f2fd'],
    'configuracao_alterada':   ['#00695c','#e0f2f1'],
};

function acaoBadge(acao) {
    const [fg, bg] = ACAO_CORES[acao] || ['#555','#f0f0f0'];
    return `<span class="dev-acao-badge" style="background:${bg}22;color:${fg};border:1px solid ${fg}44;">${acao}</span>`;
}

window.devCarregarAuditoria = async function(pg = 1) {
    _aud_pg = pg;
    const ip   = document.getElementById('aud-ip').value.trim();
    const acao = document.getElementById('aud-acao').value.trim();
    const de   = document.getElementById('aud-de').value;
    const ate  = document.getElementById('aud-ate').value;
    const url  = `${API}/dev/auditoria?pg=${pg}`
               + (ip   ? `&ip=${encodeURIComponent(ip)}`    : '')
               + (acao ? `&acao=${encodeURIComponent(acao)}` : '')
               + (de   ? `&de=${de}`   : '')
               + (ate  ? `&ate=${ate}` : '');
    try {
        const r = await fetch(url, { headers: authH(false) });
        const j = await r.json();
        if (j.status !== 'ok') { toast('Erro ao carregar logs.', 'err'); return; }
        const d   = j.data;
        _aud_total_pg = d.paginas;
        const tbody = document.getElementById('aud-tbody');
        if (!d.logs.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--dev-muted);padding:20px;">Nenhum registro encontrado.</td></tr>';
            document.getElementById('aud-pager').style.display = 'none';
            return;
        }
        tbody.innerHTML = d.logs.map(l => `
            <tr>
                <td style="color:var(--dev-muted);">${l.id}</td>
                <td style="white-space:nowrap;color:var(--dev-muted);">${l.criado_em ? l.criado_em.replace('T',' ') : '—'}</td>
                <td><code style="color:var(--dev-yellow);font-size:.7rem;">${l.ip || '—'}</code></td>
                <td style="white-space:nowrap;">
                    <div>${l.usuario_nome || '<i style="color:var(--dev-muted)">sistema</i>'}</div>
                    <div style="font-size:.65rem;color:var(--dev-muted);">${l.usuario_email || ''}</div>
                </td>
                <td><span style="color:var(--dev-muted);font-size:.68rem;">${l.usuario_role || '—'}</span></td>
                <td>${acaoBadge(l.acao)}</td>
                <td style="color:var(--dev-muted);font-size:.7rem;">${l.tabela_afetada || '—'}</td>
                <td style="font-size:.68rem;max-width:200px;word-break:break-all;color:#8899bb;">
                    ${l.detalhes ? '<pre style="margin:0;white-space:pre-wrap;font-family:monospace;font-size:.65rem;">' + JSON.stringify(l.detalhes,null,1) + '</pre>' : '—'}
                </td>
            </tr>`).join('');
        document.getElementById('aud-info').textContent =
            `Página ${d.pagina} de ${d.paginas} — ${d.total} registros`;
        document.getElementById('aud-pager').style.display = 'flex';
        document.getElementById('aud-prev').disabled = pg <= 1;
        document.getElementById('aud-next').disabled = pg >= _aud_total_pg;
    } catch(e) { toast('Erro de rede ao carregar auditoria.', 'err'); }
};

window.devPaginaAnterior = function() { if (_aud_pg > 1) devCarregarAuditoria(_aud_pg - 1); };
window.devPaginaProxima  = function() { if (_aud_pg < _aud_total_pg) devCarregarAuditoria(_aud_pg + 1); };

window.devLimparFiltros = function() {
    ['aud-ip','aud-acao','aud-de','aud-ate'].forEach(id => document.getElementById(id).value = '');
    devCarregarAuditoria(1);
};

// Carrega auditoria imediatamente
devCarregarAuditoria(1);

})();
</script>
</body>
</html>
