/**
 * Desffrut Hardware — Content Script (Chrome/Edge)
 *
 * Atua como bridge entre a página (hardware.js) e o background service worker.
 * A página envia mensagens via window.postMessage e este script as repassa
 * para chrome.runtime.sendMessage → background.js → host nativo Python.
 *
 * Protocolo:
 *   Página → Content:  { type: 'DESFFRUT_HW_REQUEST',  msgId, cmd, payload }
 *   Content → Background: chrome.runtime.sendMessage({ msgId, cmd, payload })
 *   Background → Content: { ok, data, erro, msgId }
 *   Content → Página:  { type: 'DESFFRUT_HW_RESPONSE', msgId, ok, data, erro }
 */

'use strict';

(function () {

    // Escuta mensagens da página
    window.addEventListener('message', async (event) => {

        // Aceita apenas mensagens da mesma janela
        if (event.source !== window) return;

        const msg = event.data;
        if (!msg || msg.type !== 'DESFFRUT_HW_REQUEST') return;

        const { msgId, cmd, payload } = msg;

        try {
            // Repassa ao background service worker
            const response = await chrome.runtime.sendMessage({
                source: 'desffrut_content',
                msgId,
                cmd,
                payload: payload || {},
            });

            // Devolve para a página
            window.postMessage({
                type:  'DESFFRUT_HW_RESPONSE',
                msgId,
                ok:    !!(response && response.ok !== false),
                data:  response?.data  || response || {},
                erro:  response?.erro  || null,
            }, '*');

        } catch (err) {
            // Erro na comunicação com o background (extensão não pronta, etc.)
            window.postMessage({
                type:  'DESFFRUT_HW_RESPONSE',
                msgId,
                ok:    false,
                data:  {},
                erro:  err?.message || 'Erro interno da extensão.',
            }, '*');
        }
    });

    // Sinaliza para a página que o content script está injetado
    window.postMessage({ type: 'DESFFRUT_HW_READY', versao: '2.0.0' }, '*');

})();
