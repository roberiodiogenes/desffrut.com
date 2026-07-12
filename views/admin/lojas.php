<?php
/**
 * Desffrut — Gestão de Lojas (exclusivo super_admin)
 * Rota: /admin/lojas
 * CRUD completo de filiais. Apenas super_admin pode criar novas lojas.
 */
$roles_permitidos = ['super_admin'];
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
    <title>Gestão de Lojas — <?= NOME_SISTEMA ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        body { background:#f4f6fa; font-family:'Segoe UI',sans-serif; }
        .adm-header { background:#1b5e20; color:#fff; padding:16px 24px;
                      display:flex; align-items:center; gap:16px; }
        .adm-header a { color:rgba(255,255,255,.75); text-decoration:none; font-size:.85rem; }
        .adm-header a:hover { color:#fff; }
        .adm-body { max-width:960px; margin:32px auto; padding:0 16px; }
        .loja-card { background:#fff; border-radius:12px; border:1px solid #e0e0e0;
                     padding:20px 24px; margin-bottom:16px;
                     display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
        .loja-card.inativa { opacity:.55; }
        .loja-num { background:#e8f5e9; color:#1b5e20; border-radius:50%;
                    width:36px; height:36px; display:flex; align-items:center;
                    justify-content:center; font-weight:800; font-size:1rem; flex-shrink:0; }
        .loja-info { flex:1; min-width:200px; }
        .loja-nome { font-weight:700; font-size:1rem; color:#1b1b1b; margin:0 0 2px; }
        .loja-end  { font-size:.82rem; color:#666; margin:0; }
        .loja-tags { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; }
        .loja-tag  { background:#f1f8e9; color:#388e3c; border-radius:20px;
                     padding:2px 10px; font-size:.75rem; }
        .loja-acoes { display:flex; gap:8px; flex-shrink:0; }
        .badge-ativa   { background:#e8f5e9; color:#2e7d32; }
        .badge-inativa { background:#ffebee; color:#c62828; }
        .empty-state { text-align:center; padding:60px 20px; color:#999; }
    </style>
</head>
<body>

<div class="adm-header">
    <span style="font-size:1.4rem;">🏪</span>
    <div>
        <div style="font-size:1.1rem;font-weight:700;">Gestão de Lojas</div>
        <div style="font-size:.8rem;opacity:.75;">Exclusivo para o Dono do sistema</div>
    </div>
    <div class="ms-auto d-flex gap-3 align-items-center">
        <a href="<?= BASE_PATH ?>/admin/usuarios">👥 Usuários</a>
        <a href="<?= BASE_PATH ?>/dashboard">← Painel</a>
    </div>
</div>

<div class="adm-body">

    <div id="alerta" class="alert" style="display:none;"></div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0 fw-bold">Filiais cadastradas</h5>
            <small class="text-muted">Gerencie as lojas da rede <?= NOME_SISTEMA ?></small>
        </div>
        <button class="btn btn-success" onclick="abrirModal()">
            ➕ Nova Loja
        </button>
    </div>

    <div id="lista-lojas">
        <div class="text-center py-5"><div class="spinner-border text-success"></div></div>
    </div>

</div>

<!-- ── Modal criar / editar ───────────────────────────────────────────────── -->
<div class="modal fade" id="modalLoja" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="modal-titulo">Nova Loja</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="loja-id">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Nome da Loja *</label>
                        <input type="text" id="loja-nome" class="form-control"
                               placeholder="Desffrut — Loja 4">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Telefone</label>
                        <input type="text" id="loja-tel" class="form-control"
                               placeholder="(00) 99999-0000">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Endereço completo</label>
                        <input type="text" id="loja-end" class="form-control"
                               placeholder="Rua, número — Bairro, Cidade">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Horário de Funcionamento</label>
                        <input type="text" id="loja-horario" class="form-control"
                               placeholder="Seg–Sáb 07h–19h | Dom 07h–13h">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Link WhatsApp Business</label>
                        <input type="url" id="loja-whats" class="form-control"
                               placeholder="https://wa.me/5500999990000">
                        <div class="form-text">Formato: https://wa.me/55DDD9XXXXXXXX</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" onclick="salvarLoja()">
                    💾 Salvar Loja
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
const API = '<?= BASE_PATH ?>/api/v1/lojas';
let modalBS;

document.addEventListener('DOMContentLoaded', () => {
    modalBS = new bootstrap.Modal(document.getElementById('modalLoja'));
    carregarLojas();
});

async function carregarLojas() {
    try {
        const r = await fetch(API + '?todas=1');
        const j = await r.json();
        const lojas = Array.isArray(j.data) ? j.data : [];
        renderLojas(lojas);
    } catch(e) {
        document.getElementById('lista-lojas').innerHTML =
            '<div class="alert alert-danger">Erro ao carregar lojas.</div>';
    }
}

function renderLojas(lojas) {
    const el = document.getElementById('lista-lojas');
    if (!lojas.length) {
        el.innerHTML = '<div class="empty-state">🏪<br>Nenhuma loja cadastrada ainda.</div>';
        return;
    }
    el.innerHTML = lojas.map((l, i) => `
        <div class="loja-card ${l.ativo == 0 ? 'inativa' : ''}">
            <div class="loja-num">${i + 1}</div>
            <div class="loja-info">
                <p class="loja-nome">${esc(l.nome)}
                    <span class="badge rounded-pill ms-2 ${l.ativo == 1 ? 'badge-ativa' : 'badge-inativa'}">
                        ${l.ativo == 1 ? '● Ativa' : '● Inativa'}
                    </span>
                </p>
                <p class="loja-end">📍 ${esc(l.endereco || '—')}</p>
                <div class="loja-tags">
                    ${l.telefone   ? `<span class="loja-tag">📞 ${esc(l.telefone)}</span>` : ''}
                    ${l.horario_funcionamento ? `<span class="loja-tag">🕐 ${esc(l.horario_funcionamento)}</span>` : ''}
                    ${l.whatsapp_link ? `<span class="loja-tag"><a href="${esc(l.whatsapp_link)}" target="_blank" class="text-success text-decoration-none">💬 WhatsApp</a></span>` : ''}
                </div>
            </div>
            <div class="loja-acoes">
                <button class="btn btn-outline-primary btn-sm" onclick="editarLoja(${JSON.stringify(l).replace(/"/g,'&quot;')})">
                    ✏️ Editar
                </button>
                <button class="btn btn-sm ${l.ativo == 1 ? 'btn-outline-warning' : 'btn-outline-success'}"
                        onclick="toggleAtivo(${l.id}, ${l.ativo})">
                    ${l.ativo == 1 ? '⏸ Desativar' : '▶ Ativar'}
                </button>
                <button class="btn btn-outline-danger btn-sm"
                        onclick="excluirLoja(${l.id}, '${esc(l.nome)}')">
                    🗑
                </button>
            </div>
        </div>
    `).join('');
}

function abrirModal(loja = null) {
    document.getElementById('modal-titulo').textContent = loja ? 'Editar Loja' : 'Nova Loja';
    document.getElementById('loja-id').value     = loja?.id      || '';
    document.getElementById('loja-nome').value   = loja?.nome    || '';
    document.getElementById('loja-tel').value    = loja?.telefone || '';
    document.getElementById('loja-end').value    = loja?.endereco || '';
    document.getElementById('loja-horario').value = loja?.horario_funcionamento || '';
    document.getElementById('loja-whats').value  = loja?.whatsapp_link || '';
    modalBS.show();
    setTimeout(() => document.getElementById('loja-nome').focus(), 300);
}

function editarLoja(l) { abrirModal(l); }

async function salvarLoja() {
    const id     = document.getElementById('loja-id').value;
    const nome   = document.getElementById('loja-nome').value.trim();
    if (!nome) { alerta('Informe o nome da loja.', 'danger'); return; }

    const payload = {
        nome,
        telefone:             document.getElementById('loja-tel').value.trim(),
        endereco:             document.getElementById('loja-end').value.trim(),
        horario_funcionamento: document.getElementById('loja-horario').value.trim(),
        whatsapp_link:        document.getElementById('loja-whats').value.trim(),
    };

    const url    = id ? `${API}/${id}` : API;
    const method = id ? 'PUT' : 'POST';

    try {
        const r = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (j.status === 'ok') {
            modalBS.hide();
            alerta(j.message, 'success');
            carregarLojas();
        } else {
            alerta(j.message || 'Erro ao salvar.', 'danger');
        }
    } catch(e) { alerta('Erro de conexão.', 'danger'); }
}

async function toggleAtivo(id, ativoAtual) {
    const novoAtivo = ativoAtual == 1 ? 0 : 1;
    const msg = novoAtivo ? 'Ativar esta loja?' : 'Desativar esta loja? Funcionários vinculados não conseguirão abrir o PDV.';
    if (!confirm(msg)) return;
    try {
        const r = await fetch(`${API}/${id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ativo: novoAtivo }),
        });
        const j = await r.json();
        alerta(j.message, j.status === 'ok' ? 'success' : 'danger');
        carregarLojas();
    } catch(e) { alerta('Erro de conexão.', 'danger'); }
}

async function excluirLoja(id, nome) {
    if (!confirm(`Excluir permanentemente "${nome}"?\n\nSe houver funcionários ou pedidos vinculados, a exclusão será bloqueada.`)) return;
    try {
        const r = await fetch(`${API}/${id}`, { method: 'DELETE' });
        const j = await r.json();
        alerta(j.message, j.status === 'ok' ? 'success' : 'danger');
        if (j.status === 'ok') carregarLojas();
    } catch(e) { alerta('Erro de conexão.', 'danger'); }
}

function alerta(msg, tipo) {
    const el = document.getElementById('alerta');
    el.className = `alert alert-${tipo}`;
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 4000);
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
