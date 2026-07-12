/**
 * Desffrut — Polling de Status de Pedido
 * Fase 6: rastreamento de entrega sem WebSocket.
 *
 * Intervalo: POLLING_INTERVALO_MS (definido em config.php → passado para JS via meta tag ou inline).
 * Endpoint: GET /api/v1/pedidos/{id}
 *
 * Uso:
 *   iniciarPolling(pedidoId, callbackAtualizacao);
 *   pararPolling();
 */

let _pollingTimer = null;

/**
 * Inicia o polling para um pedido específico.
 * @param {number} pedidoId
 * @param {function} onAtualizar - chamada com o objeto status recebido
 * @param {number} intervaloMs   - padrão 20000 ms
 */
function iniciarPolling(pedidoId, onAtualizar, intervaloMs = 20000) {
    pararPolling();

    async function verificar() {
        try {
            const resp = await fetch((window.API_BASE || '/api/v1') + `/pedidos/${pedidoId}`, {
                headers: { 'Authorization': 'Bearer ' + obterToken() }
            });
            if (resp.ok) {
                const json = await resp.json();
                if (json.status === 'ok') onAtualizar(json.data);
            }
        } catch (e) {
            // Silencia erros de rede; o polling continua na próxima iteração
        }
    }

    verificar(); // executa imediatamente
    _pollingTimer = setInterval(verificar, intervaloMs);
}

function pararPolling() {
    if (_pollingTimer) {
        clearInterval(_pollingTimer);
        _pollingTimer = null;
    }
}

/** Obtém o token da sessão armazenado após o login. Fase 1 define obterToken(). */
function obterToken() {
    return sessionStorage.getItem('desffrut_token') || '';
}
