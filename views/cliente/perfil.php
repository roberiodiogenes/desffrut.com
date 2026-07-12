<?php
/**
 * Desffrut — Perfil do Cliente
 * Exibe foto, pontos, histórico e edição de dados.
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

iniciar_sessao();

$roles_permitidos = ['cliente', 'super_admin'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

// Dados frescos do banco
$sessao     = usuario_logado();
$usuario_db = (new Usuario())->buscarPorId((int) ($sessao['id'] ?? 0));

if (!$usuario_db) { redirecionar(BASE_PATH . '/login'); }

$titulo_pagina  = 'Meu Perfil';
$mostrar_sacola = false;
$mostrar_busca  = false;
require_once __DIR__ . '/../../app/views/layout/header.php';

// Helpers
function fmt_cpf(?string $cpf): string {
    if (empty($cpf)) return '—';
    $d = preg_replace('/\D/', '', $cpf);
    return strlen($d) === 11
        ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d)
        : $cpf;
}
function fmt_fone(?string $f): string {
    if (empty($f)) return '—';
    $d = preg_replace('/\D/', '', $f);
    if (strlen($d) === 11) return '(' . substr($d,0,2) . ') ' . substr($d,2,5) . '-' . substr($d,7);
    if (strlen($d) === 10) return '(' . substr($d,0,2) . ') ' . substr($d,2,4) . '-' . substr($d,6);
    return $f;
}

$foto_url     = !empty($usuario_db['foto_perfil'])
    ? BASE_PATH . '/' . htmlspecialchars($usuario_db['foto_perfil']) . '?v=' . time()
    : null;
$pontos       = (int) ($usuario_db['pontos_fidelidade'] ?? 0);
$pontos_reais = number_format(pontos_para_reais($pontos), 2, ',', '.');
$membro_desde = $usuario_db['created_at']
    ? date('F/Y', strtotime($usuario_db['created_at']))
    : '';
$inicial      = mb_strtoupper(mb_substr($usuario_db['nome'], 0, 1));
?>

<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- ESTILOS                                                                     -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Variáveis ─────────────────────────────────────────────── */
:root {
    --pf-green: #2e7d32;
    --pf-green-d: #1b5e20;
    --pf-green-l: #e8f5e9;
    --pf-radius: 16px;
}

/* ── Hero ──────────────────────────────────────────────────── */
.pf-hero {
    background: linear-gradient(135deg, var(--pf-green) 0%, var(--pf-green-d) 100%);
    border-radius: var(--pf-radius);
    padding: 28px 24px 24px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.pf-hero::after {
    content: '🍃';
    position: absolute;
    right: -10px; bottom: -16px;
    font-size: 6rem;
    opacity: .08;
    pointer-events: none;
    user-select: none;
}
.pf-avatar-wrap {
    position: relative; display: inline-block;
}
.pf-avatar {
    width: 96px; height: 96px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,.7);
    box-shadow: 0 4px 16px rgba(0,0,0,.25);
    display: block;
}
.pf-avatar-placeholder {
    width: 96px; height: 96px; border-radius: 50%;
    background: rgba(255,255,255,.18);
    border: 3px solid rgba(255,255,255,.45);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.4rem; font-weight: 800; color: #fff;
    letter-spacing: -1px;
}
.pf-avatar-edit-btn {
    position: absolute; bottom: 0; right: 0;
    width: 28px; height: 28px; border-radius: 50%;
    background: #fff; color: var(--pf-green);
    border: none; font-size: .9rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,.2);
    transition: transform .15s;
}
.pf-avatar-edit-btn:hover { transform: scale(1.12); }
.pf-hero-name  { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
.pf-hero-since { font-size: .8rem; opacity: .8; margin-top: 2px; }
.pf-pts-badge {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.3);
    border-radius: 30px;
    padding: 6px 18px;
    display: inline-flex; align-items: baseline; gap: 6px;
}
.pf-pts-num { font-size: 1.7rem; font-weight: 800; }
.pf-pts-lbl { font-size: .72rem; opacity: .85; line-height: 1; }
.pf-pts-sub  { font-size: .75rem; opacity: .7; margin-top: 4px; }

/* ── Tabs ──────────────────────────────────────────────────── */
.pf-nav { border-bottom: 2px solid #e0e0e0; gap: 4px; }
.pf-nav .nav-link {
    color: #666; border: none; border-bottom: 2px solid transparent;
    border-radius: 0; padding: 10px 18px; font-size: .9rem;
    margin-bottom: -2px; font-weight: 500; transition: color .15s;
}
.pf-nav .nav-link:hover { color: var(--pf-green); }
.pf-nav .nav-link.active {
    color: var(--pf-green); border-bottom-color: var(--pf-green);
    background: none; font-weight: 700;
}

/* ── Cards ─────────────────────────────────────────────────── */
.pf-card {
    background: #fff; border-radius: 12px;
    border: 1px solid #ece9e9; padding: 20px;
    margin-bottom: 14px;
}
.pf-card-title {
    font-size: .78rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: #aaa; margin-bottom: 14px;
}
.pf-info-row { display: flex; align-items: flex-start; gap: 10px; padding: 7px 0; }
.pf-info-row + .pf-info-row { border-top: 1px solid #f5f5f5; }
.pf-info-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
.pf-info-key  { font-size: .75rem; color: #aaa; margin-bottom: 1px; }
.pf-info-val  { font-size: .92rem; color: #222; font-weight: 500; }

/* ── Histórico ─────────────────────────────────────────────── */
.pf-hist-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0; cursor: default;
}
.pf-hist-row + .pf-hist-row { border-top: 1px solid #f5f5f5; }
.pf-hist-icon {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
}
.pf-hist-icon.delivery { background: #e8f5e9; }
.pf-hist-icon.pdv      { background: #fff3e0; }
.pf-hist-date  { font-size: .78rem; color: #999; }
.pf-hist-label { font-size: .92rem; font-weight: 600; color: #222; }
.pf-hist-total { margin-left: auto; text-align: right; flex-shrink: 0; }
.pf-hist-price { font-size: 1rem; font-weight: 700; color: var(--pf-green); }
.pf-hist-pts   { font-size: .72rem; color: #aaa; }

/* ── Badge de status ───────────────────────────────────────── */
.pf-status { display:inline-flex; align-items:center; gap:4px; }
.pf-status-dot {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}
.pf-status-txt { font-size: .72rem; font-weight: 600; }
.pf-s-verde    { background: #4caf50; }
.pf-s-azul     { background: #2196f3; }
.pf-s-amarelo  { background: #ff9800; }
.pf-s-vermelho { background: #f44336; }
.c-verde       { color: #2e7d32; }
.c-azul        { color: #1565c0; }
.c-amarelo     { color: #e65100; }
.c-vermelho    { color: #c62828; }

/* ── Foto upload preview ───────────────────────────────────── */
.pf-foto-wrap {
    position: relative; display: inline-block; cursor: pointer;
}
.pf-foto-wrap img,
.pf-foto-wrap .pf-foto-placeholder {
    width: 108px; height: 108px; border-radius: 50%;
    border: 3px solid #e0e0e0; object-fit: cover;
    display: block;
}
.pf-foto-wrap .pf-foto-placeholder {
    background: var(--pf-green-l); display: flex;
    align-items: center; justify-content: center;
    font-size: 2.4rem; color: #aaa;
}
.pf-foto-overlay {
    position: absolute; inset: 0; border-radius: 50%;
    background: rgba(0,0,0,.4);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.5rem;
    opacity: 0; transition: opacity .2s;
}
.pf-foto-wrap:hover .pf-foto-overlay { opacity: 1; }

/* ── Botão de ação flutuante ───────────────────────────────── */
.btn-verde {
    background: var(--pf-green); color: #fff; border: none;
    border-radius: 8px; padding: 10px 24px; font-weight: 600;
    font-size: .9rem; transition: background .15s;
    min-height: 44px;
}
.btn-verde:hover { background: var(--pf-green-d); color: #fff; }

/* ── Indique e Ganhe ───────────────────────────────────────── */
.pf-ind-box {
    background: var(--pf-green-l); border: 1.5px dashed #66bb6a;
    border-radius: 10px; padding: 16px; text-align: center;
}
.pf-ind-code {
    font-size: 2rem; font-weight: 800; letter-spacing: 6px; color: var(--pf-green-d);
}

/* ── Mobile ────────────────────────────────────────────────── */
@media (max-width: 576px) {
    .pf-hero { padding: 20px 16px; }
    .pf-nav .nav-link { padding: 8px 12px; font-size: .82rem; }
    .pf-card { padding: 16px; }
}
</style>

<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- LAYOUT                                                                      -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<div class="container py-4" style="max-width:680px;">

    <!-- ── HERO: foto grande + nome + pontos ───────────────────────────────── -->
    <div class="pf-hero mb-4">
        <div class="d-flex align-items-center gap-4 flex-wrap">

            <!-- Avatar -->
            <div class="pf-avatar-wrap">
                <?php if ($foto_url): ?>
                <img src="<?= $foto_url ?>" class="pf-avatar" id="hero-foto" alt="Foto de perfil">
                <?php else: ?>
                <div class="pf-avatar-placeholder" id="hero-foto-placeholder"><?= $inicial ?></div>
                <img id="hero-foto" class="pf-avatar" style="display:none;" src="" alt="">
                <?php endif; ?>
                <button class="pf-avatar-edit-btn" title="Trocar foto" onclick="ativarUploadFoto()">✏️</button>
            </div>

            <!-- Nome + pontos -->
            <div class="flex-grow-1">
                <div class="pf-hero-name"><?= htmlspecialchars($usuario_db['nome']) ?></div>
                <?php if ($membro_desde): ?>
                <div class="pf-hero-since">Membro desde <?= $membro_desde ?></div>
                <?php endif; ?>
                <div class="mt-3">
                    <div class="pf-pts-badge">
                        <div>
                            <div class="pf-pts-lbl">PONTOS</div>
                            <div class="pf-pts-num"><?= number_format($pontos) ?></div>
                        </div>
                        <div style="width:1px;height:36px;background:rgba(255,255,255,.3);margin:0 4px;"></div>
                        <div>
                            <div class="pf-pts-lbl">EQUIVALEM A</div>
                            <div class="pf-pts-num" style="font-size:1.3rem;">R$ <?= $pontos_reais ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABS ─────────────────────────────────────────────────────────────── -->
    <ul class="nav pf-nav mb-3" id="perfilTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-perfil" type="button">
                👤 Dados
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pedidos" type="button" id="btn-tab-pedidos">
                🛍 Pedidos
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-editar" type="button" id="btn-tab-editar">
                ✏️ Editar
            </button>
        </li>
        <li class="nav-item" id="tab-ind-li" style="display:none;">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-indicacao" type="button">
                🎁 Indique
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- TAB: DADOS                                                         -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="tab-perfil">

            <div class="pf-card">
                <div class="pf-card-title">Dados Pessoais</div>

                <div class="pf-info-row">
                    <span class="pf-info-icon">📧</span>
                    <div>
                        <div class="pf-info-key">E-mail</div>
                        <div class="pf-info-val"><?= htmlspecialchars($usuario_db['email']) ?></div>
                    </div>
                </div>

                <div class="pf-info-row">
                    <span class="pf-info-icon">🪪</span>
                    <div>
                        <div class="pf-info-key">CPF</div>
                        <div class="pf-info-val"><?= fmt_cpf($usuario_db['cpf'] ?? null) ?></div>
                    </div>
                </div>

                <div class="pf-info-row">
                    <span class="pf-info-icon">📱</span>
                    <div>
                        <div class="pf-info-key">WhatsApp</div>
                        <div class="pf-info-val"><?= fmt_fone($usuario_db['whatsapp'] ?? null) ?></div>
                    </div>
                </div>

                <div class="pf-info-row">
                    <span class="pf-info-icon">📞</span>
                    <div>
                        <div class="pf-info-key">Telefone</div>
                        <div class="pf-info-val"><?= fmt_fone($usuario_db['telefone'] ?? null) ?></div>
                    </div>
                </div>

                <?php
                $end_parts = array_filter([
                    $usuario_db['endereco'] ?? '',
                    !empty($usuario_db['numero']) ? 'nº ' . $usuario_db['numero'] : '',
                    $usuario_db['complemento'] ?? '',
                    $usuario_db['bairro'] ?? '',
                ]);
                $end_str = implode(', ', $end_parts);
                ?>
                <div class="pf-info-row">
                    <span class="pf-info-icon">📍</span>
                    <div>
                        <div class="pf-info-key">Endereço de entrega</div>
                        <div class="pf-info-val"><?= $end_str ? htmlspecialchars($end_str) : '—' ?></div>
                    </div>
                </div>

            </div><!-- /pf-card -->

            <button class="btn-verde w-100" onclick="document.getElementById('btn-tab-editar').click()">
                ✏️ Editar meus dados
            </button>

        </div><!-- /tab-perfil -->


        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- TAB: PEDIDOS                                                       -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-pedidos">

            <div class="pf-card">
                <div class="pf-card-title">Histórico de compras</div>

                <div id="hist-loading" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-success"></div>
                    <span class="ms-2">Carregando…</span>
                </div>
                <div id="hist-vazio" style="display:none;" class="text-center py-4 text-muted">
                    <div style="font-size:2.5rem;">🛒</div>
                    <div class="mt-1 fw-semibold">Nenhum pedido ainda</div>
                    <div class="small mt-1">Faça seu primeiro pedido no catálogo!</div>
                    <a href="<?= BASE_PATH ?>/" class="btn-verde mt-3 d-inline-block"
                       style="text-decoration:none;padding:8px 20px;">Ver catálogo</a>
                </div>
                <div id="hist-lista"></div>
            </div>

        </div><!-- /tab-pedidos -->


        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- TAB: EDITAR                                                        -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-editar">

            <!-- ── Upload de Foto ──────────────────────────────────────────── -->
            <div class="pf-card">
                <div class="pf-card-title">Foto de perfil</div>
                <div class="d-flex align-items-center gap-4">
                    <label for="foto-input" class="pf-foto-wrap" title="Clique para trocar a foto">
                        <?php if ($foto_url): ?>
                        <img src="<?= $foto_url ?>" id="foto-edit-preview" alt="Foto">
                        <?php else: ?>
                        <div class="pf-foto-placeholder" id="foto-placeholder">📷</div>
                        <img id="foto-edit-preview" style="display:none;width:108px;height:108px;
                             border-radius:50%;border:3px solid #e0e0e0;object-fit:cover;" src="" alt="">
                        <?php endif; ?>
                        <div class="pf-foto-overlay">📷</div>
                        <input type="file" id="foto-input" accept="image/*" class="d-none">
                    </label>
                    <div>
                        <div class="fw-semibold mb-1" style="font-size:.9rem;">
                            <?= $foto_url ? 'Trocar foto' : 'Adicionar foto' ?>
                        </div>
                        <div class="text-muted small mb-2">JPG, PNG ou WebP · convertido automaticamente</div>
                        <button class="btn-verde btn-sm d-none" id="btn-salvar-foto" style="padding:6px 16px;">
                            💾 Enviar
                        </button>
                    </div>
                </div>
                <div id="msg-foto" class="alert d-none small mt-3 mb-0"></div>
            </div>

            <!-- ── Dados pessoais ──────────────────────────────────────────── -->
            <div class="pf-card">
                <div class="pf-card-title">Dados Pessoais</div>
                <form id="form-perfil" novalidate>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nome completo</label>
                        <input type="text" id="edit-nome" class="form-control"
                               value="<?= htmlspecialchars($usuario_db['nome']) ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold">WhatsApp</label>
                            <input type="tel" id="edit-whatsapp" class="form-control fone-mask"
                                   placeholder="(85) 99999-9999"
                                   value="<?= htmlspecialchars($usuario_db['whatsapp'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold">Telefone fixo</label>
                            <input type="tel" id="edit-telefone" class="form-control fone-mask"
                                   placeholder="(85) 9999-9999"
                                   value="<?= htmlspecialchars($usuario_db['telefone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="pf-card-title mt-2">📍 Endereço de Entrega</div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small">Rua / Avenida</label>
                            <input type="text" id="edit-endereco" class="form-control"
                                   placeholder="Rua das Flores"
                                   value="<?= htmlspecialchars($usuario_db['endereco'] ?? '') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Número</label>
                            <input type="text" id="edit-numero" class="form-control"
                                   placeholder="S/N"
                                   value="<?= htmlspecialchars($usuario_db['numero'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small">Complemento</label>
                            <input type="text" id="edit-complemento" class="form-control"
                                   placeholder="Apto, bloco…"
                                   value="<?= htmlspecialchars($usuario_db['complemento'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small">Bairro</label>
                            <input type="text" id="edit-bairro" class="form-control"
                                   placeholder="Bairro"
                                   value="<?= htmlspecialchars($usuario_db['bairro'] ?? '') ?>">
                        </div>
                    </div>

                    <div id="msg-perfil" class="alert d-none mb-3"></div>
                    <button type="submit" class="btn-verde w-100">💾 Salvar alterações</button>
                </form>
            </div>

            <!-- ── Zona de perigo ──────────────────────────────────────────── -->
            <div class="pf-card" style="border-color:#ffcdd2;">
                <div class="pf-card-title" style="color:#c62828;">⚠️ Zona de Perigo</div>
                <p class="text-muted small mb-3">
                    Conforme a <strong>LGPD</strong>, você pode solicitar a remoção dos seus dados.
                    Seus dados pessoais serão anonimizados — o histórico financeiro é mantido apenas
                    para fins contábeis, sem identificação pessoal.
                </p>
                <button class="btn btn-outline-danger btn-sm" onclick="solicitarExclusao()">
                    Solicitar Exclusão de Conta
                </button>
            </div>

        </div><!-- /tab-editar -->


        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- TAB: INDICAÇÃO (carregada dinamicamente)                           -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-indicacao">
            <div class="pf-card">
                <div class="pf-card-title">🎁 Indique e Ganhe</div>
                <p class="text-muted small mb-3">
                    Compartilhe seu link. A cada amigo que fizer o <strong>primeiro pedido entregue</strong>,
                    você recebe pontos de bônus!
                </p>
                <div class="pf-ind-box mb-3">
                    <div class="pf-pts-lbl mb-1" style="color:#555;">SEU CÓDIGO</div>
                    <div class="pf-ind-code" id="ind-codigo">—</div>
                    <div class="text-muted small mt-2" id="ind-link" style="word-break:break-all;"></div>
                </div>
                <div class="d-flex gap-2 flex-wrap mb-4">
                    <button class="btn-verde" id="btn-copiar-ind" style="padding:8px 16px;">📋 Copiar link</button>
                    <a class="btn btn-outline-success btn-sm d-flex align-items-center" id="btn-wa-ind" href="#" target="_blank">
                        📲 WhatsApp
                    </a>
                </div>
                <div class="row text-center g-3" id="ind-stats">
                    <div class="col-4">
                        <div class="fw-bold fs-4 c-verde" id="ind-total">0</div>
                        <div class="text-muted small">Indicados</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-4 c-verde" id="ind-bonus">0</div>
                        <div class="text-muted small">Bônus pagos</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-4 c-verde" id="ind-pts-val">0</div>
                        <div class="text-muted small">Pts / indicação</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->
</div><!-- /container -->


<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- SCRIPTS                                                                     -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<script>
const token = sessionStorage.getItem('desffrut_token') || '';

// ── Máscara de telefone ───────────────────────────────────────────────────────
document.querySelectorAll('.fone-mask').forEach(el => {
    el.addEventListener('input', () => {
        let v = el.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
        else if (v.length > 6) v = v.replace(/^(\d{2})(\d{4})(\d*)$/, '($1) $2-$3');
        else if (v.length > 2) v = v.replace(/^(\d{2})(\d*)$/, '($1) $2');
        el.value = v;
    });
});

// ── Helper: ativa o input de foto pelo botão do hero ─────────────────────────
function ativarUploadFoto() {
    document.getElementById('btn-tab-editar').click();
    setTimeout(() => document.getElementById('foto-input').click(), 300);
}

// ── Upload de foto ────────────────────────────────────────────────────────────
const fotoInput     = document.getElementById('foto-input');
const fotoPreviewEdit = document.getElementById('foto-edit-preview');
const fotoPlaceholder = document.getElementById('foto-placeholder');
const btnSalvarFoto = document.getElementById('btn-salvar-foto');
const msgFoto       = document.getElementById('msg-foto');

fotoInput?.addEventListener('change', () => {
    const file = fotoInput.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        msgFoto.textContent = 'Selecione uma imagem válida (JPG, PNG, WebP…)';
        msgFoto.className   = 'alert alert-warning small mt-3 mb-0';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
        fotoPreviewEdit.src   = e.target.result;
        fotoPreviewEdit.style.display = '';
    };
    reader.readAsDataURL(file);
    btnSalvarFoto.classList.remove('d-none');
    msgFoto.className = 'alert d-none small mt-3 mb-0';
});

btnSalvarFoto?.addEventListener('click', async () => {
    const file = fotoInput.files[0];
    if (!file) return;
    btnSalvarFoto.disabled     = true;
    btnSalvarFoto.textContent  = 'Enviando…';
    const fd = new FormData();
    fd.append('foto', file);
    try {
        const r = await fetch(APP.api + '/clientes/foto', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token },
            body: fd,
        });
        const j = await r.json();
        msgFoto.textContent = j.message || (r.ok ? 'Foto salva!' : 'Erro ao salvar foto.');
        msgFoto.className   = 'alert small mt-3 mb-0 ' + (r.ok ? 'alert-success' : 'alert-danger');
        if (r.ok && j.data?.foto_url) {
            const novaUrl = APP.base + '/' + j.data.foto_url;
            fotoPreviewEdit.src = novaUrl;
            // Atualiza o hero também
            const heroFoto    = document.getElementById('hero-foto');
            const heroPlaceh  = document.getElementById('hero-foto-placeholder');
            if (heroFoto) { heroFoto.src = novaUrl; heroFoto.style.display = ''; }
            if (heroPlaceh) heroPlaceh.style.display = 'none';
        }
    } catch {
        msgFoto.textContent = 'Erro de conexão.';
        msgFoto.className   = 'alert alert-danger small mt-3 mb-0';
    } finally {
        btnSalvarFoto.disabled    = false;
        btnSalvarFoto.textContent = '💾 Enviar';
    }
});

// ── Salvar dados do perfil ────────────────────────────────────────────────────
document.getElementById('form-perfil')?.addEventListener('submit', async e => {
    e.preventDefault();
    const msg  = document.getElementById('msg-perfil');
    const nome = document.getElementById('edit-nome').value.trim();
    if (!nome) {
        msg.textContent = 'O nome é obrigatório.';
        msg.className   = 'alert alert-warning';
        return;
    }
    try {
        const r = await fetch(APP.api + '/clientes/perfil', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            body: JSON.stringify({
                nome,
                whatsapp:    document.getElementById('edit-whatsapp').value.trim(),
                telefone:    document.getElementById('edit-telefone').value.trim(),
                endereco:    document.getElementById('edit-endereco').value.trim(),
                numero:      document.getElementById('edit-numero').value.trim(),
                complemento: document.getElementById('edit-complemento').value.trim(),
                bairro:      document.getElementById('edit-bairro').value.trim(),
            }),
        });
        const j = await r.json();
        msg.textContent = j.message || (r.ok ? 'Dados atualizados!' : 'Erro ao salvar.');
        msg.className   = 'alert ' + (r.ok ? 'alert-success' : 'alert-danger');
        msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch {
        msg.textContent = 'Erro de conexão. Tente novamente.';
        msg.className   = 'alert alert-danger';
    }
});

// ── Exclusão de conta ─────────────────────────────────────────────────────────
async function solicitarExclusao() {
    if (!confirm('Tem certeza? Seus dados pessoais serão anonimizados permanentemente.')) return;
    if (!confirm('Confirmar exclusão definitiva de conta?')) return;
    try {
        const r = await fetch(APP.api + '/clientes/excluir', {
            method: 'DELETE', headers: { 'Authorization': 'Bearer ' + token }
        });
        const j = await r.json();
        if (r.ok) {
            alert('Conta excluída. Dados anonimizados conforme a LGPD.');
            sessionStorage.clear();
            window.location.href = APP.base + '/';
        } else {
            alert('Erro: ' + (j.message || 'Tente novamente.'));
        }
    } catch { alert('Erro de conexão. Tente novamente.'); }
}

// ── Histórico de compras ──────────────────────────────────────────────────────
const LABEL_STATUS = {
    pendente:      { txt: 'Pendente',    cls: 'amarelo' },
    confirmado:    { txt: 'Confirmado',  cls: 'azul'    },
    em_preparo:    { txt: 'Em preparo',  cls: 'azul'    },
    em_entrega:    { txt: 'Saiu p/ entrega', cls: 'azul' },
    entregue:      { txt: 'Entregue',   cls: 'verde'   },
    finalizada:    { txt: 'Finalizada', cls: 'verde'   },
    cancelado:     { txt: 'Cancelado',  cls: 'vermelho' },
    cancelada:     { txt: 'Cancelado',  cls: 'vermelho' },
};

function renderHistorico(lista) {
    const el = document.getElementById('hist-lista');
    document.getElementById('hist-loading').style.display = 'none';
    if (!lista.length) { document.getElementById('hist-vazio').style.display = ''; return; }

    el.innerHTML = lista.map(v => {
        const dt  = new Date(v.criado_em);
        const dtf = dt.toLocaleDateString('pt-BR', { day:'2-digit', month:'short', year:'numeric' });
        const total = parseFloat(v.total_final ?? 0).toFixed(2).replace('.', ',');
        const pts = v.pontos_ganhos ?? 0;
        const s   = LABEL_STATUS[v.status] || { txt: v.status, cls: 'amarelo' };
        const tipo = v.tipo === 'pdv' ? '🏪' : '🛵';
        return `
        <div class="pf-hist-row">
            <div class="pf-hist-icon ${v.tipo === 'pdv' ? 'pdv' : 'delivery'}">${tipo}</div>
            <div>
                <div class="pf-hist-label">Pedido #${v.id}</div>
                <div class="pf-hist-date">${dtf}</div>
                <div class="pf-status mt-1">
                    <span class="pf-status-dot pf-s-${s.cls}"></span>
                    <span class="pf-status-txt c-${s.cls}">${s.txt}</span>
                </div>
            </div>
            <div class="pf-hist-total">
                <div class="pf-hist-price">R$ ${total}</div>
                ${pts ? `<div class="pf-hist-pts">+${pts} pts</div>` : ''}
            </div>
        </div>`;
    }).join('');
}

async function carregarHistorico() {
    try {
        const r = await fetch(APP.api + '/clientes/compras', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!r.ok) throw new Error();
        const j = await r.json();
        renderHistorico(j.data || []);
    } catch {
        document.getElementById('hist-loading').style.display = 'none';
        document.getElementById('hist-vazio').style.display   = '';
    }
}

// Carrega histórico ao clicar na aba de pedidos
document.getElementById('btn-tab-pedidos')?.addEventListener('shown.bs.tab', carregarHistorico);

// ── Indique e Ganhe (Fase 12) ─────────────────────────────────────────────────
(async function carregarIndicacao() {
    try {
        const r = await fetch(APP.api + '/adtech/meu-codigo');
        if (!r.ok) return;
        const j = await r.json();
        if (j.status !== 'ok') return;
        const d = j.data;

        // Exibe a aba
        document.getElementById('tab-ind-li').style.display = '';

        const base = window.location.origin + APP.base;
        const link = `${base}/cadastro?ref=${d.codigo}`;
        document.getElementById('ind-codigo').textContent   = d.codigo;
        document.getElementById('ind-link').textContent     = link;
        document.getElementById('ind-total').textContent    = d.total_indicados;
        document.getElementById('ind-bonus').textContent    = d.bonus_pagos;
        document.getElementById('ind-pts-val').textContent  = d.pontos_por_indicacao;

        document.getElementById('btn-copiar-ind').addEventListener('click', () => {
            navigator.clipboard.writeText(link).then(() => {
                document.getElementById('btn-copiar-ind').textContent = '✅ Copiado!';
                setTimeout(() => { document.getElementById('btn-copiar-ind').textContent = '📋 Copiar link'; }, 2000);
            });
        });

        const msgWa = `Olá! Compro frutas e verduras na Desffrut 🍉🥦. Crie sua conta com meu link: ${link}`;
        document.getElementById('btn-wa-ind').href = 'https://wa.me/?text=' + encodeURIComponent(msgWa);
    } catch (_) {}
})();
</script>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
