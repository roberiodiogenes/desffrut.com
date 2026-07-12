/**
 * Desffrut Hardware — Background Service Worker (Chrome/Edge)
 *
 * Recebe mensagens do content script e as repassa ao host nativo Python
 * via chrome.runtime.sendNativeMessage.
 *
 * Host nativo: "desffrut_print_host"
 * Instalado via: extension/native-host/install_chrome.bat
 *
 * Comandos suportados:
 *   status        → Retorna versão do host, Python, impressoras
 *   list_printers → Lista impressoras instaladas no Windows
 *   print         → Envia job de impressão (cupom ESC/POS, etiqueta ZPL, inkjet)
 */

'use strict';

const HOST_NAME = 'desffrut_print_host';
const VERSAO    = '2.0.0';

// ── Listener principal ────────────────────────────────────────────────────
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {

    // Aceita apenas mensagens do content script desta extensão
    if (!sender.tab || message.source !== 'desffrut_content') return false;

    const { msgId, cmd, payload } = message;

    // Executa de forma assíncrona (obrigatório para sendNativeMessage)
    _handleCommand(cmd, payload)
        .then(result => sendResponse({ ok: true,  data: result, msgId }))
        .catch(err  => sendResponse({ ok: false, erro: err.message, msgId }));

    return true; // Mantém o canal aberto para resposta assíncrona
});

// ── Roteador de comandos ──────────────────────────────────────────────────
async function _handleCommand(cmd, payload) {
    switch (cmd) {

        case 'status':
            return _chamarHost({ cmd: 'status' });

        case 'list_printers':
            return _chamarHost({ cmd: 'list_printers' });

        case 'print':
            return _handlePrint(payload);

        case 'test':
            return _chamarHost({ cmd: 'test', tipo: payload.tipo || 'cupom' });

        default:
            throw new Error(`Comando desconhecido: ${cmd}`);
    }
}

// ── Impressão ─────────────────────────────────────────────────────────────
async function _handlePrint(payload) {
    const { tipo, impressora, ...resto } = payload;

    if (!impressora) throw new Error('Nome da impressora não informado.');

    const msg = { cmd: 'print', tipo, impressora, ...resto };

    switch (tipo) {
        case 'cupom':
            if (!msg.raw) throw new Error('Dados ESC/POS (raw) não informados.');
            break;
        case 'etiqueta':
            if (!msg.zpl) throw new Error('Dados ZPL não informados.');
            break;
        case 'inkjet':
            if (!msg.html) throw new Error('Conteúdo HTML não informado.');
            break;
        default:
            throw new Error(`Tipo de impressora desconhecido: ${tipo}`);
    }

    return _chamarHost(msg);
}

// ── Comunicação com o Host Nativo ─────────────────────────────────────────
function _chamarHost(mensagem) {
    return new Promise((resolve, reject) => {
        chrome.runtime.sendNativeMessage(HOST_NAME, mensagem, (response) => {
            if (chrome.runtime.lastError) {
                const erro = chrome.runtime.lastError.message || '';

                // Mensagem amigável para erros comuns
                if (erro.includes('not found') || erro.includes('Specified native messaging host not found')) {
                    reject(new Error(
                        'Host nativo não encontrado.\n' +
                        'Execute extension/native-host/install_chrome.bat como Administrador.'
                    ));
                } else if (erro.includes('Access to the native messaging host was disabled')) {
                    reject(new Error('Acesso ao host nativo foi desativado. Verifique as políticas do Chrome.'));
                } else {
                    reject(new Error('Erro no host nativo: ' + erro));
                }
                return;
            }

            if (!response) {
                reject(new Error('Host nativo não retornou resposta.'));
                return;
            }

            if (response.ok === false) {
                reject(new Error(response.erro || 'Erro no host nativo.'));
                return;
            }

            resolve(response);
        });
    });
}

// ── Ícone dinâmico conforme status do host ───────────────────────────────
async function _verificarHostStatus() {
    try {
        await _chamarHost({ cmd: 'status' });
        chrome.action.setBadgeText({ text: '' });
        chrome.action.setBadgeBackgroundColor({ color: '#22c55e' });
    } catch (_) {
        chrome.action.setBadgeText({ text: '!' });
        chrome.action.setBadgeBackgroundColor({ color: '#ef4444' });
    }
}

// Verifica status ao instalar/atualizar a extensão
chrome.runtime.onInstalled.addListener(() => {
    _verificarHostStatus();
});

// Verifica status ao iniciar o service worker
_verificarHostStatus();
