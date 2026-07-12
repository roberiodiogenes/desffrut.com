<?php
/**
 * Desffrut — Admin: Gestão de Usuários + Permissões Granulares (Categorias 2 + 20)
 * Rota: /admin/usuarios
 */
$roles_permitidos = ['super_admin'];
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/permissoes.php';
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
    <title>Usuários — <?= NOME_SISTEMA ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        body { background:#f4f6fa; font-family:'Segoe UI',sans-serif; }
        .admin-header { background:#1b5e20; color:#fff; padding:14px 24px;
                        display:flex; align-items:center; gap:16px; }
        .admin-header a { color:rgba(255,255,255,.75); font-size:.85rem; text-decoration:none; }
        .admin-header a:hover { color:#fff; }
        .badge-role { font-size:.72rem; padding:3px 8px; border-radius:20px; }
        .badge-super_admin { background:#1b5e20; color:#fff; }
        .badge-dev_admin   { background:#4a148c; color:#fff; }
        .badge-gerente     { background:#e65100; color:#fff; }
        .badge-caixa       { background:#1565c0; color:#fff; }
        .badge-entregador  { background:#37474f; color:#fff; }
        .badge-rh_financeiro { background:#880e4f; color:#fff; }
        .badge-cliente     { background:#bdbdbd; color:#333; }
        .perm-row { display:flex; align-items:center; justify-content:space-between;
                    padding:7px 0; border-bottom:1px solid #f0f0f0; }
        .perm-row:last-child { border:none; }
        .perm-label { font-size:.88rem; }
        .perm-source { font-size:.72rem; color:#999; }
        .perm-source.excecao { color:#c62828; font-weight:600; }
        .perm-source.concedida-excecao { color:#1b5e20; font-weight:600; }
        .status-aberto  { color:#2e7d32; font-weight:600; }
        .status-inativo { color:#bdbdbd; }
    </style>
</head>
<body>

<div class="admin-header">
    <span style="font-size:1.4rem;">👥</span>
    <div>
        <div style="font-weight:700;">Usuários do Sistema</div>
        <div style="font-size:.78rem;opacity:.75;">Cadastro, roles e permissões granulares</div>
    </div>
    <div class="ms-auto d-flex gap-3 align-items-center">
        <a href="<?= BASE_PATH ?>/admin/lojas">🏪 Lojas</a>
        <a href="<?= BASE_PATH ?>/dashboard">← Painel</a>
    </div>
</div>

<div style="max-width:1100px;margin:24px auto;padding:0 16px;">

    <!-- Alertas -->
    <div id="alerta-container"></div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold">
            <span id="badge-total" class="badge bg-secondary rounded-pill me-2">…</span>
            usuários
        </h5>
        <button class="btn btn-success btn-sm" onclick="abrirModal()">
            ＋ Novo Usuário
        </button>
    </div>

    <div id="lista-usuarios" class="row g-3">
        <div class="col-12 text-center py-5 text-muted">Carregando…</div>
    </div>
</div>

<!-- ── Modal Criar/Editar Usuário ─────────────────────────────────────────── -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="modal-titulo">Novo Usuário</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="usr-id">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nome completo *</label>
            <input type="text" class="form-control" id="usr-nome">
          </div>
          <div class="col-md-6">
            <label class="form-label">E-mail *</label>
            <input type="email" class="form-control" id="usr-email">
          </div>
          <div class="col-md-4">
            <label class="form-label">Role *</label>
            <select class="form-select" id="usr-role">
              <option value="caixa">Caixa</option>
              <option value="gerente">Gerente</option>
              <option value="entregador">Entregador</option>
              <option value="rh_financeiro">RH / Financeiro</option>
              <option value="super_admin">Super Admin</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Loja</label>
            <select class="form-select" id="usr-loja">
              <option value="">Sem loja</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" id="senha-label">Senha *</label>
            <input type="password" class="form-control" id="usr-senha" placeholder="Mínimo 8 caracteres">
            <div class="form-text" id="senha-hint"></div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="usr-ativo" checked>
              <label class="form-check-label" for="usr-ativo">Usuário ativo</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" onclick="salvarUsuario()">💾 Salvar</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Permissões ───────────────────────────────────────────────────── -->
<div class="modal fade" id="modalPermissoes" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#1b5e20;color:#fff;">
        <h5 class="modal-title">🔐 Permissões — <span id="perm-nome-usuario"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
            Permissões em <strong>verde</strong> são padrão do role. Exceções individuais aparecem em destaque.
            O super_admin sempre tem todas as permissões independente desta tabela.
        </p>
        <div id="perm-lista">Carregando…</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button class="btn btn-success" onclick="salvarPermissoes()">💾 Salvar Permissões</button>
      </div>
    </div>
  </div>
</div>

<script>
const API = '<?= BASE_PATH ?>/api/v1';
let _lojas = [];
let _permEstado = {};   // {permissao: bool} — estado atual dos toggles
let _permUsuarioId = 0;

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([carregarLojas(), carregarUsuarios()]);
});

async function carregarLojas() {
    try {
        const r = await fetch(`${API}/lojas?todas=1`);
        const j = await r.json();
        _lojas = j.data || [];
        const sel = document.getElementById('usr-loja');
        _lojas.forEach(l => {
            const o = document.createElement('option');
            o.value = l.id; o.textContent = l.nome;
            sel.appendChild(o);
        });
    } catch(_) {}
}

async function carregarUsuarios() {
    try {
        const r = await fetch(`${API}/usuarios`);
        const j = await r.json();
        renderUsuarios(j.data || []);
    } catch(e) {
        document.getElementById('lista-usuarios').innerHTML =
            '<div class="col-12 text-danger">Erro ao carregar usuários.</div>';
    }
}

function renderUsuarios(lista) {
    document.getElementById('badge-total').textContent = lista.length;
    const el = document.getElementById('lista-usuarios');
    if (!lista.length) {
        el.innerHTML = '<div class="col-12 text-muted text-center py-5">Nenhum usuário cadastrado.</div>';
        return;
    }
    el.innerHTML = lista.map(u => `
    <div class="col-md-6 col-xl-4">
      <div class="card h-100 shadow-sm" style="border-left:4px solid #2e7d32;">
        <div class="card-body pb-2">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div style="font-weight:700;font-size:.95rem;">${esc(u.nome)}</div>
            <span class="badge-role badge-${u.role}">${u.role.replace('_',' ')}</span>
          </div>
          <div style="font-size:.82rem;color:#555;">${esc(u.email)}</div>
          <div style="font-size:.8rem;color:#888;margin-top:4px;">
            ${lojaLabel(u.loja_id)} ·
            <span class="${u.ativo ? 'status-aberto' : 'status-inativo'}">${u.ativo ? 'Ativo' : 'Inativo'}</span>
          </div>
        </div>
        <div class="card-footer bg-transparent pt-0 pb-2">
          <div class="d-flex gap-1 flex-wrap">
            <button class="btn btn-sm btn-outline-success" onclick="editarUsuario(${u.id})">✏️ Editar</button>
            <button class="btn btn-sm btn-outline-primary" onclick="abrirPermissoes(${u.id}, '${esc(u.nome)}')">🔐 Permissões</button>
            <button class="btn btn-sm btn-outline-warning" onclick="resetarSenha(${u.id}, '${esc(u.nome)}')">🔑 Senha</button>
          </div>
        </div>
      </div>
    </div>`).join('');
}

function lojaLabel(id) {
    if (!id) return '— sem loja';
    const l = _lojas.find(x => x.id == id);
    return l ? `🏪 ${l.nome}` : `Loja #${id}`;
}

// ── Modal Criar/Editar ────────────────────────────────────────────────────────
function abrirModal(usr = null) {
    document.getElementById('usr-id').value    = usr ? usr.id : '';
    document.getElementById('usr-nome').value  = usr ? usr.nome : '';
    document.getElementById('usr-email').value = usr ? usr.email : '';
    document.getElementById('usr-role').value  = usr ? usr.role : 'caixa';
    document.getElementById('usr-loja').value  = usr ? (usr.loja_id || '') : '';
    document.getElementById('usr-ativo').checked = usr ? !!usr.ativo : true;
    document.getElementById('usr-senha').value = '';
    document.getElementById('modal-titulo').textContent = usr ? 'Editar Usuário' : 'Novo Usuário';
    document.getElementById('senha-label').textContent  = usr ? 'Nova Senha (opcional)' : 'Senha *';
    document.getElementById('senha-hint').textContent   = usr ? 'Deixe em branco para não alterar.' : '';
    new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}

async function editarUsuario(id) {
    try {
        const r = await fetch(`${API}/usuarios/${id}`);
        const j = await r.json();
        abrirModal(j.data || j);
    } catch(_) { alerta('Erro ao carregar dados do usuário.', 'danger'); }
}

async function salvarUsuario() {
    const id    = document.getElementById('usr-id').value;
    const nome  = document.getElementById('usr-nome').value.trim();
    const email = document.getElementById('usr-email').value.trim();
    const role  = document.getElementById('usr-role').value;
    const loja  = document.getElementById('usr-loja').value;
    const ativo = document.getElementById('usr-ativo').checked ? 1 : 0;
    const senha = document.getElementById('usr-senha').value;

    if (!nome || !email || (!id && !senha)) {
        alerta('Preencha nome, e-mail e senha (obrigatória no cadastro).', 'warning'); return;
    }
    const body = { nome, email, role, loja_id: loja || null, ativo };
    if (senha) body.senha = senha;

    const method = id ? 'PUT' : 'POST';
    const url    = id ? `${API}/usuarios/${id}` : `${API}/usuarios`;
    const r = await fetch(url, { method, headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
    const j = await r.json();
    if (r.ok) {
        bootstrap.Modal.getInstance(document.getElementById('modalUsuario')).hide();
        alerta(`Usuário ${id ? 'atualizado' : 'criado'} com sucesso!`, 'success');
        await carregarUsuarios();
    } else {
        alerta(j.erro || j.message || 'Erro ao salvar.', 'danger');
    }
}

// ── Modal Permissões ──────────────────────────────────────────────────────────
async function abrirPermissoes(uid, nome) {
    _permUsuarioId = uid;
    document.getElementById('perm-nome-usuario').textContent = nome;
    document.getElementById('perm-lista').innerHTML = 'Carregando…';
    const modal = new bootstrap.Modal(document.getElementById('modalPermissoes'));
    modal.show();

    try {
        const r = await fetch(`${API}/permissoes/${uid}`);
        const texto = await r.text();
        let j;
        try {
            j = JSON.parse(texto);
        } catch (_) {
            throw new Error(`Resposta inválida (HTTP ${r.status}): ${texto.slice(0, 200)}`);
        }
        if (!r.ok) {
            throw new Error(j.erro || j.message || `Erro HTTP ${r.status}`);
        }
        renderPermissoes(j.permissoes || []);
    } catch (e) {
        console.error('Erro ao carregar permissões:', e);
        document.getElementById('perm-lista').innerHTML =
            `<div class="text-danger">Erro ao carregar permissões.<br><small>${esc(e.message || '')}</small></div>`;
    }
}

function renderPermissoes(lista) {
    _permEstado = {};
    lista.forEach(p => { _permEstado[p.permissao] = p.efetiva; });

    document.getElementById('perm-lista').innerHTML = lista.map(p => {
        const isExcecao = p.tem_excecao;
        const srcLabel  = isExcecao
            ? (p.efetiva ? '<span class="perm-source concedida-excecao">● exceção: concedida</span>'
                         : '<span class="perm-source excecao">● exceção: revogada</span>')
            : '<span class="perm-source">padrão do role</span>';
        return `
        <div class="perm-row">
          <div>
            <div class="perm-label">${esc(p.descricao)}</div>
            <div>${srcLabel}</div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="perm-${p.permissao}" data-perm="${p.permissao}"
                   ${p.efetiva ? 'checked' : ''}
                   onchange="_permEstado['${p.permissao}']=this.checked">
          </div>
        </div>`;
    }).join('');
}

async function salvarPermissoes() {
    const r = await fetch(`${API}/permissoes/${_permUsuarioId}`, {
        method: 'PUT',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(_permEstado),
    });
    const j = await r.json();
    if (r.ok) {
        bootstrap.Modal.getInstance(document.getElementById('modalPermissoes')).hide();
        alerta('Permissões salvas!', 'success');
        // Recarrega para refletir exceções salvas
        setTimeout(() => abrirPermissoes(_permUsuarioId, document.getElementById('perm-nome-usuario').textContent), 500);
    } else {
        alerta(j.erro || 'Erro ao salvar permissões.', 'danger');
    }
}

// ── Resetar senha ─────────────────────────────────────────────────────────────
async function resetarSenha(uid, nome) {
    const nova = prompt(`Nova senha para "${nome}" (mín. 8 caracteres):`);
    if (!nova) return;
    if (nova.length < 8) { alerta('Senha muito curta.', 'warning'); return; }
    const r = await fetch(`${API}/usuarios/${uid}`, {
        method: 'PUT',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ senha: nova }),
    });
    const j = await r.json();
    alerta(r.ok ? 'Senha alterada com sucesso!' : (j.erro || 'Erro.'), r.ok ? 'success' : 'danger');
}

// ── Utilitários ───────────────────────────────────────────────────────────────
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function alerta(msg, tipo = 'success') {
    const id  = 'alerta-' + Date.now();
    const el  = document.getElementById('alerta-container');
    el.insertAdjacentHTML('beforeend',
        `<div id="${id}" class="alert alert-${tipo} alert-dismissible fade show mb-2" role="alert">
            ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
    setTimeout(() => { const a = document.getElementById(id); if(a) a.remove(); }, 5000);
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
