<?php
/**
 * Desffrut — Fragmento Dashboard: Marketing & AdTech (Fase 12)
 *
 * Abas: Pixels & Tags | ROI por Campanha | Criar Anúncio | Indique e Ganhe
 * Roles: super_admin (todas as abas) | gerente (ROI somente)
 */
$role = $_SESSION['usuario']['role'] ?? '';
?>
<style data-frag="adtech">
.adt-tab-content { padding: 16px 0; }
.adt-card {
    background: #fff; border: 1px solid #e0e0e0; border-radius: 10px;
    padding: 20px; margin-bottom: 16px;
}
.adt-card h5 { font-size: 1rem; font-weight: 700; margin-bottom: 12px; }
.adt-form-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 12px; }
.adt-form-row .form-group { flex: 1; min-width: 180px; }
.adt-badge-pixel {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: .78rem; font-weight: 600;
}
.adt-badge-pixel.ativo   { background: #e8f5e9; color: #2e7d32; }
.adt-badge-pixel.inativo { background: #fbe9e7; color: #b71c1c; }

/* ROI table */
.roi-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
.roi-table th { background: #f5f5f5; font-weight: 600; padding: 8px 10px; text-align: left; }
.roi-table td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; }
.roi-table tr:last-child td { border-bottom: none; }
.roi-bar { height: 6px; border-radius: 3px; background: #2e7d32; display: block; }

/* UTM builder */
.utm-preview {
    background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px;
    padding: 12px 14px; font-size: .82rem; word-break: break-all;
    font-family: monospace; color: #333; margin-top: 12px;
}

/* Indicação */
.ind-card {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; background: #f9f9f9; border-radius: 8px;
    margin-bottom: 8px;
}
.ind-rank { font-size: 1.2rem; font-weight: 800; color: #2e7d32; min-width: 28px; }
.ind-info { flex: 1; }
.ind-info strong { display: block; }
.ind-info small { color: #777; }
.ind-pts { font-weight: 700; color: #2e7d32; }
.codigo-box {
    background: #e8f5e9; border: 1.5px dashed #2e7d32; border-radius: 10px;
    padding: 16px 20px; text-align: center; margin-bottom: 16px;
}
.codigo-box .codigo { font-size: 2rem; font-weight: 800; letter-spacing: 6px; color: #1b5e20; }
.codigo-box .link-ind { font-size: .82rem; color: #555; margin-top: 6px; word-break: break-all; }
</style>

<!-- Abas -->
<ul class="nav nav-tabs mb-3" id="adt-tabs">
    <?php if ($role === 'super_admin'): ?>
    <li class="nav-item">
        <button class="nav-link active" data-adt-tab="pixels">📡 Pixels & Tags</button>
    </li>
    <?php endif; ?>
    <li class="nav-item">
        <button class="nav-link <?= $role !== 'super_admin' ? 'active' : '' ?>" data-adt-tab="roi">📊 ROI por Campanha</button>
    </li>
    <?php if ($role === 'super_admin'): ?>
    <li class="nav-item">
        <button class="nav-link" data-adt-tab="anuncio">📣 Criar Anúncio</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-adt-tab="indicacao">🎁 Indique e Ganhe</button>
    </li>
    <?php endif; ?>
</ul>

<div id="adt-conteudo"></div>

<script>
(function() {
    const API    = APP.api;
    let _tabAtual = <?= $role === 'super_admin' ? "'pixels'" : "'roi'" ?>;

    // ── Tabs ────────────────────────────────────────────────────────────────────
    document.querySelectorAll('[data-adt-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-adt-tab]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            _tabAtual = btn.dataset.adtTab;
            renderTab();
        });
    });

    function renderTab() {
        const el = document.getElementById('adt-conteudo');
        el.innerHTML = '<div class="text-muted py-3 text-center">Carregando…</div>';
        if      (_tabAtual === 'pixels')   renderPixels();
        else if (_tabAtual === 'roi')      renderROI();
        else if (_tabAtual === 'anuncio')  renderAnuncio();
        else if (_tabAtual === 'indicacao') renderIndicacao();
    }

    // ── Pixels & Tags ───────────────────────────────────────────────────────────
    async function renderPixels() {
        const el = document.getElementById('adt-conteudo');
        el.innerHTML = `
        <div class="adt-card">
            <h5>📡 Pixels & Rastreamento</h5>
            <p class="text-muted small mb-3">
                Configure os IDs dos pixels de rastreamento. As tags são injetadas automaticamente em todas as páginas.
                Deixe em branco para desativar.
            </p>
            <div class="mb-3">
                <label class="form-label fw-semibold">Meta Pixel ID</label>
                <div class="d-flex gap-2">
                    <input type="text" id="inp-pixel" class="form-control" placeholder="Ex: 1234567890123456"
                           value="" style="max-width:280px;">
                    <span id="badge-pixel" class="adt-badge-pixel inativo align-self-center">Inativo</span>
                </div>
                <div class="form-text">Encontre em: Meta Business Suite → Pixels de Dados</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Google Analytics 4 — Measurement ID</label>
                <div class="d-flex gap-2">
                    <input type="text" id="inp-gtag" class="form-control" placeholder="Ex: G-XXXXXXXXXX"
                           value="" style="max-width:280px;">
                    <span id="badge-gtag" class="adt-badge-pixel inativo align-self-center">Inativo</span>
                </div>
                <div class="form-text">Encontre em: Google Analytics → Admin → Fluxos de Dados</div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Pontos por indicação (programa "Indique e Ganhe")</label>
                <input type="number" id="inp-pts-ind" class="form-control" min="1" max="9999" value="100"
                       style="max-width:120px;">
                <div class="form-text">Pontos creditados ao indicador após o 1º pedido entregue do indicado.</div>
            </div>
            <button class="btn btn-success" id="btn-salvar-config">💾 Salvar configurações</button>
            <div id="adt-alerta" class="mt-2"></div>
        </div>`;

        // Carrega valores atuais
        try {
            const r = await fetch(API + '/configuracoes');
            const j = await r.json();
            if (j.status === 'ok') {
                const cfg = j.data || {};
                const pixelId = cfg['pixel_meta_id'] || '';
                const gtagId  = cfg['gtag_id']       || '';
                const pts     = cfg['pontos_indicacao'] || '100';
                document.getElementById('inp-pixel').value  = pixelId;
                document.getElementById('inp-gtag').value   = gtagId;
                document.getElementById('inp-pts-ind').value = pts;
                _setBadge('badge-pixel', !!pixelId);
                _setBadge('badge-gtag',  !!gtagId);
            }
        } catch (_) {}

        ['inp-pixel','inp-gtag'].forEach((id, i) => {
            document.getElementById(id).addEventListener('input', e => {
                _setBadge(i === 0 ? 'badge-pixel' : 'badge-gtag', !!e.target.value.trim());
            });
        });

        document.getElementById('btn-salvar-config').addEventListener('click', async () => {
            const payload = {
                pixel_meta_id:    document.getElementById('inp-pixel').value.trim(),
                gtag_id:          document.getElementById('inp-gtag').value.trim(),
                pontos_indicacao: document.getElementById('inp-pts-ind').value.trim(),
            };
            const r = await fetch(API + '/adtech/config', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify(payload),
            });
            const j = await r.json();
            const al = document.getElementById('adt-alerta');
            al.className = `alert alert-${j.status === 'ok' ? 'success' : 'danger'} py-2`;
            al.textContent = j.status === 'ok' ? '✅ Salvo! Recarregue a página para ativar os pixels.' : j.message;
        });
    }

    function _setBadge(id, ativo) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = ativo ? 'Ativo' : 'Inativo';
        el.className   = `adt-badge-pixel ${ativo ? 'ativo' : 'inativo'}`;
    }

    // ── ROI por Campanha ────────────────────────────────────────────────────────
    async function renderROI() {
        const el = document.getElementById('adt-conteudo');
        const hoje  = new Date().toISOString().slice(0,10);
        const mes01 = hoje.slice(0,7) + '-01';
        el.innerHTML = `
        <div class="adt-card">
            <div class="adt-form-row">
                <div class="form-group">
                    <label class="form-label fw-semibold">De</label>
                    <input type="date" id="roi-inicio" class="form-control" value="${mes01}">
                </div>
                <div class="form-group">
                    <label class="form-label fw-semibold">Até</label>
                    <input type="date" id="roi-fim" class="form-control" value="${hoje}">
                </div>
                <div class="form-group" style="flex:0">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-success d-block" id="btn-roi-buscar">🔍 Buscar</button>
                </div>
            </div>
            <div id="roi-resultado">
                <p class="text-muted small">Clique em Buscar para carregar os dados de UTM.</p>
            </div>
        </div>`;

        async function buscarROI() {
            const inicio = document.getElementById('roi-inicio').value;
            const fim    = document.getElementById('roi-fim').value;
            const res    = document.getElementById('roi-resultado');
            res.innerHTML = '<div class="text-muted small">Carregando…</div>';
            try {
                const r = await fetch(`${API}/adtech/roi?inicio=${inicio}&fim=${fim}`);
                const j = await r.json();
                if (!j.data || !j.data.length) {
                    res.innerHTML = '<p class="text-muted small mt-2">Nenhum dado com UTM encontrado no período.</p>';
                    return;
                }
                const maxRec = Math.max(...j.data.map(d => parseFloat(d.receita_total)));
                const linhas = j.data.map(d => {
                    const pct   = maxRec > 0 ? Math.round(parseFloat(d.receita_total)/maxRec*100) : 0;
                    const rec   = parseFloat(d.receita_total).toFixed(2).replace('.',',');
                    const ticket = parseFloat(d.ticket_medio).toFixed(2).replace('.',',');
                    return `<tr>
                        <td>${d.utm_source}</td>
                        <td>${d.utm_medium}</td>
                        <td>${d.utm_campaign}</td>
                        <td>${d.total_pedidos}</td>
                        <td>
                            <span class="roi-bar" style="width:${pct}%;min-width:4px;max-width:120px;"></span>
                            R$ ${rec}
                        </td>
                        <td>R$ ${ticket}</td>
                    </tr>`;
                }).join('');
                res.innerHTML = `
                <div style="overflow-x:auto;">
                <table class="roi-table">
                    <thead><tr>
                        <th>Fonte</th><th>Meio</th><th>Campanha</th>
                        <th>Pedidos</th><th>Receita</th><th>Ticket Médio</th>
                    </tr></thead>
                    <tbody>${linhas}</tbody>
                </table>
                </div>`;
            } catch (e) {
                res.innerHTML = `<div class="alert alert-danger small">${e.message}</div>`;
            }
        }

        document.getElementById('btn-roi-buscar').addEventListener('click', buscarROI);
        buscarROI();
    }

    // ── Criar Anúncio (UTM Builder + Deep Link Meta) ────────────────────────────
    function renderAnuncio() {
        const el = document.getElementById('adt-conteudo');
        const urlBase = window.location.origin + APP.base + '/';
        el.innerHTML = `
        <div class="adt-card">
            <h5>📣 Gerador de Link com UTM</h5>
            <p class="text-muted small mb-3">
                Crie links rastreáveis para suas campanhas. Cole o link gerado no anúncio do Meta/Google.
            </p>
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">URL de destino</label>
                    <input type="url" id="utm-url" class="form-control" value="${urlBase}" placeholder="https://…">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Fonte (utm_source) *</label>
                    <input type="text" id="utm-source" class="form-control" placeholder="ex: facebook, google, instagram">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Meio (utm_medium)</label>
                    <input type="text" id="utm-medium" class="form-control" placeholder="cpc, stories, email…">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Campanha (utm_campaign)</label>
                    <input type="text" id="utm-campaign" class="form-control" placeholder="nome-da-campanha">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Conteúdo (utm_content)</label>
                    <input type="text" id="utm-content" class="form-control" placeholder="criativo-A, banner-frutas…">
                </div>
            </div>
            <button class="btn btn-success btn-sm" id="btn-gerar-utm">🔗 Gerar link</button>
            <div id="utm-preview" class="utm-preview" style="display:none;"></div>
            <button class="btn btn-outline-secondary btn-sm mt-2" id="btn-copiar-utm" style="display:none;">📋 Copiar link</button>
        </div>

        <div class="adt-card">
            <h5>📢 Criar anúncio no Meta Business</h5>
            <p class="text-muted small">
                Preencha os campos acima para gerar o link UTM, depois clique no botão abaixo
                para abrir o Gerenciador de Anúncios do Meta com os parâmetros pré-configurados.
            </p>
            <a id="btn-meta-ads"
               href="https://business.facebook.com/adsmanager/creation/create"
               target="_blank" class="btn btn-primary">
                📘 Abrir Meta Ads Manager →
            </a>
            <div class="form-text mt-2">
                Será necessário fazer login no Meta Business Suite. O link UTM gerado acima deve ser usado como URL de destino do anúncio.
            </div>
        </div>`;

        function gerarUTM() {
            const base   = document.getElementById('utm-url').value.trim();
            const source = document.getElementById('utm-source').value.trim();
            if (!base || !source) return;
            const params = new URLSearchParams();
            params.set('utm_source',  source);
            ['medium','campaign','content'].forEach(k => {
                const v = document.getElementById('utm-' + k).value.trim();
                if (v) params.set('utm_' + k, v);
            });
            const url = base + (base.includes('?') ? '&' : '?') + params.toString();
            const prev = document.getElementById('utm-preview');
            const copy = document.getElementById('btn-copiar-utm');
            prev.textContent = url;
            prev.style.display = '';
            copy.style.display  = '';
            copy.dataset.url    = url;
        }

        document.getElementById('btn-gerar-utm').addEventListener('click', gerarUTM);
        document.getElementById('btn-copiar-utm').addEventListener('click', e => {
            navigator.clipboard.writeText(e.currentTarget.dataset.url).then(() => {
                e.currentTarget.textContent = '✅ Copiado!';
                setTimeout(() => { e.currentTarget.textContent = '📋 Copiar link'; }, 2000);
            });
        });
    }

    // ── Indique e Ganhe ─────────────────────────────────────────────────────────
    async function renderIndicacao() {
        const el = document.getElementById('adt-conteudo');
        el.innerHTML = '<div class="text-muted py-3 text-center">Carregando ranking…</div>';

        try {
            const r = await fetch(API + '/adtech/indicacoes');
            const j = await r.json();
            if (!j.data || !j.data.length) {
                el.innerHTML = '<div class="adt-card"><p class="text-muted">Nenhum cliente com código de indicação ainda.</p></div>';
                return;
            }
            const linhas = j.data.slice(0,20).map((ind, i) => {
                const emoji = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : (i+1) + '.';
                return `<div class="ind-card">
                    <div class="ind-rank">${emoji}</div>
                    <div class="ind-info">
                        <strong>${_esc(ind.nome)}</strong>
                        <small>${_esc(ind.email)} · Código: <code>${_esc(ind.codigo_indicacao)}</code></small>
                    </div>
                    <div class="ind-pts">
                        ${ind.total_indicados} indicado${ind.total_indicados != 1 ? 's' : ''}
                        <br><small class="text-muted">${ind.bonus_pagos} bônus pagos</small>
                    </div>
                </div>`;
            }).join('');

            el.innerHTML = `
            <div class="adt-card">
                <h5>🏆 Ranking de Indicadores</h5>
                <p class="text-muted small mb-3">
                    Clientes que mais indicaram novos usuários. O bônus é creditado após o 1º pedido entregue do indicado.
                </p>
                ${linhas}
            </div>`;
        } catch (e) {
            el.innerHTML = `<div class="alert alert-danger m-3">${e.message}</div>`;
        }
    }

    function _esc(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    renderTab();

})();
</script>
