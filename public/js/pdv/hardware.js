/**
 * Desffrut — Hardware Local (Fase 5 — Revisão 2.0)
 * Módulo: DesffrHardware
 *
 * Substitui QZ Tray por Native Messaging (extensão Chrome/Edge + host Python).
 *
 * IMPRESSORAS SUPORTADAS:
 *   - cupom   : Térmica 80mm/58mm (ESC/POS) — cupons de venda, sangria, fechamento
 *   - etiqueta: Térmica de etiquetas (ZPL)   — Tomate MKV-006, envios marketplace
 *   - inkjet  : Qualquer impressora Windows  — relatórios, PDF
 *
 * OUTROS PERIFÉRICOS (mantidos):
 *   - Balança  : Web Serial API (Toledo/Filizola RS-232)
 *   - Scanner  : BarcodeDetector API (câmera) + USB HID (plug & play)
 *
 * REQUISITOS:
 *   - Navegador: Google Chrome ou Microsoft Edge
 *   - Extensão Desffrut instalada (extension/chrome/ ou extension/edge/)
 *   - Host nativo instalado via install_chrome.bat ou install_edge.bat
 *
 * FALLBACK (localhost/XAMPP):
 *   - Em desenvolvimento local, usa API PHP (/api/v1/print) — sem extensão.
 */

'use strict';

const DesffrHardware = (function () {

    // ── Canal de comunicação com a extensão ──────────────────────────────────
    const HW_REQUEST  = 'DESFFRUT_HW_REQUEST';
    const HW_RESPONSE = 'DESFFRUT_HW_RESPONSE';
    const EXT_TIMEOUT = 12000; // ms

    // ── Chaves de configuração no localStorage ───────────────────────────────
    const CONFIG_KEY = 'desffrut_hw_v2';

    // ── Colunas por largura de papel (cupom) ────────────────────────────────
    const COLS = { '80': 48, '58': 32 };

    // ── Estado interno (balança) ─────────────────────────────────────────────
    let _serialPort   = null;
    let _serialReader = null;

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 1 — CONFIGURAÇÃO
    // ════════════════════════════════════════════════════════════════════════

    function _cfgDefault() {
        return {
            impressoras: {
                cupom:    { nome: '', papel: '80' },
                etiqueta: { nome: '', largura: 80, altura: 40, protocolo: 'tspl' },
                inkjet:   { nome: '', papel: 'A4' },
            },
            balanca: { porta: null },
        };
    }

    function getConfig() {
        try {
            const raw = localStorage.getItem(CONFIG_KEY);
            if (!raw) return _cfgDefault();
            const parsed = JSON.parse(raw);
            // Merge profundo para garantir campos novos
            const def = _cfgDefault();
            return {
                impressoras: {
                    cupom:    { ...def.impressoras.cupom,    ...(parsed.impressoras?.cupom    || {}) },
                    etiqueta: { ...def.impressoras.etiqueta, ...(parsed.impressoras?.etiqueta || {}) },
                    inkjet:   { ...def.impressoras.inkjet,   ...(parsed.impressoras?.inkjet   || {}) },
                },
                balanca: { ...def.balanca, ...(parsed.balanca || {}) },
            };
        } catch (_) { return _cfgDefault(); }
    }

    function setConfig(patch = {}) {
        const cfg = getConfig();
        if (patch.impressoras) {
            for (const tipo of ['cupom', 'etiqueta', 'inkjet']) {
                if (patch.impressoras[tipo]) {
                    cfg.impressoras[tipo] = { ...cfg.impressoras[tipo], ...patch.impressoras[tipo] };
                }
            }
        }
        if (patch.balanca) cfg.balanca = { ...cfg.balanca, ...patch.balanca };
        localStorage.setItem(CONFIG_KEY, JSON.stringify(cfg));
    }

    /** Atalho — retorna config de uma impressora por tipo. */
    function cfgImpressora(tipo) {
        return getConfig().impressoras[tipo] || {};
    }

    function getCols() {
        const papel = cfgImpressora('cupom').papel || '80';
        return COLS[papel] || 48;
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 2 — CANAL NATIVE MESSAGING (postMessage ↔ extensão)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Detecta se está rodando em localhost (XAMPP).
     */
    function isLocal() {
        const h = location.hostname;
        return h === 'localhost' || h === '127.0.0.1' || h === '::1';
    }

    /**
     * Núcleo do canal Native Messaging: envia uma mensagem para a extensão
     * via postMessage e aguarda a resposta DESFFRUT_HW_RESPONSE.
     * Funciona em localhost E em produção — a extensão é sempre o caminho.
     *
     * @param {string} cmd     — 'print' | 'list_printers' | 'status'
     * @param {Object} payload — dados do comando
     * @returns {Promise<Object>}
     */
    function _enviarExtensao(cmd, payload = {}) {
        return new Promise((resolve, reject) => {
            const msgId = `hw_${Date.now()}_${Math.random().toString(36).slice(2)}`;
            let timer;

            const handler = (event) => {
                if (event.source !== window) return;
                if (!event.data || event.data.type !== HW_RESPONSE) return;
                if (event.data.msgId !== msgId) return;
                clearTimeout(timer);
                window.removeEventListener('message', handler);
                if (event.data.ok) {
                    resolve(event.data.data || {});
                } else {
                    reject(new Error(event.data.erro || 'Erro desconhecido na extensão.'));
                }
            };

            window.addEventListener('message', handler);

            timer = setTimeout(() => {
                window.removeEventListener('message', handler);
                reject(new Error(
                    'A extensão Desffrut não respondeu.\n' +
                    'Verifique se a extensão está instalada e o host nativo ativo.\n' +
                    'Dashboard → Hardware → Instruções de instalação.'
                ));
            }, EXT_TIMEOUT);

            window.postMessage({ type: HW_REQUEST, msgId, cmd, payload }, '*');
        });
    }

    /**
     * Verifica se a extensão está instalada e respondendo.
     * Em localhost, ainda verifica — a extensão pode estar instalada.
     * @returns {Promise<boolean>}
     */
    async function extensaoInstalada() {
        try {
            const r = await Promise.race([
                _enviarExtensao('status'),
                new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 8000)),
            ]);
            return !!(r && r.ok !== false);
        } catch (_) {
            return false;
        }
    }

    // ── API PHP local (fallback XAMPP) ───────────────────────────────────────
    async function _apiPrint(acao, body = null) {
        // Detecta o basePath correto (suporta subdiretório como /desffrut.com/)
        const base = window.APP?.baseUrl
            || (window.location.origin + '/' + window.location.pathname.split('/')[1]);
        const url  = `${base}/api/v1/print?a=${acao}`;
        const opts = {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
        };
        if (body) {
            opts.method = 'POST';
            opts.body   = JSON.stringify({ a: acao, ...body });
        }
        const r    = await fetch(url, opts);
        const text = await r.text();
        if (!text) throw new Error(`Servidor retornou resposta vazia (HTTP ${r.status})`);
        let j;
        try { j = JSON.parse(text); } catch (_) {
            throw new Error(`Resposta inválida: ${text.slice(0, 120)}`);
        }
        if (!j.ok) throw new Error(j.erro || 'Erro na API de impressão');
        return j;
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 3 — PRIMITIVAS ESC/POS (Impressora de Cupom)
    // ════════════════════════════════════════════════════════════════════════

    const ESC = '\x1B';
    const GS  = '\x1D';

    const _esc = {
        init:    () => ESC + '@',
        cut:     () => GS  + 'V\x41\x00',
        center:  () => ESC + 'a\x01',
        left:    () => ESC + 'a\x00',
        bold:    (on) => ESC + 'E' + (on ? '\x01' : '\x00'),
        big:     (on) => GS  + '!' + (on ? '\x11' : '\x00'),
        feed:    (n = 3) => '\n'.repeat(n),
    };

    function _linha(char = '-', cols) {
        return char.repeat(cols || getCols()) + '\n';
    }

    function _par(esq, dir, cols) {
        const total = cols || getCols();
        const gap   = Math.max(1, total - esq.length - dir.length);
        return esq + ' '.repeat(gap) + dir + '\n';
    }

    function _centrado(s, cols) {
        const total = cols || getCols();
        const pad   = Math.max(0, Math.floor((total - s.length) / 2));
        return ' '.repeat(pad) + s + '\n';
    }

    // ── Monta string ESC/POS e envia via extensão ────────────────────────────
    async function _imprimirCupom(raw) {
        const cfg = cfgImpressora('cupom');
        if (!cfg.nome) {
            throw new Error(
                'Impressora de cupom não configurada.\n' +
                'Acesse Dashboard → Hardware para configurar.'
            );
        }
        return _enviarExtensao('print', {
            tipo: 'cupom',
            impressora: cfg.nome,
            raw,                   // string ESC/POS
            encoding: 'cp850',
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 4 — DOCUMENTOS DE CUPOM
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Imprime cupom não-fiscal de venda.
     */
    async function imprimirCupom(cupom, totais, pontos_ganhos = 0, nomesMap = {}) {
        const cols = getCols();
        const d    = [];

        d.push(_esc.init());
        d.push(_esc.center());
        d.push(_esc.big(true));
        d.push('DESFFRUT\n');
        d.push(_esc.big(false));
        d.push(_esc.bold(true));
        d.push('HORTIFRUTI SEMPRE FRESCO\n');
        d.push(_esc.bold(false));
        d.push(_linha('=', cols));

        d.push(_esc.left());
        d.push(`Data: ${new Date().toLocaleString('pt-BR')}\n`);
        if (cupom.caixa_id)    d.push(`Caixa: #${cupom.caixa_id}\n`);
        if (cupom.cliente_nome) d.push(`Cliente: ${cupom.cliente_nome}\n`);
        d.push(`UUID: ${(cupom.cupom_uuid || '').slice(0, 12)}…\n`);
        d.push(_linha('-', cols));

        (cupom.itens || []).forEach(item => {
            const info   = nomesMap[item.produto_id] || {};
            const nome   = (info.nome || `Produto #${item.produto_id}`).slice(0, cols - 1);
            const un     = info.unidade_medida || 'un';
            const preco  = _fmtBRL(item.preco_unitario_snapshot);
            const sub    = _fmtBRL(item.quantidade * item.preco_unitario_snapshot);
            const qtdStr = un === 'kg'
                ? `${parseFloat(item.quantidade).toFixed(3)} kg`
                : `${item.quantidade} ${un}`;
            d.push(nome + '\n');
            d.push(_par(`  ${qtdStr} × ${preco}`, sub, cols));
        });

        d.push(_linha('-', cols));

        if (totais.desconto > 0) {
            d.push(_par('Subtotal:', _fmtBRL(totais.bruto), cols));
            d.push(_par('Desconto:', `-${_fmtBRL(totais.desconto)}`, cols));
        }
        d.push(_esc.bold(true));
        d.push(_esc.big(true));
        d.push(_par('TOTAL:', _fmtBRL(totais.liquido), cols));
        d.push(_esc.big(false));
        d.push(_esc.bold(false));
        d.push(_par('Pagamento:', (cupom.forma_pagamento || '').toUpperCase(), cols));

        if (cupom.cliente_id && pontos_ganhos > 0) {
            d.push(_linha('-', cols));
            d.push(_esc.center());
            d.push(_esc.bold(true));
            d.push(`+${pontos_ganhos} pontos creditados!\n`);
            d.push(_esc.bold(false));
            d.push(_esc.left());
        }

        d.push(_linha('=', cols));
        d.push(_esc.center());
        d.push('Obrigado pela preferencia!\n');
        d.push('desffrut.com.br\n');
        d.push(_esc.feed(3));
        d.push(_esc.cut());

        return _imprimirCupom(d.join(''));
    }

    /**
     * Imprime comprovante de sangria ou suprimento.
     */
    async function imprimirSangria(dados) {
        const cols = getCols();
        const tipo = dados.tipo === 'suprimento' ? 'SUPRIMENTO' : 'SANGRIA';
        const d    = [];

        d.push(_esc.init());
        d.push(_esc.center());
        d.push(_esc.bold(true));
        d.push('DESFFRUT - HORTIFRUTI\n');
        d.push(`COMPROVANTE DE ${tipo}\n`);
        d.push(_esc.bold(false));
        d.push(_linha('=', cols));
        d.push(_esc.left());
        d.push(`Data/Hora: ${new Date().toLocaleString('pt-BR')}\n`);
        d.push(`Caixa: #${dados.caixa_id || '-'}\n`);
        d.push(`Operador: ${dados.operador || '-'}\n`);
        d.push(_linha('-', cols));
        d.push(_esc.bold(true));
        d.push(_par(`${tipo}:`, _fmtBRL(dados.valor), cols));
        d.push(_esc.bold(false));
        if (dados.justificativa) d.push(`Motivo: ${dados.justificativa}\n`);
        d.push(_linha('=', cols));
        d.push(_esc.center());
        d.push('Assinatura: ____________________\n');
        d.push(_esc.feed(3));
        d.push(_esc.cut());

        return _imprimirCupom(d.join(''));
    }

    /**
     * Imprime resumo de fechamento de caixa.
     */
    async function imprimirFechamento(dados) {
        const cols = getCols();
        const d    = [];

        d.push(_esc.init());
        d.push(_esc.center());
        d.push(_esc.bold(true));
        d.push('DESFFRUT - HORTIFRUTI\n');
        d.push('FECHAMENTO DE CAIXA\n');
        d.push(_esc.bold(false));
        d.push(_linha('=', cols));
        d.push(_esc.left());
        d.push(`Data: ${new Date().toLocaleDateString('pt-BR')}\n`);
        d.push(`Turno: ${dados.turno || new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}\n`);
        d.push(`Operador: ${dados.operador || '-'}\n`);
        d.push(`Caixa: #${dados.caixa_id || '-'}\n`);
        d.push(_linha('-', cols));
        d.push(_par('Fundo de Troco:', _fmtBRL(dados.fundo_troco || 0), cols));
        d.push(_par('Total Vendas:', _fmtBRL(dados.total_vendas || 0), cols));
        d.push(_par('Qtd. Vendas:', String(dados.qtd_vendas || 0), cols));
        d.push(_par('(-) Sangrias:', _fmtBRL(dados.total_sangrias || 0), cols));
        d.push(_par('(+) Suprimentos:', _fmtBRL(dados.total_suprimentos || 0), cols));
        d.push(_linha('-', cols));
        d.push(_esc.bold(true));
        d.push(_esc.big(true));
        d.push(_par('SALDO FINAL:', _fmtBRL(dados.saldo_final || 0), cols));
        d.push(_esc.big(false));
        d.push(_esc.bold(false));
        d.push(_linha('=', cols));
        d.push(_esc.center());
        d.push('Assinatura: ____________________\n');
        d.push(_esc.feed(3));
        d.push(_esc.cut());

        return _imprimirCupom(d.join(''));
    }

    /**
     * Imprime página de teste para a impressora de cupom.
     */
    async function imprimirTeste(tipo = 'cupom') {
        const cfg = cfgImpressora(tipo);
        if (!cfg.nome) {
            throw new Error(`Impressora "${tipo}" não configurada. Acesse Dashboard → Hardware.`);
        }

        if (tipo === 'cupom') {
            const cols = getCols();
            const d    = [];
            d.push(_esc.init());
            d.push(_esc.center());
            d.push(_esc.bold(true));
            d.push('** TESTE DE IMPRESSAO **\n');
            d.push(_esc.bold(false));
            d.push(_linha('-', cols));
            d.push(_esc.left());
            d.push(`Impressora: ${cfg.nome}\n`);
            d.push(`Papel: ${cfg.papel}mm (${getCols()} colunas)\n`);
            d.push(`Desffrut v${window.APP?.version || '2.0'}\n`);
            d.push(`${new Date().toLocaleString('pt-BR')}\n`);
            d.push(_linha('=', cols));
            d.push(_esc.center());
            d.push('Extensao Native Messaging OK\n');
            d.push(_esc.feed(3));
            d.push(_esc.cut());
            return _imprimirCupom(d.join(''));
        }

        if (tipo === 'etiqueta') {
            return imprimirTesteEtiqueta();
        }

        if (tipo === 'inkjet') {
            return _enviarExtensao('print', {
                tipo: 'inkjet',
                impressora: cfg.nome,
                html: `<html><body style="font-family:Arial;padding:20px">
                    <h2>Desffrut - Teste de Impressao</h2>
                    <p>Impressora: ${cfg.nome}</p>
                    <p>Data: ${new Date().toLocaleString('pt-BR')}</p>
                    <p>Modulo Hardware v2.0 - Native Messaging OK</p>
                </body></html>`,
            });
        }

        throw new Error(`Tipo de impressora desconhecido: ${tipo}`);
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 5 — IMPRESSORA DE ETIQUETAS (Tomate MKV-006 — ZPL)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Gera etiqueta ZPL para envio (padrão Mercado Livre).
     * Tamanho padrão: 100×150mm
     *
     * @param {Object} dados
     * @param {string} dados.codigo        — código de barras / rastreio
     * @param {string} dados.destinatario  — nome do destinatário
     * @param {string} dados.endereco      — endereço de entrega
     * @param {string} dados.cidade_uf     — "Fortaleza/CE"
     * @param {string} dados.cep           — "60000-000"
     * @param {string} [dados.plataforma]  — "Mercado Livre", "Shopee", etc.
     * @param {string} [dados.pedido_id]   — ID do pedido
     * @param {number} [dados.largura=100] — largura em mm
     * @param {number} [dados.altura=150]  — altura em mm
     */
    // ════════════════════════════════════════════════════════════════════════
    // GERADOR TSPL (TSC Printer Language — Tomate MKV-006 e compatíveis)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Remove caracteres não suportados em strings TSPL.
     * TSPL usa " como delimitador — aspas devem ser removidas.
     */
    function _etqSanitize(str) {
        return String(str || '')
            .replace(/["\\]/g, '')
            .replace(/[àáâãä]/gi, 'a')
            .replace(/[èéêë]/gi, 'e')
            .replace(/[ìíîï]/gi, 'i')
            .replace(/[òóôõö]/gi, 'o')
            .replace(/[ùúûü]/gi, 'u')
            .replace(/[ç]/gi, 'c')
            .replace(/[ñ]/gi, 'n')
            .slice(0, 60);
    }

    /** Monta cabeçalho TSPL padrão (SIZE + GAP + CLS). */
    function _tsplHeader(L, H, gapMm = 3) {
        return [
            `SIZE ${L} mm, ${H} mm`,
            `GAP ${gapMm} mm, 0`,
            'DIRECTION 0',
            'CLS',
        ];
    }

    /** Envia TSPL para a impressora de etiqueta. */
    function _enviarTspl(tspl) {
        const cfg = cfgImpressora('etiqueta');
        return _enviarExtensao('print', {
            tipo:       'etiqueta',
            impressora: cfg.nome,
            protocolo:  'tspl',
            tspl,
        });
    }

    /**
     * Imprime etiqueta de envio (padrão Mercado Livre / marketplace).
     * Tamanho padrão: 100×150mm
     */
    async function imprimirEtiquetaEnvio(dados) {
        const cfg = cfgImpressora('etiqueta');
        if (!cfg.nome) throw new Error('Impressora de etiqueta não configurada. Acesse Dashboard → Hardware.');

        const L = dados.largura || cfg.largura || 100;
        const H = dados.altura  || cfg.altura  || 150;
        const W = L * 8;   // dots

        const linhas = [
            ..._tsplHeader(L, H),
            // Cabeçalho — plataforma
            `TEXT 30, 20, "4", 0, 1, 1, "${_etqSanitize(dados.plataforma || 'DESFFRUT')}"`,
            `LINE 0, 65, ${W}, 65, 3`,
            // Destinatário
            `TEXT 30, 80, "2", 0, 1, 1, "Para:"`,
            `TEXT 30, 108, "3", 0, 1, 1, "${_etqSanitize(dados.destinatario || '')}"`,
            `TEXT 30, 145, "2", 0, 1, 1, "${_etqSanitize(dados.endereco || '')}"`,
            `TEXT 30, 175, "2", 0, 1, 1, "${_etqSanitize(dados.cidade_uf || '')}  CEP: ${dados.cep || ''}"`,
            // Código de barras
            `BARCODE 80, 215, "128", 100, 1, 0, 2, 4, "${_etqSanitize(dados.codigo || '000000000000')}"`,
            // Rodapé
            `LINE 0, 375, ${W}, 375, 3`,
            `TEXT 30, 385, "2", 0, 1, 1, "Pedido: ${_etqSanitize(dados.pedido_id || '-')}"`,
            `TEXT 30, 415, "2", 0, 1, 1, "Desffrut - ${new Date().toLocaleDateString('pt-BR')}"`,
            'PRINT 1, 1',
        ];

        return _enviarTspl(linhas.join('\r\n') + '\r\n');
    }

    /**
     * Imprime etiqueta de preço para gôndola/prateleira.
     *
     * @param {Object} dados
     * @param {string} dados.nome         — nome do produto
     * @param {number} dados.preco        — preço de venda
     * @param {string} dados.unidade      — 'kg' | 'un' | 'cx'
     * @param {string} [dados.codigo]     — código interno (barcode)
     * @param {string} [dados.validade]   — data de validade
     * @param {number} [dados.quantidade] — qtd de etiquetas (padrão 1)
     */
    async function imprimirEtiquetaProduto(dados) {
        const cfg = cfgImpressora('etiqueta');
        if (!cfg.nome) throw new Error('Impressora de etiqueta não configurada.');

        const qtd   = dados.quantidade || 1;
        const L     = cfg.largura || 80;
        const H     = cfg.altura  || 40;
        const W     = L * 8;
        const hDots = H * 8;

        const precoFmt = `R$ ${parseFloat(dados.preco || 0).toFixed(2).replace('.', ',')}/${dados.unidade || 'un'}`;

        const linhas = [
            ..._tsplHeader(L, H),
            // Nome do produto
            `TEXT 20, 15, "3", 0, 1, 1, "${_etqSanitize(dados.nome || '')}"`,
            // Preço em destaque (fonte grande)
            `TEXT 20, 50, "5", 0, 1, 1, "${_etqSanitize(precoFmt)}"`,
            // Código de barras (lado direito, se informado)
            ...(dados.codigo ? [
                `BARCODE ${W - 220}, 15, "128", 80, 0, 0, 1, 2, "${_etqSanitize(dados.codigo)}"`,
            ] : []),
            // Validade
            ...(dados.validade ? [
                `TEXT 20, ${hDots - 55}, "2", 0, 1, 1, "Val: ${dados.validade}"`,
            ] : []),
            // Rodapé
            `TEXT 20, ${hDots - 30}, "1", 0, 1, 1, "Desffrut - Hortifruti"`,
            `PRINT ${qtd}, 1`,
        ];

        return _enviarTspl(linhas.join('\r\n') + '\r\n');
    }

    /** Etiqueta de teste — imprime layout de diagnóstico. */
    async function imprimirTesteEtiqueta() {
        const cfg = cfgImpressora('etiqueta');
        if (!cfg.nome) throw new Error('Impressora de etiqueta não configurada.');

        const L     = cfg.largura || 80;
        const H     = cfg.altura  || 40;
        const hDots = H * 8;

        // GAPDETECT calibra o sensor antes de imprimir (evita bips intermitentes)
        const linhas = [
            ..._tsplHeader(L, H),
            'GAPDETECT',
            `TEXT 20, 15, "4", 0, 1, 1, "TESTE ETIQUETA"`,
            `TEXT 20, 65, "2", 0, 1, 1, "Impressora: ${_etqSanitize(cfg.nome)}"`,
            `TEXT 20, 95, "2", 0, 1, 1, "Desffrut v2.0 - TSPL OK"`,
            `TEXT 20, 125, "2", 0, 1, 1, "${new Date().toLocaleString('pt-BR')}"`,
            `BARCODE 20, 160, "128", 70, 1, 0, 2, 3, "1234567890"`,
            `TEXT 20, ${hDots - 30}, "1", 0, 1, 1, "Extensao Native Messaging OK"`,
            'PRINT 1, 1',
        ];

        return _enviarTspl(linhas.join('\r\n') + '\r\n');
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 6 — LISTAGEM DE IMPRESSORAS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Lista impressoras instaladas no Windows.
     * Tenta via extensão primeiro (funciona em localhost E produção).
     * Fallback: API PHP (apenas localhost, se extensão não estiver instalada).
     *
     * @returns {Promise<string[]>}
     */
    async function listarImpressoras() {
        try {
            const r = await _enviarExtensao('list_printers');
            return r.impressoras || [];
        } catch (errExt) {
            // Fallback PHP — apenas em localhost quando extensão não está instalada
            if (isLocal()) {
                try {
                    const r = await _apiPrint('listar');
                    return r.impressoras || [];
                } catch (_) { /* sem suporte no PHP → array vazio */ }
            }
            throw errExt; // propaga erro original em produção
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 7 — BALANÇA (Web Serial API — mantida)
    // ════════════════════════════════════════════════════════════════════════

    function serialDisponivel() {
        return !!(navigator.serial);
    }

    async function selecionarPortaBalanca() {
        if (!serialDisponivel()) {
            throw new Error(
                'Web Serial API não disponível.\n' +
                'Use Google Chrome ou Edge com flags habilitadas.'
            );
        }
        _serialPort = await navigator.serial.requestPort({ filters: [] });
        return _serialPort;
    }

    async function lerPesoBalanca(timeoutMs = 4000) {
        if (!serialDisponivel()) throw new Error('Web Serial API não disponível neste navegador.');
        if (!_serialPort) await selecionarPortaBalanca();
        if (!_serialPort.readable) {
            await _serialPort.open({ baudRate: 9600, dataBits: 8, stopBits: 1, parity: 'none' });
        }

        return new Promise(async (resolve, reject) => {
            let buffer = '';
            let resolved = false;

            const timer = setTimeout(() => {
                if (!resolved) {
                    resolved = true;
                    _cancelarLeituraSerial().catch(() => {});
                    reject(new Error('Tempo esgotado. Verifique se a balança está ligada e estável.'));
                }
            }, timeoutMs);

            try {
                _serialReader = _serialPort.readable.getReader();
                while (!resolved) {
                    const { value, done } = await _serialReader.read();
                    if (done) break;
                    buffer += new TextDecoder('ascii', { fatal: false }).decode(value);
                    const match = buffer.match(/[+\-]?\s*(\d{1,3}[.,]\d{1,3})\s*[kK]/);
                    if (match) {
                        clearTimeout(timer);
                        resolved = true;
                        const peso = parseFloat(match[1].replace(',', '.'));
                        await _cancelarLeituraSerial().catch(() => {});
                        resolve(peso > 0 ? peso : 0);
                        return;
                    }
                    if (buffer.length > 256) buffer = buffer.slice(-128);
                }
            } catch (err) {
                if (!resolved) {
                    clearTimeout(timer);
                    resolved = true;
                    await _cancelarLeituraSerial().catch(() => {});
                    reject(err);
                }
            }
        });
    }

    async function _cancelarLeituraSerial() {
        if (_serialReader) {
            try { await _serialReader.cancel(); } catch (_) {}
            try { _serialReader.releaseLock(); } catch (_) {}
            _serialReader = null;
        }
    }

    async function liberarPortaBalanca() {
        await _cancelarLeituraSerial();
        if (_serialPort) {
            try { await _serialPort.close(); } catch (_) {}
            _serialPort = null;
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 8 — SCANNER DE CÓDIGO DE BARRAS (mantido)
    // ════════════════════════════════════════════════════════════════════════

    function barcodeDisponivel() {
        return 'BarcodeDetector' in window;
    }

    async function iniciarScannerCamera(videoEl, onDetect) {
        if (!barcodeDisponivel()) {
            throw new Error('BarcodeDetector API não disponível. Use Chrome/Edge 83+.');
        }
        const _desejados  = ['ean_13','ean_8','upc_a','upc_e','code_128','code_39','qr_code','data_matrix','itf'];
        const _suportados = await BarcodeDetector.getSupportedFormats();
        const _formatos   = _desejados.filter(f => _suportados.includes(f));
        const detector    = new BarcodeDetector({ formats: _formatos.length ? _formatos : ['ean_13','qr_code'] });

        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } },
        });
        videoEl.srcObject = stream;
        await videoEl.play();

        let ativo = true;
        let ultimoCodigo = '';

        async function _frame() {
            if (!ativo || videoEl.readyState < 2) {
                if (ativo) requestAnimationFrame(_frame);
                return;
            }
            try {
                const resultados = await detector.detect(videoEl);
                if (resultados.length > 0) {
                    const r = resultados[0];
                    if (r.rawValue && r.rawValue !== ultimoCodigo) {
                        ultimoCodigo = r.rawValue;
                        _beepDeteccao();
                        onDetect(r.rawValue, r.format);
                        setTimeout(() => { ultimoCodigo = ''; }, 2000);
                    }
                }
            } catch (_) {}
            if (ativo) requestAnimationFrame(_frame);
        }

        requestAnimationFrame(_frame);
        return { stream, scanner: { parar() { ativo = false; } } };
    }

    function fecharScannerCamera(stream, scanner) {
        if (scanner) scanner.parar();
        if (stream)  stream.getTracks().forEach(t => t.stop());
    }

    function _beepDeteccao() {
        try {
            const ctx  = new (window.AudioContext || window.webkitAudioContext)();
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'square';
            osc.frequency.value = 1800;
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.12);
        } catch (_) {}
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEÇÃO 9 — STATUS / DIAGNÓSTICO
    // ════════════════════════════════════════════════════════════════════════

    async function status() {
        const cfg   = getConfig();
        const local = isLocal();

        const s = {
            modo:          'native_messaging', // sempre native messaging (extensão)
            local,
            serial_api:    serialDisponivel(),
            porta_serial:  !!_serialPort,
            barcode_api:   barcodeDisponivel(),
            camera_api:    !!(navigator.mediaDevices?.getUserMedia),
            impressoras:   cfg.impressoras,
            extensao:      null,
        };

        // Verifica extensão com retry — service worker pode demorar para acordar
        // (PyInstaller .exe leva alguns segundos na primeira execução).
        // Tenta até 3 vezes com 5s de timeout cada, com 2s de intervalo.
        s.extensao = await (async () => {
            for (let tentativa = 1; tentativa <= 3; tentativa++) {
                try {
                    const ext = await Promise.race([
                        _enviarExtensao('status'),
                        new Promise((_, r) => setTimeout(() => r(new Error('timeout')), 5000)),
                    ]);
                    return { instalada: true, ...ext };
                } catch (_) {
                    if (tentativa < 3) await new Promise(r => setTimeout(r, 2000));
                }
            }
            return { instalada: false, erro: 'Extensão não respondeu. Verifique se está instalada e o host nativo ativo.' };
        })();

        // Listagem de impressoras NÃO é feita automaticamente no status.
        // O usuário aciona manualmente via botão "Detectar" no painel.
        s.impressoras_lista = [];

        return s;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    function _fmtBRL(v) {
        return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2, maximumFractionDigits: 2,
        });
    }

    // ── API Pública ──────────────────────────────────────────────────────────
    return {
        // Ambiente
        isLocal,
        // Config
        getConfig,
        setConfig,
        cfgImpressora,
        getCols,
        // Extensão
        extensaoInstalada,
        // Impressora de Cupom (ESC/POS)
        imprimirCupom,
        imprimirSangria,
        imprimirFechamento,
        imprimirTeste,
        // Impressora de Etiqueta (ZPL — Tomate MKV-006)
        imprimirEtiquetaEnvio,
        imprimirEtiquetaProduto,
        imprimirTesteEtiqueta,
        // Utilitários de impressão
        listarImpressoras,
        // Balança (Web Serial API)
        serialDisponivel,
        selecionarPortaBalanca,
        lerPesoBalanca,
        liberarPortaBalanca,
        // Scanner câmera (BarcodeDetector)
        barcodeDisponivel,
        iniciarScannerCamera,
        fecharScannerCamera,
        // Diagnóstico
        status,
    };

})();
