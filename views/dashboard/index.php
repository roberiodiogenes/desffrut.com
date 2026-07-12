<?php
/**
 * Desffrut — Dashboard Unificado
 * Sidebar com módulos ativados conforme role do usuário logado.
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();

$roles_permitidos = ['super_admin', 'gerente', 'caixa', 'entregador', 'rh_financeiro', 'dev_admin'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$u    = usuario_logado();
$role = $u['role'];

// ─── Definição completa de módulos ───────────────────────────────────────────
$todos_modulos = [
    [
        'id'    => 'pdv_acesso',
        'label' => 'PDV / Caixa',
        'icon'  => '🖥️',
        // Operadores de caixa (loja 1, 2 e 3), gerente e dono podem abrir/operar o caixa.
        'roles' => ['caixa', 'gerente', 'super_admin'],
        'abas'  => [
            ['id' => 'pdv_abrir', 'label' => 'Abrir / Operar PDV', 'frag' => 'placeholder', 'fase' => null,
             'meta' => ['titulo' => 'PDV', 'descricao' => 'link:/pdv/abertura']],
        ],
    ],
    [
        'id'    => 'produtos_estoque',
        'label' => 'Produtos & Estoque',
        'icon'  => '📦',
        'roles' => ['super_admin', 'gerente'],
        'badge' => 'critico',   // badge especial alimentado por JS
        'abas'  => [
            ['id' => 'produtos', 'label' => 'Produtos', 'frag' => 'produtos', 'fase' => null],
            ['id' => 'estoque',  'label' => 'Estoque',  'frag' => 'estoque',  'fase' => null],
            ['id' => 'quebras',  'label' => 'Quebras',  'frag' => 'quebras',  'fase' => null],
        ],
    ],
    [
        'id'    => 'pedidos',
        'label' => 'Pedidos & Despacho',
        'icon'  => '🛒',
        'roles' => ['super_admin', 'gerente', 'caixa', 'entregador'],
        // Fase 6: módulo unificado — o fragmento gerencia as 4 abas internamente
        'abas'  => [
            ['id' => 'pedidos_geral', 'label' => 'Pedidos & Despacho', 'frag' => 'pedidos', 'fase' => null],
        ],
    ],
    [
        'id'    => 'ceasa',
        'label' => 'Compras CEASA',
        'icon'  => '🚛',
        'roles' => ['super_admin', 'gerente'],
        'abas'  => [
            ['id' => 'lista',   'label' => 'Lista de Compra',    'frag' => 'ceasa',  'fase' => null],
            ['id' => 'receb',   'label' => 'Recebimento',        'frag' => 'ceasa',  'fase' => null],
            ['id' => 'distrib', 'label' => 'Distribuição Lojas', 'frag' => 'ceasa',  'fase' => null],
            ['id' => 'rota',    'label' => 'Rota Interna',       'frag' => 'ceasa',  'fase' => null],
        ],
    ],
    [
        'id'    => 'caixa_rel',
        'label' => 'Caixa',
        'icon'  => '🧾',
        'roles' => ['super_admin', 'gerente', 'rh_financeiro'],
        'abas'  => [
            ['id' => 'fech',   'label' => 'Fechamentos',        'frag' => 'caixa',       'fase' => null],
            ['id' => 'sang',   'label' => 'Sangrias',           'frag' => 'caixa',       'fase' => null],
            ['id' => 'resumo', 'label' => 'Resumo por Período', 'frag' => 'caixa',       'fase' => null],
            ['id' => 'hist',   'label' => 'Abertura & Fechamento', 'frag' => 'caixa',    'fase' => null],
        ],
    ],
    [
        'id'    => 'relatorios',
        'label' => 'Relatórios',
        'icon'  => '📊',
        'roles' => ['super_admin', 'gerente', 'rh_financeiro'],
        'abas'  => [
            ['id' => 'est_crit',   'label' => 'Estoque Crítico',       'frag' => 'relatorios',      'fase' => null],
            ['id' => 'vendas_rel', 'label' => 'Vendas por Período',    'frag' => 'vendas_relatorio', 'fase' => null],
            ['id' => 'bi',         'label' => 'Indicadores & Gráficos','frag' => 'bi',               'fase' => null],
            ['id' => 'dre',        'label' => 'DRE Simplificado',      'frag' => 'bi',               'fase' => null],
            ['id' => 'quebras_r',  'label' => 'Quebras & Perdas',      'frag' => 'quebras',          'fase' => null],
        ],
    ],
    [
        'id'    => 'financeiro',
        'label' => 'Financeiro',
        'icon'  => '💵',
        'roles' => ['super_admin', 'rh_financeiro'],
        'abas'  => [
            ['id' => 'fin_visao',    'label' => '📊 Visão Geral',       'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_alertas',  'label' => '🔔 Alertas',           'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_pagar',    'label' => '💳 Contas a Pagar',    'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_receber',  'label' => '💰 A Receber',         'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_retiradas','label' => '👤 Retiradas',         'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_aux',      'label' => '🚛 Aux. CEASA',        'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_desp',     'label' => '🧹 Despesas Extras',   'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_transf',   'label' => '↔️ Transferências',    'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_metas',    'label' => '🎯 Metas',             'frag' => 'financeiro', 'fase' => null],
            ['id' => 'fin_fluxo',    'label' => '📈 Fluxo de Caixa',   'frag' => 'financeiro', 'fase' => null],
        ],
    ],
    [
        'id'    => 'rh',
        'label' => 'RH',
        'icon'  => '👥',
        'roles' => ['super_admin', 'rh_financeiro'],
        'abas'  => [
            ['id' => 'funcs',  'label' => 'Funcionários',     'frag' => 'rh', 'fase' => null],
            ['id' => 'ponto',  'label' => 'Ponto/Jornada',    'frag' => 'rh', 'fase' => null],
            ['id' => 'folha',  'label' => 'Folha',            'frag' => 'rh', 'fase' => null],
            ['id' => 'baixas', 'label' => 'Baixas',           'frag' => 'rh', 'fase' => null],
        ],
    ],
    [
        'id'    => 'hardware',
        'label' => 'Hardware',
        'icon'  => '🖨️',
        'roles' => ['super_admin', 'gerente'],  // Fase 5
        'abas'  => [
            ['id' => 'hw_config', 'label' => 'Impressora & Balança', 'frag' => 'hardware', 'fase' => null],
        ],
    ],
    [
        'id'    => 'cms',
        'label' => 'CMS & Portal',
        'icon'  => '🎨',
        'roles' => ['super_admin', 'gerente'],                                                 // Fase 8
        'abas'  => [
            ['id' => 'cms_id',   'label' => 'Identidade Visual', 'frag' => 'cms_identidade', 'fase' => null],
            ['id' => 'cms_ban',  'label' => 'Banners',           'frag' => 'cms_banners',    'fase' => null],
            ['id' => 'cms_lj',   'label' => 'Lojas',             'frag' => 'cms_lojas',      'fase' => null],
            ['id' => 'cms_camp', 'label' => 'Campanhas',         'frag' => 'cms_campanhas',  'fase' => null],
        ],
    ],
    [
        'id'    => 'crm',
        'label' => 'Prospecção & CRM',
        'icon'  => '🤝',
        'roles' => ['super_admin', 'gerente'],                                                 // Fase 10
        'abas'  => [
            ['id' => 'crm_pipe', 'label' => 'Funil de Parcerias', 'frag' => 'crm', 'fase' => null],
            ['id' => 'crm_csv',  'label' => 'Importar CSV',       'frag' => 'crm', 'fase' => null],
        ],
    ],
    [
        'id'    => 'marketing',
        'label' => 'Marketing',
        'icon'  => '📡',
        'roles' => ['super_admin', 'gerente'],                                                 // Fase 12
        'abas'  => [
            ['id' => 'mkt_adtech', 'label' => 'Analytics & Pixels', 'frag' => 'adtech', 'fase' => null],
        ],
    ],
    [
        'id'    => 'admin',
        'label' => 'Administração',
        'icon'  => '⚙️',
        'roles' => ['super_admin'],
        'abas'  => [
            ['id' => 'usuarios', 'label' => 'Usuários & Permissões', 'frag' => 'placeholder', 'fase' => null,
             'meta' => ['titulo' => 'Usuários & Permissões', 'descricao' => 'link:/admin/usuarios']],
            ['id' => 'caixas',   'label' => 'Caixas Abertos', 'frag' => 'caixas_abertos', 'fase' => null],
            ['id' => 'audit',    'label' => 'Auditoria',      'frag' => 'auditoria',      'fase' => null],
        ],
    ],
    [
        'id'    => 'manual',
        'label' => 'Manual de Uso',
        'icon'  => '📖',
        // Disponível para todo mundo — cada role vê no manual apenas a seção do
        // próprio cargo (+ FAQ); super_admin/dev_admin veem o manual inteiro.
        'roles' => ['super_admin', 'gerente', 'caixa', 'entregador', 'rh_financeiro', 'dev_admin'],
        'abas'  => [
            ['id' => 'manual_geral', 'label' => 'Consultar Manual', 'frag' => 'placeholder', 'fase' => null,
             'meta' => ['titulo' => 'Manual de Uso', 'descricao' => 'link:/manual']],
        ],
    ],
];

// ─── Filtra módulos visíveis para o role atual ────────────────────────────────
$modulos = array_values(array_filter($todos_modulos, fn($m) => in_array($role, $m['roles'])));

// Filtra abas por role (abas com 'roles' definido: apenas roles listados)
foreach ($modulos as &$mod) {
    $mod['abas'] = array_values(array_filter($mod['abas'], function($aba) use ($role) {
        return !isset($aba['roles']) || in_array($role, $aba['roles']);
    }));
}
unset($mod);

// ─── Módulo/aba padrão por role ───────────────────────────────────────────────
$defaults = [
    'super_admin'   => ['produtos_estoque', 'produtos'],
    'gerente'       => ['produtos_estoque', 'produtos'],
    'caixa'         => ['pedidos',          'pend'],
    'entregador'    => ['pedidos',          'ativo'],
    'rh_financeiro' => ['financeiro',        'fin_visao'],
    'dev_admin'     => ['produtos_estoque', 'produtos'],
];
[$mod_default, $aba_default] = $defaults[$role] ?? ['produtos_estoque', 'produtos'];

// Rótulo amigável do role
$labels_role = [
    'super_admin'   => ['label' => 'Dono',       'cor' => '#ff6f00'],
    'gerente'       => ['label' => 'Gerente',     'cor' => '#2e7d32'],
    'caixa'         => ['label' => 'Atendente',   'cor' => '#1565c0'],
    'entregador'    => ['label' => 'Entregador',  'cor' => '#6a1b9a'],
    'rh_financeiro' => ['label' => 'Financeiro',  'cor' => '#00695c'],
    'dev_admin'     => ['label' => 'Dev Admin',   'cor' => '#7c3aed'],
];
$info_role = $labels_role[$role] ?? ['label' => $role, 'cor' => '#555'];

// ── Aviso de Inadimplência (Fase 9) ──────────────────────────────────────────
$_inadim_ativa = false;
$_inadim_msg   = '';
try {
    if (!function_exists('db')) {
        require_once __DIR__ . '/../../app/config/database.php';
    }
    $_pdo_i = db();
    $_rows_i = $_pdo_i->query(
        "SELECT chave, valor FROM configuracoes WHERE chave IN ('inadimplencia_ativa','inadimplencia_msg')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $_inadim_ativa = ($_rows_i['inadimplencia_ativa'] ?? '0') === '1';
    $_inadim_msg   = $_rows_i['inadimplencia_msg']    ?? '';
} catch (Throwable $_ie) { }

$titulo_pagina  = 'Painel';
$mostrar_sacola = false;
$mostrar_busca  = false;
require_once __DIR__ . '/../../app/views/layout/header.php';
?>
<?php if ($_inadim_ativa && $_inadim_msg): ?>
<div style="background:#b71c1c;color:#fff;padding:10px 20px;text-align:center;
            font-size:.85rem;font-weight:600;letter-spacing:.2px;position:sticky;top:56px;z-index:200;
            border-bottom:2px solid #7f0000;">
    ⚠️ <?= htmlspecialchars($_inadim_msg) ?>
    <?php if ($role === 'dev_admin'): ?>
    — <a href="/dev" style="color:#ffcdd2;font-size:.75rem;">Gerenciar no Dev Mode</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════
     LAYOUT PRINCIPAL
════════════════════════════════════════════════════════════════ -->
<div class="dashboard-outer" id="dashboard-outer">

    <!-- ── Botão toggle mobile ───────────────────────────────────── -->
    <button class="btn-sidebar-mobile" id="btn-sidebar-toggle" title="Menu">
        <span id="sidebar-icon">☰</span>
    </button>

    <!-- ── Overlay mobile ───────────────────────────────────────── -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- ── SIDEBAR ──────────────────────────────────────────────── -->
    <aside class="dashboard-sidebar" id="sidebar">

        <!-- Cabeçalho da sidebar -->
        <div class="sidebar-header">
            <span class="sidebar-brand">🌿 Desffrut</span>
            <button class="btn-sidebar-close" id="btn-sidebar-close">✕</button>
        </div>

        <!-- Info do usuário -->
        <div class="sidebar-user">
            <div class="user-avatar"><?= mb_strtoupper(mb_substr($u['nome'], 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-nome"><?= htmlspecialchars(explode(' ', $u['nome'])[0]) ?></div>
                <span class="user-role-badge" style="background:<?= $info_role['cor'] ?>;">
                    <?= $info_role['label'] ?>
                </span>
            </div>
        </div>

        <!-- Módulos -->
        <nav class="sidebar-nav" id="sidebar-nav">
        <?php foreach ($modulos as $modIdx => $mod): ?>
            <div class="sidebar-module <?= $mod['id'] === $mod_default ? 'active' : '' ?>"
                 data-modulo="<?= $mod['id'] ?>">

                <button class="sidebar-module-btn"
                        data-bs-toggle="collapse"
                        data-bs-target="#tabs-<?= $mod['id'] ?>"
                        aria-expanded="<?= $mod['id'] === $mod_default ? 'true' : 'false' ?>">
                    <span class="mod-icon"><?= $mod['icon'] ?></span>
                    <span class="mod-label"><?= $mod['label'] ?></span>
                    <?php if (!empty($mod['badge'])): ?>
                    <span class="mod-badge badge"
                          id="badge-<?= $mod['id'] ?>"
                          style="display:none;"></span>
                    <?php endif; ?>
                    <span class="mod-arrow">›</span>
                </button>

                <div class="collapse <?= $mod['id'] === $mod_default ? 'show' : '' ?>"
                     id="tabs-<?= $mod['id'] ?>">
                    <?php foreach ($mod['abas'] as $aba): ?>
                    <button class="sidebar-tab-btn
                            <?= ($mod['id'] === $mod_default && $aba['id'] === $aba_default) ? 'active' : '' ?>
                            <?= $aba['fase'] ? 'tab-futura' : '' ?>"
                            data-modulo="<?= $mod['id'] ?>"
                            data-aba="<?= $aba['id'] ?>"
                            data-frag="<?= $aba['frag'] ?>"
                            data-fase="<?= $aba['fase'] ?? '' ?>"
                            data-titulo="<?= htmlspecialchars($aba['meta']['titulo'] ?? $aba['label']) ?>"
                            data-descricao="<?= htmlspecialchars($aba['meta']['descricao'] ?? '') ?>">
                        <?= $aba['label'] ?>
                        <?php if ($aba['fase']): ?>
                        <span class="tab-fase-badge">F<?= $aba['fase'] ?></span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>

            </div>
        <?php endforeach; ?>
        </nav>

        <!-- Rodapé sidebar -->
        <div class="sidebar-footer">
            <span>v<?= VERSAO_SISTEMA ?></span>
            <a href="<?= BASE_PATH ?>/logout" class="sidebar-logout">Sair</a>
        </div>

    </aside>

    <!-- ── CONTEÚDO PRINCIPAL ────────────────────────────────────── -->
    <main class="dashboard-main" id="dashboard-main">
        <!-- Breadcrumb / título do módulo -->
        <div class="dashboard-topbar" id="dashboard-topbar">
            <span id="topbar-modulo">—</span>
            <span class="topbar-sep">›</span>
            <strong id="topbar-aba">—</strong>
        </div>
        <!-- Conteúdo do fragmento -->
        <div class="dashboard-content-area" id="dashboard-content">
            <div class="text-center py-5 text-muted">
                <div class="spinner-border text-success mb-3"></div>
                <p>Carregando…</p>
            </div>
        </div>
    </main>

</div><!-- /dashboard-outer -->

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>

<!-- ═══════════════════════════════════════════════════════════════
     ESTILOS DO DASHBOARD
════════════════════════════════════════════════════════════════ -->
<style>
/* Reset/Base */
body { overflow: hidden; }

.dashboard-outer {
    display: flex;
    height: calc(100vh - 56px);
    position: relative;
    overflow: hidden;
}

/* ── Sidebar ─────────────────────────────────────────────────── */
.dashboard-sidebar {
    width: 240px;
    min-width: 240px;
    height: 100%;
    background: #1b5e20;
    color: #fff;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    transition: transform 0.28s ease;
    z-index: 100;
    flex-shrink: 0;
}

.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px 10px;
    border-bottom: 1px solid rgba(255,255,255,.15);
}
.sidebar-brand {
    font-weight: 700;
    font-size: 1.05rem;
    letter-spacing: .3px;
}
.btn-sidebar-close {
    background: none; border: none; color: rgba(255,255,255,.6);
    font-size: 1.1rem; cursor: pointer; padding: 2px 6px; border-radius: 4px;
    display: none; /* visível só no mobile */
}
.btn-sidebar-close:hover { color: #fff; background: rgba(255,255,255,.1); }

/* User info */
.sidebar-user {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,.1);
}
.user-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,.2); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .9rem; flex-shrink: 0;
}
.user-info { min-width: 0; }
.user-nome {
    font-size: .85rem; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-role-badge {
    font-size: .65rem; font-weight: 700; text-transform: uppercase;
    padding: 1px 7px; border-radius: 20px; color: #fff; letter-spacing: .4px;
}

/* Nav */
.sidebar-nav { flex: 1; padding: 8px 0; }

.sidebar-module { border-bottom: 1px solid rgba(255,255,255,.06); }

.sidebar-module-btn {
    width: 100%; display: flex; align-items: center; gap: 8px;
    padding: 10px 16px; background: none; border: none; color: rgba(255,255,255,.85);
    font-size: .84rem; cursor: pointer; text-align: left;
    transition: background .15s, color .15s;
}
.sidebar-module-btn:hover { background: rgba(255,255,255,.08); color: #fff; }
.sidebar-module.active > .sidebar-module-btn { color: #fff; }

.mod-icon { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }
.mod-label { flex: 1; font-weight: 500; }
.mod-badge { font-size: .65rem; padding: 2px 5px; }
.mod-arrow {
    font-size: .9rem; opacity: .5; transition: transform .2s;
    display: inline-block;
}
.sidebar-module-btn[aria-expanded="true"] .mod-arrow { transform: rotate(90deg); }

/* Abas dentro do módulo */
.sidebar-tab-btn {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; padding: 7px 16px 7px 44px;
    background: none; border: none; color: rgba(255,255,255,.65);
    font-size: .82rem; cursor: pointer; text-align: left;
    transition: background .12s, color .12s;
    border-left: 2px solid transparent;
}
.sidebar-tab-btn:hover { background: rgba(255,255,255,.07); color: rgba(255,255,255,.9); }
.sidebar-tab-btn.active {
    background: rgba(255,255,255,.12);
    color: #fff; font-weight: 600;
    border-left-color: #a5d6a7;
}
.tab-futura { opacity: .65; }
.tab-fase-badge {
    font-size: .58rem; background: rgba(255,255,255,.2);
    padding: 1px 5px; border-radius: 10px; letter-spacing: .3px;
    flex-shrink: 0;
}

/* Sidebar footer */
.sidebar-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; border-top: 1px solid rgba(255,255,255,.1);
    font-size: .72rem; color: rgba(255,255,255,.45);
}
.sidebar-logout {
    color: rgba(255,255,255,.5); text-decoration: none;
    transition: color .15s;
}
.sidebar-logout:hover { color: #fff; }

/* ── Main area ───────────────────────────────────────────────── */
.dashboard-main {
    flex: 1; display: flex; flex-direction: column;
    overflow: hidden; background: #f5f5f5;
}
.dashboard-topbar {
    background: #fff; border-bottom: 1px solid #e0e0e0;
    padding: 8px 20px; font-size: .82rem; color: #666;
    display: flex; align-items: center; gap: 6px; flex-shrink: 0;
}
#topbar-modulo { color: #2e7d32; }
.topbar-sep { color: #bbb; }
#topbar-aba { color: #333; }

.dashboard-content-area {
    flex: 1; overflow-y: auto; padding: 0;
}

/* ── Mobile toggle ───────────────────────────────────────────── */
.btn-sidebar-mobile {
    display: none;
    position: fixed; top: 62px; left: 10px; z-index: 200;
    width: 36px; height: 36px; border-radius: 8px;
    background: #2e7d32; color: #fff; border: none;
    font-size: 1.1rem; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,.25);
}
.sidebar-overlay {
    display: none;
    position: fixed; inset: 0; z-index: 90;
    background: rgba(0,0,0,.4);
}

/* ── Mobile breakpoint ───────────────────────────────────────── */
@media (max-width: 767.98px) {
    body { overflow: auto; }
    .dashboard-outer { height: auto; flex-direction: column; }
    .dashboard-sidebar {
        position: fixed; top: 56px; left: 0; bottom: 0;
        transform: translateX(-100%);
        z-index: 95;
    }
    .dashboard-sidebar.open { transform: translateX(0); }
    .sidebar-overlay.open { display: block; }
    .btn-sidebar-mobile { display: flex; align-items: center; justify-content: center; }
    .btn-sidebar-close { display: block; }
    .dashboard-main { height: calc(100vh - 56px); }
    .btn-sidebar-mobile { top: 66px; }
}
</style>

<!-- ═══════════════════════════════════════════════════════════════
     JAVASCRIPT DO DASHBOARD
════════════════════════════════════════════════════════════════ -->
<script>
(function () {
'use strict';

// ── Estado ─────────────────────────────────────────────────────
const STORAGE_KEY = 'desffrut_dash_estado';
let estado = {
    modulo: '<?= $mod_default ?>',
    aba:    '<?= $aba_default ?>',
};

// Restaura estado salvo (se o módulo ainda existir para o role)
try {
    const salvo = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    const btnExiste = document.querySelector(
        `.sidebar-tab-btn[data-modulo="${salvo.modulo}"][data-aba="${salvo.aba}"]`
    );
    if (btnExiste) { estado = salvo; }
} catch {}

// ── Fragmento: loading e execução ──────────────────────────────
let timerLimpeza = null;

async function carregarFragmento(btn) {
    const frag    = btn.dataset.frag;
    const modulo  = btn.dataset.modulo;
    const aba     = btn.dataset.aba;
    const titulo  = btn.dataset.titulo || btn.textContent.trim();
    const descr   = btn.dataset.descricao || '';
    const fase    = btn.dataset.fase || '';
    const content = document.getElementById('dashboard-content');

    // Spinner
    content.innerHTML = `
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-success mb-2" style="width:2rem;height:2rem;"></div>
            <p class="mt-1 small">Carregando…</p>
        </div>`;

    // Limpa timers do fragmento anterior
    if (timerLimpeza) { clearTimeout(timerLimpeza); timerLimpeza = null; }
    if (window._dashInterval) { clearInterval(window._dashInterval); window._dashInterval = null; }

    // Monta URL com parâmetros de contexto
    const params = new URLSearchParams({ frag, m: modulo, a: aba, titulo, descricao: descr, fase });
    const url    = `${APP.base}/dashboard/fragmento?${params}`;

    try {
        const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const html = await r.text();

        // Extrai scripts antes de inserir no DOM
        const parser = new DOMParser();
        const doc    = parser.parseFromString(html, 'text/html');
        const scripts = [...doc.querySelectorAll('script')];
        scripts.forEach(s => s.remove());

        // Extrai e injeta estilos
        [...doc.querySelectorAll('style')].forEach(s => {
            const existing = document.querySelector(`style[data-frag="${frag}"]`);
            if (existing) existing.remove();
            const ns = document.createElement('style');
            ns.setAttribute('data-frag', frag);
            ns.textContent = s.textContent;
            document.head.appendChild(ns);
        });
        doc.querySelectorAll('style').forEach(s => s.remove());

        // Insere HTML
        content.innerHTML = doc.body ? doc.body.innerHTML : html;

        // Executa scripts
        scripts.forEach(s => {
            if (!s.textContent.trim()) return;
            const el = document.createElement('script');
            el.textContent = s.textContent;
            document.body.appendChild(el);
            document.body.removeChild(el);
        });

    } catch (e) {
        content.innerHTML = `
            <div class="alert alert-danger m-3">
                <strong>Erro ao carregar módulo.</strong> ${e.message}
                <br><button class="btn btn-sm btn-outline-danger mt-2"
                    onclick="carregarAbaAtiva()">Tentar novamente</button>
            </div>`;
    }
}

// ── Ativar aba ──────────────────────────────────────────────────
function ativarAba(btn) {
    const modulo = btn.dataset.modulo;
    const aba    = btn.dataset.aba;

    // Remove active de todas as abas
    document.querySelectorAll('.sidebar-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Atualiza estado
    estado = { modulo, aba };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(estado));

    // Topbar
    const modBtn = btn.closest('.sidebar-module');
    document.getElementById('topbar-modulo').textContent =
        modBtn?.querySelector('.mod-label')?.textContent || modulo;
    document.getElementById('topbar-aba').textContent =
        btn.querySelector('.tab-fase-badge')
            ? btn.textContent.replace(/F\d/, '').trim()
            : btn.textContent.trim();

    carregarFragmento(btn);
}

// ── Evento: clique em aba ────────────────────────────────────────
document.querySelectorAll('.sidebar-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => ativarAba(btn));
});

// ── Recarregar aba atual ─────────────────────────────────────────
window.carregarAbaAtiva = function () {
    const btn = document.querySelector(
        `.sidebar-tab-btn[data-modulo="${estado.modulo}"][data-aba="${estado.aba}"]`
    );
    if (btn) ativarAba(btn);
};

// ── Mobile: toggle sidebar ───────────────────────────────────────
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebar-overlay');
const btnToggle = document.getElementById('btn-sidebar-toggle');
const btnClose  = document.getElementById('btn-sidebar-close');

function abrirSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
}
function fecharSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
}

btnToggle?.addEventListener('click', () =>
    sidebar.classList.contains('open') ? fecharSidebar() : abrirSidebar()
);
btnClose?.addEventListener('click', fecharSidebar);
overlay?.addEventListener('click', fecharSidebar);

// Fecha sidebar ao clicar em aba (mobile)
document.querySelectorAll('.sidebar-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        if (window.innerWidth < 768) fecharSidebar();
    });
});

// ── Badge: estoque crítico ────────────────────────────────────────
async function atualizarBadgeCritico() {
    const token = sessionStorage.getItem('desffrut_token') || '';
    if (!token) return;
    try {
        const r = await fetch(APP.api + '/estoque/critico',
            { headers: { 'Authorization': 'Bearer ' + token } });
        const j = await r.json();
        const cnt = (j.data || []).length;
        const badge = document.getElementById('badge-produtos_estoque');
        if (badge) {
            badge.style.display = cnt > 0 ? '' : 'none';
            badge.textContent   = cnt;
            badge.className     = 'mod-badge badge ' + (cnt > 0 ? 'bg-danger' : 'bg-secondary');
        }
    } catch {}
}

// ── Init ─────────────────────────────────────────────────────────
carregarAbaAtiva();
atualizarBadgeCritico();
setInterval(atualizarBadgeCritico, 60_000); // atualiza badge a cada 1 min

})();
</script>
