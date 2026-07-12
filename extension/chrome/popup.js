'use strict';

const HOST_NAME = 'desffrut_print_host';

async function verificarHost() {
    setStatus('spin', 'Verificando host…', 'Conectando…');

    try {
        const resp = await sendToHost({ cmd: 'status' });
        setStatus('ok', 'Host nativo ativo ✓', `Python ${resp.python_version || ''} · v${resp.versao || '2.0'}`);
        renderPrinters();
    } catch (e) {
        setStatus('err', 'Host não encontrado', 'Execute install_chrome.bat como Administrador');
        document.getElementById('printers-section').style.display = 'none';
        document.getElementById('no-config').style.display = 'none';
    }
}

function setStatus(tipo, titulo, sub) {
    const dot  = document.getElementById('dot-host');
    const lbl  = document.getElementById('lbl-host');
    const ssub = document.getElementById('sub-host');
    dot.className = `dot ${tipo}`;
    lbl.textContent  = titulo;
    ssub.textContent = sub;
}

function sendToHost(msg) {
    return new Promise((resolve, reject) => {
        chrome.runtime.sendNativeMessage(HOST_NAME, msg, (response) => {
            if (chrome.runtime.lastError) {
                reject(new Error(chrome.runtime.lastError.message));
                return;
            }
            if (!response || response.ok === false) {
                reject(new Error(response?.erro || 'Resposta inválida'));
                return;
            }
            resolve(response);
        });
    });
}

function renderPrinters() {
    // Lê configuração salva no localStorage da aba ativa do Desffrut
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        if (!tabs[0]) return;

        chrome.scripting.executeScript({
            target: { tabId: tabs[0].id },
            func: () => {
                try {
                    const raw = localStorage.getItem('desffrut_hw_v2');
                    return raw ? JSON.parse(raw) : null;
                } catch (_) { return null; }
            },
        }, (results) => {
            const cfg = results?.[0]?.result;
            _mostrarImpressoras(cfg?.impressoras || null);
        });
    });
}

function _mostrarImpressoras(imp) {
    const section = document.getElementById('printers-section');
    const list    = document.getElementById('printers-list');
    const noConf  = document.getElementById('no-config');

    if (!imp || (!imp.cupom?.nome && !imp.etiqueta?.nome && !imp.inkjet?.nome)) {
        section.style.display = 'none';
        noConf.style.display  = '';
        return;
    }

    noConf.style.display  = 'none';
    section.style.display = '';

    const itens = [
        imp.cupom?.nome    ? { tipo: 'cupom',    nome: imp.cupom.nome,    info: `ESC/POS ${imp.cupom.papel || '80'}mm`           } : null,
        imp.etiqueta?.nome ? { tipo: 'etiqueta', nome: imp.etiqueta.nome, info: `ZPL ${imp.etiqueta.largura}×${imp.etiqueta.altura}mm` } : null,
        imp.inkjet?.nome   ? { tipo: 'inkjet',   nome: imp.inkjet.nome,   info: imp.inkjet.papel || 'A4'                          } : null,
    ].filter(Boolean);

    list.innerHTML = itens.map(i => `
        <div class="printer-item">
            <span class="tipo-badge ${i.tipo}">${i.tipo}</span>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${i.nome}</div>
                <div style="font-size:.7rem;color:#94a3b8">${i.info}</div>
            </div>
        </div>`).join('');
}

function abrirDashboard() {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        const url = tabs[0]?.url || '';
        if (url.includes('desffrut') || url.includes('localhost')) {
            // Já está na página — envia para o fragmento de hardware
            chrome.tabs.sendMessage(tabs[0].id, { action: 'open_hardware' });
        } else {
            chrome.tabs.create({ url: 'https://desffrut.com.br/dashboard' });
        }
    });
    window.close();
}

// Inicia ao abrir o popup
verificarHost();
