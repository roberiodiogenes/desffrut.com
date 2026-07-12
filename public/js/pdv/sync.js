/**
 * Desffrut — Background Sync do PDV
 * Fase 4: detecta reconexão e envia cupons pendentes para /api/v1/sync/upload.
 *
 * Política de conflito (Seção 3.3 do briefing):
 *   - Preço snapshot do cupom prevalece (imutável).
 *   - Estoque ajustado pela quantidade vendida.
 *   - Estoque negativo → alerta no painel do gerente, não bloqueia o sync.
 *
 * Depende de: indexeddb.js (DesffrDatabase), APP.api, APP.token
 */

'use strict';

const DesffrSync = (function () {

    let _sincronizando = false;
    let _intervalo     = null;
    const INTERVALO_MS = 30_000; // verifica a cada 30s

    // ── Carrega snapshot do dia (carga inicial) ───────────────────────────────
    async function carregarCarga(loja_id) {
        const token = APP.token || sessionStorage.getItem('desffrut_token') || '';
        const resp  = await fetch(`${APP.api}/sync/carga?loja_id=${loja_id}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!resp.ok) throw new Error('Falha ao carregar carga: HTTP ' + resp.status);

        const json = await resp.json();
        if (json.status !== 'ok') throw new Error(json.message || 'Erro na carga');

        const { produtos, clientes } = json.data;
        await DesffrDatabase.salvarProdutos(produtos);
        await DesffrDatabase.salvarClientes(clientes);

        return { produtos: produtos.length, clientes: clientes.length };
    }

    // ── Sincroniza cupons offline pendentes ───────────────────────────────────
    async function sincronizar() {
        if (_sincronizando || !navigator.onLine) return;
        _sincronizando = true;
        _emitirEvento('sync:inicio');

        try {
            const pendentes = await DesffrDatabase.listarPendentes();
            if (pendentes.length === 0) {
                _sincronizando = false;
                _emitirEvento('sync:nada');
                return;
            }

            const token = APP.token || sessionStorage.getItem('desffrut_token') || '';
            const resp  = await fetch(`${APP.api}/sync/upload`, {
                method:  'POST',
                headers: {
                    'Content-Type':  'application/json',
                    'Authorization': 'Bearer ' + token,
                },
                body: JSON.stringify({ cupons: pendentes }),
            });

            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const json = await resp.json();

            if (json.status !== 'ok') throw new Error(json.message);

            // Marca sincronizados localmente
            const resultados = json.data?.resultados || [];
            for (const r of resultados) {
                if (r.status === 'sincronizado' || r.status === 'ignorado') {
                    await DesffrDatabase.marcarSincronizado(r.uuid, r.venda_id);
                }
                // Emite alertas de estoque negativo
                if (r.alertas_estoque && r.alertas_estoque.length > 0) {
                    _emitirEvento('sync:alerta_estoque', { produtos: r.alertas_estoque, uuid: r.uuid });
                }
            }

            _emitirEvento('sync:concluido', { total: pendentes.length, sincronizados: json.data?.sincronizados });

        } catch (err) {
            _emitirEvento('sync:erro', { mensagem: err.message });
        } finally {
            _sincronizando = false;
        }
    }

    // ── Inicia monitoramento automático ───────────────────────────────────────
    function iniciar() {
        if (_intervalo) return; // já rodando

        // Tenta ao reconectar
        window.addEventListener('online', () => {
            _emitirEvento('sync:reconectado');
            sincronizar();
        });

        // Polling periódico
        _intervalo = setInterval(() => {
            if (navigator.onLine) sincronizar();
        }, INTERVALO_MS);

        // Primeira tentativa imediata
        if (navigator.onLine) sincronizar();
    }

    function parar() {
        if (_intervalo) { clearInterval(_intervalo); _intervalo = null; }
    }

    // ── Status ────────────────────────────────────────────────────────────────
    function isOnline()       { return navigator.onLine; }
    function isSincronizando() { return _sincronizando; }

    // ── Eventos customizados ──────────────────────────────────────────────────
    function _emitirEvento(tipo, detalhe = {}) {
        document.dispatchEvent(new CustomEvent('desffrut:' + tipo, { detail: detalhe }));
    }

    // API pública
    return { carregarCarga, sincronizar, iniciar, parar, isOnline, isSincronizando };

})();
